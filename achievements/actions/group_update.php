<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$id        = (int)($_POST['id'] ?? 0);
$name      = trim($_POST['name'] ?? '');
$specialty = trim($_POST['specialty'] ?? '');
$yearStart = (int)($_POST['year_start'] ?? date('Y'));
$teacherId = (int)($_POST['teacher_id'] ?? 0) ?: null;

if ($id && $name) {
    getPDO()->prepare(
        "UPDATE groups_list SET name=?, specialty=?, year_start=?, teacher_id=? WHERE id=?"
    )->execute([$name, $specialty, $yearStart, $teacherId, $id]);
    $_SESSION['flash'] = "Группа «$name» обновлена.";
}

header('Location: ' . SITE_URL . '/groups.php');