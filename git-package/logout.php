<?php
// logout.php - Выход из системы с логированием

require_once 'config/db.php';
require_once 'includes/auth.php';

// Проверяем что пользователь авторизован
if (isLoggedIn()) {
    $user = getCurrentUser();
    
    // Логируем выход ПЕРЕД уничтожением сессии
    try {
        logUserAction(
            $pdo, 
            $user['id'], 
            $user['username'], 
            $user['full_name'], 
            $user['role'], 
            'logout', 
            $user['auth_type'],
            true
        );
        
        error_log("LOGOUT: Пользователь {$user['username']} вышел из системы");
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Уничтожаем сессию
session_destroy();

// Перенаправляем на страницу входа
header('Location: index.php');
exit();
?>
