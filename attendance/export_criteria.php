<?php
/**
 * export_criteria.php — Отчёт по критериям посещаемости (.xlsx)
 * Генерация: чистый PHP, ZipArchive + Open XML (без сторонних библиотек).
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Location: /requests/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

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
// Open XML helpers
// ══════════════════════════════════════════════════════════════════════════

function xe(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function colLetter(int $n): string {
    $s = '';
    while ($n > 0) { $n--; $s = chr(65 + $n % 26) . $s; $n = intdiv($n, 26); }
    return $s;
}

// Строит XML ячейки. styleIdx — индекс в cellXfs.
// Типы: 'n'=число, 's'=строка (inlineStr)
function xmlCell(int $col, int $row, $val, int $si = 0): string {
    $ref = colLetter($col) . $row;
    if ($val === null || $val === '') return "<c r=\"$ref\" s=\"$si\"/>";
    if (is_int($val) || is_float($val)) {
        return "<c r=\"$ref\" t=\"n\" s=\"$si\"><v>$val</v></c>";
    }
    return "<c r=\"$ref\" t=\"inlineStr\" s=\"$si\"><is><t>" . xe((string)$val) . "</t></is></c>";
}

// ── Индексы стилей (cellXfs) ──────────────────────────────────────────────
// 0  normal      обычный текст, левый, с бордером
// 1  normal_c    обычный текст, центр
// 2  header      серый фон, жирный, центр, wrap
// 3  title       серый фон, жирный большой, центр
// 4  info        без фона, обычный, левый
// 5  bold_left   жирный, левый
// 6  bold_c      жирный, центр
// 7  risk_c      красный текст, центр, красный фон ячейки
// 8  ok_c        зелёный текст, центр, зелёный фон ячейки
// 9  risk_txt_c  красный текст, центр, без фона
// 10 ok_txt_c    зелёный текст, центр, без фона
// 11 itog        серый фон, жирный, центр
// 12 itog_left   серый фон, жирный, левый
// 13 risk_num    красный текст, центр (для н/уч > 0)

$stylesXml = <<<'SXML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="7">
    <font><sz val="10"/><name val="Arial"/></font>
    <font><b/><sz val="10"/><name val="Arial"/></font>
    <font><b/><sz val="13"/><name val="Arial"/></font>
    <font><sz val="10"/><color rgb="FFC0392B"/><name val="Arial"/></font>
    <font><sz val="10"/><color rgb="FF27AE60"/><name val="Arial"/></font>
    <font><b/><sz val="10"/><color rgb="FFC0392B"/><name val="Arial"/></font>
    <font><b/><sz val="10"/><color rgb="FF27AE60"/><name val="Arial"/></font>
  </fonts>
  <fills count="7">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE8EAED"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFCE8E8"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE8F5E9"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF5F5F5"/></patternFill></fill>
    <fill><patternFill patternType="none"/></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/></border>
    <border>
      <left  style="thin"><color rgb="FFB0B0B0"/></left>
      <right style="thin"><color rgb="FFB0B0B0"/></right>
      <top   style="thin"><color rgb="FFB0B0B0"/></top>
      <bottom style="thin"><color rgb="FFB0B0B0"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="14">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="left"   vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment horizontal="left"   vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0"><alignment horizontal="left"   vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="5" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="5" borderId="1" xfId="0"><alignment horizontal="left"   vertical="center"/></xf>
    <xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>
SXML;

// ══════════════════════════════════════════════════════════════════════════
// ЛИСТ 1: Подробный отчёт
// ══════════════════════════════════════════════════════════════════════════
$rows1 = [];

// Строка 1 — заголовок (объединение A1:J1 через mergeCells)
$rows1[] = '<row r="1" ht="21" customHeight="1">'
    . xmlCell(1, 1, 'Отчёт по критериям посещаемости', 3)
    . '</row>';

// Строка 2 — инфо
$info = "Группа: $groupLabel  |  Период: $periodLabel  |  Порог: {$threshold}%";
$rows1[] = '<row r="2" ht="14" customHeight="1">'
    . xmlCell(1, 2, $info, 4)
    . '</row>';

// Строка 3 — шапка
$hdr = ['№','ФИО студента','Группа','Н/уч (ч)','Уваж. (ч)','Опоздания (ч)','Пропущено дней','Уваж. дней','% посещаемости','Статус'];
$hRow = '<row r="3" ht="32" customHeight="1">';
foreach ($hdr as $ci => $h) $hRow .= xmlCell($ci + 1, 3, $h, 2);
$rows1[] = $hRow . '</row>';

// Строки данных
foreach ($students as $idx => $st) {
    $r       = $idx + 4;
    $isRisk  = $st['status'] === 'риск';
    $pctStr  = $st['pct'] . '%';
    $absH    = (int)$st['absent_h'];

    $dRow = "<row r=\"$r\" ht=\"15\" customHeight=\"1\">";
    $dRow .= xmlCell(1,  $r, $idx + 1,                 1);   // №
    $dRow .= xmlCell(2,  $r, trim($st['full_name']),   0);   // ФИО
    $dRow .= xmlCell(3,  $r, $st['group_name'],        0);   // Группа
    $dRow .= xmlCell(4,  $r, $absH,                    $absH > 0 ? 13 : 1); // Н/уч
    $dRow .= xmlCell(5,  $r, (int)$st['excused_h'],    1);   // Уваж.
    $dRow .= xmlCell(6,  $r, (int)$st['late_h'],       1);   // Опозд.
    $dRow .= xmlCell(7,  $r, (int)$st['missed_days'],  1);   // Пропущ. дней
    $dRow .= xmlCell(8,  $r, (int)$st['excused_days'], 1);   // Уваж. дней
    $dRow .= xmlCell(9,  $r, $pctStr,                  $isRisk ? 9 : 10); // %
    $dRow .= xmlCell(10, $r, $st['status'],             $isRisk ? 7 : 8); // Статус
    $dRow .= '</row>';
    $rows1[] = $dRow;
}

$sheetData1 = implode("\n", $rows1);
$lastRow1   = count($students) + 3;

$sheet1 = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetFormatPr defaultRowHeight="15"/>
  <cols>
    <col min="1"  max="1"  width="5"  customWidth="1"/>
    <col min="2"  max="2"  width="34" customWidth="1"/>
    <col min="3"  max="3"  width="16" customWidth="1"/>
    <col min="4"  max="4"  width="11" customWidth="1"/>
    <col min="5"  max="5"  width="11" customWidth="1"/>
    <col min="6"  max="6"  width="14" customWidth="1"/>
    <col min="7"  max="7"  width="15" customWidth="1"/>
    <col min="8"  max="8"  width="12" customWidth="1"/>
    <col min="9"  max="9"  width="15" customWidth="1"/>
    <col min="10" max="10" width="12" customWidth="1"/>
  </cols>
  <sheetData>
$sheetData1
  </sheetData>
  <mergeCells count="2">
    <mergeCell ref="A1:J1"/>
    <mergeCell ref="A2:J2"/>
  </mergeCells>
  <pageSetup orientation="landscape" fitToWidth="1" fitToPage="1"/>
</worksheet>
XML;

// ══════════════════════════════════════════════════════════════════════════
// ЛИСТ 2: Сводка по группам
// ══════════════════════════════════════════════════════════════════════════
$rows2 = [];

$rows2[] = '<row r="1" ht="21" customHeight="1">'
    . xmlCell(1, 1, 'Сводка по группам', 3)
    . '</row>';

$info2 = "Период: $periodLabel  |  Рабочих дней: $workDays  |  Макс. часов/студент: $maxHours";
$rows2[] = '<row r="2" ht="14" customHeight="1">'
    . xmlCell(1, 2, $info2, 4)
    . '</row>';

$hdr2 = ['Группа','Студентов','Н/уч (ч)','Уваж. (ч)','Опозд. (ч)','В группе риска','% посещаемости'];
$hRow2 = '<row r="3" ht="32" customHeight="1">';
foreach ($hdr2 as $ci => $h) $hRow2 .= xmlCell($ci + 1, 3, $h, 2);
$rows2[] = $hRow2 . '</row>';

$r2 = 4;
foreach ($groupTotals as $gname => $gt) {
    $isRisk = $gt['avg_pct'] < $threshold;
    $dRow2  = "<row r=\"$r2\" ht=\"15\" customHeight=\"1\">";
    $dRow2 .= xmlCell(1, $r2, $gname,                0);
    $dRow2 .= xmlCell(2, $r2, $gt['count'],           1);
    $dRow2 .= xmlCell(3, $r2, (int)$gt['absent_h'],  1);
    $dRow2 .= xmlCell(4, $r2, (int)$gt['excused_h'], 1);
    $dRow2 .= xmlCell(5, $r2, (int)$gt['late_h'],    1);
    $dRow2 .= xmlCell(6, $r2, $gt['risk'] . ' чел.', 1);
    $dRow2 .= xmlCell(7, $r2, $gt['avg_pct'] . '%',  $isRisk ? 7 : 8);
    $dRow2 .= '</row>';
    $rows2[] = $dRow2;
    $r2++;
}

// Итог
$rows2[] = "<row r=\"$r2\" ht=\"16\" customHeight=\"1\">"
    . xmlCell(1, $r2, 'ИТОГО',              12)
    . xmlCell(2, $r2, $totalStudents,        11)
    . xmlCell(3, $r2, $totalAbsH,            11)
    . xmlCell(4, $r2, $totalExcH,            11)
    . xmlCell(5, $r2, $totalLateH,           11)
    . xmlCell(6, $r2, $totalRisk . ' чел.',  11)
    . xmlCell(7, $r2, $avgPctAll . '%',      11)
    . '</row>';

$sheetData2 = implode("\n", $rows2);

$sheet2 = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetFormatPr defaultRowHeight="15"/>
  <cols>
    <col min="1" max="1" width="22" customWidth="1"/>
    <col min="2" max="7" width="16" customWidth="1"/>
  </cols>
  <sheetData>
$sheetData2
  </sheetData>
  <mergeCells count="2">
    <mergeCell ref="A1:G1"/>
    <mergeCell ref="A2:G2"/>
  </mergeCells>
</worksheet>
XML;

// ══════════════════════════════════════════════════════════════════════════
// Сборка ZIP → .xlsx
// ══════════════════════════════════════════════════════════════════════════
$contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"           ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"  ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml"  ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"             ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;

$rels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;

$workbook = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Подробный отчёт"   sheetId="1" r:id="rId1"/>
    <sheet name="Сводка по группам" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>
XML;

$wbRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"    Target="styles.xml"/>
</Relationships>
XML;

$tmpFile = tempnam(sys_get_temp_dir(), 'att_cr_') . '.xlsx';
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Не удалось создать файл Excel');
}
$zip->addFromString('[Content_Types].xml',        $contentTypes);
$zip->addFromString('_rels/.rels',                $rels);
$zip->addFromString('xl/workbook.xml',            $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$zip->addFromString('xl/styles.xml',              $stylesXml);
$zip->addFromString('xl/worksheets/sheet1.xml',   $sheet1);
$zip->addFromString('xl/worksheets/sheet2.xml',   $sheet2);
$zip->close();

$filename = 'criteria_'
    . ($groupId > 0 ? 'group' . $groupId : 'all')
    . '_' . str_replace('-', '', $dateFrom)
    . '-' . str_replace('-', '', $dateTo)
    . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, no-store');
readfile($tmpFile);
unlink($tmpFile);
