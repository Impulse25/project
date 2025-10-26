<?php
// delete_user.php - Удаление пользователя (только для админа)

require_once 'config/db.php';
require_once 'includes/auth.php';

requireRole('admin');

$currentUser = getCurrentUser();
$userId = $_GET['id'] ?? 0;

// Проверка что не удаляем самого себя
if ($userId == $currentUser['id']) {
    header('Location: admin_dashboard.php?error=cannot_delete_self');
    exit();
}

// Удаление пользователя
try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    header('Location: admin_dashboard.php?success=user_deleted');
    exit();
    
} catch (PDOException $e) {
    header('Location: admin_dashboard.php?error=delete_failed');
    exit();
}
?>