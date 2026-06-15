<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin', 'director');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    $_SESSION['flash_error'] = "Не указано мероприятие.";
    header('Location: ' . SITE_URL . '/events.php'); exit;
}

$pdo = getPDO();

// Удаляем документы мероприятия с диска
try {
    $docs = $pdo->prepare("SELECT filename FROM event_documents WHERE event_id = ?");
    $docs->execute([$id]);
    foreach ($docs->fetchAll() as $doc) {
        $f = __DIR__ . '/../uploads/' . $doc['filename'];
        if (file_exists($f)) @unlink($f);
    }
} catch (Exception $e) {}

// Удаляем мероприятие (каскадно удалит участников и документы)
$pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);

$_SESSION['flash'] = "Мероприятие удалено.";
header('Location: ' . SITE_URL . '/events.php');