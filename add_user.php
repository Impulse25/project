<?php
// add_user.php - Добавление пользователя

require_once 'config/db.php';
require_once 'includes/auth.php';

requireRole('admin');

$success = '';
$error = '';

// Получение всех ролей из БД
try {
    $stmt = $pdo->query("SELECT role_code, role_name_ru, role_name_kk FROM roles ORDER BY role_name_ru ASC");
    $allRoles = $stmt->fetchAll();
} catch (PDOException $e) {
    // Если таблица ролей не существует, используем стандартные
    $allRoles = [
        ['role_code' => 'admin', 'role_name_ru' => 'Администратор', 'role_name_kk' => 'Әкімші'],
        ['role_code' => 'director', 'role_name_ru' => 'Директор', 'role_name_kk' => 'Директор'],
        ['role_code' => 'teacher', 'role_name_ru' => 'Учитель', 'role_name_kk' => 'Мұғалім'],
        ['role_code' => 'technician', 'role_name_ru' => 'Системотехник', 'role_name_kk' => 'Жүйелік техник']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $fullName = $_POST['full_name'];
    $role = $_POST['role'];
    $position = $_POST['position'];
    
    // Проверка существования пользователя
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetchColumn() > 0) {
        $error = 'Пользователь с таким логином уже существует!';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, position) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$username, $hashedPassword, $fullName, $role, $position])) {
            $success = 'Пользователь успешно добавлен!';
            // Очистка полей после успешного добавления
            $_POST = [];
        } else {
            $error = 'Ошибка при добавлении пользователя!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить пользователя</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    
    <div class="max-w-2xl mx-auto p-6">
        
        <div class="mb-6">
            <a href="admin_dashboard.php?tab=users" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-700">
                <i class="fas fa-arrow-left"></i>
                Назад к пользователям
            </a>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-user-plus mr-2"></i>
                Добавить пользователя
            </h2>
            
            <?php if ($success): ?>
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Логин <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="ivanov">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Пароль <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="••••••••">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        ФИО <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="full_name" required 
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Иванов Иван Иванович">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Роль <span class="text-red-500">*</span>
                    </label>
                    <select name="role" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">Выберите роль</option>
                        <?php foreach ($allRoles as $roleData): ?>
                            <option value="<?php echo $roleData['role_code']; ?>"
                                    <?php echo (isset($_POST['role']) && $_POST['role'] === $roleData['role_code']) ? 'selected' : ''; ?>>
                                <?php echo $roleData['role_name_ru']; ?> / <?php echo $roleData['role_name_kk']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Должность <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="position" required 
                           value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Учитель математики">
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" 
                            class="flex-1 bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition font-medium">
                        <i class="fas fa-save mr-2"></i>
                        Добавить пользователя
                    </button>
                    <a href="admin_dashboard.php?tab=users" 
                       class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition font-medium text-center">
                        Отмена
                    </a>
                </div>
                
            </form>
        </div>
        
    </div>
    
</body>
</html>
