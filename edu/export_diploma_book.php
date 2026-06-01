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

$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'teacher', 'director'], true)) {
    header('Location: index.php');
    exit;
}

$studentId = (int)($_GET['student_id'] ?? $_GET['id'] ?? 0);
if (!$studentId) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("\n    SELECT s.*, g.name AS group_name, g.course, g.year_started,\n           sp.code AS specialty_code, sp.name_ru AS specialty_name, sp.qualification\n    FROM edu_students s\n    LEFT JOIN edu_groups g ON g.id = s.group_id\n    LEFT JOIN edu_specialties sp ON sp.id = COALESCE(s.speciality_id, g.specialty_id)\n    WHERE s.id = ?\n");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { header('Location: index.php'); exit; }

$grades = edu_fetch_student_grades($pdo, $studentId, true);
$fio = edu_full_name($student);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheetTitle = function_exists('mb_substr') ? mb_substr($student['surname'] ?: 'Студент', 0, 31) : substr($student['surname'] ?: 'Student', 0, 31);
$sheet->setTitle($sheetTitle);

$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'Выписка из ведомости успеваемости за время пребывания в колледже');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Ф.И.О.');
$sheet->setCellValue('B3', $fio);
$sheet->setCellValue('A4', 'Год рождения');
$sheet->setCellValue('B4', !empty($student['birth_date']) ? date('Y', strtotime($student['birth_date'])) : '');
$sheet->setCellValue('D4', 'Год поступления');
$sheet->setCellValue('E4', $student['year_started'] ?? '');
$sheet->setCellValue('A5', 'Группа');
$sheet->setCellValue('B5', $student['group_name'] ?? '');
$sheet->setCellValue('D5', 'Специальность');
$sheet->setCellValue('E5', trim(($student['specialty_code'] ?? '') . ' ' . ($student['specialty_name'] ?? '')));
$sheet->setCellValue('A6', 'Квалификация');
$sheet->setCellValue('B6', $student['qualification'] ?? '');

$headers = ['№','Наименование учебных дисциплин','Семестр','Количество часов','Балл','Буквенная','Цифровой эквивалент','Традиционная оценка'];
$sheet->fromArray($headers, null, 'A8');
$sheet->getStyle('A8:H8')->getFont()->setBold(true);
$sheet->getStyle('A8:H8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
$sheet->getStyle('A8:H8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

$row = 9;
foreach ($grades as $idx => $g) {
    $score = in_array($g['type'], ['credit', 'practice'], true) && !empty($g['passed']) ? 100 : edu_normalize_score($g['grade']);
    $scale = edu_score_scale($score);
    $sheet->fromArray([
        $idx + 1,
        trim(($g['subject_code'] ? $g['subject_code'] . '. ' : '') . ($g['subject_name'] ?? '')),
        $g['semester_num'] ?? '',
        $g['hours_total'] ?? '',
        !empty($g['absent']) ? 'н/я' : ($score === null ? '' : $score),
        !empty($g['absent']) ? '' : $scale['letter'],
        !empty($g['absent']) ? '' : $scale['gpa'],
        !empty($g['absent']) ? '' : $scale['traditional'],
    ], null, "A$row");
    $row++;
}

$sheet->setCellValue("A" . ($row + 2), 'Присвоена квалификация: ' . ($student['qualification'] ?? ''));
$sheet->mergeCells("A" . ($row + 2) . ':H' . ($row + 2));
$sheet->setCellValue("A" . ($row + 4), 'Дата формирования: ' . date('d.m.Y'));
$sheet->mergeCells("A" . ($row + 4) . ':H' . ($row + 4));

$lastDataRow = max(8, $row - 1);
$sheet->getStyle("A8:H$lastDataRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle("A9:H$lastDataRow")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
$sheet->getStyle("A9:A$lastDataRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("C9:G$lastDataRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$widths = ['A'=>6,'B'=>48,'C'=>10,'D'=>14,'E'=>10,'F'=>12,'G'=>16,'H'=>24];
foreach ($widths as $col => $width) $sheet->getColumnDimension($col)->setWidth($width);
$sheet->freezePane('A9');

$tmp = tempnam(sys_get_temp_dir(), 'diploma_book_') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($tmp);
$name = edu_safe_filename('Дипломная книга_' . $fio) . '.xlsx';
edu_send_file($tmp, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
