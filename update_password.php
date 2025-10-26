<?php
// update_password.php - Обновить пароль для пользователя
require_once 'config/db.php';

$username = 'admin';
$newPassword = '12345';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Удаляем старого пользователя
$stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
$stmt->execute([$username]);

// Создаём нового с новым хешем
$stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, position) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$username, $hash, 'Администратор', 'admin', 'Системный администратор']);

echo "✅ Пользователь создан!<br>";
echo "Логин: $username<br>";
echo "Пароль: $newPassword<br>";
echo "Хеш: $hash<br><br>";

// Проверяем
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($newPassword, $user['password'])) {
    echo "✅ <strong>ПРОВЕРКА УСПЕШНА! Теперь можно войти.</strong>";
} else {
    echo "❌ Ошибка проверки!";
}
?>