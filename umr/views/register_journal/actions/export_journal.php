<?php
// views/register_journal/actions/export_journal.php
define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

if (!$canRegisterJournal && !$isPccHead && !$isMethodist) {
    http_response_code(403);
    exit('Нет доступа');
}

require_once BASE_PATH . '/../vendor/autoload.php';
require_once BASE_PATH . '/models/baseModel.php';
require_once BASE_PATH . '/models/umr_register_journal.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$moduleRegisterJournal = new umr_register_journal($pdo);

//  Параметр фильтра
$yearStart = (int)($_GET['academic_year'] ?? date('Y'));
$yearLabel = $yearStart . '/' . ($yearStart + 1);

$journalRows = $moduleRegisterJournal->getExportRows($yearStart);

if (empty($journalRows)) {
    http_response_code(404);
    exit('Нет записей в журнале за выбранный учебный год');
}

// ── Создание Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Журнал регистрации');

//  ЗАГОЛОВОК 
$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'Журнал регистрации рабочих программ');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

$sheet->setCellValue('A3', 'Учебный год:');
$sheet->setCellValue('B3', $yearLabel);

$sheet->getStyle('A3:B3')->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 12],
]);

//  ЗАГОЛОВКИ ТАБЛИЦЫ 
$headerRow = 5;

$headers = [
    'A' => '№',
    'B' => 'Дата регистрации',
    'C' => 'Тип',
    'D' => 'Группа',
    'E' => 'Сем.',
    'F' => 'Предмет',
    'G' => 'Преподаватель',
    'H' => 'Роспись',
];

foreach ($headers as $col => $text) {
    $sheet->setCellValue($col . $headerRow, $text);
}

$sheet->getRowDimension($headerRow)->setRowHeight(38);

$sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'bold' => true, 'size' => 11],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ],
]);

//  ДАННЫЕ 
$dataRow = $headerRow + 1;

foreach ($journalRows as $row) {
    $subject = trim(($row['index_code'] ? $row['index_code'] . '. ' : '') . $row['module_name']);

    $sheet->setCellValue("A{$dataRow}", $row['reg_num']);
    $sheet->setCellValue("B{$dataRow}", date('d.m.Y', strtotime($row['registered_at'])));
    $sheet->setCellValue("C{$dataRow}", $row['module_type']);
    $sheet->setCellValue("D{$dataRow}", $row['group_name']);
    $sheet->setCellValue("E{$dataRow}", $row['semester_num']);
    $sheet->setCellValue("F{$dataRow}", $subject);
    $sheet->setCellValue("G{$dataRow}", $row['teacher_name']);
    // H — пустой для росписи

    $sheet->getStyle("A{$dataRow}:H{$dataRow}")->applyFromArray([
        'font' => ['name' => 'Times New Roman', 'size' => 10],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
    ]);

    $sheet->getStyle("A{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("C{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("E{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $dataRow++;
}

//  ШИРИНЫ КОЛОНОК 
$sheet->getColumnDimension('A')->setWidth(7.5);
$sheet->getColumnDimension('B')->setWidth(16);
$sheet->getColumnDimension('C')->setWidth(11);
$sheet->getColumnDimension('D')->setWidth(16);
$sheet->getColumnDimension('E')->setWidth(8);
$sheet->getColumnDimension('F')->setWidth(46);
$sheet->getColumnDimension('G')->setWidth(30);
$sheet->getColumnDimension('H')->setWidth(15);

$sheet->freezePane("A" . ($headerRow + 1));

//  ВЫВОД 
$filename = 'Журнал_регистрации_' . $yearLabel . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;