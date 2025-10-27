<?php
// import_users.php - Массовая загрузка пользователей из Excel

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('admin');

$user = getCurrentUser();
$success = '';
$error = '';
$results = [];

// Обработка загрузки файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, ['csv'])) {
        $error = 'Неверный формат файла! Поддерживается только CSV';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Пропускаем заголовок
        $header = fgetcsv($handle, 1000, ',');
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($data) < 5) {
                $skipped++;
                continue;
            }
            
            $username = trim($data[0]);
            $fullName = trim($data[1]);
            $role = trim($data[2]);
            $position = trim($data[3]);
            $password = trim($data[4]);
            
            if (empty($username) || empty($fullName) || empty($role) || empty($position) || empty($password)) {
                $skipped++;
                $errors[] = "Пропущена строка: пустые поля для '$username'";
                continue;
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $skipped++;
                $errors[] = "Пользователь '$username' уже существует";
                continue;
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_code = ?");
            $stmt->execute([$role]);
            $roleExists = $stmt->fetchColumn() > 0;
            
            $oldRoles = ['admin', 'director', 'teacher', 'technician'];
            if (!$roleExists && !in_array($role, $oldRoles)) {
                $skipped++;
                $errors[] = "Роль '$role' не существует для '$username'";
                continue;
            }
            
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, role, position, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $fullName, $role, $position, $hashedPassword]);
                $imported++;
            } catch (PDOException $e) {
                $skipped++;
                $errors[] = "Ошибка добавления '$username': " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        $results = [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
        
        if ($imported > 0) {
            $success = "Успешно импортировано пользователей: $imported";
        }
        if ($skipped > 0) {
            $error = "Пропущено строк: $skipped";
        }
    }
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Импорт пользователей - <?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-file-import text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Массовая загрузка пользователей</h1>
                    <p class="text-sm text-gray-600">Импорт из CSV</p>
                </div>
            </div>
            <a href="admin_dashboard.php?tab=users" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-arrow-left"></i>
                Назад
            </a>
        </div>
    </div>
    
    <div class="max-w-5xl mx-auto p-6">
        
        <?php if ($success): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <div class="mb-6 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Результаты импорта</h3>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <span class="text-green-700">Импортировано:</span>
                        <span class="text-2xl font-bold text-green-600 ml-2"><?php echo $results['imported']; ?></span>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <span class="text-yellow-700">Пропущено:</span>
                        <span class="text-2xl font-bold text-yellow-600 ml-2"><?php echo $results['skipped']; ?></span>
                    </div>
                </div>
                <?php if (!empty($results['errors'])): ?>
                    <div class="bg-red-50 rounded-lg p-4 max-h-60 overflow-y-auto">
                        <h4 class="font-semibold mb-2">Ошибки:</h4>
                        <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                            <?php foreach ($results['errors'] as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Загрузить файл</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Выберите CSV файл <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="excel_file" accept=".csv" required class="w-full px-4 py-2 border-2 border-dashed border-gray-300 rounded-lg">
                        <p class="text-xs text-gray-500 mt-2">Только CSV формат</p>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-blue-800 mb-2">Формат CSV:</h4>
                        <div class="bg-white rounded p-3 text-xs font-mono">
                            <div class="text-gray-600">Логин,ФИО,Роль,Должность,Пароль</div>
                            <div class="text-gray-800">ivanov,Иванов И.И.,teacher,Учитель,pass123</div>
                            <div class="text-gray-800">petrov,Петров П.П.,laborant,Лаборант,pass456</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-upload mr-2"></i>Загрузить
                    </button>
                </form>
            </div>
            
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Доступные роли</h3>
                    <div class="space-y-2 text-sm">
                        <?php
                        $stmt = $pdo->query("SELECT role_code, role_name_ru FROM roles ORDER BY role_name_ru");
                        $allRoles = $stmt->fetchAll();
                        
                        $oldRolesData = [
                            'admin' => 'Администратор',
                            'director' => 'Директор',
                            'teacher' => 'Преподаватель',
                            'technician' => 'Системотехник'
                        ];
                        
                        foreach ($oldRolesData as $code => $name) {
                            $exists = false;
                            foreach ($allRoles as $r) {
                                if ($r['role_code'] === $code) {
                                    $exists = true;
                                    break;
                                }
                            }
                            if (!$exists) {
                                $allRoles[] = ['role_code' => $code, 'role_name_ru' => $name];
                            }
                        }
                        
                        foreach ($allRoles as $role):
                        ?>
                            <div class="flex justify-between bg-gray-50 p-2 rounded">
                                <span><?php echo $role['role_name_ru']; ?></span>
                                <code class="text-xs bg-white px-2 py-1 rounded"><?php echo $role['role_code']; ?></code>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h4 class="font-semibold text-yellow-800 mb-2">Важно!</h4>
                    <ul class="text-xs text-yellow-700 space-y-1 list-disc list-inside">
                        <li>Все поля обязательны</li>
                        <li>Логины должны быть уникальными</li>
                        <li>Роли должны существовать</li>
                        <li>Пароли будут зашифрованы</li>
                    </ul>
                </div>
            </div>
            
        </div>
    </div>
</body>
</html>
