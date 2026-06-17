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

function edu_diploma_format_date($value): string
{
    if ($value === null || $value === '') return '';
    $ts = strtotime((string)$value);
    return $ts ? date('d.m.Y', $ts) : trim((string)$value);
}

function edu_diploma_grade_scale($grade, $passed = 0, $absent = 0): array
{
    $blank = ['percent' => '', 'letter' => '', 'gpa' => '', 'traditional' => ''];
    if ((int)$absent === 1) return $blank;

    $raw = trim((string)($grade ?? ''));
    if ($raw === '') {
        if ((int)$passed === 1) {
            return ['percent' => '', 'letter' => '', 'gpa' => '', 'traditional' => 'зачтено'];
        }
        return $blank;
    }

    $upper = function_exists('mb_strtoupper') ? mb_strtoupper($raw, 'UTF-8') : strtoupper($raw);
    $letterToScore = [
        'A' => 95, 'A-' => 90,
        'B+' => 85, 'B' => 80, 'B-' => 75,
        'C+' => 70, 'C' => 65, 'C-' => 60,
        'D+' => 55, 'D' => 50,
        'F' => 0,
        'С+' => 70, 'С' => 65, 'С-' => 60,
    ];

    $score = null;
    if (isset($letterToScore[$upper])) {
        $score = $letterToScore[$upper];
    } elseif (is_numeric(str_replace(',', '.', $raw))) {
        $num = (float)str_replace(',', '.', $raw);
        if ($num > 0 && $num <= 5) {
            if ($num >= 5) $score = 95;
            elseif ($num >= 4) $score = 80;
            elseif ($num >= 3) $score = 60;
            else $score = 0;
        } else {
            $score = (int)round($num);
        }
    }

    $score = edu_normalize_score($score);
    if ($score === null) return $blank;
    $scale = edu_score_scale($score);
    return [
        'percent' => (string)$score,
        'letter' => $scale['letter'],
        'gpa' => edu_format_decimal($scale['gpa'], false),
        'traditional' => edu_score_traditional_mark($score),
    ];
}

function edu_diploma_parse_exam_row(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return ['name' => '', 'scale' => edu_diploma_grade_scale(null)];
    }

    $pattern = '/^(.*?)[\s\-—]+([A-FА-Я][+\-]?|С[+\-]?|C[+\-]?)(?:\s*[-–—]?\s*(\d+[\.,]\d{1,2}))?$/u';
    if (preg_match($pattern, $text, $m)) {
        $name = trim((string)$m[1]);
        $letter = trim((string)$m[2]);
        if ($name !== '') {
            return ['name' => $name, 'scale' => edu_diploma_grade_scale($letter)];
        }
    }

    return ['name' => $text, 'scale' => edu_diploma_grade_scale(null)];
}

function edu_diploma_row_base_height(string $text, float $base = 15.0): float
{
    $len = mb_strlen(trim($text), 'UTF-8');
    if ($len > 170) return $base * 4.0;
    if ($len > 105) return $base * 3.0;
    if ($len > 55) return $base * 2.0;
    return $base;
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
    return $code !== '' && (bool)preg_match('/^(ООД|ООМ|БМ|ПМ)(?:\d+\.?)?$/u', $code);
}

function edu_diploma_group_title(?string $moduleType): string
{
    $type = mb_strtoupper(trim((string)$moduleType), 'UTF-8');
    if ($type === '' || str_contains($type, 'ООД') || str_contains($type, 'ООМ')) return 'Общеобразовательные дисциплины';
    if (str_contains($type, 'БМ')) return 'Базовые модули';
    if (str_contains($type, 'ПМ')) return 'Профессиональные модули';
    if (str_contains($type, 'Ф')) return 'Факультативные занятия';
    return $moduleType ?: 'Другие дисциплины';
}


function edu_diploma_norm_key(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = str_replace('–', '-', $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function edu_diploma_put_grade(array &$map, string $key, array $scale, string $changedAt): void
{
    if ($key === '') return;
    $hasValue = trim(implode('', $scale)) !== '';
    if (!$hasValue) return;
    $prev = $map[$key] ?? null;
    if ($prev === null || strcmp($changedAt, (string)($prev['changed_at'] ?? '')) >= 0) {
        $map[$key] = ['scale' => $scale, 'changed_at' => $changedAt];
    }
}

function edu_diploma_module_grade(array $map, array $module): array
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
        if (isset($map[$key])) return (array)$map[$key]['scale'];
    }
    return edu_diploma_grade_scale(null);
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

$hasDiplomaRecords = edu_table_exists($pdo, 'edu_diploma_records');
$recordSelect = $hasDiplomaRecords
    ? ", dr.diploma_number AS record_diploma_number, dr.registration_number AS record_registration_number, dr.issue_date AS record_issue_date, dr.thesis_topic AS record_thesis_topic, dr.thesis_grade AS record_thesis_grade, dr.qualification_name AS record_qualification_name"
    : ", NULL AS record_diploma_number, NULL AS record_registration_number, NULL AS record_issue_date, NULL AS record_thesis_topic, NULL AS record_thesis_grade, NULL AS record_qualification_name";
$recordJoin = $hasDiplomaRecords ? "LEFT JOIN edu_diploma_records dr ON dr.student_id = s.id" : "";

$stmt = $pdo->prepare("
    SELECT s.*,
           g.name AS group_name,
           g.course,
           g.year_started,
           g.curator_id,
           g.curriculum_id,
           c.name AS curriculum_name,
           c.specialty_code AS curriculum_specialty_code,
           c.specialty_name AS curriculum_specialty_name,
           c.qualification AS curriculum_qualification,
           c.duration_years,
           sp.code AS specialty_code,
           sp.name_ru AS specialty_name,
           sp.qualification AS specialty_qualification,
           sc.photo_path AS card_photo,
           sc.diploma_topic AS diploma_topic,
           sc.diploma_score AS diploma_score,
           sc.coursework_topic AS card_coursework_topic,
           sc.coursework_grade AS card_coursework_grade,
           sc.state_exam_1 AS card_state_exam_1,
           sc.state_exam_2 AS card_state_exam_2,
           sc.state_exam_3 AS card_state_exam_3,
           COALESCE(NULLIF(u.full_name, ''), u.username) AS curator_name
           $recordSelect
    FROM edu_students s
    LEFT JOIN edu_groups g ON g.id = s.group_id
    LEFT JOIN edu_curricula c ON c.id = g.curriculum_id
    LEFT JOIN edu_specialties sp ON sp.id = COALESCE(s.speciality_id, g.specialty_id)
    LEFT JOIN edu_student_cards sc ON sc.student_id = s.id
    LEFT JOIN users u ON u.id = g.curator_id
    $recordJoin
    WHERE s.id = ?
");
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
$birthDateText = edu_diploma_format_date($student['birth_date'] ?? '');
$startDateText = '';
if (!empty($student['year_started'])) {
    $startDateText = '01.09.' . (int)$student['year_started'];
}
$issueDate = edu_diploma_format_date($student['record_issue_date'] ?? '') ?: date('d.m.Y');
$endDateText = $issueDate;
if ($endDateText === '' && !empty($student['year_started'])) {
    $duration = (int)($student['duration_years'] ?? 0);
    if ($duration <= 0) $duration = 4;
    $endDateText = '30.06.' . ((int)$student['year_started'] + $duration);
}

$specialty = trim((string)($student['curriculum_specialty_code'] ?: $student['specialty_code']) . ' ' . (string)($student['curriculum_specialty_name'] ?: $student['specialty_name']));
$qualification = trim((string)($student['record_qualification_name'] ?: $student['curriculum_qualification'] ?: $student['specialty_qualification']));
$curriculumId = (int)($student['curriculum_id'] ?? 0);
$diplomaTopic = trim((string)((($student['record_thesis_topic'] ?? '') !== '') ? $student['record_thesis_topic'] : ($student['diploma_topic'] ?? '')));
$diplomaScale = edu_diploma_grade_scale((($student['record_thesis_grade'] ?? '') !== '') ? $student['record_thesis_grade'] : ($student['diploma_score'] ?? null));
$courseworkTopic = trim((string)($student['card_coursework_topic'] ?? ''));
$courseworkScale = edu_diploma_grade_scale($student['card_coursework_grade'] ?? null);
$curatorName = trim((string)($student['curator_name'] ?? ''));
$curatorShortName = edu_person_short_name($curatorName);
$directorShortName = edu_fetch_director_name($pdo, true);
$registrationNumber = trim((string)($student['record_registration_number'] ?? '')) ?: edu_diploma_registration_number((int)$student['id']);
$diplomaNumber = trim((string)($student['record_diploma_number'] ?? ''));

$gradeRows = [];
$courseworkRows = [];
if ($curriculumId > 0) {
    $stmt = $pdo->prepare("\n        SELECT m.id, m.index_code, m.module_type, m.name, m.component_name, m.credits, m.total_hours, m.coursework_hours, m.sort_order,\n               GROUP_CONCAT(DISTINCT d.semester_num ORDER BY d.semester_num SEPARATOR ', ') AS semesters,\n               SUM(CASE WHEN d.hours IS NULL THEN 0 ELSE d.hours END) AS distributed_hours\n        FROM edu_curriculum_modules m\n        LEFT JOIN edu_curriculum_distribution d ON d.module_id = m.id\n        WHERE m.curriculum_id = ?\n          AND COALESCE(m.is_summary, 0) = 0\n          AND COALESCE(m.module_type, '') <> 'ИТОГО'\n          AND (TRIM(COALESCE(m.name, '')) <> '' OR TRIM(COALESCE(m.component_name, '')) <> '')\n          AND LOWER(TRIM(COALESCE(m.name, ''))) NOT LIKE 'итого%'\n          AND LOWER(TRIM(COALESCE(m.component_name, ''))) NOT LIKE 'итого%'\n          AND (\n                COALESCE(m.total_hours, 0) > 0\n                OR COALESCE(m.credits, 0) > 0\n                OR COALESCE(d.hours, 0) > 0\n                OR TRIM(COALESCE(m.exam_semester, '')) <> ''\n                OR TRIM(COALESCE(m.credit_semester, '')) <> ''\n          )\n        GROUP BY m.id\n        ORDER BY m.sort_order, m.id\n    ");
    $stmt->execute([$curriculumId]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT COALESCE(eg.curriculum_module_id, gs.curriculum_module_id) AS curriculum_module_id,
               COALESCE(eg.curriculum_semester, gs.curriculum_semester) AS curriculum_semester,
               gs.type AS sheet_type,
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
    $courseworkGradeMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $gr) {
        $scale = edu_diploma_grade_scale($gr['grade'], $gr['passed'] ?? 0, $gr['absent'] ?? 0);
        if (trim(implode('', $scale)) === '') continue;
        $changedAt = (string)($gr['changed_at'] ?? '');
        $moduleId = (int)($gr['curriculum_module_id'] ?? 0);
        $semesterNum = (int)($gr['curriculum_semester'] ?? 0);
        $targetMap =& $gradeMap;
        $isCourseworkGrade = ((string)($gr['sheet_type'] ?? '') === 'coursework');
        if ($isCourseworkGrade) {
            $targetMap =& $courseworkGradeMap;
        }
        if ($moduleId > 0) {
            edu_diploma_put_grade($targetMap, 'id:' . $moduleId, $scale, $changedAt);
            if ($isCourseworkGrade && $semesterNum > 0) {
                edu_diploma_put_grade($targetMap, 'id:' . $moduleId . ':' . $semesterNum, $scale, $changedAt);
            }
        }

        foreach (['module_index_code', 'subject_code'] as $field) {
            $norm = edu_diploma_norm_key((string)($gr[$field] ?? ''));
            if ($norm !== '') {
                edu_diploma_put_grade($targetMap, 'idx:' . $norm, $scale, $changedAt);
                if ($isCourseworkGrade && $semesterNum > 0) edu_diploma_put_grade($targetMap, 'idx:' . $norm . ':' . $semesterNum, $scale, $changedAt);
            }
        }
        foreach (['module_component_name', 'module_name', 'subject_name'] as $field) {
            $norm = edu_diploma_norm_key((string)($gr[$field] ?? ''));
            if ($norm !== '') {
                edu_diploma_put_grade($targetMap, 'name:' . $norm, $scale, $changedAt);
                if ($isCourseworkGrade && $semesterNum > 0) edu_diploma_put_grade($targetMap, 'name:' . $norm . ':' . $semesterNum, $scale, $changedAt);
            }
        }
    }

    $courseworkRows = [];
    foreach ($modules as $module) {
        if (edu_diploma_is_parent_section($module) || edu_curriculum_export_is_service_row($module)) {
            continue;
        }
        $componentName = trim((string)($module['component_name'] ?? ''));
        $name = $componentName !== '' ? $componentName : trim((string)$module['name']);
        $lower = mb_strtolower($name, 'UTF-8');
        $hours = (int)($module['distributed_hours'] ?: $module['total_hours'] ?: 0);
        $credits = edu_format_decimal($module['credits'] ?? '', false);
        $grade = edu_diploma_module_grade($gradeMap, $module);
        if ($name === '' || ($hours <= 0 && $credits === '')) continue;

        // Длинные строки без оценки в РУПЛ обычно являются названиями модулей/РО, а не предметами.
        $isDescriptor = (trim(implode('', $grade)) === '' && (mb_strlen($name, 'UTF-8') > 70 || str_contains($lower, 'квалификация') || str_contains($lower, 'модули')));
        $gradeRows[] = [
            'module_id' => (int)$module['id'],
            'module_type' => (string)($module['module_type'] ?? ''),
            'name' => $name,
            'hours' => $hours,
            'credits' => $credits,
            'grade' => $grade,
            'descriptor' => $isDescriptor,
        ];

        if ((float)($module['coursework_hours'] ?? 0) > 0) {
            $cwGrade = edu_diploma_module_grade($courseworkGradeMap, $module);
            $courseworkRows[] = [
                'module_id' => (int)$module['id'],
                'module_type' => 'КР',
                'name' => 'Курсовая работа: ' . $name,
                'hours' => edu_format_decimal($module['coursework_hours'] ?? '', false),
                'credits' => '',
                'grade' => $cwGrade,
                'descriptor' => false,
            ];
        }
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
                'credits' => '',
                'grade' => edu_diploma_grade_scale($score, $g['passed'] ?? 0, $g['absent'] ?? 0),
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

$widths = ['A'=>4.14,'B'=>12.2,'C'=>8.4,'D'=>8.4,'E'=>3.7,'F'=>10.41,'G'=>8.85,'H'=>5.71,'I'=>8.85,'J'=>7.7,'K'=>8.0];
foreach ($widths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

$spreadsheet->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(10);
$spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Верхний блок приведён к структуре прикреплённой дипломной книги.
$sheet->mergeCells('D1:G1');
$sheet->setCellValue('D1', 'Диплом ' . ($diplomaNumber !== '' ? $diplomaNumber : '__________'));
$sheet->getStyle('D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('D1')->getFont()->setSize(12);
$sheet->getRowDimension(1)->setRowHeight(42);

$sheet->mergeCells('A3:B3');
$sheet->mergeCells('C3:D3');
$sheet->mergeCells('E3:F3');
$sheet->mergeCells('A4:B4');
$sheet->mergeCells('C4:D4');
$sheet->mergeCells('E4:F4');
$sheet->setCellValue('A3', 'Год рождения');
$sheet->setCellValue('C3', 'Год поступления');
$sheet->setCellValue('E3', 'год окончания');
$sheet->setCellValue('A4', $birthDateText);
$sheet->setCellValue('C4', $startDateText);
$sheet->setCellValue('E4', $endDateText);
$sheet->getStyle('A3:F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('A3:F4')->getFont()->setSize(9);
edu_diploma_set_border($sheet, 'A3:F4');
$sheet->getRowDimension(3)->setRowHeight(13);
$sheet->getRowDimension(4)->setRowHeight(13);

$sheet->mergeCells('A5:F8');
$sheet->setCellValue('A5', "Выписка из ведомости успеваемости за время пребывания в колледже\nФ.И.О_{$fio}\nВ колледже сданы следующие дисциплины:");
$sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('A5')->getFont()->setSize(10);
edu_diploma_set_border($sheet, 'A5:F8');
for ($r = 5; $r <= 8; $r++) $sheet->getRowDimension($r)->setRowHeight($r === 8 ? 35 : 13);

$sheet->mergeCells('I3:K8');
$sheet->getStyle('I3:K8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
edu_diploma_set_border($sheet, 'I3:K8', Border::BORDER_MEDIUM);
$photoAbs = edu_diploma_photo_absolute_path($student);
if ($photoAbs !== '') {
    $drawing = new Drawing();
    $drawing->setName('Фото студента');
    $drawing->setDescription('Фото студента');
    $drawing->setPath($photoAbs);
    $drawing->setCoordinates('I3');
    $drawing->setOffsetX(8);
    $drawing->setOffsetY(6);
    $drawing->setWidthAndHeight(135, 135);
    $drawing->setResizeProportional(true);
    $drawing->setWorksheet($sheet);
}

$startRow = 10;
$sheet->mergeCells('A10:A12');
$sheet->mergeCells('B10:E12');
$sheet->mergeCells('F10:G11');
$sheet->mergeCells('H10:K10');
$sheet->mergeCells('H11:J11');
$sheet->mergeCells('K11:K12');
$sheet->setCellValue('A10', "№\nп/п");
$sheet->setCellValue('B10', 'Наименование учебных дисциплин и (или) модулей');
$sheet->setCellValue('F10', 'Количество');
$sheet->setCellValue('H10', 'Итоговая оценка');
$sheet->setCellValue('H11', 'по балльно-рейтинговой буквенной системе оценивания');
$sheet->setCellValue('K11', "по цифровой\nпятибальной\nсистеме\nоценивания");
$sheet->setCellValue('F12', 'часов');
$sheet->setCellValue('G12', 'кредитов');
$sheet->setCellValue('H12', 'в %');
$sheet->setCellValue('I12', 'буквенная');
$sheet->setCellValue('J12', 'в баллах');
$sheet->getStyle('A10:K12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('A10:K12')->getFont()->setSize(9);
edu_diploma_set_border($sheet, 'A10:K12');
$sheet->getRowDimension(10)->setRowHeight(21);
$sheet->getRowDimension(11)->setRowHeight(36);
$sheet->getRowDimension(12)->setRowHeight(27);

$writeSection = static function ($sheet, int &$row, string $title): void {
    $sheet->mergeCells("A{$row}:K{$row}");
    $sheet->setCellValue("A{$row}", $title);
    $sheet->getStyle("A{$row}:K{$row}")->getFont()->setBold(true)->setItalic(true)->setSize(10);
    $sheet->getStyle("A{$row}:K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    edu_diploma_set_border($sheet, "A{$row}:K{$row}");
    $sheet->getRowDimension($row)->setRowHeight(13);
    $row++;
};

$writeGradeRow = static function ($sheet, int &$row, $num, string $name, string $hours, string $credits, array $scale, bool $descriptor = false): void {
    $sheet->mergeCells("B{$row}:E{$row}");
    $sheet->setCellValue("A{$row}", $num);
    $sheet->setCellValue("B{$row}", $name);
    $sheet->setCellValue("F{$row}", $hours);
    $sheet->setCellValue("G{$row}", $credits);
    $sheet->setCellValue("H{$row}", $scale['percent'] ?? '');
    $sheet->setCellValue("I{$row}", $scale['letter'] ?? '');
    $sheet->setCellValue("J{$row}", $scale['gpa'] ?? '');
    $sheet->setCellValue("K{$row}", $scale['traditional'] ?? '');
    $sheet->getStyle("A{$row}:K{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getStyle("A{$row}:A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("F{$row}:K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("A{$row}:K{$row}")->getFont()->setSize($descriptor ? 8 : 9);
    if ($descriptor) $sheet->getStyle("B{$row}:E{$row}")->getFont()->setItalic(true);
    edu_diploma_set_border($sheet, "A{$row}:K{$row}");
    $sheet->getRowDimension($row)->setRowHeight(edu_diploma_row_base_height($name, $descriptor ? 12.75 : 15.0));
    $row++;
};

$row = 13;
$number = 1;
$groupedGradeRows = [];
foreach ($gradeRows as $item) {
    $group = edu_diploma_group_title($item['module_type'] ?? '');
    if (!isset($groupedGradeRows[$group])) {
        $groupedGradeRows[$group] = [];
    }
    $groupedGradeRows[$group][] = $item;
}
uksort($groupedGradeRows, static function (string $a, string $b): int {
    $orderCmp = edu_curriculum_export_group_sort_key($a) <=> edu_curriculum_export_group_sort_key($b);
    if ($orderCmp !== 0) return $orderCmp;
    return strcmp($a, $b);
});
foreach ($groupedGradeRows as $group => $items) {
    $writeSection($sheet, $row, $group);
    foreach ($items as $item) {
        $isDescriptor = !empty($item['descriptor']);
        $writeGradeRow(
            $sheet,
            $row,
            $isDescriptor ? '' : $number++,
            (string)$item['name'],
            !empty($item['hours']) ? (string)$item['hours'] : '',
            (string)($item['credits'] ?? ''),
            (array)($item['grade'] ?? edu_diploma_grade_scale(null)),
            $isDescriptor
        );
    }
}

if ($row === 13) {
    $sheet->mergeCells("A{$row}:K{$row}");
    $sheet->setCellValue("A{$row}", 'Оценки по студенту ещё не выставлены.');
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    edu_diploma_set_border($sheet, "A{$row}:K{$row}");
    $row++;
}

if ($courseworkTopic !== '' || trim(implode('', $courseworkScale)) !== '') {
    $courseworkRows[] = [
        'module_id' => 0,
        'module_type' => 'КР',
        'name' => $courseworkTopic !== '' ? ('Курсовая работа: ' . $courseworkTopic) : 'Курсовая работа',
        'hours' => '',
        'credits' => '',
        'grade' => $courseworkScale,
        'descriptor' => false,
    ];
}
if (!empty($courseworkRows)) {
    $writeSection($sheet, $row, 'Курсовые работы');
    foreach ($courseworkRows as $item) {
        $writeGradeRow(
            $sheet,
            $row,
            $number++,
            (string)$item['name'],
            !empty($item['hours']) ? (string)$item['hours'] : '',
            (string)($item['credits'] ?? ''),
            (array)($item['grade'] ?? edu_diploma_grade_scale(null)),
            false
        );
    }
}

$examRows = [];
foreach (['card_state_exam_1', 'card_state_exam_2', 'card_state_exam_3'] as $examKey) {
    $parsed = edu_diploma_parse_exam_row((string)($student[$examKey] ?? ''));
    if ($parsed['name'] !== '') $examRows[] = $parsed;
}
if ($examRows) {
    $writeSection($sheet, $row, 'Государственные экзамены');
    foreach ($examRows as $exam) {
        $writeGradeRow($sheet, $row, $number++, (string)$exam['name'], '', '', (array)$exam['scale'], false);
    }
}

if ($diplomaTopic !== '') {
    $writeSection($sheet, $row, 'Защита дипломного проекта');
    $writeGradeRow($sheet, $row, $number++, 'Дипломный проект: ' . $diplomaTopic, '', '', $diplomaScale, false);
}

$row++;
$footerStart = $row;
$footerEnd = $row + 6;
$sheet->mergeCells("A{$footerStart}:K{$footerEnd}");
$sheet->setCellValue(
    "A{$footerStart}",
    'Директор колледжа: ' . $directorShortName
    . '                  Руководитель группы: ' . ($curatorShortName !== '' ? $curatorShortName : '____________________')
    . "\n\n\nРегистрационный номер " . $registrationNumber . '         Дата выдачи: ' . $issueDate
    . "\n\n\nаттестат выдан: " . $issueDate
);
$sheet->getStyle("A{$footerStart}:K{$footerEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle("A{$footerStart}:K{$footerEnd}")->getFont()->setSize(10);
for ($r = $footerStart; $r <= $footerEnd; $r++) $sheet->getRowDimension($r)->setRowHeight(13);
edu_diploma_set_border($sheet, "A{$footerStart}:K{$footerEnd}");
$row = $footerEnd + 1;

$lastRow = $row;
$sheet->getStyle("A1:K{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle("A1:K{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
$sheet->getPageSetup()->setPrintArea("A1:K{$lastRow}");
$sheet->freezePane('A13');

$tmp = tempnam(sys_get_temp_dir(), 'diploma_book_') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($tmp);
$name = edu_safe_filename('Дипломная книга_' . $fio) . '.xlsx';
edu_send_file($tmp, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
