<?php
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
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

// Выход пользователя
function logout() {
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
