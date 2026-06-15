<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . SITE_URL . '/dashboard.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
        'email'     => $_SESSION['email'] ?? '',
    ];
}

function login(string $login, string $password): bool {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([trim($login), trim($login)]);
    $user = $stmt->fetch();

    if ($user && $user['password'] && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['email']     = $user['email'] ?? '';
        return true;
    }
    return false;
}

function logout(): void {
    session_destroy();
    $parts     = explode('/', rtrim(SITE_URL, '/'));
    array_pop($parts);
    $parentUrl = implode('/', $parts) . '/';
    header('Location: ' . $parentUrl);
    exit;
}