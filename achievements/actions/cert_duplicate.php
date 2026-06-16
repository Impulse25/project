<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','teacher','director');

$pdo    = getPDO();
$certId = (int)($_POST['cert_id'] ?? 0);
$user   = currentUser();

if (!$certId) redirectError('Некорректный запрос.', SITE_URL.'/achievements.php?tab=certs');

$orig = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
$orig->execute([$certId]);
$cert = $orig->fetch();

if (!$cert) redirectError('Сертификат не найден.', SITE_URL.'/achievements.php?tab=certs');

$newFile = null;
if (!empty($cert['file_path'])) {
    $uploadDir = dirname(__DIR__) . '/uploads/';
    $origPath  = $uploadDir . $cert['file_path'];
    if (file_exists($origPath)) {
        $ext     = pathinfo($cert['file_path'], PATHINFO_EXTENSION);
        $newFile = 'cert_copy_' . time() . '_' . rand(100,999) . '.' . $ext;
        copy($origPath, $uploadDir . $newFile);
    }
}

$pdo->prepare(
    "INSERT INTO certificates
     (user_id, edu_student_id, title, issuer, issue_date, place, file_path, added_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
)->execute([
    $cert['user_id'],
    $cert['edu_student_id'] ?? null,
    $cert['title'] . ' (копия)',
    $cert['issuer'],
    $cert['issue_date'],
    $cert['place'] ?? null,
    $newFile,
    $user['id'],
]);

redirectSuccess('Сертификат продублирован.', SITE_URL.'/achievements.php?tab=certs');