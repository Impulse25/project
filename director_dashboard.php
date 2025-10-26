<?php
// director_dashboard.php - Панель директора

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('director');

$user = getCurrentUser();

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: director_dashboard.php');
    exit();
}

// Обработка одобрения/отклонения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $requestId]);
    } elseif ($action === 'reject') {
        $reason = $_POST['reason'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
        $stmt->execute([$user['id'], $reason, $requestId]);
    }
    
    header('Location: director_dashboard.php');
    exit();
}

// Получение заявок на одобрение
$stmt = $pdo->query("SELECT r.*, u.full_name as creator_name FROM requests r JOIN users u ON r.created_by = u.id WHERE r.status = 'pending' ORDER BY r.created_at DESC");
$pendingRequests = $stmt->fetchAll();

// Все заявки
$stmt = $pdo->query("SELECT r.*, u.full_name as creator_name FROM requests r JOIN users u ON r.created_by = u.id ORDER BY r.created_at DESC");
$allRequests = $stmt->fetchAll();

$currentLang = getCurrentLanguage();
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
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-cog text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600"><?php echo t('director'); ?>: <?php echo $user['full_name']; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex gap-2">
                    <a href="?lang=ru" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Рус</a>
                    <a href="?lang=kk" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Қаз</a>
                </div>
                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo t('exit'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto p-6">
        
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800"><?php echo t('pending_approval'); ?></h2>
        </div>
        
        <!-- Заявки на одобрение -->
        <div class="space-y-4 mb-8">
            <?php if (empty($pendingRequests)): ?>
                <div class="bg-gray-50 rounded-lg p-12 text-center">
                    <i class="fas fa-clock text-6xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600"><?php echo t('no_pending_requests'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($pendingRequests as $req): 
                    $typeColors = [
                        'repair' => 'border-red-200',
                        'software' => 'border-blue-200',
                        '1c_database' => 'border-purple-200'
                    ];
                ?>
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $typeColors[$req['request_type']]; ?>">
                        <div class="mb-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-semibold text-indigo-600 uppercase"><?php echo t($req['request_type']); ?></span>
                                <span class="px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    <?php echo t('pending'); ?>
                                </span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                <?php echo t('cabinet'); ?>: <?php echo $req['cabinet']; ?>
                                <?php 
                                if ($req['request_type'] === 'repair') {
                                    echo ' - ' . $req['equipment_type'];
                                } elseif ($req['request_type'] === '1c_database') {
                                    echo ' - ' . t('group_number') . ': ' . $req['group_number'];
                                }
                                ?>
                            </h3>
                            <p class="text-sm text-gray-600 mt-2">
                                <?php echo $req['description'] ?? $req['justification'] ?? $req['database_purpose']; ?>
                            </p>
                            
                            <div class="mt-3 p-3 bg-gray-50 rounded-lg text-sm">
                                <p class="text-gray-700"><strong><?php echo t('from'); ?>:</strong> <?php echo $req['creator_name']; ?> (<?php echo $req['position']; ?>)</p>
                                <p class="text-gray-700"><strong><?php echo t('cabinet'); ?>:</strong> <?php echo $req['cabinet']; ?></p>
                                <?php if ($req['inventory_number']): ?>
                                    <p class="text-gray-700"><strong><?php echo t('inventory_number'); ?>:</strong> <?php echo $req['inventory_number']; ?></p>
                                <?php endif; ?>
                                <p class="text-gray-700"><strong><?php echo t('date'); ?>:</strong> <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <!-- ИСПРАВЛЕННАЯ ФОРМА - убран flex с кнопок -->
                        <form method="POST" class="flex gap-3 pt-4 border-t">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <button type="submit" name="action" value="approve" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition" style="text-align:center;">
                                <i class="fas fa-check-circle"></i>
                                <?php echo t('approve'); ?>
                            </button>
                            <button type="button" onclick="showRejectModal(<?php echo $req['id']; ?>)" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition" style="text-align:center;">
                                <i class="fas fa-times-circle"></i>
                                <?php echo t('reject'); ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Все заявки -->
        <div>
            <h3 class="text-xl font-semibold text-gray-800 mb-4"><?php echo t('all_requests'); ?></h3>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php echo t('request_type'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php echo t('cabinet'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php echo t('from'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php echo t('pending'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php echo t('date'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($allRequests as $req): 
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'in_progress' => 'bg-blue-100 text-blue-800',
                                'completed' => 'bg-gray-100 text-gray-800'
                            ];
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900">#<?php echo $req['id']; ?></td>
                                <td class="px-6 py-4 text-xs font-medium text-gray-600"><?php echo t($req['request_type']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo $req['cabinet']; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?php echo $req['creator_name']; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColors[$req['status']]; ?>">
                                        <?php echo t($req['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('d.m.Y', strtotime($req['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    
    <!-- Модальное окно отклонения -->
    <div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center" style="z-index:1000;">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4"><?php echo t('reject'); ?></h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="request_id" id="rejectRequestId">
                <input type="hidden" name="action" value="reject">
                <textarea name="reason" class="w-full px-4 py-2 border rounded-lg mb-4" rows="3" placeholder="<?php echo t('comment'); ?>"></textarea>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"><?php echo t('reject'); ?></button>
                    <button type="button" onclick="hideRejectModal()" class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300"><?php echo t('cancel'); ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showRejectModal(requestId) {
            document.getElementById('rejectRequestId').value = requestId;
            document.getElementById('rejectModal').classList.remove('hidden');
        }
        
        function hideRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }
    </script>
    
</body>
</html>
