<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','teacher','director');

$pdo      = getPDO();
$eventId  = (int)($_POST['event_id'] ?? 0);
$ptype    = $_POST['participant_type'] ?? 'student';

if (!$eventId) {
    $_SESSION['flash_error'] = "Не выбрано мероприятие.";
    header('Location: ' . SITE_URL . '/events.php'); exit;
}

// ── СТУДЕНТ из edu_students ───────────────────────────────────
if ($ptype === 'student') {

    $eduStudentId = (int)($_POST['edu_student_id'] ?? 0);
    $roleEvent    = trim($_POST['role_event'] ?? 'Участник') ?: 'Участник';

    if (!$eduStudentId) {
        $_SESSION['flash_error'] = "Не выбран студент.";
        header('Location: ' . SITE_URL . '/events.php'); exit;
    }

    // Добавляем колонку если не существует
    try {
        $col = (bool)$pdo->query("SHOW COLUMNS FROM event_participants LIKE 'edu_student_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE event_participants ADD COLUMN edu_student_id INT UNSIGNED DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // Проверка дубликата
    try {
        $chk = $pdo->prepare("SELECT id FROM event_participants WHERE event_id=? AND edu_student_id=?");
        $chk->execute([$eventId, $eduStudentId]);
        if ($chk->fetch()) {
            $_SESSION['flash_error'] = "Этот студент уже является участником мероприятия.";
            header('Location: ' . SITE_URL . '/events.php'); exit;
        }
    } catch (Exception $e) {}

    $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM event_participants")->fetchColumn();

    try {
        $pdo->prepare(
            "INSERT INTO event_participants (id, event_id, edu_student_id, user_id, role_event)
             VALUES (?, ?, ?, 0, ?)"
        )->execute([$maxId + 1, $eventId, $eduStudentId, $roleEvent]);
    } catch (Exception $e) {
        $pdo->prepare(
            "INSERT INTO event_participants (id, event_id, user_id, role_event)
             VALUES (?, ?, 0, ?)"
        )->execute([$maxId + 1, $eventId, $roleEvent]);
    }

// ── ПРЕПОДАВАТЕЛЬ из users ────────────────────────────────────
} else {

    if (!in_array(currentUser()['role'], ['admin','director'])) {
        $_SESSION['flash_error'] = "Нет прав добавлять преподавателей.";
        header('Location: ' . SITE_URL . '/events.php'); exit;
    }

    $userId    = (int)($_POST['user_id'] ?? 0);
    $roleEvent = trim($_POST['role_event_teacher'] ?? 'Участник') ?: 'Участник';

    if (!$userId) {
        $_SESSION['flash_error'] = "Не выбран пользователь.";
        header('Location: ' . SITE_URL . '/events.php'); exit;
    }

    $chk = $pdo->prepare("SELECT id FROM event_participants WHERE event_id=? AND user_id=?");
    $chk->execute([$eventId, $userId]);
    if ($chk->fetch()) {
        $_SESSION['flash_error'] = "Этот пользователь уже является участником.";
        header('Location: ' . SITE_URL . '/events.php'); exit;
    }

    $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM event_participants")->fetchColumn();
    $pdo->prepare(
        "INSERT INTO event_participants (id, event_id, user_id, role_event)
         VALUES (?, ?, ?, ?)"
    )->execute([$maxId + 1, $eventId, $userId, $roleEvent]);

    recalcRating($userId);
}

$_SESSION['flash'] = "Участник добавлен.";
header('Location: ' . SITE_URL . '/events.php');