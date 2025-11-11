<?php
// index.php - Страница входа (С ФИКСОМ СЕССИИ)

require_once 'config/db.php';
require_once 'config/ldap.php';
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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = t('login_error');
    } else {
        // Шаг 1: Попытка LDAP авторизации
        $ldapUser = ldapAuthenticate($username, $password);
        
        if ($ldapUser) {
            // LDAP авторизация успешна!
            
            // Проверяем, есть ли пользователь в локальной БД
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([strtolower($username)]);
            $localUser = $stmt->fetch();
            
            if ($localUser) {
                // Пользователь существует в БД - используем его роль и данные
                $_SESSION['user_id'] = $localUser['id'];
                $_SESSION['username'] = $localUser['username'];
                $_SESSION['full_name'] = $localUser['full_name'];
                $_SESSION['role'] = $localUser['role'];
                $_SESSION['position'] = $localUser['position'];
                
                // КРИТИЧНО: Принудительное сохранение сессии
                session_write_close();
                session_start();
                
            } else {
                // Пользователь из AD, но НЕ в локальной БД
                // Создаем его автоматически с ролью 'teacher'
                
                try {
                    // Генерируем случайный невалидный хеш для LDAP пользователей
                    // Они все равно не смогут войти через локальную БД, только через AD
                    $dummyPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                    
                    // Проверяем и фильтруем данные из LDAP
                    $email = isset($ldapUser['email']) && !empty($ldapUser['email']) && $ldapUser['email'] !== 'N/A' 
                        ? $ldapUser['email'] 
                        : strtolower($username) . '@shc.local';
                    
                    $fullName = isset($ldapUser['full_name']) && !empty($ldapUser['full_name']) 
                        ? $ldapUser['full_name'] 
                        : $username;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, full_name, email, password, role, position, created_at) 
                        VALUES (?, ?, ?, ?, 'teacher', 'Преподаватель', NOW())
                    ");
                    
                    $success = $stmt->execute([
                        strtolower($username),
                        $fullName,
                        $email,
                        $dummyPassword
                    ]);
                    
                    if (!$success) {
                        throw new Exception("SQL Execute failed: " . print_r($stmt->errorInfo(), true));
                    }
                    
                    $newUserId = $pdo->lastInsertId();
                    
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['username'] = strtolower($username);
                    $_SESSION['full_name'] = $ldapUser['full_name'];
                    $_SESSION['role'] = 'teacher';
                    $_SESSION['position'] = 'Преподаватель';
                    
                    // КРИТИЧНО: Принудительное сохранение сессии
                    session_write_close();
                    session_start();
                    
                } catch (Exception $e) {
                    error_log("Ошибка создания LDAP пользователя: " . $e->getMessage());
                    error_log("Имя пользователя: " . $username);
                    error_log("Full name: " . ($ldapUser['full_name'] ?? 'N/A'));
                    error_log("Email: " . ($ldapUser['email'] ?? 'N/A'));
                    error_log("SQL Error Code: " . ($stmt ? $stmt->errorCode() : 'N/A'));
                    error_log("SQL Error Info: " . print_r($stmt ? $stmt->errorInfo() : [], true));
                    $error = "Ошибка создания учетной записи: " . $e->getMessage();
                }
            }
            
            if (!$error) {
                // Небольшая задержка для гарантии сохранения сессии
                usleep(100000); // 100ms
                redirectToDashboard();
            }
            
        } else {
            // LDAP не сработал - пробуем локальную БД
            if (login($pdo, $username, $password)) {
                // КРИТИЧНО: Принудительное сохранение сессии
                session_write_close();
                session_start();
                usleep(100000); // 100ms
                redirectToDashboard();
            } else {
                $error = t('login_error');
            }
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
                <p class="mt-3 text-xs text-green-600">
                    <i class="fas fa-network-wired"></i>
                    LDAP авторизация активна
                </p>
            </div>
            
        </div>
    </div>
    
</body>
</html>