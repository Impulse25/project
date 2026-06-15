<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
       || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
       || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart');

if (!isLoggedIn()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'Требуется авторизация']); exit;
    }
    header('Location: ' . SITE_URL . '/index.php'); exit;
}

$currentUser = currentUser();
if (!in_array($currentUser['role'], ['admin','director'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'Недостаточно прав']); exit;
    }
    header('Location: ' . SITE_URL . '/dashboard.php'); exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role     = in_array($_POST['role'] ?? '', ['admin','teacher','director','student']) ? $_POST['role'] : 'teacher';

$pdo = getPDO();

if (!$fullName || strlen($password) < 8) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'Укажите ФИО и пароль (минимум 8 символов).']); exit;
    }
    $_SESSION['flash_error'] = "Заполните ФИО и пароль.";
    header('Location: ' . SITE_URL . '/users.php'); exit;
}

$slug     = mb_strtolower(str_replace(' ', '.', $fullName));
$slug     = preg_replace('/[^a-z0-9.]/ui', '', $slug);
$username = substr($slug, 0, 30) . '_' . substr((string)time(), -6);

if ($email) {
    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $chk->execute([$email]);
    if ($chk->fetch()) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>"Email «$email» уже используется."]); exit;
        }
        $_SESSION['flash_error'] = "Email уже используется.";
        header('Location: ' . SITE_URL . '/users.php'); exit;
    }
}

$maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM users")->fetchColumn();
$newId = $maxId + 1;

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>10]);

$pdo->prepare(
    "INSERT INTO users (id, username, full_name, email, password, role, auth_type)
     VALUES (?, ?, ?, ?, ?, ?, 'local')"
)->execute([$newId, $username, $fullName, $email ?: null, $hash, $role]);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>true, 'id'=>$newId, 'full_name'=>$fullName]);
    exit;
}

$_SESSION['flash'] = "Пользователь «$fullName» создан.";
header('Location: ' . SITE_URL . '/users.php');