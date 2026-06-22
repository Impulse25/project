<?php
/**
 * СВГТК Портал — Модуль «Учёт посещаемости»
 * Тема 2: «Разработка модуля «Учёт посещаемости» с формированием отчётности»
 *
 * Файл: attendance/index.php  ← точка входа, только логика
 * Зависимости: style.css, layout.php, tabs.php, app.js
 */

// ── Показываем ВСЕ ошибки (убрать на проде) ──────────────────────────────
// [ИСПРАВЛЕНИЕ #5] Вывод ошибок отключён на проде
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ловим фатальные ошибки которые обрезают HTML
ob_start();
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo '<div style="position:fixed;bottom:0;left:0;right:0;background:#7f1d1d;color:#fca5a5;font-family:monospace;font-size:13px;padding:16px;z-index:9999;white-space:pre-wrap">';
        echo '🔴 PHP FATAL: ' . htmlspecialchars($err['message']);
        echo "\nФайл: " . htmlspecialchars($err['file']) . ' строка ' . $err['line'];
        echo '</div>';
    }
    ob_end_flush();
});

session_start();

// ── Подключение API ядра (предоставляет руководитель) ─────────────────────────
// require_once '../core_api.php';
// checkAuth();
// $user = getCurrentUser();
// $pdo  = getDbConnection();

// ── Читаем пользователя из сессии ────────────────────────────────────────────
// [ИСПРАВЛЕНИЕ #5] Демо-режим с жёстко заданной сессией убран.
// Неавторизованный пользователь перенаправляется на страницу входа.
if (!isset($_SESSION['user_id'])) {
    // Страница входа проекта
    header('Location: /requests/login.php');
    exit;
}
$user = [
    'id'        => (int)$_SESSION['user_id'],
    'full_name' => $_SESSION['full_name'] ?? '',
    'role'      => $_SESSION['role']      ?? 'teacher',
    'position'  => $_SESSION['position']  ?? '',
];

$userRole  = $user['role'];
$userName  = $user['full_name'];
$isAdmin   = in_array($userRole, ['admin', 'director']);
$isTeacher = ($userRole === 'teacher');
$nameParts = explode(' ', trim($userName));
$initials  = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($nameParts, 0, 2)));

// ── Параметры запроса ─────────────────────────────────────────────────────────
$activeTab = in_array($_GET['tab'] ?? '', ['journal','report','documents','analytics','criteria'])
    ? $_GET['tab'] : 'journal';
$rawDate      = $_GET['date']   ?? date('Y-m-d');
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : date('Y-m-d');
$reportPeriod = $_GET['period'] ?? 'month';
if (!in_array($reportPeriod, ['week','month','semester','year','custom'], true)) {
    $reportPeriod = 'month';
}
$rawMonth     = $_GET['month']  ?? date('Y-m');
$reportMonth  = preg_match('/^\d{4}-\d{2}$/', $rawMonth) ? $rawMonth : date('Y-m');
$rawDateFrom  = $_GET['date_from'] ?? date('Y-m-01');
$rawDateTo    = $_GET['date_to']   ?? date('Y-m-d');
$customDateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDateFrom) ? $rawDateFrom : date('Y-m-01');
$customDateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDateTo)   ? $rawDateTo   : date('Y-m-d');
if ($customDateFrom > $customDateTo) {
    [$customDateFrom, $customDateTo] = [$customDateTo, $customDateFrom];
}

// ── Подключение к БД ─────────────────────────────────────────────────────────
// Используем общий config/db.php проекта (APP_ENV: local / hosting / college)
require_once __DIR__ . '/../config/db.php';


// ── Интеграция семестров EDU для модуля attendance ─────────────────────────
// Глобальные таблицы edu_* не изменяем: только читаем edu_semesters.
// Все новые/изменённые объекты находятся в зоне модуля посещаемости (att_* / att_attendance).
function att_table_exists(PDO $pdo, string $table): bool {
    try {
        $safeTable = str_replace('`', '``', $table);
        $pdo->query("SELECT 1 FROM `$safeTable` LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function att_ensure_semester_schema(PDO $pdo): void {
    try {
        if (!att_column_exists($pdo, 'att_attendance', 'semester_id')) {
            $pdo->exec("ALTER TABLE att_attendance ADD COLUMN semester_id INT(10) UNSIGNED DEFAULT NULL AFTER group_id");
        }
    } catch (Throwable $e) { /* если нет прав ALTER — модуль продолжит работать по датам */ }

    try {
        if (!att_column_exists($pdo, 'att_attendance', 'group_id')) {
            $pdo->exec("ALTER TABLE att_attendance ADD COLUMN group_id INT(10) UNSIGNED DEFAULT NULL AFTER student_id");
        }
    } catch (Throwable $e) { /* не критично */ }

    try { $pdo->exec("CREATE INDEX idx_att_semester ON att_attendance (semester_id)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_att_semester_student ON att_attendance (semester_id, student_id)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_att_group_semester ON att_attendance (group_id, semester_id)"); } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS att_semester_absence_totals (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            semester_id INT(10) UNSIGNED NOT NULL,
            group_id INT(10) UNSIGNED NOT NULL,
            student_id INT(10) UNSIGNED NOT NULL,
            absent_hours INT(10) UNSIGNED NOT NULL DEFAULT 0,
            excused_hours INT(10) UNSIGNED NOT NULL DEFAULT 0,
            late_hours INT(10) UNSIGNED NOT NULL DEFAULT 0,
            absent_days INT(10) UNSIGNED NOT NULL DEFAULT 0,
            excused_days INT(10) UNSIGNED NOT NULL DEFAULT 0,
            late_days INT(10) UNSIGNED NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_att_semester_student (semester_id, student_id),
            KEY idx_att_semester_group (semester_id, group_id),
            KEY idx_att_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* таблица сводных итогов не обязательна для открытия страницы */ }
}

function att_load_semesters(PDO $pdo): array {
    if (!att_table_exists($pdo, 'edu_semesters')) return [];
    try {
        return $pdo->query("SELECT id, year_start, year_end, semester_num, start_date, end_date
                            FROM edu_semesters
                            ORDER BY year_start DESC, semester_num DESC, start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function att_find_semester_for_date(array $semesters, string $date): ?array {
    foreach ($semesters as $sem) {
        if ($date >= $sem['start_date'] && $date <= $sem['end_date']) return $sem;
    }
    return null;
}

function att_semester_label(array $sem): string {
    $num = (int)($sem['semester_num'] ?? 0);
    $season = $num === 1 ? 'осень' : ($num === 2 ? 'весна' : '');
    $tail = $season ? " семестр ($season)" : ' семестр';
    return $sem['year_start'] . '/' . $sem['year_end'] . ' — ' . $num . $tail;
}

att_ensure_semester_schema($pdo);
$attendanceHasSemester = att_column_exists($pdo, 'att_attendance', 'semester_id');
$attendanceHasGroupId  = att_column_exists($pdo, 'att_attendance', 'group_id');
$semesters = att_load_semesters($pdo);

$selectedSemesterId = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 0;
$selectedSemester = null;
foreach ($semesters as $sem) {
    if ((int)$sem['id'] === $selectedSemesterId) { $selectedSemester = $sem; break; }
}
if (!$selectedSemester) {
    $selectedSemester = att_find_semester_for_date($semesters, $selectedDate) ?: att_find_semester_for_date($semesters, date('Y-m-d')) ?: ($semesters[0] ?? null);
    $selectedSemesterId = $selectedSemester ? (int)$selectedSemester['id'] : 0;
}
if ($selectedSemester) {
    // Дата выставления посещаемости должна попадать в выбранный семестр.
    // Если пользователь переключил семестр, а дата осталась из другого периода — ставим ближайшую допустимую дату.
    if ($selectedDate < $selectedSemester['start_date'] || $selectedDate > $selectedSemester['end_date']) {
        $todayInSem = (date('Y-m-d') >= $selectedSemester['start_date'] && date('Y-m-d') <= $selectedSemester['end_date']);
        $selectedDate = $todayInSem ? date('Y-m-d') : $selectedSemester['start_date'];
    }

    // Если пользователь просто выбрал семестр в верхнем фильтре, вкладки отчётов
    // по умолчанию тоже должны показывать этот семестр, а не текущий месяц.
    if (!isset($_GET['period'])) {
        $reportPeriod = 'semester';
    }
    if (!isset($_GET['month'])) {
        $reportMonth = date('Y-m', strtotime($selectedSemester['start_date']));
    }
    if (!isset($_GET['date_from'])) {
        $customDateFrom = $selectedSemester['start_date'];
    }
    if (!isset($_GET['date_to'])) {
        $customDateTo = $selectedSemester['end_date'];
    }
}

// ── Права доступа к группам ────────────────────────────────────────────────
function att_column_exists(PDO $pdo, string $table, string $column): bool {
    // SHOW COLUMNS с bind-параметром на некоторых хостингах возвращает пусто,
    // поэтому проверяем через DESCRIBE и сравниваем имена полей вручную.
    try {
        $safeTable = str_replace('`', '``', $table);
        $stmt = $pdo->query("DESCRIBE `$safeTable`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($row['Field']) && $row['Field'] === $column) return true;
        }
    } catch (Throwable $e) {}
    return false;
}

// Берём должность и признаки зав. отделения из БД/сессии, если они есть.
try {
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = :uid LIMIT 1");
    $stmtUser->execute([':uid' => $user['id']]);
    $dbUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!empty($dbUser['position'])) $user['position'] = $dbUser['position'];
} catch (Throwable $e) { $dbUser = []; }

$userPosition = (string)($user['position'] ?? '');
function att_role_permission_enabled(PDO $pdo, string $roleCode, string $permission): bool {
    if ($roleCode === 'admin') return true;
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM roles LIKE :col");
        $stmt->execute([':col' => $permission]);
        if (!$stmt->fetch()) return false;

        $stmt = $pdo->prepare("SELECT `$permission` FROM roles WHERE role_code = :role LIMIT 1");
        $stmt->execute([':role' => $roleCode]);
        return ((int)$stmt->fetchColumn() === 1);
    } catch (Throwable $e) {
        return false;
    }
}

$isAdmin      = in_array($userRole, ['admin', 'director'], true);
$isTeacher    = ($userRole === 'teacher');
$hasDeptAttendancePermission = att_role_permission_enabled($pdo, $userRole, 'can_attendance_view_department');

// Администратор имеет полный доступ к модулю, но не должен считаться зав. отделения.
// Переключатель режима показываем только реальному зав. отделения.
$isDeptHead   = !$isAdmin && (
                $hasDeptAttendancePermission
             || (!empty($dbUser['is_department_head']) && (int)$dbUser['is_department_head'] === 1)
             || in_array($userRole, ['department_head','head_department','zav','zav_otdeleniya','dean','manager'], true)
             || (mb_stripos($userPosition, 'зав') !== false && mb_stripos($userPosition, 'отдел') !== false)
);
$showDeptHeadToggle = $isDeptHead;

// Главное поле для зав. отделения в твоей таблице users — head_department_id.
$userDepartmentId = isset($_SESSION['head_department_id']) ? (int)$_SESSION['head_department_id'] : 0;
if (!$userDepartmentId && isset($_SESSION['department_id'])) $userDepartmentId = (int)$_SESSION['department_id'];
if (!$userDepartmentId && !empty($dbUser['head_department_id'])) {
    $userDepartmentId = (int)$dbUser['head_department_id'];
}
if (!$userDepartmentId && !empty($dbUser['department_id'])) {
    $userDepartmentId = (int)$dbUser['department_id'];
}

// Если department_id не записан в users/session, пробуем определить по названию отделения в должности.
if (!$userDepartmentId && $isDeptHead) {
    try {
        $deps = $pdo->query("SELECT id, department_name FROM departments ORDER BY CHAR_LENGTH(department_name) DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($deps as $dep) {
            if ($dep['department_name'] && mb_stripos($userPosition, $dep['department_name']) !== false) {
                $userDepartmentId = (int)$dep['id'];
                break;
            }
        }
    } catch (Throwable $e) { /* нет таблицы departments */ }
}

$groupDeptColumn = att_column_exists($pdo, 'edu_groups', 'department_id')
    ? 'department_id'
    : (att_column_exists($pdo, 'edu_groups', 'departments_id') ? 'departments_id' : '');
$groupHasDepartment = ($groupDeptColumn !== '');

// Режим работы зав. отделения: можно переключаться между своими кураторскими группами
// и всеми группами отделения. Переключатель передаёт mode=teacher / mode=department.
$requestedMode = $_GET['mode'] ?? ($_SESSION['attendance_mode'] ?? 'department');
$attendanceMode = in_array($requestedMode, ['teacher', 'department'], true) ? $requestedMode : 'department';
if (!$isDeptHead) {
    $attendanceMode = 'teacher';
}
$_SESSION['attendance_mode'] = $attendanceMode;

// Если в users нет department_id, но преподаватель назначен куратором группы,
// определяем его отделение по этой группе. Это удобно для роли "Зав. отделения".
if (!$userDepartmentId && $isDeptHead && $groupHasDepartment) {
    try {
        $stmtDep = $pdo->prepare("SELECT `$groupDeptColumn` FROM edu_groups WHERE curator_id = :uid AND `$groupDeptColumn` IS NOT NULL LIMIT 1");
        $stmtDep->execute([':uid' => $user['id']]);
        $userDepartmentId = (int)($stmtDep->fetchColumn() ?: 0);
    } catch (Throwable $e) { /* не удалось определить отделение */ }
}

$selectDepartment   = $groupHasDepartment
    ? "g.`$groupDeptColumn` AS department_id, COALESCE(d.department_name, '') AS department_name"
    : "NULL AS department_id, '' AS department_name";
$joinDepartment     = $groupHasDepartment ? "LEFT JOIN departments d ON d.id = g.`$groupDeptColumn`" : "";

$groupSql = "
    SELECT g.id, g.name, g.curator_id, g.course,
           $selectDepartment,
           COALESCE(sp.name_ru, '') AS specialty,
           COALESCE(u.full_name, '') AS curator_name
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    LEFT JOIN users u ON u.id = g.curator_id
    $joinDepartment
";
$groupParams = [];

if ($isAdmin) {
    $groupSql .= " ORDER BY g.name";
} elseif ($isDeptHead && $attendanceMode === 'department' && $groupHasDepartment && $userDepartmentId > 0) {
    // Режим «Зав. отделения»: все группы отделения пользователя.
    $groupSql .= " WHERE g.`$groupDeptColumn` = :department_id ORDER BY g.name";
    $groupParams[':department_id'] = $userDepartmentId;
} else {
    // Режим «Преподаватель»: только группы, где пользователь назначен куратором.
    $groupSql .= " WHERE g.curator_id = :uid ORDER BY g.name";
    $groupParams[':uid'] = $user['id'];
}

$stmtGrp = $pdo->prepare($groupSql);
$stmtGrp->execute($groupParams);
$groups = [];
foreach ($stmtGrp->fetchAll(PDO::FETCH_ASSOC) as $g) $groups[(int)$g['id']] = $g;

// Дополнительная защита: если режим зав. отделения включён, но список почему-то
// получился как у преподавателя, повторно берём группы напрямую по head_department_id.
// Это исправляет ситуацию, когда на хостинге не определилось поле department_id.
if (!$isAdmin && $isDeptHead && $attendanceMode === 'department' && $userDepartmentId > 0 && count($groups) <= 1) {
    try {
        $stmtFix = $pdo->prepare("
            SELECT g.id, g.name, g.curator_id, g.course,
                   g.department_id AS department_id, COALESCE(d.department_name, '') AS department_name,
                   COALESCE(sp.name_ru, '') AS specialty, COALESCE(u.full_name, '') AS curator_name
            FROM edu_groups g
            LEFT JOIN departments d ON d.id = g.department_id
            LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
            LEFT JOIN users u ON u.id = g.curator_id
            WHERE g.department_id = :department_id
            ORDER BY g.name
        ");
        $stmtFix->execute([':department_id' => $userDepartmentId]);
        $fixedGroups = [];
        foreach ($stmtFix->fetchAll(PDO::FETCH_ASSOC) as $g) $fixedGroups[(int)$g['id']] = $g;
        if (count($fixedGroups) > count($groups)) {
            $groups = $fixedGroups;
            $groupHasDepartment = true;
            $groupDeptColumn = 'department_id';
        }
    } catch (Throwable $e) { /* оставляем основной список */ }
}

$noGroupsWarning = (!$isAdmin && empty($groups));
$modeLabel = ($isDeptHead && $attendanceMode === 'department') ? 'Зав. отделения' : ($isAdmin ? 'Администратор' : 'Преподаватель');
$groupsLabel = $isAdmin ? 'все группы' : (($isDeptHead && $attendanceMode === 'department') ? 'ваше отделение' : 'ваши группы');

// Выбранная группа: если teacher и запрошенная группа не его — берём первую свою
$selectedGrp = (int)($_GET['group'] ?? 0);
if (!isset($groups[$selectedGrp])) {
    $first       = reset($groups);
    $selectedGrp = $first ? (int)$first['id'] : 0;
}

// ── Фильтр по определённому студенту ────────────────────────────────────────
$selectedStudent = isset($_GET['student']) ? (int)$_GET['student'] : 0;
$allGroupStudents = [];
if ($selectedGrp > 0) {
    $stmtAllStudents = $pdo->prepare("SELECT id, surname, name AS first_name, patronymic FROM edu_students WHERE group_id = :gid ORDER BY surname, name, patronymic");
    $stmtAllStudents->execute([':gid' => $selectedGrp]);
    $allGroupStudents = $stmtAllStudents->fetchAll();
    $studentIdsInGroup = array_map('intval', array_column($allGroupStudents, 'id'));
    if ($selectedStudent > 0 && !in_array($selectedStudent, $studentIdsInGroup, true)) {
        $selectedStudent = 0;
    }
}

// ── Причины отсутствия из БД ─────────────────────────────────────────────────
$stmt = $pdo->query("SELECT * FROM att_absence_reasons ORDER BY id");
$reasons = [];
foreach ($stmt->fetchAll() as $r) {
    $reasons[$r['id']] = $r;
}

// ── Студенты выбранной группы из БД ──────────────────────────────────────────
$studentSql = "
    SELECT s.id, s.iin,
           CONCAT(s.surname, ' ', s.name, ' ', s.patronymic) AS full_name,
           s.surname, s.name AS first_name, s.patronymic,
           g.name AS group_name,
           COALESCE(a.status, 'present')  AS status,
           COALESCE(a.hours_missed, 0)    AS hours_missed,
           a.reason_id
    FROM edu_students s
    LEFT JOIN edu_groups g ON g.id = s.group_id
    LEFT JOIN att_attendance a
           ON a.student_id = s.id AND a.date = :date
          " . ($attendanceHasSemester && $selectedSemesterId > 0 ? " AND a.semester_id = :semester_id" : "") . "
    WHERE s.group_id = :group_id
";
$params = [':date' => $selectedDate, ':group_id' => $selectedGrp];
if ($attendanceHasSemester && $selectedSemesterId > 0) {
    $params[':semester_id'] = $selectedSemesterId;
}
if ($selectedStudent > 0) {
    $studentSql .= " AND s.id = :student_id";
    $params[':student_id'] = $selectedStudent;
}
$studentSql .= " ORDER BY s.surname, s.name, s.patronymic";
$stmt = $pdo->prepare($studentSql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// ── QR-посещаемость: первый вход и последний выход каждого студента за дату ─────
$qrActions = [];
if (!empty($students)) {
    $iinList = array_column($students, 'iin');
    $iinPlaceholders = implode(',', array_fill(0, count($iinList), '?'));
    // Первый вход (entry) за день
    $stmtEntry = $pdo->prepare("
        SELECT iin, MIN(action_time) AS entry_time
        FROM qr_attendance
        WHERE DATE(action_time) = ?
          AND action = 'entry'
          AND iin IN ($iinPlaceholders)
        GROUP BY iin
    ");
    $stmtEntry->execute(array_merge([$selectedDate], $iinList));
    foreach ($stmtEntry->fetchAll() as $row) {
        $qrActions[$row['iin']]['entry_time'] = $row['entry_time'];
    }
    // Последний выход (exit) за день
    $stmtExit = $pdo->prepare("
        SELECT iin, MAX(action_time) AS exit_time
        FROM qr_attendance
        WHERE DATE(action_time) = ?
          AND action = 'exit'
          AND iin IN ($iinPlaceholders)
        GROUP BY iin
    ");
    $stmtExit->execute(array_merge([$selectedDate], $iinList));
    foreach ($stmtExit->fetchAll() as $row) {
        $qrActions[$row['iin']]['exit_time'] = $row['exit_time'];
    }
}

// ── Данные текущей группы ─────────────────────────────────────────────────────
$groupInfo = $groups[$selectedGrp] ?? ($groups ? reset($groups) : ['name'=>'—','specialty'=>'','curator_name'=>'','curator_id'=>null]);

// Статистика за день
$total   = count($students);
$present = count(array_filter($students, fn($s) => $s['status'] === 'present'));
$absent  = count(array_filter($students, fn($s) => $s['status'] === 'absent'));
$excused = count(array_filter($students, fn($s) => $s['status'] === 'excused'));
$late    = count(array_filter($students, fn($s) => $s['status'] === 'late'));
$pct     = $total > 0 ? round($present / $total * 100) : 0;

// ── Рапортичка: вычисляем диапазон дат по периоду ───────────────────────
$daysInMonth = (int)date('t', strtotime($reportMonth . '-01'));
$today_d     = (date('Y-m') === $reportMonth) ? (int)date('d') : $daysInMonth;

[$rapYear, $rapMonthNum] = array_map('intval', explode('-', $reportMonth));

switch ($reportPeriod) {
    case 'custom':
        $rapDateFrom = $customDateFrom;
        $rapDateTo   = $customDateTo;
        break;

    case 'week':
        $refDay     = (date('Y-m') === $reportMonth) ? (int)date('d') : $daysInMonth;
        $refTs      = mktime(0,0,0,$rapMonthNum,$refDay,$rapYear);
        $dow        = (int)date('N', $refTs);
        $rapDateFrom = date('Y-m-d', $refTs - ($dow-1)*86400);
        $rapDateTo   = date('Y-m-d', $refTs + (7-$dow)*86400);
        $rapDateFrom = max($rapDateFrom, "$rapYear-" . sprintf('%02d',$rapMonthNum) . '-01');
        $rapDateTo   = min($rapDateTo,   "$rapYear-" . sprintf('%02d',$rapMonthNum) . "-$daysInMonth");
        break;

    case 'semester':
        if ($selectedSemester) {
            $rapDateFrom = $selectedSemester['start_date'];
            $rapDateTo   = $selectedSemester['end_date'];
        } elseif ($rapMonthNum >= 9) {
            $rapDateFrom = "$rapYear-09-01";
            $rapDateTo   = ($rapYear+1) . "-01-31";
        } else {
            $rapDateFrom = "$rapYear-02-01";
            $rapDateTo   = "$rapYear-06-30";
        }
        break;

    case 'year':
        // Учебный год: сентябрь текущего или прошлого года — по сегодня
        $yearStart   = ($rapMonthNum >= 9) ? $rapYear : $rapYear - 1;
        $rapDateFrom = $yearStart . "-09-01";
        $rapDateTo   = ($yearStart + 1) . "-08-31";
        break;

    default: // month
        $rapDateFrom = "$rapYear-" . sprintf('%02d',$rapMonthNum) . '-01';
        $rapDateTo   = "$rapYear-" . sprintf('%02d',$rapMonthNum) . "-$daysInMonth";
}

// Не обрезаем период сегодняшней датой: выбранный семестр может быть будущим
// (например 2026/2027), и отчёты должны показывать именно его диапазон.
if ($rapDateFrom > $rapDateTo) {
    if ($selectedSemester) {
        $rapDateFrom = $selectedSemester['start_date'];
        $rapDateTo   = $selectedSemester['end_date'];
    } else {
        [$rapDateFrom, $rapDateTo] = [$rapDateTo, $rapDateFrom];
    }
}

// Список дат внутри диапазона (для колонок таблицы)
$rapDates = [];
$cursor   = strtotime($rapDateFrom);
$endTs    = strtotime($rapDateTo);
while ($cursor <= $endTs) {
    $rapDates[] = date('Y-m-d', $cursor);
    $cursor += 86400;
}

// ── Загрузка посещаемости из БД ──────────────────────────────────────────
$rapData = [];
if (!empty($rapDates)) {
    $placeholders = implode(',', array_fill(0, count($rapDates), '?'));
    $rapSql = "
        SELECT a.student_id,
               a.date,
               a.status,
               a.hours_missed
        FROM att_attendance a
        INNER JOIN edu_students s ON s.id = a.student_id
        WHERE s.group_id = ?
    ";
    $rapParams = [(int)$selectedGrp];
    if ($selectedStudent > 0) {
        $rapSql .= " AND s.id = ?";
        $rapParams[] = (int)$selectedStudent;
    }
    if ($attendanceHasSemester && $selectedSemesterId > 0) {
        $rapSql .= " AND a.semester_id = ?";
        $rapParams[] = (int)$selectedSemesterId;
    }
    $rapSql .= " AND a.date IN ($placeholders)";
    $stmtRap = $pdo->prepare($rapSql);
    $stmtRap->execute(array_merge($rapParams, $rapDates));
    foreach ($stmtRap->fetchAll() as $row) {
        $rapData[$row['student_id']][$row['date']] = [
            'status' => $row['status'],
            'hours'  => (int)$row['hours_missed'],
        ];
    }
}

// ── Итоги по каждому студенту ─────────────────────────────────────────────
$rapTotals = [];
foreach ($students as $st) {
    $sid = $st['id'];
    $abH = 0; $exH = 0; $ltH = 0; $days = 0;
    foreach ($rapDates as $dt) {
        $dow = (int)date('N', strtotime($dt));
        if ($dow >= 6) continue;
        $days++;
        if (!isset($rapData[$sid][$dt])) continue;
        $rec = $rapData[$sid][$dt];
        if ($rec['status'] === 'absent')  $abH += $rec['hours'];
        if ($rec['status'] === 'excused') $exH += $rec['hours'];
        if ($rec['status'] === 'late')    $ltH += $rec['hours'];
    }
    $maxH    = $days * 6;
    $stPctR  = $maxH > 0 ? max(0, round((1 - $abH / $maxH) * 100)) : 100;
    $rapTotals[$sid] = ['absent_h' => $abH, 'excused_h' => $exH, 'late_h' => $ltH, 'pct' => $stPctR];
}

// ── Итоговая строка по группе ─────────────────────────────────────────────
$rapGroupAbsH   = array_sum(array_column($rapTotals, 'absent_h'));
$rapGroupExcH   = array_sum(array_column($rapTotals, 'excused_h'));
$rapGroupLateH  = array_sum(array_column($rapTotals, 'late_h'));
$rapGroupAvgPct = count($rapTotals) > 0 ? round(array_sum(array_column($rapTotals,'pct')) / count($rapTotals)) : 100;

// ── Справки: загрузка из БД ───────────────────────────────────────────────
$docsTableExists = false;
try {
    $pdo->query("SELECT 1 FROM att_documents LIMIT 1");
    $docsTableExists = true;
} catch (PDOException $e) { /* таблица не создана */ }

$documents  = [];
$docsPending = 0;
if ($docsTableExists) {
    $stmtDocs = $pdo->prepare("
        SELECT d.*,
               s.surname, s.name AS first_name, s.patronymic,
               r.name_ru AS reason_name
        FROM att_documents d
        INNER JOIN edu_students s ON s.id = d.student_id
        LEFT JOIN  att_absence_reasons r ON r.id = d.reason_id
        WHERE d.group_id = :gid
          " . ($selectedSemester ? " AND d.date_from <= :sem_to AND d.date_to >= :sem_from" : "") . "
        ORDER BY d.created_at DESC
        LIMIT 100
    ");
    $docParams = [':gid' => $selectedGrp];
    if ($selectedSemester) {
        $docParams[':sem_from'] = $selectedSemester['start_date'];
        $docParams[':sem_to']   = $selectedSemester['end_date'];
    }
    $stmtDocs->execute($docParams);
    $documents   = $stmtDocs->fetchAll();
    $docsPending = count(array_filter($documents, fn($d) => $d['status'] === 'pending'));
}

// ── АНАЛИТИКА: загрузка данных из БД ─────────────────────────────────────
$anPeriod    = $_GET['an_period'] ?? ($selectedSemester ? 'semester' : 'month');
if (!in_array($anPeriod, ['month','semester','year'], true)) { $anPeriod = $selectedSemester ? 'semester' : 'month'; }
$anMonth     = preg_match('/^\d{4}-\d{2}$/', $_GET['an_month'] ?? '') ? $_GET['an_month'] : date('Y-m');
[$anY, $anM] = array_map('intval', explode('-', $anMonth));

if ($anPeriod === 'semester' && $selectedSemester) {
    $anDateFrom = $selectedSemester['start_date'];
    $anDateTo   = $selectedSemester['end_date'];
} elseif ($anPeriod === 'year') {
    $anYear    = $anM >= 9 ? $anY : $anY - 1;
    $anDateFrom = $anYear    . '-09-01';
    $anDateTo   = ($anYear+1) . '-08-31';
} else {
    $anDateFrom = "$anY-" . sprintf('%02d', $anM) . '-01';
    $anDateTo   = "$anY-" . sprintf('%02d', $anM) . '-' . date('t', mktime(0,0,0,$anM,1,$anY));
}
// Не обрезаем аналитику сегодняшней датой, чтобы будущий выбранный семестр не сбрасывался на текущий месяц.

$anGroupIds = $isAdmin ? array_keys($groups) : array_keys($groups);
if (empty($anGroupIds)) $anGroupIds = [0];
$anIn = implode(',', array_map('intval', $anGroupIds));

// ── 1. Посещаемость по группам ───────────────────────────────────────────
$anGroups = $pdo->query("
    SELECT
        g.id,
        g.name                          AS group_name,
        COALESCE(sp.name_ru,'')         AS specialty,
        COUNT(DISTINCT s.id)            AS total_students,
        COUNT(DISTINCT CASE WHEN a.status='absent'  THEN a.id END) AS absent_records,
        COUNT(DISTINCT CASE WHEN a.status='excused' THEN a.id END) AS excused_records,
        COUNT(DISTINCT CASE WHEN a.status='late'    THEN a.id END) AS late_records,
        COALESCE(SUM(CASE WHEN a.status='absent'  THEN a.hours_missed ELSE 0 END),0) AS absent_hours,
        COALESCE(SUM(CASE WHEN a.status='excused' THEN a.hours_missed ELSE 0 END),0) AS excused_hours,
        COALESCE(u.full_name, '')        AS curator_name
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    LEFT JOIN edu_students s     ON s.group_id = g.id
    LEFT JOIN att_attendance a   ON a.student_id = s.id
                                AND a.date BETWEEN '$anDateFrom' AND '$anDateTo'
                                " . ($attendanceHasSemester && $selectedSemesterId > 0 ? " AND a.semester_id = " . (int)$selectedSemesterId : "") . "
    LEFT JOIN users u            ON u.id = g.curator_id
    WHERE g.id IN ($anIn)
    GROUP BY g.id, g.name, sp.name_ru, u.full_name
    ORDER BY g.name
")->fetchAll();

$workDaysAn = 0;
$cur = strtotime($anDateFrom);
while ($cur <= strtotime($anDateTo)) {
    if ((int)date('N',$cur) <= 5) $workDaysAn++;
    $cur += 86400;
}
foreach ($anGroups as &$ag) {
    $maxH = max(1, $ag['total_students'] * $workDaysAn * 6);
    $ag['pct'] = max(0, round((1 - $ag['absent_hours'] / $maxH) * 100));
}
unset($ag);
usort($anGroups, fn($a,$b) => $a['pct'] - $b['pct']);

// ── 2. Студенты с пропусками (топ 30) ───────────────────────────────────
$anRiskStudents = $pdo->prepare("
    SELECT
        s.id, s.surname, s.name AS first_name, s.patronymic,
        g.name AS group_name,
        COALESCE(SUM(CASE WHEN a.status='absent'  THEN a.hours_missed ELSE 0 END),0) AS absent_h,
        COALESCE(SUM(CASE WHEN a.status='excused' THEN a.hours_missed ELSE 0 END),0) AS excused_h,
        COALESCE(SUM(CASE WHEN a.status='late'    THEN a.hours_missed ELSE 0 END),0) AS late_h,
        COUNT(DISTINCT CASE WHEN a.status IN ('absent','late') THEN a.date END) AS missed_days
    FROM edu_students s
    INNER JOIN edu_groups g ON g.id = s.group_id
    LEFT JOIN att_attendance a ON a.student_id = s.id
                               AND a.date BETWEEN :df AND :dt
                               " . ($attendanceHasSemester && $selectedSemesterId > 0 ? " AND a.semester_id = :an_semester_id" : "") . "
    WHERE g.id IN ($anIn)
    GROUP BY s.id, s.surname, s.name, s.patronymic, g.name
    HAVING absent_h > 0
    ORDER BY absent_h DESC
    LIMIT 30
");
$anRiskParams = [':df'=>$anDateFrom,':dt'=>$anDateTo];
if ($attendanceHasSemester && $selectedSemesterId > 0) $anRiskParams[':an_semester_id'] = $selectedSemesterId;
$anRiskStudents->execute($anRiskParams);
$anRiskStudents = $anRiskStudents->fetchAll();

$maxHperStudent = max(1, $workDaysAn * 6);
foreach ($anRiskStudents as &$rs) {
    $rs['pct'] = max(0, round((1 - $rs['absent_h'] / $maxHperStudent) * 100));
}
unset($rs);

// ── 3. Динамика по дням (текущая группа) ────────────────────────────────
$anTrend = $pdo->prepare("
    SELECT
        a.date,
        COUNT(DISTINCT s.id)  AS total,
        SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN a.status='absent'  THEN 1 ELSE 0 END) AS absent,
        SUM(CASE WHEN a.status='excused' THEN 1 ELSE 0 END) AS excused,
        SUM(CASE WHEN a.status='late'    THEN 1 ELSE 0 END) AS late
    FROM att_attendance a
    INNER JOIN edu_students s ON s.id = a.student_id
    WHERE s.group_id = :gid
      AND a.date BETWEEN :df AND :dt
      " . ($attendanceHasSemester && $selectedSemesterId > 0 ? " AND a.semester_id = :an_semester_id" : "") . "
    GROUP BY a.date
    ORDER BY a.date ASC
");
$anTrendParams = [':gid'=>$selectedGrp, ':df'=>$anDateFrom, ':dt'=>$anDateTo];
if ($attendanceHasSemester && $selectedSemesterId > 0) $anTrendParams[':an_semester_id'] = $selectedSemesterId;
$anTrend->execute($anTrendParams);
$anTrend = $anTrend->fetchAll();

// ── 4. Пропуски по причинам ──────────────────────────────────────────────
$anReasons = $pdo->prepare("
    SELECT
        COALESCE(r.name_ru, 'Без причины') AS reason_name,
        COUNT(*) AS cnt,
        SUM(a.hours_missed) AS hours
    FROM att_attendance a
    LEFT JOIN att_absence_reasons r ON r.id = a.reason_id
    INNER JOIN edu_students s ON s.id = a.student_id
    WHERE s.group_id IN ($anIn)
      AND a.status IN ('absent','excused')
      AND a.date BETWEEN :df AND :dt
      " . ($attendanceHasSemester && $selectedSemesterId > 0 ? " AND a.semester_id = :an_semester_id" : "") . "
    GROUP BY r.id, r.name_ru
    ORDER BY hours DESC
");
$anReasonParams = [':df'=>$anDateFrom,':dt'=>$anDateTo];
if ($attendanceHasSemester && $selectedSemesterId > 0) $anReasonParams[':an_semester_id'] = $selectedSemesterId;
$anReasons->execute($anReasonParams);
$anReasons = $anReasons->fetchAll();
$anReasonsTotal = max(1, array_sum(array_column($anReasons, 'hours')));

// ── 5. Сводные KPI для аналитики ────────────────────────────────────────
$anTotalStudents = array_sum(array_column($anGroups, 'total_students'));
$anTotalAbsH     = array_sum(array_column($anGroups, 'absent_hours'));
$anTotalExcH     = array_sum(array_column($anGroups, 'excused_hours'));
$anAvgPct        = count($anGroups) > 0
    ? round(array_sum(array_column($anGroups,'pct')) / count($anGroups))
    : 100;
$anRiskCount     = count(array_filter($anGroups, fn($g) => $g['pct'] < 75));

// ── КРИТЕРИИ: параметры и данные ─────────────────────────────────────────
$crGroupId   = isset($_GET['cr_group']) ? (int)$_GET['cr_group'] : 0;
$crDefaultFrom = $selectedSemester ? $selectedSemester['start_date'] : date('Y-m-01');
$crDefaultTo   = $selectedSemester ? $selectedSemester['end_date']   : date('Y-m-t');
$crDateFrom  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['cr_from'] ?? '') ? $_GET['cr_from'] : $crDefaultFrom;
$crDateTo    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['cr_to']   ?? '') ? $_GET['cr_to']   : $crDefaultTo;
if ($crDateFrom > $crDateTo) { [$crDateFrom, $crDateTo] = [$crDateTo, $crDateFrom]; }
$crThreshold = max(1, min(100, (int)($_GET['cr_threshold'] ?? 75)));

// Рабочих дней в периоде
$crWorkDays = 0;
$crCur = strtotime($crDateFrom);
while ($crCur <= strtotime($crDateTo)) {
    if ((int)date('N', $crCur) <= 5) $crWorkDays++;
    $crCur += 86400;
}
$crMaxHours = max(1, $crWorkDays * 6);

// Группы для фильтра (те же что доступны пользователю)
$crGroupIds = $crGroupId > 0 ? [$crGroupId] : array_keys($groups);
if (empty($crGroupIds)) $crGroupIds = [0];
$crIn = implode(',', array_map('intval', $crGroupIds));

// Данные по студентам
$crStmt = $pdo->prepare("
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
                               " . ($attendanceHasSemester && $selectedSemesterId > 0 ? " AND a.semester_id = :cr_semester_id" : "") . "
    WHERE g.id IN ($crIn)
    GROUP BY s.id, s.surname, s.name, s.patronymic, g.name
    ORDER BY g.name, s.surname, s.name
");
$crParams = [':df' => $crDateFrom, ':dt' => $crDateTo];
if ($attendanceHasSemester && $selectedSemesterId > 0) $crParams[':cr_semester_id'] = $selectedSemesterId;
$crStmt->execute($crParams);
$crStudents = $crStmt->fetchAll();

// Вычисляем % и статус
foreach ($crStudents as &$cs) {
    $cs['pct']    = (int)round(max(0, ($crMaxHours - $cs['absent_h']) / $crMaxHours * 100));
    $cs['status'] = $cs['pct'] >= $crThreshold ? 'норма' : 'риск';
}
unset($cs);

// KPI
$crTotalStudents = count($crStudents);
$crRiskCount     = count(array_filter($crStudents, fn($s) => $s['status'] === 'риск'));
$crAvgPct        = $crTotalStudents > 0 ? (int)round(array_sum(array_column($crStudents,'pct')) / $crTotalStudents) : 100;
$crTotalAbsH     = (int)array_sum(array_column($crStudents, 'absent_h'));
$crTotalExcH     = (int)array_sum(array_column($crStudents, 'excused_h'));
$crGroupCount    = count(array_unique(array_column($crStudents, 'group_name')));

// ── Рендер страницы ──────────────────────────────────────────────────────
require_once 'layout.php';
