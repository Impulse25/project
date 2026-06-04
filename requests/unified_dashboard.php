<?php
// unified_dashboard.php - Универсальная панель для новых ролей

require_once __DIR__ . '/../config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireLogin();

$user = getCurrentUser();

// Проверка что это НЕ старая роль (они должны быть на своих страницах)
$oldRoles = ['admin', 'director', 'teacher', 'technician'];
if (in_array($user['role'], $oldRoles)) {
    redirectToDashboard(); // Перенаправляем на правильную страницу
}

// Получение прав текущей роли из таблицы roles
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

// Если роль не найдена - базовые права (только свои заявки)
if (!$permissions) {
    $permissions = [
        'role_name_ru' => 'Пользователь',
        'role_name_kk' => 'Пайдаланушы',
        'can_create_request' => 1,
        'can_approve_request' => 0,
        'can_work_on_request' => 0,
        'can_manage_users' => 0,
        'can_manage_cabinets' => 0,
        'can_view_all_requests' => 0
    ];
}

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: unified_dashboard.php?tab=' . ($_GET['tab'] ?? 'my_requests'));
    exit();
}

// Текущая вкладка
$currentTab = $_GET['tab'] ?? 'my_requests';

// Получение ВСЕХ заявок (будем фильтровать по вкладкам)
$stmt = $pdo->query("
    SELECT r.*, u.full_name as creator_name, u.position as creator_position,
           t.full_name as technician_name
    FROM requests r 
    JOIN users u ON r.created_by = u.id 
    LEFT JOIN users t ON r.assigned_to = t.id
    ORDER BY 
        FIELD(r.priority, 'urgent', 'high', 'normal', 'low'),
        r.created_at DESC
");
$allRequests = $stmt->fetchAll();

// Фильтрация заявок в зависимости от вкладки
$displayRequests = [];

switch ($currentTab) {
    case 'my_requests':
        // Мои заявки (созданные мной)
        $displayRequests = array_filter($allRequests, function($r) use ($user) {
            return $r['created_by'] == $user['id'];
        });
        break;
        
    case 'pending':
        // Заявки на одобрение (только если есть право)
        if ($permissions['can_approve_request']) {
            $displayRequests = array_filter($allRequests, function($r) {
                return $r['status'] == 'pending';
            });
        }
        break;
        
    case 'active':
        // Активные заявки для работы (только если есть право)
        if ($permissions['can_work_on_request']) {
            $displayRequests = array_filter($allRequests, function($r) {
                return in_array($r['status'], ['new', 'approved', 'in_progress']);
            });
        }
        break;
        
    case 'all':
        // Все заявки (только если есть право)
        if ($permissions['can_view_all_requests']) {
            $displayRequests = $allRequests;
        }
        break;
        
    case 'archive':
        // Архив завершенных заявок
        $displayRequests = array_filter($allRequests, function($r) use ($user, $permissions) {
            $isCompleted = $r['status'] == 'completed';
            // Показываем либо свои, либо все (если есть право)
            if ($permissions['can_view_all_requests']) {
                return $isCompleted;
            } else {
                return $isCompleted && $r['created_by'] == $user['id'];
            }
        });
        break;
}

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

// Статистика для панели
$stats = [
    'my_total' => count(array_filter($allRequests, function($r) use ($user) {
        return $r['created_by'] == $user['id'];
    })),
    'pending' => count(array_filter($allRequests, function($r) {
        return $r['status'] == 'pending';
    })),
    'active' => count(array_filter($allRequests, function($r) {
        return in_array($r['status'], ['new', 'approved', 'in_progress']);
    })),
    'completed' => count(array_filter($allRequests, function($r) {
        return $r['status'] == 'completed';
    })),
];

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('my_requests'); ?> - <?php echo t('system_name'); ?></title>
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
        .tab-link {
            position: relative;
            padding: 12px 20px;
            transition: all 0.2s;
        }
        .tab-link.active {
            color: #4F46E5;
            font-weight: 600;
        }
        .tab-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #4F46E5;
            border-radius: 3px 3px 0 0;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
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
                    echo '<i class="fas fa-user text-3xl text-gray-600"></i>';
                }
                ?>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600">
                        <?php 
                        $roleName = $currentLang === 'kk' ? $permissions['role_name_kk'] : $permissions['role_name_ru'];
                        echo $roleName . ': ' . $user['full_name']; 
                        ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <!-- Переключатель языка -->
                <div class="flex gap-2">
                    <a href="?tab=<?php echo $currentTab; ?>&lang=ru" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
                        Рус
                    </a>
                    <a href="?tab=<?php echo $currentTab; ?>&lang=kk" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
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
        
        <!-- Навигация по вкладкам -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="flex items-center justify-between border-b">
                <div class="flex">
                    <!-- Мои заявки -->
                    <?php if ($permissions['can_create_request']): ?>
                    <a href="?tab=my_requests" class="tab-link <?php echo $currentTab === 'my_requests' ? 'active' : 'text-gray-600 hover:text-gray-900'; ?>">
                        <i class="fas fa-file-alt mr-2"></i>
                        Мои заявки
                        <?php if ($stats['my_total'] > 0): ?>
                            <span class="badge bg-blue-100 text-blue-700 ml-2"><?php echo $stats['my_total']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <!-- На одобрение -->
                    <?php if ($permissions['can_approve_request']): ?>
                    <a href="?tab=pending" class="tab-link <?php echo $currentTab === 'pending' ? 'active' : 'text-gray-600 hover:text-gray-900'; ?>">
                        <i class="fas fa-clock mr-2"></i>
                        На одобрение
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="badge bg-yellow-100 text-yellow-700 ml-2"><?php echo $stats['pending']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Активные -->
                    <?php if ($permissions['can_work_on_request']): ?>
                    <a href="?tab=active" class="tab-link <?php echo $currentTab === 'active' ? 'active' : 'text-gray-600 hover:text-gray-900'; ?>">
                        <i class="fas fa-wrench mr-2"></i>
                        Активные
                        <?php if ($stats['active'] > 0): ?>
                            <span class="badge bg-purple-100 text-purple-700 ml-2"><?php echo $stats['active']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Все заявки -->
                    <?php if ($permissions['can_view_all_requests']): ?>
                    <a href="?tab=all" class="tab-link <?php echo $currentTab === 'all' ? 'active' : 'text-gray-600 hover:text-gray-900'; ?>">
                        <i class="fas fa-list mr-2"></i>
                        Все заявки
                    </a>
                    <?php endif; ?>
                    
                    <!-- Архив -->
                    <a href="?tab=archive" class="tab-link <?php echo $currentTab === 'archive' ? 'active' : 'text-gray-600 hover:text-gray-900'; ?>">
                        <i class="fas fa-archive mr-2"></i>
                        Архив
                        <?php if ($stats['completed'] > 0): ?>
                            <span class="badge bg-gray-100 text-gray-700 ml-2"><?php echo $stats['completed']; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <!-- Кнопка создать заявку -->
                <?php if ($permissions['can_create_request']): ?>
                <div class="p-3">
                    <a href="create_request.php" class="flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-plus"></i>
                        <?php echo t('create_request'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Заголовок текущей вкладки -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <?php 
                switch($currentTab) {
                    case 'my_requests': echo 'Мои заявки'; break;
                    case 'pending': echo 'Заявки на одобрение'; break;
                    case 'active': echo 'Активные заявки'; break;
                    case 'all': echo 'Все заявки'; break;
                    case 'archive': echo 'Архив заявок'; break;
                }
                ?>
            </h2>
            <p class="text-sm text-gray-600 mt-1">
                Найдено заявок: <span class="font-semibold"><?php echo count($displayRequests); ?></span>
            </p>
        </div>
        
        <!-- Список заявок -->
        <div class="space-y-4">
            <?php if (empty($displayRequests)): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600 text-lg">Заявок нет</p>
                    <?php if ($currentTab === 'my_requests' && $permissions['can_create_request']): ?>
                        <a href="create_request.php" class="inline-block mt-4 text-indigo-600 hover:text-indigo-700 font-medium">
                            <i class="fas fa-plus mr-2"></i>Создать первую заявку
                        </a>
                    <?php endif; ?>
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
                                    <!-- ПРИОРИТЕТ -->
                                    <span class="priority-badge <?php echo getPriorityColor($priority); ?>">
                                        <i class="fas <?php echo getPriorityIcon($priority); ?>"></i>
                                        <?php echo getPriorityText($priority); ?>
                                    </span>
                                    
                                    <span class="text-xs font-medium text-gray-500 uppercase">
                                        <?php echo t($req['request_type']); ?>
                                    </span>
                                    <span class="text-xs text-gray-400">•</span>
                                    <span class="text-xs text-gray-500"><?php echo t('cabinet'); ?>: <?php echo $req['cabinet']; ?></span>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <?php 
                                    if ($req['request_type'] === 'repair') {
                                        echo $req['equipment_type'] . ' (' . $req['inventory_number'] . ')';
                                    } elseif ($req['request_type'] === 'software') {
                                        echo t('software') . ' - ' . ($req['software_name'] ?? $req['computer_inventory']);
                                    } else {
                                        echo t('1c_database') . ' - ' . $req['group_number'];
                                    }
                                    ?>
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php 
                                    if ($req['request_type'] === 'repair') {
                                        echo $req['description'] ?? '';
                                    } elseif ($req['request_type'] === 'software') {
                                        echo $req['justification'] ?? '';
                                    } else {
                                        echo $req['database_purpose'] ?? '';
                                    }
                                    ?>
                                </p>
                                <?php if ($currentTab !== 'my_requests'): ?>
                                    <p class="text-sm text-gray-500 mt-2">
                                        <i class="fas fa-user mr-1"></i>
                                        <strong>Создал:</strong> <?php echo $req['creator_name']; ?> (<?php echo $req['creator_position']; ?>)
                                    </p>
                                <?php endif; ?>
                                <?php if ($req['technician_name']): ?>
                                    <p class="text-sm text-gray-500 mt-1">
                                        <i class="fas fa-user-cog mr-1"></i>
                                        <strong>Исполнитель:</strong> <?php echo $req['technician_name']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusColors[$req['status']]; ?>">
                                <?php echo t($req['status']); ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm text-gray-500 pt-3 border-t">
                            <span>
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo t('created'); ?>: <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?>
                            </span>
                            <div class="flex gap-2">
                                <a href="view_request.php?id=<?php echo $req['id']; ?>" class="text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                                    <i class="fas fa-eye"></i>
                                    <?php echo t('details'); ?>
                                </a>
                                
                                <!-- Кнопки действий в зависимости от прав и статуса -->
                                <?php if ($permissions['can_approve_request'] && $req['status'] === 'pending'): ?>
                                    <a href="approve_request.php?id=<?php echo $req['id']; ?>" class="text-green-600 hover:text-green-700 flex items-center gap-1 ml-3">
                                        <i class="fas fa-check"></i>
                                        Одобрить
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($permissions['can_work_on_request'] && in_array($req['status'], ['new', 'approved'])): ?>
                                    <a href="assign_request.php?id=<?php echo $req['id']; ?>" class="text-blue-600 hover:text-blue-700 flex items-center gap-1 ml-3">
                                        <i class="fas fa-hand-paper"></i>
                                        Взять в работу
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>
    
</body>
</html>
