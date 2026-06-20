<?php
//teacher_assignments\actions\assign.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!$canViewTeacherAssignments && !$isPccHead) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}

$moduleId   = (int)($_POST['module_id']    ?? 0);
$groupId    = (int)($_POST['group_id']     ?? 0);
$semesterNum = (int)($_POST['semester_num'] ?? 0);
$teacherId  = (int)($_POST['teacher_id']   ?? 0);
$teacherId  = (int)($_POST['teacher_id']   ?? 0);

if (!$moduleId || !$groupId || !$semesterNum || !$teacherId) {
    echo json_encode(['ok' => false, 'error' => 'Неверные параметры']); exit;
}

// проверка что такого назначения ещё нет тот же модуль + группа + семестр + учитель
$exists = $pdo->prepare("
    SELECT id FROM umr_teacher_assignments
    WHERE module_id = ? AND group_id = ? AND semester_num = ?
");

$exists->execute([$moduleId, $groupId, $semesterNum]);
if ($exists->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Преподаватель уже назначен']); exit;
}


// Если запрос от admin и передан pcc_head_id
$pccHeadId = $userId;
if ($isAdmin && !empty($_POST['pcc_head_id'])) {
    $overrideId = (int)$_POST['pcc_head_id'];
    // Проверяем что это действительно председатель ПЦК
    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_pcc_head = 1");
    $chk->execute([$overrideId]);
    if ($chk->fetch()) {
        $pccHeadId = $overrideId;
    }
}

$stmt = $pdo->prepare("
    INSERT INTO umr_teacher_assignments
        (module_id, group_id, semester_num, teacher_id, pcc_head_id, created_by)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$moduleId, $groupId, $semesterNum, $teacherId, $pccHeadId, $pccHeadId]);
$newId = (int)$pdo->lastInsertId();

// Возвращаем имя преподавателя для вставки
$teacher = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$teacher->execute([$teacherId]);
$teacher = $teacher->fetch();


echo json_encode([
    'ok'           => true,
    'assignment_id' => $newId,
    'teacher_name' => $teacher['full_name'] ?? '',
    'pcc_name' => $userName ?? 'вы',
]);