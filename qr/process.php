<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php'; // timezone берём из db.php

$iin    = trim($_POST['iin'] ?? '');
$action = trim($_POST['action'] ?? '');
$device = $_SERVER['REMOTE_ADDR'];

if (!preg_match('/^\d{12}$/', $iin)) {
    echo json_encode(['success' => false, 'message' => 'Неверный формат ИИН']);
    exit;
}

if (!in_array($action, ['entry', 'exit'])) {
    echo json_encode(['success' => false, 'message' => 'Неверное действие']);
    exit;
}

// ===== ЗАЩИТА: один IP не более 1 ИИН за последние 60 минут =====
if ($action === 'entry') {
    $stmt_ip = $pdo->prepare("
        SELECT COUNT(DISTINCT iin) AS cnt FROM attendance
        WHERE device_ip = ?
          AND action = 'entry'
          AND TIMESTAMPDIFF(MINUTE, action_time, NOW()) <= 60
    ");
    $stmt_ip->execute([$device]);
    $ip_count = $stmt_ip->fetch()['cnt'];

    if ($ip_count >= 1) {
        echo json_encode([
            'success' => false,
            'message' => 'С этого устройства уже отмечен студент. Попробуйте через час.'
        ]);
        exit;
    }
}

// ===== ЗАЩИТА: логика вход/выход =====
$stmt_last = $pdo->prepare("
    SELECT action, action_time FROM attendance
    WHERE iin = ?
    ORDER BY action_time DESC LIMIT 1
");
$stmt_last->execute([$iin]);
$last = $stmt_last->fetch();

if ($action === 'entry') {
    // Нельзя войти если последнее действие — вход
    if ($last && $last['action'] === 'entry') {
        echo json_encode([
            'success' => false,
            'message' => 'Вы уже отметили вход. Сначала отметьте выход.'
        ]);
        exit;
    }

    // Повторный вход только через час после выхода
    if ($last && $last['action'] === 'exit') {
        $time_diff = $pdo->prepare("
            SELECT TIMESTAMPDIFF(MINUTE, ?, NOW()) AS diff_min
        ");
        $time_diff->execute([$last['action_time']]);
        $diff_min = $time_diff->fetch()['diff_min'];

        if ($diff_min < 60) {
            $wait = ceil(60 - $diff_min);
            echo json_encode([
                'success' => false,
                'message' => 'Повторный вход возможен через ' . $wait . ' мин.'
            ]);
            exit;
        }
    }
}

if ($action === 'exit') {
    // Нельзя выйти если нет ни одной записи или последнее действие — выход
    if (!$last || $last['action'] === 'exit') {
        echo json_encode([
            'success' => false,
            'message' => 'Сначала отметьте вход.'
        ]);
        exit;
    }
}

// ===== Ищем студента =====
$stmt = $pdo->prepare("
    SELECT s.surname, s.name, s.patronymic, s.group_id, g.name AS group_name
    FROM edu_students s
    LEFT JOIN edu_groups g ON g.id = s.group_id
    WHERE s.iin = ?
");
$stmt->execute([$iin]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'ИИН не найден в базе']);
    exit;
}

// ===== Записываем событие =====
$stmt3 = $pdo->prepare("
    INSERT INTO attendance (iin, surname, name, patronymic, group_id, action, device_ip)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt3->execute([
    $iin,
    $student['surname'],
    $student['name'],
    $student['patronymic'],
    $student['group_id'],
    $action,
    $device
]);

// Время из MySQL (уже правильное через SET time_zone в db.php)
$time_row = $pdo->query("
    SELECT DATE_FORMAT(NOW(), '%H:%i') AS t,
           DATE_FORMAT(NOW(), '%d.%m.%Y') AS d
")->fetch();

// ===== $full была не определена — исправлено =====
$full = $student['surname'] . ' ' . $student['name'] . ' ' . $student['patronymic'];

echo json_encode([
    'success'    => true,
    'student'    => $full,
    'group_name' => $student['group_name'] ?? 'Группа #' . $student['group_id'],
    'action'     => $action,
    'time'       => $time_row['t'],
    'date'       => $time_row['d'],
]);
?>