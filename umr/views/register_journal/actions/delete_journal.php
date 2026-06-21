<?php
// views/register_journal/actions/delete_journal.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!$canRegisterJournal && !$isPccHead && !$isMethodist) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}


if (!$isAdmin && !$isPccHead && !$isMethodist) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа — только ПЦК, методист или администратор']); exit;
}

require_once BASE_PATH . '/models/baseModel.php';
require_once BASE_PATH . '/models/umr_register_journal.php';
$moduleRegisterJournal = new umr_register_journal($pdo);

$journalId = (int)($_POST['journal_id'] ?? 0);
if (!$journalId) {
    echo json_encode(['ok' => false, 'error' => 'Неверный ID']); exit;
}

$jr = $moduleRegisterJournal->getJournalEntryForDeletion($journalId);

if (!$jr) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit;
}

if (!$isAdmin && !$isMethodist && (int)$jr['pcc_head_id'] !== $userId) {
    echo json_encode(['ok' => false, 'error' => 'Нельзя удалить запись другого ПЦК']); exit;
}

$moduleRegisterJournal->deleteJournalEntry($journalId);

echo json_encode(['ok' => true]);