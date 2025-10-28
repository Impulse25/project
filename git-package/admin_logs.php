<?php
// admin_logs.php - Логи и статистика активности (с поддержкой LDAP)

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('admin');

$user = getCurrentUser();

// Параметры фильтрации
$filterUser = $_GET['user_id'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterAuthType = $_GET['auth_type'] ?? ''; // НОВОЕ: фильтр по типу авторизации
$filterMonth = $_GET['month'] ?? date('Y-m');

// Получение логов с фильтрацией
$whereConditions = [];
$params = [];

if ($filterUser) {
    $whereConditions[] = "user_id = ?";
    $params[] = $filterUser;
}

if ($filterAction) {
    $whereConditions[] = "action = ?";
    $params[] = $filterAction;
}

if ($filterAuthType) {
    $whereConditions[] = "auth_type = ?";
    $params[] = $filterAuthType;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

$stmt = $pdo->prepare("
    SELECT * FROM user_logs 
    $whereClause
    ORDER BY created_at DESC 
    LIMIT 100
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Статистика по типам авторизации (последние 30 дней)
$stmt = $pdo->query("
    SELECT 
        auth_type,
        COUNT(*) as login_count,
        COUNT(DISTINCT user_id) as unique_users
    FROM user_logs
    WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY auth_type
    ORDER BY login_count DESC
");
$authTypeStats = $stmt->fetchAll();

// Остальные статистики...
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as login_count,
        COUNT(DISTINCT user_id) as unique_users
    FROM user_logs
    WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$monthlyStats = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT 
        l.user_id,
        l.full_name,
        l.role,
        l.auth_type,
        COUNT(*) as login_count,
        MAX(l.created_at) as last_login
    FROM user_logs l
    WHERE l.action = 'login' AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY l.user_id, l.full_name, l.role, l.auth_type
    ORDER BY login_count DESC
    LIMIT 10
");
$topUsers = $stmt->fetchAll();

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

$stmt = $pdo->query("SELECT id, full_name, role FROM users ORDER BY full_name ASC");
$allUsers = $stmt->fetchAll();

list($year, $month) = explode('-', $filterMonth);
$stmt = $pdo->prepare("
    SELECT 
        user_id,
        role,
        SUM(login_count) as total_logins,
        SUM(requests_created) as total_requests_created,
        SUM(requests_completed) as total_requests_completed
    FROM activity_stats
    WHERE year = ? AND month = ?
    GROUP BY user_id, role
");
$stmt->execute([$year, $month]);
$monthActivity = $stmt->fetchAll();

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Логи и статистика - Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .stat-card { transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-chart-line text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Логи и статистика</h1>
                    <p class="text-sm text-gray-600"><?php echo $user['full_name']; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-arrow-left"></i>
                    Назад в админку
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto p-6">
        
        <!-- Статистика за последние 30 дней -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Всего входов</p>
                        <p class="text-3xl font-bold text-indigo-600">
                            <?php 
                            $stmt = $pdo->query("SELECT COUNT(*) FROM user_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                            echo $stmt->fetchColumn();
                            ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">За последние 30 дней</p>
                    </div>
                    <i class="fas fa-sign-in-alt text-4xl text-indigo-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Через LDAP</p>
                        <p class="text-3xl font-bold text-blue-600">
                            <?php 
                            $stmt = $pdo->query("SELECT COUNT(*) FROM user_logs WHERE action = 'login' AND auth_type = 'ldap' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                            echo $stmt->fetchColumn();
                            ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Доменные входы</p>
                    </div>
                    <i class="fas fa-network-wired text-4xl text-blue-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Локальные входы</p>
                        <p class="text-3xl font-bold text-green-600">
                            <?php 
                            $stmt = $pdo->query("SELECT COUNT(*) FROM user_logs WHERE action = 'login' AND auth_type = 'local' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                            echo $stmt->fetchColumn();
                            ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Через БД</p>
                    </div>
                    <i class="fas fa-database text-4xl text-green-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Неудачные попытки</p>
                        <p class="text-3xl font-bold text-red-600">
                            <?php 
                            $stmt = $pdo->query("SELECT COUNT(*) FROM user_logs WHERE action = 'failed_login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                            echo $stmt->fetchColumn();
                            ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">За месяц</p>
                    </div>
                    <i class="fas fa-times-circle text-4xl text-red-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Статистика по типам авторизации -->
        <?php if (!empty($authTypeStats)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-pie mr-2"></i>
                Статистика по типам авторизации (30 дней)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-<?php echo count($authTypeStats); ?> gap-4">
                <?php foreach ($authTypeStats as $stat): ?>
                <div class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-600">
                            <?php echo $stat['auth_type'] === 'ldap' ? '🌐 Домен (LDAP)' : '💾 Локальная БД'; ?>
                        </span>
                        <span class="text-2xl font-bold text-indigo-600"><?php echo $stat['login_count']; ?></span>
                    </div>
                    <p class="text-xs text-gray-500">
                        Уникальных пользователей: <?php echo $stat['unique_users']; ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Фильтры -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-filter mr-2"></i>
                Фильтры
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Пользователь</label>
                    <select name="user_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Все пользователи</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $filterUser == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo $u['full_name']; ?> (<?php echo t($u['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Действие</label>
                    <select name="action" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Все действия</option>
                        <option value="login" <?php echo $filterAction === 'login' ? 'selected' : ''; ?>>Вход</option>
                        <option value="failed_login" <?php echo $filterAction === 'failed_login' ? 'selected' : ''; ?>>Неудачный вход</option>
                        <option value="logout" <?php echo $filterAction === 'logout' ? 'selected' : ''; ?>>Выход</option>
                    </select>
                </div>
                
                <!-- НОВОЕ: Фильтр по типу авторизации -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Тип авторизации</label>
                    <select name="auth_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Все типы</option>
                        <option value="ldap" <?php echo $filterAuthType === 'ldap' ? 'selected' : ''; ?>>🌐 Домен (LDAP)</option>
                        <option value="local" <?php echo $filterAuthType === 'local' ? 'selected' : ''; ?>>💾 Локальная БД</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-search mr-2"></i>
                        Применить
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Таблица логов -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-list mr-2"></i>
                    История входов (последние 100 записей)
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP-адрес</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата и время</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2"></i>
                                    <p>Логи отсутствуют</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): 
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
                                $authType = $log['auth_type'] ?? 'local';
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-900">#<?php echo $log['id']; ?></td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $log['full_name']; ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $log['username']; ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo t($log['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $actionColors[$log['action']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                            <i class="fas <?php echo $actionIcons[$log['action']] ?? 'fa-circle'; ?> mr-1"></i>
                                            <?php echo $actionTexts[$log['action']] ?? $log['action']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $authType === 'ldap' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                            <?php echo $authType === 'ldap' ? '🌐 LDAP' : '💾 Local'; ?>
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
        
    </div>
    
</body>
</html>
