<?php
// edit_role.php - Редактирование роли

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('admin');

$user = getCurrentUser();
$roleId = $_GET['id'] ?? null;

if (!$roleId) {
    header('Location: admin_dashboard.php?tab=roles');
    exit();
}

// Получение данных роли
$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$roleId]);
$role = $stmt->fetch();

if (!$role) {
    header('Location: admin_dashboard.php?tab=roles');
    exit();
}

// Проверка что это не базовая роль (их нельзя редактировать)
$protectedRoles = ['admin', 'director', 'teacher', 'technician'];
$isProtected = in_array($role['role_code'], $protectedRoles);

$success = '';
$error = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isProtected) {
    $roleNameRu = $_POST['role_name_ru'];
    $roleNameKk = $_POST['role_name_kk'];
    $description = $_POST['description'] ?? '';
    $canCreateRequest = isset($_POST['can_create_request']) ? 1 : 0;
    $canApproveRequest = isset($_POST['can_approve_request']) ? 1 : 0;
    $canWorkOnRequest = isset($_POST['can_work_on_request']) ? 1 : 0;
    $canManageUsers = isset($_POST['can_manage_users']) ? 1 : 0;
    $canManageCabinets = isset($_POST['can_manage_cabinets']) ? 1 : 0;
    $canViewAllRequests = isset($_POST['can_view_all_requests']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE roles 
            SET role_name_ru = ?, 
                role_name_kk = ?, 
                description = ?,
                can_create_request = ?, 
                can_approve_request = ?, 
                can_work_on_request = ?, 
                can_manage_users = ?, 
                can_manage_cabinets = ?, 
                can_view_all_requests = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $roleNameRu, 
            $roleNameKk, 
            $description,
            $canCreateRequest, 
            $canApproveRequest, 
            $canWorkOnRequest, 
            $canManageUsers, 
            $canManageCabinets, 
            $canViewAllRequests,
            $roleId
        ]);
        
        $success = 'Роль успешно обновлена!';
        
        // Обновляем данные для отображения
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();
        
    } catch (PDOException $e) {
        $error = 'Ошибка при обновлении роли: ' . $e->getMessage();
    }
}

// Получение количества пользователей с этой ролью
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
$stmt->execute([$role['role_code']]);
$usersCount = $stmt->fetchColumn();

// Получение списка пользователей с этой ролью
$stmt = $pdo->prepare("SELECT id, username, full_name, position FROM users WHERE role = ? ORDER BY full_name ASC");
$stmt->execute([$role['role_code']]);
$roleUsers = $stmt->fetchAll();

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование роли - <?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .permission-checkbox {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            transition: all 0.2s;
        }
        .permission-checkbox:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        .permission-checkbox input:checked ~ label {
            font-weight: 600;
            color: #4f46e5;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-user-tag text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Редактирование роли</h1>
                    <p class="text-sm text-gray-600"><?php echo $role['role_name_ru']; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php?tab=roles" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-arrow-left"></i>
                    Назад
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-4xl mx-auto p-6">
        
        <?php if ($success): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($isProtected): ?>
            <div class="mb-6 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>Системная роль:</strong> Эта роль является базовой и не может быть изменена.
                    Вы можете только просматривать её настройки.
                </div>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Основная информация -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-cog mr-2"></i>
                        Настройки роли
                    </h2>
                    
                    <form method="POST">
                        <div class="space-y-4">
                            
                            <!-- Код роли (только для просмотра) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Код роли
                                </label>
                                <input type="text" value="<?php echo htmlspecialchars($role['role_code']); ?>" disabled class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                                <p class="text-xs text-gray-500 mt-1">Код роли нельзя изменить после создания</p>
                            </div>
                            
                            <!-- Название на русском -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Название (рус) <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="role_name_ru" value="<?php echo htmlspecialchars($role['role_name_ru']); ?>" required <?php echo $isProtected ? 'disabled' : ''; ?> class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent <?php echo $isProtected ? 'bg-gray-100 cursor-not-allowed' : ''; ?>">
                            </div>
                            
                            <!-- Название на казахском -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Название (қаз) <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="role_name_kk" value="<?php echo htmlspecialchars($role['role_name_kk']); ?>" required <?php echo $isProtected ? 'disabled' : ''; ?> class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent <?php echo $isProtected ? 'bg-gray-100 cursor-not-allowed' : ''; ?>">
                            </div>
                            
                            <!-- Описание -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Описание
                                </label>
                                <textarea name="description" rows="3" <?php echo $isProtected ? 'disabled' : ''; ?> class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent <?php echo $isProtected ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"><?php echo htmlspecialchars($role['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Права доступа -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    Права доступа:
                                </label>
                                <div class="space-y-2">
                                    <label class="permission-checkbox <?php echo $isProtected ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'; ?>">
                                        <input type="checkbox" name="can_create_request" <?php echo $role['can_create_request'] ? 'checked' : ''; ?> <?php echo $isProtected ? 'disabled' : ''; ?> class="w-4 h-4 text-indigo-600 mr-3">
                                        <label class="text-sm text-gray-700 flex-1">
                                            <i class="fas fa-plus-circle mr-2"></i>
                                            Создавать заявки
                                        </label>
                                    </label>
                                    
                                    <label class="permission-checkbox <?php echo $isProtected ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'; ?>">
                                        <input type="checkbox" name="can_approve_request" <?php echo $role['can_approve_request'] ? 'checked' : ''; ?> <?php echo $isProtected ? 'disabled' : ''; ?> class="w-4 h-4 text-indigo-600 mr-3">
                                        <label class="text-sm text-gray-700 flex-1">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            Одобрять заявки
                                        </label>
                                    </label>
                                    
                                    <label class="permission-checkbox <?php echo $isProtected ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'; ?>">
                                        <input type="checkbox" name="can_work_on_request" <?php echo $role['can_work_on_request'] ? 'checked' : ''; ?> <?php echo $isProtected ? 'disabled' : ''; ?> class="w-4 h-4 text-indigo-600 mr-3">
                                        <label class="text-sm text-gray-700 flex-1">
                                            <i class="fas fa-wrench mr-2"></i>
                                            Работать над заявками
                                        </label>
                                    </label>
                                    
                                    <label class="permission-checkbox <?php echo $isProtected ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'; ?>">
                                        <input type="checkbox" name="can_manage_users" <?php echo $role['can_manage_users'] ? 'checked' : ''; ?> <?php echo $isProtected ? 'disabled' : ''; ?> class="w-4 h-4 text-indigo-600 mr-3">
                                        <label class="text-sm text-gray-700 flex-1">
                                            <i class="fas fa-users-cog mr-2"></i>
                                            Управлять пользователями
                                        </label>
                                    </label>
                                    
                                    <label class="permission-checkbox <?php echo $isProtected ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'; ?>">
                                        <input type="checkbox" name="can_manage_cabinets" <?php echo $role['can_manage_cabinets'] ? 'checked' : ''; ?> <?php echo $isProtected ? 'disabled' : ''; ?> class="w-4 h-4 text-indigo-600 mr-3">
                                        <label class="text-sm text-gray-700 flex-1">
                                            <i class="fas fa-building mr-2"></i>
                                            Управлять кабинетами
                                        </label>
                                    </label>
                                    
                                    <label class="permission-checkbox <?php echo $isProtected ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'; ?>">
                                        <input type="checkbox" name="can_view_all_requests" <?php echo $role['can_view_all_requests'] ? 'checked' : ''; ?> <?php echo $isProtected ? 'disabled' : ''; ?> class="w-4 h-4 text-indigo-600 mr-3">
                                        <label class="text-sm text-gray-700 flex-1">
                                            <i class="fas fa-eye mr-2"></i>
                                            Видеть все заявки
                                        </label>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Кнопки -->
                            <?php if (!$isProtected): ?>
                            <div class="flex gap-3 pt-4">
                                <button type="submit" class="flex-1 bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition font-medium">
                                    <i class="fas fa-save mr-2"></i>
                                    Сохранить изменения
                                </button>
                                <a href="admin_dashboard.php?tab=roles" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition font-medium text-center">
                                    Отмена
                                </a>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Боковая панель -->
            <div class="space-y-6">
                
                <!-- Информация о роли -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2"></i>
                        Информация
                    </h3>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-600">ID роли</p>
                            <p class="text-lg font-semibold text-gray-800">#<?php echo $role['id']; ?></p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Дата создания</p>
                            <p class="text-lg font-semibold text-gray-800">
                                <?php echo date('d.m.Y', strtotime($role['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Пользователей</p>
                            <p class="text-lg font-semibold text-indigo-600">
                                <?php echo $usersCount; ?>
                            </p>
                        </div>
                        
                        <?php if ($isProtected): ?>
                        <div class="pt-3 border-t">
                            <div class="flex items-center gap-2 text-yellow-700 bg-yellow-50 p-2 rounded">
                                <i class="fas fa-shield-alt"></i>
                                <span class="text-sm font-medium">Системная роль</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Пользователи с этой ролью -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-users mr-2"></i>
                        Пользователи
                    </h3>
                    
                    <?php if (empty($roleUsers)): ?>
                        <p class="text-sm text-gray-500 text-center py-4">
                            Нет пользователей с этой ролью
                        </p>
                    <?php else: ?>
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            <?php foreach ($roleUsers as $u): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800 text-sm"><?php echo $u['full_name']; ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $u['position']; ?></p>
                                    </div>
                                    <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="text-indigo-600 hover:text-indigo-700">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Опасная зона -->
                <?php if (!$isProtected && $usersCount == 0): ?>
                <div class="bg-red-50 rounded-lg shadow-md p-6 border-2 border-red-200">
                    <h3 class="text-lg font-bold text-red-800 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Опасная зона
                    </h3>
                    <p class="text-sm text-red-600 mb-4">
                        Удаление роли нельзя отменить!
                    </p>
                    <a href="admin_dashboard.php?tab=roles&delete_role=<?php echo $role['id']; ?>" onclick="return confirm('Вы уверены, что хотите удалить роль <?php echo $role['role_name_ru']; ?>? Это действие нельзя отменить!')" class="block bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-center font-medium">
                        <i class="fas fa-trash mr-2"></i>
                        Удалить роль
                    </a>
                </div>
                <?php endif; ?>
                
            </div>
            
        </div>
        
    </div>
    
</body>
</html>
