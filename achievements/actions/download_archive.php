<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin', 'teacher', 'director');

$pdo       = getPDO();
$studentId = (int)($_GET['student_id'] ?? 0);
$userId    = (int)($_GET['user_id']    ?? 0);

if (!$studentId && !$userId)
    redirectError('Не указан пользователь.', SITE_URL.'/achievements.php');

$uploadDir = dirname(__DIR__) . '/uploads/';
$files     = [];
$name      = 'archive';

if ($studentId) {
    $st = $pdo->prepare("SELECT CONCAT(surname,'_',name) AS fname FROM edu_students WHERE id = ?");
    $st->execute([$studentId]);
    $row  = $st->fetch();
    $name = $row ? preg_replace('/[^a-zа-яё_]/iu', '_', $row['fname']) : 'student_'.$studentId;

    try {
        $q = $pdo->prepare("SELECT file_path FROM achievements WHERE edu_student_id = ? AND file_path IS NOT NULL");
        $q->execute([$studentId]);
        foreach ($q->fetchAll() as $r)
            $files[] = ['path' => $uploadDir.$r['file_path'], 'name' => 'ach_'.basename($r['file_path'])];
    } catch (Exception $e) {}

    try {
        $q = $pdo->prepare("SELECT file_path FROM certificates WHERE edu_student_id = ? AND file_path IS NOT NULL");
        $q->execute([$studentId]);
        foreach ($q->fetchAll() as $r)
            $files[] = ['path' => $uploadDir.$r['file_path'], 'name' => 'cert_'.basename($r['file_path'])];
    } catch (Exception $e) {}

} elseif ($userId) {
    $u = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $u->execute([$userId]);
    $row  = $u->fetch();
    $name = $row ? preg_replace('/[^a-zа-яё_]/iu', '_', $row['full_name']) : 'user_'.$userId;

    try {
        $q = $pdo->prepare("SELECT file_path FROM achievements WHERE user_id = ? AND file_path IS NOT NULL");
        $q->execute([$userId]);
        foreach ($q->fetchAll() as $r)
            $files[] = ['path' => $uploadDir.$r['file_path'], 'name' => 'ach_'.basename($r['file_path'])];
    } catch (Exception $e) {}

    try {
        $q = $pdo->prepare("SELECT file_path FROM certificates WHERE user_id = ? AND file_path IS NOT NULL");
        $q->execute([$userId]);
        foreach ($q->fetchAll() as $r)
            $files[] = ['path' => $uploadDir.$r['file_path'], 'name' => 'cert_'.basename($r['file_path'])];
    } catch (Exception $e) {}
}

$files = array_filter($files, fn($f) => file_exists($f['path']));

if (empty($files))
    redirectError('Нет файлов для скачивания.', SITE_URL.'/achievements.php');

$zipPath = sys_get_temp_dir() . '/arch_' . $name . '_' . time() . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true)
    redirectError('Ошибка создания архива.', SITE_URL.'/achievements.php');

foreach ($files as $f)
    $zip->addFile($f['path'], $f['name']);
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $name . '_documents.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
@unlink($zipPath);
exit;