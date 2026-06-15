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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$role = edu_current_role();
$userId = edu_current_user_id();
$isAdmin = edu_is_admin();
$isDir = edu_is_director();
$isTeacher = edu_is_teacher();
if (!in_array($role, ['admin', 'teacher', 'director'], true)) {
    header('Location: index.php');
    exit;
}
edu_require_permission($pdo, 'can_edu_diploma_book', 'index.php');

$studentId = (int)($_GET['student_id'] ?? $_GET['id'] ?? 0);
if (!$studentId) {
    header('Location: index.php');
    exit;
}

function edu_diploma_trad_label($grade, $passed = 0, $absent = 0): string
{
    if ((int)$absent === 1) return '';
    if ($grade === null || $grade === '') {
        return ((int)$passed === 1) ? 'зачтено' : '';
    }
    $raw = trim((string)$grade);
    if (is_numeric($raw)) {
        $num = (float)$raw;
        if ($num <= 5) {
            $int = (int)round($num);
            if ($int >= 5) return '5 (отлично)';
            if ($int === 4) return '4 (хорошо)';
            if ($int === 3) return '3 (удовлетворительно)';
            if ($int > 0) return '2 (неудовлетворительно)';
            return '';
        }
        return edu_score_traditional(edu_normalize_score($num));
    }
    $upper = mb_strtoupper($raw, 'UTF-8');
    if (in_array($upper, ['A', 'A-'], true)) return '5 (отлично)';
    if (in_array($upper, ['B+', 'B', 'B-', 'C+'], true)) return '4 (хорошо)';
    if (in_array($upper, ['C', 'C-', 'D+', 'D'], true)) return '3 (удовлетворительно)';
    if ($upper === 'F') return '2 (неудовлетворительно)';
    return $raw;
}


function edu_diploma_score_label($score): string
{
    if ($score === null || $score === '') return '';
    $normalized = edu_normalize_score($score);
    return $normalized === null ? '' : edu_score_traditional($normalized);
}

function edu_diploma_registration_number(int $studentId): string
{
    return date('Y') . '-' . str_pad((string)$studentId, 5, '0', STR_PAD_LEFT);
}

function edu_diploma_ensure_student_card_diploma_fields(PDO $pdo): void
{
    try {
        $checks = [
            'diploma_topic' => "ALTER TABLE edu_student_cards ADD COLUMN diploma_topic TEXT NULL AFTER state_exam_3",
            'diploma_score' => "ALTER TABLE edu_student_cards ADD COLUMN diploma_score DECIMAL(5,2) NULL AFTER diploma_topic",
        ];
        foreach ($checks as $column => $alterSql) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'edu_student_cards' AND COLUMN_NAME = ?");
            $stmt->execute([$column]);
            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->exec($alterSql);
            }
        }
    } catch (Throwable $e) {
        // Если пользователь ещё не запускал миграции и у БД нет прав ALTER,
        // экспорт продолжит работу без этих полей после fallback-запроса ниже.
    }
}

function edu_diploma_norm_token($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    if (function_exists('mb_strtoupper')) return mb_strtoupper($value, 'UTF-8');
    return strtoupper($value);
}

function edu_diploma_is_parent_section(array $module): bool
{
    $code = edu_diploma_norm_token($module['index_code'] ?? '');
    return $code !== '' && (bool)preg_match('/^(ООМ|БМ|ПМ)(?:\d+\.?)?$/u', $code);
}

function edu_diploma_group_title(?string $moduleType): string
{
    $type = mb_strtoupper(trim((string)$moduleType), 'UTF-8');
    if ($type === '' || str_contains($type, 'ООД') || str_contains($type, 'ООМ')) return 'Общеобразовательные модули';
    if (str_contains($type, 'БМ')) return 'Базовые модули';
    if (str_contains($type, 'ПМ')) return 'Профессиональные модули';
    return $moduleType ?: 'Другие модули';
}


function edu_diploma_norm_key(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = str_replace('–', '-', $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function edu_diploma_put_grade(array &$map, string $key, string $label, string $changedAt): void
{
    if ($key === '' || $label === '') return;
    $prev = $map[$key] ?? null;
    if ($prev === null || strcmp($changedAt, (string)($prev['changed_at'] ?? '')) >= 0) {
        $map[$key] = ['label' => $label, 'changed_at' => $changedAt];
    }
}

function edu_diploma_module_grade(array $map, array $module): string
{
    $keys = [];
    $moduleId = (int)($module['id'] ?? 0);
    if ($moduleId > 0) $keys[] = 'id:' . $moduleId;

    $idx = edu_diploma_norm_key((string)($module['index_code'] ?? ''));
    if ($idx !== '') $keys[] = 'idx:' . $idx;

    foreach (['component_name', 'name'] as $field) {
        $name = edu_diploma_norm_key((string)($module[$field] ?? ''));
        if ($name !== '') $keys[] = 'name:' . $name;
    }

    foreach (array_unique($keys) as $key) {
        if (isset($map[$key])) return (string)$map[$key]['label'];
    }
    return '';
}

function edu_diploma_set_border($sheet, string $range, string $style = Border::BORDER_THIN): void
{
    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle($style)->getColor()->setARGB('FF000000');
}

function edu_diploma_row_height(string $text, float $base = 18.0): float
{
    $len = mb_strlen(trim($text), 'UTF-8');
    if ($len > 170) return $base * 4.0;
    if ($len > 110) return $base * 3.0;
    if ($len > 65) return $base * 2.0;
    return $base;
}

function edu_diploma_photo_absolute_path(array $student): string
{
    $raw = trim((string)($student['card_photo'] ?? ''));
    $candidates = [];
    $add = static function (?string $path) use (&$candidates): void {
        $path = trim((string)$path);
        if ($path === '') return;
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (!in_array($path, $candidates, true)) {
            $candidates[] = $path;
        }
    };

    $isAbsolute = static function (string $path): bool {
        return (bool)preg_match('~^[A-Za-z]:[\\/]~', $path) || str_starts_with($path, DIRECTORY_SEPARATOR) || str_starts_with($path, '\\\\');
    };

    if ($raw !== '') {
        $normalizedRaw = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        if ($isAbsolute($normalizedRaw)) {
            $add($normalizedRaw);
        }

        $relative = ltrim($raw, "/\\");
        $relativeWithoutEdu = preg_replace('~^edu[\\/]~i', '', $relative);

        // Обычно photo_path = uploads/students/ID.jpg относительно папки edu.
        $add(__DIR__ . DIRECTORY_SEPARATOR . $relative);
        $add(__DIR__ . DIRECTORY_SEPARATOR . $relativeWithoutEdu);

        // На случай, если в БД сохранено edu/uploads/students/ID.jpg относительно корня проекта.
        $add(dirname(__DIR__) . DIRECTORY_SEPARATOR . $relative);
        $add(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'edu' . DIRECTORY_SEPARATOR . $relativeWithoutEdu);

        $documentRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($documentRoot !== '') {
            $add($documentRoot . DIRECTORY_SEPARATOR . $relative);
            $add($documentRoot . DIRECTORY_SEPARATOR . $relativeWithoutEdu);
            $add($documentRoot . DIRECTORY_SEPARATOR . 'edu' . DIRECTORY_SEPARATOR . $relativeWithoutEdu);
        }
    }

    $id = (int)($student['id'] ?? 0);
    $iin = trim((string)($student['iin'] ?? ''));
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        if ($id > 0) {
            $add(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'students' . DIRECTORY_SEPARATOR . $id . '.' . $ext);
        }
        if ($iin !== '') {
            $add(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'students' . DIRECTORY_SEPARATOR . $iin . '.' . $ext);
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            $real = realpath($candidate);
            return $real ?: $candidate;
        }
    }

    return '';
}

edu_diploma_ensure_student_card_diploma_fields($pdo);

$stmt = $pdo->prepare("\n    SELECT s.*,\n           g.name AS group_name,\n           g.course,\n           g.year_started,\n           g.curator_id,\n           g.curriculum_id,\n           c.name AS curriculum_name,\n           c.specialty_code AS curriculum_specialty_code,\n           c.specialty_name AS curriculum_specialty_name,\n           c.qualification AS curriculum_qualification,\n           c.duration_years,\n           sp.code AS specialty_code,\n           sp.name_ru AS specialty_name,\n           sp.qualification AS specialty_qualification,\n           sc.photo_path AS card_photo,\n           sc.diploma_topic AS diploma_topic,\n           sc.diploma_score AS diploma_score,\n           COALESCE(NULLIF(u.full_name, ''), u.username) AS curator_name\n    FROM edu_students s\n    LEFT JOIN edu_groups g ON g.id = s.group_id\n    LEFT JOIN edu_curricula c ON c.id = g.curriculum_id\n    LEFT JOIN edu_specialties sp ON sp.id = COALESCE(s.speciality_id, g.specialty_id)\n    LEFT JOIN edu_student_cards sc ON sc.student_id = s.id\n    LEFT JOIN users u ON u.id = g.curator_id\n    WHERE s.id = ?\n");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    header('Location: index.php');
    exit;
}

$accessibleGroupIds = edu_accessible_group_ids($pdo, $userId, $role);
if (!$isAdmin && !$isDir && !($isTeacher && in_array((int)($student['group_id'] ?? 0), $accessibleGroupIds, true))) {
    header('Location: index.php');
    exit;
}

$fio = edu_full_name($student);
$birthYear = '';
if (!empty($student['birth_date'])) {
    $ts = strtotime((string)$student['birth_date']);
    if ($ts) $birthYear = date('Y', $ts) . 'г.';
}
$startYear = $student['year_started'] ? ((int)$student['year_started'] . 'г.') : '';
$endYear = '';
if (!empty($student['year_started'])) {
    $duration = (int)($student['duration_years'] ?? 0);
    if ($duration <= 0) $duration = 4;
    $endYear = ((int)$student['year_started'] + $duration) . 'г.';
}

$specialty = trim((string)($student['curriculum_specialty_code'] ?: $student['specialty_code']) . ' ' . (string)($student['curriculum_specialty_name'] ?: $student['specialty_name']));
$qualification = trim((string)($student['curriculum_qualification'] ?: $student['specialty_qualification']));
$curriculumId = (int)($student['curriculum_id'] ?? 0);
$diplomaTopic = trim((string)($student['diploma_topic'] ?? ''));
$diplomaGrade = edu_diploma_score_label($student['diploma_score'] ?? null);
$curatorName = trim((string)($student['curator_name'] ?? ''));
$registrationNumber = edu_diploma_registration_number((int)$student['id']);
$issueDate = date('d.m.Y');

$gradeRows = [];
if ($curriculumId > 0) {
    $stmt = $pdo->prepare("\n        SELECT m.id, m.index_code, m.module_type, m.name, m.component_name, m.total_hours, m.sort_order,\n               GROUP_CONCAT(DISTINCT d.semester_num ORDER BY d.semester_num SEPARATOR ', ') AS semesters,\n               SUM(CASE WHEN d.hours IS NULL THEN 0 ELSE d.hours END) AS distributed_hours\n        FROM edu_curriculum_modules m\n        LEFT JOIN edu_curriculum_distribution d ON d.module_id = m.id\n        WHERE m.curriculum_id = ?\n          AND COALESCE(m.is_summary, 0) = 0\n          AND COALESCE(m.module_type, '') <> 'ИТОГО'\n          AND (TRIM(COALESCE(m.name, '')) <> '' OR TRIM(COALESCE(m.component_name, '')) <> '')\n          AND LOWER(TRIM(COALESCE(m.name, ''))) NOT LIKE 'итого%'\n          AND LOWER(TRIM(COALESCE(m.component_name, ''))) NOT LIKE 'итого%'\n          AND (\n                COALESCE(m.total_hours, 0) > 0\n                OR COALESCE(m.credits, 0) > 0\n                OR COALESCE(d.hours, 0) > 0\n                OR TRIM(COALESCE(m.exam_semester, '')) <> ''\n                OR TRIM(COALESCE(m.credit_semester, '')) <> ''\n          )\n        GROUP BY m.id\n        ORDER BY m.sort_order, m.id\n    ");
    $stmt->execute([$curriculumId]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT COALESCE(eg.curriculum_module_id, gs.curriculum_module_id) AS curriculum_module_id,
               eg.grade, eg.passed, eg.absent,
               m.index_code AS module_index_code,
               m.name AS module_name,
               m.component_name AS module_component_name,
               sub.code AS subject_code,
               sub.name_ru AS subject_name,
               COALESCE(eg.updated_at, gs.updated_at, eg.created_at, gs.created_at) AS changed_at
        FROM edu_grades eg
        JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id
        JOIN edu_students st ON st.id = eg.student_id
        LEFT JOIN edu_curriculum_modules m ON m.id = COALESCE(eg.curriculum_module_id, gs.curriculum_module_id)
        LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id
        WHERE eg.student_id = ?
          AND (gs.status IS NULL OR gs.status <> 'rejected')
          AND (gs.group_id IS NULL OR gs.group_id = st.group_id)
        ORDER BY changed_at ASC, eg.id ASC
    ");
    $stmt->execute([$studentId]);
    $gradeMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $gr) {
        $label = edu_diploma_trad_label($gr['grade'], $gr['passed'] ?? 0, $gr['absent'] ?? 0);
        if ($label === '') continue;
        $changedAt = (string)($gr['changed_at'] ?? '');
        $moduleId = (int)($gr['curriculum_module_id'] ?? 0);
        if ($moduleId > 0) edu_diploma_put_grade($gradeMap, 'id:' . $moduleId, $label, $changedAt);

        foreach (['module_index_code', 'subject_code'] as $field) {
            $norm = edu_diploma_norm_key((string)($gr[$field] ?? ''));
            if ($norm !== '') edu_diploma_put_grade($gradeMap, 'idx:' . $norm, $label, $changedAt);
        }
        foreach (['module_component_name', 'module_name', 'subject_name'] as $field) {
            $norm = edu_diploma_norm_key((string)($gr[$field] ?? ''));
            if ($norm !== '') edu_diploma_put_grade($gradeMap, 'name:' . $norm, $label, $changedAt);
        }
    }

    foreach ($modules as $module) {
        if (edu_diploma_is_parent_section($module)) {
            continue;
        }
        $componentName = trim((string)($module['component_name'] ?? ''));
        $name = $componentName !== '' ? $componentName : trim((string)$module['name']);
        $lower = mb_strtolower($name, 'UTF-8');
        $hours = (int)($module['distributed_hours'] ?: $module['total_hours'] ?: 0);
        $grade = edu_diploma_module_grade($gradeMap, $module);
        if ($name === '' || $hours <= 0) continue;

        // Длинные строки без оценки в РУПЛ обычно являются названиями модулей/РО, а не предметами.
        $isDescriptor = ($grade === '' && (mb_strlen($name, 'UTF-8') > 70 || str_contains($lower, 'квалификация') || str_contains($lower, 'модули')));
        $gradeRows[] = [
            'module_id' => (int)$module['id'],
            'module_type' => (string)($module['module_type'] ?? ''),
            'name' => $name,
            'hours' => $hours,
            'grade' => $grade,
            'descriptor' => $isDescriptor,
        ];
    }
}

// Если у группы нет РУПЛ или в РУПЛ нет строк, fallback по старым оценкам, чтобы файл не был пустым.
if (!$gradeRows) {
    try {
        foreach (edu_fetch_student_grades($pdo, $studentId, false) as $g) {
            $score = in_array($g['type'] ?? '', ['credit', 'practice'], true) && !empty($g['passed']) ? 100 : edu_normalize_score($g['grade'] ?? null);
            $name = trim(($g['subject_code'] ? $g['subject_code'] . '. ' : '') . ($g['subject_name'] ?? ''));
            if ($name === '') continue;
            $gradeRows[] = [
                'module_id' => 0,
                'module_type' => '',
                'name' => $name,
                'hours' => (int)($g['hours_total'] ?? 0),
                'grade' => edu_diploma_trad_label($score, $g['passed'] ?? 0, $g['absent'] ?? 0),
                'descriptor' => false,
            ];
        }
    } catch (Throwable $e) {
        $gradeRows = [];
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheetTitle = function_exists('mb_substr') ? mb_substr($student['surname'] ?: 'Студент', 0, 31) : substr($student['surname'] ?: 'Student', 0, 31);
$sheet->setTitle($sheetTitle ?: 'Студент');
$sheet->setShowGridlines(false);

$sheet->getPageSetup()
    ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
    ->setPaperSize(PageSetup::PAPERSIZE_A4)
    ->setFitToWidth(1)
    ->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25);

$widths = ['A'=>6,'B'=>23,'C'=>10,'D'=>10,'E'=>10,'F'=>12,'G'=>12,'H'=>11,'I'=>13];
foreach ($widths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

$spreadsheet->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);
$spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Верхний блок с годами и фото, как в приложенном шаблоне.
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'Диплом № __________');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFont()->setSize(12);

$sheet->mergeCells('A2:B2');
$sheet->mergeCells('C2:D2');
$sheet->mergeCells('E2:G2');
$sheet->mergeCells('A3:B3');
$sheet->mergeCells('C3:D3');
$sheet->mergeCells('E3:G3');
$sheet->setCellValue('A2', 'Год рождения');
$sheet->setCellValue('C2', 'Год поступления');
$sheet->setCellValue('E2', 'год окончания');
$sheet->setCellValue('A3', $birthYear);
$sheet->setCellValue('C3', $startYear);
$sheet->setCellValue('E3', $endYear);
$sheet->getStyle('A2:G3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
$sheet->getStyle('A2:G3')->getFont()->setSize(11);
$sheet->getRowDimension(2)->setRowHeight(20);
$sheet->getRowDimension(3)->setRowHeight(22);
edu_diploma_set_border($sheet, 'A2:G3');

$sheet->mergeCells('A4:G6');
$sheet->setCellValue('A4', "Выписка из ведомости успеваемости за время пребывания в колледже\nФ.И.О _______{$fio} ________________________________\nВ колледже сданы следующие дисциплины:");
$sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('A4')->getFont()->setSize(11);
$sheet->getRowDimension(4)->setRowHeight(22);
$sheet->getRowDimension(5)->setRowHeight(22);
$sheet->getRowDimension(6)->setRowHeight(22);
edu_diploma_set_border($sheet, 'A4:G6');

// Белый квадрат под фото из edu_student_cards.photo_path.
$sheet->mergeCells('H2:I6');
$sheet->getStyle('H2:I6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
edu_diploma_set_border($sheet, 'H2:I6', Border::BORDER_MEDIUM);
$photoAbs = edu_diploma_photo_absolute_path($student);
if ($photoAbs !== '') {
    $drawing = new Drawing();
    $drawing->setName('Фото студента');
    $drawing->setDescription('Фото студента');
    $drawing->setPath($photoAbs);
    $drawing->setCoordinates('H2');
    $drawing->setOffsetX(8);
    $drawing->setOffsetY(6);
    $drawing->setWidthAndHeight(118, 125);
    $drawing->setResizeProportional(true);
    $drawing->setWorksheet($sheet);
}

$startRow = 8;
$sheet->mergeCells("B{$startRow}:E{$startRow}");
$sheet->mergeCells("F{$startRow}:G{$startRow}");
$sheet->mergeCells("H{$startRow}:I{$startRow}");
$sheet->setCellValue("A{$startRow}", '№');
$sheet->setCellValue("B{$startRow}", 'наименование учебных дисциплин');
$sheet->setCellValue("F{$startRow}", 'количество часов');
$sheet->setCellValue("H{$startRow}", 'оценка');
$sheet->getStyle("A{$startRow}:I{$startRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle("A{$startRow}:I{$startRow}")->getFont()->setBold(false)->setSize(11);
edu_diploma_set_border($sheet, "A{$startRow}:I{$startRow}");
$sheet->getRowDimension($startRow)->setRowHeight(19);

$row = $startRow + 1;
$currentGroup = null;
$number = 1;
$groupHours = [];
foreach ($gradeRows as $item) {
    $group = edu_diploma_group_title($item['module_type'] ?? '');
    if ($group !== $currentGroup) {
        $currentGroup = $group;
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->mergeCells("F{$row}:G{$row}");
        $sheet->mergeCells("H{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", $group);
        $sheet->setCellValue("F{$row}", '');
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setItalic(true)->setSize(11);
        $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        edu_diploma_set_border($sheet, "A{$row}:I{$row}");
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;
    }

    $isDescriptor = !empty($item['descriptor']);
    $sheet->mergeCells("B{$row}:E{$row}");
    $sheet->mergeCells("F{$row}:G{$row}");
    $sheet->mergeCells("H{$row}:I{$row}");
    $sheet->setCellValue("A{$row}", $isDescriptor ? '' : $number++);
    $sheet->setCellValue("B{$row}", $item['name']);
    $sheet->setCellValue("F{$row}", $item['hours'] ?: '');
    $sheet->setCellValue("H{$row}", $item['grade']);
    $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getStyle("A{$row}:A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("F{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("A{$row}:I{$row}")->getFont()->setSize(10);
    if ($isDescriptor) {
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setSize(9);
    }
    edu_diploma_set_border($sheet, "A{$row}:I{$row}");
    $sheet->getRowDimension($row)->setRowHeight(edu_diploma_row_height($item['name'], $isDescriptor ? 17.0 : 18.0));
    $row++;
}

if ($row === $startRow + 1) {
    $sheet->mergeCells("A{$row}:I{$row}");
    $sheet->setCellValue("A{$row}", 'Оценки по студенту ещё не выставлены.');
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    edu_diploma_set_border($sheet, "A{$row}:I{$row}");
    $row++;
}

$row += 1;

// Нижний блок дипломной книги по приложенному шаблону:
// государственные экзамены, защита диплома, специальность/квалификация,
// подписи, регистрационный номер и дата выдачи.
$sheet->mergeCells("A{$row}:I{$row}");
$sheet->setCellValue("A{$row}", 'Государственные экзамены:');
$sheet->getStyle("A{$row}")->getFont()->setItalic(true);
$sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
edu_diploma_set_border($sheet, "A{$row}:I{$row}");
$sheet->getRowDimension($row)->setRowHeight(20);
$row++;
$sheet->mergeCells("A{$row}:I" . ($row + 3));
edu_diploma_set_border($sheet, "A{$row}:I" . ($row + 3));
for ($r = $row; $r <= $row + 3; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(24);
}
$row += 4;

$sheet->mergeCells("A{$row}:I{$row}");
$sheet->setCellValue("A{$row}", 'Защита диплома:');
$sheet->getStyle("A{$row}")->getFont()->setItalic(true);
$sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
edu_diploma_set_border($sheet, "A{$row}:I{$row}");
$sheet->getRowDimension($row)->setRowHeight(20);
$row++;

$sheet->mergeCells("A{$row}:B" . ($row + 2));
$sheet->mergeCells("C{$row}:G" . ($row + 2));
$sheet->mergeCells("H{$row}:I" . ($row + 2));
$sheet->setCellValue("A{$row}", 'Тема:');
$sheet->setCellValue("C{$row}", $diplomaTopic);
$sheet->setCellValue("H{$row}", $diplomaGrade);
$sheet->getStyle("A{$row}:I" . ($row + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
edu_diploma_set_border($sheet, "A{$row}:I" . ($row + 2));
for ($r = $row; $r <= $row + 2; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(24);
}
$row += 3;

$sheet->mergeCells("A{$row}:C{$row}");
$sheet->mergeCells("D{$row}:F{$row}");
$sheet->mergeCells("G{$row}:H{$row}");
$sheet->setCellValue("A{$row}", 'специальность');
$sheet->setCellValue("D{$row}", 'квалификация');
$sheet->setCellValue("G{$row}", 'дата решения');
$sheet->setCellValue("I{$row}", "отметка о выдаче\nдубликата");
$sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
edu_diploma_set_border($sheet, "A{$row}:I{$row}");
$sheet->getRowDimension($row)->setRowHeight(32);
$row++;

$sheet->mergeCells("A{$row}:C" . ($row + 2));
$sheet->mergeCells("D{$row}:F" . ($row + 2));
$sheet->mergeCells("G{$row}:H" . ($row + 2));
$sheet->mergeCells("I{$row}:I" . ($row + 2));
$sheet->setCellValue("A{$row}", $specialty);
$sheet->setCellValue("D{$row}", $qualification);
$sheet->setCellValue("G{$row}", '');
$sheet->setCellValue("I{$row}", '');
$sheet->getStyle("A{$row}:I" . ($row + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
edu_diploma_set_border($sheet, "A{$row}:I" . ($row + 2));
for ($r = $row; $r <= $row + 2; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(24);
}
$row += 3;

$sheet->mergeCells("A{$row}:I" . ($row + 2));
edu_diploma_set_border($sheet, "A{$row}:I" . ($row + 2));
for ($r = $row; $r <= $row + 2; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(26);
}
$row += 3;

$sheet->mergeCells("A{$row}:D{$row}");
$sheet->mergeCells("E{$row}:I{$row}");
$sheet->setCellValue("A{$row}", 'Директор колледжа_________________');
$sheet->setCellValue("E{$row}", 'Руководитель группы ' . ($curatorName !== '' ? $curatorName : '_________________'));
$sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$row += 2;

$sheet->mergeCells("A{$row}:D{$row}");
$sheet->mergeCells("E{$row}:I{$row}");
$sheet->setCellValue("A{$row}", 'Регистрационный номер      ' . $registrationNumber);
$sheet->setCellValue("E{$row}", 'Дата выдачи      ' . $issueDate);
$sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$row += 2;

$sheet->mergeCells("A{$row}:I{$row}");
$sheet->setCellValue("A{$row}", 'аттестат выдан');
$sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
$row++;

$lastRow = $row;
$sheet->getStyle("A1:I{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle("A1:I{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
$sheet->getPageSetup()->setPrintArea("A1:I{$lastRow}");
$sheet->freezePane('A9');

$tmp = tempnam(sys_get_temp_dir(), 'diploma_book_') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($tmp);
$name = edu_safe_filename('Дипломная книга_' . $fio) . '.xlsx';
edu_send_file($tmp, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
