<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','director','teacher');

$userId = (int)($_GET['user_id'] ?? 0);

if ($userId) {
    getPDO()->prepare("DELETE FROM ratings WHERE user_id = ?")->execute([$userId]);
    $_SESSION['flash'] = "Пользователь удалён из рейтинга.";
}

header('Location: ' . SITE_URL . '/rating.php');