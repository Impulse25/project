<?php
/**
 * edu/vedomost_disciplines.php
 * AJAX: список дисциплин из РУПл выбранной группы.
 */
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$role = edu_current_role();
$userId = edu_current_user_id();
if (!in_array($role, ['admin', 'director', 'teacher'], true)) {
    echo '[]';
    exit;
}

$gid = (int)($_GET['group_id'] ?? 0);
$semesterFilter = (int)($_GET['semester'] ?? 0);
if ($semesterFilter < 1 || $semesterFilter > 8) $semesterFilter = 0;
if (!$gid) {
    echo '[]';
    exit;
}

if (!edu_user_can_access_group($pdo, $gid, $userId, $role)) {
    echo '[]';
    exit;
}
$group = $pdo->prepare('SELECT id, curriculum_id FROM edu_groups WHERE id = ?');
$group->execute([$gid]);
$group = $group->fetch(PDO::FETCH_ASSOC);
if (!$group || empty($group['curriculum_id'])) {
    echo '[]';
    exit;
}



function edu_ajax_norm_token($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    if (function_exists('mb_strtoupper')) return mb_strtoupper($value, 'UTF-8');
    return strtoupper($value);
}

function edu_ajax_lower($value): string
{
    $value = trim((string)$value);
    if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
    return strtolower($value);
}

function edu_ajax_is_assessable_module(array $row): bool
{
    $code = edu_ajax_norm_token($row['index_code'] ?? '');
    $type = edu_ajax_norm_token($row['module_type'] ?? '');
    $name = edu_ajax_lower($row['name'] ?? '');

    // Не отдаём в AJAX родительские разделы: "ООМ 1", "БМ 2", "ПМ 8".
    // Нужны только реальные подразделы/дисциплины: "ООМ 1.1" и т.п.
    if ($code !== '' && preg_match('/^(ООМ|БМ|ПМ)\d+$/u', $code)) return false;

    if ($code !== '' && (preg_match('/^ПА\d*$/u', $code) || preg_match('/^К\d*$/u', $code) || $code === 'Ф')) return false;
    if ($type !== '' && (preg_match('/^ПА\d*$/u', $type) || preg_match('/^К\d*$/u', $type))) return false;

    if ($name !== '') {
        foreach (['промежуточная аттестация', 'консультац', 'факультативные занятия'] as $needle) {
            if (strpos($name, $needle) !== false) return false;
        }
    }
    return true;
}

function edu_ajax_semester_numbers($value): array
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

function edu_ajax_effective_semesters(array $row): array
{
    $dist = edu_ajax_semester_numbers($row['semesters'] ?? '');
    if ($dist) {
        $dist = array_values(array_filter($dist, static fn($n) => $n >= 1 && $n <= 8));
        sort($dist);
        return array_values(array_unique($dist));
    }

    $items = [];
    foreach (['exam_semester', 'credit_semester', 'control_work'] as $key) {
        foreach (edu_ajax_semester_numbers($row[$key] ?? '') as $n) {
            if ($n >= 1 && $n <= 8) $items[] = $n;
        }
    }
    sort($items);
    return array_values(array_unique($items));
}

$stmt = $pdo->prepare("\n    SELECT m.id, m.index_code, m.name, m.component_name, m.module_type,
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
$stmt->execute([$semesterFilter, (int)$group['curriculum_id']]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as &$row) {
    $component = trim((string)($row['component_name'] ?? ''));
    if ($component !== '') $row['name'] = $component;
}
unset($row);
$rows = array_values(array_filter($rows, static function (array $row) use ($semesterFilter): bool {
    if (!edu_ajax_is_assessable_module($row)) return false;
    $semesters = edu_ajax_effective_semesters($row);
    if (!$semesters) return false;
    if ($semesterFilter <= 0) return true;
    if (!in_array($semesterFilter, $semesters, true)) return false;

    $hasAnyDistribution = ((float)($row['distributed_hours'] ?? 0) > 0);
    if ($hasAnyDistribution) {
        return ((float)($row['semester_hours'] ?? 0) > 0);
    }

    return in_array($semesterFilter, edu_ajax_semester_numbers($row['exam_semester'] ?? ''), true)
        || in_array($semesterFilter, edu_ajax_semester_numbers($row['credit_semester'] ?? ''), true)
        || in_array($semesterFilter, edu_ajax_semester_numbers($row['control_work'] ?? ''), true);
}));
foreach ($rows as &$row) {
    $row['semesters'] = implode(',', edu_ajax_effective_semesters($row));
}
unset($row);
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
