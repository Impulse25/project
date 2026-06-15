<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$userId  = (int)$_GET['id'];
$groupId = (int)$_GET['group_id'];
getPDO()->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);

$_SESSION['flash'] = "Студент удалён.";
header('Location: ' . SITE_URL . '/group_detail.php?id=' . $groupId);