<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','director');

$id       = (int)($_POST['id'] ?? 0);
$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$role     = in_array($_POST['role'] ?? '', ['admin','teacher','director','student']) ? $_POST['role'] : 'teacher';
$password = $_POST['password'] ?? '';

if (!$id || !$fullName) {
    header('Location: ' . SITE_URL . '/users.php'); exit;
}

$pdo = getPDO();

if ($email) {
    $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
    $chk->execute([$email, $id]);
    if ($chk->fetch()) {
        $_SESSION['flash_error'] = "Email «$email» уже используется.";
        header('Location: ' . SITE_URL . '/users.php'); exit;
    }
}

if ($password && strlen($password) >= 8) {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>10]);
    $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, password=? WHERE id=?")
        ->execute([$fullName, $email ?: null, $role, $hash, $id]);
} else {
    $pdo->prepare("UPDATE users SET full_name=?, email=?, role=? WHERE id=?")
        ->execute([$fullName, $email ?: null, $role, $id]);
}

$_SESSION['flash'] = "Пользователь «$fullName» обновлён.";
header('Location: ' . SITE_URL . '/users.php');