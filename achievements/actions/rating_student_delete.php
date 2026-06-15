<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

// Берём роль напрямую из сессии
$userRole = $_SESSION['role'] ?? '';

if (!in_array($userRole, ['admin','director'])) {
    header('Location: ' . SITE_URL . '/rating.php?tab=students'); exit;
}

$studentId = (int)($_GET['student_id'] ?? 0);
if (!$studentId) {
    header('Location: ' . SITE_URL . '/rating.php?tab=students'); exit;
}

$pdo = getPDO();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rating_hidden_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        edu_student_id INT UNSIGNED NOT NULL,
        hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_student (edu_student_id)
    )");
} catch(Exception $e) {}

$pdo->prepare("INSERT IGNORE INTO rating_hidden_students (edu_student_id) VALUES (?)")
    ->execute([$studentId]);

$_SESSION['flash'] = "Студент удалён из рейтинга.";
header('Location: ' . SITE_URL . '/rating.php?tab=students');