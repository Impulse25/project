<?php
// technician_dashboard.php - Панель системного техника

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('technician');

$user = getCurrentUser();

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
        $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', assigned_to = ?, started_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $requestId]);
    } elseif ($action === 'complete') {
        $comment = $_POST['comment'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
        
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
        }
    } elseif ($action === 'add_comment') {
        $comment = $_POST['comment'] ?? '';
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
        }
    } elseif ($action === 'send_to_director') {
        // НОВОЕ: Отправка на согласование директору
        $comment = $_POST['comment'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'pending', sent_to_director = 1, sent_to_director_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
        
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
        }
    } elseif ($action === 'reject') {
        // НОВОЕ: Отклонение заявки
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $requestId]);
        
        if ($reason) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], 'Заявка отклонена. Причина: ' . $reason]);
        }
    } elseif ($action === 'set_deadline') {
        // НОВОЕ: Установка срока выполнения
        $deadline = $_POST['deadline'] ?? '';
        if ($deadline) {
            $stmt = $pdo->prepare("UPDATE requests SET deadline = ?, deadline_set_by = ? WHERE id = ?");
            $stmt->execute([$deadline, $user['id'], $requestId]);
        }
    }
    
    header('Location: technician_dashboard.php?tab=' . $tab);
    exit();
}

// Получение активных заявок (включая новые, одобренные и в работе)
// С СОРТИРОВКОЙ ПО ПРИОРИТЕТУ: urgent > high > normal > low
$stmt = $pdo->query("
    SELECT r.*, u.full_name as creator_name,
    FIELD(r.priority, 'urgent', 'high', 'normal', 'low') as priority_order
    FROM requests r 
    JOIN users u ON r.created_by = u.id 
    WHERE r.status IN ('new', 'approved', 'in_progress') 
    ORDER BY priority_order ASC, r.created_at ASC
");
$activeRequests = $stmt->fetchAll();

// Получение завершенных заявок (архив)
$stmt = $pdo->query("SELECT r.*, u.full_name as creator_name FROM requests r JOIN users u ON r.created_by = u.id WHERE r.status = 'completed' ORDER BY r.completed_at DESC LIMIT 50");
$completedRequests = $stmt->fetchAll();

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
    <script>
        function showRejectForm(requestId) {
            const modal = document.getElementById('rejectModal');
            document.getElementById('reject_request_id').value = requestId;
            modal.style.display = 'flex';
        }
        function closeRejectForm() {
            document.getElementById('rejectModal').style.display = 'none';
        }
    </script>
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
                        Активные заявки (<?php echo count($activeRequests); ?>)
                    </a>
                    <a href="?tab=archive" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'archive' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-archive"></i>
                        Архив (<?php echo count($completedRequests); ?>)
                    </a>
                </nav>
            </div>
        </div>
        
        <?php if ($tab === 'active'): ?>
            <!-- Активные заявки -->
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-800"><?php echo t('approved_requests'); ?></h2>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <i class="fas fa-sort-amount-down"></i>
                    <span>Сортировка по приоритету</span>
                </div>
            </div>
            
            <div class="space-y-4">
                <?php if (empty($activeRequests)): ?>
                    <div class="bg-gray-50 rounded-lg p-12 text-center">
                        <i class="fas fa-check-circle text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600"><?php echo t('no_active_requests'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeRequests as $req): 
                        $typeColors = [
                            'repair' => 'border-red-200',
                            'software' => 'border-blue-200',
                            '1c_database' => 'border-purple-200'
                        ];
                        $statusColors = [
                            'new' => 'bg-blue-100 text-blue-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'in_progress' => 'bg-yellow-100 text-yellow-800'
                        ];
                        
                        $students = $req['students_list'] ? json_decode($req['students_list'], true) : [];
                        
                        // Получаем данные о приоритете
                        $priority = $req['priority'] ?? 'normal';
                        $priorityColor = getPriorityColor($priority);
                        $priorityIcon = getPriorityIcon($priority);
                        $priorityText = getPriorityText($priority);
                    ?>
                        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $typeColors[$req['request_type']]; ?>">
                            <div class="mb-4">
                                <div class="flex items-center gap-3 mb-2 flex-wrap">
                                    <!-- ПРИОРИТЕТ (главный индикатор) -->
                                    <span class="priority-badge <?php echo $priorityColor; ?>">
                                        <i class="fas <?php echo $priorityIcon; ?>"></i>
                                        <?php echo $priorityText; ?>
                                    </span>
                                    
                                    <span class="text-xs font-semibold text-indigo-600 uppercase"><?php echo t($req['request_type']); ?></span>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusColors[$req['status']]; ?>">
                                        <?php echo t($req['status']); ?>
                                    </span>
                                    <?php if ($req['deadline']): ?>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            Срок: <?php echo date('d.m.Y', strtotime($req['deadline'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <?php echo t('cabinet'); ?>: <?php echo $req['cabinet']; ?>
                                    <?php 
                                    if ($req['request_type'] === 'repair') {
                                        echo ' - ' . $req['equipment_type'];
                                    } elseif ($req['request_type'] === '1c_database') {
                                        echo ' - ' . ($req['database_type'] ?? 'База данных');
                                    }
                                    ?>
                                </h3>
                                <p class="text-gray-600 mt-2">
                                    <?php 
                                    if ($req['request_type'] === 'repair') {
                                        echo $req['problem_description'];
                                    } elseif ($req['request_type'] === 'software') {
                                        echo $req['software_name'] . ' - ' . $req['justification'];
                                    } else {
                                        echo $req['database_purpose'];
                                    }
                                    ?>
                                </p>
                                <div class="text-sm text-gray-500 mt-2">
                                    <i class="fas fa-user mr-1"></i><?php echo $req['creator_name']; ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-clock mr-1"></i><?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?>
                                </div>
                                
                                <?php if (!empty($students)): ?>
                                    <div class="mt-2">
                                        <p class="font-medium"><?php echo t('students_list'); ?> (<?php echo count($students); ?>):</p>
                                        <ul class="list-disc list-inside ml-2 mt-1">
                                            <?php foreach ($students as $student): ?>
                                                <li><?php echo $student; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- ФОРМА 1: Установка срока выполнения -->
                            <form method="POST" class="border-t pt-4">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                
                                <div class="deadline-input-group">
                                    <label class="text-sm font-medium text-gray-700 whitespace-nowrap">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        Срок выполнения:
                                    </label>
                                    <input 
                                        type="date" 
                                        name="deadline" 
                                        value="<?php echo $req['deadline'] ?? ''; ?>" 
                                        min="<?php echo date('Y-m-d'); ?>"
                                    >
                                    <button type="submit" name="action" value="set_deadline">
                                        <i class="fas fa-check mr-1"></i>
                                        <?php echo $req['deadline'] ? 'Изменить' : 'Установить'; ?>
                                    </button>
                                </div>
                            </form>
                            
                            <!-- ФОРМА 2: Действия с заявкой -->
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <textarea name="comment" class="w-full px-4 py-2 border rounded-lg mb-3" rows="2" placeholder="<?php echo t('comment'); ?>..."></textarea>
                                
                                <div class="flex gap-3 flex-wrap">
                                    <?php if ($req['status'] === 'new'): ?>
                                        <button type="submit" name="action" value="take_to_work" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                            <i class="fas fa-play mr-1"></i>
                                            <?php echo t('take_to_work'); ?>
                                        </button>
                                        <button type="submit" name="action" value="send_to_director" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                                            <i class="fas fa-user-tie mr-1"></i>
                                            На согласование
                                        </button>
                                        <button type="button" onclick="showRejectForm(<?php echo $req['id']; ?>)" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                                            <i class="fas fa-times-circle mr-1"></i>
                                            Отклонить
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['status'] === 'approved'): ?>
                                        <button type="submit" name="action" value="take_to_work" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                            <i class="fas fa-play mr-1"></i>
                                            <?php echo t('take_to_work'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['status'] === 'in_progress'): ?>
                                        <button type="submit" name="action" value="complete" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                            <i class="fas fa-check mr-1"></i>
                                            <?php echo t('complete_work'); ?>
                                        </button>
                                        <button type="submit" name="action" value="send_to_director" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                                            <i class="fas fa-user-tie mr-1"></i>
                                            На согласование
                                        </button>
                                        <button type="button" onclick="showRejectForm(<?php echo $req['id']; ?>)" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                                            <i class="fas fa-times-circle mr-1"></i>
                                            Отклонить
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="submit" name="action" value="add_comment" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition">
                                        <i class="fas fa-comment mr-1"></i>
                                        <?php echo t('add_comment'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- Архив завершенных заявок -->
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-archive mr-2"></i>
                Архив завершенных заявок
            </h2>
            
            <?php if (empty($completedRequests)): ?>
                <div class="bg-gray-50 rounded-lg p-12 text-center">
                    <i class="fas fa-archive text-6xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600">Архив пуст</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Кабинет</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Описание</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Завершено</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($completedRequests as $req): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $req['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo t($req['request_type']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $req['cabinet']; ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php 
                                        if ($req['request_type'] === 'repair') {
                                            echo $req['problem_description'];
                                        } elseif ($req['request_type'] === 'software') {
                                            echo $req['software_name'];
                                        } else {
                                            echo $req['database_purpose'];
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('d.m.Y', strtotime($req['completed_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Модальное окно для отклонения -->
    <div id="rejectModal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4 text-red-600">
                <i class="fas fa-times-circle mr-2"></i>
                Отклонить заявку
            </h3>
            <form method="POST">
                <input type="hidden" name="request_id" id="reject_request_id">
                <textarea name="rejection_reason" class="w-full px-4 py-2 border rounded-lg mb-4" rows="4" placeholder="Укажите причину отклонения..." required></textarea>
                <div class="flex gap-3">
                    <button type="submit" name="action" value="reject" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-times mr-1"></i>
                        Отклонить
                    </button>
                    <button type="button" onclick="closeRejectForm()" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
</body>
</html>
