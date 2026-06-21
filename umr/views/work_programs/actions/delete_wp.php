<?php
//views/work_programs/actions/delete_wp.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!$canWorkPrograms && !$isPccHead && !$isMethodist) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}

$wpId = (int)($_POST['wp_id'] ?? 0);
if (!$wpId) {
    echo json_encode(['ok' => false, 'error' => 'Неверный ID']); exit;
}

$cur = $pdo->prepare("
    SELECT wp.id, wp.file_path, ta.teacher_id, ta.pcc_head_id
    FROM umr_work_programs wp
    JOIN umr_teacher_assignments ta ON ta.id = wp.assignment_id
    WHERE wp.id = ?
");
$cur->execute([$wpId]);
$wp = $cur->fetch();

if (!$wp) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit;
}

$isOwnerTeacher = ((int)$wp['teacher_id']  === $userId);
$isOwnerPcc     = ((int)$wp['pcc_head_id'] === $userId);
$hasFullAccess  = $isAdmin || $isPccHead || $isMethodist;

if (!$hasFullAccess && !$isOwnerTeacher && !$isOwnerPcc) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа к этому назначению']); exit;
}

$pdo->prepare("DELETE FROM umr_work_programs WHERE id = ?")->execute([$wpId]);

if ($wp['file_path']) {
    $filePath = BASE_PATH . '/' . $wp['file_path'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

echo json_encode(['ok' => true]);