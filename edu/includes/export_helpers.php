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
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
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
    $statusSql = $approvedOnly ? "AND gs.status = 'approved'" : "AND gs.status <> 'rejected'";
    $stmt = $pdo->prepare("\n        SELECT eg.*, gs.type, gs.status,\n               sub.code AS subject_code, sub.name_ru AS subject_name, sub.hours_total,\n               sem.year_start, sem.year_end, sem.semester_num\n        FROM edu_grades eg\n        JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id\n        LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id\n        LEFT JOIN edu_semesters sem ON sem.id = gs.semester_id\n        WHERE eg.student_id = ? $statusSql\n        ORDER BY sem.year_start, sem.semester_num, sub.name_ru\n    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
