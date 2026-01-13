<?php
// logout.php - Выход из системы

require_once 'includes/auth.php';

<<<<<<< HEAD
// Вызов logout с передачей $pdo для логирования
logout($pdo);
=======
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
>>>>>>> ae83841d72d8ff3b9f96d54572e7259dd3d73581
