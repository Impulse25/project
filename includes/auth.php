<?php
date_default_timezone_set('Asia/Almaty');
// includes/auth.php - Функции авторизации

// Запуск сессии только если она еще не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Получение данных текущего пользователя
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role'],
        'position' => $_SESSION['position']
    ];
}

// Проверка роли пользователя
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Проверка прав доступа (несколько ролей)
function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    return in_array($_SESSION['role'], $roles);
}

// Вход пользователя
function login($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Регенерация ID сессии для безопасности
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
<<<<<<< HEAD
            $_SESSION['position'] = $user['position'] ?? '';
            
            // Логирование успешного входа
            logLogin($pdo, $user['id'], $user['username'], $user['full_name'], $user['role'], 'local', true);
            
=======
            $_SESSION['position'] = $user['position'];
            
>>>>>>> ae83841d72d8ff3b9f96d54572e7259dd3d73581
            // Принудительное сохранение сессии
            session_write_close();
            session_start();
            
            return true;
        }
        
<<<<<<< HEAD
        // Логирование неудачной попытки
        logLogin($pdo, null, $username, null, null, 'local', false, 'Неверный логин или пароль');
        
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        logLogin($pdo, null, $username, null, null, 'local', false, $e->getMessage());
        return false;
    }
}

// Вход через LDAP
function loginLdap($pdo, $username, $password) {
    // Конфигурация LDAP
    $ldapServer = 'ldap://your-domain-controller';
    $ldapDomain = '@yourdomain.local';
    $baseDn = 'DC=yourdomain,DC=local';
    
    try {
        $ldapConn = @ldap_connect($ldapServer);
        if (!$ldapConn) {
            logLogin($pdo, null, $username, null, null, 'ldap', false, 'Не удалось подключиться к LDAP серверу');
            return false;
        }
        
        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
        
        // Попытка привязки к LDAP
        $ldapBind = @ldap_bind($ldapConn, $username . $ldapDomain, $password);
        
        if (!$ldapBind) {
            logLogin($pdo, null, $username, null, null, 'ldap', false, 'Неверные учетные данные LDAP');
            ldap_close($ldapConn);
            return false;
        }
        
        // Поиск пользователя в локальной БД
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR ldap_username = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Пользователь найден - авторизуем
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['position'] = $user['position'] ?? '';
            
            // Логирование успешного LDAP входа
            logLogin($pdo, $user['id'], $username, $user['full_name'], $user['role'], 'ldap', true);
            
            ldap_close($ldapConn);
            session_write_close();
            session_start();
            return true;
        } else {
            // Пользователь не найден в локальной БД
            logLogin($pdo, null, $username, null, null, 'ldap', false, 'Пользователь не найден в системе');
            ldap_close($ldapConn);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("LDAP Login error: " . $e->getMessage());
        logLogin($pdo, null, $username, null, null, 'ldap', false, $e->getMessage());
        return false;
    }
}

// Функция логирования входа
function logLogin($pdo, $userId, $username, $fullName, $role, $authType, $success, $errorMessage = null) {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $action = $success ? 'login' : 'login_failed';
        
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, username, full_name, role, action, ip_address, user_agent, auth_type, success, error_message, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $username,
            $fullName,
            $role,
            $action,
            $ipAddress,
            $userAgent,
            $authType,
            $success ? 1 : 0,
            $errorMessage
        ]);
    } catch (PDOException $e) {
        error_log("Login log error: " . $e->getMessage());
    }
}

// Логирование выхода
function logLogout($pdo) {
    if (isLoggedIn()) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO login_logs (user_id, username, full_name, role, action, ip_address, user_agent, auth_type, success, created_at) 
                VALUES (?, ?, ?, ?, 'logout', ?, ?, 'session', 1, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $_SESSION['username'],
                $_SESSION['full_name'],
                $_SESSION['role'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            error_log("Logout log error: " . $e->getMessage());
        }
=======
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
>>>>>>> ae83841d72d8ff3b9f96d54572e7259dd3d73581
    }
}

// Выход пользователя
<<<<<<< HEAD
function logout($pdo = null) {
    if ($pdo) {
        logLogout($pdo);
    }
=======
function logout() {
>>>>>>> ae83841d72d8ff3b9f96d54572e7259dd3d73581
    session_destroy();
    header('Location: index.php');
    exit();
}

// Редирект на страницу входа
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

// Редирект по роли
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: teacher_dashboard.php');
        exit();
    }
}

// Перенаправление на соответствующую панель по роли
function redirectToDashboard() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
    
    $role = $_SESSION['role'];
    
    switch ($role) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'director':
            header('Location: director_dashboard.php');
            break;
        case 'teacher':
            header('Location: teacher_dashboard.php');
            break;
        case 'technician':
            header('Location: technician_dashboard.php');
            break;
        default:
            // Для всех остальных ролей - на teacher_dashboard
            header('Location: teacher_dashboard.php');
            break;
    }
    exit();
}
?>
