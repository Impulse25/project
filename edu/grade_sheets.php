<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/export_helpers.php';

$role      = edu_current_role();
$userId    = edu_current_user_id();
$isAdmin   = edu_is_admin();
$isDir     = edu_is_director();
$isTeacher = edu_is_teacher();
$canEduViewGrades = edu_can($pdo, 'can_edu_view_grades');
$canEduGrades     = edu_can($pdo, 'can_edu_grades');

$canEditGrades = $canEduGrades && ($isAdmin || $isTeacher);
$canViewGradesOnly = $canEduViewGrades && !$canEditGrades;

if (!in_array($role, ['admin', 'teacher', 'director'], true) || !$canEduViewGrades) {
    header('Location: index.php');
    exit;
}

$myGroups = edu_accessible_group_ids($pdo, $userId, $role);
$message = '';
$messageType = '';
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $message = 'Оценки сохранены. Запись обновлена в последних записях.';
    $messageType = 'success';
}
$recentFilterQ = trim((string)($_GET['recent_q'] ?? ''));
$recentFilterGroup = (isset($_GET['recent_group_id']) && $_GET['recent_group_id'] !== '') ? (int)$_GET['recent_group_id'] : null;
$recentFilterSemester = (isset($_GET['recent_semester']) && $_GET['recent_semester'] !== '') ? (int)$_GET['recent_semester'] : null;
if ($recentFilterSemester !== null && ($recentFilterSemester < 1 || $recentFilterSemester > 8)) $recentFilterSemester = null;
$recentFilterFill = in_array(($_GET['recent_fill'] ?? ''), ['complete', 'incomplete', 'empty'], true) ? $_GET['recent_fill'] : '';

function edu_grade_semester_nums($value): array
{
    if ($value === null || $value === '') return [];
    preg_match_all('/\d+/u', (string)$value, $m);
    $items = [];
    foreach ($m[0] ?? [] as $n) {
        $n = (int)$n;
        if ($n >= 1 && $n <= 12) $items[] = $n;
    }
    return array_values(array_unique($items));
}


function edu_grade_norm_token($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    if (function_exists('mb_strtoupper')) return mb_strtoupper($value, 'UTF-8');
    return strtoupper($value);
}

function edu_grade_lower($value): string
{
    $value = trim((string)$value);
    if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
    return strtolower($value);
}

function edu_grade_is_assessable_module(array $module): bool
{
    // Исключаем только служебные строки РУПл.
    // ВАЖНО: Ф1, Ф2, ... — это реальные факультативные дисциплины,
    // их нельзя выкидывать вместе со служебной строкой "Ф".
    $code = edu_grade_norm_token($module['index_code'] ?? '');
    $type = edu_grade_norm_token($module['module_type'] ?? '');
    $name = edu_grade_lower($module['name'] ?? '');

    // Родительские разделы РУПл вида "ООМ 1", "БМ 2", "ПМ 8" не являются
    // дисциплинами для выставления оценок. Оставляем только подразделы:
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

function edu_grade_module_semesters(array $module): array
{
    // Главное правило: если у дисциплины есть распределение часов по семестрам,
    // семестр берём именно из edu_curriculum_distribution. Поля экзамен/зачёт
    // используются только как резерв, когда распределение по часам не импортировано.
    // Иначе дисциплины с экзаменом/зачётом могут ошибочно появляться в чужом семестре.
    $dist = edu_grade_semester_nums($module['dist_semesters'] ?? '');
    if ($dist) {
        sort($dist);
        return array_values(array_unique($dist));
    }

    $items = [];
    foreach (edu_grade_semester_nums($module['exam_semester'] ?? '') as $n) $items[] = $n;
    foreach (edu_grade_semester_nums($module['credit_semester'] ?? '') as $n) $items[] = $n;
    foreach (edu_grade_semester_nums($module['control_work'] ?? '') as $n) $items[] = $n;
    sort($items);
    return array_values(array_unique($items));
}

function edu_grade_module_type(array $module, int $semester): string
{
    if (in_array($semester, edu_grade_semester_nums($module['exam_semester'] ?? ''), true)) return 'exam';
    if (in_array($semester, edu_grade_semester_nums($module['credit_semester'] ?? ''), true)) return 'credit';
    if (!empty($module['exam_semester']) && empty($module['credit_semester'])) return 'exam';
    if (!empty($module['credit_semester']) && empty($module['exam_semester'])) return 'credit';
    return 'current';
}

function edu_grade_table_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
}

function edu_grade_module_belongs_to_semester(array $module, int $semester): bool
{
    if ($semester < 1) {
        return false;
    }
    $semesters = edu_grade_module_semesters($module);
    return $semesters && in_array($semester, $semesters, true);
}

function edu_grade_modules_for_group_semester(PDO $pdo, int $groupId, int $semester): array
{
    if ($groupId <= 0 || $semester < 1 || $semester > 8) {
        return [];
    }

    $stmt = $pdo->prepare("\n        SELECT
            m.id,
            m.curriculum_id,
            m.parent_id,
            m.index_code,
            m.module_type,
            m.name,
            m.component_name,
            m.credits,
            m.total_hours,
            m.exam_semester,
            m.credit_semester,
            m.control_work,
            m.is_summary,
            m.sort_order,
            GROUP_CONCAT(DISTINCT CASE WHEN d.hours > 0 AND d.semester_num BETWEEN 1 AND 8 THEN d.semester_num END ORDER BY d.semester_num SEPARATOR ',') AS dist_semesters,
            SUM(CASE WHEN d.semester_num = ? THEN COALESCE(d.hours, 0) ELSE 0 END) AS semester_hours,
            SUM(COALESCE(d.hours, 0)) AS distributed_hours
        FROM edu_groups g
        JOIN edu_curriculum_modules m ON m.curriculum_id = g.curriculum_id
        LEFT JOIN edu_curriculum_distribution d ON d.module_id = m.id
        WHERE g.id = ?
          AND m.is_summary = 0
          AND (m.module_type IS NULL OR m.module_type <> 'ИТОГО')
          AND (TRIM(COALESCE(m.name, '')) <> '' OR TRIM(COALESCE(m.component_name, '')) <> '')
          AND LOWER(TRIM(COALESCE(m.name, ''))) NOT LIKE 'итого%'
          AND LOWER(TRIM(COALESCE(m.component_name, ''))) NOT LIKE 'итого%'
        GROUP BY
            m.id, m.curriculum_id, m.parent_id, m.index_code, m.module_type, m.name, m.component_name,
            m.credits, m.total_hours, m.exam_semester, m.credit_semester, m.control_work,
            m.is_summary, m.sort_order
        ORDER BY m.sort_order, m.name
    ");
    $stmt->execute([$semester, $groupId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $component = trim((string)($row['component_name'] ?? ''));
        if ($component !== '') {
            $row['name'] = $component;
        }
        if (!edu_grade_is_assessable_module($row)) {
            continue;
        }
        $row['_semesters'] = edu_grade_module_semesters($row);
        if (!edu_grade_module_belongs_to_semester($row, $semester)) {
            continue;
        }

        // Показываем только строки, у которых есть собственная привязка к семестру.
        // Главное — фактические часы из edu_curriculum_distribution. Сравниваем как float:
        // у некоторых РУПл в семестровой сетке могут быть дробные значения, и (int)0.5 превращался в 0.
        $hasSemesterHours = ((float)($row['semester_hours'] ?? 0) > 0);
        $hasAnyDistribution = ((float)($row['distributed_hours'] ?? 0) > 0);

        if ($hasAnyDistribution) {
            if (!$hasSemesterHours) {
                continue;
            }
        } else {
            $hasControlInSemester = in_array($semester, edu_grade_semester_nums($row['exam_semester'] ?? ''), true)
                || in_array($semester, edu_grade_semester_nums($row['credit_semester'] ?? ''), true)
                || in_array($semester, edu_grade_semester_nums($row['control_work'] ?? ''), true);
            if (!$hasControlInSemester) {
                continue;
            }
        }

        $rows[] = $row;
    }

    return $rows;
}


function edu_grade_load_module_by_id(PDO $pdo, int $moduleId, int $semester = 0): ?array
{
    if ($moduleId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("\n        SELECT
            m.id,
            m.curriculum_id,
            m.parent_id,
            m.index_code,
            m.module_type,
            m.name,
            m.component_name,
            m.credits,
            m.total_hours,
            m.exam_semester,
            m.credit_semester,
            m.control_work,
            m.is_summary,
            m.sort_order,
            GROUP_CONCAT(DISTINCT CASE WHEN d.hours > 0 AND d.semester_num BETWEEN 1 AND 8 THEN d.semester_num END ORDER BY d.semester_num SEPARATOR ',') AS dist_semesters,
            SUM(CASE WHEN d.semester_num = ? THEN COALESCE(d.hours, 0) ELSE 0 END) AS semester_hours,
            SUM(COALESCE(d.hours, 0)) AS distributed_hours
        FROM edu_curriculum_modules m
        LEFT JOIN edu_curriculum_distribution d ON d.module_id = m.id
        WHERE m.id = ?
        GROUP BY
            m.id, m.curriculum_id, m.parent_id, m.index_code, m.module_type, m.name, m.component_name,
            m.credits, m.total_hours, m.exam_semester, m.credit_semester, m.control_work,
            m.is_summary, m.sort_order
        LIMIT 1
    ");
    $stmt->execute([$semester, $moduleId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $component = trim((string)($row['component_name'] ?? ''));
    if ($component !== '') {
        $row['name'] = $component;
    }
    $row['_semesters'] = edu_grade_module_semesters($row);
    return $row;
}

function edu_grade_module_option_payload(array $m): array
{
    $sems = implode(',', $m['_semesters'] ?? edu_grade_module_semesters($m));
    $label = trim(((string)($m['index_code'] ?? '') !== '' ? $m['index_code'] . ' — ' : '') . (string)($m['name'] ?? ''));
    $hours = (int)($m['total_hours'] ?? 0);
    $credits = (float)($m['credits'] ?? 0);

    $parts = [$label];
    $parts[] = $sems ? ('сем. ' . $sems) : 'семестр не определён';
    if ($hours > 0) $parts[] = $hours . ' ч.';
    if ($credits > 0) $parts[] = rtrim(rtrim(number_format($credits, 2, '.', ''), '0'), '.') . ' кр.';

    return [
        'id' => (int)$m['id'],
        'curriculum_id' => (int)$m['curriculum_id'],
        'semesters' => $sems,
        'label' => implode(' · ', $parts),
    ];
}

function edu_grade_find_or_create_subject(PDO $pdo, array $module): ?int
{
    $subjectId = (int)($module['subject_id'] ?? 0);
    if ($subjectId > 0) return $subjectId;

    $code = trim((string)($module['index_code'] ?? ''));
    if ($code === '') $code = 'RUPL-' . (int)$module['id'];
    $name = trim((string)($module['name'] ?? ''));
    if ($name === '') return null;

    $find = $pdo->prepare('SELECT id FROM edu_subjects WHERE code = ? OR name_ru = ? ORDER BY id LIMIT 1');
    $find->execute([$code, $name]);
    $found = $find->fetchColumn();
    if ($found) return (int)$found;

    $ins = $pdo->prepare('INSERT INTO edu_subjects (code, name_ru, name_kz, hours_total) VALUES (?, ?, NULL, ?)');
    $ins->execute([$code, $name, $module['total_hours'] ?? null]);
    return (int)$pdo->lastInsertId();
}

function edu_grade_find_or_create_semester(PDO $pdo, array $group, int $curriculumSemester): int
{
    $enrollment = (int)($group['year_started'] ?? date('Y'));
    if ($enrollment < 2000) $enrollment = (int)date('Y');

    $studyYear = (int)floor(($curriculumSemester - 1) / 2);
    $yearStart = $enrollment + $studyYear;
    $yearEnd = $yearStart + 1;
    $semesterNum = ($curriculumSemester % 2) === 1 ? 1 : 2;

    $find = $pdo->prepare('SELECT id FROM edu_semesters WHERE year_start = ? AND year_end = ? AND semester_num = ? ORDER BY id LIMIT 1');
    $find->execute([$yearStart, $yearEnd, $semesterNum]);
    $found = $find->fetchColumn();
    if ($found) return (int)$found;

    $startDate = $semesterNum === 1 ? ($yearStart . '-09-01') : ($yearEnd . '-01-15');
    $endDate   = $semesterNum === 1 ? ($yearEnd . '-01-10') : ($yearEnd . '-06-30');
    $ins = $pdo->prepare('INSERT INTO edu_semesters (year_start, year_end, semester_num, start_date, end_date) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$yearStart, $yearEnd, $semesterNum, $startDate, $endDate]);
    return (int)$pdo->lastInsertId();
}


function edu_grade_row_has_value(array $row): bool
{
    return $row['grade'] !== null
        || (int)($row['passed'] ?? 0) === 1
        || (int)($row['absent'] ?? 0) === 1
        || trim((string)($row['comment'] ?? '')) !== ''
        || !empty($row['date']);
}

function edu_grade_sync_sheet_students(PDO $pdo, int $sheetId, int $groupId, int $moduleId, int $curriculumSemester): void
{
    if ($sheetId <= 0 || $groupId <= 0) {
        return;
    }

    $studentStmt = $pdo->prepare('SELECT id FROM edu_students WHERE group_id = ? ORDER BY surname, name, patronymic, id');
    $studentStmt->execute([$groupId]);
    $studentIds = array_map('intval', $studentStmt->fetchAll(PDO::FETCH_COLUMN));
    $studentSet = array_fill_keys($studentIds, true);

    // Чужие студенты в этой таблице оценок — результат старой ошибки. Их надо удалить сразу.
    $deleteForeign = $pdo->prepare("
        DELETE eg
        FROM edu_grades eg
        LEFT JOIN edu_students s ON s.id = eg.student_id AND s.group_id = ?
        WHERE eg.grade_sheet_id = ?
          AND s.id IS NULL
    ");
    $deleteForeign->execute([$groupId, $sheetId]);

    // Удаляем дубли по одному студенту в рамках одной ведомости, сохраняя строку с уже введённой оценкой.
    $rowsStmt = $pdo->prepare('SELECT * FROM edu_grades WHERE grade_sheet_id = ? ORDER BY student_id, id');
    $rowsStmt->execute([$sheetId]);
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

    $keepByStudent = [];
    $deleteIds = [];
    foreach ($rows as $row) {
        $sid = (int)$row['student_id'];
        if (!isset($studentSet[$sid])) {
            if (!empty($row['id'])) $deleteIds[] = (int)$row['id'];
            continue;
        }

        if (!isset($keepByStudent[$sid])) {
            $keepByStudent[$sid] = $row;
            continue;
        }

        $currentHasValue = edu_grade_row_has_value($keepByStudent[$sid]);
        $candidateHasValue = edu_grade_row_has_value($row);
        if (!$currentHasValue && $candidateHasValue) {
            if (!empty($keepByStudent[$sid]['id'])) $deleteIds[] = (int)$keepByStudent[$sid]['id'];
            $keepByStudent[$sid] = $row;
        } else {
            if (!empty($row['id'])) $deleteIds[] = (int)$row['id'];
        }
    }

    $deleteIds = array_values(array_unique(array_filter($deleteIds)));
    if ($deleteIds) {
        $in = implode(',', array_fill(0, count($deleteIds), '?'));
        $del = $pdo->prepare("DELETE FROM edu_grades WHERE id IN ($in)");
        $del->execute($deleteIds);
    }

    $hasGradeModule = edu_grade_table_column_exists($pdo, 'edu_grades', 'curriculum_module_id');
    $hasGradeSemester = edu_grade_table_column_exists($pdo, 'edu_grades', 'curriculum_semester');

    if ($hasGradeModule && $hasGradeSemester) {
        $upd = $pdo->prepare('UPDATE edu_grades SET curriculum_module_id = ?, curriculum_semester = ? WHERE grade_sheet_id = ?');
        $upd->execute([$moduleId, $curriculumSemester, $sheetId]);
        $ins = $pdo->prepare('INSERT INTO edu_grades (grade_sheet_id, student_id, curriculum_module_id, curriculum_semester) VALUES (?, ?, ?, ?)');
    } else {
        $ins = $pdo->prepare('INSERT INTO edu_grades (grade_sheet_id, student_id) VALUES (?, ?)');
    }

    $existing = [];
    $existingStmt = $pdo->prepare('SELECT student_id FROM edu_grades WHERE grade_sheet_id = ?');
    $existingStmt->execute([$sheetId]);
    foreach ($existingStmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        $existing[(int)$sid] = true;
    }

    foreach ($studentIds as $studentId) {
        if (isset($existing[$studentId])) {
            continue;
        }
        if ($hasGradeModule && $hasGradeSemester) {
            $ins->execute([$sheetId, $studentId, $moduleId, $curriculumSemester]);
        } else {
            $ins->execute([$sheetId, $studentId]);
        }
    }
}

function edu_grade_ensure_sheet(PDO $pdo, array $group, array $module, int $curriculumSemester, int $teacherId): int
{
    // Дисциплина выбирается из РУПл: edu_curriculum_modules.id.
    // subject_id оставлен только как техническое зеркало для старых отчётов проекта.
    $subjectId = edu_grade_find_or_create_subject($pdo, $module);
    $semesterId = edu_grade_find_or_create_semester($pdo, $group, $curriculumSemester);
    $type = edu_grade_module_type($module, $curriculumSemester);
    $moduleId = (int)$module['id'];

    $find = $pdo->prepare("
        SELECT id
        FROM edu_grade_sheets
        WHERE group_id = ? AND curriculum_module_id = ? AND curriculum_semester = ?
        ORDER BY FIELD(status, 'draft', 'rejected', 'submitted', 'approved'), id DESC
        LIMIT 1
    ");
    $find->execute([(int)$group['id'], $moduleId, $curriculumSemester]);
    $sheetId = (int)($find->fetchColumn() ?: 0);

    if ($sheetId > 0) {
        $upd = $pdo->prepare('UPDATE edu_grade_sheets SET subject_id = ?, semester_id = ?, type = ?, curriculum_module_id = ?, curriculum_semester = ? WHERE id = ?');
        $upd->execute([$subjectId, $semesterId, $type, $moduleId, $curriculumSemester, $sheetId]);
    } else {
        $ins = $pdo->prepare("
            INSERT INTO edu_grade_sheets (group_id, subject_id, semester_id, teacher_id, type, curriculum_module_id, curriculum_semester)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([(int)$group['id'], $subjectId, $semesterId, $teacherId, $type, $moduleId, $curriculumSemester]);
        $sheetId = (int)$pdo->lastInsertId();
    }

    edu_grade_sync_sheet_students($pdo, $sheetId, (int)$group['id'], $moduleId, $curriculumSemester);

    return $sheetId;
}

// ── Удаление записи оценок ────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $row = $pdo->prepare('SELECT * FROM edu_grade_sheets WHERE id = ?');
    $row->execute([$id]);
    $row = $row->fetch(PDO::FETCH_ASSOC);
    if ($row && $canEditGrades && ($isAdmin || ((int)$row['teacher_id'] === $userId && ($row['status'] ?? '') === 'draft'))) {
        $pdo->prepare('DELETE FROM edu_grades WHERE grade_sheet_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM edu_grade_sheets WHERE id = ?')->execute([$id]);
        $message = 'Запись оценок удалена.';
        $messageType = 'success';
    } else {
        $message = 'Нет доступа для удаления.';
        $messageType = 'error';
    }
}

// ── Данные для выбора ─────────────────────────────────────────────────────
if ($isAdmin || $isDir) {
    $groups = $pdo->query("\n        SELECT g.id, g.name, g.year_started, g.curriculum_id, c.name AS curriculum_name\n        FROM edu_groups g\n        LEFT JOIN edu_curricula c ON c.id = g.curriculum_id\n        ORDER BY g.name\n    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    if ($myGroups) {
        $in = implode(',', array_map('intval', $myGroups));
        $groups = $pdo->query("\n            SELECT g.id, g.name, g.year_started, g.curriculum_id, c.name AS curriculum_name\n            FROM edu_groups g\n            LEFT JOIN edu_curricula c ON c.id = g.curriculum_id\n            WHERE g.id IN ($in)\n            ORDER BY g.name\n        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $groups = [];
    }
}

$groupsById = [];
$curriculumIds = [];
foreach ($groups as $g) {
    $groupsById[(int)$g['id']] = $g;
    if (!empty($g['curriculum_id'])) $curriculumIds[] = (int)$g['curriculum_id'];
}
$curriculumIds = array_values(array_unique($curriculumIds));

$currentGroupId = (int)($_POST['group_id'] ?? $_GET['group_id'] ?? 0);
$currentSemester = (int)($_POST['curriculum_semester'] ?? $_GET['semester'] ?? 0);
$currentModuleId = (int)($_POST['module_id'] ?? $_GET['module_id'] ?? 0);
$currentSheetId = (int)($_POST['sheet_id'] ?? $_GET['sheet_id'] ?? 0);
$openedSheet = null;
if ($currentSemester < 1 || $currentSemester > 8) $currentSemester = 0;

// Кнопка "Открыть" в последних записях должна открывать конкретный лист оценок,
// а не заново угадывать дисциплину по текущему РУПл группы. Иначе старые записи
// перестают открываться после повторного импорта/смены привязанного РУПл.
if ($currentSheetId > 0) {
    $sheetReq = $pdo->prepare('SELECT * FROM edu_grade_sheets WHERE id = ? LIMIT 1');
    $sheetReq->execute([$currentSheetId]);
    $openedSheet = $sheetReq->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($openedSheet && isset($groupsById[(int)$openedSheet['group_id']])) {
        $currentGroupId = (int)$openedSheet['group_id'];
        $currentSemester = (int)($openedSheet['curriculum_semester'] ?: $currentSemester);
        $currentModuleId = (int)($openedSheet['curriculum_module_id'] ?: $currentModuleId);
    } else {
        $openedSheet = null;
        $currentSheetId = 0;
    }
}

$activeGroup = $groupsById[$currentGroupId] ?? null;
$availableModules = ($activeGroup && $currentSemester >= 1)
    ? edu_grade_modules_for_group_semester($pdo, $currentGroupId, $currentSemester)
    : [];

$modulesById = [];
foreach ($availableModules as $m) {
    $modulesById[(int)$m['id']] = $m;
}

// Если открывается уже существующая запись, её module_id может относиться к РУПл,
// который был привязан раньше. Добавляем этот модуль в список вручную, чтобы
// страница могла открыть и сохранить старую запись без пустого селекта.
if ($currentModuleId > 0 && !isset($modulesById[$currentModuleId])) {
    $openedModule = edu_grade_load_module_by_id($pdo, $currentModuleId, $currentSemester);
    if ($openedModule && edu_grade_is_assessable_module($openedModule)) {
        $modulesById[$currentModuleId] = $openedModule;
        $availableModules[] = $openedModule;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'modules') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$activeGroup || $currentSemester < 1) {
        echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $items = array_map('edu_grade_module_option_payload', $availableModules);
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

$activeModule = $modulesById[$currentModuleId] ?? null;
$activeSheet = null;
$students = [];
$canSave = false;

// ── Сохранение оценок ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $isExistingSheetOpen = ($currentSheetId > 0 && $openedSheet && (int)$openedSheet['group_id'] === (int)$currentGroupId);
    if (!$canEditGrades) {
        $message = 'У директора режим только просмотра. Выставление и сохранение оценок недоступно.';
        $messageType = 'error';
    } elseif (!$activeGroup || !$activeModule || $currentSemester < 1) {
        $message = 'Выберите группу, семестр и дисциплину.';
        $messageType = 'error';
    } elseif (!$isExistingSheetOpen && (int)$activeGroup['curriculum_id'] !== (int)$activeModule['curriculum_id']) {
        $message = 'Выбранная дисциплина не относится к РУПл этой группы.';
        $messageType = 'error';
    } elseif (!$isExistingSheetOpen && !edu_grade_module_belongs_to_semester($activeModule, $currentSemester)) {
        $message = 'Выбранная дисциплина не относится к указанному семестру РУПл.';
        $messageType = 'error';
    } elseif ($isExistingSheetOpen && $isTeacher && !$isAdmin && (int)($openedSheet['teacher_id'] ?? 0) > 0 && (int)$openedSheet['teacher_id'] !== $userId) {
        $message = 'Нет доступа для изменения этой записи оценок.';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            if ($isExistingSheetOpen) {
                $sheetId = $currentSheetId;
                edu_grade_sync_sheet_students($pdo, $sheetId, (int)$activeGroup['id'], (int)$activeModule['id'], $currentSemester);
            } else {
                $sheetId = edu_grade_ensure_sheet($pdo, $activeGroup, $activeModule, $currentSemester, $userId);
            }
            $grades = $_POST['grade'] ?? [];
            $passed = $_POST['passed'] ?? [];
            $absent = $_POST['absent'] ?? [];
            $comments = $_POST['comment'] ?? [];
            $date = trim((string)($_POST['grade_date'] ?? date('Y-m-d')));

            $rows = $pdo->prepare('SELECT student_id FROM edu_grades WHERE grade_sheet_id = ?');
            $rows->execute([$sheetId]);

            $hasGradeModule = edu_grade_table_column_exists($pdo, 'edu_grades', 'curriculum_module_id');
            $hasGradeSemester = edu_grade_table_column_exists($pdo, 'edu_grades', 'curriculum_semester');
            if ($hasGradeModule && $hasGradeSemester) {
                $upd = $pdo->prepare("\n                    UPDATE edu_grades\n                    SET grade = ?, passed = ?, absent = ?, comment = ?, date = ?,
                        curriculum_module_id = ?, curriculum_semester = ?, updated_at = NOW()\n                    WHERE grade_sheet_id = ? AND student_id = ?\n                ");
            } else {
                $upd = $pdo->prepare("\n                    UPDATE edu_grades\n                    SET grade = ?, passed = ?, absent = ?, comment = ?, date = ?, updated_at = NOW()\n                    WHERE grade_sheet_id = ? AND student_id = ?\n                ");
            }

            foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                $raw = trim((string)($grades[$sid] ?? ''));
                $grade = $raw !== '' ? edu_normalize_score($raw) : null;
                if ($raw !== '' && $grade === null) {
                    throw new RuntimeException('Оценка должна быть числом от 0 до 100.');
                }

                $baseParams = [
                    $grade,
                    isset($passed[$sid]) ? 1 : 0,
                    isset($absent[$sid]) ? 1 : 0,
                    trim((string)($comments[$sid] ?? '')) ?: null,
                    $date ?: null,
                ];

                if ($hasGradeModule && $hasGradeSemester) {
                    $upd->execute(array_merge($baseParams, [
                        (int)$activeModule['id'],
                        $currentSemester,
                        $sheetId,
                        (int)$sid,
                    ]));
                } else {
                    $upd->execute(array_merge($baseParams, [
                        $sheetId,
                        (int)$sid,
                    ]));
                }
            }
            $pdo->prepare('UPDATE edu_grade_sheets SET updated_at = NOW(), curriculum_module_id = ?, curriculum_semester = ? WHERE id = ?')
                ->execute([(int)$activeModule['id'], $currentSemester, $sheetId]);
            $pdo->commit();
            header('Location: grade_sheets.php?sheet_id=' . (int)$sheetId . '&saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = 'Ошибка: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ── Загрузка активной таблицы студентов ───────────────────────────────────
$canOpenSheet = $activeGroup && $activeModule && $currentSemester >= 1 && (
    ($currentSheetId > 0 && $openedSheet)
    || ((int)$activeGroup['curriculum_id'] === (int)$activeModule['curriculum_id'] && edu_grade_module_belongs_to_semester($activeModule, $currentSemester))
);
if ($canOpenSheet) {
    try {
        if ($currentSheetId > 0 && $openedSheet) {
            $sheetId = $currentSheetId;
            if ($canEditGrades) {
                edu_grade_sync_sheet_students($pdo, $sheetId, (int)$activeGroup['id'], (int)$activeModule['id'], $currentSemester);
            }
        } elseif ($canEditGrades) {
            $sheetId = edu_grade_ensure_sheet($pdo, $activeGroup, $activeModule, $currentSemester, $userId);
        } else {
            // Директор открывает оценки только для просмотра. Новые листы и строки edu_grades не создаются.
            $findSheet = $pdo->prepare("
                SELECT id
                FROM edu_grade_sheets
                WHERE group_id = ? AND curriculum_module_id = ? AND curriculum_semester = ?
                ORDER BY updated_at DESC, created_at DESC, id DESC
                LIMIT 1
            ");
            $findSheet->execute([(int)$activeGroup['id'], (int)$activeModule['id'], $currentSemester]);
            $sheetId = (int)($findSheet->fetchColumn() ?: 0);
            if ($sheetId <= 0) {
                $message = 'По выбранной группе, семестру и дисциплине ещё нет сохранённой записи оценок.';
                $messageType = 'error';
                $canOpenSheet = false;
            }
        }

        if ($canOpenSheet) {
            $sheetStmt = $pdo->prepare("
                SELECT gs.*, sem.year_start, sem.year_end, sem.semester_num
                FROM edu_grade_sheets gs
                LEFT JOIN edu_semesters sem ON sem.id = gs.semester_id
                WHERE gs.id = ?
            ");
            $sheetStmt->execute([$sheetId]);
            $activeSheet = $sheetStmt->fetch(PDO::FETCH_ASSOC);
            $canSave = $isAdmin || ($isTeacher && ((int)($activeSheet['teacher_id'] ?? 0) === 0 || (int)($activeSheet['teacher_id'] ?? 0) === $userId));

            $studentRows = $pdo->prepare("
                SELECT eg.*, s.surname, s.name, s.patronymic, s.iin
                FROM edu_students s
                LEFT JOIN edu_grades eg ON eg.student_id = s.id AND eg.grade_sheet_id = ?
                WHERE s.group_id = ?
                ORDER BY s.surname, s.name, s.patronymic, s.id
            ");
            $studentRows->execute([$sheetId, (int)$activeGroup['id']]);
            $students = $studentRows->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $message = 'Ошибка подготовки оценок: ' . $e->getMessage();
        $messageType = 'error';
    }
} 

// ── Последние записи ──────────────────────────────────────────────────────
$sqlSheets = "
    SELECT gs.*, g.name AS group_name, sub.name_ru AS subject_name, sub.code AS subject_code,
           sem.year_start, sem.year_end, sem.semester_num, u.full_name AS teacher_name,
           m.id AS module_id, m.index_code AS module_code, COALESCE(NULLIF(TRIM(m.component_name), ''), m.name) AS module_name, m.curriculum_id AS module_curriculum_id,
           (SELECT COUNT(DISTINCT s_cnt.id) FROM edu_students s_cnt WHERE s_cnt.group_id = gs.group_id) AS student_count,
           (SELECT COUNT(DISTINCT eg.student_id)
              FROM edu_grades eg
              JOIN edu_students s_gr ON s_gr.id = eg.student_id
             WHERE eg.grade_sheet_id = gs.id
               AND s_gr.group_id = gs.group_id
               AND (eg.grade IS NOT NULL OR eg.passed = 1 OR eg.absent = 1)) AS graded_count
    FROM edu_grade_sheets gs
    LEFT JOIN edu_groups g ON g.id = gs.group_id
    LEFT JOIN edu_subjects sub ON sub.id = gs.subject_id
    LEFT JOIN edu_semesters sem ON sem.id = gs.semester_id
    LEFT JOIN users u ON u.id = gs.teacher_id
    LEFT JOIN edu_curriculum_modules m ON m.id = gs.curriculum_module_id
";
$recentWhere = [];
$recentHaving = [];
$recentParams = [];
if ($isTeacher && $myGroups) {
    $in = implode(',', array_map('intval', $myGroups));
    $recentWhere[] = "gs.group_id IN ($in)";
} elseif ($isTeacher) {
    $recentWhere[] = '1=0';
}
if ($recentFilterGroup) {
    $recentWhere[] = 'gs.group_id = :recent_gid';
    $recentParams[':recent_gid'] = $recentFilterGroup;
}
if ($recentFilterSemester) {
    $recentWhere[] = 'gs.curriculum_semester = :recent_semester';
    $recentParams[':recent_semester'] = $recentFilterSemester;
}
if ($recentFilterQ !== '') {
    $recentWhere[] = "CONCAT_WS(' ', g.name, m.index_code, m.component_name, m.name, sub.code, sub.name_ru, u.full_name) LIKE :recent_q";
    $recentParams[':recent_q'] = '%' . $recentFilterQ . '%';
}
if ($recentFilterFill === 'complete') {
    $recentHaving[] = 'student_count > 0 AND graded_count >= student_count';
} elseif ($recentFilterFill === 'incomplete') {
    $recentHaving[] = 'graded_count > 0 AND graded_count < student_count';
} elseif ($recentFilterFill === 'empty') {
    $recentHaving[] = 'graded_count = 0';
}
if ($recentWhere) $sqlSheets .= ' WHERE ' . implode(' AND ', $recentWhere);
if ($recentHaving) $sqlSheets .= ' HAVING ' . implode(' AND ', $recentHaving);
$sqlSheets .= ' ORDER BY gs.updated_at DESC, gs.created_at DESC, gs.id DESC LIMIT 30';
$recentSheetsError = '';
try {
    $stmtSheets = $pdo->prepare($sqlSheets);
    $stmtSheets->execute($recentParams);
    $recentSheets = $stmtSheets->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentSheets = [];
    $recentSheetsError = $e->getMessage();
}

$TYPE_LABELS = ['exam'=>'Экзамен','credit'=>'Зачёт','coursework'=>'Курсовая','practice'=>'Практика','current'=>'Итоговая оценка'];
$pageTitle = 'Выставление оценок';
$activeNav = 'edu';
$breadcrumbs = [
    ['label'=>'СВГТК', 'href'=>'../'],
    ['label'=>'Учебный процесс', 'href'=>'index.php'],
    ['label'=>'Выставление оценок'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <?php require 'includes/head.php' ?>
  <style>
    .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:1rem; }
    .form-group { display:flex; flex-direction:column; gap:.375rem; }
    .form-group label { font-size:.8125rem; font-weight:600; color:var(--color-text-muted); }
    .form-group input,.form-group select { padding:.5rem .75rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); color:var(--color-text); font-size:.9375rem; }
    .form-group input:focus,.form-group select:focus { outline:none; border-color:var(--color-primary); box-shadow:0 0 0 3px var(--color-primary-highlight); }
    .table-wrapper { overflow-x:auto; }
    .action-btns { display:flex; gap:.375rem; flex-wrap:wrap; justify-content:flex-end; }
    .grades-table { width:100%; min-width:820px; }
    .grades-table th { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-muted); padding:.75rem 1rem; background:var(--color-surface-2); border-bottom:1px solid var(--color-divider); text-align:left; white-space:nowrap; }
    .grades-table td { padding:.55rem 1rem; border-bottom:1px solid var(--color-divider); vertical-align:middle; }
    .grades-table tr:last-child td { border-bottom:none; }
    .grade-input { width:96px; padding:.35rem .5rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); }
    .comment-input { width:100%; min-width:160px; padding:.35rem .5rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); }
    .muted-note { font-size:.8125rem; color:var(--color-text-muted); line-height:1.4; }
    .selection-summary { display:flex; gap:1rem; flex-wrap:wrap; padding:1rem 1.25rem; background:var(--color-surface-2); border-bottom:1px solid var(--color-divider); }
    .selection-summary div { display:flex; flex-direction:column; gap:2px; }
    .selection-summary span { font-size:.75rem; color:var(--color-text-muted); }
    .selection-summary strong { font-size:.9375rem; }
    .empty-state { text-align:center; padding:2.5rem 1.5rem; color:var(--color-text-muted); }
  </style>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Выставление оценок</h1>
        <p class="page-subtitle">Итоговые оценки по дисциплинам РУПл для последующего формирования ведомостей</p>
      </div>
      <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Назад к студентам
        </a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>" style="margin-bottom:1rem">
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif ?>
    <?php if (!empty($recentSheetsError)): ?>
    <div class="alert alert-error" style="margin-bottom:1rem">
      Ошибка загрузки последних записей оценок: <?= htmlspecialchars($recentSheetsError) ?>
    </div>
    <?php endif ?>

    <div class="card">
      <div class="card-header"><span class="card-title">Параметры выставления оценок</span></div>
      <div class="card-body">
        <form method="GET" action="grade_sheets.php" id="selectForm">
          <div class="form-grid">
            <div class="form-group">
              <label>Группа</label>
              <select name="group_id" id="gradeGroup" required>
                <option value="">— выберите группу —</option>
                <?php foreach ($groups as $g): ?>
                <option value="<?= (int)$g['id'] ?>" data-curriculum="<?= (int)($g['curriculum_id'] ?? 0) ?>" <?= $currentGroupId === (int)$g['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($g['name']) ?><?= !empty($g['curriculum_name']) ? ' · ' . htmlspecialchars($g['curriculum_name']) : '' ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label>Семестр РУПл</label>
              <select name="semester" id="gradeSemester" required>
                <option value="">— выберите семестр —</option>
                <?php for ($i=1;$i<=8;$i++): ?>
                <option value="<?= $i ?>" <?= $currentSemester === $i ? 'selected' : '' ?>><?= $i ?> семестр</option>
                <?php endfor ?>
              </select>
            </div>
            <div class="form-group">
              <label>Дисциплина из РУПл</label>
              <select name="module_id" id="gradeModule" required>
                <option value="">— выберите дисциплину —</option>
                <?php foreach ($availableModules as $m):
                  $sems = implode(',', $m['_semesters']);
                  $label = trim(($m['index_code'] ? $m['index_code'] . ' — ' : '') . $m['name']);
                  $hours = (int)($m['total_hours'] ?? 0);
                  $credits = (float)($m['credits'] ?? 0);
                ?>
                <option value="<?= (int)$m['id'] ?>" data-curriculum="<?= (int)$m['curriculum_id'] ?>" data-sems="<?= htmlspecialchars($sems) ?>" <?= $currentModuleId === (int)$m['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars(mb_strimwidth($label, 0, 110, '…')) ?><?= $sems ? ' · сем. ' . htmlspecialchars($sems) : ' · семестр не определён' ?><?= $hours ? ' · ' . $hours . ' ч.' : '' ?><?= $credits ? ' · ' . rtrim(rtrim(number_format($credits, 2, '.', ''), '0'), '.') . ' кр.' : '' ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
          </div>
          <div style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary">Открыть оценки</button>
            <a href="grade_sheets.php" class="btn btn-outline">Сбросить</a>
          </div>
        </form>
      </div>
    </div>

    <?php if ($activeSheet && $activeGroup && $activeModule): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Итоговые оценки</span>
        <span class="badge badge-blue"><?= count($students) ?> студентов</span>
      </div>
      <div class="selection-summary">
        <div><span>Группа</span><strong><?= htmlspecialchars($activeGroup['name']) ?></strong></div>
        <div><span>Семестр РУПл</span><strong><?= $currentSemester ?> семестр</strong></div>
        <div><span>Дисциплина РУПл</span><strong><?= htmlspecialchars(trim(($activeModule['index_code'] ? $activeModule['index_code'].' — ' : '') . $activeModule['name'])) ?></strong></div>
        <div><span>Тип</span><strong><?= htmlspecialchars($TYPE_LABELS[$activeSheet['type']] ?? $activeSheet['type']) ?></strong></div>
      </div>
      <?php if ($students): ?>
      <form method="POST" action="grade_sheets.php">
        <input type="hidden" name="save_grades" value="1">
        <input type="hidden" name="group_id" value="<?= (int)$currentGroupId ?>">
        <input type="hidden" name="curriculum_semester" value="<?= (int)$currentSemester ?>">
        <input type="hidden" name="module_id" value="<?= (int)$currentModuleId ?>">
        <input type="hidden" name="sheet_id" value="<?= (int)($activeSheet['id'] ?? $currentSheetId) ?>">
        <div style="padding:.75rem 1.25rem;background:var(--color-surface-2);border-bottom:1px solid var(--color-divider);display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
          <label style="font-size:.8125rem;font-weight:600;color:var(--color-text-muted)">Дата:</label>
          <input type="date" name="grade_date" class="grade-input" style="width:auto" value="<?= date('Y-m-d') ?>" <?= !$canSave ? 'disabled' : '' ?>>
          <span class="muted-note">Оценка указывается по 100-балльной шкале. Для зачёта можно поставить «Зачтено».</span>
        </div>
        <div class="table-wrapper">
          <table class="grades-table">
            <thead>
              <tr><th style="width:60px">#</th><th>Студент</th><th style="width:130px">Балл</th><th style="width:110px">Зачтено</th><th style="width:90px">Н/я</th><th>Комментарий</th></tr>
            </thead>
            <tbody>
            <?php foreach ($students as $i => $s): ?>
              <tr>
                <td style="color:var(--color-text-muted)"><?= $i + 1 ?></td>
                <td>
                  <div style="font-weight:600"><?= htmlspecialchars(trim($s['surname'].' '.$s['name'].' '.($s['patronymic'] ?? ''))) ?></div>
                  <div class="muted-note">ИИН: <?= htmlspecialchars($s['iin'] ?? '—') ?></div>
                </td>
                <td><input class="grade-input" type="number" name="grade[<?= (int)$s['student_id'] ?>]" min="0" max="100" step="1" value="<?= $s['grade'] !== null ? htmlspecialchars((string)(int)$s['grade']) : '' ?>" <?= !$canSave ? 'disabled' : '' ?>></td>
                <td style="text-align:center"><input type="checkbox" name="passed[<?= (int)$s['student_id'] ?>]" value="1" <?= !empty($s['passed']) ? 'checked' : '' ?> <?= !$canSave ? 'disabled' : '' ?>></td>
                <td style="text-align:center"><input type="checkbox" name="absent[<?= (int)$s['student_id'] ?>]" value="1" <?= !empty($s['absent']) ? 'checked' : '' ?> <?= !$canSave ? 'disabled' : '' ?>></td>
                <td><input class="comment-input" type="text" name="comment[<?= (int)$s['student_id'] ?>]" value="<?= htmlspecialchars($s['comment'] ?? '') ?>" <?= !$canSave ? 'disabled' : '' ?>></td>
              </tr>
            <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <?php if ($canSave): ?>
        <div style="padding:1rem 1.25rem;border-top:1px solid var(--color-divider)">
          <button type="submit" class="btn btn-primary">Сохранить оценки</button>
        </div>
        <?php else: ?>
        <div style="padding:1rem 1.25rem;border-top:1px solid var(--color-divider);color:var(--color-text-muted);font-size:.875rem">
          Режим просмотра: изменение и сохранение оценок недоступно.
        </div>
        <?php endif ?>
      </form>
      <?php else: ?>
      <div class="empty-state">В выбранной группе нет студентов.</div>
      <?php endif ?>
    </div>
    <?php elseif ($currentGroupId || $currentSemester || $currentModuleId): ?>
    <div class="alert alert-error" style="margin-bottom:1rem">Для загрузки оценок нужно выбрать группу, семестр и дисциплину из РУПл этой группы.</div>
    <?php endif ?>

    <form method="GET" action="grade_sheets.php" class="criteria-card">
      <div class="criteria-grid">
        <div class="criteria-field">
          <label for="recent_q">Поиск по оценкам</label>
          <input type="search" id="recent_q" name="recent_q" placeholder="Группа, дисциплина, преподаватель…" value="<?= htmlspecialchars($recentFilterQ) ?>">
        </div>
        <div class="criteria-field">
          <label for="recent_group_id">Группа</label>
          <select id="recent_group_id" name="recent_group_id">
            <option value="">Все группы</option>
            <?php foreach ($groups as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= $recentFilterGroup === (int)$g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="criteria-field">
          <label for="recent_semester">Семестр</label>
          <select id="recent_semester" name="recent_semester">
            <option value="">Все семестры</option>
            <?php for ($i = 1; $i <= 8; $i++): ?>
            <option value="<?= $i ?>" <?= $recentFilterSemester === $i ? 'selected' : '' ?>><?= $i ?> семестр</option>
            <?php endfor ?>
          </select>
        </div>
        <div class="criteria-field">
          <label for="recent_fill">Заполнение</label>
          <select id="recent_fill" name="recent_fill">
            <option value="">Любое</option>
            <option value="complete" <?= $recentFilterFill === 'complete' ? 'selected' : '' ?>>Полностью заполнено</option>
            <option value="incomplete" <?= $recentFilterFill === 'incomplete' ? 'selected' : '' ?>>Заполнено частично</option>
            <option value="empty" <?= $recentFilterFill === 'empty' ? 'selected' : '' ?>>Пустые записи</option>
          </select>
        </div>
        <div class="criteria-actions">
          <button type="submit" class="btn btn-primary">Найти</button>
          <a href="grade_sheets.php" class="btn btn-outline">Сброс</a>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Последние записи оценок</span>
        <span style="font-size:.875rem;color:var(--color-text-muted)"><?= count($recentSheets) ?> записей</span>
      </div>
      <?php if ($recentSheets): ?>
      <div class="table-wrapper">
        <table class="data-table" style="min-width:900px">
          <thead><tr><th>#</th><th>Группа</th><th>Дисциплина</th><th>Семестр</th><th>Заполнено</th><th>Преподаватель</th><th style="text-align:right">Действия</th></tr></thead>
          <tbody>
          <?php foreach ($recentSheets as $i => $sh):
            $pct = (int)$sh['student_count'] > 0 ? round((int)$sh['graded_count'] / (int)$sh['student_count'] * 100) : 0;
            $moduleTitle = trim((($sh['module_code'] ?? '') ? $sh['module_code'] . ' — ' : '') . ($sh['module_name'] ?: $sh['subject_name']));
          ?>
            <tr>
              <td style="color:var(--color-text-muted)"><?= $i + 1 ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($sh['group_name'] ?? '—') ?></td>
              <td><div style="font-weight:500"><?= htmlspecialchars($moduleTitle ?: '—') ?></div><div class="muted-note"><?= htmlspecialchars($sh['subject_code'] ?? '') ?></div></td>
              <td><?= $sh['curriculum_semester'] ? ((int)$sh['curriculum_semester'] . ' семестр РУПл') : (($sh['year_start'] ?? '') . '/' . ($sh['year_end'] ?? '') . ' · ' . ($sh['semester_num'] ?? '') . ' сем.') ?></td>
              <td><?= (int)$sh['graded_count'] ?>/<?= (int)$sh['student_count'] ?> <span class="badge badge-gray"><?= $pct ?>%</span></td>
              <td style="color:var(--color-text-muted)"><?= htmlspecialchars($sh['teacher_name'] ?? '—') ?></td>
              <td>
                <div class="action-btns">
                  <a href="grade_sheets.php?sheet_id=<?= (int)$sh['id'] ?>" class="btn btn-primary" style="padding:.3rem .7rem;font-size:.8125rem">Открыть</a>
                  <?php if ($isAdmin): ?>
                  <a href="grade_sheets.php?delete=<?= (int)$sh['id'] ?>" class="btn btn-danger" style="padding:.3rem .6rem;font-size:.8125rem" onclick="return confirm('Удалить запись оценок?')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                  </a>
                  <?php endif ?>
                </div>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state">Оценки ещё не выставлялись.</div>
      <?php endif ?>
    </div>

  </main>
</div>
<script src="assets/app.js"></script>
<script>
(function(){
  const groupSelect = document.getElementById('gradeGroup');
  const semesterSelect = document.getElementById('gradeSemester');
  const moduleSelect = document.getElementById('gradeModule');
  const hint = document.getElementById('moduleHint');
  if (!groupSelect || !semesterSelect || !moduleSelect) return;

  const initialModuleValue = moduleSelect.value;

  function resetModules(text) {
    moduleSelect.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = text || '— выберите дисциплину —';
    moduleSelect.appendChild(placeholder);
  }

  function setHint(text) {
    if (hint) hint.textContent = text;
  }

  async function loadModules(preferValue) {
    const groupId = groupSelect.value;
    const sem = semesterSelect.value;
    resetModules(groupId && sem ? 'Загрузка дисциплин…' : '— выберите дисциплину —');

    if (!groupId || !sem) {
      return;
    }

    try {
      const url = `grade_sheets.php?ajax=modules&group_id=${encodeURIComponent(groupId)}&semester=${encodeURIComponent(sem)}`;
      const response = await fetch(url, {headers: {'Accept': 'application/json'}});
      const data = await response.json();
      resetModules('— выберите дисциплину —');

      const items = Array.isArray(data.items) ? data.items : [];
      items.forEach(item => {
        const option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = item.label;
        option.dataset.curriculum = String(item.curriculum_id || '');
        option.dataset.sems = item.semesters || '';
        if (preferValue && String(item.id) === String(preferValue)) option.selected = true;
        moduleSelect.appendChild(option);
      });

      if (!items.length) {
        setHint('');
      } else {
        setHint('');
      }
    } catch (e) {
      resetModules('— ошибка загрузки дисциплин —');
    }
  }

  groupSelect.addEventListener('change', () => loadModules(''));
  semesterSelect.addEventListener('change', () => loadModules(''));
  if (groupSelect.value && semesterSelect.value) {
    loadModules(initialModuleValue);
  }
})();
</script>
</body>
</html>
