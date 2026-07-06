<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/export_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$role = edu_current_role();
$userId = edu_current_user_id();
$isAdmin = edu_is_admin();
$isDir = edu_is_director();
$isTeacher = edu_is_teacher();

if (!in_array($role, ['admin', 'teacher', 'director'], true)) {
    header('Location: index.php');
    exit;
}
edu_require_permission($pdo, 'can_edu_generate_sheets', 'groups.php');

$groupId = (int)($_GET['group_id'] ?? 0);
if (!$groupId) {
    header('Location: groups.php');
    exit;
}

$stmt = $pdo->prepare("\n    SELECT g.*, u.full_name AS curator_name, u.username AS curator_login,\n           sp.code AS specialty_code, sp.name_ru AS specialty_name, sp.name_kz AS specialty_name_kz, sp.qualification\n    FROM edu_groups g\n    LEFT JOIN users u ON u.id = g.curator_id\n    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id\n    WHERE g.id = ?\n");
$stmt->execute([$groupId]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$group) {
    header('Location: groups.php');
    exit;
}

if (!$isAdmin && !$isDir && !edu_user_can_access_group($pdo, $groupId, $userId, $role)) {
    header('Location: groups.php');
    exit;
}

$studentsStmt = $pdo->prepare("SELECT COUNT(*) FROM edu_students WHERE group_id = ?");
$studentsStmt->execute([$groupId]);
$studentsCount = (int)$studentsStmt->fetchColumn();

$planStmt = $pdo->prepare("\n    SELECT DISTINCT\n           sub.id AS subject_id, sub.code, sub.name_ru, sub.name_kz, sub.hours_total,\n           gs.type, sem.year_start, sem.year_end, sem.semester_num\n    FROM edu_grade_sheets gs\n    LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id\n    LEFT JOIN edu_semesters sem ON sem.id = gs.semester_id\n    WHERE gs.group_id = ?\n    ORDER BY sem.year_start, sem.semester_num, sub.code, sub.name_ru\n");
$planStmt->execute([$groupId]);
$planRows = $planStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$planRows) {
    $fallbackStmt = $pdo->query("\n        SELECT id AS subject_id, code, name_ru, name_kz, hours_total,\n               NULL AS type, NULL AS year_start, NULL AS year_end, NULL AS semester_num\n        FROM edu_subjects\n        ORDER BY code, name_ru\n    ");
    $planRows = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
}

$TYPE_LABELS = [
    'exam' => 'экзамен',
    'credit' => 'зачет',
    'coursework' => 'контрольная работа',
    'practice' => 'практика',
    'current' => 'текущий контроль',
];

$CONTROL_SHORT = [
    'exam' => 'Э',
    'credit' => 'З',
    'coursework' => 'КР',
    'practice' => 'П',
    'current' => 'ТК',
];

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('СВГТК Портал')
    ->setTitle('ОП ' . ($group['name'] ?? ''))
    ->setSubject('Образовательная программа ' . ($group['name'] ?? ''));

function op_border(string $color = 'FF000000'): array
{
    return [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => $color],
            ],
        ],
    ];
}

function op_title($sheet, string $range, int $size = 13): void
{
    $sheet->getStyle($range)->getFont()->setBold(true)->setSize($size);
    $sheet->getStyle($range)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
}

function op_header($sheet, string $range, string $fill = 'FFD9EAF7'): void
{
    $sheet->getStyle($range)->getFont()->setBold(true);
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
    $sheet->getStyle($range)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle($range)->applyFromArray(op_border());
}

function op_table($sheet, string $range): void
{
    $sheet->getStyle($range)->applyFromArray(op_border());
    $sheet->getStyle($range)->getAlignment()
        ->setVertical(Alignment::VERTICAL_TOP)
        ->setWrapText(true);
}

function op_set_widths($sheet, array $widths): void
{
    foreach ($widths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }
}

function op_control_label(?string $type, array $labels): string
{
    if ($type === null || $type === '') return '';
    return $labels[$type] ?? $type;
}

function op_credit_value($hours): string
{
    if ($hours === null || $hours === '' || !is_numeric($hours)) return '';
    $credits = (float)$hours / 24;
    if (abs($credits - round($credits)) < 0.01) return (string)(int)round($credits);
    return rtrim(rtrim(number_format($credits, 2, '.', ''), '0'), '.');
}

function op_group_plan_rows(array $planRows): array
{
    $grouped = [];
    foreach ($planRows as $row) {
        $key = (string)($row['subject_id'] ?? '') . '|' . (string)($row['code'] ?? '') . '|' . (string)($row['name_ru'] ?? '');
        if (!isset($grouped[$key])) {
            $grouped[$key] = $row;
            $grouped[$key]['controls'] = [];
            $grouped[$key]['semesters'] = [];
        }
        if (!empty($row['type'])) {
            $grouped[$key]['controls'][] = $row['type'];
        }
        if (!empty($row['semester_num'])) {
            $grouped[$key]['semesters'][] = (int)$row['semester_num'];
        }
    }

    foreach ($grouped as &$row) {
        $row['controls'] = array_values(array_unique($row['controls']));
        $row['semesters'] = array_values(array_unique($row['semesters']));
        sort($row['semesters']);
    }
    unset($row);

    return array_values($grouped);
}

$rows = op_group_plan_rows($planRows);
$specialty = trim(($group['specialty_code'] ?? '') . ' - ' . ($group['specialty_name'] ?? ''), " -\t\n\r\0\x0B");
$qualification = trim((string)($group['qualification'] ?? ''));
$curator = trim((string)($group['curator_name'] ?: ($group['curator_login'] ?? '')));
$programName = 'Образовательная программа ' . trim((string)($group['name'] ?? ''));

// ── 1. Паспорт ───────────────────────────────────────────────────────────
$passport = $spreadsheet->getActiveSheet();
$passport->setTitle('Паспорт');
$passport->mergeCells('A1:C1');
$passport->setCellValue('A1', '1. Паспорт образовательной программы');
$passport->fromArray([
    ['Код и наименование \nспециальности:', null, $specialty],
    ['Код и наименование \nквалификации (-ий):', null, $qualification],
    [null, null, null],
    ['Цель образовательной программы:', null, ''],
    ['Нормативно-правовое обеспечение в области образования', null, ''],
    ['Профессиональный стандарт (при наличии):', null, ''],
    ['Трудовая функция', null, ''],
    ['Навык', null, ''],
    ['Знания', null, ''],
    ['Умения', null, ''],
    ['Профессиональный стандарт WorldSkills (при наличии):', null, ''],
    ['Отличительные особенности образовательной программы:', null, 'Кредитно-модульная система'],
], null, 'A2');
$passport->setCellValue('C4', '');
op_title($passport, 'A1:C1', 13);
op_table($passport, 'A1:C13');
$passport->getStyle('A2:A13')->getFont()->setBold(true);
$passport->getStyle('A1:C13')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
op_set_widths($passport, ['A' => 36, 'B' => 4, 'C' => 90]);
$passport->getRowDimension(1)->setRowHeight(24);
foreach ([2,3,5,6,7,8,9,10,11,12,13] as $r) $passport->getRowDimension($r)->setRowHeight(34);

// ── 2. Перечень компетенций ─────────────────────────────────────────────
$competencies = $spreadsheet->createSheet();
$competencies->setTitle('Перечень компетенций');
$competencies->mergeCells('A1:C1');
$competencies->setCellValue('A1', '2. Перечень компетенций');
$competencies->fromArray(['Индекс компетенции', 'Наименование компетенции', 'Соответствие модулю'], null, 'A2');
op_title($competencies, 'A1:C1', 13);
op_header($competencies, 'A2:C2');
for ($r = 3; $r <= 21; $r++) {
    $competencies->setCellValue("A$r", '');
    $competencies->setCellValue("B$r", '');
    $competencies->setCellValue("C$r", '');
}
op_table($competencies, 'A1:C21');
op_set_widths($competencies, ['A' => 18, 'B' => 80, 'C' => 24]);
$competencies->getStyle('A1:C21')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

// ── 3. Содержание модулей, дисциплин ────────────────────────────────────
$content = $spreadsheet->createSheet();
$content->setTitle('Содержание модулей, дисциплин');
$content->mergeCells('A1:I1');
$content->setCellValue('A1', '3. Содержание модулей, дисциплин');
$contentHeaders = [
    '№',
    'Наименование модулей / дисциплин',
    'Краткое содержание',
    'Результаты обучения',
    'Кредиты',
    'Часы',
    'Компетенции',
    'Форма контроля',
    'Семестр'
];
$content->fromArray($contentHeaders, null, 'A2');
$row = 3;
foreach ($rows as $idx => $item) {
    $controls = array_map(fn($t) => op_control_label($t, $CONTROL_SHORT), $item['controls'] ?? []);
    $semesters = $item['semesters'] ?? [];
    $content->fromArray([
        $idx + 1,
        trim((string)(($item['code'] ?? '') . ' ' . ($item['name_ru'] ?? ''))),
        '',
        '',
        op_credit_value($item['hours_total'] ?? null),
        $item['hours_total'] ?? '',
        '',
        implode(', ', array_filter($controls)),
        implode(', ', $semesters),
    ], null, "A$row");
    $row++;
}
$lastContentRow = max(21, $row - 1);
for (; $row <= $lastContentRow; $row++) {
    $content->fromArray(['','','','','','','','',''], null, "A$row");
}
op_title($content, 'A1:I1', 13);
op_header($content, 'A2:I2');
op_table($content, "A1:I$lastContentRow");
op_set_widths($content, ['A'=>6,'B'=>34,'C'=>62,'D'=>62,'E'=>10,'F'=>10,'G'=>16,'H'=>16,'I'=>10]);
$content->getStyle("A1:I$lastContentRow")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
$content->freezePane('A3');

// ── 4. Учебный план ─────────────────────────────────────────────────────
$plan = $spreadsheet->createSheet();
$plan->setTitle('учебный план');
$plan->mergeCells('A1:AC1');
$plan->setCellValue('A1', 'Рабочий учебный план');
$plan->fromArray([
    ['индекс', null, 'Наименование модулей / дисциплин', 'Форма контроля', null, null, 'Объем учебного', null, null, null, null, 'Самостоятельная работа студента с педагогом', 'Самостоятельная работа студента', 'времени', null, 'Распределение по курсам и семестрам', null, null, null, null, null, null, null, null, 'всего', 'семестр', null, null, null],
    [null, null, null, 'экзамен (семестр)', 'зачет (семестр)', 'контрольная работа', 'ВСЕГО КРЕДИТОВ', 'ВСЕГО ЧАСОВ', 'в том', null, null, null, null, 'числе', null, '1 курс', null, '2 курс', null, '3 курс', null, '4 курс', null, null, null, null, null, null, null],
    [null, null, null, null, null, null, null, null, 'Теоретическое обучение', 'Лабораторно-практические работы', 'Курсовой проект/ работы', null, null, 'Производственное обучение/ Профессиональная практика', 'Индивидуальные', '1 семестр', '2 семестр', '3 семестр', '4 семестр', '5 семестр', '6 семестр', '7 семестр', '8 семестр', null, null, null, null, null, null],
    [1, null, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, null, 23, 24, null, null, null],
    [null, null, 'количество учебных недель', null, null, null, null, null, null, null, null, null, null, null, null, 16, 22, 16, 22, 16, 22, 16, 22, null, null, null, null, null, null],
    [null, null, 'итого в неделю', null, null, null, null, null, null, null, null, null, null, null, null, 36, 36, 36, 36, 36, 36, 36, 36, null, null, null, null, null, null],
], null, 'A2');
$plan->setCellValue('A8', '');
$plan->setCellValue('C8', 'Дисциплины и модули');
$startPlanRow = 9;
$row = $startPlanRow;
$totalHours = 0;
$totalCredits = 0.0;
foreach ($rows as $idx => $item) {
    $hours = is_numeric($item['hours_total'] ?? null) ? (int)$item['hours_total'] : null;
    $credits = ($hours !== null) ? ((float)$hours / 24) : null;
    if ($hours !== null) $totalHours += $hours;
    if ($credits !== null) $totalCredits += $credits;

    $examSem = '';
    $creditSem = '';
    $controlSem = '';
    foreach (($item['controls'] ?? []) as $type) {
        $semText = implode(',', $item['semesters'] ?? []);
        if ($type === 'exam') $examSem = $semText;
        elseif ($type === 'credit' || $type === 'practice') $creditSem = $semText;
        elseif ($type === 'coursework' || $type === 'current') $controlSem = $semText;
    }

    $line = array_fill(0, 29, null);
    $line[0] = $item['code'] ?? ($idx + 1);
    $line[2] = $item['name_ru'] ?? '';
    $line[3] = $examSem;
    $line[4] = $creditSem;
    $line[5] = $controlSem;
    $line[6] = $credits !== null ? op_credit_value($hours) : '';
    $line[7] = $hours ?? '';
    $line[8] = $hours ?? '';
    $line[24] = $hours ?? '';
    $line[25] = $hours ?? '';
    foreach (($item['semesters'] ?? []) as $sem) {
        if ($sem >= 1 && $sem <= 8 && $hours !== null) {
            $line[14 + $sem] = $hours;
        }
    }
    $plan->fromArray($line, null, "A$row");
    $row++;
}
$lastPlanRow = max($startPlanRow, $row - 1);
$plan->setCellValue("C" . ($lastPlanRow + 1), 'ИТОГО');
$plan->setCellValue("G" . ($lastPlanRow + 1), op_credit_value($totalHours));
$plan->setCellValue("H" . ($lastPlanRow + 1), $totalHours ?: '');
$plan->setCellValue("Y" . ($lastPlanRow + 1), $totalHours ?: '');
$plan->getStyle("A" . ($lastPlanRow + 1) . ':AC' . ($lastPlanRow + 1))->getFont()->setBold(true);

op_title($plan, 'A1:AC1', 13);
op_header($plan, 'A2:AC7');
op_header($plan, 'A8:AC8', 'FFEFEFEF');
op_table($plan, 'A1:AC' . ($lastPlanRow + 1));
$plan->getStyle('A1:AC' . ($lastPlanRow + 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
$plan->getStyle('A2:AC7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$plan->freezePane('A9');
for ($i = 1; $i <= 29; $i++) {
    $col = Coordinate::stringFromColumnIndex($i);
    $plan->getColumnDimension($col)->setWidth(in_array($i, [3], true) ? 38 : (in_array($i, [1,4,5,6,7,8,25,26], true) ? 10 : 8));
}

// ── 5. График учебного процесса ─────────────────────────────────────────
$schedule = $spreadsheet->createSheet();
$schedule->setTitle('график учебного процесса');
$schedule->mergeCells('N1:Z1');
$schedule->setCellValue('N1', 'График учебного процесса');
$schedule->fromArray([
    [null, 'Курсы', 'сентябрь', null, null, null, null, 'октябрь', null, null, null, 'ноябрь', null, null, null, 'декабрь', null, null, null, null, 'январь', null, null, null, null, 'февраль', null, null, null, 'март', null, null, null, null, 'апрель', null, null, null, 'май', null, null, null, 'июнь', null, null, null, 'июль', null, null, null, 'август', null, null, null],
    [null, 'недели', 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52],
    [null, 'I', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
    [null, 'II', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
    [null, 'III', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
    [null, 'IV', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
], null, 'A3');
$schedule->setCellValue('M10', 'условные обозначения');
$schedule->fromArray([
    ['ТО', null, 'теоретическое обучение', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'ДП', null, 'дипломное проектирование', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'ПА', null, 'промежуточная аттестация'],
    ['ПО', null, 'производственное обучение', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'ПС', null, 'полевые сборы', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'ИА', null, 'итоговая аттестация'],
    ['ПП', null, 'профессиональная практика', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'Пдн', null, 'праздничные дни', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'К', null, 'каникулы'],
], null, 'C11');
op_title($schedule, 'N1:Z1', 13);
op_header($schedule, 'B3:BB4');
op_table($schedule, 'B3:BB8');
$schedule->getStyle('B3:BB13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$schedule->getColumnDimension('B')->setWidth(10);
for ($i = 3; $i <= 54; $i++) $schedule->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(5);

// ── 6. Сводные данные ───────────────────────────────────────────────────
$summary = $spreadsheet->createSheet();
$summary->setTitle('сводные данные');
$summary->mergeCells('A1:K1');
$summary->setCellValue('A1', 'Сводные данные по бюджету времени');
$summary->fromArray([
    ['Курс', 'Теоретическое обучение', null, null, 'Промежуточная аттестация', 'Производственное обучение и профессиональная практика', 'Дипломное проектирование (если запланировано)', 'Итоговая аттестация', 'Праздничные дни', 'Каникулы', 'Всего недель в учебном году'],
    [null, 'недель', 'часов', 'кредитов', null, null, null, null, null, null, null],
], null, 'A3');
$courseHours = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
foreach ($rows as $item) {
    $hours = is_numeric($item['hours_total'] ?? null) ? (int)$item['hours_total'] : 0;
    $semesters = $item['semesters'] ?? [];
    if (!$semesters) {
        $course = (int)($group['course'] ?? 1);
        $course = max(1, min(4, $course));
        $courseHours[$course] += $hours;
        continue;
    }
    foreach ($semesters as $sem) {
        $course = (int)ceil(((int)$sem) / 2);
        $course = max(1, min(4, $course));
        $courseHours[$course] += $hours;
    }
}
$rowMap = [1 => 5, 2 => 7, 3 => 9, 4 => 11];
$courseNames = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];
foreach ($rowMap as $course => $excelRow) {
    $hours = $courseHours[$course] ?? 0;
    $summary->setCellValue("A$excelRow", $courseNames[$course]);
    $summary->setCellValue("C$excelRow", $hours ?: '');
    $summary->setCellValue("D$excelRow", $hours ? op_credit_value($hours) : '');
}
$summary->setCellValue('A13', 'Итого');
$summary->setCellValue('C13', array_sum($courseHours) ?: '');
$summary->setCellValue('D13', array_sum($courseHours) ? op_credit_value(array_sum($courseHours)) : '');
op_title($summary, 'A1:K1', 13);
op_header($summary, 'A3:K4');
op_table($summary, 'A3:K13');
$summary->getStyle('A13:K13')->getFont()->setBold(true);
$summary->getStyle('A1:K13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
op_set_widths($summary, ['A'=>12,'B'=>18,'C'=>12,'D'=>12,'E'=>22,'F'=>34,'G'=>28,'H'=>20,'I'=>16,'J'=>12,'K'=>22]);

$spreadsheet->setActiveSheetIndex(0);

$tmp = tempnam(sys_get_temp_dir(), 'op_') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($tmp);
$name = edu_safe_filename('ОП_' . ($group['name'] ?? 'group')) . '.xlsx';
edu_send_file($tmp, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
