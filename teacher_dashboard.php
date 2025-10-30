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

// Обработка подтверждения/отклонения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    
    if ($action === 'confirm') {
        // Учитель подтверждает работу
        $feedback = $_POST['feedback'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'completed', confirmed_at = NOW(), confirmed_by = ?, teacher_feedback = ?, is_archived = 1 WHERE id = ? AND created_by = ?");
        $stmt->execute([$user['id'], $feedback, $requestId, $user['id']]);
    } elseif ($action === 'reject_completion') {
        // Учитель возвращает в работу
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', completed_at = NULL, rejection_reason = ? WHERE id = ? AND created_by = ?");
        $stmt->execute([$reason, $requestId, $user['id']]);
    }
    
    header('Location: teacher_dashboard.php?tab=' . $tab);
    exit();
}

// Получение заявок в зависимости от вкладки
if ($tab === 'waiting') {
    // Заявки ожидающие подтверждения
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as tech_name FROM requests r LEFT JOIN users u ON r.assigned_to = u.id WHERE r.created_by = ? AND r.status = 'waiting_confirmation' ORDER BY r.completed_at DESC");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
} elseif ($tab === 'archive') {
    // Архив
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as tech_name, DATEDIFF(r.confirmed_at, r.created_at) as days_to_complete FROM requests r LEFT JOIN users u ON r.assigned_to = u.id WHERE r.created_by = ? AND r.is_archived = 1 ORDER BY r.confirmed_at DESC");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
} else {
    // Активные заявки
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE created_by = ? AND is_archived = 0 AND status != 'waiting_confirmation' ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
}

// Получаем счетчики для вкладок
$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND is_archived = 0 AND status != 'waiting_confirmation'");
$stmt->execute([$user['id']]);
$activeCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND status = 'waiting_confirmation'");
$stmt->execute([$user['id']]);
$waitingCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by = ? AND is_archived = 1");
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
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
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
                    <p class="text-sm text-gray-600"><?php echo t('teacher'); ?>: <?php echo $user['full_name']; ?></p>
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
        
        <!-- НОВЫЕ ВКЛАДКИ -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-4">
                    <a href="?tab=active" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'active' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-tasks"></i>
                        <?php echo t('my_requests'); ?>
                        <span class="tab-badge"><?php echo $activeCount; ?></span>
                    </a>
                    <a href="?tab=waiting" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'waiting' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-clock"></i>
                        Ожидают подтверждения
                        <?php if ($waitingCount > 0): ?>
                            <span class="tab-badge animate-pulse"><?php echo $waitingCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?tab=archive" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $tab === 'archive' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-archive"></i>
                        Архив
                        <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded-full text-xs"><?php echo $archiveCount; ?></span>
                    </a>
                </nav>
            </div>
        </div>
        
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <?php 
                if ($tab === 'waiting') echo 'Ожидают вашего подтверждения';
                elseif ($tab === 'archive') echo 'Архив завершенных заявок';
                else echo t('my_requests');
                ?>
            </h2>
            <?php if ($tab === 'active'): ?>
                <a href="create_request.php" class="flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-plus"></i>
                    <?php echo t('create_request'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Список заявок -->
        <div class="space-y-4">
            <?php if (empty($requests)): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">
                        <?php 
                        if ($tab === 'waiting') echo 'Нет заявок ожидающих подтверждения';
                        elseif ($tab === 'archive') echo 'Архив пуст';
                        else echo t('no_requests');
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
                        '1c_database' => 'border-purple-200'
                    ];
                    
                    // Получаем приоритет
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
                                        echo $req['problem_description'] ?? '';
                                    } elseif ($req['request_type'] === 'software') {
                                        echo $req['justification'] ?? '';
                                    } else {
                                        echo $req['database_purpose'] ?? '';
                                    }
                                    ?>
                                </p>
                                
                                <?php if ($tab === 'waiting' && !empty($req['completion_note'])): ?>
                                    <div class="mt-2 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                        <p class="text-sm text-gray-700">
                                            <strong><i class="fas fa-comment mr-1"></i>Примечание системотехника:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($req['completion_note'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($tab === 'waiting' && !empty($req['tech_name'])): ?>
                                    <div class="mt-2 text-sm text-gray-600">
                                        <i class="fas fa-user-cog mr-1"></i>
                                        <strong>Выполнил:</strong> <?php echo $req['tech_name']; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($tab === 'archive'): ?>
                                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                        <?php if (!empty($req['tech_name'])): ?>
                                            <div>
                                                <i class="fas fa-user-cog text-gray-500 mr-1"></i>
                                                <strong>Выполнил:</strong> <?php echo $req['tech_name']; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($req['days_to_complete'])): ?>
                                            <div>
                                                <i class="fas fa-clock text-gray-500 mr-1"></i>
                                                <strong>Срок выполнения:</strong> <?php echo $req['days_to_complete']; ?> дн.
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($req['teacher_feedback'])): ?>
                                            <div class="col-span-2 p-2 bg-green-50 rounded border border-green-200">
                                                <strong class="text-green-800">Ваш отзыв:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($req['teacher_feedback'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusColors[$req['status']]; ?>">
                                <?php echo t($req['status']); ?>
                            </span>
                        </div>
                        
                        <!-- КНОПКИ ПОДТВЕРЖДЕНИЯ (только для вкладки "Ожидают подтверждения") -->
                        <?php if ($tab === 'waiting'): ?>
                            <div class="flex gap-3 pt-4 border-t">
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
                        
                        <div class="flex justify-between items-center text-sm text-gray-500 pt-3 <?php echo $tab === 'waiting' ? '' : 'border-t'; ?>">
                            <span>
                                <i class="fas fa-clock mr-1"></i>
                                <?php 
                                if ($tab === 'archive') {
                                    echo 'Завершено: ' . date('d.m.Y H:i', strtotime($req['confirmed_at']));
                                } elseif ($tab === 'waiting') {
                                    echo 'Завершено системотехником: ' . date('d.m.Y H:i', strtotime($req['completed_at']));
                                } else {
                                    echo t('created') . ': ' . date('d.m.Y H:i', strtotime($req['created_at']));
                                }
                                ?>
                            </span>
                            <?php if ($tab !== 'waiting'): ?>
                                <a href="view_request.php?id=<?php echo $req['id']; ?>" class="text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                                    <i class="fas fa-eye"></i>
                                    <?php echo t('details'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
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