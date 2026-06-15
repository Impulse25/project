<?php
// hr/app/bootstrap.php — запуск модуля, авторизация и данные пользователя
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/access.php';

$userId   = (int)$_SESSION['user_id'];
$userRole = hr_normalize_role($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? '');
$userName = $_SESSION['full_name'] ?? '';

$isSystemAdmin = $userRole === 'admin';
$isDirector    = $userRole === 'director';
$isTeacher     = $userRole === 'teacher';
$isAdmin       = $isSystemAdmin; // оставлено для старых шаблонов: admin — только администратор
$canOpenAdminPanel = $isSystemAdmin || $isDirector;
$canManageHrRecords = $isSystemAdmin || $isTeacher;

$nameParts = explode(' ', trim($userName));
$initials  = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($nameParts, 0, 2)));

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
