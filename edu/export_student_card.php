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
edu_require_permission($pdo, 'can_edu_student_card', 'index.php');

$studentId = (int)($_GET['student_id'] ?? $_GET['id'] ?? 0);
if (!$studentId) { header('Location: index.php'); exit; }

$cardExtraColumns = [
    'gender', 'birth_place', 'enrollment_order', 'previous_education', 'school_finished',
    'promotion_orders', 'graduation_order', 'job_assignment', 'coursework_topic',
    'coursework_grade', 'state_exam_1', 'state_exam_2', 'state_exam_3'
];
$availableCardColumns = edu_student_card_available_columns($pdo);
$extraSelect = '';
foreach ($cardExtraColumns as $col) {
    if (in_array($col, $availableCardColumns, true)) {
        $extraSelect .= ", sc.`$col` AS card_$col";
    }
}

function edu_student_card_score_label_0_100($value): string
{
    $raw = trim((string)($value ?? ''));
    if ($raw === '') return '';

    $normalized = edu_normalize_score(str_replace(',', '.', $raw));
    if ($normalized !== null) {
        return edu_score_traditional($normalized);
    }

    return $raw;
}

$stmt = $pdo->prepare("\n    SELECT s.*, g.name AS group_name, g.course, g.year_started, g.curator_id, g.curriculum_id,\n           sp.code AS specialty_code, sp.name_ru AS specialty_name, sp.qualification AS specialty_qualification,\n           c.specialty_code AS curriculum_specialty_code, c.specialty_name AS curriculum_specialty_name,\n           c.qualification AS curriculum_qualification,\n           sc.notes AS card_notes $extraSelect\n    FROM edu_students s\n    LEFT JOIN edu_groups g ON g.id = s.group_id\n    LEFT JOIN edu_specialties sp ON sp.id = COALESCE(s.speciality_id, g.specialty_id)\n    LEFT JOIN edu_curricula c ON c.id = g.curriculum_id\n    LEFT JOIN edu_student_cards sc ON sc.student_id = s.id\n    WHERE s.id = ?\n");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { header('Location: index.php'); exit; }

$accessibleGroupIds = edu_accessible_group_ids($pdo, $userId, $role);
if (!$isAdmin && !$isDir && !($isTeacher && in_array((int)($student['group_id'] ?? 0), $accessibleGroupIds, true))) {
    header('Location: index.php');
    exit;
}

$fio = edu_full_name($student);
$curriculumId = (int)($student['curriculum_id'] ?? 0);
$groupName = (string)($student['group_name'] ?? '');
$specialtyCode = trim((string)($student['curriculum_specialty_code'] ?: $student['specialty_code'] ?: ''));
$specialtyName = trim((string)($student['curriculum_specialty_name'] ?: $student['specialty_name'] ?: ''));
$qualification = trim((string)($student['curriculum_qualification'] ?: $student['specialty_qualification'] ?: ''));
$yearStarted = (string)($student['year_started'] ?? '');
$birthDate = !empty($student['birth_date']) ? date('d.m.Y', strtotime($student['birth_date'])) : '';
$birthYear = !empty($student['birth_date']) ? date('Y', strtotime($student['birth_date'])) : '';
$enrollDateText = $yearStarted !== '' ? '«___1__»____сентября_____' . $yearStarted . '___г.' : '«____»________________20____г.';

$gradeLookup = edu_student_card_grade_lookup($pdo, $studentId);
$subjects = edu_student_card_subject_rows($pdo, $curriculumId, $gradeLookup);
$practices = edu_student_card_practice_rows($subjects);
$academicSubjects = array_values(array_filter($subjects, static fn($r) => !$r['is_practice']));
$courseworkGradeLabel = edu_student_card_score_label_0_100($student['card_coursework_grade'] ?? '');

$page1Left = '';
$page1Left .= card_p('III.    Практика', 'center', true, 20);
$page1Left .= card_p('а) учебная', 'center', true, 18);
$page1Left .= edu_student_card_practice_table($practices['study']);
$page1Left .= card_p('б) производственная', 'center', true, 18);
$page1Left .= edu_student_card_practice_table($practices['production']);
$page1Left .= card_p('IV.    Число пропущенных занятий', 'center', true, 20);
$page1Left .= edu_student_card_absence_table();
$page1Left .= card_p('V.    Курсовая работа', 'center', true, 20);
$page1Left .= card_p('Тема «' . card_val($student, 'card_coursework_topic', '________________________________________________') . '»', 'both', false, 18);
$page1Left .= card_p('Оценка ГКК __' . ($courseworkGradeLabel ?: '________________') . '__________________________________________', 'left', true, 18);
$page1Left .= card_p('VI.    Государственные экзамены', 'center', true, 20);
$page1Left .= edu_student_card_state_exam_table($student);
$page1Left .= card_p('Присвоена квалификация ' . ($qualification ?: '____________________________'), 'left', false, 18, true);

$page1Right = '';
$page1Right .= card_p('Министерство образования и науки Республики Казахстан', 'center', true, 18);
$page1Right .= card_p('Саранский гуманитарно-технический колледж им.Абая Кунанбаева', 'center', true, 18);
$page1Right .= card_p('город Сарань, проспект Ленина 14', 'center', true, 18);
$page1Right .= card_p('', 'center', false, 12);
$page1Right .= card_p('Л И Ч Н А Я    К А Р Т О Ч К А', 'center', true, 24);
$page1Right .= card_p('', 'center', false, 12);
$page1Right .= card_p('Ф.И.О. студента        ' . $fio, 'left', true, 18, true);
$page1Right .= card_p('Зачислен на ________1________курс в группу_______' . ($groupName ?: '____________') . '___________', 'left', true, 18);
$page1Right .= card_p('По специальности  ' . trim($specialtyCode . ' «' . $specialtyName . '»'), 'left', true, 18, true);
$page1Right .= card_p($enrollDateText, 'center', true, 18);
$page1Right .= card_p('', 'center', false, 24);
$page1Right .= card_p('I.    Общие сведения о студенте', 'center', true, 20);
$page1Right .= card_bullet('Ф.И.О. ' . $fio, true);
$page1Right .= card_gender_bullet(card_val($student, 'card_gender'));
$page1Right .= card_bullet('Год, месяц и число рождения: ' . ($birthDate ?: '________________'));
$page1Right .= card_bullet('Место рождения: ' . card_val($student, 'card_birth_place', '________________'));
$page1Right .= card_bullet('Национальность: ' . (($student['nationality'] ?? '') ?: '________________'));
$page1Right .= card_bullet('Время поступления в данное заведение, № и дата приказа о зачислении ' . card_val($student, 'card_enrollment_order', '________________'));
$page1Right .= card_bullet('Образование до поступления в данное учебное заведение.');
$page1Right .= card_bullet('Какой класс общеобразовательной школы окончил, и в каком году (указать наименование школы) ' . card_val($student, 'card_school_finished', '________________'));
$page1Right .= card_bullet('Приказ (№ и дата) о переводе на:');
$page1Right .= card_p(card_val($student, 'card_promotion_orders', "2 курс ____________________\n3 курс ____________________\n4 курс ____________________"), 'left', false, 18);
$page1Right .= card_bullet('о выпуске из учебного заведения ' . card_val($student, 'card_graduation_order', '________________________________'));
$page1Right .= card_bullet('Куда направлен на работу и на какую должность ' . card_val($student, 'card_job_assignment', '________________'));

$page2Left = '';
$page2Right = '';
[$leftSubjects, $rightSubjects] = edu_student_card_split_subjects($academicSubjects);
$page2Left .= card_p('II.    Оценка успеваемости и поведения', 'center', true, 20);
$page2Left .= edu_student_card_grades_table($leftSubjects, $gradeLookup, true);
$page2Right .= edu_student_card_grades_table($rightSubjects, $gradeLookup, false);

$body = '';
$body .= edu_student_card_two_column_page($page1Left, $page1Right);
$body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
$body .= edu_student_card_two_column_page($page2Left, $page2Right);

$tmp = tempnam(sys_get_temp_dir(), 'student_card_') . '.docx';
edu_docx_make($body, $tmp, true);
$name = edu_safe_filename('Личная карточка_' . $fio) . '.docx';
edu_send_file($tmp, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

function edu_student_card_available_columns(PDO $pdo): array
{
    try {
        $rows = $pdo->query('SHOW COLUMNS FROM edu_student_cards')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn($r) => $r['Field'], $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function card_val(array $row, string $key, string $fallback = ''): string
{
    $v = trim((string)($row[$key] ?? ''));
    return $v !== '' ? $v : $fallback;
}

function card_run(string $text, bool $bold = false, int $size = 18, bool $underline = false): string
{
    $props = '<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>'
        . ($bold ? '<w:b/><w:bCs/>' : '')
        . ($underline ? '<w:u w:val="single"/>' : '')
        . '</w:rPr>';
    $parts = preg_split('/\R/u', $text);
    $xml = '';
    foreach ($parts as $idx => $part) {
        if ($idx > 0) $xml .= '<w:br/>';
        $xml .= '<w:t xml:space="preserve">' . edu_xml($part) . '</w:t>';
    }
    return '<w:r>' . $props . $xml . '</w:r>';
}

function card_p(string $text = '', string $align = 'left', bool $bold = false, int $size = 18, bool $underline = false): string
{
    $jc = in_array($align, ['left','center','right','both'], true) ? $align : 'left';
    return '<w:p><w:pPr><w:jc w:val="' . $jc . '"/><w:spacing w:after="20"/></w:pPr>' . card_run($text, $bold, $size, $underline) . '</w:p>';
}

function card_bullet(string $text, bool $underline = false): string
{
    return '<w:p><w:pPr><w:ind w:left="360" w:hanging="180"/><w:spacing w:after="0"/></w:pPr>'
        . card_run('• ', false, 18) . card_run($text, true, 18, $underline) . '</w:p>';
}

function card_gender_bullet(string $gender = ''): string
{
    $normalized = trim($gender);
    $normalizedLower = function_exists('mb_strtolower') ? mb_strtolower($normalized, 'UTF-8') : strtolower($normalized);
    $isMale = $normalizedLower === 'мужской' || $normalizedLower === 'м' || $normalizedLower === 'male' || strpos($normalizedLower, 'муж') !== false;
    $isFemale = $normalizedLower === 'женский' || $normalizedLower === 'ж' || $normalizedLower === 'female' || strpos($normalizedLower, 'жен') !== false;

    return '<w:p><w:pPr><w:ind w:left="360" w:hanging="180"/><w:spacing w:after="0"/></w:pPr>'
        . card_run('• ', false, 18)
        . card_run('Пол: ', true, 18)
        . card_run('мужской', true, 18, $isMale)
        . card_run(', ', true, 18)
        . card_run('женский', true, 18, $isFemale)
        . card_run(' (подчеркнуть)', true, 18)
        . '</w:p>';
}

function card_cell_raw(string $content, int $width = 0, string $vAlign = 'top', string $borders = 'all', int $gridSpan = 1): string
{
    $tcPr = '<w:tcPr>';
    if ($width > 0) $tcPr .= '<w:tcW w:w="' . $width . '" w:type="dxa"/>';
    if ($gridSpan > 1) $tcPr .= '<w:gridSpan w:val="' . $gridSpan . '"/>';
    $tcPr .= '<w:vAlign w:val="' . $vAlign . '"/>';
    $tcPr .= '<w:tcMar><w:top w:w="40" w:type="dxa"/><w:left w:w="40" w:type="dxa"/><w:bottom w:w="40" w:type="dxa"/><w:right w:w="40" w:type="dxa"/></w:tcMar>';
    if ($borders === 'none') {
        $tcPr .= '<w:tcBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/></w:tcBorders>';
    } elseif ($borders === 'right-thick') {
        $tcPr .= '<w:tcBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="single" w:sz="12" w:space="0" w:color="000000"/></w:tcBorders>';
    } else {
        $tcPr .= '<w:tcBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/></w:tcBorders>';
    }
    $tcPr .= '</w:tcPr>';

    // В WordprocessingML ячейка таблицы должна завершаться абзацем.
    // Если последним элементом внутри ячейки оказывается вложенная таблица,
    // Microsoft Word часто открывает DOCX через восстановление. Поэтому
    // всегда добавляем технический пустой абзац в конец ячейки.
    $contentXml = $content ?: card_p('');
    return '<w:tc>' . $tcPr . $contentXml . '<w:p/>' . '</w:tc>';
}

function card_cell_text(string $text, bool $bold = false, int $size = 16, string $align = 'center', int $width = 0, int $gridSpan = 1): string
{
    return card_cell_raw(card_p($text, $align, $bold, $size), $width, 'center', 'all', $gridSpan);
}

function edu_student_card_two_column_page(string $leftXml, string $rightXml): string
{
    return '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr>'
        . '<w:tblGrid><w:gridCol w:w="7600"/><w:gridCol w:w="7600"/></w:tblGrid><w:tr>'
        . card_cell_raw($leftXml, 7600, 'top', 'right-thick')
        . card_cell_raw($rightXml, 7600, 'top', 'none')
        . '</w:tr></w:tbl>';
}

function edu_student_card_simple_table(array $rows, array $widths, int $fontSize = 15): string
{
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:insideH w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="000000"/></w:tblBorders></w:tblPr><w:tblGrid>';
    foreach ($widths as $w) $xml .= '<w:gridCol w:w="' . (int)$w . '"/>';
    $xml .= '</w:tblGrid>';
    foreach ($rows as $rIdx => $row) {
        $xml .= '<w:tr>';
        foreach ($row as $i => $cell) {
            $xml .= card_cell_text((string)$cell, $rIdx === 0, $fontSize, $rIdx === 0 ? 'center' : ($i === 1 ? 'left' : 'center'), $widths[$i] ?? 0);
        }
        $xml .= '</w:tr>';
    }
    return $xml . '</w:tbl>';
}

function edu_student_card_absence_table(): string
{
    $rows = [
        ['Причины', '1', '2', '3', '4', '5', '6', '7', '8', 'Всего'],
        ['Уважительные', '', '', '', '', '', '', '', '', '-'],
        ['Неуважительные', '', '', '', '', '', '', '', '', '-'],
    ];
    return edu_student_card_simple_table($rows, [1500, 450, 450, 450, 450, 450, 450, 450, 450, 700], 15);
}

function edu_student_card_state_exam_table(array $student): string
{
    $rows = [
        ['1.', card_val($student, 'card_state_exam_1')],
        ['2.', card_val($student, 'card_state_exam_2')],
        ['3.', card_val($student, 'card_state_exam_3')],
    ];
    return edu_student_card_simple_table($rows, [450, 6000], 15);
}

function edu_student_card_practice_table(array $items): string
{
    $rows = [['№', 'Наименование практики', 'Семестр', 'Продолжи-тельность', 'Оценка']];
    foreach (array_values($items) as $i => $p) {
        $rows[] = [
            (string)($i + 1) . '.',
            $p['name'],
            $p['semester'],
            $p['hours'],
            $p['mark'],
        ];
    }
    while (count($rows) < 4) $rows[] = [(string)count($rows) . '.', '', '', '', ''];
    return edu_student_card_simple_table($rows, [450, 3600, 900, 1100, 900], 15);
}

function edu_student_card_grade_lookup(PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare("\n        SELECT eg.id, eg.student_id,\n               COALESCE(eg.curriculum_module_id, gs.curriculum_module_id) AS module_id,\n               COALESCE(eg.curriculum_semester, gs.curriculum_semester) AS semester_num,\n               eg.grade, eg.passed, eg.absent,\n               m.index_code AS module_index_code,\n               m.name AS module_name,\n               m.component_name AS module_component_name,\n               sub.code AS subject_code,\n               sub.name_ru AS subject_name,\n               COALESCE(eg.updated_at, gs.updated_at, eg.created_at, gs.created_at) AS changed_at\n        FROM edu_grades eg\n        JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id\n        LEFT JOIN edu_curriculum_modules m ON m.id = COALESCE(eg.curriculum_module_id, gs.curriculum_module_id)\n        LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id\n        WHERE eg.student_id = ?\n        ORDER BY changed_at ASC, eg.id ASC\n    ");
    $stmt->execute([$studentId]);
    $lookup = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (edu_student_card_is_parent_section_code($row['module_index_code'] ?? $row['subject_code'] ?? '')) {
            continue;
        }
        $moduleId = (int)($row['module_id'] ?? 0);
        $semester = (int)($row['semester_num'] ?? 0);
        if ($semester <= 0 || $semester > 8) continue;

        $score = !empty($row['passed']) ? 100 : edu_student_card_normalize_grade_value($row['grade'] ?? null);
        if (!empty($row['absent'])) $score = null;

        $keys = [];
        if ($moduleId > 0) $keys[] = $moduleId . ':' . $semester;

        foreach (['module_index_code', 'subject_code'] as $field) {
            $norm = edu_student_card_norm_key((string)($row[$field] ?? ''));
            if ($norm !== '') $keys[] = 'idx:' . $norm . ':' . $semester;
        }
        foreach (['module_component_name', 'module_name', 'subject_name'] as $field) {
            $norm = edu_student_card_norm_key((string)($row[$field] ?? ''));
            if ($norm !== '') $keys[] = 'name:' . $norm . ':' . $semester;
        }

        foreach (array_unique($keys) as $key) {
            edu_student_card_store_score($lookup, $key, $score, (string)($row['changed_at'] ?? ''));
        }
    }
    return $lookup;
}

function edu_student_card_normalize_grade_value($value): ?int
{
    if ($value === null || $value === '') return null;
    $raw = trim((string)$value);
    if ($raw === '') return null;

    $upper = function_exists('mb_strtoupper') ? mb_strtoupper($raw, 'UTF-8') : strtoupper($raw);
    $letterMap = [
        'A' => 95, 'A-' => 90,
        'B+' => 85, 'B' => 80, 'B-' => 75,
        'C+' => 70, 'C' => 65, 'C-' => 60,
        'D+' => 55, 'D' => 50,
        'F' => 0,
    ];
    if (isset($letterMap[$upper])) return $letterMap[$upper];

    if (!is_numeric(str_replace(',', '.', $raw))) return null;
    $num = (float)str_replace(',', '.', $raw);

    // В старых таблицах могла храниться не 100-балльная оценка, а традиционная 2–5.
    // Для личной карточки её надо вывести как 2/3/4/5, поэтому переводим в
    // условный 100-балльный диапазон, используемый функцией edu_student_card_mark_by_score().
    if ($num > 0 && $num <= 5) {
        if ($num >= 5) return 95;
        if ($num >= 4) return 80;
        if ($num >= 3) return 60;
        return 0;
    }

    $score = (int)round($num);
    if ($score < 0 || $score > 100) return null;
    return $score;
}


function edu_student_card_norm_token($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    if (function_exists('mb_strtoupper')) return mb_strtoupper($value, 'UTF-8');
    return strtoupper($value);
}

function edu_student_card_is_parent_section_code($value): bool
{
    $code = edu_student_card_norm_token($value);
    return $code !== '' && (bool)preg_match('/^(ООМ|БМ|ПМ)(?:\d+\.?)?$/u', $code);
}

function edu_student_card_norm_key(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = str_replace('–', '-', $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function edu_student_card_store_score(array &$lookup, string $key, ?int $score, string $changedAt): void
{
    if ($key === '') return;
    $prev = $lookup[$key] ?? null;
    if ($prev === null || ($score !== null && $prev['score'] === null) || strcmp($changedAt, (string)($prev['changed_at'] ?? '')) >= 0) {
        $lookup[$key] = ['score' => $score, 'changed_at' => $changedAt];
    }
}

function edu_student_card_subject_score(array $lookup, array $subject, int $semester): ?int
{
    $keys = [];
    if (!empty($subject['id'])) $keys[] = (int)$subject['id'] . ':' . $semester;
    $idx = edu_student_card_norm_key((string)($subject['index_code'] ?? ''));
    if ($idx !== '') $keys[] = 'idx:' . $idx . ':' . $semester;

    foreach (['component_name', 'name', 'original_name', 'practice_name'] as $field) {
        $name = edu_student_card_norm_key((string)($subject[$field] ?? ''));
        if ($name !== '') $keys[] = 'name:' . $name . ':' . $semester;
    }

    foreach (array_unique($keys) as $key) {
        if (array_key_exists($key, $lookup)) return $lookup[$key]['score'];
    }
    return null;
}

function edu_student_card_subject_rows(PDO $pdo, int $curriculumId, array $gradeLookup): array
{
    if ($curriculumId <= 0) return [];
    $stmt = $pdo->prepare("\n        SELECT m.id, m.name, m.component_name, m.index_code, m.module_type, m.total_hours, m.sort_order,\n               m.parent_id, COALESCE(m.is_summary, 0) AS is_summary,\n               p.name AS parent_name, p.component_name AS parent_component_name, p.index_code AS parent_index_code, p.module_type AS parent_module_type,\n               gp.name AS grandparent_name, gp.component_name AS grandparent_component_name, gp.index_code AS grandparent_index_code, gp.module_type AS grandparent_module_type,\n               GROUP_CONCAT(DISTINCT d.semester_num ORDER BY d.semester_num SEPARATOR ',') AS semesters,\n               GROUP_CONCAT(CONCAT(d.semester_num, ':', d.hours) ORDER BY d.semester_num SEPARATOR ',') AS semester_hours,\n               SUM(COALESCE(d.hours, 0)) AS distributed_hours\n        FROM edu_curriculum_modules m\n        LEFT JOIN edu_curriculum_modules p ON p.id = m.parent_id\n        LEFT JOIN edu_curriculum_modules gp ON gp.id = p.parent_id\n        LEFT JOIN edu_curriculum_distribution d ON d.module_id = m.id AND d.hours > 0\n        WHERE m.curriculum_id = ?\n          AND LOWER(TRIM(COALESCE(m.name, ''))) NOT LIKE 'итого%' AND LOWER(TRIM(COALESCE(m.component_name, ''))) NOT LIKE 'итого%'\n          AND (TRIM(COALESCE(m.name, '')) <> '' OR TRIM(COALESCE(m.component_name, '')) <> '')\n          AND (m.module_type IS NULL OR m.module_type <> 'ИТОГО')\n          AND (\n                COALESCE(m.is_summary, 0) = 0\n                OR d.hours > 0\n                OR LOWER(CONCAT_WS(' ', COALESCE(m.name, ''), COALESCE(m.component_name, ''), COALESCE(p.name, ''), COALESCE(p.component_name, ''), COALESCE(gp.name, ''), COALESCE(gp.component_name, ''), COALESCE(m.index_code, ''), COALESCE(p.index_code, ''), COALESCE(gp.index_code, ''))) REGEXP 'практик|производствен|преддиплом|профессиональн|(^|[[:space:]])уп[[:space:]]*[0-9]|(^|[[:space:]])пп[[:space:]]*[0-9]'\n          )\n          AND (COALESCE(m.total_hours, 0) > 0 OR COALESCE(m.credits, 0) > 0 OR d.hours > 0 OR COALESCE(m.exam_semester, '') <> '' OR COALESCE(m.credit_semester, '') <> '')\n        GROUP BY m.id\n        ORDER BY m.sort_order, m.id\n    ");
    $stmt->execute([$curriculumId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (edu_student_card_is_parent_section_code($r['index_code'] ?? '')) {
            continue;
        }
        $semesters = [];
        $semesterHours = [];
        foreach (explode(',', (string)($r['semester_hours'] ?? '')) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || strpos($chunk, ':') === false) continue;
            [$semRaw, $hoursRaw] = explode(':', $chunk, 2);
            $n = (int)$semRaw;
            $hours = (float)str_replace(',', '.', (string)$hoursRaw);
            if ($n >= 1 && $n <= 8 && $hours > 0) {
                $semesters[$n] = true;
                $semesterHours[$n] = ($semesterHours[$n] ?? 0) + $hours;
            }
        }
        if (!$semesters) {
            foreach (explode(',', (string)($r['semesters'] ?? '')) as $sem) {
                $n = (int)$sem;
                if ($n >= 1 && $n <= 8) $semesters[$n] = true;
            }
        }
        if (!$semesters) {
            for ($i = 1; $i <= 8; $i++) {
                if (isset($gradeLookup[((int)$r['id']) . ':' . $i])) $semesters[$i] = true;
            }
        }
        ksort($semesterHours);
        $name = trim((string)$r['name']);
        $componentName = trim((string)($r['component_name'] ?? ''));
        $indexCode = trim((string)($r['index_code'] ?? ''));
        $moduleType = trim((string)($r['module_type'] ?? ''));
        $parentName = trim((string)($r['parent_name'] ?? ''));
        $parentComponentName = trim((string)($r['parent_component_name'] ?? ''));
        $parentIndexCode = trim((string)($r['parent_index_code'] ?? ''));
        $parentModuleType = trim((string)($r['parent_module_type'] ?? ''));
        $grandparentName = trim((string)($r['grandparent_name'] ?? ''));
        $grandparentComponentName = trim((string)($r['grandparent_component_name'] ?? ''));
        $grandparentIndexCode = trim((string)($r['grandparent_index_code'] ?? ''));
        $grandparentModuleType = trim((string)($r['grandparent_module_type'] ?? ''));

        $indexUpper = function_exists('mb_strtoupper') ? mb_strtoupper($indexCode, 'UTF-8') : strtoupper($indexCode);
        $parentIndexUpper = function_exists('mb_strtoupper') ? mb_strtoupper($parentIndexCode, 'UTF-8') : strtoupper($parentIndexCode);
        $grandparentIndexUpper = function_exists('mb_strtoupper') ? mb_strtoupper($grandparentIndexCode, 'UTF-8') : strtoupper($grandparentIndexCode);
        $typeUpper = function_exists('mb_strtoupper') ? mb_strtoupper($moduleType, 'UTF-8') : strtoupper($moduleType);
        $parentTypeUpper = function_exists('mb_strtoupper') ? mb_strtoupper($parentModuleType, 'UTF-8') : strtoupper($parentModuleType);
        $grandparentTypeUpper = function_exists('mb_strtoupper') ? mb_strtoupper($grandparentModuleType, 'UTF-8') : strtoupper($grandparentModuleType);

        // Практики в личной карточке определяем строго по названию строки
        // или её родителя. Нельзя считать практикой любую строку со словом
        // "производственные": из-за этого в раздел попадали результаты обучения
        // вроде "Моделировать производственные...". Для производственной части
        // нужны именно фразы "Производственная практика" или "Преддипломная практика".
        $practiceCandidates = [
            ['name' => $componentName, 'source' => 'self_component'],
            ['name' => $name, 'source' => 'self'],
            ['name' => $parentComponentName, 'source' => 'parent_component'],
            ['name' => $parentName, 'source' => 'parent'],
            ['name' => $grandparentComponentName, 'source' => 'grandparent_component'],
            ['name' => $grandparentName, 'source' => 'grandparent'],
        ];

        $isPractice = false;
        $isProductionPractice = false;
        $practiceName = $name;

        foreach ($practiceCandidates as $candidate) {
            $candidateName = trim((string)$candidate['name']);
            if ($candidateName === '') continue;

            $candidateLower = function_exists('mb_strtolower')
                ? mb_strtolower($candidateName, 'UTF-8')
                : strtolower($candidateName);

            $isProductionTitle = preg_match('/производственн\w*\s+практик\w*/ui', $candidateLower) === 1;
            $isPrediplomaTitle = preg_match('/преддипломн\w*\s+практик\w*/ui', $candidateLower) === 1;
            $isStudyTitle = preg_match('/практик\w*/ui', $candidateLower) === 1
                && !$isProductionTitle
                && !$isPrediplomaTitle;

            if (!$isStudyTitle && !$isProductionTitle && !$isPrediplomaTitle) {
                continue;
            }

            // Приоритет: если у самой строки нормальное название практики, берём его.
            // Если у самой строки только результат обучения, но родитель называется
            // "Производственная практика..." / "Преддипломная практика...", берём
            // название родителя и часы дочерней строки.
            $selfLower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            $selfIsPracticeTitle = preg_match('/практик\w*/ui', $selfLower) === 1;
            if (in_array($candidate['source'], ['self', 'self_component'], true) || !$selfIsPracticeTitle) {
                $isPractice = true;
                $isProductionPractice = $isProductionTitle || $isPrediplomaTitle;
                $practiceName = $candidateName;
                break;
            }
        }

        $rows[] = [
            'id' => (int)$r['id'],
            'name' => $componentName !== '' ? $componentName : $name,
            'original_name' => $name,
            'component_name' => $componentName,
            'practice_name' => $practiceName,
            'index_code' => $indexCode,
            'module_type' => $moduleType,
            'total_hours' => (int)($r['total_hours'] ?? 0),
            'semesters' => array_keys($semesters),
            'semester_hours' => $semesterHours,
            'is_practice' => $isPractice,
            'is_production_practice' => $isProductionPractice,
        ];
    }
    return $rows;
}

function edu_student_card_practice_rows(array $subjects): array
{
    $result = ['study' => [], 'production' => []];
    $aggregate = ['study' => [], 'production' => []];
    foreach ($subjects as $s) {
        if (empty($s['is_practice'])) continue;

        // Для личной карточки практики выводятся только по фактическому наличию часов
        // в edu_curriculum_distribution. Оценки или служебные поля контроля не должны
        // подставлять практику в 1 семестр, если часов в этом семестре нет.
        $semesterHours = $s['semester_hours'] ?? [];
        if (!$semesterHours) continue;

        foreach ($semesterHours as $sem => $hours) {
            $sem = (int)$sem;
            $hours = (float)$hours;
            if ($sem < 1 || $sem > 8 || $hours <= 0) continue;

            $name = trim((string)($s['practice_name'] ?? $s['name'] ?? ''));
            if ($name === '') continue;
            $category = !empty($s['is_production_practice']) ? 'production' : 'study';
            $key = edu_student_card_norm_key($name) . ':' . $sem;
            $mark = edu_student_card_mark_by_score(edu_student_card_subject_score($GLOBALS['gradeLookup'], $s, $sem));

            if (!isset($aggregate[$category][$key])) {
                $aggregate[$category][$key] = [
                    'name' => $name,
                    'semester' => (string)$sem,
                    'hours_value' => 0.0,
                    'mark' => $mark,
                ];
            }
            $aggregate[$category][$key]['hours_value'] += $hours;
            if ($aggregate[$category][$key]['mark'] === '' && $mark !== '') {
                $aggregate[$category][$key]['mark'] = $mark;
            }
        }
    }

    foreach (['study', 'production'] as $category) {
        foreach ($aggregate[$category] as $item) {
            $hours = (float)$item['hours_value'];
            $item['hours'] = abs($hours - round($hours)) < 0.0001
                ? (string)(int)round($hours)
                : rtrim(rtrim(str_replace('.', ',', (string)$hours), '0'), ',');
            unset($item['hours_value']);
            $result[$category][] = $item;
        }
        usort($result[$category], static function ($a, $b) {
            $semCmp = (int)$a['semester'] <=> (int)$b['semester'];
            if ($semCmp !== 0) return $semCmp;
            return strcmp((string)$a['name'], (string)$b['name']);
        });
    }
    return $result;
}

function edu_student_card_mark_by_score(?int $score): string
{
    if ($score === null) return '';
    if ($score >= 90) return '5';
    if ($score >= 70) return '4';
    if ($score >= 50) return '3';
    return '2';
}

function edu_student_card_split_subjects(array $subjects): array
{
    $half = (int)ceil(count($subjects) / 2);
    return [array_slice($subjects, 0, $half), array_slice($subjects, $half)];
}

function edu_student_card_grades_table(array $subjects, array $lookup, bool $withTitleHeader): string
{
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:insideH w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="000000"/></w:tblBorders></w:tblPr>'
        . '<w:tblGrid><w:gridCol w:w="4100"/><w:gridCol w:w="350"/><w:gridCol w:w="350"/><w:gridCol w:w="350"/><w:gridCol w:w="350"/><w:gridCol w:w="350"/><w:gridCol w:w="350"/><w:gridCol w:w="350"/><w:gridCol w:w="350"/></w:tblGrid>';
    if ($withTitleHeader) {
        $xml .= '<w:tr>' . card_cell_text('Наименование учебных предметов', true, 14, 'center', 4100, 1)
            . card_cell_text('1 курс', true, 14, 'center', 700, 2)
            . card_cell_text('2 курс', true, 14, 'center', 700, 2)
            . card_cell_text('3 курс', true, 14, 'center', 700, 2)
            . card_cell_text('4 курс', true, 14, 'center', 700, 2)
            . '</w:tr>';
    } else {
        $xml .= '<w:tr>' . card_cell_text('Наименование учебных предметов', true, 14, 'center', 4100, 1)
            . card_cell_text('1 курс', true, 14, 'center', 700, 2)
            . card_cell_text('2 курс', true, 14, 'center', 700, 2)
            . card_cell_text('3 курс', true, 14, 'center', 700, 2)
            . card_cell_text('4 курс', true, 14, 'center', 700, 2)
            . '</w:tr>';
    }
    $xml .= '<w:tr>' . card_cell_text('', true, 14, 'center', 4100);
    for ($i = 1; $i <= 8; $i++) $xml .= card_cell_text((string)$i, true, 14, 'center', 350);
    $xml .= '</w:tr>';
    foreach ($subjects as $s) {
        $xml .= '<w:tr>' . card_cell_text($s['name'], false, 14, 'left', 4100);
        for ($sem = 1; $sem <= 8; $sem++) {
            $score = edu_student_card_subject_score($lookup, $s, $sem);
            $mark = edu_student_card_mark_by_score($score);
            $xml .= card_cell_text($mark, false, 14, 'center', 350);
        }
        $xml .= '</w:tr>';
    }
    for ($i = count($subjects); $i < 28; $i++) {
        $xml .= '<w:tr>' . card_cell_text('', false, 14, 'left', 4100);
        for ($sem = 1; $sem <= 8; $sem++) $xml .= card_cell_text('', false, 14, 'center', 350);
        $xml .= '</w:tr>';
    }
    return $xml . '</w:tbl>';
}
