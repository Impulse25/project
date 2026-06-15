<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','director');

$id  = (int)($_GET['id'] ?? 0);
$pdo = getPDO();

if (!$id) {
    header('Location: ' . SITE_URL . '/users.php'); exit;
}

$current = currentUser();
if ($id === $current['id']) {
    $_SESSION['flash_error'] = "Нельзя удалить собственный аккаунт.";
    header('Location: ' . SITE_URL . '/users.php'); exit;
}

// Удаляем связанные данные
$pdo->prepare("DELETE FROM achievements WHERE user_id=?")->execute([$id]);
$pdo->prepare("DELETE FROM certificates WHERE user_id=?")->execute([$id]);
$pdo->prepare("DELETE FROM ratings WHERE user_id=?")->execute([$id]);
$pdo->prepare("DELETE FROM event_participants WHERE user_id=?")->execute([$id]);

try {
    $pdo->prepare("DELETE FROM students WHERE user_id=?")->execute([$id]);
} catch (Exception $e) {}

$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);

$_SESSION['flash'] = "Пользователь удалён.";
header('Location: ' . SITE_URL . '/users.php');