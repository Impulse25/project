<?php
// technician_dashboard.php - Панель системного техника (УЛУЧШЕННАЯ)

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('technician');

$user = getCurrentUser();
$technicianId = $user['id'];

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: technician_dashboard.php');
    exit();
}

// Получение текущей вкладки
$tab = $_GET['tab'] ?? 'active';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    
    if ($action === 'take_to_work') {
        // Взять заявку в работу
        $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', assigned_to = ?, assigned_at = NOW(), started_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $requestId]);
        
        // Лог действия
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'assigned', 'pending', 'in_progress', 'Взял заявку в работу')");
        $stmt->execute([$requestId, $user['id']]);
        
    } elseif ($action === 'complete') {
        // Системотехник завершает работу - отправляет на подтверждение преподавателю
        $comment = $_POST['comment'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'awaiting_approval', approval_requested_at = NOW(), sent_to_director = 0 WHERE id = ?");
        $stmt->execute([$requestId]);
        
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
        }
        
        // Лог
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'sent_for_approval', 'in_progress', 'awaiting_approval', ?)");
        $stmt->execute([$requestId, $user['id'], 'Отправлено на подтверждение преподавателю: ' . $comment]);
        
    } elseif ($action === 'add_comment') {
        $comment = $_POST['comment'] ?? '';
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
            
            // Лог
            $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, comment) VALUES (?, ?, 'comment_added', ?)");
            $stmt->execute([$requestId, $user['id'], 'Добавлен комментарий: ' . mb_substr($comment, 0, 100)]);
        }
        
    } elseif ($action === 'edit_comment') {
        // Редактирование существующего комментария
        $commentId = $_POST['comment_id'] ?? 0;
        $newComment = $_POST['comment'] ?? '';
        
        // Проверяем что комментарий принадлежит текущему пользователю
        $stmt = $pdo->prepare("SELECT user_id, comment FROM comments WHERE id = ? AND request_id = ?");
        $stmt->execute([$commentId, $requestId]);
        $existingComment = $stmt->fetch();
        
        if ($existingComment && $existingComment['user_id'] == $user['id'] && $newComment) {
            // Обновляем комментарий
            $stmt = $pdo->prepare("UPDATE comments SET comment = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newComment, $commentId]);
            
            // Лог
            $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, comment) VALUES (?, ?, 'comment_edited', ?)");
            $stmt->execute([$requestId, $user['id'], 'Отредактирован комментарий']);
        }
        
    } elseif ($action === 'send_to_director') {
        // Отправка на согласование директору
        $comment = $_POST['comment'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'awaiting_approval', sent_to_director = 1, sent_to_director_at = NOW(), approval_requested_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
        
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
        }
        
        // Лог
        $logComment = 'Отправлено директору на согласование' . ($comment ? ': ' . $comment : '');
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'sent_to_director', 'in_progress', 'awaiting_approval', ?)");
        $stmt->execute([$requestId, $user['id'], $logComment]);
        
    } elseif ($action === 'reject') {
        // Отклонение заявки
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $requestId]);
        
        if ($reason) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], 'Заявка отклонена. Причина: ' . $reason]);
        }
        
        // Лог
        $logComment = 'Заявка отклонена' . ($reason ? '. Причина: ' . $reason : '');
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'rejected', 'pending', 'rejected', ?)");
        $stmt->execute([$requestId, $user['id'], $logComment]);
        
    } elseif ($action === 'set_deadline') {
        // Установка срока выполнения
        $deadline = $_POST['deadline'] ?? '';
        if ($deadline) {
            $stmt = $pdo->prepare("UPDATE requests SET deadline = ?, deadline_set_by = ? WHERE id = ?");
            $stmt->execute([$deadline, $user['id'], $requestId]);
            
            // Лог
            $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, comment) VALUES (?, ?, 'deadline_set', ?)");
            $stmt->execute([$requestId, $user['id'], 'Установлен срок выполнения: ' . date('d.m.Y H:i', strtotime($deadline))]);
        }
    }
    
    header('Location: technician_dashboard.php?tab=' . $tab);
    exit();
}

// ════════════════════════════════════════════════════════════════
// ПОЛУЧЕНИЕ ЗАЯВОК В ЗАВИСИМОСТИ ОТ ВКЛАДКИ
// ════════════════════════════════════════════════════════════════

// Статистика
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE status = 'pending'");
$stmt->execute();
$activeCount = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE (status = 'in_progress' OR status = 'approved') AND assigned_to = ?");
$stmt->execute([$technicianId]);
$myWorkCount = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE status = 'awaiting_approval' AND assigned_to = ? AND sent_to_director = 0");
$stmt->execute([$technicianId]);
$awaitingCount = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE status = 'awaiting_approval' AND assigned_to = ? AND sent_to_director = 1");
$stmt->execute([$technicianId]);
$awaitingDirectorCount = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE status = 'completed' AND assigned_to = ?");
$stmt->execute([$technicianId]);
$archiveCount = $stmt->fetch()['cnt'];

// Получение заявок
$requests = [];

if ($tab === 'active') {
    // Активные заявки (только новые без назначения)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name,
        FIELD(r.priority, 'urgent', 'high', 'normal', 'low') as priority_order
        FROM requests r 
        WHERE r.status = 'pending' AND r.assigned_to IS NULL
        ORDER BY priority_order ASC, r.created_at ASC
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'in_progress') {
    // Мои заявки в работе (включая одобренные директором)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name,
        FIELD(r.priority, 'urgent', 'high', 'normal', 'low') as priority_order
        FROM requests r 
        WHERE (r.status = 'in_progress' OR r.status = 'approved') AND r.assigned_to = ?
        ORDER BY priority_order ASC, r.started_at ASC
    ");
    $stmt->execute([$technicianId]);
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'awaiting') {
    // Ожидают подтверждения от преподавателя (отправленные системотехником)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name
        FROM requests r 
        WHERE r.status = 'awaiting_approval' AND r.assigned_to = ? AND r.sent_to_director = 0
        ORDER BY r.approval_requested_at DESC
    ");
    $stmt->execute([$technicianId]);
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'awaiting_director') {
    // Ожидают согласования директора (отправленные директору)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name
        FROM requests r 
        WHERE r.status = 'awaiting_approval' AND r.assigned_to = ? AND r.sent_to_director = 1
        ORDER BY r.sent_to_director_at DESC
    ");
    $stmt->execute([$technicianId]);
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'archive') {
    // Архив (мои завершённые)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name 
        FROM requests r 
        WHERE r.status = 'completed' AND r.assigned_to = ? 
        ORDER BY r.completed_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$technicianId]);
    $requests = $stmt->fetchAll();
}

// Функции для работы с приоритетами
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

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('technician'); ?> - <?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .priority-low { 
            background: #f3f4f6; 
            color: #1f2937;
            border: 2px solid #6b7280;
        }
        .priority-normal { 
            background: #dbeafe; 
            color: #1e40af;
            border: 2px solid #3b82f6;
        }
        .priority-high { 
            background: #fed7aa; 
            color: #c2410c;
            border: 2px solid #ea580c;
            box-shadow: 0 2px 4px rgba(234, 88, 12, 0.2);
        }
        .priority-urgent { 
            background: #fca5a5; 
            color: #7f1d1d;
            border: 2px solid #dc2626;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { 
                opacity: 1; 
                transform: scale(1);
            }
            50% { 
                opacity: 0.85;
                transform: scale(1.02);
            }
        }
        .deadline-input-group {
            display: flex;
            gap: 8px;
            align-items: center;
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 12px;
        }
        .deadline-input-group input[type="date"] {
            flex: 1;
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .deadline-input-group button {
            padding: 6px 16px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .deadline-input-group button:hover {
            background: #4338ca;
        }
        #rejectModal {
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
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-cog text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600"><?php echo t('technician'); ?>: <?php echo $user['full_name']; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex gap-2">
                    <a href="?lang=ru&tab=<?php echo $tab; ?>" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Рус</a>
                    <a href="?lang=kk&tab=<?php echo $tab; ?>" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Қаз</a>
                </div>
                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo t('exit'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto p-6">
        
        <!-- Вкладки -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-4">
                    <a href="?tab=active" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'active' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-tasks"></i>
                        Активные заявки (<?php echo $activeCount; ?>)
                    </a>
                    <a href="?tab=in_progress" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'in_progress' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-wrench"></i>
                        В работе (<?php echo $myWorkCount; ?>)
                    </a>
                    <a href="?tab=awaiting" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'awaiting' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-hourglass-half"></i>
                        Ожидают подтверждения (<?php echo $awaitingCount; ?>)
                    </a>
                    <a href="?tab=awaiting_director" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'awaiting_director' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-user-tie"></i>
                        Ожидают согласования (<?php echo $awaitingDirectorCount; ?>)
                    </a>
                    <a href="?tab=archive" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'archive' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-archive"></i>
                        Архив (<?php echo $archiveCount; ?>)
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Заголовок -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <?php
                switch ($tab) {
                    case 'active':
                        echo 'Активные заявки';
                        break;
                    case 'in_progress':
                        echo 'Мои заявки в работе';
                        break;
                    case 'awaiting':
                        echo 'Ожидают подтверждения от преподавателя';
                        break;
                    case 'awaiting_director':
                        echo 'Ожидают согласования директора';
                        break;
                    case 'archive':
                        echo 'Архив';
                        break;
                }
                ?>
            </h2>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <i class="fas fa-info-circle"></i>
                <span>Всего: <?php echo count($requests); ?></span>
            </div>
        </div>
        
        <!-- Заявки -->
        <?php if (empty($requests)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-500">Нет заявок</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($requests as $req): ?>
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition p-6 border-l-4 <?php 
                        if ($req['priority'] === 'urgent') echo 'border-red-500';
                        elseif ($req['priority'] === 'high') echo 'border-orange-500';
                        elseif ($req['priority'] === 'normal') echo 'border-blue-500';
                        else echo 'border-gray-300';
                    ?>">
                        
                        <!-- Заголовок заявки -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <!-- Приоритет -->
                                    <span class="priority-badge priority-<?php echo $req['priority']; ?>">
                                        <i class="fas <?php echo getPriorityIcon($req['priority']); ?>"></i>
                                        <?php echo getPriorityText($req['priority']); ?>
                                    </span>
                                    
                                    <!-- Тип заявки -->
                                    <?php if ($req['request_type'] === 'repair'): ?>
                                        <span class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-semibold">
                                            <i class="fas fa-tools"></i> РЕМОНТ И ОБСЛУЖИВАНИЕ
                                        </span>
                                    <?php elseif ($req['request_type'] === 'software'): ?>
                                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                            <i class="fas fa-laptop-code"></i> ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ
                                        </span>
                                    <?php elseif ($req['request_type'] === '1c_database'): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                            <i class="fas fa-database"></i> 1С БАЗА ДАННЫХ
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center gap-4 text-sm text-gray-600">
                                    <span><i class="fas fa-door-open text-indigo-600"></i> <span class="text-xs text-gray-500">Кабинет:</span> <strong><?php echo htmlspecialchars($req['cabinet'] ?? 'Не указан'); ?></strong></span>
                                    <span><i class="fas fa-user text-gray-500"></i> <?php echo htmlspecialchars($req['creator_name']); ?></span>
                                    <span><i class="fas fa-calendar text-gray-500"></i> <span class="text-xs text-gray-500">Создана:</span> <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?></span>
                                    
                                    <?php if ($req['deadline'] && $tab !== 'archive'): ?>
                                        <?php 
                                        $deadline = strtotime($req['deadline']);
                                        $now = time();
                                        $daysLeft = ceil(($deadline - $now) / 86400);
                                        
                                        if ($daysLeft < 0) {
                                            echo '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold"><i class="fas fa-exclamation-triangle"></i> <span class="text-xs">Срок:</span> ' . date('d.m.Y', $deadline) . ' (Просрочено!)</span>';
                                        } elseif ($daysLeft == 0) {
                                            echo '<span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-bold animate-pulse"><i class="fas fa-clock"></i> <span class="text-xs">Срок:</span> ' . date('d.m.Y', $deadline) . ' (СЕГОДНЯ)</span>';
                                        } elseif ($daysLeft <= 3) {
                                            echo '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold"><i class="fas fa-hourglass-half"></i> <span class="text-xs">Срок:</span> ' . date('d.m.Y', $deadline) . ' (Осталось ' . $daysLeft . ' дн.)</span>';
                                        } else {
                                            echo '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs"><i class="fas fa-calendar-check"></i> <span class="text-xs">Срок:</span> ' . date('d.m.Y', $deadline) . ' (Осталось ' . $daysLeft . ' дн.)</span>';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- ID заявки -->
                            <div class="text-right">
                                <span class="text-xs text-gray-500">Заявка №</span>
                                <span class="text-lg font-bold text-gray-800"><?php echo $req['id']; ?></span>
                            </div>
                        </div>
                        
                        <!-- Описание проблемы -->
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                            <div class="text-xs font-semibold text-gray-600 mb-2 uppercase">
                                <i class="fas fa-file-alt"></i> Описание проблемы:
                            </div>
                            <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($req['description'])); ?></p>
                        </div>
                        
                        <!-- Последний комментарий техника (если есть) -->
                        <?php
                        $stmt = $pdo->prepare("SELECT c.id, c.comment, c.created_at, c.updated_at FROM comments c WHERE c.request_id = ? AND c.user_id = ? ORDER BY c.created_at DESC LIMIT 1");
                        $stmt->execute([$req['id'], $user['id']]);
                        $lastComment = $stmt->fetch();
                        
                        if ($lastComment && $tab === 'in_progress'): ?>
                            <div class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-500 rounded-r-lg">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1">
                                        <div class="text-xs font-semibold text-blue-800 mb-1">
                                            <i class="fas fa-comment"></i> МОЙ КОММЕНТАРИЙ:
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($lastComment['comment'])); ?></p>
                                        <div class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-clock"></i> <?php echo date('d.m.Y H:i', strtotime($lastComment['created_at'])); ?>
                                            <?php if ($lastComment['updated_at']): ?>
                                                <span class="ml-2">(изменён: <?php echo date('d.m.Y H:i', strtotime($lastComment['updated_at'])); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button onclick='editComment(<?php echo $req['id']; ?>, <?php echo $lastComment['id']; ?>, <?php echo json_encode($lastComment['comment'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition flex-shrink-0">
                                        <i class="fas fa-edit"></i> Редактировать
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Кнопки действий -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <?php if ($tab === 'active'): ?>
                                <!-- Кнопка "Взять в работу" -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="action" value="take_to_work">
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold">
                                        <i class="fas fa-hand-paper"></i> Взять в работу
                                    </button>
                                </form>
                            <?php elseif ($tab === 'in_progress'): ?>
                                <!-- Кнопка "Добавить комментарий" -->
                                <button onclick="showCommentModal(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold" title="Добавить внутренний комментарий для себя или других техников">
                                    <i class="fas fa-comment"></i> Комментарий
                                </button>
                                
                                <!-- Кнопка "Отправить директору" (только если НЕ одобрено) -->
                                <?php if ($req['status'] !== 'approved'): ?>
                                    <button onclick="showDirectorModal(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold">
                                        <i class="fas fa-paper-plane"></i> На согласование
                                    </button>
                                <?php endif; ?>
                                
                                <!-- Кнопка "Завершить" -->
                                <button onclick="showCompleteModal(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold">
                                    <i class="fas fa-check-circle"></i> Завершить
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($tab === 'active' || $tab === 'in_progress'): ?>
                                <!-- Кнопка "Отклонить" -->
                                <button onclick="showRejectForm(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                    <i class="fas fa-times-circle"></i> Отклонить
                                </button>
                                
                                <!-- Установка срока (только если НЕ установлен) -->
                                <?php if (empty($req['deadline'])): ?>
                                    <form method="POST" class="inline flex gap-2">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="action" value="set_deadline">
                                        <input type="date" name="deadline" class="px-3 py-2 border rounded" required>
                                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                            <i class="fas fa-calendar-alt"></i> Установить срок
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- История действий (сворачиваемая) -->
                        <?php
                        // Получаем историю действий из request_logs
                        $stmt = $pdo->prepare("
                            SELECT rl.*, u.full_name, u.role 
                            FROM request_logs rl 
                            JOIN users u ON rl.user_id = u.id 
                            WHERE rl.request_id = ? 
                            ORDER BY rl.created_at ASC
                        ");
                        $stmt->execute([$req['id']]);
                        $logs = $stmt->fetchAll();
                        
                        if ($logs): ?>
                            <div class="mt-3 pt-3 border-t">
                                <button onclick="toggleHistory(<?php echo $req['id']; ?>)" class="text-xs font-semibold text-gray-600 uppercase hover:text-indigo-600 transition flex items-center gap-2">
                                    <i class="fas fa-history"></i> 
                                    <span>История действий (<?php echo count($logs); ?>)</span>
                                    <i id="history-icon-<?php echo $req['id']; ?>" class="fas fa-chevron-down text-xs"></i>
                                </button>
                                
                                <div id="history-<?php echo $req['id']; ?>" style="display: none;" class="mt-2">
                                    <?php foreach ($logs as $log): ?>
                                        <div class="text-sm text-gray-600 mb-1 flex items-start gap-2">
                                            <?php
                                            // Иконки для разных типов действий
                                            switch($log['action']) {
                                                case 'assigned':
                                                    $icon = '<i class="fas fa-user-check text-green-600"></i>';
                                                    break;
                                                case 'sent_for_approval':
                                                    $icon = '<i class="fas fa-paper-plane text-blue-600"></i>';
                                                    break;
                                                case 'sent_to_director':
                                                    $icon = '<i class="fas fa-user-tie text-purple-600"></i>';
                                                    break;
                                                case 'confirmed':
                                                    $icon = '<i class="fas fa-check-circle text-green-600"></i>';
                                                    break;
                                                case 'returned':
                                                    $icon = '<i class="fas fa-undo text-orange-600"></i>';
                                                    break;
                                                case 'rejected':
                                                    $icon = '<i class="fas fa-times-circle text-red-600"></i>';
                                                    break;
                                                case 'comment_added':
                                                    $icon = '<i class="fas fa-comment text-blue-600"></i>';
                                                    break;
                                                case 'deadline_set':
                                                    $icon = '<i class="fas fa-clock text-yellow-600"></i>';
                                                    break;
                                                default:
                                                    $icon = '<i class="fas fa-circle text-gray-400"></i>';
                                                    break;
                                            }
                                            echo $icon;
                                            ?>
                                            <span class="flex-1">
                                                <strong><?php echo date('d.m.Y H:i', strtotime($log['created_at'])); ?></strong>
                                                - <?php echo htmlspecialchars($log['comment']); ?>
                                                <span class="text-gray-500">(<?php echo htmlspecialchars($log['full_name']); ?>)</span>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Кнопка "Просмотр деталей заявки" -->
                                    <div class="mt-3 pt-2 border-t">
                                        <a href="view_request.php?id=<?php echo $req['id']; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm">
                                            <i class="fas fa-file-alt"></i> Просмотр деталей заявки
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Информация об одобрении директором -->
                        <?php if ($req['status'] === 'approved' && $req['approved_at']): ?>
                            <div class="mt-3 p-4 bg-green-50 border-l-4 border-green-500 rounded-r-lg">
                                <div class="flex items-center gap-2 text-green-800 font-semibold mb-2">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Одобрено директором</span>
                                </div>
                                <div class="text-sm text-gray-700">
                                    <i class="fas fa-clock mr-1"></i>
                                    <strong>Время одобрения:</strong> <?php echo date('d.m.Y H:i', strtotime($req['approved_at'])); ?>
                                </div>
                                <?php 
                                // Получить комментарий директора при одобрении
                                $stmt = $pdo->prepare("SELECT c.comment, c.created_at, u.full_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.request_id = ? AND u.role = 'director' ORDER BY c.created_at DESC LIMIT 1");
                                $stmt->execute([$req['id']]);
                                $directorComment = $stmt->fetch();
                                if ($directorComment && strpos($directorComment['comment'], 'отклонена') === false):
                                ?>
                                    <div class="mt-2 text-sm text-gray-700">
                                        <i class="fas fa-comment mr-1"></i>
                                        <strong>Комментарий директора:</strong> <?php echo htmlspecialchars($directorComment['comment']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($req['completed_at'] && $tab === 'archive'): ?>
                            <div class="mt-1 text-sm text-gray-500">
                                <i class="fas fa-check-circle text-green-600"></i> Завершена: <?php echo date('d.m.Y H:i', strtotime($req['completed_at'])); ?>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Модальное окно отклонения -->
    <div id="rejectModal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">Отклонить заявку</h3>
            <form method="POST">
                <input type="hidden" id="reject_request_id" name="request_id">
                <input type="hidden" name="action" value="reject">
                <textarea name="rejection_reason" rows="4" class="w-full px-3 py-2 border rounded mb-4" placeholder="Укажите причину отклонения..." required></textarea>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Отклонить</button>
                    <button type="button" onclick="closeRejectForm()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно комментария -->
    <div id="commentModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content">
            <h3 id="commentModalTitle" class="text-xl font-bold mb-2">
                <i class="fas fa-comment text-blue-600"></i>
                <span id="commentModalTitleText">Добавить внутренний комментарий</span>
            </h3>
            <p class="text-sm text-gray-600 mb-4">
                <i class="fas fa-info-circle text-blue-500"></i>
                Этот комментарий будет виден только системотехникам. Используйте для заметок о работе.
            </p>
            <form method="POST">
                <input type="hidden" id="comment_request_id" name="request_id">
                <input type="hidden" id="comment_id" name="comment_id" value="">
                <input type="hidden" id="comment_action" name="action" value="add_comment">
                <textarea id="comment_text" name="comment" rows="4" class="w-full px-3 py-2 border rounded mb-4" placeholder="Например: Нужно заказать запчасти, осталось переустановить драйвер..." required></textarea>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-paper-plane"></i> <span id="commentSubmitText">Отправить</span>
                    </button>
                    <button type="button" onclick="closeCommentModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно отправки директору -->
    <div id="directorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-paper-plane text-purple-600"></i>
                Отправить на согласование директору
            </h3>
            <form method="POST">
                <input type="hidden" id="director_request_id" name="request_id">
                <input type="hidden" name="action" value="send_to_director">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Комментарий (обязательно):</label>
                    <textarea name="comment" rows="4" class="w-full px-3 py-2 border rounded" placeholder="Опишите, что нужно согласовать. Например: 'Нужна покупка принтера HP LaserJet за 85 000₸'" required></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-check"></i> Отправить
                    </button>
                    <button type="button" onclick="closeDirectorModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно завершения -->
    <div id="completeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-check-circle text-green-600"></i>
                Завершить заявку
            </h3>
            <form method="POST">
                <input type="hidden" id="complete_request_id" name="request_id">
                <input type="hidden" name="action" value="complete">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Комментарий о выполненной работе (необязательно):</label>
                    <textarea name="comment" rows="4" class="w-full px-3 py-2 border rounded" placeholder="Опишите, что было сделано. Например: 'Заменил мышь, почистил клавиатуру'"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-check"></i> Завершить
                    </button>
                    <button type="button" onclick="closeCompleteModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Модальное окно отклонения
        function showRejectForm(requestId) {
            const modal = document.getElementById('rejectModal');
            document.getElementById('reject_request_id').value = requestId;
            modal.style.display = 'flex';
        }
        function closeRejectForm() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        // Модальное окно комментария
        function showCommentModal(requestId) {
            const modal = document.getElementById('commentModal');
            document.getElementById('comment_request_id').value = requestId;
            document.getElementById('comment_id').value = '';
            document.getElementById('comment_action').value = 'add_comment';
            document.getElementById('comment_text').value = '';
            document.getElementById('commentModalTitleText').textContent = 'Добавить внутренний комментарий';
            document.getElementById('commentSubmitText').textContent = 'Отправить';
            modal.style.display = 'flex';
        }
        
        function editComment(requestId, commentId, currentText) {
            const modal = document.getElementById('commentModal');
            document.getElementById('comment_request_id').value = requestId;
            document.getElementById('comment_id').value = commentId;
            document.getElementById('comment_action').value = 'edit_comment';
            document.getElementById('comment_text').value = currentText;
            document.getElementById('commentModalTitleText').textContent = 'Редактировать комментарий';
            document.getElementById('commentSubmitText').textContent = 'Сохранить';
            modal.style.display = 'flex';
        }
        
        function closeCommentModal() {
            document.getElementById('commentModal').style.display = 'none';
            document.getElementById('comment_text').value = '';
        }
        
        // Модальное окно отправки директору
        function showDirectorModal(requestId) {
            const modal = document.getElementById('directorModal');
            document.getElementById('director_request_id').value = requestId;
            modal.style.display = 'flex';
        }
        function closeDirectorModal() {
            document.getElementById('directorModal').style.display = 'none';
        }
        
        // Модальное окно завершения
        function showCompleteModal(requestId) {
            const modal = document.getElementById('completeModal');
            document.getElementById('complete_request_id').value = requestId;
            modal.style.display = 'flex';
        }
        function closeCompleteModal() {
            document.getElementById('completeModal').style.display = 'none';
        }
        
        // Функция сворачивания/разворачивания истории
        function toggleHistory(requestId) {
            const historyDiv = document.getElementById('history-' + requestId);
            const icon = document.getElementById('history-icon-' + requestId);
            
            if (historyDiv.style.display === 'none') {
                historyDiv.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                historyDiv.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }
    </script>
    
</body>
</html>