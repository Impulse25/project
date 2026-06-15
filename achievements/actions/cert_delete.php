<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin', 'teacher', 'director');

$pdo    = getPDO();
$id     = (int)($_GET['id']      ?? 0);
$userId = (int)($_GET['user_id'] ?? 0);

if (!$id)
    redirectError('Некорректный запрос.', SITE_URL . '/achievements.php?tab=certs');

$row = $pdo->prepare("SELECT file_path FROM certificates WHERE id = ?");
$row->execute([$id]);
$cert = $row->fetch();
if ($cert && $cert['file_path']) {
    $filePath = dirname(__DIR__) . '/uploads/' . $cert['file_path'];
    if (file_exists($filePath)) @unlink($filePath);
}

$pdo->prepare("DELETE FROM certificates WHERE id = ?")->execute([$id]);

if ($userId) recalcRating($userId);

redirectSuccess('Сертификат удалён.', SITE_URL . '/achievements.php?tab=certs');