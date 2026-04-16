<?php
// includes/auth.php — Функции авторизации
// Конфликты слияния git устранены. Версия: 2.0

date_default_timezone_set('Asia/Almaty');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ═══════════════════════════════════════════════════════════════
// ПРОВЕРКИ СЕССИИ
// ═══════════════════════════════════════════════════════════════

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
        'position'  => $_SESSION['position'] ?? '',
    ];
}

function hasRole(string $role): bool {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

function hasAnyRole(array $roles): bool {
    return isLoggedIn() && in_array($_SESSION['role'], $roles, true);
}

// ═══════════════════════════════════════════════════════════════
// ОСНОВНАЯ ФУНКЦИЯ ВХОДА
// Логика: если LDAP_ACTIVE=true → сначала пробуем домен,
//         при неудаче — падаем на локальную БД.
//         Если LDAP_ACTIVE=false → сразу локальная БД.
// ═══════════════════════════════════════════════════════════════

function login(PDO $pdo, string $username, string $password): bool {
    // 1. Попытка LDAP (только если включён в config/db.php)
    if (defined('LDAP_ACTIVE') && LDAP_ACTIVE) {
        // Подключаем конфиг LDAP если ещё не подключён
        if (!function_exists('ldapAuthenticate')) {
            require_once __DIR__ . '/../config/ldap.php';
        }
        $ldapUser = ldapAuthenticate($username, $password);
        if ($ldapUser !== false) {
            return _startSessionFromLdap($pdo, $username, $ldapUser);
        }
        // LDAP не прошёл — пробуем локально (fallback)
        error_log("AUTH: LDAP не прошёл для $username, пробуем локальную БД");
    }

    // 2. Локальная авторизация через таблицу users
    return _loginLocal($pdo, $username, $password);
}

// ═══════════════════════════════════════════════════════════════
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ (приватные — начинаются с _)
// ═══════════════════════════════════════════════════════════════

function _loginLocal(PDO $pdo, string $username, string $password): bool {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['position']  = $user['position'] ?? '';
            logLogin($pdo, $user['id'], $user['username'],
                     $user['full_name'], $user['role'], 'local', true);
            session_write_close();
            session_start();
            return true;
        }

        logLogin($pdo, null, $username, null, null, 'local', false, 'Неверный логин или пароль');
        return false;

    } catch (PDOException $e) {
        error_log("AUTH local error: " . $e->getMessage());
        return false;
    }
}

function _startSessionFromLdap(PDO $pdo, string $username, array $ldapUser): bool {
    try {
        // Ищем пользователя в локальной БД по username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([strtolower($username)]);
        $user = $stmt->fetch();

        if (!$user) {
            // Пользователь прошёл LDAP, но не заведён в системе
            logLogin($pdo, null, $username, null, null, 'ldap', false,
                     'Пользователь не найден в локальной БД');
            error_log("AUTH LDAP: пользователь $username прошёл домен, но не найден в users");
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        // Предпочитаем ФИО из домена, если оно заполнено
        $_SESSION['full_name'] = !empty($ldapUser['full_name'])
                                 ? $ldapUser['full_name']
                                 : $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['position']  = $user['position'] ?? '';

        logLogin($pdo, $user['id'], $username,
                 $_SESSION['full_name'], $user['role'], 'ldap', true);
        session_write_close();
        session_start();
        return true;

    } catch (PDOException $e) {
        error_log("AUTH LDAP session error: " . $e->getMessage());
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// ЛОГИРОВАНИЕ
// ═══════════════════════════════════════════════════════════════

function logLogin(
    PDO $pdo,
    ?int $userId,
    ?string $username,
    ?string $fullName,
    ?string $role,
    string $authType,
    bool $success,
    ?string $errorMessage = null
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_logs
                (user_id, username, full_name, role, action,
                 ip_address, user_agent, auth_type, success, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $username,
            $fullName,
            $role,
            $success ? 'login' : 'login_failed',
            $_SERVER['REMOTE_ADDR']     ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $authType,
            $success ? 1 : 0,
            $errorMessage,
        ]);
    } catch (PDOException $e) {
        error_log("AUTH log error: " . $e->getMessage());
    }
}

function logLogout(PDO $pdo): void {
    if (!isLoggedIn()) return;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_logs
                (user_id, username, full_name, role, action,
                 ip_address, user_agent, auth_type, success, created_at)
            VALUES (?, ?, ?, ?, 'logout', ?, ?, 'session', 1, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['username'],
            $_SESSION['full_name'],
            $_SESSION['role'],
            $_SERVER['REMOTE_ADDR']     ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    } catch (PDOException $e) {
        error_log("AUTH logout log error: " . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════════
// ВЫХОД И РЕДИРЕКТЫ
// ═══════════════════════════════════════════════════════════════

function logout(PDO $pdo = null): void {
    if ($pdo) {
        logLogout($pdo);
    }
    session_destroy();
    header('Location: index.php');
    exit();
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

function requireRole(string $role): void {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: teacher_dashboard.php');
        exit();
    }
}

function redirectToDashboard(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'director':
            header('Location: director_dashboard.php');
            break;
        case 'technician':
            header('Location: technician_dashboard.php');
            break;
        case 'teacher':
        default:
            header('Location: teacher_dashboard.php');
            break;
    }
    exit();
}
