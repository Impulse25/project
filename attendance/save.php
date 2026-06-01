<?php
session_start();
header('Content-Type: application/json');
// Показываем все PHP-ошибки в JSON чтобы видеть что идёт не так
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
});
set_error_handler(function($no, $str, $file, $line) {
    throw new ErrorException($str, 0, $no, $file, $line);
});

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=p-355792_svgtk;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка подключения: ' . $e->getMessage()]);
    exit;
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
$teacher_id = $_SESSION['user_id'] ?? 1;
$allowed    = ['present', 'absent', 'excused', 'late'];

// Получаем список допустимых student_id для данной группы (защита от подмены)
if ($group_id > 0) {
    $stmtIds = $pdo->prepare("SELECT id FROM edu_students WHERE group_id = :gid");
    $stmtIds->execute([':gid' => $group_id]);
    $allowedStudentIds = array_column($stmtIds->fetchAll(), 'id');
    $allowedStudentIds = array_flip($allowedStudentIds); // для быстрой проверки
} else {
    $allowedStudentIds = null; // group_id не передан — не ограничиваем
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
            continue; // пропускаем чужих студентов
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
            ':teacher' => (int)$teacher_id,
        ]);
        $saved++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'saved' => $saved]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
