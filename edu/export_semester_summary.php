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
    header('Location: grade_sheets.php');
    exit;
}

$groupId = (int)($_GET['group_id'] ?? 0);
$semesterId = (int)($_GET['semester_id'] ?? 0);
if (!$groupId || !$semesterId) { header('Location: grade_sheets.php'); exit; }

$groupStmt = $pdo->prepare("\n    SELECT g.*, sp.code AS specialty_code, sp.name_ru AS specialty_name, sp.qualification\n    FROM edu_groups g\n    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id\n    WHERE g.id = ?\n");
$groupStmt->execute([$groupId]);
$group = $groupStmt->fetch(PDO::FETCH_ASSOC);
if (!$group) { header('Location: grade_sheets.php'); exit; }
if (!$isAdmin && !$isDir && !edu_user_can_access_group($pdo, $groupId, $userId, $role)) {
    header('Location: grade_sheets.php');
    exit;
}

$semStmt = $pdo->prepare("SELECT * FROM edu_semesters WHERE id = ?");
$semStmt->execute([$semesterId]);
$semester = $semStmt->fetch(PDO::FETCH_ASSOC);
if (!$semester) { header('Location: grade_sheets.php'); exit; }

$sheetsStmt = $pdo->prepare("\n    SELECT gs.id, gs.type, gs.status, sub.code AS subject_code, sub.name_ru AS subject_name\n    FROM edu_grade_sheets gs\n    LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id\n    WHERE gs.group_id = ? AND gs.semester_id = ? AND gs.status <> 'rejected'\n    ORDER BY sub.name_ru\n");
$sheetsStmt->execute([$groupId, $semesterId]);
$gradeSheets = $sheetsStmt->fetchAll(PDO::FETCH_ASSOC);

$studentsStmt = $pdo->prepare("SELECT * FROM edu_students WHERE group_id = ? ORDER BY surname, name, patronymic");
$studentsStmt->execute([$groupId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$gradesStmt = $pdo->prepare("\n    SELECT eg.*\n    FROM edu_grades eg\n    JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id\n    WHERE gs.group_id = ? AND gs.semester_id = ? AND gs.status <> 'rejected'\n");
$gradesStmt->execute([$groupId, $semesterId]);
$gradeMap = [];
foreach ($gradesStmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
    $gradeMap[(int)$g['student_id']][(int)$g['grade_sheet_id']] = $g;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Итоговая');

$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'СВОДНАЯ ВЕДОМОСТЬ УСПЕВАЕМОСТИ');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Группа ' . ($group['name'] ?? '') . ' за ' . ($semester['semester_num'] ?? '') . ' семестр ' . ($semester['year_start'] ?? '') . '-' . ($semester['year_end'] ?? '') . ' учебного года');
$sheet->mergeCells('A2:D2');

$headers = ['№','Ф.И.О.','ИИН'];
foreach ($gradeSheets as $gs) {
    $name = trim(($gs['subject_code'] ? $gs['subject_code'] . ' ' : '') . ($gs['subject_name'] ?? ''));
    $headers[] = $name . ' — балл';
    $headers[] = $name . ' — букв.';
}
$sheet->fromArray($headers, null, 'A4');
$sheet->getStyle('A4:' . Coordinate::stringFromColumnIndex(count($headers)) . '4')->getFont()->setBold(true);
$sheet->getStyle('A4:' . Coordinate::stringFromColumnIndex(count($headers)) . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
$sheet->getStyle('A4:' . Coordinate::stringFromColumnIndex(count($headers)) . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

$row = 5;
$scholarshipRows = [];
foreach ($students as $idx => $student) {
    $line = [$idx + 1, edu_full_name($student), $student['iin'] ?? ''];
    $eligible = count($gradeSheets) > 0;
    $sum = 0; $cnt = 0;
    foreach ($gradeSheets as $gs) {
        $g = $gradeMap[(int)$student['id']][(int)$gs['id']] ?? null;
        $score = null;
        $letter = '';
        $ok = false;
        if ($g) {
            if (in_array($gs['type'], ['credit', 'practice'], true)) {
                $score = !empty($g['passed']) ? 100 : edu_normalize_score($g['grade']);
                $ok = !empty($g['passed']) && empty($g['absent']);
            } else {
                $score = edu_normalize_score($g['grade']);
                $ok = empty($g['absent']) && $score !== null && $score >= 70;
            }
            $letter = empty($g['absent']) ? edu_score_letter($score) : '';
        }
        if (!$ok) $eligible = false;
        if ($score !== null && empty($g['absent'])) { $sum += $score; $cnt++; }
        $line[] = ($g && !empty($g['absent'])) ? 'н/я' : ($score === null ? '' : $score);
        $line[] = $letter;
    }
    $sheet->fromArray($line, null, "A$row");
    if ($eligible) {
        $scholarshipRows[] = [$idx + 1, edu_full_name($student), $student['iin'] ?? '', $cnt ? round($sum / $cnt, 2) : ''];
    }
    $row++;
}

$lastCol = Coordinate::stringFromColumnIndex(count($headers));
$lastRow = max(4, $row - 1);
$sheet->getStyle("A4:$lastCol$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle("A4:$lastCol$lastRow")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
$sheet->freezePane('D5');
$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(32);
$sheet->getColumnDimension('C')->setWidth(16);
for ($i = 4; $i <= count($headers); $i++) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(14);

$sch = $spreadsheet->createSheet();
$sch->setTitle('Стипендия');
$sch->mergeCells('A1:D1');
$sch->setCellValue('A1', 'Студенты, сохраняющие право на стипендию');
$sch->setCellValue('A2', 'Условие: по всем дисциплинам семестра балл >= 70, без неявок и незакрытых зачетов.');
$sch->mergeCells('A2:D2');
$sch->fromArray(['№','Ф.И.О.','ИИН','Средний балл'], null, 'A4');
$sch->fromArray($scholarshipRows, null, 'A5');
$schLastRow = max(4, 4 + count($scholarshipRows));
$sch->getStyle("A4:D$schLastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sch->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sch->getStyle('A4:D4')->getFont()->setBold(true);
$sch->getStyle('A4:D4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
foreach (['A'=>6,'B'=>34,'C'=>16,'D'=>14] as $col => $width) $sch->getColumnDimension($col)->setWidth($width);

$tmp = tempnam(sys_get_temp_dir(), 'semester_summary_') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($tmp);
$name = edu_safe_filename('Итоговая_' . ($semester['semester_num'] ?? '') . '_семестр_' . ($group['name'] ?? '')) . '.xlsx';
edu_send_file($tmp, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
