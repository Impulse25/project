<?php
// admin_dashboard.php - Панель администратора с управлением ролями

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('admin');

$user = getCurrentUser();

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: admin_dashboard.php?tab=' . ($_GET['tab'] ?? 'dashboard'));
    exit();
}

// Обработка добавления кабинета
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_cabinet') {
    $cabinetNumber = $_POST['cabinet_number'];
    $description = $_POST['description'] ?? '';
    $departmentId = $_POST['department_id'] ?? null;
    
    // Если пустая строка - преобразуем в NULL
    if ($departmentId === '' || $departmentId === '0') {
        $departmentId = null;
    }
    
    $stmt = $pdo->prepare("INSERT INTO cabinets (cabinet_number, description, department_id) VALUES (?, ?, ?)");
    $stmt->execute([$cabinetNumber, $description, $departmentId]);
    
    header('Location: admin_dashboard.php?tab=cabinets&success=cabinet_added');
    exit();
}

// Обработка добавления отделения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_department') {
    $departmentName = $_POST['department_name'];
    $description = $_POST['description'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
    $stmt->execute([$departmentName, $description]);
    
    header('Location: admin_dashboard.php?tab=cabinets&success=department_added');
    exit();
}

// Обработка добавления роли
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_role') {
    $roleCode = $_POST['role_code'];
    $roleNameRu = $_POST['role_name_ru'];
    $roleNameKk = $_POST['role_name_kk'];
    $description = $_POST['description'] ?? '';
    $canCreateRequest = isset($_POST['can_create_request']) ? 1 : 0;
    $canApproveRequest = isset($_POST['can_approve_request']) ? 1 : 0;
    $canWorkOnRequest = isset($_POST['can_work_on_request']) ? 1 : 0;
    $canManageUsers = isset($_POST['can_manage_users']) ? 1 : 0;
    $canManageCabinets = isset($_POST['can_manage_cabinets']) ? 1 : 0;
    $canViewAllRequests = isset($_POST['can_view_all_requests']) ? 1 : 0;
    
    $stmt = $pdo->prepare("INSERT INTO roles (role_code, role_name_ru, role_name_kk, description, can_create_request, can_approve_request, can_work_on_request, can_manage_users, can_manage_cabinets, can_view_all_requests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$roleCode, $roleNameRu, $roleNameKk, $description, $canCreateRequest, $canApproveRequest, $canWorkOnRequest, $canManageUsers, $canManageCabinets, $canViewAllRequests]);
    
    header('Location: admin_dashboard.php?tab=roles&success=role_added');
    exit();
}

// Обработка удаления кабинета
if (isset($_GET['delete_cabinet'])) {
    $cabinetId = $_GET['delete_cabinet'];
    $stmt = $pdo->prepare("DELETE FROM cabinets WHERE id = ?");
    $stmt->execute([$cabinetId]);
    
    header('Location: admin_dashboard.php?tab=cabinets&success=cabinet_deleted');
    exit();
}

// Обработка удаления отделения
if (isset($_GET['delete_department'])) {
    $departmentId = $_GET['delete_department'];
    
    // Проверка, есть ли кабинеты в отделении
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cabinets WHERE department_id = ?");
    $stmt->execute([$departmentId]);
    $cabinetsCount = $stmt->fetchColumn();
    
    if ($cabinetsCount > 0) {
        header('Location: admin_dashboard.php?tab=cabinets&error=department_has_cabinets');
        exit();
    }
    
    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->execute([$departmentId]);
    
    header('Location: admin_dashboard.php?tab=cabinets&success=department_deleted');
    exit();
}

// Обработка удаления роли
if (isset($_GET['delete_role'])) {
    $roleId = $_GET['delete_role'];
    
    // Проверка, что роль не используется
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = (SELECT role_code FROM roles WHERE id = ?)");
    $stmt->execute([$roleId]);
    $usersCount = $stmt->fetchColumn();
    
    if ($usersCount > 0) {
        header('Location: admin_dashboard.php?tab=roles&error=role_in_use');
        exit();
    }
    
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    
    header('Location: admin_dashboard.php?tab=roles&success=role_deleted');
    exit();
}

// Получение текущей вкладки
$tab = $_GET['tab'] ?? 'dashboard';

// Получение статистики
$stmt = $pdo->query("SELECT COUNT(*) as total FROM requests");
$totalRequests = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM requests WHERE status = 'pending'");
$pendingRequests = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM requests WHERE status = 'in_progress'");
$inProgressRequests = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM requests WHERE status = 'completed'");
$completedRequests = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmt->fetch()['total'];

// Все заявки
$stmt = $pdo->query("SELECT r.*, u.full_name as creator_name FROM requests r JOIN users u ON r.created_by = u.id ORDER BY r.created_at DESC LIMIT 20");
$recentRequests = $stmt->fetchAll();

// Все пользователи
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$allUsers = $stmt->fetchAll();

// Все отделения
try {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC");
    $allDepartments = $stmt->fetchAll();
} catch (PDOException $e) {
    $allDepartments = [];
}

// Все кабинеты с информацией об отделении
try {
    $stmt = $pdo->query("
        SELECT c.*, d.department_name 
        FROM cabinets c 
        LEFT JOIN departments d ON c.department_id = d.id 
        ORDER BY d.department_name ASC, c.cabinet_number ASC
    ");
    $allCabinets = $stmt->fetchAll();
} catch (PDOException $e) {
    $stmt = $pdo->query("SELECT * FROM cabinets ORDER BY cabinet_number ASC");
    $allCabinets = $stmt->fetchAll();
}

// Все роли
$stmt = $pdo->query("SELECT r.*, COUNT(u.id) as users_count FROM roles r LEFT JOIN users u ON u.role = r.role_code GROUP BY r.id ORDER BY r.created_at ASC");
$allRoles = $stmt->fetchAll();

// Данные для вкладки "Логи"
// Статистика логов за последние 30 дней
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $totalLogins = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM user_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $uniqueUsers = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_logs WHERE action = 'failed_login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $failedLogins = $stmt->fetchColumn();
    
    // Последние 50 логов
    $stmt = $pdo->query("SELECT * FROM user_logs ORDER BY created_at DESC LIMIT 50");
    $recentLogs = $stmt->fetchAll();
    
    // Топ-10 активных пользователей
    $stmt = $pdo->query("
        SELECT 
            user_id,
            full_name,
            role,
            COUNT(*) as login_count,
            MAX(created_at) as last_login
        FROM user_logs
        WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY user_id, full_name, role
        ORDER BY login_count DESC
        LIMIT 10
    ");
    $topUsers = $stmt->fetchAll();
    
    // Статистика по ролям
    $stmt = $pdo->query("
        SELECT 
            role,
            COUNT(*) as login_count,
            COUNT(DISTINCT user_id) as unique_users
        FROM user_logs
        WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY role
        ORDER BY login_count DESC
    ");
    $roleStats = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Если таблицы логов ещё нет
    $totalLogins = 0;
    $uniqueUsers = 0;
    $failedLogins = 0;
    $recentLogs = [];
    $topUsers = [];
    $roleStats = [];
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('admin'); ?> - <?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .permission-checkbox {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            transition: all 0.2s;
        }
        .permission-checkbox:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        .permission-checkbox input:checked + label {
            font-weight: 600;
            color: #4f46e5;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-user-shield text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600"><?php echo t('admin'); ?>: <?php echo $user['full_name']; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex gap-2">
                    <a href="?tab=<?php echo $tab; ?>&lang=ru" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Рус</a>
                    <a href="?tab=<?php echo $tab; ?>&lang=kk" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Қаз</a>
                </div>
                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo t('exit'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto p-6">
        
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Панель администратора</h2>
        
        <!-- Статистика (только на главной странице) -->
        <?php if ($tab === 'dashboard'): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Всего заявок</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $totalRequests; ?></p>
                    </div>
                    <i class="fas fa-file-alt text-4xl text-indigo-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Ожидают одобрения</p>
                        <p class="text-3xl font-bold text-yellow-600"><?php echo $pendingRequests; ?></p>
                    </div>
                    <i class="fas fa-clock text-4xl text-yellow-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">В работе</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $inProgressRequests; ?></p>
                    </div>
                    <i class="fas fa-spinner text-4xl text-blue-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Завершено</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $completedRequests; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-4xl text-green-600"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Вкладки -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-4 overflow-x-auto">
                    <a href="?tab=dashboard" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'dashboard' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-home"></i>
                        Главная
                    </a>
                    <a href="?tab=requests" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-file-alt"></i>
                        Заявки
                    </a>
                    <a href="?tab=users" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-users"></i>
                        Пользователи (<?php echo $totalUsers; ?>)
                    </a>
                    <a href="?tab=roles" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'roles' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-user-tag"></i>
                        Роли (<?php echo count($allRoles); ?>)
                    </a>
                    <a href="?tab=cabinets" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'cabinets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-building"></i>
                        Отделения и кабинеты
                    </a>
                    <a href="?tab=logs" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'logs' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-chart-line"></i>
                        Логи
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Контент вкладки Dashboard -->
        <?php if ($tab === 'dashboard'): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Последние 20 заявок</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Кабинет</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">От</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recentRequests as $req): 
                                $statusColors = [
                                    'new' => 'bg-blue-100 text-blue-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'in_progress' => 'bg-purple-100 text-purple-800',
                                    'completed' => 'bg-gray-100 text-gray-800'
                                ];
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-900">#<?php echo $req['id']; ?></td>
                                    <td class="px-6 py-4 text-xs font-medium text-gray-600"><?php echo t($req['request_type']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo $req['cabinet']; ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo $req['creator_name']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColors[$req['status']]; ?>">
                                            <?php echo t($req['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?></td>
                                    <td class="px-6 py-4">
                                        <a href="view_request.php?id=<?php echo $req['id']; ?>" class="text-indigo-600 hover:text-indigo-700">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Контент вкладки Requests -->
        <?php if ($tab === 'requests'): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Кабинет</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">От</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recentRequests as $req): 
                                $statusColors = [
                                    'new' => 'bg-blue-100 text-blue-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'in_progress' => 'bg-purple-100 text-purple-800',
                                    'completed' => 'bg-gray-100 text-gray-800'
                                ];
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-900">#<?php echo $req['id']; ?></td>
                                    <td class="px-6 py-4 text-xs font-medium text-gray-600"><?php echo t($req['request_type']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo $req['cabinet']; ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo $req['creator_name']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColors[$req['status']]; ?>">
                                            <?php echo t($req['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?></td>
                                    <td class="px-6 py-4">
                                        <a href="view_request.php?id=<?php echo $req['id']; ?>" class="text-indigo-600 hover:text-indigo-700">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Контент вкладки Users -->
        <?php if ($tab === 'users'): ?>
            <div class="mb-4 flex gap-3">
                <a href="add_user.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition inline-flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    Добавить пользователя
                </a>
                <a href="import_users.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition inline-flex items-center gap-2">
                    <i class="fas fa-file-import"></i>
                    Импорт из CSV
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Логин</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ФИО</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Должность</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата создания</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($allUsers as $u): 
                                $roleColors = [
                                    'admin' => 'bg-purple-100 text-purple-800',
                                    'director' => 'bg-red-100 text-red-800',
                                    'teacher' => 'bg-blue-100 text-blue-800',
                                    'technician' => 'bg-green-100 text-green-800'
                                ];
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-900">#<?php echo $u['id']; ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo $u['username']; ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo $u['full_name']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $roleColors[$u['role']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo t($u['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo $u['position']; ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('d.m.Y', strtotime($u['created_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="text-indigo-600 hover:text-indigo-700 mr-3" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($u['id'] != $user['id']): ?>
                                            <a href="delete_user.php?id=<?php echo $u['id']; ?>" onclick="return confirm('Удалить пользователя?')" class="text-red-600 hover:text-red-700" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Контент вкладки Roles -->
        <?php if ($tab === 'roles'): ?>
            <div class="mb-4">
                <button onclick="openModal('addRoleModal')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition inline-flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    Создать роль
                </button>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php 
                    if ($_GET['success'] === 'role_added') {
                        echo 'Роль успешно создана!';
                    } elseif ($_GET['success'] === 'role_deleted') {
                        echo 'Роль успешно удалена!';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php 
                    if ($_GET['error'] === 'role_in_use') {
                        echo 'Невозможно удалить роль! Она используется пользователями.';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($allRoles as $role): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="bg-indigo-100 p-3 rounded-lg">
                                    <i class="fas fa-user-tag text-2xl text-indigo-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800"><?php echo $role['role_name_ru']; ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo $role['role_name_kk']; ?></p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <span class="font-medium">Код:</span> <?php echo $role['role_code']; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a href="edit_role.php?id=<?php echo $role['id']; ?>" class="text-indigo-600 hover:text-indigo-700 text-lg" title="Редактировать">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (!in_array($role['role_code'], ['admin', 'director', 'teacher', 'technician'])): ?>
                                    <a href="?tab=roles&delete_role=<?php echo $role['id']; ?>" onclick="return confirm('Удалить роль <?php echo $role['role_name_ru']; ?>?')" class="text-red-600 hover:text-red-700 text-lg" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($role['description']): ?>
                            <p class="text-sm text-gray-600 mb-4"><?php echo $role['description']; ?></p>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between mb-4 pb-4 border-b">
                            <span class="text-sm text-gray-600">Пользователей с этой ролью:</span>
                            <span class="font-bold text-indigo-600"><?php echo $role['users_count']; ?></span>
                        </div>
                        
                        <div class="space-y-2">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Права доступа:</h4>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <?php if ($role['can_create_request']): ?>
                                    <div class="flex items-center gap-1 text-green-600">
                                        <i class="fas fa-check"></i>
                                        <span>Создавать заявки</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($role['can_approve_request']): ?>
                                    <div class="flex items-center gap-1 text-green-600">
                                        <i class="fas fa-check"></i>
                                        <span>Одобрять заявки</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($role['can_work_on_request']): ?>
                                    <div class="flex items-center gap-1 text-green-600">
                                        <i class="fas fa-check"></i>
                                        <span>Работать над заявками</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($role['can_manage_users']): ?>
                                    <div class="flex items-center gap-1 text-green-600">
                                        <i class="fas fa-check"></i>
                                        <span>Управлять пользователями</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($role['can_manage_cabinets']): ?>
                                    <div class="flex items-center gap-1 text-green-600">
                                        <i class="fas fa-check"></i>
                                        <span>Управлять кабинетами</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($role['can_view_all_requests']): ?>
                                    <div class="flex items-center gap-1 text-green-600">
                                        <i class="fas fa-check"></i>
                                        <span>Видеть все заявки</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($allRoles)): ?>
                    <div class="col-span-2 bg-gray-50 rounded-lg p-12 text-center">
                        <i class="fas fa-user-tag text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600">Роли не созданы</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Контент вкладки Отделения и кабинеты -->
        <?php if ($tab === 'cabinets'): ?>
            <div class="mb-4 flex gap-3">
                <button onclick="openModal('addDepartmentModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition inline-flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    Добавить отделение
                </button>
                <button onclick="openModal('addCabinetModal')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition inline-flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    Добавить кабинет
                </button>
            </div>
            
            <?php if (isset($_GET['success']) && $tab === 'cabinets'): ?>
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php 
                    if ($_GET['success'] === 'cabinet_added') {
                        echo 'Кабинет успешно добавлен!';
                    } elseif ($_GET['success'] === 'cabinet_deleted') {
                        echo 'Кабинет успешно удалён!';
                    } elseif ($_GET['success'] === 'department_added') {
                        echo 'Отделение успешно добавлено!';
                    } elseif ($_GET['success'] === 'department_deleted') {
                        echo 'Отделение успешно удалено!';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error']) && $tab === 'cabinets'): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php 
                    if ($_GET['error'] === 'department_has_cabinets') {
                        echo 'Нельзя удалить отделение! В нём есть кабинеты.';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Структура: Отделения → Кабинеты -->
            <?php if (empty($allDepartments) && empty($allCabinets)): ?>
                <div class="bg-gray-50 rounded-lg p-12 text-center">
                    <i class="fas fa-building text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600 mb-2">Отделения и кабинеты не добавлены</p>
                    <p class="text-sm text-gray-500">Начните с создания отделения</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <!-- Отображение отделений с кабинетами -->
                    <?php foreach ($allDepartments as $dept): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <!-- Заголовок отделения -->
                            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3 text-white">
                                    <i class="fas fa-building text-2xl"></i>
                                    <div>
                                        <h3 class="text-lg font-bold"><?php echo $dept['department_name']; ?></h3>
                                        <?php if ($dept['description']): ?>
                                            <p class="text-sm opacity-90"><?php echo $dept['description']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="?tab=cabinets&delete_department=<?php echo $dept['id']; ?>" 
                                   onclick="return confirm('Удалить отделение <?php echo $dept['department_name']; ?>?')" 
                                   class="text-white hover:text-red-200 transition">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            
                            <!-- Кабинеты отделения -->
                            <div class="p-4">
                                <?php 
                                $deptCabinets = array_filter($allCabinets, function($cab) use ($dept) {
                                    return isset($cab['department_id']) && $cab['department_id'] == $dept['id'];
                                });
                                ?>
                                
                                <?php if (empty($deptCabinets)): ?>
                                    <p class="text-gray-500 text-center py-4">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        В этом отделении пока нет кабинетов
                                    </p>
                                <?php else: ?>
                                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                                        <?php foreach ($deptCabinets as $cab): ?>
                                            <div class="relative bg-gray-50 border-2 border-indigo-200 rounded-lg p-4 hover:border-indigo-400 hover:shadow-md transition text-center group">
                                                <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition">
                                                    <a href="?tab=cabinets&delete_cabinet=<?php echo $cab['id']; ?>" 
                                                       onclick="return confirm('Удалить кабинет <?php echo $cab['cabinet_number']; ?>?')" 
                                                       class="text-red-600 hover:text-red-700 text-xs">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                                <i class="fas fa-door-open text-3xl text-indigo-600 mb-2"></i>
                                                <div class="font-bold text-gray-800"><?php echo $cab['cabinet_number']; ?></div>
                                                <?php if ($cab['description']): ?>
                                                    <div class="text-xs text-gray-500 mt-1 truncate" title="<?php echo $cab['description']; ?>">
                                                        <?php echo $cab['description']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Кабинеты без отделения -->
                    <?php 
                    $cabinetsWithoutDept = array_filter($allCabinets, function($cab) {
                        return !isset($cab['department_id']) || $cab['department_id'] === null;
                    });
                    ?>
                    
                    <?php if (!empty($cabinetsWithoutDept)): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="bg-gray-600 p-4 flex items-center gap-3 text-white">
                                <i class="fas fa-question-circle text-2xl"></i>
                                <div>
                                    <h3 class="text-lg font-bold">Без отделения</h3>
                                    <p class="text-sm opacity-90">Кабинеты не привязанные к отделениям</p>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                                    <?php foreach ($cabinetsWithoutDept as $cab): ?>
                                        <div class="relative bg-gray-50 border-2 border-gray-300 rounded-lg p-4 hover:border-gray-400 hover:shadow-md transition text-center group">
                                            <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition">
                                                <a href="?tab=cabinets&delete_cabinet=<?php echo $cab['id']; ?>" 
                                                   onclick="return confirm('Удалить кабинет <?php echo $cab['cabinet_number']; ?>?')" 
                                                   class="text-red-600 hover:text-red-700 text-xs">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </div>
                                            <i class="fas fa-door-open text-3xl text-gray-600 mb-2"></i>
                                            <div class="font-bold text-gray-800"><?php echo $cab['cabinet_number']; ?></div>
                                            <?php if ($cab['description']): ?>
                                                <div class="text-xs text-gray-500 mt-1 truncate" title="<?php echo $cab['description']; ?>">
                                                    <?php echo $cab['description']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Контент вкладки Логи -->
        <?php if ($tab === 'logs'): ?>
            <!-- Статистика за 30 дней -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Всего входов</p>
                            <p class="text-3xl font-bold text-indigo-600"><?php echo $totalLogins; ?></p>
                            <p class="text-xs text-gray-500 mt-1">За последние 30 дней</p>
                        </div>
                        <i class="fas fa-sign-in-alt text-4xl text-indigo-600"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Уникальных пользователей</p>
                            <p class="text-3xl font-bold text-green-600"><?php echo $uniqueUsers; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Активных за месяц</p>
                        </div>
                        <i class="fas fa-users text-4xl text-green-600"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Неудачные попытки</p>
                            <p class="text-3xl font-bold text-red-600"><?php echo $failedLogins; ?></p>
                            <p class="text-xs text-gray-500 mt-1">За последние 30 дней</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-4xl text-red-600"></i>
                    </div>
                </div>
            </div>
            
            <!-- Топ пользователей и статистика по ролям -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Топ-10 активных -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-trophy mr-2"></i>
                        Топ-10 активных (30 дней)
                    </h3>
                    <?php if (!empty($topUsers)): ?>
                        <div class="space-y-3">
                            <?php foreach ($topUsers as $index => $tu): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center font-bold text-indigo-600">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?php echo $tu['full_name']; ?></p>
                                            <p class="text-xs text-gray-500"><?php echo t($tu['role']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-indigo-600"><?php echo $tu['login_count']; ?> входов</p>
                                        <p class="text-xs text-gray-500"><?php echo date('d.m.Y', strtotime($tu['last_login'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">Нет данных</p>
                    <?php endif; ?>
                </div>
                
                <!-- Статистика по ролям -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-user-tag mr-2"></i>
                        Активность по ролям (30 дней)
                    </h3>
                    <?php if (!empty($roleStats)): ?>
                        <div class="space-y-2">
                            <?php foreach ($roleStats as $rs): 
                                $roleColors = [
                                    'admin' => 'bg-purple-100 text-purple-800',
                                    'director' => 'bg-red-100 text-red-800',
                                    'teacher' => 'bg-blue-100 text-blue-800',
                                    'technician' => 'bg-green-100 text-green-800'
                                ];
                            ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $roleColors[$rs['role']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo t($rs['role']); ?>
                                    </span>
                                    <span class="text-sm text-gray-600">
                                        <?php echo $rs['login_count']; ?> входов / <?php echo $rs['unique_users']; ?> польз.
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">Нет данных</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Таблица логов -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-list mr-2"></i>
                        История входов (последние 50 записей)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Пользователь</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действие</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP-адрес</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата и время</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($recentLogs)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-2"></i>
                                        <p>Логи отсутствуют</p>
                                        <p class="text-xs mt-2">Убедитесь что выполнен SQL: create_logs_table.sql</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentLogs as $log): 
                                    $actionColors = [
                                        'login' => 'bg-green-100 text-green-800',
                                        'failed_login' => 'bg-red-100 text-red-800',
                                        'logout' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $actionIcons = [
                                        'login' => 'fa-sign-in-alt',
                                        'failed_login' => 'fa-times-circle',
                                        'logout' => 'fa-sign-out-alt'
                                    ];
                                    $actionTexts = [
                                        'login' => 'Вход',
                                        'failed_login' => 'Неудачный вход',
                                        'logout' => 'Выход'
                                    ];
                                    $roleColors = [
                                        'admin' => 'bg-purple-100 text-purple-800',
                                        'director' => 'bg-red-100 text-red-800',
                                        'teacher' => 'bg-blue-100 text-blue-800',
                                        'technician' => 'bg-green-100 text-green-800'
                                    ];
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-900">#<?php echo $log['id']; ?></td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo $log['full_name']; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $log['username']; ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $roleColors[$log['role']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo t($log['role']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $actionColors[$log['action']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <i class="fas <?php echo $actionIcons[$log['action']] ?? 'fa-circle'; ?> mr-1"></i>
                                                <?php echo $actionTexts[$log['action']] ?? $log['action']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo $log['ip_address']; ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Модальное окно добавления отделения -->
    <div id="addDepartmentModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-building mr-2"></i>
                    Добавить отделение
                </h3>
                <button onclick="closeModal('addDepartmentModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_department">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Название отделения <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="department_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Информационные технологии">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Описание (необязательно)
                    </label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Отделение информационных технологий"></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-plus mr-2"></i>
                        Добавить
                    </button>
                    <button type="button" onclick="closeModal('addDepartmentModal')" class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно добавления кабинета -->
    <div id="addCabinetModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-door-open mr-2"></i>
                    Добавить кабинет
                </h3>
                <button onclick="closeModal('addCabinetModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_cabinet">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Отделение
                    </label>
                    <select name="department_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">Без отделения</option>
                        <?php foreach ($allDepartments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo $dept['department_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Номер кабинета <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="cabinet_number" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="201">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Описание (необязательно)
                    </label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Компьютерный класс"></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-plus mr-2"></i>
                        Добавить
                    </button>
                    <button type="button" onclick="closeModal('addCabinetModal')" class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно создания роли -->
    <div id="addRoleModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-user-tag mr-2"></i>
                    Создать роль
                </h3>
                <button onclick="closeModal('addRoleModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_role">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Код роли (англ.) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="role_code" required pattern="[a-z_]+" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="methodist">
                    <p class="text-xs text-gray-500 mt-1">Только строчные латинские буквы и подчеркивание</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Название (рус) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="role_name_ru" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Методист">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Название (каз) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="role_name_kk" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Әдіскер">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Описание
                    </label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Описание роли и её обязанностей"></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        Права доступа:
                    </label>
                    <div class="space-y-2">
                        <label class="permission-checkbox cursor-pointer">
                            <input type="checkbox" name="can_create_request" class="mr-3 w-4 h-4 text-indigo-600">
                            <span class="text-sm text-gray-700">Создавать заявки</span>
                        </label>
                        
                        <label class="permission-checkbox cursor-pointer">
                            <input type="checkbox" name="can_approve_request" class="mr-3 w-4 h-4 text-indigo-600">
                            <span class="text-sm text-gray-700">Одобрять заявки</span>
                        </label>
                        
                        <label class="permission-checkbox cursor-pointer">
                            <input type="checkbox" name="can_work_on_request" class="mr-3 w-4 h-4 text-indigo-600">
                            <span class="text-sm text-gray-700">Работать над заявками</span>
                        </label>
                        
                        <label class="permission-checkbox cursor-pointer">
                            <input type="checkbox" name="can_manage_users" class="mr-3 w-4 h-4 text-indigo-600">
                            <span class="text-sm text-gray-700">Управлять пользователями</span>
                        </label>
                        
                        <label class="permission-checkbox cursor-pointer">
                            <input type="checkbox" name="can_manage_cabinets" class="mr-3 w-4 h-4 text-indigo-600">
                            <span class="text-sm text-gray-700">Управлять кабинетами</span>
                        </label>
                        
                        <label class="permission-checkbox cursor-pointer">
                            <input type="checkbox" name="can_view_all_requests" class="mr-3 w-4 h-4 text-indigo-600">
                            <span class="text-sm text-gray-700">Видеть все заявки</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                        <i class="fas fa-plus mr-2"></i>
                        Создать роль
                    </button>
                    <button type="button" onclick="closeModal('addRoleModal')" class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Закрытие модального окна при клике вне его
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
    
</body>
</html>