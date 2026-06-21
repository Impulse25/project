<?php
// views/teacher_assignments/actions/export_tarification.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

if (!$canTeacherAssignments && !$isPccHead) {
  http_response_code(403);
  echo "Доступ запрещён. У вас нет прав для просмотра этого раздела.";
  exit;
}

require_once BASE_PATH . '/../edu/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Учебный год
$yearStart = (int)($_GET['academic_year'] ?? date('Y'));
$yearLabel = $yearStart . '/' . ($yearStart + 1);

// ПЦК
$filterPccId = null;
if (!empty($_GET['pcc_head_id']) && $_GET['pcc_head_id'] !== 'all') {
    $filterPccId = (int)$_GET['pcc_head_id'];
}

$whereClause = $filterPccId ? 'AND ta.pcc_head_id = :pcc_head_id' : '';

// Запрос
$stmt = $pdo->prepare("
    SELECT
        ta.id            AS assignment_id,
        ta.semester_num,
        ta.teacher_id,
        u.full_name      AS teacher_name,
        g.name           AS group_name,
        g.curriculum_id,
        m.index_code,
        m.name           AS module_name,
        sm.study_weeks,
        sm.weekly_hours,
        c.specialty_name
    FROM umr_teacher_assignments ta
    JOIN users u            ON u.id = ta.teacher_id
    JOIN edu_groups g       ON g.id = ta.group_id
    JOIN edu_curricula c    ON c.id = g.curriculum_id
    JOIN edu_curriculum_modules m ON m.id = ta.module_id
    LEFT JOIN edu_curriculum_semester_meta sm
           ON sm.curriculum_id = g.curriculum_id
          AND sm.semester_num  = ta.semester_num
    WHERE g.year_started <= :year_start1
        AND (g.year_started + c.duration_years) > :year_start2
    ORDER BY u.full_name, g.name, m.sort_order, m.id, ta.semester_num
");

$params = [':year_start1' => $yearStart, ':year_start2' => $yearStart];
if ($filterPccId) {
    $params[':pcc_head_id'] = $filterPccId;
}
$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


if (empty($rows)) {
    http_response_code(404);
    exit('Нет назначений для экспорта за ' . htmlspecialchars($yearLabel));
}

// Группировка по преподавателю , объединение семестров
$byTeacher = [];

foreach ($rows as $row) {
    $tid = (int)$row['teacher_id'];
    if (!isset($byTeacher[$tid])) {
        $byTeacher[$tid] = [
            'name'      => $row['teacher_name'],
            'specialty' => $row['specialty_name'] ?? '',
            'rows'      => [],
        ];
    }

    $key = $row['group_name'] . '|' . $row['index_code'] . '|' . $row['module_name'];

    if (!isset($byTeacher[$tid]['rows'][$key])) {
        $byTeacher[$tid]['rows'][$key] = [
            'group_name'  => $row['group_name'],
            'module_name' => trim(($row['index_code'] ? $row['index_code'] . '. ' : '') . $row['module_name']),
            'sem1_weeks'  => null,
            'sem1_hours'  => null,
            'sem2_weeks'  => null,
            'sem2_hours'  => null,
        ];
    }

    $entry = &$byTeacher[$tid]['rows'][$key];
    $sem = (int)$row['semester_num'];

    if ($sem % 2 === 1) { // 1-й семестр
        $entry['sem1_weeks'] = $row['study_weeks'] ? (float)$row['study_weeks'] : null;
        $entry['sem1_hours'] = $row['weekly_hours'] ? (float)$row['weekly_hours'] : null;
    } else { // 2-й семестр
        $entry['sem2_weeks'] = $row['study_weeks'] ? (float)$row['study_weeks'] : null;
        $entry['sem2_hours'] = $row['weekly_hours'] ? (float)$row['weekly_hours'] : null;
    }
}
unset($entry); // ВАЖНО: разрываем ссылку. Без этого следующий foreach по
                // $teacher['rows'] (использующий ту же переменную $entry)
                // перезапишет предпоследний элемент массива значением последнего —
                // отсюда дублирование одной строки и "пропажа" другой.

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$sheetIndex = 0;

foreach ($byTeacher as $teacher) {
    $sheet = $spreadsheet->createSheet($sheetIndex++);
    
    $nameParts  = explode(' ', trim($teacher['name']));
    $sheetTitle = mb_substr($nameParts[0] ?? $teacher['name'], 0, 31);
    $sheetTitle = preg_replace('/[\\\\\/\?\*\[\]:]/u', '', $sheetTitle);
    $sheet->setTitle($sheetTitle ?: ('Лист' . $sheetIndex));

    // ШАПКА
    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', 'Тарификационная ведомость');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['name' => 'Times New Roman', 'bold' => true, 'size' => 12],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // ФИО, Специальность, Образование, Стаж
    $sheet->mergeCells('A3:B3'); $sheet->setCellValue('A3', 'Фамилия, имя, отчество');
    $sheet->mergeCells('C3:G3'); $sheet->setCellValue('C3', $teacher['name']);

    $sheet->mergeCells('A4:B4'); $sheet->setCellValue('A4', 'Специальность');
    $sheet->mergeCells('C4:G4'); $sheet->setCellValue('C4', $teacher['specialty'] ?? '');

    $sheet->mergeCells('A5:B5'); $sheet->setCellValue('A5', 'Образование');
    $sheet->mergeCells('C5:G5'); $sheet->setCellValue('C5', 'высшее');

    $sheet->mergeCells('A6:B6'); $sheet->setCellValue('A6', 'Стаж работы');
    $sheet->mergeCells('C6:G6'); $sheet->setCellValue('C6', '');

    $sheet->getStyle('A3:G6')->applyFromArray([
        'font' => ['name' => 'Times New Roman', 'size' => 12],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    $sheet->getStyle('C3:G6')->applyFromArray([
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // ЗАГОЛОВОК ТАБЛИЦЫ
    $headerRow = 8;
    $sheet->setCellValue('A8', 'Группа');
    $sheet->setCellValue('B8', 'Предметы');
    $sheet->setCellValue('C8', 'Кол-во недель 1 сем');
    $sheet->setCellValue('D8', 'Недельные часы');
    $sheet->setCellValue('E8', 'Кол-во недель 2 сем');
    $sheet->setCellValue('F8', 'Недельные часы');
    $sheet->setCellValue('G8', 'Средняя годовая');

    $sheet->getRowDimension($headerRow)->setRowHeight(52.80);

    $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
        'font' => ['name' => 'Times New Roman', 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);

    $sheet->getStyle('A8')->getFont()->setSize(8);

    // ДАННЫЕ (с объединением)
    $dataRow = $headerRow + 1;
    $totalAnnual = 0;

    foreach ($teacher['rows'] as $entry) {
        $sheet->setCellValue("A{$dataRow}", $entry['group_name']);
        $sheet->setCellValue("B{$dataRow}", $entry['module_name']);

        // 1-й семестр
        if ($entry['sem1_weeks'] !== null) {
            $sheet->setCellValue("C{$dataRow}", $entry['sem1_weeks']);
        }
        if ($entry['sem1_hours'] !== null) {
            $sheet->setCellValue("D{$dataRow}", $entry['sem1_hours']);
        }

        // 2-й семестр
        if ($entry['sem2_weeks'] !== null) {
            $sheet->setCellValue("E{$dataRow}", $entry['sem2_weeks']);
        }
        if ($entry['sem2_hours'] !== null) {
            $sheet->setCellValue("F{$dataRow}", $entry['sem2_hours']);
        }

        // Средняя годовая сумма часов за оба семестра
        $annual = ($entry['sem1_hours'] ?? 0) + ($entry['sem2_hours'] ?? 0);
        if ($annual > 0) {
            $sheet->setCellValue("G{$dataRow}", $annual);
            $totalAnnual += $annual;
        }

        $sheet->getStyle("A{$dataRow}:G{$dataRow}")->applyFromArray([
            'font' => ['name' => 'Times New Roman', 'size' => 10],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $sheet->getRowDimension($dataRow)->setRowHeight(26.40);

        $dataRow++;
    }

    //  ИТОГО 
    $sheet->setCellValue("B{$dataRow}", 'Итого:');
    $sheet->setCellValue("G{$dataRow}", $totalAnnual);

    $sheet->getStyle("A{$dataRow}:G{$dataRow}")->applyFromArray([
        'font' => ['name' => 'Times New Roman', 'bold' => true, 'size' => 10],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);

    //  ПОДПИСИ 
    $sigRow = $dataRow + 2;

    $sigStyle = ['font' => ['name' => 'Times New Roman', 'size' => 12]];

    // Зав.кабинетом
    $sheet->mergeCells("A{$sigRow}:B{$sigRow}"); $sheet->setCellValue("A{$sigRow}", 'Зав.кабинетом');
    $sheet->mergeCells("C{$sigRow}:G{$sigRow}");
    $sheet->getStyle("C{$sigRow}:G{$sigRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$sigRow}")->applyFromArray($sigStyle);

    // Классное руководство
    $sigRow++;
    $sheet->mergeCells("A{$sigRow}:B{$sigRow}"); $sheet->setCellValue("A{$sigRow}", 'Классное руководство');
    $sheet->mergeCells("C{$sigRow}:G{$sigRow}");
    $sheet->getStyle("C{$sigRow}:G{$sigRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$sigRow}")->applyFromArray($sigStyle);

    // Зав.учебной частью
    $sigRow++;
    $sheet->mergeCells("A{$sigRow}:B{$sigRow}"); $sheet->setCellValue("A{$sigRow}", 'Зав.учебной частью');
    $sheet->mergeCells("C{$sigRow}:G{$sigRow}");
    $sheet->getStyle("C{$sigRow}:G{$sigRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$sigRow}")->applyFromArray($sigStyle);

    $sigRow += 2;

    // Преподаватель
    $sheet->mergeCells("A{$sigRow}:B{$sigRow}"); $sheet->setCellValue("A{$sigRow}", 'Преподаватель:');
    $sheet->mergeCells("C{$sigRow}:G{$sigRow}");
    $sheet->getStyle("C{$sigRow}:G{$sigRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$sigRow}")->applyFromArray($sigStyle);
    $sheet->getStyle("A{$sigRow}")->getFont()->setBold(true);

    // Председатель ПЦК
    $sigRow++;
    $sheet->mergeCells("A{$sigRow}:B{$sigRow}"); $sheet->setCellValue("A{$sigRow}", 'Председатель ПЦК:');
    $sheet->mergeCells("C{$sigRow}:G{$sigRow}");
    $sheet->getStyle("C{$sigRow}:G{$sigRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$sigRow}")->applyFromArray($sigStyle);
    $sheet->getStyle("A{$sigRow}")->getFont()->setBold(true);

    // ШИРИНА
    $sheet->getColumnDimension('A')->setWidth(8.2);
    $sheet->getColumnDimension('B')->setWidth(48.8);
    foreach (range('C', 'G') as $col) {
        $sheet->getColumnDimension($col)->setWidth(5.6);
    }

    $sheet->freezePane('A9');
}

// ВЫВОД 
$filename = 'Тарификация_' . $yearLabel . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
