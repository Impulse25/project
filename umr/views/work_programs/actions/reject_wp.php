<?php
// views/work_programs/actions/reject_wp.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!$canWorkPrograms && !$isPccHead && !$isMethodist) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}

$wpId   = (int)($_POST['wp_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));

if (!$wpId) {
    echo json_encode(['ok' => false, 'error' => 'Неверный ID']); exit;
}
if ($reason === '') {
    echo json_encode(['ok' => false, 'error' => 'Укажите причину отклонения']); exit;
}

$cur = $pdo->prepare("
    SELECT wp.id, wp.status, ta.pcc_head_id
    FROM umr_work_programs wp
    JOIN umr_teacher_assignments ta ON ta.id = wp.assignment_id
    WHERE wp.id = ?
");
$cur->execute([$wpId]);
$wp = $cur->fetch();

if (!$wp) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit;
}

$isOwnerPcc    = ((int)$wp['pcc_head_id'] === $userId);
$hasFullAccess = $isAdmin || $isPccHead || $isMethodist;

if (!$hasFullAccess && !$isOwnerPcc) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа к этому назначению']); exit;
}

if ($wp['status'] !== 'pending') {
    echo json_encode(['ok' => false, 'error' => 'Программа не находится на проверке']); exit;
}

$upd = $pdo->prepare("
    UPDATE umr_work_programs
    SET status = 'rejected', reject_reason = ?,
        approved_by = NULL, approved_at = NULL
    WHERE id = ?
");
$upd->execute([$reason, $wpId]);

echo json_encode(['ok' => true]);