<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
});
set_error_handler(function($no, $str, $file, $line) {
    throw new ErrorException($str, 0, $no, $file, $line);
});

// [ИСПРАВЛЕНИЕ #1] Проверка авторизации
require_once __DIR__ . '/auth_check.php';

// [ИСПРАВЛЕНИЕ #6] Централизованное подключение к БД
require_once __DIR__ . '/../config/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Не удалось подключиться к базе данных');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['date']) || !isset($data['rows']) || !is_array($data['rows'])) {
    echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса', 'raw' => $raw]);
    exit;
}

// Валидация формата даты
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
    echo json_encode(['success' => false, 'error' => 'Некорректный формат даты']);
    exit;
}

$date       = $data['date'];
$rows       = $data['rows'];
$group_id   = isset($data['group_id']) ? (int)$data['group_id'] : 0;
$semester_id = isset($data['semester_id']) ? (int)$data['semester_id'] : 0;
$teacher_id = (int)$_SESSION['user_id'];
$userRole   = $_SESSION['role'] ?? 'teacher';
$allowed    = ['present', 'absent', 'excused', 'late'];

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

$userDepartmentId = isset($_SESSION['head_department_id']) ? (int)$_SESSION['head_department_id'] : 0;
if (!$userDepartmentId && isset($_SESSION['department_id'])) $userDepartmentId = (int)$_SESSION['department_id'];
$userPosition = $_SESSION['position'] ?? '';
try {
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = :uid LIMIT 1");
    $stmtUser->execute([':uid' => $teacher_id]);
    $dbUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$userDepartmentId && !empty($dbUser['head_department_id'])) $userDepartmentId = (int)$dbUser['head_department_id'];
    if (!$userDepartmentId && !empty($dbUser['department_id'])) $userDepartmentId = (int)$dbUser['department_id'];
    if (!$userPosition && !empty($dbUser['position'])) $userPosition = $dbUser['position'];
} catch (Throwable $e) { $dbUser = []; }

$isDeptHead = (!empty($dbUser['is_department_head']) && (int)$dbUser['is_department_head'] === 1)
          || in_array($userRole, ['department_head','head_department','zav','zav_otdeleniya','dean','manager'], true)
          || (mb_stripos((string)$userPosition, 'зав') !== false && mb_stripos((string)$userPosition, 'отдел') !== false);
$groupDeptColumn = att_column_exists($pdo, 'edu_groups', 'department_id')
    ? 'department_id'
    : (att_column_exists($pdo, 'edu_groups', 'departments_id') ? 'departments_id' : '');
$groupHasDepartment = ($groupDeptColumn !== '');

if (!$userDepartmentId && $isDeptHead) {
    try {
        $deps = $pdo->query("SELECT id, department_name FROM departments ORDER BY CHAR_LENGTH(department_name) DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($deps as $dep) {
            if ($dep['department_name'] && mb_stripos((string)$userPosition, $dep['department_name']) !== false) {
                $userDepartmentId = (int)$dep['id'];
                break;
            }
        }
    } catch (Throwable $e) {}
}


function att_table_exists(PDO $pdo, string $table): bool {
    try {
        $safeTable = str_replace('`', '``', $table);
        $pdo->query("SELECT 1 FROM `$safeTable` LIMIT 1");
        return true;
    } catch (Throwable $e) { return false; }
}

function att_ensure_semester_schema(PDO $pdo): void {
    try {
        if (!att_column_exists($pdo, 'att_attendance', 'semester_id')) {
            $pdo->exec("ALTER TABLE att_attendance ADD COLUMN semester_id INT(10) UNSIGNED DEFAULT NULL AFTER group_id");
        }
    } catch (Throwable $e) {}
    try {
        if (!att_column_exists($pdo, 'att_attendance', 'group_id')) {
            $pdo->exec("ALTER TABLE att_attendance ADD COLUMN group_id INT(10) UNSIGNED DEFAULT NULL AFTER student_id");
        }
    } catch (Throwable $e) {}
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
    } catch (Throwable $e) {}
}

att_ensure_semester_schema($pdo);
$attendanceHasSemester = att_column_exists($pdo, 'att_attendance', 'semester_id');
$attendanceHasGroupId  = att_column_exists($pdo, 'att_attendance', 'group_id');

if ($semester_id <= 0 && att_table_exists($pdo, 'edu_semesters')) {
    try {
        $stmtSem = $pdo->prepare("SELECT id FROM edu_semesters WHERE :dt BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1");
        $stmtSem->execute([':dt' => $date]);
        $semester_id = (int)($stmtSem->fetchColumn() ?: 0);
    } catch (Throwable $e) {}
}
if ($semester_id > 0 && att_table_exists($pdo, 'edu_semesters')) {
    $stmtSemCheck = $pdo->prepare("SELECT id FROM edu_semesters WHERE id = :sid AND :dt BETWEEN start_date AND end_date LIMIT 1");
    $stmtSemCheck->execute([':sid' => $semester_id, ':dt' => $date]);
    if (!$stmtSemCheck->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Дата не входит в выбранный семестр']);
        exit;
    }
}

// Проверяем доступ к группе.
// admin/director — все группы; teacher — свои curator_id; зав. отделения — только группы своего отделения.
$isAdmin = in_array($userRole, ['admin', 'director'], true);

if ($group_id > 0 && !$isAdmin) {
    if ($isDeptHead && $groupHasDepartment && $userDepartmentId > 0) {
        $stmtGroup = $pdo->prepare("SELECT id FROM edu_groups WHERE id = :gid AND `$groupDeptColumn` = :department_id");
        $stmtGroup->execute([':gid' => $group_id, ':department_id' => $userDepartmentId]);
    } else {
        $stmtGroup = $pdo->prepare("SELECT id FROM edu_groups WHERE id = :gid AND curator_id = :tid");
        $stmtGroup->execute([':gid' => $group_id, ':tid' => $teacher_id]);
    }
    if (!$stmtGroup->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Нет доступа к данной группе']);
        exit;
    }
}

// Получаем список допустимых student_id для данной группы (защита от подмены student_id)
if ($group_id > 0) {
    $stmtIds = $pdo->prepare("SELECT id FROM edu_students WHERE group_id = :gid");
    $stmtIds->execute([':gid' => $group_id]);
    $allowedStudentIds = array_flip(array_column($stmtIds->fetchAll(), 'id'));
} else {
    $allowedStudentIds = null;
}

$insertColumns = ['student_id'];
$insertValues  = [':sid'];
if ($attendanceHasGroupId) { $insertColumns[] = 'group_id'; $insertValues[] = ':group_id'; }
if ($attendanceHasSemester) { $insertColumns[] = 'semester_id'; $insertValues[] = ':semester_id'; }
$insertColumns = array_merge($insertColumns, ['date','status','hours_missed','reason_id','teacher_id']);
$insertValues  = array_merge($insertValues,  [':date',':status',':hours',':reason',':teacher']);

$stmt = $pdo->prepare("
    INSERT INTO att_attendance (" . implode(',', $insertColumns) . ")
    VALUES (" . implode(',', $insertValues) . ")
    ON DUPLICATE KEY UPDATE
        " . ($attendanceHasGroupId ? "group_id = VALUES(group_id)," : "") . "
        " . ($attendanceHasSemester ? "semester_id = VALUES(semester_id)," : "") . "
        status       = VALUES(status),
        hours_missed = VALUES(hours_missed),
        reason_id    = VALUES(reason_id),
        teacher_id   = VALUES(teacher_id)
");

try {
    $pdo->beginTransaction();
    $saved = 0;

    foreach ($rows as $row) {
        $sid = (int)$row['student_id'];

        // Проверяем что студент принадлежит указанной группе
        if ($allowedStudentIds !== null && !isset($allowedStudentIds[$sid])) {
            continue;
        }

        $status = in_array($row['status'] ?? '', $allowed) ? $row['status'] : 'present';
        $hours  = max(0, min(8, (int)($row['hours_missed'] ?? 0)));
        $reason = (!empty($row['reason_id']) && (int)$row['reason_id'] > 0)
                  ? (int)$row['reason_id'] : null;

        $execParams = [
            ':sid'     => $sid,
            ':date'    => $date,
            ':status'  => $status,
            ':hours'   => $hours,
            ':reason'  => $reason,
            ':teacher' => $teacher_id,
        ];
        if ($attendanceHasGroupId) { $execParams[':group_id'] = $group_id > 0 ? $group_id : null; }
        if ($attendanceHasSemester) { $execParams[':semester_id'] = $semester_id > 0 ? $semester_id : null; }
        $stmt->execute($execParams);
        $saved++;
    }

    if ($semester_id > 0 && $group_id > 0 && att_table_exists($pdo, 'att_semester_absence_totals')) {
        try {
            $stmtRange = $pdo->prepare("SELECT start_date, end_date FROM edu_semesters WHERE id = :sid LIMIT 1");
            $stmtRange->execute([':sid' => $semester_id]);
            $range = $stmtRange->fetch(PDO::FETCH_ASSOC);
            if ($range) {
                $sumSql = "
                    INSERT INTO att_semester_absence_totals
                        (semester_id, group_id, student_id, absent_hours, excused_hours, late_hours, absent_days, excused_days, late_days)
                    SELECT
                        :semester_id AS semester_id,
                        :group_id AS group_id,
                        s.id AS student_id,
                        COALESCE(SUM(CASE WHEN a.status='absent'  THEN a.hours_missed ELSE 0 END),0) AS absent_hours,
                        COALESCE(SUM(CASE WHEN a.status='excused' THEN a.hours_missed ELSE 0 END),0) AS excused_hours,
                        COALESCE(SUM(CASE WHEN a.status='late'    THEN a.hours_missed ELSE 0 END),0) AS late_hours,
                        COUNT(DISTINCT CASE WHEN a.status='absent'  THEN a.date END) AS absent_days,
                        COUNT(DISTINCT CASE WHEN a.status='excused' THEN a.date END) AS excused_days,
                        COUNT(DISTINCT CASE WHEN a.status='late'    THEN a.date END) AS late_days
                    FROM edu_students s
                    LEFT JOIN att_attendance a ON a.student_id = s.id
                        AND a.date BETWEEN :df AND :dt
                        " . ($attendanceHasSemester ? " AND a.semester_id = :semester_id_join" : "") . "
                    WHERE s.group_id = :group_id_where
                    GROUP BY s.id
                    ON DUPLICATE KEY UPDATE
                        group_id = VALUES(group_id),
                        absent_hours = VALUES(absent_hours),
                        excused_hours = VALUES(excused_hours),
                        late_hours = VALUES(late_hours),
                        absent_days = VALUES(absent_days),
                        excused_days = VALUES(excused_days),
                        late_days = VALUES(late_days),
                        updated_at = CURRENT_TIMESTAMP
                ";
                $sumParams = [
                    ':semester_id' => $semester_id,
                    ':group_id' => $group_id,
                    ':df' => $range['start_date'],
                    ':dt' => $range['end_date'],
                    ':group_id_where' => $group_id,
                ];
                if ($attendanceHasSemester) $sumParams[':semester_id_join'] = $semester_id;
                $pdo->prepare($sumSql)->execute($sumParams);
            }
        } catch (Throwable $e) { /* журнал уже сохранён; сводку можно пересчитать позже */ }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'saved' => $saved, 'semester_id' => $semester_id]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
