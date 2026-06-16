<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$eventId = (int)($_POST['event_id'] ?? 0);
$title   = trim($_POST['title'] ?? '');
$docDate = $_POST['doc_date'] ?: null;
$place   = trim($_POST['place'] ?? '');

if (!$eventId) {
    $_SESSION['flash_error'] = "Не выбрано мероприятие.";
    header('Location: ' . SITE_URL . '/events.php'); exit;
}

if (empty($_FILES['pdf_file']['name'])) {
    $_SESSION['flash_error'] = "Файл не выбран.";
    header('Location: ' . SITE_URL . '/events.php'); exit;
}

$file    = $_FILES['pdf_file'];
$maxSize = 10 * 1024 * 1024;

if ($file['size'] > $maxSize) {
    $_SESSION['flash_error'] = "Файл слишком большой (макс. 10 МБ).";
    header('Location: ' . SITE_URL . '/events.php'); exit;
}

$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf','jpg','jpeg','png'];
if (!in_array($ext, $allowed)) {
    $_SESSION['flash_error'] = "Разрешены только PDF, JPG, PNG файлы.";
    header('Location: ' . SITE_URL . '/events.php'); exit;
}

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$filename = 'event_' . $eventId . '_' . time() . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $_SESSION['flash_error'] = "Ошибка при сохранении файла.";
    header('Location: ' . SITE_URL . '/events.php'); exit;
}

$pdo = getPDO();

$pdo->exec("CREATE TABLE IF NOT EXISTS event_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    title VARCHAR(255),
    doc_date DATE,
    place VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
)");

$pdo->prepare("INSERT INTO event_documents (event_id, uploaded_by, filename, title, doc_date, place)
    VALUES (?, ?, ?, ?, ?, ?)")
    ->execute([$eventId, $user['id'], $filename, $title ?: null, $docDate, $place ?: null]);

$_SESSION['flash'] = "Документ успешно загружен и прикреплён к мероприятию.";
header('Location: ' . SITE_URL . '/events.php');