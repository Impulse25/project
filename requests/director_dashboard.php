<?php
// director_dashboard.php - Панель директора (с вкладками)

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('director');

$user = getCurrentUser();
$tab = $_GET['tab'] ?? 'pending';

if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: director_dashboard.php?tab=' . $tab);
    exit();
}

// Обработка одобрения/отклонения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $comment = $_POST['approval_comment'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $requestId]);
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
        }
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, new_status, comment) VALUES (?, ?, 'approved', 'approved', ?)");
        $stmt->execute([$requestId, $user['id'], 'Одобрено директором: ' . $comment]);
    } elseif ($action === 'reject') {
        // Отклонение заявки
        $reason = $_POST['reason'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
        $stmt->execute([$user['id'], $reason, $requestId]);
=======
        
        // Добавить комментарий директора об отклонении
        if ($reason) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], 'Заявка отклонена. Причина: ' . $reason]);
        }
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, new_status, comment) VALUES (?, ?, 'rejected', 'rejected', ?)");
        $stmt->execute([$requestId, $user['id'], 'Отклонено: ' . $reason]);
    }
    header('Location: director_dashboard.php?tab=' . $tab);
    exit();
}

// Запросы для заявок
$stmt = $pdo->query("SELECT r.*, u.full_name as creator_name, u.position FROM requests r JOIN users u ON r.created_by = u.id WHERE r.status = 'awaiting_approval' AND r.sent_to_director = 1 ORDER BY r.created_at DESC");
$pendingRequests = $stmt->fetchAll();
$pendingCount = count($pendingRequests);

$stmt = $pdo->query("SELECT r.*, u.full_name as creator_name FROM requests r JOIN users u ON r.created_by = u.id WHERE r.status = 'approved' ORDER BY r.approved_at DESC LIMIT 50");
$approvedRequests = $stmt->fetchAll();
$approvedCount = count($approvedRequests);

$stmt = $pdo->query("SELECT r.*, u.full_name as creator_name FROM requests r JOIN users u ON r.created_by = u.id WHERE r.status = 'rejected' ORDER BY r.approved_at DESC LIMIT 50");
$rejectedRequests = $stmt->fetchAll();
$rejectedCount = count($rejectedRequests);

$stmt = $pdo->query("SELECT r.*, u.full_name as creator_name FROM requests r JOIN users u ON r.created_by = u.id ORDER BY r.created_at DESC LIMIT 100");
$allRequests = $stmt->fetchAll();

// Статистика по внутренним задачам IT-отдела (technician_tasks)
$stmt = $pdo->query("SELECT u.id, u.full_name, u.position, COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_count, COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) as in_progress_count, AVG(CASE WHEN t.status = 'completed' AND t.started_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.started_at, t.completed_at) END) as avg_hours FROM users u LEFT JOIN technician_tasks t ON t.assigned_to = u.id WHERE u.role = 'technician' GROUP BY u.id, u.full_name, u.position ORDER BY completed_count DESC");
$technicianStats = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DATE_FORMAT(completed_at, '%Y-%m') as month, DATE_FORMAT(completed_at, '%m.%Y') as month_display, COUNT(*) as completed_count, AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at)) as avg_hours FROM technician_tasks WHERE status = 'completed' AND completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(completed_at, '%Y-%m'), DATE_FORMAT(completed_at, '%m.%Y') ORDER BY month DESC");
$monthlyStats = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(*) as total, COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed, COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress, COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending, AVG(CASE WHEN status = 'completed' AND started_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, started_at, completed_at) END) as avg_completion_hours FROM technician_tasks");
$taskSummary = $stmt->fetch();

// ═══════════════════════════════════════════════════════════════
// СТАТИСТИКА ПО ЗАЯВКАМ ОТ СОТРУДНИКОВ (requests)
// ═══════════════════════════════════════════════════════════════

// Статистика выполненных заявок по каждому системотехнику
$stmt = $pdo->query("
    SELECT 
        u.id, u.full_name, u.position,
        COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed_requests,
        COUNT(CASE WHEN r.status = 'in_progress' THEN 1 END) as in_progress_requests,
        COUNT(CASE WHEN r.status IN ('pending', 'approved') THEN 1 END) as pending_requests,
        AVG(CASE WHEN r.status = 'completed' AND r.completed_at IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, r.assigned_at, r.completed_at) END) as avg_request_hours
    FROM users u
    LEFT JOIN requests r ON r.assigned_to = u.id
    WHERE u.role = 'technician'
    GROUP BY u.id, u.full_name, u.position
    ORDER BY completed_requests DESC
");
$technicianRequestStats = $stmt->fetchAll();

// Статистика заявок по месяцам
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(completed_at, '%Y-%m') as month,
        DATE_FORMAT(completed_at, '%m.%Y') as month_display,
        COUNT(*) as completed_count,
        AVG(TIMESTAMPDIFF(HOUR, assigned_at, completed_at)) as avg_hours
    FROM requests 
    WHERE status = 'completed' 
    AND completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(completed_at, '%Y-%m'), DATE_FORMAT(completed_at, '%m.%Y')
    ORDER BY month DESC
");
$monthlyRequestStats = $stmt->fetchAll();

// Общая статистика по заявкам
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status IN ('pending', 'approved') THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        AVG(CASE WHEN status = 'completed' AND completed_at IS NOT NULL AND assigned_at IS NOT NULL
            THEN TIMESTAMPDIFF(HOUR, assigned_at, completed_at) END) as avg_completion_hours
    FROM requests
");
$requestSummary = $stmt->fetch();

$currentLang = getCurrentLanguage();
$isRequestsTab = in_array($tab, ['pending', 'approved', 'rejected', 'all']);
$isStatsTab = ($tab === 'stats');
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('director'); ?> - <?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-cog text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600"><?php echo !empty($user['position']) ? htmlspecialchars($user['position']) : t('director'); ?>: <?php echo htmlspecialchars($user['full_name']); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex gap-2">
                    <a href="?lang=ru&tab=<?php echo $tab; ?>" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Рус</a>
                    <a href="?lang=kk&tab=<?php echo $tab; ?>" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Қаз</a>
                </div>
                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition"><i class="fas fa-sign-out-alt"></i> <?php echo t('exit'); ?></a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto p-6">
        
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-4">
                    <a href="?tab=pending" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $isRequestsTab ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-file-alt"></i> Заявки
                        <?php if ($pendingCount > 0): ?><span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-semibold"><?php echo $pendingCount; ?></span><?php endif; ?>
                    </a>
                    <a href="?tab=stats" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $isStatsTab ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-chart-bar"></i> Статистика IT-отдела
                    </a>
                </nav>
            </div>
        </div>
        
        <?php if ($isRequestsTab): ?>
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="flex items-center p-4 border-b">
                <nav class="flex gap-2 flex-wrap">
                    <a href="?tab=pending" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'pending' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                        <i class="fas fa-clock"></i> На согласовании <?php if ($pendingCount > 0): ?><span class="px-2 py-0.5 <?php echo $tab === 'pending' ? 'bg-white/20' : 'bg-purple-100 text-purple-800'; ?> rounded-full text-xs"><?php echo $pendingCount; ?></span><?php endif; ?>
                    </a>
                    <a href="?tab=approved" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'approved' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                        <i class="fas fa-check-circle"></i> Одобренные <?php if ($approvedCount > 0): ?><span class="px-2 py-0.5 <?php echo $tab === 'approved' ? 'bg-white/20' : 'bg-green-100 text-green-800'; ?> rounded-full text-xs"><?php echo $approvedCount; ?></span><?php endif; ?>
                    </a>
                    <a href="?tab=rejected" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                        <i class="fas fa-times-circle"></i> Отклонённые <?php if ($rejectedCount > 0): ?><span class="px-2 py-0.5 <?php echo $tab === 'rejected' ? 'bg-white/20' : 'bg-red-100 text-red-800'; ?> rounded-full text-xs"><?php echo $rejectedCount; ?></span><?php endif; ?>
                    </a>
                    <a href="?tab=all" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'all' ? 'bg-gray-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                        <i class="fas fa-list"></i> Все заявки
                    </a>
                </nav>
            </div>
        </div>
        
        <?php if ($tab === 'pending'): ?>
        <div class="space-y-4">
            <?php if (empty($pendingRequests)): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center"><i class="fas fa-check-circle text-6xl text-green-300 mb-4"></i><p class="text-gray-600">Нет заявок на согласовании</p></div>
            <?php else: ?>
                <?php foreach ($pendingRequests as $req): ?>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-mono">#<?php echo $req['id']; ?></span>
                                <span class="text-sm font-semibold text-indigo-600 uppercase"><?php echo t($req['request_type']); ?></span>
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800"><i class="fas fa-clock"></i> На согласовании</span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">Кабинет: <?php echo htmlspecialchars($req['cabinet']); ?></h3>
                            <p class="text-gray-600 mb-3"><?php echo nl2br(htmlspecialchars($req['description'] ?? '')); ?></p>
                            <div class="flex items-center gap-4 text-sm text-gray-500">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($req['creator_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="flex gap-2 ml-4">
                            <button onclick="showApproveModal(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"><i class="fas fa-check"></i> Одобрить</button>
                            <button onclick="showRejectModal(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"><i class="fas fa-times"></i> Отклонить</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($tab === 'approved'): ?>
        <div class="space-y-3">
            <?php if (empty($approvedRequests)): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center"><i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i><p class="text-gray-600">Нет одобренных заявок</p></div>
            <?php else: ?>
                <?php foreach ($approvedRequests as $req): ?>
                <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-green-500">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-mono">#<?php echo $req['id']; ?></span>
                        <span class="text-sm text-indigo-600"><?php echo t($req['request_type']); ?></span>
                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs"><i class="fas fa-check"></i> Одобрено</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium">Каб. <?php echo htmlspecialchars($req['cabinet']); ?> — <?php echo htmlspecialchars($req['creator_name']); ?></span>
                        <span class="text-sm text-gray-500"><?php echo date('d.m.Y', strtotime($req['approved_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($tab === 'rejected'): ?>
        <div class="space-y-3">
            <?php if (empty($rejectedRequests)): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center"><i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i><p class="text-gray-600">Нет отклонённых заявок</p></div>
            <?php else: ?>
                <?php foreach ($rejectedRequests as $req): ?>
                <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-red-500 opacity-80">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-mono">#<?php echo $req['id']; ?></span>
                        <span class="text-sm text-indigo-600"><?php echo t($req['request_type']); ?></span>
                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs"><i class="fas fa-times"></i> Отклонено</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium">Каб. <?php echo htmlspecialchars($req['cabinet']); ?> — <?php echo htmlspecialchars($req['creator_name']); ?></span>
                        <span class="text-sm text-gray-500"><?php echo date('d.m.Y', strtotime($req['approved_at'])); ?></span>
                    </div>
                    <?php if (!empty($req['rejection_reason'])): ?><p class="text-sm text-red-600 mt-1"><i class="fas fa-comment"></i> <?php echo htmlspecialchars($req['rejection_reason']); ?></p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($tab === 'all'): ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Кабинет</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">От кого</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($allRequests as $req): 
                        $statusColors = ['pending'=>'bg-yellow-100 text-yellow-800','approved'=>'bg-green-100 text-green-800','rejected'=>'bg-red-100 text-red-800','in_progress'=>'bg-blue-100 text-blue-800','completed'=>'bg-gray-100 text-gray-800','awaiting_approval'=>'bg-purple-100 text-purple-800'];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">#<?php echo $req['id']; ?></td>
                        <td class="px-4 py-3 text-xs text-gray-600"><?php echo t($req['request_type']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($req['cabinet']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($req['creator_name']); ?></td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs <?php echo $statusColors[$req['status']] ?? 'bg-gray-100'; ?>"><?php echo t($req['status']); ?></span></td>
                        <td class="px-4 py-3 text-sm text-gray-500"><?php echo date('d.m.Y', strtotime($req['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($isStatsTab): ?>
        
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- ЗАЯВКИ ОТ СОТРУДНИКОВ КОЛЛЕДЖА -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-ticket-alt text-orange-500"></i>
                Заявки от сотрудников колледжа
            </h2>
            
            <!-- Общая статистика заявок -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-4 text-center">
                    <div class="text-3xl font-bold text-gray-800"><?php echo $requestSummary['total'] ?? 0; ?></div>
                    <div class="text-sm text-gray-500">Всего заявок</div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-green-500">
                    <div class="text-3xl font-bold text-green-600"><?php echo $requestSummary['completed'] ?? 0; ?></div>
                    <div class="text-sm text-gray-500">Выполнено</div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-blue-500">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $requestSummary['in_progress'] ?? 0; ?></div>
                    <div class="text-sm text-gray-500">В работе</div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-yellow-500">
                    <div class="text-3xl font-bold text-yellow-600"><?php echo $requestSummary['pending'] ?? 0; ?></div>
                    <div class="text-sm text-gray-500">Ожидают</div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-indigo-500">
                    <div class="text-3xl font-bold text-indigo-600">
                        <?php $avgH = $requestSummary['avg_completion_hours'] ?? 0; echo $avgH >= 24 ? round($avgH / 24, 1) . ' дн.' : round($avgH, 1) . ' ч.'; ?>
                    </div>
                    <div class="text-sm text-gray-500">Среднее время</div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- По сотрудникам -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-user-cog text-orange-500"></i> Выполнение заявок по сотрудникам</h3>
                    <?php if (!empty($technicianRequestStats)): ?>
                    <div class="space-y-3">
                        <?php foreach ($technicianRequestStats as $tech): ?>
                        <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                            <div>
                                <div class="font-semibold"><?php echo htmlspecialchars($tech['full_name']); ?></div>
                                <?php if (!empty($tech['position'])): ?><div class="text-xs text-gray-500"><?php echo htmlspecialchars($tech['position']); ?></div><?php endif; ?>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <div class="text-center"><div class="font-bold text-green-600"><?php echo $tech['completed_requests']; ?></div><div class="text-xs text-gray-500">выполн.</div></div>
                                <div class="text-center"><div class="font-bold text-blue-600"><?php echo $tech['in_progress_requests']; ?></div><div class="text-xs text-gray-500">в работе</div></div>
                                <div class="text-center"><div class="font-bold text-yellow-600"><?php echo $tech['pending_requests']; ?></div><div class="text-xs text-gray-500">ожидает</div></div>
                                <div class="text-center"><div class="font-bold text-indigo-600"><?php $avgH = $tech['avg_request_hours'] ?? 0; echo $avgH >= 24 ? round($avgH / 24, 1) . 'д' : ($avgH > 0 ? round($avgH, 1) . 'ч' : '-'); ?></div><div class="text-xs text-gray-500">среднее</div></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p class="text-gray-500 text-center py-4">Нет данных</p><?php endif; ?>
                </div>
                
                <!-- По месяцам -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-calendar-check text-orange-500"></i> Заявки по месяцам</h3>
                    <?php if (!empty($monthlyRequestStats)): ?>
                    <div class="space-y-3">
                        <?php $monthNames = ['01'=>'Январь','02'=>'Февраль','03'=>'Март','04'=>'Апрель','05'=>'Май','06'=>'Июнь','07'=>'Июль','08'=>'Август','09'=>'Сентябрь','10'=>'Октябрь','11'=>'Ноябрь','12'=>'Декабрь'];
                        foreach ($monthlyRequestStats as $month): $parts = explode('.', $month['month_display']); ?>
                        <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                            <div class="font-semibold"><?php echo ($monthNames[$parts[0]] ?? $parts[0]) . ' ' . $parts[1]; ?></div>
                            <div class="flex items-center gap-4 text-sm">
                                <div class="text-center"><div class="font-bold text-green-600"><?php echo $month['completed_count']; ?></div><div class="text-xs text-gray-500">выполнено</div></div>
                                <div class="text-center"><div class="font-bold text-indigo-600"><?php $avgH = $month['avg_hours'] ?? 0; echo $avgH >= 24 ? round($avgH / 24, 1) . ' дн.' : ($avgH > 0 ? round($avgH, 1) . ' ч.' : '-'); ?></div><div class="text-xs text-gray-500">среднее</div></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p class="text-gray-500 text-center py-4">Нет данных за последние 6 месяцев</p><?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- ВНУТРЕННИЕ ЗАДАЧИ IT-ОТДЕЛА -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-tasks text-indigo-600"></i>
                Внутренние задачи IT-отдела
            </h2>
            
            <!-- Общая статистика задач -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-4 text-center">
                    <div class="text-3xl font-bold text-gray-800"><?php echo $taskSummary['total'] ?? 0; ?></div>
                    <div class="text-sm text-gray-500">Всего задач</div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-green-500">
                    <div class="text-3xl font-bold text-green-600"><?php echo $taskSummary['completed'] ?? 0; ?></div>
                    <div class="text-sm text-gray-500">Выполнено</div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-blue-500">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $taskSummary['in_progress'] ?? 0; ?></div>
                    <div class="text-sm text-gray-500">В работе</div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-yellow-500">
                    <div class="text-3xl font-bold text-yellow-600"><?php echo $taskSummary['pending'] ?? 0; ?></div>
                    <div class="text-sm text-gray-500">В пуле</div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-indigo-500">
                    <div class="text-3xl font-bold text-indigo-600">
                        <?php $avgH = $taskSummary['avg_completion_hours'] ?? 0; echo $avgH >= 24 ? round($avgH / 24, 1) . ' дн.' : round($avgH, 1) . ' ч.'; ?>
                    </div>
                    <div class="text-sm text-gray-500">Среднее время</div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- По сотрудникам -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-users text-indigo-600"></i> Выполнение задач по сотрудникам</h3>
                    <?php if (!empty($technicianStats)): ?>
                    <div class="space-y-3">
                        <?php foreach ($technicianStats as $tech): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-semibold"><?php echo htmlspecialchars($tech['full_name']); ?></div>
                                <?php if (!empty($tech['position'])): ?><div class="text-xs text-gray-500"><?php echo htmlspecialchars($tech['position']); ?></div><?php endif; ?>
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                <div class="text-center"><div class="font-bold text-green-600"><?php echo $tech['completed_count']; ?></div><div class="text-xs text-gray-500">выполн.</div></div>
                                <div class="text-center"><div class="font-bold text-blue-600"><?php echo $tech['in_progress_count']; ?></div><div class="text-xs text-gray-500">в работе</div></div>
                                <div class="text-center"><div class="font-bold text-indigo-600"><?php $avgH = $tech['avg_hours'] ?? 0; echo $avgH >= 24 ? round($avgH / 24, 1) . 'д' : ($avgH > 0 ? round($avgH, 1) . 'ч' : '-'); ?></div><div class="text-xs text-gray-500">среднее</div></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p class="text-gray-500 text-center py-4">Нет данных</p><?php endif; ?>
                </div>
                
                <!-- По месяцам -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-calendar-alt text-indigo-600"></i> Задачи по месяцам</h3>
                    <?php if (!empty($monthlyStats)): ?>
                    <div class="space-y-3">
                        <?php $monthNames = ['01'=>'Январь','02'=>'Февраль','03'=>'Март','04'=>'Апрель','05'=>'Май','06'=>'Июнь','07'=>'Июль','08'=>'Август','09'=>'Сентябрь','10'=>'Октябрь','11'=>'Ноябрь','12'=>'Декабрь'];
                        foreach ($monthlyStats as $month): $parts = explode('.', $month['month_display']); ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="font-semibold"><?php echo ($monthNames[$parts[0]] ?? $parts[0]) . ' ' . $parts[1]; ?></div>
                            <div class="flex items-center gap-4 text-sm">
                                <div class="text-center"><div class="font-bold text-green-600"><?php echo $month['completed_count']; ?></div><div class="text-xs text-gray-500">выполнено</div></div>
                                <div class="text-center"><div class="font-bold text-indigo-600"><?php $avgH = $month['avg_hours'] ?? 0; echo $avgH >= 24 ? round($avgH / 24, 1) . ' дн.' : ($avgH > 0 ? round($avgH, 1) . ' ч.' : '-'); ?></div><div class="text-xs text-gray-500">среднее</div></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p class="text-gray-500 text-center py-4">Нет данных за последние 6 месяцев</p><?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
        
    </div>
    
    <div id="approveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center" style="z-index:1000;">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4 text-green-600"><i class="fas fa-check-circle mr-2"></i>Одобрить заявку</h3>
            <form method="POST">
                <input type="hidden" name="request_id" id="approveRequestId">
                <input type="hidden" name="action" value="approve">
                <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-2">Комментарий:</label><textarea name="approval_comment" class="w-full px-4 py-2 border rounded-lg" rows="3"></textarea></div>
                <div class="flex gap-3"><button type="submit" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Одобрить</button><button type="button" onclick="hideApproveModal()" class="flex-1 bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300">Отмена</button></div>
            </form>
        </div>
    </div>
    
    <div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center" style="z-index:1000;">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4 text-red-600"><i class="fas fa-times-circle mr-2"></i>Отклонить заявку</h3>
            <form method="POST">
                <input type="hidden" name="request_id" id="rejectRequestId">
                <input type="hidden" name="action" value="reject">
                <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-2">Причина: *</label><textarea name="reason" class="w-full px-4 py-2 border rounded-lg" rows="3" required></textarea></div>
                <div class="flex gap-3"><button type="submit" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Отклонить</button><button type="button" onclick="hideRejectModal()" class="flex-1 bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300">Отмена</button></div>
            </form>
        </div>
    </div>
    
    <script>
        function showApproveModal(id) { document.getElementById('approveRequestId').value = id; document.getElementById('approveModal').classList.remove('hidden'); }
        function hideApproveModal() { document.getElementById('approveModal').classList.add('hidden'); }
        function showRejectModal(id) { document.getElementById('rejectRequestId').value = id; document.getElementById('rejectModal').classList.remove('hidden'); }
        function hideRejectModal() { document.getElementById('rejectModal').classList.add('hidden'); }
    </script>
</body>
</html>