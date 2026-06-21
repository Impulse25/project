<?php
// views/register_journal/actions/register.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!$canRegisterJournal && !$isPccHead && !$isMethodist) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}

require_once BASE_PATH . '/models/baseModel.php';
require_once BASE_PATH . '/models/umr_register_journal.php';
$moduleRegisterJournal = new umr_register_journal($pdo);

$wpId         = (int)($_POST['wp_id']          ?? 0);
$registeredAt = trim((string)($_POST['registered_at'] ?? ''));

if (!$wpId) {
    echo json_encode(['ok' => false, 'error' => 'Неверный ID']); exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $registeredAt)) {
    echo json_encode(['ok' => false, 'error' => 'Неверный формат даты']); exit;
}

// Проверяем РП
$wp = $moduleRegisterJournal->getWorkProgramForRegistration($wpId);

if (!$wp) {
    echo json_encode(['ok' => false, 'error' => 'РП не найдена']); exit;
}
if ($wp['status'] !== 'approved') {
    echo json_encode(['ok' => false, 'error' => 'Можно регистрировать только утверждённые РП']); exit;
}

$isOwnerTeacher = ((int)$wp['teacher_id'] === $userId);
if (!$isAdmin && !$isMethodist && !$isPccHead && !$isOwnerTeacher) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}

// Проверяем, не зарегистрирована ли уже
if ($moduleRegisterJournal->isAlreadyRegistered($wpId)) {
    echo json_encode(['ok' => false, 'error' => 'РП уже зарегистрирована в журнале']); exit;
}

$journalId = $moduleRegisterJournal->registerWorkProgram($wpId, $registeredAt, $userId);

echo json_encode(['ok' => true, 'journal_id' => $journalId]);