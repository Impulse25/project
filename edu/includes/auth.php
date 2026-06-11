<?php
/**
 * includes/auth.php
 * Инициализация сессии и данных текущего пользователя.
 * Подключать в самом начале каждой страницы (до любого вывода).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $loginPath = '../requests/login.php';
    header('Location: ' . $loginPath);
    exit;
}

function edu_normalize_role($role): string
{
    $role = trim((string)$role);
    $role = function_exists('mb_strtolower') ? mb_strtolower($role, 'UTF-8') : strtolower($role);
    $role = str_replace([' ', '-'], '_', $role);

    $map = [
        // В users.role в старой базе могут лежать как коды, так и id из таблицы roles.
        '1' => 'admin',
        '2' => 'director',
        '3' => 'teacher',
        '4' => 'technician',
        'admin' => 'admin',
        'administrator' => 'admin',
        'админ' => 'admin',
        'администратор' => 'admin',
        'director' => 'director',
        'директор' => 'director',
        'teacher' => 'teacher',
        'prepod' => 'teacher',
        'препод' => 'teacher',
        'преподаватель' => 'teacher',
        'технолог' => 'teacher',
    ];

    return $map[$role] ?? $role;
}

function edu_current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function edu_current_role(): string
{
    return edu_normalize_role($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? '');
}

function edu_is_admin(): bool
{
    return edu_current_role() === 'admin';
}

function edu_is_director(): bool
{
    return edu_current_role() === 'director';
}

function edu_is_teacher(): bool
{
    return edu_current_role() === 'teacher';
}

function edu_can_use_edu_module(): bool
{
    return in_array(edu_current_role(), ['admin', 'director', 'teacher'], true);
}

function edu_dashboard_url(?string $role = null): string
{
    $role = edu_normalize_role($role ?? edu_current_role());

    return match ($role) {
        'admin' => '../requests/admin_dashboard.php',
        'director' => '../requests/director_dashboard.php',
        'teacher' => '../requests/teacher_dashboard.php',
        default => '../requests/teacher_dashboard.php',
    };
}

/**
 * Группы, доступные пользователю в учебном модуле.
 * admin/director видят все группы.
 * teacher видит группы, где он куратор, а также группы ведомостей, где он указан преподавателем.
 */
function edu_accessible_group_ids(PDO $pdo, ?int $userId = null, ?string $role = null): array
{
    $userId = $userId ?? edu_current_user_id();
    $role = edu_normalize_role($role ?? edu_current_role());

    if (in_array($role, ['admin', 'director'], true)) {
        return array_map('intval', $pdo->query("SELECT id FROM edu_groups")->fetchAll(PDO::FETCH_COLUMN));
    }

    if ($role !== 'teacher' || $userId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT g.id
        FROM edu_groups g
        LEFT JOIN edu_grade_sheets gs ON gs.group_id = g.id
        WHERE g.curator_id = :uid_curator OR gs.teacher_id = :uid_teacher
        GROUP BY g.id, g.name
        ORDER BY g.name
    ");
    $stmt->execute([
        ':uid_curator' => $userId,
        ':uid_teacher' => $userId,
    ]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function edu_in_int_list(array $ids): string
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    return $ids ? implode(',', $ids) : '0';
}

function edu_user_can_access_group(PDO $pdo, int $groupId, ?int $userId = null, ?string $role = null): bool
{
    $role = edu_normalize_role($role ?? edu_current_role());
    if (in_array($role, ['admin', 'director'], true)) return true;
    if ($role !== 'teacher') return false;
    return in_array($groupId, edu_accessible_group_ids($pdo, $userId, $role), true);
}


/**
 * Список отдельных прав учебного модуля, которые настраиваются в /requests/edit_role.php.
 */
function edu_permission_keys(): array
{
    return [
        'can_edu_view_grades',
        'can_edu_grades',
        'can_edu_generate_sheets',
        'can_edu_export_students',
        'can_edu_edit_students',
        'can_edu_student_card',
        'can_edu_diploma_book',
    ];
}

/**
 * Поведение по умолчанию, если миграция с колонками прав ещё не применена.
 * admin принудительно сохраняет полный доступ, чтобы не заблокировать админку.
 */
function edu_default_permissions_for_role(?string $role = null): array
{
    $role = edu_normalize_role($role ?? edu_current_role());
    $defaults = array_fill_keys(edu_permission_keys(), false);

    if ($role === 'admin' || $role === 'teacher') {
        return array_fill_keys(edu_permission_keys(), true);
    }

    if ($role === 'director') {
        $defaults['can_edu_view_grades'] = true;
        $defaults['can_edu_grades'] = false;
        $defaults['can_edu_generate_sheets'] = true;
        $defaults['can_edu_export_students'] = true;
        $defaults['can_edu_student_card'] = true;
        $defaults['can_edu_diploma_book'] = true;
    }

    return $defaults;
}

function edu_role_permissions(PDO $pdo, ?string $role = null): array
{
    static $cache = [];

    $role = edu_normalize_role($role ?? edu_current_role());
    if (isset($cache[$role])) {
        return $cache[$role];
    }

    $permissions = edu_default_permissions_for_role($role);

    // Администратор не должен случайно потерять доступ к учебному модулю.
    if ($role === 'admin') {
        return $cache[$role] = $permissions;
    }

    try {
        $columns = array_map(
            static fn($r) => $r['Field'],
            $pdo->query('SHOW COLUMNS FROM roles')->fetchAll(PDO::FETCH_ASSOC)
        );
        $availableKeys = array_values(array_intersect(edu_permission_keys(), $columns));
        if (!$availableKeys) {
            return $cache[$role] = $permissions;
        }

        $select = implode(', ', array_map(static fn($c) => '`' . str_replace('`', '``', $c) . '`', $availableKeys));
        $stmt = $pdo->prepare("SELECT $select FROM roles WHERE role_code = ? LIMIT 1");
        $stmt->execute([$role]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $cache[$role] = $permissions;
        }

        foreach ($availableKeys as $key) {
            $permissions[$key] = ((int)($row[$key] ?? 0) === 1);
        }

        // Обратная совместимость: если новая колонка просмотра ещё не добавлена,
        // роль с правом выставления оценок должна хотя бы открывать страницу оценок.
        if (!in_array('can_edu_view_grades', $availableKeys, true) && !empty($permissions['can_edu_grades'])) {
            $permissions['can_edu_view_grades'] = true;
        }
    } catch (Throwable $e) {
        return $cache[$role] = $permissions;
    }

    return $cache[$role] = $permissions;
}

function edu_can(PDO $pdo, string $permission, ?string $role = null): bool
{
    if (!in_array($permission, edu_permission_keys(), true)) {
        return false;
    }

    $role = edu_normalize_role($role ?? edu_current_role());
    if ($role === 'admin') {
        return true;
    }

    $permissions = edu_role_permissions($pdo, $role);
    return !empty($permissions[$permission]);
}

function edu_require_permission(PDO $pdo, string $permission, string $redirectUrl = 'index.php'): void
{
    if (!edu_can($pdo, $permission)) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Если логин-код не положил role в сессию, берём роль из users по user_id.
if (empty($_SESSION['role']) && empty($_SESSION['user_role']) && empty($_SESSION['role_code'])) {
    $dbPath = __DIR__ . '/../../config/db.php';
    if (is_file($dbPath)) {
        require_once $dbPath;
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                $roleStmt->execute([edu_current_user_id()]);
                $dbRole = $roleStmt->fetchColumn();
                if ($dbRole !== false && $dbRole !== null && $dbRole !== '') {
                    $_SESSION['role'] = edu_normalize_role($dbRole);
                }
            } catch (Throwable $e) {
                // Роль останется пустой, доступ к учебным данным будет закрыт на страницах модуля.
            }
        }
    }
} else {
    $_SESSION['role'] = edu_current_role();
}

$userRole   = edu_current_role();
$userName   = $_SESSION['full_name'] ?? '';
$isAdmin    = in_array($userRole, ['admin', 'director'], true);
$isLoggedIn = isset($_SESSION['user_id']);

$nameParts = explode(' ', trim($userName));
$initials  = implode('', array_map(
    fn($p) => mb_strtoupper(mb_substr($p, 0, 1)),
    array_slice($nameParts, 0, 2)
));
