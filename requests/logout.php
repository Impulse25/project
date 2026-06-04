<?php
// logout.php — Выход из системы
require_once 'config/db.php';
require_once 'includes/auth.php';

logout($pdo);

$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
header('Location: ' . $base . '/');
exit();
