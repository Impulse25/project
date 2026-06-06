<?php
/**
 * edu/vedomost_generate.php
 * Формирование зачётной/экзаменационной ведомости по дисциплине из РУПл.
 */
require 'includes/auth.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/export_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$role       = edu_current_role();
$userId     = edu_current_user_id();
$isAdmin    = edu_is_admin();
$isDirector = edu_is_director();
$isTeacher  = edu_is_teacher();

if (!in_array($role, ['admin', 'director', 'teacher'], true)) {
    header('Location: index.php');
    exit;
}

$message = '';
$msgType = '';
$currentGroupId = (int)($_POST['group_id'] ?? $_GET['group_id'] ?? 0);
$currentModuleId = (int)($_POST['module_id'] ?? 0);

function edu_semester_numbers($value): array
{
    if ($value === null || $value === '') return [];
    preg_match_all('/\d+/u', (string)$value, $m);
    $nums = [];
    foreach ($m[0] ?? [] as $n) {
        $n = (int)$n;
        if ($n >= 1 && $n <= 12) $nums[] = $n;
    }
    return array_values(array_unique($nums));
}


function edu_module_norm_token($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    if (function_exists('mb_strtoupper')) return mb_strtoupper($value, 'UTF-8');
    return strtoupper($value);
}

function edu_module_lower($value): string
{
    $value = trim((string)$value);
    if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
    return strtolower($value);
}

function edu_is_assessable_module(array $module): bool
{
    // Не выводим только служебные строки РУПл.
    // Ф1, Ф2, ... являются отдельными факультативными дисциплинами и
    // должны попадать в выбор оценок и итоговые ведомости по своим семестрам.
    $code = edu_module_norm_token($module['index_code'] ?? '');
    $type = edu_module_norm_token($module['module_type'] ?? '');
    $name = edu_module_lower($module['name'] ?? '');

    // Родительские разделы РУПл вида "ООМ 1", "БМ 2", "ПМ 8" не являются
    // отдельными дисциплинами. В ведомости должны попадать только подразделы:
    // "ООМ 1.1", "БМ 2.3", "ПМ 8.1" и т.п.
    if ($code !== '' && preg_match('/^(ООМ|БМ|ПМ)\d+$/u', $code)) {
        return false;
    }

    if ($code !== '' && (preg_match('/^ПА\d*$/u', $code) || preg_match('/^К\d*$/u', $code) || $code === 'Ф')) {
        return false;
    }
    if ($type !== '' && (preg_match('/^ПА\d*$/u', $type) || preg_match('/^К\d*$/u', $type))) {
        return false;
    }

    if ($name !== '') {
        foreach (['промежуточная аттестация', 'консультац', 'факультативные занятия'] as $needle) {
            if (strpos($name, $needle) !== false) {
                return false;
            }
        }
    }

    return true;
}

function edu_module_effective_semesters(array $module): array
{
    // ВАЖНО: принадлежность дисциплины к семестру для выбора и ведомостей
    // определяем по фактическому распределению часов из edu_curriculum_distribution.
    // Поля экзамена/зачёта/контрольной используем только если распределения часов
    // у строки вообще нет. Иначе дисциплины из 3-8 семестров могут ошибочно
    // попадать в ведомость 1 семестра только из-за цифры в поле контроля.
    $dist = [];
    foreach (edu_semester_numbers($module['semesters'] ?? '') as $n) {
        if ($n >= 1 && $n <= 8) $dist[] = $n;
    }
    if ($dist) {
        sort($dist);
        return array_values(array_unique($dist));
    }

    $items = [];
    foreach (['exam_semester', 'credit_semester', 'control_work'] as $key) {
        foreach (edu_semester_numbers($module[$key] ?? '') as $n) {
            if ($n >= 1 && $n <= 8) $items[] = $n;
        }
    }
    sort($items);
    return array_values(array_unique($items));
}

function edu_module_effective_semester_csv(array $module): string
{
    return implode(',', edu_module_effective_semesters($module));
}

function edu_pick_semester(array $module, string $examType): int
{
    $primary = $examType === 'экзамен' ? ($module['exam_semester'] ?? null) : ($module['credit_semester'] ?? null);
    $secondary = $examType === 'экзамен' ? ($module['credit_semester'] ?? null) : ($module['exam_semester'] ?? null);

    $nums = edu_semester_numbers($primary);
    if ($nums) return $nums[0];

    $nums = edu_semester_numbers($secondary);
    if ($nums) return $nums[0];

    $nums = edu_module_effective_semesters($module);
    if ($nums) return $nums[0];

    return 0;
}

function edu_docx_xpath(DOMDocument $dom): DOMXPath
{
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    return $xpath;
}

function edu_docx_node_text(DOMXPath $xpath, DOMNode $node): string
{
    $text = '';
    foreach ($xpath->query('.//w:t', $node) as $t) {
        $text .= $t->nodeValue;
    }
    return $text;
}

function edu_docx_set_node_text(DOMDocument $dom, DOMXPath $xpath, DOMElement $node, string $text): void
{
    $texts = [];
    foreach ($xpath->query('.//w:t', $node) as $t) {
        $texts[] = $t;
    }

    if (!$texts) {
        $p = $node->localName === 'p' ? $node : null;
        if (!$p) {
            $existing = $xpath->query('./w:p', $node)->item(0);
            $p = $existing instanceof DOMElement ? $existing : $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
            if (!$existing) $node->appendChild($p);
        }
        $r = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
        $t = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $t->nodeValue = $text;
        $r->appendChild($t);
        $p->appendChild($r);
        return;
    }

    $lines = preg_split('/\R/u', $text);
    if ($lines === false || count($lines) === 0) $lines = [$text];

    foreach ($texts as $idx => $t) {
        $t->nodeValue = $idx === 0 ? (string)$lines[0] : '';
        if ($idx === 0) {
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $run = $t->parentNode instanceof DOMElement ? $t->parentNode : null;
            if ($run) {
                for ($i = 1; $i < count($lines); $i++) {
                    $br = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:br');
                    $nt = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
                    $nt->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                    $nt->nodeValue = (string)$lines[$i];
                    $run->appendChild($br);
                    $run->appendChild($nt);
                }
            }
        }
    }
}

function edu_docx_clear_row(DOMXPath $xpath, DOMElement $row): void
{
    foreach ($xpath->query('.//w:t', $row) as $t) {
        $t->nodeValue = '';
    }
}

function edu_docx_fill_cell(DOMDocument $dom, DOMXPath $xpath, ?DOMElement $cell, $text): void
{
    if (!$cell) return;
    $paragraph = $xpath->query('./w:p', $cell)->item(0);
    if (!$paragraph instanceof DOMElement) {
        $paragraph = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
        $cell->appendChild($paragraph);
    }
    edu_docx_set_node_text($dom, $xpath, $paragraph, (string)($text ?? ''));
}

function edu_docx_direct_children(DOMXPath $xpath, DOMElement $node, string $query): array
{
    $items = [];
    foreach ($xpath->query($query, $node) as $item) {
        if ($item instanceof DOMElement) $items[] = $item;
    }
    return $items;
}

function edu_build_vedomost_docx_php(array $data, string $outFile): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('В PHP не включено расширение ZipArchive. Включите php_zip или используйте PHP-сборку с ZipArchive.');
    }

    $template = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'vedomost_template.docx';
    if (!is_file($template)) {
        throw new RuntimeException('Шаблон ведомости не найден: ' . $template);
    }

    $tmpCopy = $outFile;
    if (!copy($template, $tmpCopy)) {
        throw new RuntimeException('Не удалось создать временную копию шаблона ведомости.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpCopy) !== true) {
        throw new RuntimeException('Не удалось открыть DOCX-шаблон ведомости.');
    }

    $documentXml = $zip->getFromName('word/document.xml');
    if ($documentXml === false || $documentXml === '') {
        $zip->close();
        throw new RuntimeException('В DOCX-шаблоне не найден word/document.xml.');
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;
    libxml_use_internal_errors(true);
    if (!$dom->loadXML($documentXml)) {
        $zip->close();
        $errors = libxml_get_errors();
        libxml_clear_errors();
        throw new RuntimeException('Не удалось прочитать XML DOCX-шаблона' . ($errors ? ': ' . trim($errors[0]->message) : '.'));
    }
    libxml_clear_errors();

    $xpath = edu_docx_xpath($dom);

    $discipline = trim((string)($data['discipline'] ?? ''));
    $groupName = trim((string)($data['group_name'] ?? ''));
    $specialty = trim((string)($data['specialty'] ?? ''));
    $qualification = trim((string)($data['qualification'] ?? ''));
    $teacher = trim((string)($data['teacher'] ?? ''));
    $courseNum = trim((string)($data['course_num'] ?? ''));
    $examType = (string)($data['exam_type'] ?? 'зачёт');
    $students = is_array($data['students'] ?? null) ? $data['students'] : [];

    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $txt = edu_docx_node_text($xpath, $paragraph);

        if (mb_stripos($txt, 'ЗАЧЕТНАЯ ВЕДОМОСТЬ', 0, 'UTF-8') !== false || mb_stripos($txt, 'ЗАЧЁТНАЯ ВЕДОМОСТЬ', 0, 'UTF-8') !== false || mb_stripos($txt, 'ЭКЗАМЕНАЦИОННАЯ ВЕДОМОСТЬ', 0, 'UTF-8') !== false) {
            edu_docx_set_node_text($dom, $xpath, $paragraph, $examType === 'экзамен' ? 'ЭКЗАМЕНАЦИОННАЯ ВЕДОМОСТЬ' : 'ЗАЧЕТНАЯ ВЕДОМОСТЬ');
            continue;
        }
        if (mb_stripos($txt, 'Индекс модуля', 0, 'UTF-8') !== false && mb_stripos($txt, 'модулю', 0, 'UTF-8') !== false) {
            edu_docx_set_node_text($dom, $xpath, $paragraph, 'Индекс модуля, по дисциплине и (или) модулю ' . $discipline);
            continue;
        }
        if (mb_stripos($txt, 'курса группы', 0, 'UTF-8') !== false) {
            edu_docx_set_node_text($dom, $xpath, $paragraph, '«' . $courseNum . '» курса группы ' . $groupName);
            continue;
        }
        if (str_starts_with(trim($txt), 'Специальность:')) {
            edu_docx_set_node_text($dom, $xpath, $paragraph, 'Специальность: ' . $specialty);
            continue;
        }
        if (str_starts_with(trim($txt), 'Квалификация:')) {
            edu_docx_set_node_text($dom, $xpath, $paragraph, 'Квалификация: ' . $qualification);
            continue;
        }
        if (str_starts_with(trim($txt), 'Преподаватель:')) {
            edu_docx_set_node_text($dom, $xpath, $paragraph, 'Преподаватель: ' . $teacher);
            continue;
        }
    }

    $table = $xpath->query('//w:tbl')->item(0);
    if (!$table instanceof DOMElement) {
        $zip->close();
        throw new RuntimeException('В шаблоне ведомости не найдена таблица студентов.');
    }

    $headerRows = 3;
    $rows = edu_docx_direct_children($xpath, $table, './w:tr');
    if (count($rows) <= $headerRows) {
        $zip->close();
        throw new RuntimeException('В таблице шаблона нет строки-образца для студентов.');
    }

    $templateRow = $rows[$headerRows]->cloneNode(true);
    $currentDataRows = count($rows) - $headerRows;
    $neededDataRows = count($students);

    while ($currentDataRows < $neededDataRows) {
        $table->appendChild($templateRow->cloneNode(true));
        $currentDataRows++;
    }
    while ($currentDataRows > $neededDataRows) {
        $rows = edu_docx_direct_children($xpath, $table, './w:tr');
        $last = end($rows);
        if ($last instanceof DOMElement) {
            $table->removeChild($last);
        }
        $currentDataRows--;
    }

    $rows = edu_docx_direct_children($xpath, $table, './w:tr');
    foreach ($students as $idx => $student) {
        if (!isset($rows[$headerRows + $idx]) || !$rows[$headerRows + $idx] instanceof DOMElement) continue;
        $row = $rows[$headerRows + $idx];
        edu_docx_clear_row($xpath, $row);
        $cells = edu_docx_direct_children($xpath, $row, './w:tc');

        edu_docx_fill_cell($dom, $xpath, $cells[0] ?? null, (string)($idx + 1));
        if (count($cells) >= 15) {
            edu_docx_fill_cell($dom, $xpath, $cells[1] ?? null, $student['rating_score'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[2] ?? null, $student['rating_letter'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[3] ?? null, $student['rating_gpa'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[4] ?? null, $student['full_name'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[5] ?? null, $student['written_score'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[6] ?? null, $student['written_letter'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[7] ?? null, $student['written_gpa'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[8] ?? null, $student['oral_score'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[9] ?? null, $student['oral_letter'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[10] ?? null, $student['oral_gpa'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[11] ?? null, $student['total_score'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[12] ?? null, $student['total_letter'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[13] ?? null, $student['total_gpa'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[14] ?? null, '');
        } else {
            edu_docx_fill_cell($dom, $xpath, $cells[1] ?? null, $student['rating_letter'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[2] ?? null, $student['rating_gpa'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[3] ?? null, $student['ticket_num'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[4] ?? null, $student['full_name'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[5] ?? null, $student['written_letter'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[6] ?? null, $student['written_gpa'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[7] ?? null, $student['oral_letter'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[8] ?? null, $student['oral_gpa'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[9] ?? null, $student['total_gpa'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[10] ?? null, $student['total_letter'] ?? '');
            edu_docx_fill_cell($dom, $xpath, $cells[11] ?? null, $student['total_gpa2'] ?? '');
        }
    }


    $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
    foreach ($xpath->query('//w:tbl') as $footerTable) {
        if (!$footerTable instanceof DOMElement || $footerTable === $table) continue;
        $txt = edu_docx_node_text($xpath, $footerTable);
        if (mb_stripos($txt, 'Количество оценок', 0, 'UTF-8') === false) continue;
        $cells = $xpath->query('.//w:tc', $footerTable);
        foreach ($cells as $cell) {
            if (!$cell instanceof DOMElement) continue;
            $cellText = edu_docx_node_text($xpath, $cell);
            if (mb_stripos($cellText, 'Количество оценок', 0, 'UTF-8') !== false) {
                edu_docx_set_node_text($dom, $xpath, $cell, "Количество оценок:
А, А- ___" . (int)($counts['excellent'] ?? 0) . "___
В+, В, В-, С+ ___" . (int)($counts['good'] ?? 0) . "___
С, С- D+, D ___" . (int)($counts['satisfactory'] ?? 0) . "___
F ___" . (int)($counts['fail'] ?? 0) . "___");
            }
        }
    }

    $newXml = $dom->saveXML();
    if ($newXml === false || $newXml === '') {
        $zip->close();
        throw new RuntimeException('Не удалось сохранить XML ведомости.');
    }

    $zip->addFromString('word/document.xml', $newXml);
    $zip->close();

    if (!is_file($outFile) || filesize($outFile) === 0) {
        throw new RuntimeException('DOCX-файл не был создан.');
    }
}

function edu_decode_process_output(string $output): string
{
    $output = trim(str_replace(["\r\n", "\r"], "\n", $output));
    if ($output === '') return '';

    if (function_exists('mb_check_encoding') && !mb_check_encoding($output, 'UTF-8')) {
        foreach (['CP866', 'Windows-1251', 'CP1251'] as $encoding) {
            $converted = @mb_convert_encoding($output, 'UTF-8', $encoding);
            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return trim($converted);
            }
        }
    }

    return $output;
}

function edu_run_vedomost_builder(string $jsonFile, string $outFile): array
{
    $phpError = '';
    try {
        $raw = file_get_contents($jsonFile);
        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException('JSON-данные ведомости не найдены.');
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('JSON-данные ведомости имеют неверный формат.');
        }
        edu_build_vedomost_docx_php($data, $outFile);
        return [true, 'OK:' . $outFile];
    } catch (Throwable $e) {
        @unlink($outFile);
        $phpError = $e->getMessage();
    }

    $script = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'vedomost_builder.py';
    if (!is_file($script)) {
        return [false, $phpError ?: 'Скрипт генерации не найден: ' . $script];
    }

    $candidates = [];
    $envPython = getenv('PYTHON');
    if ($envPython) $candidates[] = $envPython;

    if (PHP_OS_FAMILY === 'Windows') {
        $candidates[] = 'py -3';
        $candidates[] = 'python';
        $candidates[] = 'python3';
    } else {
        $candidates[] = 'python3';
        $candidates[] = 'python';
    }

    $lastOutput = '';
    foreach (array_values(array_unique($candidates)) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') continue;

        if (str_contains($candidate, DIRECTORY_SEPARATOR) || preg_match('/\.exe$/i', $candidate)) {
            $pythonCmd = escapeshellarg($candidate);
        } else {
            $pythonCmd = $candidate;
        }

        $cmd = $pythonCmd . ' ' . escapeshellarg($script)
             . ' --json ' . escapeshellarg($jsonFile)
             . ' --out ' . escapeshellarg($outFile)
             . ' 2>&1';
        $output = edu_decode_process_output((string)shell_exec($cmd));
        $lastOutput = $output ?: $lastOutput;

        if (is_file($outFile) && str_starts_with(trim($output), 'OK:')) {
            return [true, $output];
        }
        @unlink($outFile);
    }

    $details = [];
    if ($phpError !== '') $details[] = 'PHP-генератор: ' . $phpError;
    if (trim($lastOutput) !== '') $details[] = 'Python-генератор: ' . trim($lastOutput);
    if (!$details) $details[] = 'не удалось запустить генератор DOCX.';
    return [false, implode(' ', $details)];
}

function edu_send_saved_docx(string $path, string $downloadName): void
{
    if (ob_get_length()) @ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: max-age=0');
    readfile($path);
    exit;
}

function edu_load_disciplines(PDO $pdo, int $curriculumId): array
{
    $stmt = $pdo->prepare("\n        SELECT m.id, m.index_code, m.name, m.component_name, m.module_type,
               m.exam_semester, m.credit_semester, m.control_work,
               m.total_hours, m.credits,
               GROUP_CONCAT(DISTINCT d.semester_num ORDER BY d.semester_num SEPARATOR ',') AS semesters
        FROM edu_curriculum_modules m
        LEFT JOIN edu_curriculum_distribution d ON d.module_id = m.id AND d.hours > 0
        WHERE m.curriculum_id = ?
          AND m.is_summary = 0
          AND m.index_code <> ''
          AND (TRIM(COALESCE(m.name, '')) <> '' OR TRIM(COALESCE(m.component_name, '')) <> '')
          AND LOWER(TRIM(COALESCE(m.name, ''))) NOT LIKE 'итого%'
          AND LOWER(TRIM(COALESCE(m.component_name, ''))) NOT LIKE 'итого%'
          AND (m.module_type IS NULL OR m.module_type <> 'ИТОГО')
          AND (
                COALESCE(m.total_hours, 0) > 0
                OR COALESCE(m.credits, 0) > 0
                OR TRIM(COALESCE(m.exam_semester, '')) <> ''
                OR TRIM(COALESCE(m.credit_semester, '')) <> ''
                OR TRIM(COALESCE(m.control_work, '')) <> ''
                OR d.hours > 0
          )
        GROUP BY m.id, m.index_code, m.name, m.component_name, m.module_type, m.exam_semester, m.credit_semester,
                 m.control_work, m.total_hours, m.credits, m.sort_order
        ORDER BY m.sort_order
    ");
    $stmt->execute([$curriculumId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $component = trim((string)($row['component_name'] ?? ''));
        if ($component !== '') $row['name'] = $component;
    }
    unset($row);
    $rows = array_values(array_filter($rows, static function (array $row): bool {
        return edu_is_assessable_module($row) && (bool)edu_module_effective_semesters($row);
    }));
    foreach ($rows as &$row) {
        $row['semesters'] = edu_module_effective_semester_csv($row);
    }
    unset($row);
    return $rows;
}

function edu_load_all_curriculum_modules(PDO $pdo, int $curriculumId): array
{
    $stmt = $pdo->prepare("
        SELECT m.id, m.index_code, m.name, m.component_name, m.module_type,
               m.exam_semester, m.credit_semester, m.control_work,
               m.total_hours, m.credits,
               GROUP_CONCAT(DISTINCT d.semester_num ORDER BY d.semester_num SEPARATOR ',') AS semesters
        FROM edu_curriculum_modules m
        LEFT JOIN edu_curriculum_distribution d ON d.module_id = m.id AND d.hours > 0
        WHERE m.curriculum_id = ?
          AND m.is_summary = 0
          AND m.index_code <> ''
          AND (TRIM(COALESCE(m.name, '')) <> '' OR TRIM(COALESCE(m.component_name, '')) <> '')
          AND LOWER(TRIM(COALESCE(m.name, ''))) NOT LIKE 'итого%'
          AND LOWER(TRIM(COALESCE(m.component_name, ''))) NOT LIKE 'итого%'
          AND (m.module_type IS NULL OR m.module_type <> 'ИТОГО')
          AND (
                COALESCE(m.total_hours, 0) > 0
                OR COALESCE(m.credits, 0) > 0
                OR TRIM(COALESCE(m.exam_semester, '')) <> ''
                OR TRIM(COALESCE(m.credit_semester, '')) <> ''
                OR TRIM(COALESCE(m.control_work, '')) <> ''
                OR d.hours > 0
          )
        GROUP BY m.id, m.index_code, m.name, m.component_name, m.module_type, m.exam_semester, m.credit_semester,
                 m.control_work, m.total_hours, m.credits, m.sort_order
        ORDER BY m.sort_order
    ");
    $stmt->execute([$curriculumId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $component = trim((string)($row['component_name'] ?? ''));
        if ($component !== '') $row['name'] = $component;
        $row['semesters'] = edu_module_effective_semester_csv($row);
    }
    unset($row);
    return array_values(array_filter($rows, static function (array $row): bool {
        return edu_is_assessable_module($row) && (bool)edu_module_effective_semesters($row);
    }));
}

function edu_module_in_semester(array $module, int $semNum): bool
{
    return in_array($semNum, edu_module_effective_semesters($module), true);
}

function edu_modules_for_semester(array $modules, int $semNum): array
{
    return array_values(array_filter($modules, fn($m) => edu_is_assessable_module($m) && edu_module_in_semester($m, $semNum)));
}


function edu_load_curriculum_modules_for_semester(PDO $pdo, int $curriculumId, int $semNum): array
{
    if ($curriculumId <= 0 || $semNum < 1 || $semNum > 8) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT m.id, m.index_code, m.name, m.component_name, m.module_type,
               m.exam_semester, m.credit_semester, m.control_work,
               m.total_hours, m.credits,
               GROUP_CONCAT(DISTINCT CASE WHEN d.hours > 0 AND d.semester_num BETWEEN 1 AND 8 THEN d.semester_num END ORDER BY d.semester_num SEPARATOR ',') AS semesters,
               SUM(CASE WHEN d.semester_num = ? THEN COALESCE(d.hours, 0) ELSE 0 END) AS semester_hours,
               SUM(COALESCE(d.hours, 0)) AS distributed_hours
        FROM edu_curriculum_modules m
        LEFT JOIN edu_curriculum_distribution d ON d.module_id = m.id
        WHERE m.curriculum_id = ?
          AND m.is_summary = 0
          AND m.index_code <> ''
          AND (TRIM(COALESCE(m.name, '')) <> '' OR TRIM(COALESCE(m.component_name, '')) <> '')
          AND LOWER(TRIM(COALESCE(m.name, ''))) NOT LIKE 'итого%'
          AND LOWER(TRIM(COALESCE(m.component_name, ''))) NOT LIKE 'итого%'
          AND (m.module_type IS NULL OR m.module_type <> 'ИТОГО')
          AND (
                COALESCE(m.total_hours, 0) > 0
                OR COALESCE(m.credits, 0) > 0
                OR TRIM(COALESCE(m.exam_semester, '')) <> ''
                OR TRIM(COALESCE(m.credit_semester, '')) <> ''
                OR TRIM(COALESCE(m.control_work, '')) <> ''
                OR d.hours > 0
          )
        GROUP BY m.id, m.index_code, m.name, m.component_name, m.module_type, m.exam_semester, m.credit_semester,
                 m.control_work, m.total_hours, m.credits, m.sort_order
        ORDER BY m.sort_order
    ");
    $stmt->execute([$semNum, $curriculumId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $component = trim((string)($row['component_name'] ?? ''));
        if ($component !== '') $row['name'] = $component;
        if (!edu_is_assessable_module($row)) {
            continue;
        }

        $effectiveSemesters = edu_module_effective_semesters($row);
        if (!$effectiveSemesters || !in_array($semNum, $effectiveSemesters, true)) {
            continue;
        }

        $hasSemesterHours = ((float)($row['semester_hours'] ?? 0) > 0);
        $hasAnyDistribution = ((float)($row['distributed_hours'] ?? 0) > 0);

        if ($hasAnyDistribution) {
            if (!$hasSemesterHours) {
                continue;
            }
        } else {
            $hasControlInSemester = in_array($semNum, edu_semester_numbers($row['exam_semester'] ?? null), true)
                || in_array($semNum, edu_semester_numbers($row['credit_semester'] ?? null), true)
                || in_array($semNum, edu_semester_numbers($row['control_work'] ?? null), true);
            if (!$hasControlInSemester) {
                continue;
            }
        }

        $row['semesters'] = edu_module_effective_semester_csv($row);
        $rows[] = $row;
    }

    return $rows;
}

function edu_module_grade_type(array $module, ?int $semNum = null): string
{
    if ($semNum !== null && in_array($semNum, edu_semester_numbers($module['exam_semester'] ?? null), true)) return 'exam';
    if ($semNum !== null && in_array($semNum, edu_semester_numbers($module['credit_semester'] ?? null), true)) return 'credit';
    return !empty($module['exam_semester']) ? 'exam' : (!empty($module['credit_semester']) ? 'credit' : 'current');
}

function edu_summary_grade_result(?array $grade, string $type): array
{
    if (!$grade) {
        return ['score' => '', 'letter' => '', 'ok' => false, 'filled' => false, 'avg' => null, 'excellent' => false];
    }
    if (!empty($grade['absent'])) {
        return ['score' => 'н/я', 'letter' => '', 'ok' => false, 'filled' => true, 'avg' => null, 'excellent' => false];
    }

    $rawGrade = $grade['grade'] ?? null;
    if (in_array($type, ['credit', 'practice'], true) && !empty($grade['passed']) && ($rawGrade === null || $rawGrade === '')) {
        $scale = edu_score_scale(100);
        return ['score' => 100, 'letter' => $scale['letter'], 'ok' => true, 'filled' => true, 'avg' => 100, 'excellent' => true];
    }

    $score = edu_normalize_score($rawGrade);
    if ($score === null) {
        return ['score' => '', 'letter' => '', 'ok' => false, 'filled' => false, 'avg' => null, 'excellent' => false];
    }

    $scale = edu_score_scale($score);
    $letter = $scale['letter'];

    return [
        'score'     => $score,
        'letter'    => $letter,
        // Для листа стипендии студент допускается только при заполненных оценках
        // по всем дисциплинам семестра и без оценок ниже уровня «хорошо».
        'ok'        => $score >= 70,
        'filled'    => true,
        'avg'       => $score,
        'excellent' => $score >= 90,
    ];
}

function edu_vedomost_grade_has_value(array $row): bool
{
    return ($row['grade'] ?? null) !== null
        || !empty($row['passed'])
        || !empty($row['absent']);
}

function edu_vedomost_grade_timestamp(array $row): int
{
    $values = [
        $row['grade_updated_at'] ?? null,
        $row['sheet_updated_at'] ?? null,
        $row['grade_created_at'] ?? null,
        $row['sheet_created_at'] ?? null,
    ];
    foreach ($values as $value) {
        if ($value) {
            $ts = strtotime((string)$value);
            if ($ts !== false) return $ts;
        }
    }
    return 0;
}

function edu_vedomost_choose_grade(?array $current, array $candidate, int $semester): array
{
    if ($current === null) return $candidate;

    $candidateSem = (int)($candidate['linked_semester'] ?? 0);
    $currentSem = (int)($current['linked_semester'] ?? 0);
    $candidateExact = $candidateSem === $semester ? 1 : 0;
    $currentExact = $currentSem === $semester ? 1 : 0;
    if ($candidateExact !== $currentExact) {
        return $candidateExact > $currentExact ? $candidate : $current;
    }

    $candidateHas = edu_vedomost_grade_has_value($candidate) ? 1 : 0;
    $currentHas = edu_vedomost_grade_has_value($current) ? 1 : 0;
    if ($candidateHas !== $currentHas) {
        return $candidateHas > $currentHas ? $candidate : $current;
    }

    $candidateTs = edu_vedomost_grade_timestamp($candidate);
    $currentTs = edu_vedomost_grade_timestamp($current);
    if ($candidateTs !== $currentTs) {
        return $candidateTs > $currentTs ? $candidate : $current;
    }

    return ((int)($candidate['grade_id'] ?? 0) > (int)($current['grade_id'] ?? 0)) ? $candidate : $current;
}

function edu_load_selected_discipline_grades(PDO $pdo, int $groupId, array $module, int $semester): array
{
    $moduleId = (int)($module['id'] ?? 0);
    if ($groupId <= 0 || $moduleId <= 0) return [];

    $moduleCode = trim((string)($module['index_code'] ?? ''));
    $moduleName = trim((string)($module['name'] ?? ''));

    $stmt = $pdo->prepare("
        SELECT
            eg.id AS grade_id,
            eg.student_id,
            eg.grade,
            eg.passed,
            eg.absent,
            eg.created_at AS grade_created_at,
            eg.updated_at AS grade_updated_at,
            gs.id AS sheet_id,
            gs.group_id AS sheet_group_id,
            gs.curriculum_module_id AS sheet_module_id,
            gs.curriculum_semester AS sheet_semester,
            gs.created_at AS sheet_created_at,
            gs.updated_at AS sheet_updated_at,
            COALESCE(eg.curriculum_module_id, gs.curriculum_module_id) AS linked_module_id,
            COALESCE(eg.curriculum_semester, gs.curriculum_semester) AS linked_semester
        FROM edu_grades eg
        JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id
        JOIN edu_students st ON st.id = eg.student_id
        LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id
        WHERE st.group_id = ?
          AND (
                eg.curriculum_module_id = ?
                OR gs.curriculum_module_id = ?
                OR (
                    (eg.curriculum_module_id IS NULL OR eg.curriculum_module_id = 0)
                    AND (gs.curriculum_module_id IS NULL OR gs.curriculum_module_id = 0)
                    AND ? <> ''
                    AND TRIM(COALESCE(sub.code, '')) = ?
                )
                OR (
                    (eg.curriculum_module_id IS NULL OR eg.curriculum_module_id = 0)
                    AND (gs.curriculum_module_id IS NULL OR gs.curriculum_module_id = 0)
                    AND ? <> ''
                    AND TRIM(COALESCE(sub.name_ru, '')) = ?
                )
          )
          AND COALESCE(eg.curriculum_semester, gs.curriculum_semester) = ?
          AND (gs.status IS NULL OR gs.status <> 'rejected')
        ORDER BY eg.student_id, gs.updated_at, eg.updated_at, eg.id
    ");
    $stmt->execute([
        $groupId,
        $moduleId,
        $moduleId,
        $moduleCode,
        $moduleCode,
        $moduleName,
        $moduleName,
        $semester,
    ]);

    $grades = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentId = (int)$row['student_id'];
        if ($studentId <= 0) continue;
        $grades[$studentId] = edu_vedomost_choose_grade($grades[$studentId] ?? null, $row, $semester);
    }

    return $grades;
}

function edu_sheet_title(string $title, array $used): string
{
    $title = preg_replace('~[\\/\?\*\[\]:]+~u', ' ', trim($title));
    if ($title === null || trim($title) === '') $title = 'Лист';
    $title = mb_substr(trim($title), 0, 31);
    $base = $title;
    $i = 2;
    while (in_array($title, $used, true)) {
        $suffix = ' ' . $i++;
        $title = mb_substr($base, 0, max(1, 31 - mb_strlen($suffix))) . $suffix;
    }
    return $title;
}

function edu_academic_year_label(array $group, int $semNum): string
{
    $enrollment = (int)($group['enrollment_year'] ?? $group['year_started'] ?? date('Y'));
    if ($enrollment < 2000 || $enrollment > 2099) $enrollment = (int)date('Y');
    $start = $enrollment + (int)floor(max(0, $semNum - 1) / 2);
    return $start . '-' . ($start + 1);
}

function edu_course_number_for_semester(array $group, int $semNum): int
{
    $course = (int)($group['course'] ?? 0);
    if ($course < 1 || $course > 6) $course = (int)ceil(max(1, $semNum) / 2);
    return max(1, min(6, $course));
}

function edu_roman_course(int $course): string
{
    return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI'][$course] ?? (string)$course;
}

function edu_group_specialty_text(array $group): string
{
    return trim(($group['spec_code'] ?? $group['specialty_code'] ?? '') . ' ' . ($group['spec_name'] ?? $group['specialty_name'] ?? ''));
}

function edu_group_qualification_text(array $group): string
{
    $qualRaw = (string)($group['qualification'] ?? '');
    $qualArr = json_decode($qualRaw, true);
    return is_array($qualArr) ? implode('; ', $qualArr) : $qualRaw;
}

function edu_apply_summary_page_setup(Worksheet $sheet): void
{
    $sheet->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(0.28)->setRight(0.20)->setLeft(0.20)->setBottom(0.30);
    $sheet->freezePane('C6');
}

function edu_summary_module_caption(array $module): string
{
    $code = trim((string)($module['index_code'] ?? ''));
    $name = trim((string)($module['name'] ?? ''));
    return trim(($code !== '' ? $code . ' ' : '') . $name);
}

function edu_summary_module_group(array $module): string
{
    $code = trim((string)($module['index_code'] ?? ''));
    $type = trim((string)($module['module_type'] ?? ''));
    if ($type !== '') return $type;
    if ($code !== '' && preg_match('/^[А-ЯA-ZЁ]+/u', $code, $m)) return $m[0];
    return 'Дисциплины';
}

function edu_summary_is_exam_module(array $module, int $semNum): bool
{
    $examSemesters = edu_semester_numbers($module['exam_semester'] ?? null);
    if ($examSemesters) return in_array($semNum, $examSemesters, true);
    return false;
}

function edu_order_modules_for_summary(array $modules, int $semNum): array
{
    $indexed = [];
    foreach ($modules as $i => $module) {
        $module['_summary_original_order'] = $i;
        $module['_summary_is_exam'] = edu_summary_is_exam_module($module, $semNum) ? 1 : 0;
        $indexed[] = $module;
    }
    usort($indexed, static function (array $a, array $b): int {
        $cmp = ((int)($a['_summary_is_exam'] ?? 0)) <=> ((int)($b['_summary_is_exam'] ?? 0));
        if ($cmp !== 0) return $cmp;
        return ((int)($a['_summary_original_order'] ?? 0)) <=> ((int)($b['_summary_original_order'] ?? 0));
    });
    foreach ($indexed as &$module) {
        unset($module['_summary_original_order'], $module['_summary_is_exam']);
    }
    unset($module);
    return $indexed;
}

function edu_style_summary_table(Worksheet $sheet, string $range): void
{
    $sheet->getStyle($range)->getFont()->setName('Times New Roman')->setSize(10);
    $sheet->getStyle($range)->getAlignment()
        ->setWrapText(true)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

function edu_write_summary_section_headers(Worksheet $sheet, array $modules, int $semNum, int $topRow, int $groupRow, int $moduleRow, int $lastColIdx): void
{
    $lastCol = Coordinate::stringFromColumnIndex($lastColIdx);
    $lastModuleColIdx = $lastColIdx - 1;
    $lastModuleCol = Coordinate::stringFromColumnIndex($lastModuleColIdx);

    $sheet->mergeCells('A' . $topRow . ':A' . $moduleRow);
    $sheet->setCellValue('A' . $topRow, '№');
    $sheet->mergeCells('B' . $groupRow . ':B' . $moduleRow);
    $sheet->setCellValue('B' . $groupRow, 'Ф.И.О.');
    $sheet->mergeCells($lastCol . $groupRow . ':' . $lastCol . $moduleRow);
    $sheet->setCellValue($lastCol . $groupRow, 'Примечание');

    $examStartIdx = null;
    foreach ($modules as $idx => $module) {
        if (edu_summary_is_exam_module($module, $semNum)) {
            $examStartIdx = 3 + ($idx * 2);
            break;
        }
    }

    if ($examStartIdx === null) {
        $sheet->mergeCells('B' . $topRow . ':' . $lastModuleCol . $topRow);
        $sheet->setCellValue('B' . $topRow, 'Итоговые                                                    оценки');
    } else {
        if ($examStartIdx > 3) {
            $beforeExamCol = Coordinate::stringFromColumnIndex($examStartIdx - 1);
            $sheet->mergeCells('B' . $topRow . ':' . $beforeExamCol . $topRow);
            $sheet->setCellValue('B' . $topRow, 'Итоговые                                                    оценки');
        } else {
            $sheet->setCellValue('B' . $topRow, 'Итоговые                                                    оценки');
        }
        $examStartCol = Coordinate::stringFromColumnIndex($examStartIdx);
        $sheet->mergeCells($examStartCol . $topRow . ':' . $lastModuleCol . $topRow);
        $sheet->setCellValue($examStartCol . $topRow, 'Экзаменационные оценки');
    }

    $col = 3;
    $groupStart = 3;
    $groupLabel = null;
    foreach ($modules as $i => $module) {
        $currentGroup = edu_summary_module_group($module);
        if ($groupLabel === null) {
            $groupLabel = $currentGroup;
            $groupStart = $col;
        } elseif ($currentGroup !== $groupLabel) {
            $startCol = Coordinate::stringFromColumnIndex($groupStart);
            $endCol = Coordinate::stringFromColumnIndex($col - 1);
            $sheet->mergeCells($startCol . $groupRow . ':' . $endCol . $groupRow);
            $sheet->setCellValue($startCol . $groupRow, $groupLabel);
            $groupLabel = $currentGroup;
            $groupStart = $col;
        }

        $c1 = Coordinate::stringFromColumnIndex($col);
        $c2 = Coordinate::stringFromColumnIndex($col + 1);
        $sheet->mergeCells($c1 . $moduleRow . ':' . $c2 . $moduleRow);
        $sheet->setCellValue($c1 . $moduleRow, edu_summary_module_caption($module));
        $col += 2;
    }

    if ($groupLabel !== null) {
        $startCol = Coordinate::stringFromColumnIndex($groupStart);
        $endCol = Coordinate::stringFromColumnIndex($col - 1);
        $sheet->mergeCells($startCol . $groupRow . ':' . $endCol . $groupRow);
        $sheet->setCellValue($startCol . $groupRow, $groupLabel);
    }

    $sheet->getStyle('A' . $topRow . ':' . $lastCol . $moduleRow)->getFont()->setBold(true);
    edu_style_summary_table($sheet, 'A' . $topRow . ':' . $lastCol . $moduleRow);
}

function edu_write_summary_headers(Worksheet $sheet, array $group, array $modules, int $semNum, bool $scholarshipSheet = false): array
{
    $actualModuleCount = count($modules);
    $moduleCount = max(1, $actualModuleCount);
    $lastColIdx = 2 + ($moduleCount * 2) + 1;
    $lastCol = Coordinate::stringFromColumnIndex($lastColIdx);
    $lastModuleCol = Coordinate::stringFromColumnIndex($lastColIdx - 1);
    $year = edu_academic_year_label($group, $semNum);
    $course = edu_course_number_for_semester($group, $semNum);
    $specialty = edu_group_specialty_text($group);
    $qualification = edu_group_qualification_text($group);

    foreach ($sheet->getMergeCells() as $mergeRange) {
        $sheet->unmergeCells($mergeRange);
    }

    $sheet->getStyle('A1:' . $lastCol . '140')->getFont()->setName('Times New Roman')->setSize(10);
    $sheet->getStyle('A1:' . $lastCol . '140')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

    if ($scholarshipSheet) {
        for ($r = 1; $r <= 7; $r++) {
            $sheet->mergeCells('A' . $r . ':' . $lastCol . $r);
        }
        $sheet->setCellValue('A1', 'КГКП «Саранский высший гуманитарно-технический колледж имени Абая Кунанбаева»');
        $sheet->setCellValue('A2', 'ВЕДОМОСТЬ');
        $sheet->setCellValue('A3', 'на назначение стипендии');
        $sheet->setCellValue('A4', 'Учебный год  ' . $year . '  Семестр  ' . edu_roman_course($course));
        $sheet->setCellValue('A5', 'Специальность ' . $specialty);
        $sheet->setCellValue('A6', 'Квалификация ' . $qualification);
        $sheet->setCellValue('A7', 'Курс  ' . edu_roman_course($course) . '   Группа  ' . ($group['name'] ?? ''));
        $sheet->getStyle('A1:A7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:A3')->getFont()->setBold(true)->setSize(12);
        $topRow = 8;
        $groupRow = 9;
        $moduleRow = 10;
        $firstDataRow = 11;
    } else {
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->setCellValue('A1', 'СВОДНАЯ ВЕДОМОСТЬ УСПЕВАЕМОСТИ ');
        $sheet->setCellValue('A2', 'группа       ' . ($group['name'] ?? '') . '        за      ' . $semNum . '     семестр         ' . $year . '        учебного года     ');
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $topRow = 3;
        $groupRow = 4;
        $moduleRow = 5;
        $firstDataRow = 6;
    }

    if ($actualModuleCount > 0) {
        edu_write_summary_section_headers($sheet, $modules, $semNum, $topRow, $groupRow, $moduleRow, $lastColIdx);
    }

    $sheet->getColumnDimension('A')->setWidth(3.2);
    $sheet->getColumnDimension('B')->setWidth($scholarshipSheet ? 16.5 : 19.6);
    for ($i = 3; $i <= $lastColIdx - 1; $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($scholarshipSheet ? 4.6 : 5.35);
    }
    $sheet->getColumnDimension($lastCol)->setWidth(8.0);

    $sheet->getRowDimension($topRow)->setRowHeight($scholarshipSheet ? 14 : 16.5);
    $sheet->getRowDimension($groupRow)->setRowHeight($scholarshipSheet ? 34 : 42);
    $sheet->getRowDimension($moduleRow)->setRowHeight($scholarshipSheet ? 130 : 97);
    $sheet->getStyle('A' . $groupRow . ':' . $lastCol . $moduleRow)->getAlignment()->setTextRotation(0);
    $sheet->getStyle('C' . $moduleRow . ':' . $lastModuleCol . $moduleRow)->getAlignment()->setTextRotation(0);

    edu_apply_summary_page_setup($sheet);
    if ($scholarshipSheet) {
        $sheet->freezePane('C11');
    }

    return [$firstDataRow, $lastColIdx, $lastCol];
}

function edu_write_semester_rows(Worksheet $sheet, array $rows, int $firstDataRow, int $lastColIdx, string $lastCol, ?string $footer = null): int
{
    $rowNum = $firstDataRow;
    foreach ($rows as $line) {
        $sheet->fromArray($line, null, 'A' . $rowNum);
        edu_style_summary_table($sheet, 'A' . $rowNum . ':' . $lastCol . $rowNum);
        $sheet->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($rowNum)->setRowHeight(13.2);
        $rowNum++;
    }

    if ($footer !== null && $footer !== '') {
        $rowNum++;
        $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
        $sheet->setCellValue('A' . $rowNum, $footer);
        $sheet->getStyle('A' . $rowNum)->getFont()->setName('Times New Roman')->setSize(10);
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    return $rowNum;
}

function edu_build_semester_sheet(Spreadsheet $spreadsheet, PDO $pdo, array $group, array $students, array $modules, int $semNum, array &$usedTitles, bool $first = false, bool $includeScholarshipSheet = true): void
{
    $modules = edu_order_modules_for_summary($modules, $semNum);

    $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
    $title = edu_sheet_title('Итоговая ' . $semNum . ' семестр', $usedTitles);
    $usedTitles[] = $title;
    $sheet->setTitle($title);

    $moduleIds = array_map('intval', array_column($modules, 'id'));
    $grades = [];
    if ($moduleIds) {
        $in = implode(',', array_fill(0, count($moduleIds), '?'));
        $stmt = $pdo->prepare("
            SELECT
                eg.id AS grade_id,
                gs.id AS sheet_id,
                gs.type,
                COALESCE(eg.curriculum_module_id, gs.curriculum_module_id) AS curriculum_module_id,
                COALESCE(eg.curriculum_semester, gs.curriculum_semester) AS linked_semester,
                eg.student_id,
                eg.grade,
                eg.passed,
                eg.absent,
                eg.created_at AS grade_created_at,
                eg.updated_at AS grade_updated_at,
                gs.created_at AS sheet_created_at,
                gs.updated_at AS sheet_updated_at
            FROM edu_grades eg
            JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id
            JOIN edu_students st ON st.id = eg.student_id
            WHERE gs.group_id = ?
              AND st.group_id = ?
              AND COALESCE(eg.curriculum_module_id, gs.curriculum_module_id) IN ($in)
              AND COALESCE(eg.curriculum_semester, gs.curriculum_semester) = ?
              AND (gs.status IS NULL OR gs.status <> 'rejected')
        ");
        $params = array_merge([(int)$group['id'], (int)$group['id']], $moduleIds, [$semNum]);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['student_id'] === null || $row['curriculum_module_id'] === null) continue;
            $moduleId = (int)$row['curriculum_module_id'];
            $studentId = (int)$row['student_id'];
            $grades[$moduleId][$studentId] = edu_vedomost_choose_grade($grades[$moduleId][$studentId] ?? null, $row, $semNum);
        }
    }

    [$firstDataRow, $lastColIdx, $lastCol] = edu_write_summary_headers($sheet, $group, $modules, $semNum, false);

    $allRows = [];
    $scholarshipRowsExcellent = [];
    $scholarshipRowsGood = [];
    foreach ($students as $idx => $student) {
        $line = [$idx + 1, edu_full_name($student)];
        $eligible = count($modules) > 0;
        $allExcellent = count($modules) > 0;
        $sum = 0;
        $cnt = 0;
        foreach ($modules as $m) {
            $type = edu_module_grade_type($m, $semNum);
            $res = edu_summary_grade_result($grades[(int)$m['id']][(int)$student['id']] ?? null, $type);
            if (empty($res['filled']) || empty($res['ok'])) $eligible = false;
            if (empty($res['excellent'])) $allExcellent = false;
            if ($res['avg'] !== null) { $sum += (float)$res['avg']; $cnt++; }
            $line[] = $res['score'];
            $line[] = $res['letter'];
        }
        $line[] = '';
        $allRows[] = $line;

        // На лист стипендии попадает только студент, у которого заполнены все дисциплины
        // семестра и нет академической задолженности по этим дисциплинам.
        if ($eligible && $cnt === count($modules)) {
            if ($allExcellent) $scholarshipRowsExcellent[] = $line;
            else $scholarshipRowsGood[] = $line;
        }
    }

    $footer = 'Зам. директора по УР: ____________________        Зав. отделением: ____________________        Руководитель группы: ____________________';
    edu_write_semester_rows($sheet, $allRows, $firstDataRow, $lastColIdx, $lastCol, $footer);

    if (!$includeScholarshipSheet) {
        return;
    }

    $sch = $spreadsheet->createSheet();
    $schTitle = edu_sheet_title($semNum . ' семестр стипендия', $usedTitles);
    $usedTitles[] = $schTitle;
    $sch->setTitle($schTitle);
    [$schFirstRow, $schLastColIdx, $schLastCol] = edu_write_summary_headers($sch, $group, $modules, $semNum, true);

    $row = $schFirstRow;
    if ($scholarshipRowsExcellent) {
        $sch->mergeCells('A' . $row . ':' . $schLastCol . $row);
        $sch->setCellValue('A' . $row, 'Обучающимся окончивших экзаменационную сессию на «отлично»:');
        $sch->getStyle('A' . $row)->getFont()->setBold(true)->setName('Times New Roman')->setSize(10);
        $sch->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $row++;
        $row = edu_write_semester_rows($sch, $scholarshipRowsExcellent, $row, $schLastColIdx, $schLastCol, null);
    }
    if ($scholarshipRowsGood) {
        $sch->mergeCells('A' . $row . ':' . $schLastCol . $row);
        $sch->setCellValue('A' . $row, 'Обучающимся окончивших экзаменационную сессию на «отлично» и «хорошо»:');
        $sch->getStyle('A' . $row)->getFont()->setBold(true)->setName('Times New Roman')->setSize(10);
        $sch->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $row++;
        $row = edu_write_semester_rows($sch, $scholarshipRowsGood, $row, $schLastColIdx, $schLastCol, null);
    }
    if (!$scholarshipRowsExcellent && !$scholarshipRowsGood) {
        $sch->mergeCells('A' . $row . ':' . $schLastCol . $row);
        $sch->setCellValue('A' . $row, 'Студенты, сохраняющие право на стипендию, не найдены. Проверь: оценки должны быть заполнены по всем дисциплинам семестра.');
        $sch->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        $row++;
    }
    $row++;
    $sch->mergeCells('A' . $row . ':' . $schLastCol . $row);
    $sch->setCellValue('A' . $row, 'Руководитель группы:                                           ____________________');
    $sch->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}
function edu_send_semester_workbook(PDO $pdo, array $group, array $semesters, bool $includeScholarshipSheets = true): void
{
    $studentsStmt = $pdo->prepare("SELECT * FROM edu_students WHERE group_id = ? ORDER BY surname, name, patronymic");
    $studentsStmt->execute([(int)$group['id']]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$students) throw new RuntimeException('В выбранной группе нет студентов.');

    $spreadsheet = new Spreadsheet();
    $usedTitles = [];
    $first = true;
    foreach ($semesters as $semNum) {
        $modules = edu_load_curriculum_modules_for_semester($pdo, (int)$group['curriculum_id'], (int)$semNum);
        edu_build_semester_sheet($spreadsheet, $pdo, $group, $students, $modules, (int)$semNum, $usedTitles, $first, $includeScholarshipSheets);
        $first = false;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'rupl_vedomost_') . '.xlsx';
    (new Xlsx($spreadsheet))->save($tmp);
    $suffix = count($semesters) === 1 ? ($semesters[0] . '_семестр') : '1-8_семестры';
    $filename = edu_safe_filename('vedomost_' . ($group['name'] ?? 'group') . '_' . $suffix) . '.xlsx';
    edu_send_file($tmp, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
}

// ── Группы с привязанным РУПл ────────────────────────────────────────────────
if ($isAdmin || $isDirector) {
    $groups = $pdo->query("\n        SELECT g.id, g.name, g.course, g.curriculum_id,\n               c.name AS curriculum_name, c.specialty_code, c.specialty_name, c.qualification, c.enrollment_year\n        FROM edu_groups g\n        INNER JOIN edu_curricula c ON c.id = g.curriculum_id\n        ORDER BY g.name\n    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("\n        SELECT g.id, g.name, g.course, g.curriculum_id,\n               c.name AS curriculum_name, c.specialty_code, c.specialty_name, c.qualification, c.enrollment_year\n        FROM edu_groups g\n        INNER JOIN edu_curricula c ON c.id = g.curriculum_id\n        WHERE g.curator_id = ?\n        ORDER BY g.name\n    ");
    $stmt->execute([$userId]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$allowedGroupIds = array_map('intval', array_column($groups, 'id'));

$selectedGroup = null;
$disciplines = [];
if ($currentGroupId && in_array($currentGroupId, $allowedGroupIds, true)) {
    $stmt = $pdo->prepare("\n        SELECT g.*, c.name AS curriculum_name, c.specialty_code, c.specialty_name, c.qualification, c.enrollment_year\n        FROM edu_groups g\n        INNER JOIN edu_curricula c ON c.id = g.curriculum_id\n        WHERE g.id = ?\n    ");
    $stmt->execute([$currentGroupId]);
    $selectedGroup = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($selectedGroup && !empty($selectedGroup['curriculum_id'])) {
        $disciplines = edu_load_disciplines($pdo, (int)$selectedGroup['curriculum_id']);
    }
}

// ── Генерация ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $gid = (int)($_POST['group_id'] ?? 0);
    $modId = (int)($_POST['module_id'] ?? 0);
    $semNum = (int)($_POST['semester_num'] ?? 0);
    $examType = in_array($_POST['exam_type'] ?? '', ['зачёт', 'экзамен'], true) ? $_POST['exam_type'] : 'зачёт';
    $teacherId = ($_POST['teacher_id'] ?? '') !== '' ? (int)$_POST['teacher_id'] : null;
    $generationMode = in_array($_POST['generation_mode'] ?? '', ['discipline', 'semester', 'all'], true)
        ? $_POST['generation_mode']
        : 'discipline';

    if (!$gid || !in_array($gid, $allowedGroupIds, true)) {
        $message = 'Нет доступа к выбранной группе или группа не привязана к РУПл.';
        $msgType = 'error';
    } elseif ($generationMode === 'discipline' && !$modId) {
        $message = 'Выберите дисциплину из РУПл.';
        $msgType = 'error';
    } elseif ($generationMode === 'semester' && ($semNum < 1 || $semNum > 8)) {
        $message = 'Выберите семестр от 1 до 8.';
        $msgType = 'error';
    } else {
        $groupStmt = $pdo->prepare("
            SELECT g.*, c.specialty_code, c.specialty_name, c.qualification, c.enrollment_year,
                   COALESCE(NULLIF(c.specialty_code, ''), sp.code) AS spec_code,
                   COALESCE(NULLIF(c.specialty_name, ''), sp.name_ru) AS spec_name
            FROM edu_groups g
            INNER JOIN edu_curricula c ON c.id = g.curriculum_id
            LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
            WHERE g.id = ?
        ");
        $groupStmt->execute([$gid]);
        $group = $groupStmt->fetch(PDO::FETCH_ASSOC);

        if (!$group) {
            $message = 'Группа не найдена или у неё не привязан РУПл.';
            $msgType = 'error';
        } elseif ($generationMode === 'semester' || $generationMode === 'all') {
            try {
                $semestersToExport = $generationMode === 'all' ? range(1, 8) : [$semNum];
                edu_send_semester_workbook($pdo, $group, $semestersToExport, $generationMode !== 'all');
            } catch (Throwable $e) {
                $message = 'Ошибка формирования сводной ведомости: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $msgType = 'error';
            }
        } else {
            $moduleStmt = $pdo->prepare("
                SELECT *
                FROM edu_curriculum_modules
                WHERE id = ? AND curriculum_id = ? AND is_summary = 0
            ");
            $moduleStmt->execute([$modId, (int)($group['curriculum_id'] ?? 0)]);
            $module = $moduleStmt->fetch(PDO::FETCH_ASSOC);

            if (!$module) {
                $message = 'Дисциплина не относится к выбранной группе или РУПл.';
                $msgType = 'error';
            } elseif (!edu_is_assessable_module($module)) {
                $message = 'Выбранная строка РУПл не является дисциплиной для ведомости: служебные строки РУПл не формируются.';
                $msgType = 'error';
            } else {
                $semStmt = $pdo->prepare('SELECT semester_num FROM edu_curriculum_distribution WHERE module_id = ? AND COALESCE(hours, 0) > 0 AND semester_num BETWEEN 1 AND 8 ORDER BY semester_num');
                $semStmt->execute([$modId]);
                $module['semesters'] = implode(',', $semStmt->fetchAll(PDO::FETCH_COLUMN));

                if ($examType === 'экзамен' && empty($module['exam_semester']) && !empty($module['credit_semester'])) {
                    $examType = 'зачёт';
                } elseif ($examType === 'зачёт' && empty($module['credit_semester']) && !empty($module['exam_semester'])) {
                    $examType = 'экзамен';
                }

                $allowedModuleSemesters = edu_module_effective_semesters($module);
                $typeSemesterRaw = $examType === 'экзамен' ? ($module['exam_semester'] ?? null) : ($module['credit_semester'] ?? null);
                $typeSemesters = array_values(array_intersect(edu_semester_numbers($typeSemesterRaw), $allowedModuleSemesters));
                if ($typeSemesters && ($semNum <= 0 || !in_array($semNum, $typeSemesters, true))) {
                    $semNum = $typeSemesters[0];
                } elseif ($semNum <= 0 || ($allowedModuleSemesters && !in_array($semNum, $allowedModuleSemesters, true))) {
                    $semNum = $allowedModuleSemesters[0] ?? edu_pick_semester($module, $examType);
                }

                if ($semNum < 1 || $semNum > 8 || !edu_module_in_semester($module, $semNum)) {
                    $message = 'Выбранная дисциплина не относится к указанному семестру РУПл. Проверьте распределение часов и семестры контроля в РУПл.';
                    $msgType = 'error';
                    $students = [];
                } else {
                $studentsStmt = $pdo->prepare("
                    SELECT s.id, CONCAT(s.surname, ' ', s.name, ' ', COALESCE(s.patronymic, '')) AS full_name
                    FROM edu_students s
                    WHERE s.group_id = ?
                    ORDER BY s.surname, s.name, s.patronymic
                ");
                $studentsStmt->execute([$gid]);
                $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!$students) {
                    $message = 'В выбранной группе нет студентов.';
                    $msgType = 'error';
                } else {
                    $teacher = '';
                    if ($teacherId) {
                        $t = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
                        $t->execute([$teacherId]);
                        $teacher = $t->fetchColumn() ?: '';
                    } elseif ($isTeacher && $userId > 0) {
                        // Если ведомость формирует преподаватель, по умолчанию ставим его ФИО.
                        $t = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
                        $t->execute([$userId]);
                        $teacher = $t->fetchColumn() ?: ($userName ?? '');
                    } elseif (!empty($group['curator_id'])) {
                        $t = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
                        $t->execute([$group['curator_id']]);
                        $teacher = $t->fetchColumn() ?: '';
                    }

                    try {
                        $grades = edu_load_selected_discipline_grades($pdo, $gid, $module, $semNum);
                    } catch (Throwable $e) {
                        $grades = [];
                    }

                    $studentsData = [];
                    foreach ($students as $s) {
                        $gr = $grades[(int)$s['id']] ?? [];
                        if (!empty($gr['absent'])) {
                            $scoreValue = null;
                            $scoreText = 'н/я';
                            $letter = '';
                            $gpa = '';
                        } elseif (!empty($gr['passed']) && $examType === 'зачёт') {
                            $scoreValue = 100;
                            $scoreText = '100';
                            $scale = edu_score_scale($scoreValue);
                            $letter = $scale['letter'];
                            $gpa = $scale['gpa'];
                        } else {
                            $scoreValue = array_key_exists('grade', $gr) ? edu_normalize_score($gr['grade']) : null;
                            $scoreText = $scoreValue === null ? '' : (string)$scoreValue;
                            $scale = edu_score_scale($scoreValue);
                            $letter = $scale['letter'];
                            $gpa = $letter !== '' ? $scale['gpa'] : '';
                        }

                        $studentsData[] = [
                            'full_name'      => trim((string)$s['full_name']),
                            'rating_score'   => $scoreText,
                            'rating_letter'  => $letter,
                            'rating_gpa'     => $gpa,
                            'ticket_num'     => '',
                            'written_score'  => $scoreText,
                            'written_letter' => $letter,
                            'written_gpa'    => $gpa,
                            'oral_score'     => $scoreText,
                            'oral_letter'    => $letter,
                            'oral_gpa'       => $gpa,
                            'total_score'    => $scoreText,
                            'total_letter'   => $letter,
                            'total_gpa'      => $gpa,
                            'total_gpa2'     => $gpa,
                        ];
                    }

                    $gradeCounts = ['excellent' => 0, 'good' => 0, 'satisfactory' => 0, 'fail' => 0];
                    foreach ($studentsData as $rowData) {
                        $letterForCount = strtoupper(trim((string)($rowData['total_letter'] ?? '')));
                        if ($letterForCount === '') continue;
                        if (in_array($letterForCount, ['A', 'A-'], true)) {
                            $gradeCounts['excellent']++;
                        } elseif (in_array($letterForCount, ['B+', 'B', 'B-', 'C+'], true)) {
                            $gradeCounts['good']++;
                        } elseif (in_array($letterForCount, ['C', 'C-', 'D+', 'D'], true)) {
                            $gradeCounts['satisfactory']++;
                        } elseif ($letterForCount === 'F') {
                            $gradeCounts['fail']++;
                        }
                    }

                    $qualRaw = $group['qualification'] ?? '';
                    $qualArr = json_decode($qualRaw, true);
                    $qualText = is_array($qualArr) ? implode('; ', $qualArr) : (string)$qualRaw;
                    $courseNum = (int)($group['course'] ?: max(1, (int)ceil($semNum / 2)));

                    $jsonData = [
                        'discipline'    => trim(($module['index_code'] ? $module['index_code'] . ' ' : '') . $module['name']),
                        'group_name'    => $group['name'],
                        'specialty'     => trim(($group['spec_code'] ?? '') . ' ' . ($group['spec_name'] ?? '')),
                        'qualification' => $qualText,
                        'teacher'       => $teacher,
                        'semester_num'  => $semNum,
                        'course_num'    => $courseNum,
                        'exam_type'     => $examType,
                        'students'      => $studentsData,
                        'counts'        => $gradeCounts,
                    ];

                    $tmpDir = sys_get_temp_dir();
                    $jsonFile = $tmpDir . DIRECTORY_SEPARATOR . 'vedomost_' . uniqid('', true) . '.json';
                    $outFile = $tmpDir . DIRECTORY_SEPARATOR . 'vedomost_' . uniqid('', true) . '.docx';
                    file_put_contents($jsonFile, json_encode($jsonData, JSON_UNESCAPED_UNICODE));

                    [$ok, $output] = edu_run_vedomost_builder($jsonFile, $outFile);
                    @unlink($jsonFile);

                    if ($ok && is_file($outFile)) {
                        $saveDir = __DIR__ . '/uploads/vedomosti/';
                        if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);
                        $filename = edu_safe_filename('vedomost_' . $group['name'] . '_' . ($module['index_code'] ?: $module['name']) . '_' . date('Ymd_His')) . '.docx';
                        $savePath = $saveDir . $filename;
                        rename($outFile, $savePath);
                        edu_send_saved_docx($savePath, $filename);
                    } else {
                        @unlink($outFile);
                        $message = 'Ошибка генерации: ' . htmlspecialchars($output ?: 'неизвестная ошибка', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $msgType = 'error';
                    }
                }
                }
            }
        }
    }
}

$teachers = $pdo->query("
    SELECT id, full_name
    FROM users
    WHERE role IN ('teacher','admin','преподаватель','администратор','3','1')
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Сформировать ведомость';
$activeNav = 'edu';
$breadcrumbs = [
    ['label' => 'СВГТК', 'href' => '../'],
    ['label' => 'Учебный процесс', 'href' => 'index.php'],
    ['label' => 'Ведомость'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — СВГТК</title>
  <?php require 'includes/head.php' ?>
  <style>
    .vedomost-actions{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center}
    .form-label{display:block;margin-bottom:.35rem;font-size:.8125rem;font-weight:600;color:var(--color-text-muted)}
    .vedomost-form{max-width:1040px}
    .ved-form-section{margin-bottom:1.4rem}
    .ved-select-wide{max-width:560px}
    .generation-options{display:grid!important;grid-template-columns:repeat(3,minmax(230px,1fr));gap:.85rem;max-width:980px;align-items:stretch}
    .generation-option{display:flex!important;gap:.75rem;align-items:flex-start;padding:1rem 1.1rem;border:1px solid var(--color-border);border-radius:var(--radius-lg);background:var(--color-surface);cursor:pointer;min-height:88px;transition:border-color var(--transition),box-shadow var(--transition),background var(--transition);box-shadow:var(--shadow-xs,none)}
    .generation-option:hover{border-color:var(--color-primary);box-shadow:var(--shadow-sm)}
    .generation-option input{margin-top:.2rem;flex-shrink:0}
    .generation-option:has(input:checked){border-color:var(--color-primary);background:var(--color-primary-highlight)}
    .generation-title{display:block;font-weight:700;color:var(--color-text);line-height:1.25}.generation-note{display:block;margin-top:.25rem;font-size:.8125rem;color:var(--color-text-muted);line-height:1.35}
    .ved-fields-grid{display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:1rem;max-width:980px;margin-bottom:1.4rem}
    .ved-submit-row{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;padding-top:.25rem}
    @media (max-width: 980px){.generation-options,.ved-fields-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Сформировать ведомость</h1>
        <p class="page-subtitle">По дисциплине, по всем дисциплинам семестра или за все 8 семестров из привязанного РУПл</p>
      </div>
      <div class="page-actions vedomost-actions">
        <a href="index.php" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Назад к студентам
        </a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>" style="margin-bottom:1rem">
      <?= $message ?>
    </div>
    <?php endif ?>

    <div class="card vedomost-card">
      <div class="card-header"><span class="card-title">Параметры ведомости</span></div>
      <div class="card-body">
        <form method="POST" id="genForm" class="vedomost-form">

          <div class="ved-form-section">
            <label class="form-label">
              Группа <span style="color:var(--color-error)">*</span>
            </label>
            <select name="group_id" id="groupSelect" class="form-control ved-select-wide" onchange="loadDisciplines(this.value)" required>
              <option value="">— Выберите группу —</option>
              <?php foreach ($groups as $g): ?>
              <option value="<?= (int)$g['id'] ?>" <?= $currentGroupId === (int)$g['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($g['name']) ?><?= $g['curriculum_name'] ? ' (' . htmlspecialchars($g['curriculum_name']) . ')' : '' ?>
              </option>
              <?php endforeach ?>
            </select>
            <?php if (empty($groups)): ?>
            <p style="font-size:.8125rem;color:var(--color-warning);margin-top:.5rem">
              Нет групп с привязанным РУПл. Сначала привяжите учебный план в управлении группами.
            </p>
            <?php endif ?>
          </div>

          <div class="ved-form-section">
            <label class="form-label">Что сформировать</label>
            <?php $generationMode = $_POST['generation_mode'] ?? 'discipline'; ?>
            <div class="generation-options">
              <label class="generation-option">
                <input type="radio" name="generation_mode" value="discipline" <?= $generationMode === 'discipline' ? 'checked' : '' ?>>
                <span><span class="generation-title">Выбранная дисциплина</span><span class="generation-note">DOCX по одной дисциплине</span></span>
              </label>
              <label class="generation-option">
                <input type="radio" name="generation_mode" value="semester" <?= $generationMode === 'semester' ? 'checked' : '' ?>>
                <span><span class="generation-title">Все дисциплины семестра</span><span class="generation-note">XLSX: группа + лист стипендии</span></span>
              </label>
              <label class="generation-option">
                <input type="radio" name="generation_mode" value="all" <?= $generationMode === 'all' ? 'checked' : '' ?>>
                <span><span class="generation-title">Все 8 семестров</span><span class="generation-note">XLSX по каждому семестру, без листов стипендии</span></span>
              </label>
            </div>
          </div>

          <div id="disciplineBlock" class="ved-form-section" style="<?= empty($disciplines) ? 'display:none' : '' ?>">
            <label class="form-label">
              Дисциплина / модуль <span style="color:var(--color-error)">*</span>
            </label>
            <select name="module_id" id="moduleSelect" class="form-control" style="max-width:760px" onchange="updateSemesters(this)">
              <option value="">— Выберите дисциплину —</option>
              <?php foreach ($disciplines as $d): ?>
              <option value="<?= (int)$d['id'] ?>"
                      data-exam="<?= htmlspecialchars($d['exam_semester'] ?? '') ?>"
                      data-credit="<?= htmlspecialchars($d['credit_semester'] ?? '') ?>"
                      data-semesters="<?= htmlspecialchars($d['semesters'] ?? '') ?>"
                      <?= $currentModuleId === (int)$d['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(trim($d['index_code'] . ' — ' . mb_strimwidth($d['name'], 0, 90, '…'))) ?>
                <?php if ($d['exam_semester']): ?> [Экз: сем.<?= htmlspecialchars($d['exam_semester']) ?>]<?php endif ?>
                <?php if ($d['credit_semester']): ?> [Зач: сем.<?= htmlspecialchars($d['credit_semester']) ?>]<?php endif ?>
              </option>
              <?php endforeach ?>
            </select>
          </div>

          <div id="examBlock" class="ved-fields-grid" style="<?= empty($disciplines) ? 'display:none' : '' ?>">
            <div>
              <label class="form-label">Тип аттестации</label>
              <select name="exam_type" class="form-control" style="width:100%">
                <option value="зачёт" <?= ($_POST['exam_type'] ?? '') === 'зачёт' ? 'selected' : '' ?>>Зачёт</option>
                <option value="экзамен" <?= ($_POST['exam_type'] ?? '') === 'экзамен' ? 'selected' : '' ?>>Экзамен</option>
              </select>
            </div>
            <div>
              <label class="form-label">Семестр</label>
              <select name="semester_num" id="semesterSelect" class="form-control" style="width:100%">
                <option value="0">— Автоопределение —</option>
                <?php for ($s = 1; $s <= 8; $s++): ?>
                <option value="<?= $s ?>" <?= (isset($_POST['semester_num']) && (int)$_POST['semester_num'] === $s) ? 'selected' : '' ?>><?= $s ?> семестр</option>
                <?php endfor ?>
              </select>
            </div>
            <div>
              <label class="form-label">Преподаватель</label>
              <select name="teacher_id" class="form-control" style="width:100%">
                <option value="">— Куратор группы —</option>
                <?php foreach ($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (isset($_POST['teacher_id']) && (int)$_POST['teacher_id'] === (int)$t['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['full_name']) ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
          </div>

          <div class="ved-submit-row">
          <button type="submit" name="generate" value="1" class="btn btn-primary" id="genBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
            </svg>
            Сформировать ведомость
          </button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>
<script src="assets/app.js"></script>
<script>
function loadDisciplines(groupId) {
    const block = document.getElementById('disciplineBlock');
    const examBlock = document.getElementById('examBlock');
    const sel = document.getElementById('moduleSelect');
    if (!groupId) {
        block.style.display = 'none';
        examBlock.style.display = 'none';
        sel.innerHTML = '<option value="">— Выберите дисциплину —</option>';
        return;
    }

    fetch('vedomost_disciplines.php?group_id=' + encodeURIComponent(groupId))
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">— Выберите дисциплину —</option>';
            data.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.dataset.exam = d.exam_semester || '';
                opt.dataset.credit = d.credit_semester || '';
                opt.dataset.semesters = d.semesters || '';
                let label = (d.index_code ? d.index_code + ' — ' : '') + String(d.name || '').substring(0, 90);
                if (d.exam_semester) label += ' [Экз: сем.' + d.exam_semester + ']';
                if (d.credit_semester) label += ' [Зач: сем.' + d.credit_semester + ']';
                opt.textContent = label;
                sel.appendChild(opt);
            });
            block.style.display = '';
            examBlock.style.display = data.length ? '' : 'none';
            syncGenerationMode();
        })
        .catch(() => {
            block.style.display = 'none';
            examBlock.style.display = 'none';
        });
}

function currentGenerationMode() {
    const checked = document.querySelector('[name=generation_mode]:checked');
    return checked ? checked.value : 'discipline';
}

function syncGenerationMode() {
    const mode = currentGenerationMode();
    const disciplineBlock = document.getElementById('disciplineBlock');
    const examBlock = document.getElementById('examBlock');
    const moduleSelect = document.getElementById('moduleSelect');
    const hasDisciplines = moduleSelect && moduleSelect.options.length > 1;
    const semesterSelect = document.getElementById('semesterSelect');
    if (moduleSelect) moduleSelect.required = (mode === 'discipline');
    if (semesterSelect) semesterSelect.required = (mode === 'semester');

    if (mode === 'discipline') {
        disciplineBlock.style.display = hasDisciplines ? '' : 'none';
        examBlock.style.display = hasDisciplines ? '' : 'none';
    } else if (mode === 'semester') {
        disciplineBlock.style.display = 'none';
        examBlock.style.display = '';
    } else {
        disciplineBlock.style.display = 'none';
        examBlock.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const moduleSelect = document.getElementById('moduleSelect');
    if (moduleSelect && moduleSelect.value) updateSemesters(moduleSelect);
    document.querySelectorAll('[name=generation_mode]').forEach(el => el.addEventListener('change', syncGenerationMode));
    syncGenerationMode();
});

function semesterNums(value) {
    const match = String(value || '').match(/\d+/g) || [];
    return [...new Set(match.map(n => parseInt(n, 10)).filter(n => n >= 1 && n <= 8))];
}

function firstSemester(value) {
    const nums = semesterNums(value);
    return nums.length ? String(nums[0]) : '';
}

function firstAllowedSemester(preferred, allowed) {
    const pref = semesterNums(preferred);
    const allow = semesterNums(allowed);
    if (pref.length && allow.length) {
        const found = pref.find(n => allow.includes(n));
        if (found) return String(found);
    }
    if (allow.length) return String(allow[0]);
    if (pref.length) return String(pref[0]);
    return '';
}

function selectSemester(value) {
    const semSel = document.getElementById('semesterSelect');
    if (!semSel || !value) return;
    for (let i = 0; i < semSel.options.length; i++) {
        if (semSel.options[i].value === String(value)) {
            semSel.selectedIndex = i;
            break;
        }
    }
}

function syncSemesterWithType() {
    const moduleSelect = document.getElementById('moduleSelect');
    const examType = document.querySelector('[name=exam_type]');
    if (!moduleSelect || !examType || !moduleSelect.value) return;

    const opt = moduleSelect.options[moduleSelect.selectedIndex];
    if (!opt) return;

    const preferred = examType.value === 'экзамен' ? opt.dataset.exam : opt.dataset.credit;
    const fallback = opt.dataset.semesters || opt.dataset.exam || opt.dataset.credit || '';
    selectSemester(firstAllowedSemester(preferred, fallback));
}

function updateSemesters(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    const examType = document.querySelector('[name=exam_type]');
    if (opt.dataset.exam && !opt.dataset.credit) examType.value = 'экзамен';
    else if (opt.dataset.credit && !opt.dataset.exam) examType.value = 'зачёт';
    syncSemesterWithType();
}

const examTypeSelect = document.querySelector('[name=exam_type]');
if (examTypeSelect) {
    examTypeSelect.addEventListener('change', syncSemesterWithType);
}
</script>
</body>
</html>
