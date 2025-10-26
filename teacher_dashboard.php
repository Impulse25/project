<?php
// teacher_dashboard.php - Панель преподавателя

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

// Получение заявок преподавателя
$stmt = $pdo->prepare("SELECT * FROM requests WHERE created_by = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();

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
    </style>
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
                    <a href="?lang=ru" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
                        Рус
                    </a>
                    <a href="?lang=kk" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
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
        
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800"><?php echo t('my_requests'); ?></h2>
            <a href="create_request.php" class="flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-plus"></i>
                <?php echo t('create_request'); ?>
            </a>
        </div>
        
        <!-- Список заявок -->
        <div class="space-y-4">
            <?php if (empty($requests)): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600"><?php echo t('no_requests'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $req): 
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
                            <a href="view_request.php?id=<?php echo $req['id']; ?>" class="text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                                <i class="fas fa-eye"></i>
                                <?php echo t('details'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>
    
</body>
</html>
