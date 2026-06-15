<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','teacher','director');

$id     = (int)($_GET['id'] ?? 0);
$userId = (int)($_GET['user_id'] ?? 0);
$tab    = in_array($_GET['tab'] ?? '', ['students','teachers']) ? $_GET['tab'] : 'students';
$from   = $_GET['from'] ?? 'achievements';

if ($id) {
    $row = getPDO()->prepare("SELECT file_path FROM achievements WHERE id=?");
    $row->execute([$id]);
    $ach = $row->fetch();
    if ($ach && $ach['file_path']) {
        $fp = __DIR__ . '/../uploads/' . $ach['file_path'];
        if (file_exists($fp)) unlink($fp);
    }
    getPDO()->prepare("DELETE FROM achievements WHERE id=?")->execute([$id]);
    if ($userId) recalcRating($userId);
}

$_SESSION['flash'] = "Достижение удалено.";
if ($from === 'profile') {
    header('Location: ' . SITE_URL . '/profile.php?id=' . $userId);
} else {
    header('Location: ' . SITE_URL . '/achievements.php?tab=' . $tab);
}