<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin', 'teacher');

$title       = trim($_POST['title'] ?? '');
$event_date  = $_POST['event_date'] ?: null;
$location    = trim($_POST['location'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($title === '') {
    header('Location: ' . SITE_URL . '/events.php');
    exit;
}

try {
    getPDO()->exec("ALTER TABLE events ADD COLUMN location VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {}

getPDO()->prepare("INSERT INTO events (title, event_date, location, description) VALUES (?, ?, ?, ?)")
    ->execute([$title, $event_date, $location ?: null, $description]);

$_SESSION['flash'] = "Мероприятие «$title» добавлено.";
header('Location: ' . SITE_URL . '/events.php');