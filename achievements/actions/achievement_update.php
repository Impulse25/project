<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','teacher','director');

$id          = (int)($_POST['id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$category    = trim($_POST['category'] ?? 'other');
$level       = trim($_POST['level'] ?? 'college');
$place       = (isset($_POST['place']) && $_POST['place'] !== '') ? (int)$_POST['place'] : null;
$dateEvent   = (!empty($_POST['date_event'])) ? $_POST['date_event'] : null;
$tab         = in_array($_POST['tab'] ?? '', ['students','teachers']) ? $_POST['tab'] : 'students';

if (!$id || !$title) {
    $_SESSION['flash_error'] = "Не заполнено название.";
    header('Location: ' . SITE_URL . '/achievements.php?tab=' . $tab); exit;
}

getPDO()->prepare(
    "UPDATE achievements SET title=?, description=?, category=?, level=?, place=?, date_event=? WHERE id=?"
)->execute([$title, $description, $category, $level, $place, $dateEvent, $id]);

$_SESSION['flash'] = "Достижение обновлено.";
header('Location: ' . SITE_URL . '/achievements.php?tab=' . $tab);