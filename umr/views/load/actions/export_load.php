<?php
// views/load/actions/export_load.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

// Проверка доступа
if (!$canLoadSummary && !$isPccHead && !$isMethodist) {
    http_response_code(403);
    echo "Доступ запрещён.";
    exit;
}

require_once BASE_PATH . '/../edu/vendor/autoload.php';
require_once BASE_PATH . '/models/baseModel.php';
require_once BASE_PATH . '/models/umr_load.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

//  Права 
$hasFullAccess = $isAdmin || $isMethodist || $isPccHead;

// Определяем чью нагрузку экспортируем 
$filterYear = (int)($_GET['academic_year'] ?? date('Y'));
$viewId     = $userId; // по умолчанию своя

if ($hasFullAccess && !empty($_GET['teacher_id'])) {
    $viewId = (int)$_GET['teacher_id'];
}

$yearLabel = $filterYear . '/' . ($filterYear + 1);

//  Данные
$model    = new umr_load($pdo);
$viewName = $model->getTeacherName($viewId) ?: 'Преподаватель';
$rows     = $model->getTeacherLoad($viewId, $filterYear);

// Группировка по группам
$byGroup = [];
foreach ($rows as $r) {
    $k = $r['group_name'];
    if (!isset($byGroup[$k])) {
        $byGroup[$k] = ['course' => $r['course_num'], 'rows' => []];
    }
    $byGroup[$k]['rows'][] = $r;
}

//  Создание Excel
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Нагрузка');

$font = 'Times New Roman';

// Шапка
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A1', 'Нагрузка преподавателя — ' . $yearLabel);
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['name' => $font, 'bold' => true, 'size' => 13],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

$sheet->mergeCells('A2:J2');
$sheet->setCellValue('A2', $viewName);
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['name' => $font, 'bold' => true, 'size' => 12],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

// Заголовки таблицы
$headerRow = 4;
$headers   = ['№', 'Индекс', 'Дисциплина', 'Тип', 'Всего часов', 'Теория', 'Практика', '1 Сем.', '2 Сем.', 'Итого за год'];

foreach ($headers as $i => $h) {
    $sheet->setCellValue(chr(65 + $i) . $headerRow, $h);
}

$sheet->getRowDimension($headerRow)->setRowHeight(38);
$sheet->getStyle("A{$headerRow}:J{$headerRow}")->applyFromArray([
    'font'      => ['name' => $font, 'bold' => true, 'size' => 10],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

// Данные
$dataRow  = $headerRow + 1;
$num      = 0;
$grandOdd = $grandEven = 0;

foreach ($byGroup as $gname => $grp) {
    // Строка-заголовок группы
    $sheet->mergeCells("A{$dataRow}:J{$dataRow}");
    $sheet->setCellValue("A{$dataRow}", $gname . ' — ' . $grp['course'] . ' курс');
    $sheet->getStyle("A{$dataRow}")->applyFromArray([
        'font'      => ['name' => $font, 'bold' => true, 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    $dataRow++;

    $groupOdd = $groupEven = 0;

    foreach ($grp['rows'] as $r) {
        $num++;
        $odd   = (int)($r['hours_odd']  ?? 0);
        $even  = (int)($r['hours_even'] ?? 0);
        $total = $odd + $even;

        $groupOdd  += $odd;
        $groupEven += $even;

        $sheet->setCellValue("A{$dataRow}", $num);
        $sheet->setCellValue("B{$dataRow}", $r['index_code']);
        $sheet->setCellValue("C{$dataRow}", $r['module_name']);
        $sheet->setCellValue("D{$dataRow}", $r['module_type']);
        $sheet->setCellValue("E{$dataRow}", $r['total_hours']    ?? '');
        $sheet->setCellValue("F{$dataRow}", $r['theory_hours']   ?? '');
        $sheet->setCellValue("G{$dataRow}", $r['practice_hours'] ?? '');
        $sheet->setCellValue("H{$dataRow}", $odd  ?: '');
        $sheet->setCellValue("I{$dataRow}", $even ?: '');
        $sheet->setCellValue("J{$dataRow}", $total ?: '');

        $sheet->getStyle("A{$dataRow}:J{$dataRow}")->applyFromArray([
            'font'    => ['name' => $font, 'size' => 10],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getStyle("A{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$dataRow}:J{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $dataRow++;
    }

    // Итого по группе
    $groupTotal = $groupOdd + $groupEven;
    $grandOdd  += $groupOdd;
    $grandEven += $groupEven;

    $sheet->mergeCells("A{$dataRow}:G{$dataRow}");
    $sheet->setCellValue("A{$dataRow}", 'Итого по группе:');
    $sheet->setCellValue("H{$dataRow}", $groupOdd);
    $sheet->setCellValue("I{$dataRow}", $groupEven);
    $sheet->setCellValue("J{$dataRow}", $groupTotal);
    $sheet->getStyle("A{$dataRow}:J{$dataRow}")->applyFromArray([
        'font'    => ['name' => $font, 'bold' => true, 'size' => 10],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    $sheet->getStyle("H{$dataRow}:J{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $dataRow++;
}

// Итого за год
$grandTotal = $grandOdd + $grandEven;
$sheet->mergeCells("A{$dataRow}:G{$dataRow}");
$sheet->setCellValue("A{$dataRow}", 'ИТОГО за ' . $yearLabel . ':');
$sheet->setCellValue("H{$dataRow}", $grandOdd);
$sheet->setCellValue("I{$dataRow}", $grandEven);
$sheet->setCellValue("J{$dataRow}", $grandTotal);
$sheet->getStyle("A{$dataRow}:J{$dataRow}")->applyFromArray([
    'font'    => ['name' => $font, 'bold' => true, 'size' => 11],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);
$sheet->getStyle("H{$dataRow}:J{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Ширины колонок
$sheet->getColumnDimension('A')->setWidth(6.5);
$sheet->getColumnDimension('B')->setWidth(11);
$sheet->getColumnDimension('C')->setWidth(44);
$sheet->getColumnDimension('D')->setWidth(9);
$sheet->getColumnDimension('E')->setWidth(9);
$sheet->getColumnDimension('F')->setWidth(9);
$sheet->getColumnDimension('G')->setWidth(9);
$sheet->getColumnDimension('H')->setWidth(9);
$sheet->getColumnDimension('I')->setWidth(9);
$sheet->getColumnDimension('J')->setWidth(12);

$sheet->freezePane("A5");

// Вывод файла
$filename = 'Нагрузка_' . preg_replace('/\s+/', '_', $viewName) . '_' . $yearLabel . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
