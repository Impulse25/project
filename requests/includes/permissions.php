<?php
// includes/permissions.php - Система проверки прав доступа на основе ролей

/**
 * Получить права доступа текущего пользователя
 * @param PDO $pdo - объект подключения к БД
 * @return array|null - массив с правами или null если роль не найдена
 */
function getUserPermissions($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    
    $userRole = $_SESSION['role'];
    
    // Кешируем права в сессии для производительности
    if (isset($_SESSION['permissions'])) {
        return $_SESSION['permissions'];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                can_create_request,
                can_approve_request,
                can_work_on_request,
                can_manage_users,
                can_manage_cabinets,
                can_view_all_requests
            FROM roles 
            WHERE role_code = ?
        ");
        $stmt->execute([$userRole]);
        $permissions = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($permissions) {
            // Кешируем в сессию
            $_SESSION['permissions'] = $permissions;
            return $permissions;
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Permissions error: " . $e->getMessage());
        return null;
    }
}

/**
 * Проверить, есть ли у пользователя конкретное право
 * @param PDO $pdo - объект подключения к БД
 * @param string $permission - название права (например, 'can_manage_users')
 * @return bool - true если право есть, false если нет
 */
function hasPermission($pdo, $permission) {
    $permissions = getUserPermissions($pdo);
    
    if (!$permissions) {
        return false;
    }
    
    return isset($permissions[$permission]) && $permissions[$permission] == 1;
}

/**
 * Проверить несколько прав одновременно (ИЛИ)
 * @param PDO $pdo - объект подключения к БД
 * @param array $permissionsList - массив названий прав
 * @return bool - true если хотя бы одно право есть
 */
function hasAnyPermission($pdo, $permissionsList) {
    foreach ($permissionsList as $permission) {
        if (hasPermission($pdo, $permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Проверить несколько прав одновременно (И)
 * @param PDO $pdo - объект подключения к БД
 * @param array $permissionsList - массив названий прав
 * @return bool - true если все права есть
 */
function hasAllPermissions($pdo, $permissionsList) {
    foreach ($permissionsList as $permission) {
        if (!hasPermission($pdo, $permission)) {
            return false;
        }
    }
    return true;
}

/**
 * Требовать наличие определённого права (редирект если нет доступа)
 * @param PDO $pdo - объект подключения к БД
 * @param string $permission - название права
 * @param string $redirectUrl - URL для редиректа (по умолчанию на дашборд)
 */
function requirePermission($pdo, $permission, $redirectUrl = null) {
    if (!hasPermission($pdo, $permission)) {
        if ($redirectUrl === null) {
            // Редирект на соответствующий дашборд по роли
            redirectToDashboard();
        } else {
            header("Location: $redirectUrl");
            exit();
        }
    }
}

/**
 * Сбросить кеш прав доступа (полезно после изменения ролей)
 */
function clearPermissionsCache() {
    if (isset($_SESSION['permissions'])) {
        unset($_SESSION['permissions']);
    }
}

/**
 * Получить все права текущей роли в читаемом виде
 * @param PDO $pdo - объект подключения к БД
 * @return array - массив с описаниями прав
 */
function getPermissionsList($pdo) {
    $permissions = getUserPermissions($pdo);
    
    if (!$permissions) {
        return [];
    }
    
    $list = [];
    
    $permissionNames = [
        'can_create_request' => 'Создавать заявки',
        'can_approve_request' => 'Одобрять заявки',
        'can_work_on_request' => 'Работать над заявками',
        'can_manage_users' => 'Управлять пользователями',
        'can_manage_cabinets' => 'Управлять кабинетами',
        'can_view_all_requests' => 'Видеть все заявки'
    ];
    
    foreach ($permissions as $key => $value) {
        if ($value == 1 && isset($permissionNames[$key])) {
            $list[] = $permissionNames[$key];
        }
    }
    
    return $list;
}
?>
