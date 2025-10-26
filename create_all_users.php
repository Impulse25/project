<?php
// create_all_users.php - Создание всех пользователей системы
require_once 'config/db.php';

$password = '12345'; // Один пароль для всех
$hash = password_hash($password, PASSWORD_DEFAULT);

// Удаляем всех старых пользователей
$pdo->query("SET FOREIGN_KEY_CHECKS = 0");
$pdo->query("DELETE FROM users");
$pdo->query("SET FOREIGN_KEY_CHECKS = 1");

// Массив пользователей
$users = [
    ['admin', 'Администратор', 'admin', 'Системный администратор'],
    ['director', 'Темирбулатова А.А.', 'director', 'Директор СВГТК'],
    ['teacher1', 'Иванов Иван Иванович', 'teacher', 'Преподаватель информатики'],
    ['teacher2', 'Петрова Мария Сергеевна', 'teacher', 'Преподаватель математики'],
    ['tech1', 'Сидоров Петр Васильевич', 'technician', 'Системный техник'],
    ['tech2', 'Козлов Алексей Николаевич', 'technician', 'Системный техник']
];

// Создаём всех пользователей
$stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, position) VALUES (?, ?, ?, ?, ?)");

echo "<h2>✅ Создание пользователей</h2>";
echo "<p><strong>Пароль для всех:</strong> $password</p>";
echo "<hr>";

foreach ($users as $user) {
    $stmt->execute([$user[0], $hash, $user[1], $user[2], $user[3]]);
    echo "✅ <strong>{$user[0]}</strong> - {$user[1]} ({$user[2]})<br>";
}

echo "<hr>";
echo "<h3>🎉 Готово! Все пользователи созданы.</h3>";
echo "<p><a href='index.php'>Перейти на страницу входа</a></p>";

// Проверка
echo "<hr><h3>Проверка:</h3>";
$stmt = $pdo->query("SELECT username, full_name, role FROM users ORDER BY role");
while ($user = $stmt->fetch()) {
    echo "- {$user['username']} ({$user['role']})<br>";
}
?>