<?php
//teacher_assignments\actions\unassign.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!$canTeacherAssignments && !$isPccHead) {
  http_response_code(403);
  echo "Доступ запрещён. У вас нет прав для просмотра этого раздела.";
  exit;
}

$assignmentId = (int)($_POST['assignment_id'] ?? 0);
if (!$assignmentId) {
    echo json_encode(['ok' => false, 'error' => 'Неверный ID']); exit;
}

// Проверяем, что запись существует
$row = $pdo->prepare("SELECT pcc_head_id FROM umr_teacher_assignments WHERE id = ?");
$row->execute([$assignmentId]);
$row = $row->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit;
}

$isOwner   = ((int)$row['pcc_head_id'] === $userId);

if (!$isAdmin && !$isOwner) {
    echo json_encode(['ok' => false, 'error' => 'Нельзя удалить чужое назначение']); exit;
}

$pdo->prepare("DELETE FROM umr_teacher_assignments WHERE id = ?")->execute([$assignmentId]);

echo json_encode(['ok' => true]);