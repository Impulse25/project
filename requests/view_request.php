<?php
// view_request.php - Просмотр заявки

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireLogin();

$user = getCurrentUser();
$requestId = $_GET['id'] ?? null;

if (!$requestId) {
    header('Location: index.php');
    exit();
}

// Получение заявки
$stmt = $pdo->prepare("
    SELECT r.*, 
           creator.full_name as creator_name,
           creator.position as creator_position,
           tech.full_name as tech_name,
           approver.full_name as approver_name
    FROM requests r
    LEFT JOIN users creator ON r.created_by = creator.id
    LEFT JOIN users tech ON r.assigned_to = tech.id
    LEFT JOIN users approver ON r.approved_by = approver.id
    WHERE r.id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: index.php');
    exit();
}

// Проверка прав доступа
$canView = false;
if ($user['role'] === 'director' || $user['role'] === 'technician') {
    $canView = true;
} elseif ($user['role'] === 'teacher' && $request['created_by'] == $user['id']) {
    $canView = true;
}

if (!$canView) {
    header('Location: index.php');
    exit();
}

// Получение комментариев
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name, u.role
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.request_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$requestId]);
$comments = $stmt->fetchAll();

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

function getRoleColor($role) {
    $colors = [
        'teacher' => 'border-indigo-500 bg-indigo-50',
        'technician' => 'border-blue-500 bg-blue-50',
        'director' => 'border-purple-500 bg-purple-50'
    ];
    return $colors[$role] ?? 'border-gray-500 bg-gray-50';
}

function getRoleIcon($role) {
    $icons = [
        'teacher' => 'fa-chalkboard-teacher text-indigo-600',
        'technician' => 'fa-tools text-blue-600',
        'director' => 'fa-user-tie text-purple-600'
    ];
    return $icons[$role] ?? 'fa-user text-gray-600';
}

function getRoleName($role) {
    $names = [
        'teacher' => 'Преподаватель',
        'technician' => 'Системотехник',
        'director' => 'Директор'
    ];
    return $names[$role] ?? 'Пользователь';
}

$currentLang = getCurrentLanguage();
$priority = $request['priority'] ?? 'normal';
$students = $request['students_list'] ? json_decode($request['students_list'], true) : [];
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявка #<?php echo $request['id']; ?> - <?php echo t('system_name'); ?></title>
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
        .timeline-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 30px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 30px;
            bottom: -10px;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-item:last-child:before {
            display: none;
        }
        .timeline-dot {
            position: absolute;
            left: 0;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            border: 3px solid #3b82f6;
        }
        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-label {
            font-weight: 600;
            color: #4b5563;
        }
        .info-value {
            color: #1f2937;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b no-print">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-file-alt text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Заявка #<?php echo $request['id']; ?></h1>
                    <p class="text-sm text-gray-600"><?php echo $user['full_name']; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    <i class="fas fa-print mr-2"></i>
                    Печать
                </button>
                <a href="<?php 
                    if ($user['role'] === 'teacher') {
                        echo 'teacher_dashboard.php';
                    } elseif ($user['role'] === 'technician') {
                        echo 'technician_dashboard.php';
                    } else {
                        echo 'director_dashboard.php';
                    }
                ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Назад
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto p-6">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Основная информация -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Заголовок заявки -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center gap-3 mb-4 flex-wrap">
                        <!-- Приоритет -->
                        <span class="priority-badge <?php echo getPriorityColor($priority); ?>">
                            <i class="fas <?php echo getPriorityIcon($priority); ?>"></i>
                            <?php echo getPriorityText($priority); ?>
                        </span>
                        
                        <!-- Тип заявки -->
                        <span class="px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                            <i class="fas fa-tag mr-1"></i>
                            <?php echo t($request['request_type']); ?>
                        </span>
                        
                        <!-- Статус -->
                        <?php 
                        $statusColors = [
                            'new' => 'bg-blue-100 text-blue-800',
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'in_progress' => 'bg-purple-100 text-purple-800',
                            'completed' => 'bg-gray-100 text-gray-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        ?>
                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusColors[$request['status']]; ?>">
                            <i class="fas fa-circle mr-1" style="font-size:8px;"></i>
                            <?php echo t($request['status']); ?>
                        </span>
                        
                        <!-- Срок -->
                        <?php if ($request['deadline']): ?>
                            <span class="px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                Срок: <?php echo date('d.m.Y', strtotime($request['deadline'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">
                        <?php echo t('cabinet'); ?>: <?php echo $request['cabinet']; ?>
                        <?php 
                        if ($request['request_type'] === 'repair') {
                            echo ' - ' . $request['equipment_type'];
                        } elseif ($request['request_type'] === '1c_database') {
                            echo ' - База данных 1С';
                        } elseif ($request['request_type'] === 'software') {
                            echo ' - ' . $request['software_name'];
                        } elseif ($request['request_type'] === 'general_question') {
                            echo ' - Общие вопросы / Консультация';
                            if (!empty($request['software_or_system'])) {
                                echo ' (' . htmlspecialchars($request['software_or_system']) . ')';
                            }
                        }
                        ?>
                    </h2>
                    
                    <!-- Описание -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <h3 class="font-semibold text-gray-700 mb-2">Описание:</h3>
                        <p class="text-gray-600 whitespace-pre-wrap">
                            <?php 
                            if ($request['request_type'] === 'repair') {
                                echo $request['problem_description'];
                            } elseif ($request['request_type'] === 'software') {
                                echo $request['justification'];
                            } elseif ($request['request_type'] === '1c_database') {
                                echo $request['database_purpose'];
                            } elseif ($request['request_type'] === 'general_question') {
                                echo htmlspecialchars($request['question_description']);
                            }
                            ?>
                        </p>
                    </div>
                    
                    <!-- Список студентов -->
                    <?php if (!empty($students)): ?>
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-700 mb-2">
                                <i class="fas fa-users mr-2"></i>
                                Список студентов (<?php echo count($students); ?>):
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <?php foreach ($students as $student): ?>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-user-graduate text-blue-600"></i>
                                        <span><?php echo htmlspecialchars($student); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Детальная информация -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2"></i>
                        Детальная информация
                    </h3>
                    
                    <div class="space-y-1">
                        <div class="info-row">
                            <div class="info-label">Создал:</div>
                            <div class="info-value">
                                <i class="fas fa-user mr-2"></i>
                                <?php echo $request['creator_name']; ?>
                                (<?php echo $request['creator_position']; ?>)
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Кабинет:</div>
                            <div class="info-value">
                                <i class="fas fa-door-open mr-2"></i>
                                <?php echo $request['cabinet']; ?>
                            </div>
                        </div>
                        
                        <?php if ($request['inventory_number']): ?>
                            <div class="info-row">
                                <div class="info-label">Инвентарный номер:</div>
                                <div class="info-value">
                                    <i class="fas fa-barcode mr-2"></i>
                                    <?php echo $request['inventory_number']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['request_type'] === '1c_database'): ?>
                            <div class="info-row">
                                <div class="info-label">Номер группы:</div>
                                <div class="info-value">
                                    <i class="fas fa-users mr-2"></i>
                                    <?php echo $request['group_number']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-row">
                            <div class="info-label">Дата создания:</div>
                            <div class="info-value">
                                <i class="fas fa-clock mr-2"></i>
                                <?php echo date('d.m.Y H:i', strtotime($request['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($request['tech_name']): ?>
                            <div class="info-row">
                                <div class="info-label">Системотехник:</div>
                                <div class="info-value">
                                    <i class="fas fa-user-cog mr-2"></i>
                                    <?php echo $request['tech_name']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['started_at']): ?>
                            <div class="info-row">
                                <div class="info-label">Начато:</div>
                                <div class="info-value">
                                    <i class="fas fa-play mr-2"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($request['started_at'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['completed_at']): ?>
                            <div class="info-row">
                                <div class="info-label">Завершено:</div>
                                <div class="info-value">
                                    <i class="fas fa-check mr-2"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($request['completed_at'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['approver_name']): ?>
                            <div class="info-row">
                                <div class="info-label">Одобрил:</div>
                                <div class="info-value">
                                    <i class="fas fa-user-tie mr-2"></i>
                                    <?php echo $request['approver_name']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['approved_at']): ?>
                            <div class="info-row">
                                <div class="info-label">Дата одобрения:</div>
                                <div class="info-value">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($request['approved_at'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['rejection_reason']): ?>
                            <div class="info-row">
                                <div class="info-label">Причина отклонения:</div>
                                <div class="info-value text-red-600">
                                    <i class="fas fa-times-circle mr-2"></i>
                                    <?php echo $request['rejection_reason']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Отзыв преподавателя и комментарии директора -->
                <?php if ($request['teacher_feedback'] || $request['rejection_reason'] || $request['completion_note']): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-star mr-2"></i>
                            Дополнительная информация
                        </h3>
                        
                        <?php if ($request['teacher_feedback']): ?>
                            <div class="mb-4 p-4 border-l-4 border-indigo-500 bg-indigo-50 rounded-r-lg">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-chalkboard-teacher text-indigo-600"></i>
                                    <span class="font-semibold text-indigo-800">Отзыв преподавателя о выполненной работе:</span>
                                </div>
                                <p class="text-gray-800 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($request['teacher_feedback'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['rejection_reason']): ?>
                            <div class="mb-4 p-4 border-l-4 border-red-500 bg-red-50 rounded-r-lg">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-times-circle text-red-600"></i>
                                    <span class="font-semibold text-red-800">Причина отклонения:</span>
                                </div>
                                <p class="text-gray-800 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($request['rejection_reason'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['completion_note']): ?>
                            <div class="p-4 border-l-4 border-green-500 bg-green-50 rounded-r-lg">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-check-circle text-green-600"></i>
                                    <span class="font-semibold text-green-800">Примечание техника о завершении:</span>
                                </div>
                                <p class="text-gray-800 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($request['completion_note'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Комментарии -->
                <?php if (!empty($comments)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-comments mr-2"></i>
                            История комментариев (<?php echo count($comments); ?>)
                        </h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($comments as $comment): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="p-4 border-l-4 rounded-r-lg <?php echo getRoleColor($comment['role']); ?>">
                                        <div class="flex items-center gap-3 mb-2">
                                            <i class="fas <?php echo getRoleIcon($comment['role']); ?>"></i>
                                            <span class="font-semibold text-gray-800">
                                                <?php echo $comment['full_name']; ?>
                                            </span>
                                            <span class="px-2 py-1 rounded text-xs font-medium bg-white shadow-sm">
                                                <?php echo getRoleName($comment['role']); ?>
                                            </span>
                                            <span class="text-sm text-gray-500 ml-auto">
                                                <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-800 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Боковая панель -->
            <div class="space-y-6">
                
                <!-- Статистика -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-line mr-2"></i>
                        Статистика
                    </h3>
                    
                    <div class="space-y-3">
                        <?php 
                        // Подсчёт времени
                        $createdTime = strtotime($request['created_at']);
                        $currentTime = time();
                        
                        if ($request['completed_at']) {
                            $completedTime = strtotime($request['completed_at']);
                            $totalDays = round(($completedTime - $createdTime) / 86400, 1);
                        } else {
                            $totalDays = round(($currentTime - $createdTime) / 86400, 1);
                        }
                        
                        if ($request['started_at']) {
                            $startedTime = strtotime($request['started_at']);
                            $waitingDays = round(($startedTime - $createdTime) / 86400, 1);
                            
                            if ($request['completed_at']) {
                                $workingDays = round(($completedTime - $startedTime) / 86400, 1);
                            } else {
                                $workingDays = round(($currentTime - $startedTime) / 86400, 1);
                            }
                        }
                        ?>
                        
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-hourglass-start text-blue-600"></i>
                                <span class="text-sm text-gray-700">Всего дней:</span>
                            </div>
                            <span class="font-bold text-blue-600"><?php echo $totalDays; ?></span>
                        </div>
                        
                        <?php if (isset($waitingDays)): ?>
                            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-clock text-yellow-600"></i>
                                    <span class="text-sm text-gray-700">Ожидание:</span>
                                </div>
                                <span class="font-bold text-yellow-600"><?php echo $waitingDays; ?> дн.</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($workingDays)): ?>
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-tools text-green-600"></i>
                                    <span class="text-sm text-gray-700">В работе:</span>
                                </div>
                                <span class="font-bold text-green-600"><?php echo $workingDays; ?> дн.</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-comment text-purple-600"></i>
                                <span class="text-sm text-gray-700">Комментариев:</span>
                            </div>
                            <span class="font-bold text-purple-600"><?php echo count($comments); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Быстрые действия -->
                <div class="bg-white rounded-lg shadow-md p-6 no-print">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-bolt mr-2"></i>
                        Действия
                    </h3>
                    
                  
                        
                        <a href="<?php 
                            if ($user['role'] === 'teacher') {
                                echo 'teacher_dashboard.php';
                            } elseif ($user['role'] === 'technician') {
                                echo 'technician_dashboard.php';
                            } else {
                                echo 'director_dashboard.php';
                            }
                        ?>" class="block w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-center">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Вернуться назад
                        </a>
                    </div>
                </div>
                
            </div>
            
        </div>
        
    </div>
    
</body>
</html>