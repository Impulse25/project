<?php
// views/curriculum_calendar/actions/export.php

define('BASE_PATH', dirname(__DIR__, 3));

// Авторизация
require_once BASE_PATH . '/partials/init.php';

// Проверка прав
if (!$canCurricula && !$isPccHead && !$isMethodist) {
    http_response_code(403);
    echo "Доступ запрещён. У вас нет прав для просмотра этого раздела.";
    exit;
}

// Подключение файлов
require_once BASE_PATH . '/../edu/vendor/autoload.php';
require_once BASE_PATH . '/models/baseModel.php';
require_once BASE_PATH . '/models/edu_curriculum_calendar.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$moduleCalendar = new edu_curriculum_calendar($pdo);

$filterYear    = (int)($_GET['academic_year'] ?? date('Y'));
$filterGroupId = (int)($_GET['group_id'] ?? 0);
$sortDir       = in_array($_GET['sort'] ?? 'asc', ['asc', 'desc'], true) ? $_GET['sort'] : 'asc';

function edu_curriculum_calendar_default_months(): array
{
    $ranges = [
        'сентябрь' => [1, 5], 'октябрь' => [6, 9], 'ноябрь' => [10, 13],
        'декабрь' => [14, 18], 'январь' => [19, 23], 'февраль' => [24, 27],
        'март' => [28, 32], 'апрель' => [33, 36], 'май' => [37, 40],
        'июнь' => [41, 44], 'июль' => [45, 48], 'август' => [49, 52],
    ];
    $map = [];
    foreach ($ranges as $month => [$from, $to]) {
        for ($w = $from; $w <= $to; $w++) $map[$w] = $month;
    }
    return $map;
}

function edu_graph_cell_span(?array $cell): int
{
    if (!$cell) return 1;
    $span = (int)($cell['span_weeks'] ?? 1);
    $value = trim((string)($cell['value_text'] ?? ''));
    if ($span <= 1 && preg_match('/(?:^|\s)(\d{1,2})(?:\s*)$/u', $value, $m)) {
        $n = (int)$m[1];
        if ($n > 1 && $n <= 52) $span = $n;
    }
    return max(1, $span);
}

$allGroupsForYear = $moduleCalendar->getActiveGroupsForYear($filterYear, 0, 'asc');
$activeGroups = $filterGroupId
    ? array_values(array_filter($allGroupsForYear, fn($g) => (int)$g['id'] === $filterGroupId))
    : $allGroupsForYear;

usort($activeGroups, function ($a, $b) use ($sortDir) {
    $cmp = strcmp($a['name'], $b['name']);
    return $sortDir === 'desc' ? -$cmp : $cmp;
});

$curriculumIds = array_values(array_unique(array_map(fn($g) => (int)$g['curriculum_id'], $activeGroups)));
$monthMap = $moduleCalendar->getMonthMapForCurricula($curriculumIds);
$legend   = $moduleCalendar->getLegendForCurricula($curriculumIds);

$nonEmptyMonths = array_filter(array_map('trim', $monthMap), static fn($v) => $v !== '');
if (count($nonEmptyMonths) < 10) {
    $monthMap = edu_curriculum_calendar_default_months();
}

$rows = [];
foreach ($activeGroups as $g) {
    $course = (int)$g['current_course'];
    $label  = edu_curriculum_calendar::courseLabel($course);
    if ($label === null) continue;
    $schedule = $moduleCalendar->getScheduleRows((int)$g['curriculum_id'], $label);
    $rows[] = ['group' => $g, 'course_label' => $label, 'schedule' => $schedule];
}

// Построение книги
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('График ' . $filterYear);

$fontName = 'Arial';
$spreadsheet->getDefaultStyle()->getFont()->setName($fontName)->setSize(9);

$headerFill = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'EEF2FF'],
];
$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
    ],
];

// Заголовок таблицы
$sheet->setCellValue('A1', 'Группа');
$sheet->mergeCells('A1:A2');
$sheet->setCellValue('B1', 'Курс');
$sheet->mergeCells('B1:B2');

$col = 3; // C = неделя 1
$w = 1;
$defaultMonths = edu_curriculum_calendar_default_months();
while ($w <= 52) {
    $month = trim($monthMap[$w] ?? '');
    if ($month === '') {
        $month = $defaultMonths[$w] ?? '—';
    }

    $span = 1;
    while (($w + $span) <= 52) {
        $nextMonth = trim($monthMap[$w + $span] ?? '');
        if ($nextMonth === '') {
            $nextMonth = $defaultMonths[$w + $span] ?? '';
        }
        if ($nextMonth !== $month) break;
        $span++;
    }

    $startColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $endColLetter   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $span - 1);
    $sheet->setCellValue($startColLetter . '1', $month);
    if ($span > 1) {
        $sheet->mergeCells("{$startColLetter}1:{$endColLetter}1");
    }

    for ($i = 0; $i < $span; $i++) {
        $weekColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $i);
        $sheet->setCellValue($weekColLetter . '2', $w + $i);
    }

    $col += $span;
    $w += $span;
}
$lastCol = $col - 1;
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);

$headerRange = "A1:{$lastColLetter}2";
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()->applyFromArray($headerFill);
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle($headerRange)->applyFromArray($thinBorder);

// Строки данных
$rowIdx = 3;
foreach ($rows as $r) {
    $g = $r['group'];
    $schedule = $r['schedule'];

    $sheet->setCellValue("A{$rowIdx}", $g['name']);
    $sheet->setCellValue("B{$rowIdx}", $r['course_label']);
    $sheet->getStyle("A{$rowIdx}")->getFont()->setBold(true);
    $sheet->getStyle("B{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    if (empty($schedule)) {
        $noteColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3);
        $sheet->setCellValue("{$noteColLetter}{$rowIdx}", 'График не импортирован для этого плана');
        $sheet->mergeCells("{$noteColLetter}{$rowIdx}:{$lastColLetter}{$rowIdx}");
        $sheet->getStyle("{$noteColLetter}{$rowIdx}")->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
        $rowIdx++;
        continue;
    }

    $col = 3;
    $w = 1;
    while ($w <= 52) {
        $cell = $schedule[$w] ?? null;
        $span = min(edu_graph_cell_span($cell), 52 - $w + 1);
        $value = trim((string)($cell['value_text'] ?? ''));

        $startColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValue("{$startColLetter}{$rowIdx}", $value);

        if ($span > 1) {
            $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $span - 1);
            $sheet->mergeCells("{$startColLetter}{$rowIdx}:{$endColLetter}{$rowIdx}");
        }

        if ($value !== '') {
            $sheet->getStyle("{$startColLetter}{$rowIdx}")->getFill()->applyFromArray([
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '60a5fa'],
            ]);
            $sheet->getStyle("{$startColLetter}{$rowIdx}")->getFont()->setBold(true);
        }

        $col += $span;
        $w += $span;
    }

    $rowIdx++;
}

$lastRow = $rowIdx - 1;
if ($lastRow >= 3) {
    $sheet->getStyle("A1:{$lastColLetter}{$lastRow}")->applyFromArray($thinBorder);
    $sheet->getStyle("A3:{$lastColLetter}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(7);
for ($c = 3; $c <= $lastCol; $c++) {
    $sheet->getColumnDimensionByColumn($c)->setWidth(5);
}
$sheet->freezePane('C3');

// Легенда на отдельном листе
if (!empty($legend)) {
    $legendSheet = $spreadsheet->createSheet();
    $legendSheet->setTitle('Легенда');
    $legendSheet->setCellValue('A1', 'Код');
    $legendSheet->setCellValue('B1', 'Описание');
    $legendSheet->getStyle('A1:B1')->getFont()->setBold(true);
    $legendSheet->getStyle('A1:B1')->getFill()->applyFromArray($headerFill);

    $lr = 2;
    foreach ($legend as $item) {
        $legendSheet->setCellValue("A{$lr}", $item['code']);
        $legendSheet->setCellValue("B{$lr}", $item['description']);
        $lr++;
    }
    $legendSheet->getColumnDimension('A')->setWidth(10);
    $legendSheet->getColumnDimension('B')->setWidth(40);
    $legendSheet->getStyle("A1:B" . ($lr - 1))->applyFromArray($thinBorder);
}

$spreadsheet->setActiveSheetIndex(0);

$fileName = 'График_учебного_плана_' . $filterYear . '_' . ($filterYear + 1) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
