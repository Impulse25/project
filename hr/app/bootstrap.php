<?php
// hr/app/bootstrap.php — запуск модуля, авторизация и данные пользователя
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';
$userName = $_SESSION['full_name'] ?? '';
$isAdmin  = in_array($userRole, ['admin', 'director']);
$nameParts = explode(' ', trim($userName));
$initials  = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($nameParts, 0, 2)));

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
