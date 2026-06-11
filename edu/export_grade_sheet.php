<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/export_helpers.php';

$role   = edu_current_role();
$userId = edu_current_user_id();
$isAdmin = edu_is_admin();
$isDir = edu_is_director();
$isTeacher = edu_is_teacher();

if (!in_array($role, ['admin', 'teacher', 'director'], true)) {
    header('Location: grade_sheets.php');
    exit;
}
edu_require_permission($pdo, 'can_edu_generate_sheets', 'grade_sheets.php');

$sheetId = (int)($_GET['sheet_id'] ?? 0);
if (!$sheetId) { header('Location: grade_sheets.php'); exit; }

$stmt = $pdo->prepare("\n    SELECT gs.*,\n           g.name AS group_name, g.curator_id, g.course,\n           sp.code AS specialty_code, sp.name_ru AS specialty_name, sp.qualification,\n           COALESCE(NULLIF(m.index_code, ''), sub.code) AS subject_code,\n           COALESCE(NULLIF(m.name, ''), sub.name_ru) AS subject_name,\n           COALESCE(m.total_hours, sub.hours_total) AS hours_total,\n           sem.year_start, sem.year_end, sem.semester_num,\n           u.full_name AS teacher_name\n    FROM edu_grade_sheets gs\n    LEFT JOIN edu_groups g ON g.id = gs.group_id\n    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id\n    LEFT JOIN edu_curriculum_modules m ON m.id = gs.curriculum_module_id\n    LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id\n    LEFT JOIN edu_semesters sem ON sem.id = gs.semester_id\n    LEFT JOIN users u ON u.id = gs.teacher_id\n    WHERE gs.id = ?\n");
$stmt->execute([$sheetId]);
$sheet = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sheet) { header('Location: grade_sheets.php'); exit; }

$accessibleGroupIds = edu_accessible_group_ids($pdo, $userId, $role);
$isCurator = ((int)$sheet['teacher_id'] === $userId || in_array((int)$sheet['group_id'], $accessibleGroupIds, true));
if (!$isAdmin && !$isDir && !$isCurator) {
    header('Location: grade_sheets.php');
    exit;
}

$studentsStmt = $pdo->prepare("\n    SELECT eg.*, s.surname, s.name, s.patronymic, s.iin\n    FROM edu_grades eg\n    JOIN edu_students s ON s.id = eg.student_id\n    WHERE eg.grade_sheet_id = ?\n    ORDER BY s.surname, s.name, s.patronymic\n");
$studentsStmt->execute([$sheetId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$rows = [
    ['пп','Балл','Буквенная','Цифровой экв.','Фамилия имя отчество студента','Балл','Буквенная','Цифровой экв.','Балл','Буквенная','Цифровой экв.','Балл','Буквенная','Цифровой экв.','Подпись'],
];

$countA = $countB = $countC = $countF = 0;
foreach ($students as $i => $s) {
    $score = null;
    if (in_array($sheet['type'], ['credit', 'practice'], true)) {
        $score = !empty($s['passed']) ? 100 : (empty($s['absent']) ? edu_normalize_score($s['grade']) : null);
    } else {
        $score = edu_normalize_score($s['grade']);
    }

    if (!empty($s['absent'])) {
        $scoreText = 'н/я';
        $letter = '';
        $gpa = '';
    } else {
        $scale = edu_score_scale($score);
        $scoreText = $score === null ? '' : (string)$score;
        $letter = $scale['letter'];
        $gpa = $scale['gpa'];
        if ($score !== null) {
            if ($score >= 90) $countA++;
            elseif ($score >= 70) $countB++;
            elseif ($score >= 50) $countC++;
            else $countF++;
        }
    }

    $fio = edu_full_name($s);
    $rows[] = [
        (string)($i + 1), $scoreText, $letter, $gpa, $fio,
        $scoreText, $letter, $gpa,
        $scoreText, $letter, $gpa,
        $scoreText, $letter, $gpa, ''
    ];
}

$date = !empty($_GET['date']) ? strtotime($_GET['date']) : time();
$months = [1=>'января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
$dateLine = '« ' . date('d', $date) . ' » ' . $months[(int)date('n', $date)] . ' ' . date('Y', $date) . ' г.';

$subjectLine = trim(($sheet['subject_code'] ? $sheet['subject_code'] . '. ' : '') . ($sheet['subject_name'] ?? ''));
$body = '';
$body .= edu_docx_p('Министерство просвещения Республики Казахстан', 'center', false, 22);
$body .= edu_docx_p('КГКП «Саранский высший гуманитарно-технический колледж имени Абая Кунанбаева»', 'center', false, 22);
$body .= edu_docx_p('ЗАЧЕТНАЯ ВЕДОМОСТЬ', 'center', true, 28);
$body .= edu_docx_p('(для промежуточной аттестации обучающихся)', 'center', false, 20);
$body .= edu_docx_p('Индекс модуля, по дисциплине и (или) модулю: ' . $subjectLine, 'left', false, 22);
$body .= edu_docx_p('«' . ($sheet['course'] ?? '') . '» курса группы ' . ($sheet['group_name'] ?? '') . ', ' . ($sheet['semester_num'] ?? '') . ' семестр ' . ($sheet['year_start'] ?? '') . '-' . ($sheet['year_end'] ?? '') . ' уч. года', 'left', false, 22);
$body .= edu_docx_p('Специальность: ' . trim(($sheet['specialty_code'] ?? '') . ' ' . ($sheet['specialty_name'] ?? '')), 'left', false, 22);
$body .= edu_docx_p('Квалификация: ' . ($sheet['qualification'] ?? ''), 'left', false, 22);
$body .= edu_docx_p('Преподаватель: ' . ($sheet['teacher_name'] ?? ''), 'left', false, 22);
$body .= edu_docx_p($dateLine, 'right', false, 22);
$body .= edu_docx_table($rows, 1, 16);
$body .= edu_docx_p('Количество оценок: A/A- — ' . $countA . '; B+/B/B-/C+ — ' . $countB . '; C/C-/D+/D — ' . $countC . '; F — ' . $countF . '.', 'left', false, 20);
$body .= edu_docx_p('Подпись преподавателя _______________________', 'right', false, 22);

$tmp = tempnam(sys_get_temp_dir(), 'grade_sheet_') . '.docx';
edu_docx_make($body, $tmp, true);
$name = edu_safe_filename('Ведомость зачетная_' . ($sheet['group_name'] ?? '') . '_' . ($sheet['subject_code'] ?? $sheet['subject_name'] ?? '')) . '.docx';
edu_send_file($tmp, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
