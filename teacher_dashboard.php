<?php
// teacher_dashboard.php - Панель преподавателя (ОБНОВЛЕННАЯ ВЕРСИЯ)

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('teacher');

$user = getCurrentUser();

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: teacher_dashboard.php');
    exit();
}

// Получение текущей вкладки
$tab = $_GET['tab'] ?? 'active';

// ОБХОД: если tab=approval, меняем на waiting для внутренней логики
$internalTab = ($tab === 'approval') ? 'waiting' : $tab;

// Обработка подтверждения/отклонения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    
    if ($action === 'confirm') {
        // Учитель подтверждает работу (старый метод - совместимость)
        $feedback = $_POST['feedback'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'completed', confirmed_at = NOW(), confirmed_by = ?, teacher_feedback = ?, completed_at = NOW() WHERE id = ? AND created_by = ?");
        $stmt->execute([$user['id'], $feedback, $requestId, $user['id']]);
        
        // Комментарий с отзывом
        if ($feedback) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], 'Отзыв преподавателя: ' . $feedback]);
        }
        
        // Лог
        $logComment = $feedback ? 'Работа принята преподавателем. Отзыв: ' . $feedback : 'Работа принята преподавателем';
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'confirmed', 'awaiting_approval', 'completed', ?)");
        $stmt->execute([$requestId, $user['id'], $logComment]);
    } elseif ($action === 'reject_completion') {
        // Учитель возвращает в работу (старый метод)
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', completed_at = NULL, rejection_reason = ? WHERE id = ? AND created_by = ?");
        $stmt->execute([$reason, $requestId, $user['id']]);
        
        // Добавить комментарий
        if ($reason) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], 'Возврат на доработку: ' . $reason]);
        }
        
        // Лог
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'returned', 'awaiting_approval', 'in_progress', ?)");
        $stmt->execute([$requestId, $user['id'], 'Возврат на доработку: ' . $reason]);
    } elseif ($action === 'confirm_work') {
        // Преподаватель принимает работу (новый метод с логированием)
        $stmt = $pdo->prepare("UPDATE requests SET status = 'completed', confirmed_at = NOW(), confirmed_by = ?, completed_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $requestId]);
        
        // Добавить комментарий если есть
        $comment = $_POST['comment'] ?? '';
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], 'Отзыв преподавателя: ' . $comment]);
        }
        
        // Лог
        $logComment = $comment ? 'Работа принята преподавателем. Отзыв: ' . $comment : 'Работа принята преподавателем';
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'confirmed', 'awaiting_approval', 'completed', ?)");
        $stmt->execute([$requestId, $user['id'], $logComment]);
        
    } elseif ($action === 'reject_work') {
        // Преподаватель возвращает на доработку (новый метод с логированием)
        $reason = $_POST['reason'] ?? 'Требуется доработка';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', teacher_feedback = ? WHERE id = ?");
        $stmt->execute([$reason, $requestId]);
        
        // Комментарий
        $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$requestId, $user['id'], 'Возврат на доработку: ' . $reason]);
        
        // Лог
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'returned', 'awaiting_approval', 'in_progress', ?)");
        $stmt->execute([$requestId, $user['id'], 'Возврат на доработку: ' . $reason]);
    }
    
    header('Location: teacher_dashboard.php?tab=' . $tab);
    exit();
}

// Получение заявок в зависимости от вкладки
if ($internalTab === 'pending') {
    // Заявки ожидающие одобрения (pending, approved - ещё не взяты техником)
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE created_by = ? AND status IN ('pending', 'approved') ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
} elseif ($internalTab === 'waiting') {
    // Заявки ожидающие подтверждения от преподавателя
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as tech_name FROM requests r LEFT JOIN users u ON r.assigned_to = u.id WHERE r.created_by = ? AND r.status = 'awaiting_approval' AND r.sent_to_director = 0 ORDER BY r.approval_requested_at DESC");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
} elseif ($internalTab === 'archive') {
    // Архив
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as tech_name, DATEDIFF(r.confirmed_at, r.created_at) as days_to_complete FROM requests r LEFT JOIN users u ON r.assigned_to = u.id WHERE r.created_by = ? AND r.status = 'completed' ORDER BY r.confirmed_at DESC");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
} else {
    // Активные заявки (в работе)
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as tech_name FROM requests r LEFT JOIN users u ON r.assigned_to = u.id WHERE r.created_by = ? AND r.status = 'in_progress' ORDER BY r.created_at DESC");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
}

// Получаем счетчики для вкладок
// В работе
$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status = 'in_progress'");
$stmt->execute([$user['id']]);
$activeCount = $stmt->fetchColumn();

// Ожидают одобрения
$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status IN ('pending', 'approved')");
$stmt->execute([$user['id']]);
$pendingCount = $stmt->fetchColumn();

// Ожидают подтверждения
$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status = 'awaiting_approval' AND sent_to_director = 0");
$stmt->execute([$user['id']]);
$waitingCount = $stmt->fetchColumn();

// Архив
$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$archiveCount = $stmt->fetchColumn();

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
        }
        .tab-badge {
            background: #ef4444;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 6px;
        }
    </style>
    <script>
        function showConfirmModal(requestId) {
            document.getElementById('confirmModal').style.display = 'flex';
            document.getElementById('confirm_request_id').value = requestId;
        }
        function showRejectModal(requestId) {
            document.getElementById('rejectModal').style.display = 'flex';
            document.getElementById('reject_request_id').value = requestId;
        }
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
            document.getElementById('rejectModal').style.display = 'none';
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
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-chalkboard-teacher text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600"><?php echo !empty($user['position']) ? htmlspecialchars($user['position']) : t('teacher'); ?>: <?php echo htmlspecialchars($user['full_name']); ?></p>
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
        
        <!-- Основная вкладка -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-4">
                    <a href="?tab=active" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 border-indigo-600 text-indigo-600">
                        <i class="fas fa-file-alt"></i> Заявки
                        <?php $totalActive = $activeCount + $pendingCount + $waitingCount; if ($totalActive > 0): ?>
                            <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded-full text-xs"><?php echo $totalActive; ?></span>
                        <?php endif; ?>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Под-вкладки - ВСЕГДА показываются -->
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="flex items-center justify-between p-4 border-b">
                <nav class="flex gap-2 flex-wrap">
                    <!-- DEBUG: Если эти кнопки исчезают - значит используется СТАРЫЙ файл. В правильном файле этот блок НЕ обёрнут в условие if -->
                    
                    <!-- КНОПКА 1 -->
                    <a href="?tab=active" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'active' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                        <i class="fas fa-spinner"></i> В работе
                        <span class="px-2 py-0.5 <?php echo $tab === 'active' ? 'bg-white/20' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs"><?php echo $activeCount; ?></span>
                    </a>
                    
                    <!-- КНОПКА 2 -->
                    <a href="?tab=pending" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'pending' ? 'bg-yellow-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                        <i class="fas fa-hourglass-half"></i> Ожидают одобрения
                        <span class="px-2 py-0.5 <?php echo $tab === 'pending' ? 'bg-white/20' : 'bg-yellow-100 text-yellow-800'; ?> rounded-full text-xs"><?php echo $pendingCount; ?></span>
                    </a>
                    
                    <!-- КНОПКА 3 -->
                    <a href="?tab=approval" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'approval' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                        <i class="fas fa-check-double"></i> Ожидают подтверждения
                        <span class="px-2 py-0.5 <?php echo $tab === 'approval' ? 'bg-white/20' : 'bg-orange-100 text-orange-800'; ?> rounded-full text-xs"><?php echo $waitingCount; ?></span>
                    </a>
                    
                    <!-- КНОПКА 4 -->
                    <a href="?tab=archive" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'archive' ? 'bg-gray-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                        <i class="fas fa-archive"></i> Архив
                        <span class="px-2 py-0.5 <?php echo $tab === 'archive' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'; ?> rounded-full text-xs"><?php echo $archiveCount; ?></span>
                    </a>
                </nav>
                <a href="create_request.php" class="flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition text-sm">
                    <i class="fas fa-plus"></i> <?php echo t('create_request'); ?>
                </a>
            </div>
        </div>
        
        <!-- Список заявок -->
        <div class="space-y-4">
            <?php if (empty($requests)): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">
                        <?php 
                        if ($tab === 'pending') echo 'Нет заявок ожидающих одобрения';
                        elseif ($tab === 'approval') echo 'Нет заявок ожидающих подтверждения';
                        elseif ($tab === 'archive') echo 'Архив пуст';
                        else echo 'Нет заявок в работе';
                        ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $req): 
                    $statusColors = [
                        'new' => 'bg-blue-100 text-blue-800',
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'approved' => 'bg-green-100 text-green-800',
                        'rejected' => 'bg-red-100 text-red-800',
                        'in_progress' => 'bg-purple-100 text-purple-800',
                        'completed' => 'bg-gray-100 text-gray-800',
                        'waiting_confirmation' => 'bg-orange-100 text-orange-800'
                    ];
                    $typeColors = [
                        'repair' => 'border-red-200',
                        'software' => 'border-blue-200',
                        '1c_database' => 'border-purple-200',
                        'general_question' => 'border-green-200'
                    ];
                    
                    // Получаем приоритет
                    $priority = $req['priority'] ?? 'normal';
                ?>
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition p-6 border-l-4 <?php 
                        if ($priority === 'urgent') echo 'border-red-500';
                        elseif ($priority === 'high') echo 'border-orange-500';
                        elseif ($priority === 'normal') echo 'border-blue-500';
                        else echo 'border-gray-300';
                    ?>">
                        
                        <!-- Заголовок заявки -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <!-- Приоритет и Категория -->
                                <div class="mb-3">
                                    <!-- Приоритет -->
                                    <div class="mb-2">
                                        <span class="text-xs text-gray-500 uppercase font-semibold">Приоритет:</span>
                                        <div class="mt-1">
                                            <span class="priority-badge priority-<?php echo $priority; ?>">
                                                <i class="fas <?php echo getPriorityIcon($priority); ?>"></i>
                                                <?php echo getPriorityText($priority); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Категория / Тип заявки -->
                                    <div>
                                        <span class="text-xs text-gray-500 uppercase font-semibold">Категория:</span>
                                        <div class="mt-1">
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
                                            <?php elseif ($req['request_type'] === 'general_question'): ?>
                                                <span class="px-3 py-1 bg-teal-100 text-teal-800 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-question-circle"></i> ОБЩИЕ ВОПРОСЫ / КОНСУЛЬТАЦИЯ
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 text-sm text-gray-600">
                                    <span><i class="fas fa-door-open text-indigo-600"></i> <span class="text-xs text-gray-500">Кабинет:</span> <strong><?php echo htmlspecialchars($req['cabinet']); ?></strong></span>
                                    <span><i class="fas fa-user text-gray-500"></i> <?php echo htmlspecialchars($user['full_name']); ?></span>
                                    <span><i class="fas fa-calendar text-gray-500"></i> <span class="text-xs text-gray-500">Создана:</span> <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?></span>
                                    
                                    <?php if ($req['deadline'] && $req['status'] !== 'completed'): ?>
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
                            
                            <!-- ID заявки и статус -->
                            <div class="text-right">
                                <span class="text-xs text-gray-500">Заявка №</span>
                                <span class="text-lg font-bold text-gray-800"><?php echo $req['id']; ?></span>
                                <div class="mt-1">
                                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusColors[$req['status']]; ?>">
                                        <?php echo t($req['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Описание проблемы -->
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                            <div class="text-xs font-semibold text-gray-600 mb-2 uppercase">
                                <i class="fas fa-file-alt"></i> Описание проблемы:
                            </div>
                            <?php 
                            // Выбираем правильное поле в зависимости от типа заявки
                            $displayText = '';
                            
                            if ($req['request_type'] === 'repair') {
                                $displayText = $req['description'] ?? '';
                            } elseif ($req['request_type'] === 'software') {
                                // Для заявок на ПО показываем software_list
                                $displayText = $req['software_list'] ?? '';
                            } elseif ($req['request_type'] === '1c_database') {
                                $displayText = $req['database_purpose'] ?? '';
                            } elseif ($req['request_type'] === 'general_question') {
                                $displayText = $req['question_description'] ?? '';
                            }
                            
                            if (empty(trim($displayText))) {
                                $displayText = 'Описание не указано';
                            }
                            ?>
                            <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($displayText)); ?></p>
                        </div>
                        
                        <!-- Дополнительная информация для заявок на ПО -->
                        <?php if ($req['request_type'] === 'software'): ?>
                            <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php if (!empty($req['computer_inventory'])): ?>
                                    <div class="p-3 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                                        <div class="text-xs font-semibold text-blue-800 mb-1 uppercase">
                                            <i class="fas fa-desktop"></i> Инвентарный номер ПК:
                                        </div>
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($req['computer_inventory']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($req['justification'])): ?>
                                    <div class="p-3 bg-purple-50 rounded-lg border-l-4 border-purple-500">
                                        <div class="text-xs font-semibold text-purple-800 mb-1 uppercase">
                                            <i class="fas fa-info-circle"></i> Обоснование:
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($req['justification'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Дополнительная информация для заявок на ремонт -->
                        <?php if ($req['request_type'] === 'repair' && !empty($req['equipment_type'])): ?>
                            <div class="mb-4 p-3 bg-orange-50 rounded-lg border-l-4 border-orange-500">
                                <div class="text-xs font-semibold text-orange-800 mb-1 uppercase">
                                    <i class="fas fa-tools"></i> Тип оборудования:
                                </div>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($req['equipment_type']); ?></p>
                                <?php if (!empty($req['inventory_number'])): ?>
                                    <div class="text-xs text-gray-600 mt-2">
                                        <strong>Инв. номер:</strong> <?php echo htmlspecialchars($req['inventory_number']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Дополнительная информация для заявок на БД 1С -->
                        <?php if ($req['request_type'] === '1c_database'): ?>
                            <div class="mb-4">
                                <?php if (!empty($req['group_number'])): ?>
                                    <div class="p-3 bg-green-50 rounded-lg border-l-4 border-green-500 mb-3">
                                        <div class="text-xs font-semibold text-green-800 mb-1 uppercase">
                                            <i class="fas fa-users"></i> Номер группы:
                                        </div>
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($req['group_number']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($req['students_list'])): ?>
                                    <div class="p-3 bg-indigo-50 rounded-lg border-l-4 border-indigo-500">
                                        <div class="text-xs font-semibold text-indigo-800 mb-1 uppercase">
                                            <i class="fas fa-list"></i> Список студентов:
                                        </div>
                                        <?php 
                                        $students = json_decode($req['students_list'], true);
                                        if (is_array($students) && count($students) > 0):
                                        ?>
                                            <ul class="text-sm text-gray-700 list-disc list-inside">
                                                <?php foreach ($students as $student): ?>
                                                    <li><?php echo htmlspecialchars($student); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-500">Список не указан</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Дополнительная информация для общих вопросов -->
                        <?php if ($req['request_type'] === 'general_question' && !empty($req['software_or_system'])): ?>
                            <div class="mb-4 p-3 bg-teal-50 rounded-lg border-l-4 border-teal-500">
                                <div class="text-xs font-semibold text-teal-800 mb-1 uppercase">
                                    <i class="fas fa-cog"></i> ПО / Система:
                                </div>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($req['software_or_system']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- КНОПКИ (только для вкладки "Ожидают подтверждения") -->
                        <?php if ($tab === 'approval'): ?>
                            <div class="flex gap-3 mb-4">
                                <button onclick="showConfirmModal(<?php echo $req['id']; ?>)" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2">
                                    <i class="fas fa-check-circle"></i>
                                    Принять работу
                                </button>
                                <button onclick="showRejectModal(<?php echo $req['id']; ?>)" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition flex items-center gap-2">
                                    <i class="fas fa-undo"></i>
                                    Вернуть в работу
                                </button>
                            </div>
                        <?php endif; ?>
                        
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
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($tab === 'approval' && !empty($req['completion_note'])): ?>
                            <div class="mt-3 p-3 bg-blue-50 border-l-4 border-blue-500 rounded-r-lg">
                                <p class="text-sm text-gray-700">
                                    <strong><i class="fas fa-comment mr-1"></i>Примечание системотехника:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($req['completion_note'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($tab === 'archive' && !empty($req['teacher_feedback'])): ?>
                            <div class="mt-3 p-3 bg-indigo-50 border-l-4 border-indigo-500 rounded-r-lg">
                                <strong class="text-indigo-800"><i class="fas fa-star mr-1"></i>Ваш отзыв:</strong><br>
                                <p class="text-sm text-gray-700 mt-1"><?php echo nl2br(htmlspecialchars($req['teacher_feedback'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>
    
    <!-- Модальное окно подтверждения -->
    <div id="confirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; padding:30px; border-radius:12px; max-width:500px; width:90%;">
            <h3 class="text-xl font-bold mb-4 text-green-600">
                <i class="fas fa-check-circle mr-2"></i>
                Подтвердить выполнение работы?
            </h3>
            <form method="POST">
                <input type="hidden" name="request_id" id="confirm_request_id">
                <input type="hidden" name="action" value="confirm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Отзыв о работе (необязательно):</label>
                    <textarea name="feedback" class="w-full px-4 py-2 border rounded-lg" rows="3" placeholder="Напишите отзыв о качестве выполненной работы..."></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                        <i class="fas fa-check mr-1"></i>
                        Подтвердить
                    </button>
                    <button type="button" onclick="closeModal()" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно возврата в работу -->
    <div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; padding:30px; border-radius:12px; max-width:500px; width:90%;">
            <h3 class="text-xl font-bold mb-4 text-red-600">
                <i class="fas fa-undo mr-2"></i>
                Вернуть заявку в работу
            </h3>
            <form method="POST">
                <input type="hidden" name="request_id" id="reject_request_id">
                <input type="hidden" name="action" value="reject_completion">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Причина возврата: *</label>
                    <textarea name="rejection_reason" class="w-full px-4 py-2 border rounded-lg" rows="3" placeholder="Укажите что нужно исправить..." required></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-undo mr-1"></i>
                        Вернуть в работу
                    </button>
                    <button type="button" onclick="closeModal()" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
</body>
</html>