<?php
// hr/export.php — Экспорт данных HR-Аналитики в Excel, CSV и Word
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Не авторизован');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/app/access.php';

$userId = (int)$_SESSION['user_id'];
$userRole = hr_normalize_role($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? '');
$allowedViews = hr_allowed_views_for_role($userRole);
if (!$allowedViews) {
    http_response_code(403);
    die('Нет доступа к HR-модулю');
}

$isDirector = $userRole === 'director';
$currentYear = (int)date('Y');

// ── Параметры экспорта ────────────────────────────────────
$format = mb_strtolower(trim($_GET['format'] ?? 'excel')); // excel|xlsx|csv|word
$requestedView = trim((string)($_GET['view'] ?? ''));
if ($requestedView === 'previous') {
    $requestedView = 'graduates';
}
$hrView = in_array($requestedView, $allowedViews, true) ? $requestedView : hr_default_view_for_role($userRole);

$fGroup      = isset($_GET['group_id'])      && $_GET['group_id']      !== '' ? (int)$_GET['group_id']      : null;
$fSpec       = isset($_GET['specialty_id'])  && $_GET['specialty_id']  !== '' ? (int)$_GET['specialty_id']  : null;
$fDepartment = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : null;
$fYear       = isset($_GET['grad_year'])     && $_GET['grad_year']     !== '' ? (int)$_GET['grad_year']     : null;
$fStatus     = isset($_GET['status'])        && $_GET['status']        !== '' ? trim($_GET['status'])       : null;
$fSearch     = isset($_GET['search'])        ? trim($_GET['search'])          : '';

if ($isDirector) {
    $fGroup = null;
}

$gradExpr = hr_group_grad_expr('g');
$groupStateExpr = hr_group_state_sql('g', $currentYear);
$departmentExpr = 'COALESCE(g.department_id, sp.department_id)';

// ── Основной запрос с теми же фильтрами, что и в index.php ─
[$scopeConds, $scopeParams] = hr_scope_sql('g', $userRole, $userId, $hrView, $currentYear);
$where  = $scopeConds ?: ['1=1'];
$params = $scopeParams;

if ($fGroup) {
    $where[]  = 's.group_id = ?';
    $params[] = $fGroup;
}
if ($fDepartment) {
    $where[] = "$departmentExpr = ?";
    $params[] = $fDepartment;
}
if ($fSpec) {
    $where[]  = 'COALESCE(s.speciality_id, g.specialty_id) = ?';
    $params[] = $fSpec;
}
if ($fYear) {
    $where[]  = "$gradExpr = ?";
    $params[] = $fYear;
}
if ($fStatus) {
    $where[]  = 'e.status = ?';
    $params[] = $fStatus;
}
if ($fSearch !== '') {
    // Нечёткий поиск: каждое слово ищется отдельно и не зависит от порядка ввода.
    $terms = preg_split('/\s+/u', mb_strtolower(str_replace('ё', 'е', $fSearch)), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $where[] = "(
            LOWER(REPLACE(COALESCE(s.surname,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(s.name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(s.patronymic,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(CONCAT_WS(' ', COALESCE(s.surname,''), COALESCE(s.name,''), COALESCE(s.patronymic,'')), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(g.name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(sp.name_ru,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(sp.code,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(d.department_name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(e.employer_name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(e.position,''), 'ё', 'е')) LIKE ?
        )";
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }
}

$whereStr = implode(' AND ', $where);

$sql = "
    SELECT
        s.id AS student_id,
        s.surname, s.name, s.patronymic,
        g.name AS group_name,
        g.course,
        g.year_started,
        $gradExpr AS grad_year,
        $groupStateExpr AS group_state,
        d.department_name,
        sp.name_ru AS specialty_name,
        sp.code AS specialty_code,
        e.status,
        e.employer_name,
        e.position,
        e.employment_date,
        e.employment_type,
        e.is_by_specialty,
        e.graduation_year AS employment_graduation_year,
        e.notes
    FROM edu_students s
    LEFT JOIN edu_groups g ON s.group_id = g.id
    LEFT JOIN edu_specialties sp ON sp.id = COALESCE(s.speciality_id, g.specialty_id)
    LEFT JOIN departments d ON d.id = $departmentExpr
    LEFT JOIN hr_employment e ON e.student_id = s.id
        AND e.id = (SELECT MAX(e2.id) FROM hr_employment e2 WHERE e2.student_id = s.id)
    WHERE $whereStr
    ORDER BY d.department_name, g.name, s.surname, s.name, s.patronymic
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Форматирование данных ─────────────────────────────────
function statusText(?string $s): string {
    return match($s) {
        'employed'   => 'Трудоустроен',
        'unemployed' => 'Не трудоустроен',
        'studying'   => 'Продолжает учёбу',
        'decree'     => 'В декрете',
        'military'   => 'Военная служба',
        'unknown'    => 'Неизвестно',
        default      => '—',
    };
}
function empTypeText(?string $t): string {
    return match($t) {
        'full_time'     => 'Полная занятость',
        'part_time'     => 'Частичная занятость',
        'contract'      => 'Договор/контракт',
        'self_employed' => 'Самозанятый',
        'other'         => 'Прочее',
        default         => '—',
    };
}
function fmtDate(?string $d): string {
    if (!$d) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d.m.Y') : $d;
}
function yesNo($v): string {
    return ((int)$v === 1) ? 'Да' : 'Нет';
}
function valueOrDash($v): string {
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : '—';
}
function fullName(array $row): string {
    return trim(preg_replace('/\s+/u', ' ', ($row['surname'] ?? '') . ' ' . ($row['name'] ?? '') . ' ' . ($row['patronymic'] ?? '')));
}
function exportRows(array $students): array {
    $headers = [
        '№', 'ФИО', 'Отделение', 'Группа', 'Тип группы', 'Год выпуска', 'Код специальности', 'Специальность',
        'Статус', 'Организация', 'Должность', 'Дата трудоустройства',
        'Тип занятости', 'По специальности', 'Примечания'
    ];

    $rows = [];
    foreach ($students as $i => $row) {
        $gradYear = $row['employment_graduation_year'] ?: $row['grad_year'];
        $rows[] = [
            (string)($i + 1),
            valueOrDash(fullName($row)),
            valueOrDash($row['department_name'] ?? ''),
            valueOrDash($row['group_name'] ?? ''),
            match($row['group_state'] ?? '') { 'current' => 'Группа', 'previous' => 'Выпускники', 'archive' => 'Архив', default => '—' },
            valueOrDash($gradYear),
            valueOrDash($row['specialty_code'] ?? ''),
            valueOrDash($row['specialty_name'] ?? ''),
            statusText($row['status'] ?? null),
            valueOrDash($row['employer_name'] ?? ''),
            valueOrDash($row['position'] ?? ''),
            fmtDate($row['employment_date'] ?? null),
            empTypeText($row['employment_type'] ?? null),
            yesNo($row['is_by_specialty'] ?? 0),
            valueOrDash($row['notes'] ?? ''),
        ];
    }

    return [$headers, $rows];
}
function xmlText($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
function colName(int $index): string {
    $name = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = intdiv($index - $mod, 26);
    }
    return $name;
}
function downloadFileName(string $base, string $ext): string {
    return $base . '-' . date('Y-m-d') . '.' . $ext;
}

[$headers, $rows] = exportRows($students);

// ════════════════════════════════════════════════════════════
// CSV
// ════════════════════════════════════════════════════════════
if ($format === 'csv') {
    $filename = downloadFileName('hr-analitika', 'csv');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel корректно открывает кириллицу
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

// ════════════════════════════════════════════════════════════
// XLSX / Excel
// ════════════════════════════════════════════════════════════
if ($format === 'excel' || $format === 'xlsx') {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        die('Для экспорта Excel требуется расширение PHP ZipArchive. Включите php_zip в OpenServer.');
    }

    $filename = downloadFileName('hr-analitika', 'xlsx');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        die('Не удалось создать Excel-файл');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="HR-Аналитика" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>
<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs>
</styleSheet>');

    $maxRow = max(1, count($rows) + 1);
    $lastCol = colName(count($headers) - 1);
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<dimension ref="A1:' . $lastCol . $maxRow . '"/>
<cols>';
    $widths = [6, 30, 16, 12, 18, 38, 22, 30, 26, 20, 22, 18, 45];
    foreach ($widths as $i => $width) {
        $col = $i + 1;
        $sheetXml .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
    }
    $sheetXml .= '</cols><sheetData>';

    $sheetXml .= '<row r="1">';
    foreach ($headers as $i => $header) {
        $cellRef = colName($i) . '1';
        $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr" s="1"><is><t>' . xmlText($header) . '</t></is></c>';
    }
    $sheetXml .= '</row>';

    foreach ($rows as $r => $row) {
        $rowNum = $r + 2;
        $sheetXml .= '<row r="' . $rowNum . '">';
        foreach ($row as $i => $value) {
            $cellRef = colName($i) . $rowNum;
            $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr" s="0"><is><t>' . xmlText($value) . '</t></is></c>';
        }
        $sheetXml .= '</row>';
    }

    $sheetXml .= '</sheetData><autoFilter ref="A1:' . $lastCol . $maxRow . '"/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

    $created = gmdate('Y-m-d\TH:i:s\Z');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>СВГТК Портал</dc:creator>
<dc:title>HR-Аналитика</dc:title>
<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>
</cp:coreProperties>');

    $zip->close();
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// ════════════════════════════════════════════════════════════
// DOCX / Word — оставлен для совместимости со старой кнопкой
// ════════════════════════════════════════════════════════════
if ($format === 'word' || $format === 'docx') {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        die('Для экспорта Word требуется расширение PHP ZipArchive. Включите php_zip в OpenServer.');
    }

    $filename = downloadFileName('hr-analitika', 'docx');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $tmpFile = tempnam(sys_get_temp_dir(), 'docx_');
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        die('Не удалось создать Word-файл');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>');

    $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t>Отчёт о трудоустройстве выпускников</w:t></w:r></w:p>
<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>СВГТК им. Абая Кунанбаева</w:t></w:r></w:p>
<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>' . date('d.m.Y') . '</w:t></w:r></w:p>
<w:p><w:r><w:t>Всего записей: ' . count($rows) . '</w:t></w:r></w:p>';

    if ($fSearch !== '') {
        $docXml .= '<w:p><w:r><w:t>Поиск: ' . xmlText($fSearch) . '</w:t></w:r></w:p>';
    }

    $docXml .= '<w:tbl><w:tblPr><w:tblBorders>
<w:top w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:left w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:bottom w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:right w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:insideH w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:insideV w:val="single" w:sz="8" w:space="0" w:color="000000"/>
</w:tblBorders></w:tblPr>';

    $wordHeaders = ['ФИО', 'Группа', 'Статус', 'Организация', 'Должность', 'Дата', 'По спец.'];
    $docXml .= '<w:tr>';
    foreach ($wordHeaders as $h) {
        $docXml .= '<w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' . xmlText($h) . '</w:t></w:r></w:p></w:tc>';
    }
    $docXml .= '</w:tr>';

    foreach ($students as $row) {
        $data = [
            valueOrDash(fullName($row)),
            valueOrDash($row['group_name'] ?? ''),
            statusText($row['status'] ?? null),
            valueOrDash($row['employer_name'] ?? ''),
            valueOrDash($row['position'] ?? ''),
            fmtDate($row['employment_date'] ?? null),
            yesNo($row['is_by_specialty'] ?? 0),
        ];
        $docXml .= '<w:tr>';
        foreach ($data as $v) {
            $docXml .= '<w:tc><w:p><w:r><w:t>' . xmlText($v) . '</w:t></w:r></w:p></w:tc>';
        }
        $docXml .= '</w:tr>';
    }
    $docXml .= '</w:tbl><w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/></w:sectPr></w:body></w:document>';
    $zip->addFromString('word/document.xml', $docXml);

    $created = gmdate('Y-m-d\TH:i:s\Z');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>СВГТК Портал</dc:creator>
<dc:title>HR-Аналитика</dc:title>
<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>
</cp:coreProperties>');

    $zip->close();
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

http_response_code(400);
echo 'Неверный формат экспорта';
