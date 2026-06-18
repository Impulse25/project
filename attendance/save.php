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

$stmt = $pdo->prepare("
    INSERT INTO att_attendance (student_id, date, status, hours_missed, reason_id, teacher_id)
    VALUES (:sid, :date, :status, :hours, :reason, :teacher)
    ON DUPLICATE KEY UPDATE
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

        $stmt->execute([
            ':sid'     => $sid,
            ':date'    => $date,
            ':status'  => $status,
            ':hours'   => $hours,
            ':reason'  => $reason,
            ':teacher' => $teacher_id,
        ]);
        $saved++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'saved' => $saved]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
