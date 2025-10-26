<?php
// admin_cabinets.php - Управление кабинетами (только для админа)

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('admin');

$user = getCurrentUser();

$success = '';
$error = '';

// Обработка добавления кабинета
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $cabinetNumber = trim($_POST['cabinet_number']);
        $description = trim($_POST['description']);
        
        try {
            // Проверка на существование
            $stmt = $pdo->prepare("SELECT id FROM cabinets WHERE cabinet_number = ?");
            $stmt->execute([$cabinetNumber]);
            
            if ($stmt->fetch()) {
                $error = 'Кабинет с таким номером уже существует!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO cabinets (cabinet_number, description) VALUES (?, ?)");
                $stmt->execute([$cabinetNumber, $description]);
                $success = 'Кабинет успешно добавлен!';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete') {
        $cabinetId = $_POST['cabinet_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM cabinets WHERE id = ?");
            $stmt->execute([$cabinetId]);
            $success = 'Кабинет удален!';
        } catch (PDOException $e) {
            $error = 'Ошибка при удалении: ' . $e->getMessage();
        }
    }
}

// Получение всех кабинетов
$stmt = $pdo->query("SELECT * FROM cabinets ORDER BY cabinet_number ASC");
$cabinets = $stmt->fetchAll();

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление кабинетами - <?php echo t('system_name'); ?></title>
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
                    <p class="text-sm text-gray-600">Управление кабинетами</p>
                </div>
            </div>
            <a href="admin_dashboard.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-arrow-left"></i>
                <?php echo t('back'); ?>
            </a>
        </div>
    </div>
    
    <div class="max-w-6xl mx-auto p-6">
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Форма добавления -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Добавить кабинет</h2>
            
            <form method="POST" class="flex gap-4 items-end">
                <input type="hidden" name="action" value="add">
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Номер кабинета *</label>
                    <input type="text" name="cabinet_number" required class="w-full px-4 py-2 border rounded-lg" placeholder="101 или Актовый зал">
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Описание</label>
                    <input type="text" name="description" class="w-full px-4 py-2 border rounded-lg" placeholder="Компьютерный класс">
                </div>
                
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    Добавить
                </button>
            </form>
        </div>
        
        <!-- Список кабинетов -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="font-semibold text-lg">Все кабинеты (<?php echo count($cabinets); ?>)</h3>
            </div>
            
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Номер кабинета</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Описание</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата добавления</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($cabinets)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p>Кабинеты не добавлены</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cabinets as $cabinet): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900">#<?php echo $cabinet['id']; ?></td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($cabinet['cabinet_number']); ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?php echo htmlspecialchars($cabinet['description']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('d.m.Y', strtotime($cabinet['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <form method="POST" class="inline" onsubmit="return confirm('Удалить кабинет?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="cabinet_id" value="<?php echo $cabinet['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-700">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </div>
    
</body>
</html>