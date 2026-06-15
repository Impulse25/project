<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$name      = trim($_POST['name'] ?? '');
$specialty = trim($_POST['specialty'] ?? '');
$yearStart = (int)($_POST['year_start'] ?? date('Y'));
$teacherId = (int)($_POST['teacher_id'] ?? 0) ?: null;

if ($name) {
    getPDO()->prepare(
        "INSERT INTO groups_list (name, specialty, year_start, teacher_id) VALUES(?, ?, ?, ?)"
    )->execute([$name, $specialty, $yearStart, $teacherId]);
    $_SESSION['flash'] = "Группа «$name» добавлена.";
}

header('Location: ' . SITE_URL . '/groups.php');