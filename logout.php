<?php
// logout.php - Выход из системы

require_once 'includes/auth.php';

// Проверяем, что пользователь авторизован
if (isLoggedIn()) {
    // Получаем данные пользователя перед выходом (опционально для логирования)
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'unknown';
    
    // Уничтожаем сессию
    session_destroy();
    
    // Опционально: можно добавить логирование выхода в будущем
    // error_log("User logout: $username (ID: $userId)");
}

// Перенаправляем на страницу входа
header('Location: index.php');
exit();
?>
