<?php
/**
 * export_criteria.php — Отчёт по критериям посещаемости (.xlsx)
 * Генерация через PhpSpreadsheet (vendor из модуля edu).
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Location: /requests/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../edu/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

// ── Параметры ──────────────────────────────────────────────────────────────
$groupId   = (int)($_GET['group_id']  ?? 0);
$dateFrom  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-01');
$dateTo    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']   ?? '') ? $_GET['date_to']   : date('Y-m-t');
$threshold = max(1, min(100, (int)($_GET['threshold'] ?? 75)));
$userId    = (int)$_SESSION['user_id'];
$userRole  = $_SESSION['role'] ?? 'teacher';
$isAdmin   = in_array($userRole, ['admin', 'director', 'curator']);

// ── Доступные группы ──────────────────────────────────────────────────────
if ($isAdmin) {
    $grpStmt = $pdo->query("SELECT g.id, g.name FROM edu_groups g ORDER BY g.name");
} else {
    $grpStmt = $pdo->prepare("SELECT g.id, g.name FROM edu_groups g WHERE g.curator_id = ? ORDER BY g.name");
    $grpStmt->execute([$userId]);
}
$allGroups = $grpStmt->fetchAll();
$groupIds  = $groupId > 0 ? [$groupId] : array_column($allGroups, 'id');
if (empty($groupIds)) $groupIds = [0];
$inPlaces  = implode(',', array_map('intval', $groupIds));

// ── Рабочие дни ──────────────────────────────────────────────────────────
$workDays = 0;
$cur = strtotime($dateFrom);
while ($cur <= strtotime($dateTo)) {
    if ((int)date('N', $cur) <= 5) $workDays++;
    $cur += 86400;
}
$maxHours = max(1, $workDays * 6);

// ── Данные студентов ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        s.id,
        CONCAT(s.surname, ' ', s.name, ' ', COALESCE(s.patronymic,'')) AS full_name,
        g.name AS group_name,
        COALESCE(SUM(CASE WHEN a.status='absent'  THEN a.hours_missed ELSE 0 END),0) AS absent_h,
        COALESCE(SUM(CASE WHEN a.status='excused' THEN a.hours_missed ELSE 0 END),0) AS excused_h,
        COALESCE(SUM(CASE WHEN a.status='late'    THEN a.hours_missed ELSE 0 END),0) AS late_h,
        COUNT(DISTINCT CASE WHEN a.status IN ('absent','late') THEN a.date END)      AS missed_days,
        COUNT(DISTINCT CASE WHEN a.status='excused'            THEN a.date END)      AS excused_days
    FROM edu_students s
    INNER JOIN edu_groups g ON g.id = s.group_id
    LEFT JOIN att_attendance a ON a.student_id = s.id
                               AND a.date BETWEEN :df AND :dt
    WHERE g.id IN ($inPlaces)
    GROUP BY s.id, s.surname, s.name, s.patronymic, g.name
    ORDER BY g.name, s.surname, s.name
");
$stmt->execute([':df' => $dateFrom, ':dt' => $dateTo]);
$students = $stmt->fetchAll();

foreach ($students as &$st) {
    $st['pct']    = (int)round(max(0, ($maxHours - $st['absent_h']) / $maxHours * 100));
    $st['status'] = $st['pct'] >= $threshold ? 'норма' : 'риск';
}
unset($st);

// ── Итоги по группам ─────────────────────────────────────────────────────
$groupTotals = [];
foreach ($students as $st) {
    $gn = $st['group_name'];
    if (!isset($groupTotals[$gn])) {
        $groupTotals[$gn] = ['count'=>0,'absent_h'=>0,'excused_h'=>0,'late_h'=>0,'risk'=>0,'pct_sum'=>0];
    }
    $groupTotals[$gn]['count']++;
    $groupTotals[$gn]['absent_h']  += $st['absent_h'];
    $groupTotals[$gn]['excused_h'] += $st['excused_h'];
    $groupTotals[$gn]['late_h']    += $st['late_h'];
    $groupTotals[$gn]['risk']      += ($st['status'] === 'риск' ? 1 : 0);
    $groupTotals[$gn]['pct_sum']   += $st['pct'];
}
foreach ($groupTotals as &$gt) {
    $gt['avg_pct'] = $gt['count'] > 0 ? (int)round($gt['pct_sum'] / $gt['count']) : 100;
}
unset($gt);

$groupLabel    = $groupId > 0
    ? ($allGroups[array_search($groupId, array_column($allGroups,'id'))]['name'] ?? 'Группа')
    : 'Все группы';
$periodLabel   = date('d.m.Y', strtotime($dateFrom)) . ' — ' . date('d.m.Y', strtotime($dateTo));
$totalStudents = count($students);
$totalAbsH     = (int)array_sum(array_column($students, 'absent_h'));
$totalExcH     = (int)array_sum(array_column($students, 'excused_h'));
$totalLateH    = (int)array_sum(array_column($students, 'late_h'));
$totalRisk     = count(array_filter($students, fn($s) => $s['status'] === 'риск'));
$avgPctAll     = $totalStudents > 0 ? (int)round(array_sum(array_column($students,'pct')) / $totalStudents) : 100;

// ══════════════════════════════════════════════════════════════════════════
// Стили (переиспользуемые массивы)
// ══════════════════════════════════════════════════════════════════════════
$borderAll = [
    'allBorders' => [
        'borderStyle' => Border::BORDER_THIN,
        'color'       => ['rgb' => 'B0B0B0'],
    ],
];

$styleTitle = [
    'font'      => ['bold' => true, 'size' => 13, 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EAED']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => $borderAll,
];

$styleInfo = [
    'font'      => ['size' => 10, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleHeader = [
    'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EAED']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders'   => $borderAll,
];

$styleNormal = [
    'font'      => ['size' => 10, 'name' => 'Arial'],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => $borderAll,
];

$styleCenter = array_merge_recursive($styleNormal, [
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$styleRiskCell = [
    'font'      => ['size' => 10, 'name' => 'Arial', 'color' => ['rgb' => 'C0392B']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE8E8']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => $borderAll,
];

$styleOkCell = [
    'font'      => ['size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '27AE60']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => $borderAll,
];

$styleRiskTxt = [
    'font'      => ['size' => 10, 'name' => 'Arial', 'color' => ['rgb' => 'C0392B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => $borderAll,
];

$styleOkTxt = [
    'font'      => ['size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '27AE60']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => $borderAll,
];

$styleItog = [
    'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => $borderAll,
];

// ══════════════════════════════════════════════════════════════════════════
// ЛИСТ 1: Подробный отчёт
// ══════════════════════════════════════════════════════════════════════════
$ss = new Spreadsheet();
$ws1 = $ss->getActiveSheet()->setTitle('Подробный отчёт');

// Ширины колонок
$ws1->getColumnDimension('A')->setWidth(5);
$ws1->getColumnDimension('B')->setWidth(34);
$ws1->getColumnDimension('C')->setWidth(16);
$ws1->getColumnDimension('D')->setWidth(11);
$ws1->getColumnDimension('E')->setWidth(11);
$ws1->getColumnDimension('F')->setWidth(14);
$ws1->getColumnDimension('G')->setWidth(15);
$ws1->getColumnDimension('H')->setWidth(12);
$ws1->getColumnDimension('I')->setWidth(15);
$ws1->getColumnDimension('J')->setWidth(12);

// Строка 1 — заголовок
$ws1->mergeCells('A1:J1');
$ws1->setCellValue('A1', 'Отчёт по критериям посещаемости');
$ws1->getStyle('A1')->applyFromArray($styleTitle);
$ws1->getRowDimension(1)->setRowHeight(21);

// Строка 2 — инфо
$ws1->mergeCells('A2:J2');
$ws1->setCellValue('A2', "Группа: $groupLabel  |  Период: $periodLabel  |  Порог: {$threshold}%");
$ws1->getStyle('A2')->applyFromArray($styleInfo);
$ws1->getRowDimension(2)->setRowHeight(14);

// Строка 3 — шапка
$headers = ['A3'=>'№','B3'=>'ФИО студента','C3'=>'Группа','D3'=>'Н/уч (ч)',
            'E3'=>'Уваж. (ч)','F3'=>'Опоздания (ч)','G3'=>'Пропущено дней',
            'H3'=>'Уваж. дней','I3'=>'% посещаемости','J3'=>'Статус'];
foreach ($headers as $coord => $val) {
    $ws1->setCellValue($coord, $val);
}
$ws1->getStyle('A3:J3')->applyFromArray($styleHeader);
$ws1->getRowDimension(3)->setRowHeight(32);

// Данные
foreach ($students as $idx => $st) {
    $r      = $idx + 4;
    $isRisk = $st['status'] === 'риск';
    $absH   = (int)$st['absent_h'];

    $ws1->setCellValue("A$r", $idx + 1);
    $ws1->setCellValue("B$r", trim($st['full_name']));
    $ws1->setCellValue("C$r", $st['group_name']);
    $ws1->setCellValue("D$r", $absH);
    $ws1->setCellValue("E$r", (int)$st['excused_h']);
    $ws1->setCellValue("F$r", (int)$st['late_h']);
    $ws1->setCellValue("G$r", (int)$st['missed_days']);
    $ws1->setCellValue("H$r", (int)$st['excused_days']);
    $ws1->setCellValue("I$r", $st['pct'] . '%');
    $ws1->setCellValue("J$r", $st['status']);

    // базовый стиль строки
    $ws1->getStyle("A$r")->applyFromArray(array_merge($styleCenter, ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]));
    $ws1->getStyle("B$r:C$r")->applyFromArray($styleNormal);
    $ws1->getStyle("D$r:H$r")->applyFromArray($styleCenter);
    $ws1->getStyle("B$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $ws1->getStyle("C$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Н/уч — красный если > 0
    if ($absH > 0) {
        $ws1->getStyle("D$r")->getFont()->getColor()->setRGB('C0392B');
    }
    // % посещаемости
    $ws1->getStyle("I$r")->applyFromArray($isRisk ? $styleRiskTxt : $styleOkTxt);
    // Статус
    $ws1->getStyle("J$r")->applyFromArray($isRisk ? $styleRiskCell : $styleOkCell);

    $ws1->getRowDimension($r)->setRowHeight(15);
}

// ══════════════════════════════════════════════════════════════════════════
// ЛИСТ 2: Сводка по группам
// ══════════════════════════════════════════════════════════════════════════
$ws2 = $ss->createSheet()->setTitle('Сводка по группам');

$ws2->getColumnDimension('A')->setWidth(22);
foreach (['B','C','D','E','F','G'] as $col) {
    $ws2->getColumnDimension($col)->setWidth(16);
}

// Заголовок
$ws2->mergeCells('A1:G1');
$ws2->setCellValue('A1', 'Сводка по группам');
$ws2->getStyle('A1')->applyFromArray($styleTitle);
$ws2->getRowDimension(1)->setRowHeight(21);

// Инфо
$ws2->mergeCells('A2:G2');
$ws2->setCellValue('A2', "Период: $periodLabel  |  Рабочих дней: $workDays  |  Макс. часов/студент: $maxHours");
$ws2->getStyle('A2')->applyFromArray($styleInfo);
$ws2->getRowDimension(2)->setRowHeight(14);

// Шапка
$headers2 = ['A3'=>'Группа','B3'=>'Студентов','C3'=>'Н/уч (ч)','D3'=>'Уваж. (ч)',
             'E3'=>'Опозд. (ч)','F3'=>'В группе риска','G3'=>'% посещаемости'];
foreach ($headers2 as $coord => $val) {
    $ws2->setCellValue($coord, $val);
}
$ws2->getStyle('A3:G3')->applyFromArray($styleHeader);
$ws2->getRowDimension(3)->setRowHeight(32);

// Данные групп
$r2 = 4;
foreach ($groupTotals as $gname => $gt) {
    $isRisk = $gt['avg_pct'] < $threshold;
    $ws2->setCellValue("A$r2", $gname);
    $ws2->setCellValue("B$r2", $gt['count']);
    $ws2->setCellValue("C$r2", (int)$gt['absent_h']);
    $ws2->setCellValue("D$r2", (int)$gt['excused_h']);
    $ws2->setCellValue("E$r2", (int)$gt['late_h']);
    $ws2->setCellValue("F$r2", $gt['risk'] . ' чел.');
    $ws2->setCellValue("G$r2", $gt['avg_pct'] . '%');
    $ws2->getStyle("A$r2:G$r2")->applyFromArray($styleNormal);
    $ws2->getStyle("B$r2:G$r2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws2->getStyle("G$r2")->applyFromArray($isRisk ? $styleRiskCell : $styleOkCell);
    $ws2->getRowDimension($r2)->setRowHeight(15);
    $r2++;
}

// Итог
$ws2->setCellValue("A$r2", 'ИТОГО');
$ws2->setCellValue("B$r2", $totalStudents);
$ws2->setCellValue("C$r2", $totalAbsH);
$ws2->setCellValue("D$r2", $totalExcH);
$ws2->setCellValue("E$r2", $totalLateH);
$ws2->setCellValue("F$r2", $totalRisk . ' чел.');
$ws2->setCellValue("G$r2", $avgPctAll . '%');
$ws2->getStyle("A$r2:G$r2")->applyFromArray($styleItog);
$ws2->getStyle("A$r2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$ws2->getRowDimension($r2)->setRowHeight(16);

// ── Отдаём файл ──────────────────────────────────────────────────────────
$filename = 'criteria_'
    . ($groupId > 0 ? 'group' . $groupId : 'all')
    . '_' . str_replace('-', '', $dateFrom)
    . '-' . str_replace('-', '', $dateTo)
    . '.xlsx';

$ss->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');

$writer = new Xlsx($ss);
$writer->save('php://output');
