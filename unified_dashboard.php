<?php
// unified_dashboard.php - Универсальная панель для всех ролей

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireLogin();

$user = getCurrentUser();

// Получение прав текущей роли
$stmt = $pdo->prepare("
    SELECT 
        role_name_ru,
        role_name_kk,
        can_create_request,
        can_approve_request,
        can_work_on_request,
        can_manage_users,
        can_manage_cabinets,
        can_view_all_requests
    FROM roles 
    WHERE role_code = ?
");
$stmt->execute([$user['role']]);
$permissions = $stmt->fetch();

// Если роль не найдена в таблице - используем базовые права
if (!$permissions) {
    // Fallback для старых ролей
    $permissions = [
        'role_name_ru' => 'Пользователь',
        'role_name_kk' => 'Пайдаланушы',
        'can_create_request' => ($user['role'] === 'teacher'),
        'can_approve_request' => ($user['role'] === 'director'),
        'can_work_on_request' => ($user['role'] === 'technician'),
        'can_manage_users' => ($user['role'] === 'admin'),
        'can_manage_cabinets' => ($user['role'] === 'admin'),
        'can_view_all_requests' => in_array($user['role'], ['admin', 'director', 'technician'])
    ];
}

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: unified_dashboard.php?tab=' . ($_GET['tab'] ?? 'requests'));
    exit();
}

// Определение активной вкладки
$tab = $_GET['tab'] ?? 'requests';

// Получение заявок в зависимости от прав
if ($permissions['can_view_all_requests']) {
    // Видит все заявки
    $stmt = $pdo->query("
        SELECT r.*, u.full_name as creator_name,
        FIELD(r.priority, 'urgent', 'high', 'normal', 'low') as priority_order
        FROM requests r 
        JOIN users u ON r.created_by = u.id 
        ORDER BY priority_order ASC, r.created_at DESC
    ");
    $requests = $stmt->fetchAll();
} else {
    // Видит только свои заявки
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as creator_name 
        FROM requests r 
        JOIN users u ON r.created_by = u.id 
        WHERE r.created_by = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
}

// Строка 79
$myRequests = array_filter($requests, function($r) use ($user) {
    return $r['created_by'] == $user['id'];
});

// Строка 80
$pendingApproval = array_filter($requests, function($r) {
    return $r['status'] == 'pending';
});

// Строка 81
$activeRequests = array_filter($requests, function($r) {
    return in_array($r['status'], ['new', 'approved', 'in_progress']);
});

// Строка 82
$completedRequests = array_filter($requests, function($r) {
    return $r['status'] === 'completed';
});

// Строка 86
$newRequests = count(array_filter($requests, function($r) {
    return $r['status'] === 'new';
}));

// Строка 87
$inProgressRequests = count(array_filter($requests, function($r) {
    return $r['status'] === 'in_progress';
}));
$completedCount = count($completedRequests);

$currentLang = getCurrentLanguage();

// Функции для приоритетов
function getPriorityColor($priority) {
    $colors = [
        'low' => 'bg-gray-100 text-gray-800',
        'normal' => 'bg-blue-100 text-blue-800',
        'high' => 'bg-orange-200 text-orange-900',
        'urgent' => 'bg-red-200 text-red-900'
    ];
    return $colors[$priority] ?? 'bg-gray-100 text-gray-700';
}

function getPriorityIcon($priority) {
    $icons = [
        'low' => 'fa-angle-double-down',
        'normal' => 'fa-minus',
        'high' => 'fa-angle-double-up',
        'urgent' => 'fa-exclamation-triangle'
    ];
    return $icons[$priority] ?? 'fa-minus';
}

function getPriorityText($priority) {
    $texts = [
        'low' => 'Низкий',
        'normal' => 'Обычный',
        'high' => 'Высокий',
        'urgent' => '🔥 СРОЧНО'
    ];
    return $texts[$priority] ?? 'Обычный';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <?php
                // Иконка в зависимости от прав
                if ($permissions['can_manage_users']) {
                    echo '<i class="fas fa-user-shield text-3xl text-indigo-600"></i>';
                } elseif ($permissions['can_approve_request']) {
                    echo '<i class="fas fa-clipboard-check text-3xl text-green-600"></i>';
                } elseif ($permissions['can_work_on_request']) {
                    echo '<i class="fas fa-tools text-3xl text-blue-600"></i>';
                } else {
                    echo '<i class="fas fa-user text-3xl text-indigo-600"></i>';
                }
                ?>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600">
                        <?php echo $currentLang === 'ru' ? $permissions['role_name_ru'] : $permissions['role_name_kk']; ?>: 
                        <?php echo $user['full_name']; ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <!-- Переключатель языка -->
                <div class="flex gap-2">
                    <a href="?lang=ru&tab=<?php echo $tab; ?>" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
                        Рус
                    </a>
                    <a href="?lang=kk&tab=<?php echo $tab; ?>" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
                        Қаз
                    </a>
                </div>
                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo t('exit'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Основной контент -->
    <div class="max-w-7xl mx-auto p-6">
        
        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Всего заявок</p>
                        <p class="text-3xl font-bold text-indigo-600"><?php echo $totalRequests; ?></p>
                    </div>
                    <i class="fas fa-clipboard-list text-4xl text-indigo-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Новые</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $newRequests; ?></p>
                    </div>
                    <i class="fas fa-file-alt text-4xl text-blue-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">В работе</p>
                        <p class="text-3xl font-bold text-orange-600"><?php echo $inProgressRequests; ?></p>
                    </div>
                    <i class="fas fa-spinner text-4xl text-orange-600"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Завершено</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $completedCount; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-4xl text-green-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Навигация по вкладкам (показываются только те, на которые есть права) -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px overflow-x-auto">
                    <!-- Вкладка "Мои заявки" - показывается всем -->
                    <?php if ($permissions['can_create_request'] || !$permissions['can_view_all_requests']): ?>
                        <a href="?tab=my_requests" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'my_requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            <i class="fas fa-user"></i>
                            Мои заявки (<?php echo count($myRequests); ?>)
                        </a>
                    <?php endif; ?>
                    
                    <!-- Вкладка "Все заявки" - для тех, кто видит все -->
                    <?php if ($permissions['can_view_all_requests']): ?>
                        <a href="?tab=all_requests" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'all_requests' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            <i class="fas fa-list"></i>
                            Все заявки (<?php echo $totalRequests; ?>)
                        </a>
                    <?php endif; ?>
                    
                    <!-- Вкладка "На одобрение" - для директоров -->
                    <?php if ($permissions['can_approve_request']): ?>
                        <a href="?tab=pending" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'pending' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            <i class="fas fa-clock"></i>
                            На одобрение (<?php echo count($pendingApproval); ?>)
                        </a>
                    <?php endif; ?>
                    
                    <!-- Вкладка "Активные" - для системотехников -->
                    <?php if ($permissions['can_work_on_request']): ?>
                        <a href="?tab=active" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'active' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            <i class="fas fa-tasks"></i>
                            Активные (<?php echo count($activeRequests); ?>)
                        </a>
                    <?php endif; ?>
                    
                    <!-- Вкладка "Архив" -->
                    <a href="?tab=archive" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'archive' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-archive"></i>
                        Архив (<?php echo $completedCount; ?>)
                    </a>
                    
                    <!-- Вкладка "Админ" - только для администраторов -->
                    <?php if ($permissions['can_manage_users']): ?>
                        <a href="admin_dashboard.php" class="whitespace-nowrap border-b-2 py-3 px-4 font-medium flex items-center gap-2 border-transparent text-gray-500 hover:text-gray-700">
                            <i class="fas fa-cog"></i>
                            Администрирование
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
        
        <!-- Действия -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <?php 
                if ($tab === 'my_requests') echo 'Мои заявки';
                elseif ($tab === 'all_requests') echo 'Все заявки';
                elseif ($tab === 'pending') echo 'Заявки на одобрение';
                elseif ($tab === 'active') echo 'Активные заявки';
                elseif ($tab === 'archive') echo 'Архив';
                else echo 'Заявки';
                ?>
            </h2>
            <?php if ($permissions['can_create_request']): ?>
                <a href="create_request.php" class="flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-plus"></i>
                    Создать заявку
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Список заявок -->
        <div class="space-y-4">
            <?php 
            // Выбираем нужный массив заявок в зависимости от вкладки
            $displayRequests = [];
            if ($tab === 'my_requests') $displayRequests = $myRequests;
            elseif ($tab === 'all_requests') $displayRequests = $requests;
            elseif ($tab === 'pending') $displayRequests = $pendingApproval;
            elseif ($tab === 'active') $displayRequests = $activeRequests;
            elseif ($tab === 'archive') $displayRequests = $completedRequests;
            else $displayRequests = $requests;
            
            if (empty($displayRequests)): 
            ?>
                <div class="bg-white rounded-lg shadow p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">Нет заявок</p>
                </div>
            <?php else: ?>
                <?php foreach ($displayRequests as $req): 
                    $statusColors = [
                        'new' => 'bg-blue-100 text-blue-800',
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'approved' => 'bg-green-100 text-green-800',
                        'rejected' => 'bg-red-100 text-red-800',
                        'in_progress' => 'bg-purple-100 text-purple-800',
                        'completed' => 'bg-gray-100 text-gray-800'
                    ];
                    $typeColors = [
                        'repair' => 'border-red-200',
                        'software' => 'border-blue-200',
                        '1c_database' => 'border-purple-200'
                    ];
                    
                    $priority = $req['priority'] ?? 'normal';
                ?>
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition border-l-4 <?php echo $typeColors[$req['request_type']]; ?>">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2 flex-wrap">
                                    <!-- Приоритет -->
                                    <span class="priority-badge <?php echo getPriorityColor($priority); ?>">
                                        <i class="fas <?php echo getPriorityIcon($priority); ?>"></i>
                                        <?php echo getPriorityText($priority); ?>
                                    </span>
                                    
                                    <span class="text-xs font-medium text-gray-500 uppercase">
                                        <?php echo t($req['request_type']); ?>
                                    </span>
                                    <span class="text-xs text-gray-400">•</span>
                                    <span class="text-xs text-gray-500">Кабинет: <?php echo $req['cabinet']; ?></span>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <?php 
                                    if ($req['request_type'] === 'repair') {
                                        echo $req['equipment_type'] . ' (' . $req['inventory_number'] . ')';
                                    } elseif ($req['request_type'] === 'software') {
                                        echo 'Установка ПО - ' . ($req['software_name'] ?? $req['computer_inventory']);
                                    } else {
                                        echo 'База данных 1С - ' . $req['group_number'];
                                    }
                                    ?>
                                </h3>
                                <?php if ($permissions['can_view_all_requests']): ?>
                                    <p class="text-sm text-gray-500 mt-1">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo $req['creator_name']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusColors[$req['status']]; ?>">
                                    <?php echo t($req['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center text-sm text-gray-500 pt-3 border-t">
                            <span>
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?>
                            </span>
                            <a href="view_request.php?id=<?php echo $req['id']; ?>" class="text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                                <i class="fas fa-eye"></i>
                                Подробнее
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>
    
</body>
</html>