<?php
// technician_dashboard_v2.php - Улучшенная панель системотехника с вкладками

require_once 'config/db.php';
require_once 'config/ldap.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireLogin();
requireRole('technician');

$currentUser = getCurrentUser();
$technicianId = $currentUser['id'];

// Получение текущей вкладки
$tab = $_GET['tab'] ?? 'active';

// ════════════════════════════════════════════════════════════════
// СТАТИСТИКА ДЛЯ ТЕХНИКА
// ════════════════════════════════════════════════════════════════

// Активные заявки (общий пул)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM requests 
    WHERE status = 'pending'
");
$stmt->execute();
$activeCount = $stmt->fetch()['count'];

// Заявки в работе (мои)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM requests 
    WHERE status = 'in_progress' AND assigned_to = ?
");
$stmt->execute([$technicianId]);
$myWorkCount = $stmt->fetch()['count'];

// Ожидают одобрения (мои)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM requests 
    WHERE status IN ('awaiting_approval', 'approved', 'rejected') 
    AND assigned_to = ?
");
$stmt->execute([$technicianId]);
$awaitingCount = $stmt->fetch()['count'];

// Архив (мои завершённые)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM requests 
    WHERE status = 'completed' AND assigned_to = ?
");
$stmt->execute([$technicianId]);
$archiveCount = $stmt->fetch()['count'];

// ════════════════════════════════════════════════════════════════
// ПОЛУЧЕНИЕ ЗАЯВОК В ЗАВИСИМОСТИ ОТ ВКЛАДКИ
// ════════════════════════════════════════════════════════════════

switch ($tab) {
    case 'active':
        // Активные заявки (общий пул - не назначены)
        $stmt = $pdo->prepare("
            SELECT r.*, r.full_name as requester_name, r.cabinet as cabinet_name
            FROM requests r
            WHERE r.status = 'pending'
            ORDER BY 
                FIELD(r.priority, 'high', 'normal', 'low'),
                r.created_at ASC
        ");
        $stmt->execute();
        break;
        
    case 'in_progress':
        // Мои заявки в работе
        $stmt = $pdo->prepare("
            SELECT r.*, r.full_name as requester_name, r.cabinet as cabinet_name
            FROM requests r
            WHERE r.status = 'in_progress' AND r.assigned_to = ?
            ORDER BY 
                FIELD(r.priority, 'high', 'normal', 'low'),
                r.assigned_at ASC
        ");
        $stmt->execute([$technicianId]);
        break;
        
    case 'awaiting':
        // Мои заявки, ожидающие одобрения
        $stmt = $pdo->prepare("
            SELECT r.*, r.full_name as requester_name, r.cabinet as cabinet_name
            FROM requests r
            WHERE r.status IN ('awaiting_approval', 'approved', 'rejected') 
            AND r.assigned_to = ?
            ORDER BY r.approval_requested_at DESC
        ");
        $stmt->execute([$technicianId]);
        break;
        
    case 'archive':
        // Мои завершённые заявки
        $stmt = $pdo->prepare("
            SELECT r.*, r.full_name as requester_name, r.cabinet as cabinet_name
            FROM requests r
            WHERE r.status = 'completed' AND r.assigned_to = ?
            ORDER BY r.completed_at DESC
            LIMIT 50
        ");
        $stmt->execute([$technicianId]);
        break;
        
    default:
        $stmt = $pdo->query("SELECT * FROM requests WHERE 1=0");
}

$requests = $stmt->fetchAll();

// Функция получения цвета статуса
function getStatusColor($status) {
    $colors = [
        'pending' => 'blue',
        'in_progress' => 'yellow',
        'awaiting_approval' => 'purple',
        'approved' => 'green',
        'rejected' => 'red',
        'completed' => 'gray',
        'cancelled' => 'gray'
    ];
    return $colors[$status] ?? 'gray';
}

// Функция получения текста статуса
function getStatusText($status) {
    $texts = [
        'pending' => 'Ожидает',
        'in_progress' => 'В работе',
        'awaiting_approval' => 'На согласовании',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        'completed' => 'Завершена',
        'cancelled' => 'Отменена'
    ];
    return $texts[$status] ?? $status;
}

// Функция получения цвета приоритета
function getPriorityColor($priority) {
    return $priority === 'high' ? 'red' : ($priority === 'normal' ? 'blue' : 'gray');
}

function getPriorityText($priority) {
    $texts = [
        'high' => 'ВЫСОКИЙ',
        'normal' => 'ОБЫЧНЫЙ',
        'low' => 'НИЗКИЙ'
    ];
    return $texts[$priority] ?? $priority;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель системотехника</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    
    <!-- Навигация -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-tools text-2xl text-indigo-600 mr-3"></i>
                    <span class="text-xl font-bold text-gray-800">Панель системотехника</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($currentUser['full_name']); ?>
                    </span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i> Выход
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            
            <!-- Активные заявки -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Активные заявки</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $activeCount; ?></p>
                    </div>
                    <i class="fas fa-inbox text-4xl text-blue-500"></i>
                </div>
            </div>
            
            <!-- В работе -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">В работе</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $myWorkCount; ?></p>
                    </div>
                    <i class="fas fa-cog text-4xl text-yellow-500"></i>
                </div>
            </div>
            
            <!-- Ожидают одобрения -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">На согласовании</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $awaitingCount; ?></p>
                    </div>
                    <i class="fas fa-clock text-4xl text-purple-500"></i>
                </div>
            </div>
            
            <!-- Архив -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-gray-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Завершено</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $archiveCount; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-4xl text-gray-500"></i>
                </div>
            </div>
            
        </div>

        <!-- Вкладки -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    
                    <!-- Активные -->
                    <a href="?tab=active" 
                       class="<?php echo $tab === 'active' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> 
                              whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        Активные заявки
                        <?php if ($activeCount > 0): ?>
                            <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                <?php echo $activeCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- В работе -->
                    <a href="?tab=in_progress" 
                       class="<?php echo $tab === 'in_progress' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> 
                              whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-wrench mr-2"></i>
                        В работе
                        <?php if ($myWorkCount > 0): ?>
                            <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                <?php echo $myWorkCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Ожидают одобрения -->
                    <a href="?tab=awaiting" 
                       class="<?php echo $tab === 'awaiting' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> 
                              whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-hourglass-half mr-2"></i>
                        Ожидают одобрения
                        <?php if ($awaitingCount > 0): ?>
                            <span class="ml-2 bg-purple-100 text-purple-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                <?php echo $awaitingCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Архив -->
                    <a href="?tab=archive" 
                       class="<?php echo $tab === 'archive' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> 
                              whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-archive mr-2"></i>
                        Архив
                    </a>
                    
                </nav>
            </div>
        </div>

        <!-- Заявки -->
        <div class="space-y-4">
            <?php if (empty($requests)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">
                        <?php
                        switch ($tab) {
                            case 'active':
                                echo 'Нет активных заявок';
                                break;
                            case 'in_progress':
                                echo 'У вас нет заявок в работе';
                                break;
                            case 'awaiting':
                                echo 'Нет заявок на согласовании';
                                break;
                            case 'archive':
                                echo 'Архив пуст';
                                break;
                        }
                        ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $req): ?>
                    <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                
                                <!-- Левая часть -->
                                <div class="flex-1">
                                    <!-- Приоритет и статус -->
                                    <div class="flex items-center mb-2 space-x-2">
                                        <span class="px-2 py-1 text-xs font-bold rounded bg-<?php echo getPriorityColor($req['priority']); ?>-100 text-<?php echo getPriorityColor($req['priority']); ?>-800">
                                            <?php echo getPriorityText($req['priority']); ?>
                                        </span>
                                        <span class="px-2 py-1 text-xs font-bold rounded bg-<?php echo getStatusColor($req['status']); ?>-100 text-<?php echo getStatusColor($req['status']); ?>-800">
                                            <?php echo getStatusText($req['status']); ?>
                                        </span>
                                        <?php if ($req['request_type'] === 'repair'): ?>
                                            <span class="px-2 py-1 text-xs rounded bg-orange-100 text-orange-800">
                                                <i class="fas fa-tools"></i> РЕМОНТ И ОБСЛУЖИВАНИЕ
                                            </span>
                                        <?php elseif ($req['request_type'] === 'software'): ?>
                                            <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                                <i class="fas fa-laptop-code"></i> ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Кабинет -->
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                                        <i class="fas fa-door-open text-indigo-600"></i>
                                        Кабинет: <?php echo htmlspecialchars($req['cabinet_name'] ?? 'Не указан'); ?>
                                    </h3>
                                    
                                    <!-- Заявитель -->
                                    <p class="text-gray-600 mb-2">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($req['requester_name']); ?>
                                        •
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?>
                                    </p>
                                    
                                    <!-- Описание -->
                                    <p class="text-gray-700 mb-3">
                                        <?php echo nl2br(htmlspecialchars($req['description'])); ?>
                                    </p>
                                    
                                    <!-- Временные метки -->
                                    <?php if ($req['assigned_at']): ?>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-user-check text-green-600"></i>
                                            Взята в работу: <?php echo date('d.m.Y H:i', strtotime($req['assigned_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['approval_requested_at']): ?>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-paper-plane text-purple-600"></i>
                                            Отправлена на согласование: <?php echo date('d.m.Y H:i', strtotime($req['approval_requested_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Кнопки действий -->
                                <div class="ml-4 flex flex-col space-y-2">
                                    <?php if ($tab === 'active'): ?>
                                        <!-- Взять в работу -->
                                        <button onclick="takeRequest(<?php echo $req['id']; ?>)" 
                                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 whitespace-nowrap">
                                            <i class="fas fa-hand-paper"></i> Взять в работу
                                        </button>
                                    <?php elseif ($tab === 'in_progress'): ?>
                                        <!-- Отправить на согласование -->
                                        <button onclick="sendForApproval(<?php echo $req['id']; ?>)" 
                                                class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 whitespace-nowrap">
                                            <i class="fas fa-check"></i> На согласование
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Просмотр -->
                                    <a href="view_request.php?id=<?php echo $req['id']; ?>" 
                                       class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-center whitespace-nowrap">
                                        <i class="fas fa-eye"></i> Просмотр
                                    </a>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <script>
    // Взять заявку в работу
    function takeRequest(requestId) {
        if (confirm('Взять эту заявку в работу?')) {
            fetch('api/take_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Заявка взята в работу!');
                    location.reload();
                } else {
                    alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ошибка при обработке запроса');
            });
        }
    }

    // Отправить на согласование
    function sendForApproval(requestId) {
        const comment = prompt('Добавьте комментарий о выполненной работе:');
        if (comment !== null) {
            fetch('api/send_for_approval.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    comment: comment
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Заявка отправлена на согласование!');
                    location.reload();
                } else {
                    alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ошибка при обработке запроса');
            });
        }
    }
    </script>

</body>
</html>
