<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $pdo = getPDO();
    $row = $pdo->prepare("SELECT name FROM groups_list WHERE id=?");
    $row->execute([$id]);
    $group = $row->fetch();

    $pdo->prepare("UPDATE students SET group_id = NULL WHERE group_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM groups_list WHERE id=?")->execute([$id]);

    $_SESSION['flash'] = "Группа «" . ($group['name'] ?? '') . "» удалена. Студенты откреплены.";
}

header('Location: ' . SITE_URL . '/groups.php');
