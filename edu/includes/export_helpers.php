<?php
/**
 * Общие функции для оценок 0–100 и экспортов учебных документов.
 */

function edu_normalize_score($value): ?int
{
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) return null;
    $score = (int)$value;
    if ($score < 0 || $score > 100) return null;
    return $score;
}

function edu_score_scale(?int $score): array
{
    if ($score === null) return ['letter' => '', 'gpa' => '', 'traditional' => ''];

    if ($score >= 95) return ['letter' => 'A',  'gpa' => '4.00', 'traditional' => '5 (отлично)'];
    if ($score >= 90) return ['letter' => 'A-', 'gpa' => '3.67', 'traditional' => '5 (отлично)'];
    if ($score >= 85) return ['letter' => 'B+', 'gpa' => '3.33', 'traditional' => '4 (хорошо)'];
    if ($score >= 80) return ['letter' => 'B',  'gpa' => '3.00', 'traditional' => '4 (хорошо)'];
    if ($score >= 75) return ['letter' => 'B-', 'gpa' => '2.67', 'traditional' => '4 (хорошо)'];
    if ($score >= 70) return ['letter' => 'C+', 'gpa' => '2.33', 'traditional' => '4 (хорошо)'];
    if ($score >= 65) return ['letter' => 'C',  'gpa' => '2.00', 'traditional' => '3 (удовлетворительно)'];
    if ($score >= 60) return ['letter' => 'C-', 'gpa' => '1.67', 'traditional' => '3 (удовлетворительно)'];
    if ($score >= 55) return ['letter' => 'D+', 'gpa' => '1.33', 'traditional' => '3 (удовлетворительно)'];
    if ($score >= 50) return ['letter' => 'D',  'gpa' => '1.00', 'traditional' => '3 (удовлетворительно)'];
    return ['letter' => 'F', 'gpa' => '0.00', 'traditional' => '2 (неудовлетворительно)'];
}

function edu_score_letter(?int $score): string
{
    return edu_score_scale($score)['letter'];
}

function edu_score_gpa(?int $score): string
{
    return edu_score_scale($score)['gpa'];
}

function edu_score_traditional(?int $score): string
{
    return edu_score_scale($score)['traditional'];
}

function edu_score_badge_class(?int $score): string
{
    if ($score === null) return 'badge-gray';
    if ($score >= 90) return 'badge-green';
    if ($score >= 70) return 'badge-blue';
    if ($score >= 50) return 'badge-amber';
    return 'badge-red';
}



function edu_curriculum_export_lower($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["\xc2\xa0", "\r", "\n", "\t"], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function edu_curriculum_export_token($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["\xc2\xa0", ' ', "\r", "\n", "\t"], '', $value);
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function edu_curriculum_export_is_service_row(array $row): bool
{
    $name = edu_curriculum_export_lower(($row['component_name'] ?? '') !== '' ? $row['component_name'] : ($row['name'] ?? ''));
    $allText = edu_curriculum_export_lower(implode(' ', [
        $row['index_code'] ?? '',
        $row['module_type'] ?? '',
        $row['component_name'] ?? '',
        $row['name'] ?? '',
    ]));
    $indexCode = edu_curriculum_export_token($row['index_code'] ?? '');
    $moduleType = edu_curriculum_export_token($row['module_type'] ?? '');

    // Это строки календарно-учебной нагрузки, а не дисциплины студента.
    // В дипломной книге и личной карточке они не должны появляться как оценки.
    if (preg_match('/консультац/ui', $allText) && ($moduleType === 'К' || preg_match('/^К0*$/u', $indexCode) || preg_match('/не\s+более/ui', $allText))) {
        return true;
    }

    if (preg_match('/факультативн\w*\s+заняти/ui', $allText) && preg_match('/не\s+более/ui', $allText)) {
        return true;
    }

    if (preg_match('/промежуточн\w*\s+аттестаци/ui', $name) && ($moduleType === 'ПА' || preg_match('/^ПА\d*$/u', $indexCode) || preg_match('/^промежуточн\w*\s+аттестаци/ui', $name))) {
        return true;
    }

    if (preg_match('/итогов\w*\s+аттестаци/ui', $name) && ($moduleType === 'ИА' || preg_match('/^ИА\d*$/u', $indexCode) || preg_match('/^итогов\w*\s+аттестаци/ui', $name))) {
        return true;
    }

    return false;
}

function edu_curriculum_export_group_sort_key(string $groupTitle): int
{
    $group = edu_curriculum_export_lower($groupTitle);
    if (preg_match('/общеобразователь/ui', $group)) return 10;
    if (preg_match('/базов/ui', $group)) return 20;
    if (preg_match('/профессиональ/ui', $group)) return 30;
    if (preg_match('/факультатив/ui', $group)) return 40;
    return 90;
}

function edu_table_exists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$tableName]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        try {
            $safe = str_replace('`', '``', $tableName);
            $pdo->query("SELECT 1 FROM `{$safe}` LIMIT 1");
            return true;
        } catch (Throwable $ignored) {
            return false;
        }
    }
}

function edu_person_short_name(string $fullName): string
{
    $fullName = trim(preg_replace('/\s+/u', ' ', $fullName));
    if ($fullName === '') return '';

    $parts = preg_split('/\s+/u', $fullName) ?: [];
    $surname = $parts[0] ?? '';
    if ($surname === '') return '';

    $initials = '';
    for ($i = 1; $i < count($parts); $i++) {
        $part = trim($parts[$i]);
        if ($part === '') continue;
        $initial = function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
        $initials .= $initial . '.';
    }

    return trim($surname . ' ' . $initials);
}


function edu_export_table_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (function_exists('edu_table_column_exists')) {
        try {
            return (bool)edu_table_column_exists($pdo, $table, $column);
        } catch (Throwable $e) {
            // fall through to local check
        }
    }

    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM INFORMATION_SCHEMA.COLUMNS\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = ?\n              AND COLUMN_NAME = ?\n        ");
        $stmt->execute([$table, $column]);
        return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function edu_fetch_department_head_name_by_group(PDO $pdo, int $groupId, bool $short = false): string
{
    if ($groupId <= 0) {
        return '';
    }

    if (!edu_table_exists($pdo, 'edu_groups') || !edu_table_exists($pdo, 'users')) {
        return '';
    }

    if (!edu_export_table_column_exists($pdo, 'edu_groups', 'department_id') ||
        !edu_export_table_column_exists($pdo, 'users', 'is_department_head') ||
        !edu_export_table_column_exists($pdo, 'users', 'head_department_id')) {
        return '';
    }

    try {
        $stmt = $pdo->prepare("\n            SELECT COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS head_name\n            FROM edu_groups g\n            INNER JOIN users u\n                ON u.head_department_id = g.department_id\n               AND COALESCE(u.is_department_head, 0) = 1\n            WHERE g.id = ?\n              AND g.department_id IS NOT NULL\n            ORDER BY\n                CASE\n                    WHEN LOWER(TRIM(CAST(u.role AS CHAR))) IN ('teacher', 'преподаватель', '3') THEN 0\n                    ELSE 1\n                END,\n                u.full_name,\n                u.id\n            LIMIT 1\n        ");
        $stmt->execute([$groupId]);
        $name = trim((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        $name = '';
    }

    return $short ? edu_person_short_name($name) : $name;
}

function edu_fetch_director_name(PDO $pdo, bool $short = false): string
{
    try {
        $stmt = $pdo->query("
            SELECT COALESCE(NULLIF(TRIM(full_name), ''), username) AS director_name
            FROM users
            WHERE LOWER(TRIM(CAST(role AS CHAR))) IN ('director', 'директор', '2')
            ORDER BY
                CASE WHEN LOWER(TRIM(CAST(position AS CHAR))) LIKE '%директор%' THEN 0 ELSE 1 END,
                id
            LIMIT 1
        ");
        $name = trim((string)($stmt ? $stmt->fetchColumn() : ''));
    } catch (Throwable $e) {
        $name = '';
    }

    return $short ? edu_person_short_name($name) : $name;
}

function edu_score_traditional_mark(?int $score): string
{
    if ($score === null) return '';
    if ($score >= 90) return '5';
    if ($score >= 70) return '4';
    if ($score >= 50) return '3';
    return '2';
}

function edu_format_decimal($value, bool $comma = false): string
{
    if ($value === null || $value === '') return '';
    if (!is_numeric((string)$value)) return (string)$value;
    $num = (float)$value;
    $text = number_format($num, 2, '.', '');
    $text = rtrim(rtrim($text, '0'), '.');
    if ($text === '-0') $text = '0';
    return $comma ? str_replace('.', ',', $text) : $text;
}

function edu_safe_filename(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[\\\/\:\*\?"\<\>\|]+/u', '_', $value);
    $value = preg_replace('/\s+/u', '_', $value);
    return trim($value, '._') ?: 'document';
}

function edu_full_name(array $row): string
{
    return trim(($row['surname'] ?? '') . ' ' . ($row['name'] ?? '') . ' ' . ($row['patronymic'] ?? ''));
}

function edu_xml(string $value): string
{
    // Word строго проверяет XML внутри DOCX. Данные из БД/Excel/старых DOC могут
    // содержать управляющие символы или Unicode non-character вроде U+FFFE, из-за
    // чего Word открывает файл через восстановление. Перед вставкой в OOXML чистим
    // строку и экранируем спецсимволы.
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1251, CP1251, ISO-8859-1');
        if (is_string($converted)) {
            $value = $converted;
        }
    }

    // XML 1.0 legal chars: TAB, LF, CR, #x20-#xD7FF, #xE000-#xFFFD, #x10000-#x10FFFF.
    $clean = @preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
    if ($clean === null) {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($clean === false) {
            $clean = '';
        }
    }

    return htmlspecialchars($clean, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}

function edu_docx_run(string $text, bool $bold = false, int $size = 22): string
{
    $props = '<w:rPr><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>' . ($bold ? '<w:b/><w:bCs/>' : '') . '</w:rPr>';
    $parts = preg_split('/\R/u', $text);
    $xml = '';
    foreach ($parts as $idx => $part) {
        if ($idx > 0) $xml .= '<w:br/>';
        $xml .= '<w:t xml:space="preserve">' . edu_xml($part) . '</w:t>';
    }
    return '<w:r>' . $props . $xml . '</w:r>';
}

function edu_docx_p(string $text = '', string $align = 'left', bool $bold = false, int $size = 22): string
{
    $jc = in_array($align, ['left','center','right','both'], true) ? $align : 'left';
    return '<w:p><w:pPr><w:jc w:val="' . $jc . '"/></w:pPr>' . edu_docx_run($text, $bold, $size) . '</w:p>';
}

function edu_docx_cell(string $text, bool $bold = false, int $size = 18, string $align = 'center'): string
{
    return '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/><w:vAlign w:val="center"/></w:tcPr>' . edu_docx_p($text, $align, $bold, $size) . '</w:tc>';
}

function edu_docx_table(array $rows, int $headerRows = 1, int $fontSize = 18): string
{
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
         . '<w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
         . '<w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
         . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
         . '<w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
         . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
         . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
         . '</w:tblBorders></w:tblPr>';
    foreach ($rows as $rIdx => $row) {
        $xml .= '<w:tr>' . ($rIdx < $headerRows ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
        foreach ($row as $cell) {
            $xml .= edu_docx_cell((string)$cell, $rIdx < $headerRows, $fontSize, $rIdx < $headerRows ? 'center' : 'left');
        }
        $xml .= '</w:tr>';
    }
    return $xml . '</w:tbl>';
}

function edu_docx_make(string $bodyXml, string $path, bool $landscape = false): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Для DOCX-экспорта нужен PHP ZipArchive.');
    }

    $pgSz = $landscape
        ? '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
        : '<w:pgSz w:w="11906" w:h="16838"/>';

    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<w:body>' . $bodyXml
        . '<w:sectPr>' . $pgSz . '<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/></w:sectPr>'
        . '</w:body></w:document>';

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Не удалось создать DOCX-файл.');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>');
    $zip->addFromString('word/document.xml', $document);
    $zip->close();
}

function edu_send_file(string $path, string $downloadName, string $contentType): void
{
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: ' . $contentType);
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: max-age=0');
    readfile($path);
    @unlink($path);
    exit;
}

function edu_fetch_student_grades(PDO $pdo, int $studentId, bool $approvedOnly = false): array
{
    $statusSql = $approvedOnly ? "AND gs.status = 'approved'" : "AND (gs.status IS NULL OR gs.status <> 'rejected')";
    $stmt = $pdo->prepare("\n        SELECT eg.*, gs.type, gs.status,\n               sub.code AS subject_code, sub.name_ru AS subject_name, sub.hours_total,\n               sem.year_start, sem.year_end, sem.semester_num\n        FROM edu_grades eg\n        JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id\n        LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id\n        LEFT JOIN edu_semesters sem ON sem.id = gs.semester_id\n        WHERE eg.student_id = ? $statusSql\n        ORDER BY sem.year_start, sem.semester_num, sub.name_ru\n    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
