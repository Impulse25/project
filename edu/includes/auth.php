<?php
/**
 * includes/auth.php
 * Инициализация сессии и данных текущего пользователя.
 * Подключать в самом начале каждой страницы (до любого вывода).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userRole   = $_SESSION['role']      ?? '';
$userName   = $_SESSION['full_name'] ?? '';
$isAdmin    = in_array($userRole, ['admin', 'director']);
$isLoggedIn = isset($_SESSION['user_id']);

$nameParts = explode(' ', trim($userName));
$initials  = implode('', array_map(
    fn($p) => mb_strtoupper(mb_substr($p, 0, 1)),
    array_slice($nameParts, 0, 2)
));
