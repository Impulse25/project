<?php
date_default_timezone_set('Asia/Almaty');
// includes/auth.php - Функции авторизации с поддержкой LDAP и логирования

session_start();

require_once __DIR__ . '/../config/ldap.php';

// Проверка авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']);
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
        'position' => $_SESSION['position'],
        'auth_type' => $_SESSION['auth_type'] ?? 'local'
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

/**
 * Логирование действий пользователя
 * Универсальная функция - работает с любой структурой таблицы логов
 */
function logUserAction($pdo, $userId, $username, $fullName, $role, $action, $authType = 'local', $success = true, $errorMessage = null) {
    try {
        // Определяем название таблицы логов
        $possibleTables = ['user_logs', 'activity_logs', 'audit_logs', 'logs', 'user_activity', 'auth_logs'];
        $logTable = null;
        
        foreach ($possibleTables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt && $stmt->rowCount() > 0) {
                $logTable = $table;
                break;
            }
        }
        
        // Если таблица не найдена - создаём
        if (!$logTable) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    username VARCHAR(50),
                    full_name VARCHAR(255),
                    role VARCHAR(50),
                    action VARCHAR(50),
                    auth_type VARCHAR(10),
                    success BOOLEAN DEFAULT 1,
                    error_message TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_username (username),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $logTable = 'user_logs';
        }
        
        // Получаем IP адрес
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        // Получаем User Agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Проверяем какие поля есть в таблице
        $columns = $pdo->query("SHOW COLUMNS FROM $logTable")->fetchAll(PDO::FETCH_COLUMN);
        
        // Формируем SQL в зависимости от структуры таблицы
        $fields = ['user_id', 'username', 'action', 'ip_address', 'user_agent', 'created_at'];
        $values = [$userId, $username, $action, $ipAddress, $userAgent, date('Y-m-d H:i:s')];
        $placeholders = ['?', '?', '?', '?', '?', '?'];
        
        // Добавляем дополнительные поля если они есть
        if (in_array('full_name', $columns)) {
            $fields[] = 'full_name';
            $values[] = $fullName;
            $placeholders[] = '?';
        }
        
        if (in_array('role', $columns)) {
            $fields[] = 'role';
            $values[] = $role;
            $placeholders[] = '?';
        }
        
        if (in_array('auth_type', $columns)) {
            $fields[] = 'auth_type';
            $values[] = $authType;
            $placeholders[] = '?';
        }
        
        if (in_array('success', $columns)) {
            $fields[] = 'success';
            $values[] = $success ? 1 : 0;
            $placeholders[] = '?';
        }
        
        if (in_array('error_message', $columns) && $errorMessage) {
            $fields[] = 'error_message';
            $values[] = $errorMessage;
            $placeholders[] = '?';
        }
        
        // Выполняем INSERT
        $sql = "INSERT INTO $logTable (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Log error: " . $e->getMessage());
        return false;
    }
}

/**
 * ГИБРИДНАЯ АУТЕНТИФИКАЦИЯ
 */
function login($pdo, $username, $password) {
    $username = trim($username);
    
    // ШАГ 1: Попытка LDAP
    if (LDAP_ENABLED) {
        $ldapUser = ldapAuthenticate($username, $password);
        
        if ($ldapUser !== false) {
            return loginLdapUser($pdo, $ldapUser);
        }
    }
    
    // ШАГ 2: Локальная БД
    return loginLocalUser($pdo, $username, $password);
}

/**
 * Вход через LDAP
 */
function loginLdapUser($pdo, $ldapUser) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND auth_type = 'ldap'");
        $stmt->execute([$ldapUser['username']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, full_name, role, position, auth_type, password, created_at) 
                VALUES (?, ?, 'teacher', '', 'ldap', '', NOW())
            ");
            $stmt->execute([$ldapUser['username'], $ldapUser['full_name']]);
            
            $userId = $pdo->lastInsertId();
            $role = 'teacher';
            $position = '';
            
            error_log("LDAP: Создан пользователь {$ldapUser['username']}");
        } else {
            if ($user['full_name'] !== $ldapUser['full_name']) {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                $stmt->execute([$ldapUser['full_name'], $user['id']]);
            }
            
            $userId = $user['id'];
            $role = $user['role'];
            $position = $user['position'];
        }
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $ldapUser['username'];
        $_SESSION['full_name'] = $ldapUser['full_name'];
        $_SESSION['role'] = $role;
        $_SESSION['position'] = $position;
        $_SESSION['auth_type'] = 'ldap';
        
        // Логируем вход
        logUserAction($pdo, $userId, $ldapUser['username'], $ldapUser['full_name'], $role, 'login', 'ldap', true);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("LDAP Login DB error: " . $e->getMessage());
        logUserAction($pdo, null, $ldapUser['username'], $ldapUser['full_name'], '', 'login', 'ldap', false, $e->getMessage());
        return false;
    }
}

/**
 * Вход через локальную БД
 */
function loginLocalUser($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND auth_type = 'local'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['position'] = $user['position'];
            $_SESSION['auth_type'] = 'local';
            
            // Логируем успешный вход
            logUserAction($pdo, $user['id'], $user['username'], $user['full_name'], $user['role'], 'login', 'local', true);
            
            return true;
        }
        
        // Логируем неудачную попытку
        if ($user) {
            logUserAction($pdo, $user['id'], $username, $user['full_name'], $user['role'], 'failed_login', 'local', false, 'Неверный пароль');
        } else {
            logUserAction($pdo, null, $username, '', '', 'failed_login', 'local', false, 'Пользователь не найден');
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Local login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Создание локального администратора
 */
function createLocalAdmin($pdo, $username, $password, $fullName = 'Администратор') {
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, role, position, auth_type, created_at) 
            VALUES (?, ?, ?, 'admin', 'Системный администратор', 'local', NOW())
            ON DUPLICATE KEY UPDATE password = ?, full_name = ?
        ");
        
        $stmt->execute([$username, $hashedPassword, $fullName, $hashedPassword, $fullName]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Create local admin error: " . $e->getMessage());
        return false;
    }
}

// Выход пользователя
function logout($pdo = null) {
    // Если $pdo не передан, пытаемся получить глобальный
    if ($pdo === null) {
        global $pdo;
    }
    
    // Логируем выход ПЕРЕД уничтожением сессии
    if (isLoggedIn() && $pdo) {
        $user = getCurrentUser();
        
        try {
            logUserAction(
                $pdo, 
                $user['id'], 
                $user['username'], 
                $user['full_name'], 
                $user['role'], 
                'logout', 
                $user['auth_type'],
                true
            );
        } catch (Exception $e) {
            error_log("Logout logging error: " . $e->getMessage());
        }
    }
    
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
            header('Location: teacher_dashboard.php');
            break;
    }
    exit();
}
?>
