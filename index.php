<?php
// index.php - Страница входа

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

// Если уже авторизован - редирект на панель
if (isLoggedIn()) {
    redirectToDashboard();
}

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: index.php');
    exit();
}

$error = '';

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($pdo, $username, $password)) {
        redirectToDashboard();
    } else {
        $error = t('login_error');
    }
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    
    <!-- Переключатель языка -->
    <div class="absolute top-4 right-4 flex gap-2">
        <a href="?lang=ru" class="px-3 py-1 rounded <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'; ?>">
            Рус
        </a>
        <a href="?lang=kk" class="px-3 py-1 rounded <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'; ?>">
            Қаз
        </a>
    </div>

    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
            
            <div class="text-center mb-8">
                <i class="fas fa-cog text-6xl text-indigo-600 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                <p class="text-gray-600 mt-2">Обслуживание компьютерного оборудования</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo t('login'); ?>
                    </label>
                    <input 
                        type="text" 
                        name="username"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="<?php echo t('login'); ?>"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo t('password'); ?>
                    </label>
                    <input 
                        type="password" 
                        name="password"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="<?php echo t('password'); ?>"
                    >
                </div>
                
                <button 
                    type="submit"
                    class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition font-medium"
                >
                    <?php echo t('enter'); ?>
                </button>
            </form>
            
            <div class="mt-6 text-center text-sm text-gray-600">
                <p class="mb-2">Тестовые аккаунты (пароль: 12345):</p>
                <div class="text-xs space-y-1">
                    <div>admin / teacher1 / director / tech1</div>
                </div>
            </div>
            
        </div>
    </div>
    
</body>
</html>