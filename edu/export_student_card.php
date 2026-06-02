<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/export_helpers.php';

$role = edu_current_role();
$userId = edu_current_user_id();
$isAdmin = edu_is_admin();
$isDir = edu_is_director();
$isTeacher = edu_is_teacher();
if (!in_array($role, ['admin', 'teacher', 'director'], true)) {
    header('Location: index.php');
    exit;
}

$studentId = (int)($_GET['student_id'] ?? $_GET['id'] ?? 0);
if (!$studentId) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("\n    SELECT s.*, g.name AS group_name, g.course, g.year_started, g.curator_id,\n           sp.code AS specialty_code, sp.name_ru AS specialty_name, sp.qualification,\n           sc.notes\n    FROM edu_students s\n    LEFT JOIN edu_groups g ON g.id = s.group_id\n    LEFT JOIN edu_specialties sp ON sp.id = COALESCE(s.speciality_id, g.specialty_id)\n    LEFT JOIN edu_student_cards sc ON sc.student_id = s.id\n    WHERE s.id = ?\n");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { header('Location: index.php'); exit; }
$accessibleGroupIds = edu_accessible_group_ids($pdo, $userId, $role);
if (!$isAdmin && !$isDir && !($isTeacher && in_array((int)($student['group_id'] ?? 0), $accessibleGroupIds, true))) {
    header('Location: index.php');
    exit;
}

$grades = edu_fetch_student_grades($pdo, $studentId, false);
$semesters = [];
$absences = [];
foreach ($grades as $g) {
    $semKey = ($g['year_start'] ?? '') . '/' . ($g['year_end'] ?? '') . ' — ' . ($g['semester_num'] ?? '') . ' семестр';
    $semesters[$semKey][] = $g;
    if (!empty($g['absent'])) $absences[] = $g;
}

$fio = edu_full_name($student);
$birth = !empty($student['birth_date']) ? date('d.m.Y', strtotime($student['birth_date'])) : '';
$body = '';
$body .= edu_docx_p('ЛИЧНАЯ КАРТОЧКА', 'center', true, 30);
$body .= edu_docx_p('Ф.И.О. студента: ' . $fio, 'left', true, 24);
$body .= edu_docx_p('Группа: ' . ($student['group_name'] ?? '') . '     Курс: ' . ($student['course'] ?? ''), 'left', false, 22);
$body .= edu_docx_p('Специальность: ' . trim(($student['specialty_code'] ?? '') . ' ' . ($student['specialty_name'] ?? '')), 'left', false, 22);
$body .= edu_docx_p('Квалификация: ' . ($student['qualification'] ?? ''), 'left', false, 22);
$body .= edu_docx_p('ИИН: ' . ($student['iin'] ?? '') . '     Дата рождения: ' . $birth, 'left', false, 22);
$body .= edu_docx_p('Гражданство: ' . ($student['citizenship'] ?? '') . '     Национальность: ' . ($student['nationality'] ?? ''), 'left', false, 22);
$body .= edu_docx_p('Год поступления: ' . ($student['year_started'] ?? ''), 'left', false, 22);

$body .= edu_docx_p('I. Оценка успеваемости', 'left', true, 24);
if ($semesters) {
    foreach ($semesters as $semTitle => $items) {
        $body .= edu_docx_p($semTitle, 'left', true, 22);
        $rows = [['№','Дисциплина / модуль','Часы','Балл','Буквенная','Цифровой экв.','Традиционная','Статус']];
        foreach ($items as $i => $g) {
            $score = in_array($g['type'], ['credit', 'practice'], true) && !empty($g['passed']) ? 100 : edu_normalize_score($g['grade']);
            $scale = edu_score_scale($score);
            $rows[] = [
                (string)($i + 1),
                trim(($g['subject_code'] ? $g['subject_code'] . '. ' : '') . ($g['subject_name'] ?? '')),
                (string)($g['hours_total'] ?? ''),
                !empty($g['absent']) ? 'н/я' : ($score === null ? '' : (string)$score),
                !empty($g['absent']) ? '' : $scale['letter'],
                !empty($g['absent']) ? '' : $scale['gpa'],
                !empty($g['absent']) ? '' : $scale['traditional'],
                $g['status'] ?? ''
            ];
        }
        $body .= edu_docx_table($rows, 1, 17);
    }
} else {
    $body .= edu_docx_p('Оценок по ведомостям пока нет.', 'left', false, 22);
}

$body .= edu_docx_p('II. Пропуски занятий', 'left', true, 24);
if ($absences) {
    $rows = [['№','Семестр','Дисциплина','Дата','Примечание']];
    foreach ($absences as $i => $g) {
        $rows[] = [
            (string)($i + 1),
            ($g['year_start'] ?? '') . '/' . ($g['year_end'] ?? '') . ', ' . ($g['semester_num'] ?? '') . ' сем.',
            $g['subject_name'] ?? '',
            !empty($g['date']) ? date('d.m.Y', strtotime($g['date'])) : '',
            $g['comment'] ?? ''
        ];
    }
    $body .= edu_docx_table($rows, 1, 18);
} else {
    $body .= edu_docx_p('Отмеченных неявок нет.', 'left', false, 22);
}

if (!empty($student['notes'])) {
    $body .= edu_docx_p('III. Примечания', 'left', true, 24);
    $body .= edu_docx_p($student['notes'], 'left', false, 22);
}

$tmp = tempnam(sys_get_temp_dir(), 'student_card_') . '.docx';
edu_docx_make($body, $tmp, false);
$name = edu_safe_filename('Личная карточка_' . $fio) . '.docx';
edu_send_file($tmp, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
