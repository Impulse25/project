<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

$user = getCurrentUser();

// Копируем первые 140 строк из teacher_dashboard и смотрим где падает
$tab = 'active';

echo "Шаг 1: переменные OK<br>";

$requests = [];
$activeCount = 0;
$pendingCount = 0;
$waitingCount = 0;
$archiveCount = 0;

if ($tab === 'active') {
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as tech_name FROM requests r LEFT JOIN users u ON r.assigned_to = u.id WHERE r.created_by = ? AND r.status = 'in_progress' ORDER BY r.created_at DESC");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
}
echo "Шаг 2: запросы OK<br>";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status = 'in_progress'");
$stmt->execute([$user['id']]);
$activeCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status IN ('pending', 'approved')");
$stmt->execute([$user['id']]);
$pendingCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status = 'awaiting_approval' AND sent_to_director = 0");
$stmt->execute([$user['id']]);
$waitingCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$archiveCount = $stmt->fetchColumn();

echo "Шаг 3: счётчики OK — active:$activeCount pending:$pendingCount waiting:$waitingCount archive:$archiveCount<br>";

$currentLang = getCurrentLanguage();
echo "Шаг 4: язык OK — $currentLang<br>";

echo "<br><b>Всё работает — проблема в самом файле teacher_dashboard.php на хостинге</b>";
?>