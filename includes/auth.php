<?php
date_default_timezone_set('Asia/Almaty');
// includes/auth.php - Функции авторизации

session_start();

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
// Вход пользователя
function login($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['position'] = $user['position'];
            
            // Логирование успешного входа
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO login_logs (user_id, username, full_name, role, action, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, 'login', ?, ?)
                ");
                $logStmt->execute([
                    $user['id'],
                    $user['username'],
                    $user['full_name'],
                    $user['role'],
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (PDOException $e) {
                error_log("Login log error: " . $e->getMessage());
            }
            
            return true;
        }
        
        // Логирование неудачной попытки входа
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO login_logs (user_id, username, full_name, role, action, ip_address, user_agent) 
                VALUES (0, ?, '', '', 'failed_login', ?, ?)
            ");
            $logStmt->execute([
                $username,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Failed login log error: " . $e->getMessage());
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

// Выход пользователя
function logout() {
    global $pdo;
    
    // Получаем данные пользователя перед выходом
    if (isset($_SESSION['user_id'])) {
        try {
            require_once __DIR__ . '/../config/db.php';
            
            $stmt = $pdo->prepare("
                INSERT INTO login_logs (user_id, username, full_name, role, action, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, 'logout', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $_SESSION['username'],
                $_SESSION['full_name'],
                $_SESSION['role'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Logout log error: " . $e->getMessage());
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

// Перенаправление на соответствующую панель по роли (СТАРАЯ ЛОГИКА)
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
