<?php
// views/teacher_assignments/actions/export_tests.php

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

// Параметры
$yearStart = (int)($_GET['academic_year'] ?? date('Y'));
$yearLabel = $yearStart . '/' . ($yearStart + 1);

$filterPccId = null;
$whereClauseGroups = '';

$stmt = $pdo->prepare("
    SELECT
        g.name           AS group_name,
        g.year_started,
        d.semester_num,
        m.index_code,
        m.name           AS module_name,
        m.control_work,
        d.hours          AS total_hours,
        c.specialty_name
    FROM edu_groups g
    JOIN edu_curricula c ON c.id = g.curriculum_id
    JOIN edu_curriculum_modules m ON m.curriculum_id = c.id
    JOIN edu_curriculum_distribution d ON d.module_id = m.id
    WHERE g.year_started <= :year_start1
      AND (g.year_started + c.duration_years) > :year_start2
      AND m.control_work > 0
      AND m.is_summary = 0
      {$whereClauseGroups}
    ORDER BY g.name, d.semester_num, m.sort_order, m.index_code
");

$params = [':year_start1' => $yearStart, ':year_start2' => $yearStart];
if ($filterPccId) {
    $params[':pcc_head_id'] = $filterPccId;
}
$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    http_response_code(404);
    exit('Нет контрольных работ для экспорта за ' . htmlspecialchars($yearLabel));
}

// Группировка: Группа → Семестр → Предметы
$byGroup = [];

foreach ($rows as $row) {
    $groupName = $row['group_name'];
    $sem = (int)$row['semester_num'];

    if (!isset($byGroup[$groupName])) {
        $byGroup[$groupName] = [
            'name'       => $groupName,
            'year_start' => (int)$row['year_started'],
            'specialty'  => $row['specialty_name'] ?? '',
            'semesters'  => []
        ];
    }

    if (!isset($byGroup[$groupName]['semesters'][$sem])) {
        $byGroup[$groupName]['semesters'][$sem] = [];
    }

    $byGroup[$groupName]['semesters'][$sem][] = [
        'index_code'    => $row['index_code'],
        'module_name'   => $row['module_name'],
        'control_work'  => (float)$row['control_work'],
        'total_hours'=> (float)$row['total_hours']
    ];
}

//  Создание Excel
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$sheetIndex = 0;

foreach ($byGroup as $group) {
    $sheet = $spreadsheet->createSheet($sheetIndex++);
    
    // Название листа = название группы
    $sheetTitle = mb_substr($group['name'], 0, 31);
    $sheetTitle = preg_replace('/[\\\\\/\?\*\[\]:]/u', '', $sheetTitle);
    $sheet->setTitle($sheetTitle ?: 'Группа' . $sheetIndex);

    // Шапка листа
    $sheet->mergeCells('A1:D1');
    $sheet->setCellValue('A1', 'Контрольные работы — ' . $group['name']);
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);

    $sheet->setCellValue('A2', 'Специальность: ' . $group['specialty']);
    $sheet->mergeCells('A2:D2');

    $rowNum = 4;

    // Сортируем семестры по порядку
    ksort($group['semesters']);

    foreach ($group['semesters'] as $sem => $modules) {
        // Заголовок семестра
        $sheet->mergeCells("A{$rowNum}:D{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", "{$sem} семестр");
        $sheet->getStyle("A{$rowNum}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E40AF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']]
        ]);
        $rowNum++;

        // Подзаголовок таблицы
        $sheet->setCellValue("A{$rowNum}", '№');
        $sheet->setCellValue("B{$rowNum}", 'Индекс');
        $sheet->setCellValue("C{$rowNum}", 'Дисциплина');
        $sheet->setCellValue("D{$rowNum}", 'Кол-во работ');


        $sheet->getStyle("A{$rowNum}:D{$rowNum}")->applyFromArray([
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        $rowNum++;

        $semTotal = 0;
        foreach ($modules as $i => $m) {
            $sheet->setCellValue("A{$rowNum}", $i + 1);
            $sheet->setCellValue("B{$rowNum}", $m['index_code']);
            $sheet->setCellValue("C{$rowNum}", $m['module_name']);
            $sheet->setCellValue("D{$rowNum}", $m['control_work']);

            $semTotal += $m['control_work'];
            $rowNum++;
        }

        // Итого по семестру
        $sheet->setCellValue("C{$rowNum}", 'Итого по семестру:');
        $sheet->setCellValue("D{$rowNum}", $semTotal);
        $sheet->getStyle("C{$rowNum}:D{$rowNum}")->getFont()->setBold(true);
        $rowNum += 2;
    }

    // Автоширина колонок
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(55);
    $sheet->getColumnDimension('D')->setWidth(16);

    $sheet->freezePane('A5');
}

// Вывод
$filename = 'Контрольные_работы_по_группам'  . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
