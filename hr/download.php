<?php
// hr/download.php — Скачивание документа
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit;
}

require_once '../config/db.php';

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$docId) { http_response_code(404); echo 'Документ не найден'; exit; }

$stmt = $pdo->prepare("SELECT filename, original_name, mime_type FROM hr_documents WHERE id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) { http_response_code(404); echo 'Документ не найден'; exit; }

$path = __DIR__ . '/uploads/' . $doc['filename'];
if (!file_exists($path)) { http_response_code(404); echo 'Файл не найден на сервере'; exit; }

$mime     = $doc['mime_type'] ?: 'application/octet-stream';
$origName = $doc['original_name'];

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($origName) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache');
readfile($path);
exit;
