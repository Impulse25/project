<?php
/**
 * Сводная ведомость посещаемости — экспорт в .docx
 * Вызывается из index.php (таб «Рапортичка»):
 *   export_vedomost.php?group_id=X&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&course=3&period_label=03.2026
 *
 * Генерирует .docx без внешних библиотек (чистый ZIP + OOXML).
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Проверка авторизации (редирект, т.к. файл отдаёт docx, а не JSON)
if (!isset($_SESSION['user_id'])) {
    header('Location: /requests/login.php');
    exit;
}

// Подключение к БД через общий config/db.php проекта
require_once __DIR__ . '/../config/db.php';
$pdo = $pdo ?? getDbConnection();

// ── Параметры ────────────────────────────────────────────────────────────
$group_id     = (int)($_GET['group_id']    ?? 0);
$date_from    = $_GET['date_from']         ?? date('Y-m-01');
$date_to      = $_GET['date_to']           ?? date('Y-m-t');
$course       = trim($_GET['course']       ?? '');
$period_label = trim($_GET['period_label'] ?? date('m.Y'));
$semester     = trim($_GET['semester']     ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-t');

// ── Группа ───────────────────────────────────────────────────────────────
$grpStmt = $pdo->prepare("
    SELECT g.name AS group_name, g.course,
           COALESCE(sp.name_ru,'') AS specialty,
           COALESCE(u.full_name,'') AS curator_name
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    LEFT JOIN users u ON u.id = g.curator_id
    WHERE g.id = :gid
");
$grpStmt->execute([':gid' => $group_id]);
$groupInfo = $grpStmt->fetch();
if (!$groupInfo) die('Группа не найдена');

$groupName = $groupInfo['group_name'];
$courseNum = $course ?: $groupInfo['course'];

// ── Студенты с суммой пропусков ───────────────────────────────────────────
$stmtSt = $pdo->prepare("
    SELECT s.id,
           CONCAT(s.surname, ' ', s.name, ' ', s.patronymic) AS full_name,
           COALESCE(SUM(CASE WHEN a.status IN ('absent','excused') THEN a.hours_missed ELSE 0 END), 0) AS total_hours,
           COALESCE(SUM(CASE WHEN a.status='excused'  THEN a.hours_missed ELSE 0 END), 0) AS excused_hours,
           COALESCE(SUM(CASE WHEN a.status='absent'   THEN a.hours_missed ELSE 0 END), 0) AS absent_hours
    FROM edu_students s
    LEFT JOIN att_attendance a
           ON a.student_id = s.id AND a.date BETWEEN :df AND :dt
    WHERE s.group_id = :gid
    GROUP BY s.id, s.surname, s.name, s.patronymic
    ORDER BY s.surname, s.name, s.patronymic
");
$stmtSt->execute([':df' => $date_from, ':dt' => $date_to, ':gid' => $group_id]);
$students = $stmtSt->fetchAll();

$padTo      = max(count($students) + 3, 20);
$padTo      = (int)(ceil($padTo / 5) * 5);
$sumTotal   = array_sum(array_column($students, 'total_hours'));
$sumExcused = array_sum(array_column($students, 'excused_hours'));
$sumAbsent  = array_sum(array_column($students, 'absent_hours'));

// ── Строка периода ────────────────────────────────────────────────────────
$monthNames = [
    1=>'январь',2=>'февраль',3=>'март',4=>'апрель',5=>'май',6=>'июнь',
    7=>'июль',8=>'август',9=>'сентябрь',10=>'октябрь',11=>'ноябрь',12=>'декабрь'
];
$fromTs = strtotime($date_from);
$toTs   = strtotime($date_to);
$fromM  = (int)date('n', $fromTs);
$toM    = (int)date('n', $toTs);
$fromY  = date('Y', $fromTs);
$toY    = date('Y', $toTs);
$periodStr = ($fromM === $toM && $fromY === $toY)
    ? $monthNames[$fromM] . ' ' . $fromY . 'г'
    : $monthNames[$fromM] . ' – ' . $monthNames[$toM] . ' ' . $toY . 'г';

// ── XML-хелперы ───────────────────────────────────────────────────────────
function xe(string $s): string {
    return htmlspecialchars($s, ENT_XML1, 'UTF-8');
}

function makeRpr(int $sz = 20, bool $bold = false): string {
    return "<w:rPr>"
         . "<w:rFonts w:ascii=\"Times New Roman\" w:hAnsi=\"Times New Roman\" w:cs=\"Times New Roman\"/>"
         . "<w:sz w:val=\"{$sz}\"/><w:szCs w:val=\"{$sz}\"/>"
         . ($bold ? "<w:b/><w:bCs/>" : "")
         . "</w:rPr>";
}

function docxRow(array $cells, int $heightTwips = 360, bool $isHeader = false): string {
    $trPr = "<w:trPr><w:trHeight w:val=\"{$heightTwips}\" w:hRule=\"atLeast\"/>"
          . ($isHeader ? "<w:tblHeader/>" : "")
          . "</w:trPr>";
    $xml  = "<w:tr>{$trPr}";
    foreach ($cells as $c) {
        $w        = (int)($c['w']          ?? 1000);
        $bold     = !empty($c['bold']);
        $align    = $c['align']            ?? 'center';
        $vMerge   = !empty($c['vMerge']);
        $vMergeR  = !empty($c['vMergeStart']);
        $colSpan  = (int)($c['colSpan']    ?? 1);
        $sz       = (int)($c['sz']         ?? 20);
        $text     = xe((string)($c['text'] ?? ''));

        $tcPr = "<w:tcPr><w:tcW w:w=\"{$w}\" w:type=\"dxa\"/>";
        if ($colSpan > 1) $tcPr .= "<w:gridSpan w:val=\"{$colSpan}\"/>";
        if ($vMergeR)     $tcPr .= "<w:vMerge w:val=\"restart\"/>";
        if ($vMerge)      $tcPr .= "<w:vMerge/>";
        $tcPr .= "<w:tcBorders>"
               . "<w:top    w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"000000\"/>"
               . "<w:left   w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"000000\"/>"
               . "<w:bottom w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"000000\"/>"
               . "<w:right  w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"000000\"/>"
               . "</w:tcBorders>"
               . "<w:tcMar>"
               . "<w:top    w:w=\"40\"  w:type=\"dxa\"/><w:left  w:w=\"80\" w:type=\"dxa\"/>"
               . "<w:bottom w:w=\"40\"  w:type=\"dxa\"/><w:right w:w=\"80\" w:type=\"dxa\"/>"
               . "</w:tcMar>"
               . "<w:vAlign w:val=\"center\"/>"
               . "</w:tcPr>";

        $rPr = makeRpr($sz, $bold);
        $jc  = "<w:jc w:val=\"{$align}\"/>";
        $xml .= "<w:tc>{$tcPr}"
              . "<w:p><w:pPr><w:spacing w:before=\"0\" w:after=\"0\"/>{$jc}</w:pPr>"
              . "<w:r>{$rPr}<w:t xml:space=\"preserve\">{$text}</w:t></w:r>"
              . "</w:p></w:tc>";
    }
    return $xml . "</w:tr>";
}

function docxPara(string $text, string $align='center', bool $bold=false, int $sz=22, int $before=0, int $after=120): string {
    $rPr = makeRpr($sz, $bold);
    return "<w:p>"
         . "<w:pPr><w:jc w:val=\"{$align}\"/><w:spacing w:before=\"{$before}\" w:after=\"{$after}\"/></w:pPr>"
         . "<w:r>{$rPr}<w:t xml:space=\"preserve\">" . xe($text) . "</w:t></w:r>"
         . "</w:p>";
}

// ── Размеры колонок (A4 portrait: 11906 - 1008 left - 720 right = 10178) ─
$wN     = 520;
$wFIO   = 6058;
$wTot   = 1100;
$wEx    = 1250;
$wUnex  = 1250;
$tableW = $wN + $wFIO + $wTot + $wEx + $wUnex; // 10178

// ── Шапка таблицы ────────────────────────────────────────────────────────
$hdr1 = docxRow([
    ['text'=>'№',    'w'=>$wN,              'bold'=>true, 'align'=>'center', 'vMergeStart'=>true, 'sz'=>20],
    ['text'=>'Ф.И.О','w'=>$wFIO,            'bold'=>true, 'align'=>'center', 'vMergeStart'=>true, 'sz'=>20],
    ['text'=>'Пропуски в часах', 'w'=>$wTot+$wEx+$wUnex, 'bold'=>true, 'align'=>'center', 'colSpan'=>3, 'sz'=>20],
], 400, true);

$hdr2 = docxRow([
    ['text'=>'',                        'w'=>$wN,   'vMerge'=>true],
    ['text'=>'',                        'w'=>$wFIO, 'vMerge'=>true],
    ['text'=>'всего',                   'w'=>$wTot, 'bold'=>true,'align'=>'center','sz'=>18],
    ['text'=>'из них по уваж. причине', 'w'=>$wEx,  'bold'=>true,'align'=>'center','sz'=>18],
    ['text'=>'неуважит. причина',       'w'=>$wUnex,'bold'=>true,'align'=>'center','sz'=>18],
], 540, true);

// ── Строки данных ─────────────────────────────────────────────────────────
$dataXml = '';
for ($i = 0; $i < $padTo; $i++) {
    if (isset($students[$i])) {
        $s = $students[$i];
        $tot = $s['total_hours']   > 0 ? (string)(int)$s['total_hours']   : '';
        $exc = $s['excused_hours'] > 0 ? (string)(int)$s['excused_hours'] : '';
        $abs = $s['absent_hours']  > 0 ? (string)(int)$s['absent_hours']  : '';
        $dataXml .= docxRow([
            ['text'=>(string)($i+1), 'w'=>$wN,   'align'=>'center','sz'=>20],
            ['text'=>$s['full_name'],'w'=>$wFIO,  'align'=>'left',  'sz'=>20],
            ['text'=>$tot,           'w'=>$wTot,  'align'=>'center','sz'=>20],
            ['text'=>$exc,           'w'=>$wEx,   'align'=>'center','sz'=>20],
            ['text'=>$abs,           'w'=>$wUnex, 'align'=>'center','sz'=>20],
        ], 360);
    } else {
        $dataXml .= docxRow([
            ['text'=>'','w'=>$wN],['text'=>'','w'=>$wFIO,'align'=>'left'],
            ['text'=>'','w'=>$wTot],['text'=>'','w'=>$wEx],['text'=>'','w'=>$wUnex],
        ], 360);
    }
}

// ── Итого ─────────────────────────────────────────────────────────────────
$dataXml .= docxRow([
    ['text'=>'',                                    'w'=>$wN,   'align'=>'center','bold'=>true,'sz'=>20],
    ['text'=>'Итого',                               'w'=>$wFIO, 'align'=>'right', 'bold'=>true,'sz'=>20],
    ['text'=>($sumTotal>0?(string)$sumTotal:''),    'w'=>$wTot, 'align'=>'center','bold'=>true,'sz'=>20],
    ['text'=>($sumExcused>0?(string)$sumExcused:''),'w'=>$wEx,  'align'=>'center','bold'=>true,'sz'=>20],
    ['text'=>($sumAbsent>0?(string)$sumAbsent:''),  'w'=>$wUnex,'align'=>'center','bold'=>true,'sz'=>20],
], 400);

// ── Таблица ───────────────────────────────────────────────────────────────
$tblPr  = "<w:tblPr><w:tblW w:w=\"{$tableW}\" w:type=\"dxa\"/><w:tblLayout w:type=\"fixed\"/></w:tblPr>";
$tblGrid= "<w:tblGrid><w:gridCol w:w=\"{$wN}\"/><w:gridCol w:w=\"{$wFIO}\"/>"
        . "<w:gridCol w:w=\"{$wTot}\"/><w:gridCol w:w=\"{$wEx}\"/><w:gridCol w:w=\"{$wUnex}\"/></w:tblGrid>";
$table  = "<w:tbl>{$tblPr}{$tblGrid}{$hdr1}{$hdr2}{$dataXml}</w:tbl>";

// ── Строки подписей ───────────────────────────────────────────────────────
$rPrSign = makeRpr(20);
$line35  = str_repeat('_', 38);
$line20  = str_repeat('_', 22);
$line28  = str_repeat('_', 30);

$signZav = "<w:p><w:pPr><w:spacing w:before=\"280\" w:after=\"80\"/></w:pPr>"
         . "<w:r>{$rPrSign}<w:t xml:space=\"preserve\">Зав.отделением {$line35}</w:t></w:r></w:p>";

$signBottom = "<w:p><w:pPr><w:spacing w:before=\"200\" w:after=\"80\"/></w:pPr>"
    . "<w:r>{$rPrSign}<w:t xml:space=\"preserve\">Кл. руководитель {$line28}</w:t></w:r>"
    . "<w:r>{$rPrSign}<w:t xml:space=\"preserve\">          Староста {$line20}</w:t></w:r>"
    . "</w:p>";

// ── Заголовки ─────────────────────────────────────────────────────────────
$title1 = 'СВОДНАЯ ВЕДОМОСТЬ ПОСЕЩАЕМОСТИ УЧ-СЯ  ГР. ' . mb_strtoupper($groupName);
$semPart = $semester ? '   за  ' . xe($semester) . ' семестр' : '';
$title2 = 'Курса ' . xe((string)$courseNum) . $semPart . '   ' . xe($periodStr);

// ── document.xml ──────────────────────────────────────────────────────────
$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>'
. docxPara($title1, 'center', true, 22, 0, 60)
. docxPara($title2, 'center', false, 22, 0, 200)
. $table
. docxPara('', 'left', false, 20, 120, 0)
. $signZav
. $signBottom
. '<w:sectPr>
  <w:pgSz w:w="11906" w:h="16838"/>
  <w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="1008" w:header="0" w:footer="0" w:gutter="0"/>
</w:sectPr>
</w:body>
</w:document>';

// ── Вспомогательные XML-файлы ─────────────────────────────────────────────
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/word/document.xml"
    ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/settings.xml"
    ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="word/document.xml"/>
</Relationships>';

$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings"
    Target="settings.xml"/>
</Relationships>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault><w:rPr>
      <w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>
      <w:sz w:val="22"/><w:szCs w:val="22"/>
    </w:rPr></w:rPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:pPr><w:spacing w:after="0"/></w:pPr>
    <w:rPr>
      <w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>
      <w:sz w:val="22"/><w:szCs w:val="22"/>
    </w:rPr>
  </w:style>
</w:styles>';

$settingsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:compat>
    <w:compatSetting w:name="compatibilityMode"
      w:uri="http://schemas.microsoft.com/office/word" w:val="15"/>
  </w:compat>
</w:settings>';

// ── ZIP → DOCX ────────────────────────────────────────────────────────────
$tmpFile = tempnam(sys_get_temp_dir(), 'ved_') . '.docx';
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Не удалось создать архив');
}
$zip->addFromString('[Content_Types].xml',          $contentTypes);
$zip->addFromString('_rels/.rels',                  $rels);
$zip->addFromString('word/document.xml',            $documentXml);
$zip->addFromString('word/_rels/document.xml.rels', $docRels);
$zip->addFromString('word/styles.xml',              $stylesXml);
$zip->addFromString('word/settings.xml',            $settingsXml);
$zip->close();

// ── Отдаём файл ───────────────────────────────────────────────────────────
$safeGroup = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $groupName);
$fileName  = 'vedomost_' . $safeGroup . '_' . preg_replace('/[^0-9\-]/', '', $date_from) . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, must-revalidate');
readfile($tmpFile);
unlink($tmpFile);
exit;
