<?php
// partials/init.php

if (defined('UMR_INIT')) {
    return;
}
define('UMR_INIT', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_PATH')) {
    $currentDir = __DIR__;
    $basePath   = dirname($currentDir);
    define('BASE_PATH', $basePath);
}

//Подключение файлов
require_once BASE_PATH . '/../config/db.php';
require_once BASE_PATH . '/../includes/auth.php';

requireLogin();

// Текущий пользователь
$user      = getCurrentUser();
$userId    = (int)($user['id']          ?? 0);
$userRole  = (string)($user['role']     ?? '');
$userName  = (string)($user['full_name'] ?? '');

// Инициалы
$isLoggedIn = isset($_SESSION['user_id']);
$nameParts  = explode(' ', trim($userName));
$initials   = implode('', array_map(
    fn($p) => mb_strtoupper(mb_substr($p, 0, 1)),
    array_slice($nameParts, 0, 2)
));

// Роли базовые
$isAdmin   = ($userRole === 'admin');
$isDirector = ($userRole === 'director');
$isTeacher  = ($userRole === 'teacher');

$isRolePccHead = (bool)(int)($user['is_pcc_head'] ?? 0);
$isRoleMethodist = (bool)(int)($user['is_methodist'] ?? 0);

// Права из roles 
if (!isset($_SESSION['_umr_perms']) || ($_SESSION['_umr_perms']['_role'] ?? '') !== $userRole) {
    try {
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT
                can_umr_teacher_assignments,
                can_umr_curricula,
                can_umr_work_programs,
                can_umr_register_journal,
                can_umr_load_summary
            FROM roles
            WHERE role_code = ?
            LIMIT 1
        ");
        $stmt->execute([$userRole]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $row = [];
    }

    $_SESSION['_umr_perms'] = [
        '_role'                         => $userRole,
        'can_umr_teacher_assignments'         => (bool)(int)($row['can_umr_teacher_assignments']      ?? 0),
        'can_umr_curricula'        => (bool)(int)($row['can_umr_curricula']     ?? 0),
        'can_umr_work_programs'    => (bool)(int)($row['can_umr_work_programs'] ?? 0),
        'can_umr_register_journal'         => (bool)(int)($row['can_umr_register_journal']      ?? 0),
        'can_umr_load_summary'     => (bool)(int)($row['can_umr_load_summary']  ?? 0),
    ];
}

$perms = $_SESSION['_umr_perms'];

// Флаги прав
$canTeacherAssignments    = $perms['can_umr_teacher_assignments'];
$canCurricula = $perms['can_umr_curricula'];
$canWorkPrograms = $perms['can_umr_work_programs'];
$canRegisterJournal = $perms['can_umr_register_journal'];
$canLoadSummary = $perms['can_umr_load_summary'];

// Должностные флаги
$isPccHead = $isTeacher && $isRolePccHead;
$isMethodist = $isTeacher && $isRoleMethodist;
$isPlainTeacher = $isTeacher && !$isPccHead && !$isMethodist;

if (!$isAdmin && !$isDirector && !$isTeacher) {
    $_SESSION['error'] = 'У вас нет доступа к модулю УМР';
    header('Location: /../requests/login.php');
    exit;
}

$pdo = $GLOBALS['pdo'];