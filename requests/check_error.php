<?php
// check_error.php - Файл для диагностики ошибки 500
// Скопируйте этот файл в корень проекта и откройте в браузере

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Диагностика ошибки 500</h1>";
echo "<hr>";

// 1. Проверка версии PHP
echo "<h2>1. Версия PHP:</h2>";
echo "PHP версия: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.0.0', '>=')) {
    echo "<span style='color: green;'>✓ PHP версия подходит</span><br>";
} else {
    echo "<span style='color: red;'>✗ PHP версия слишком старая! Нужна минимум 7.0</span><br>";
}
echo "<hr>";

// 2. Проверка файлов
echo "<h2>2. Проверка существования файлов:</h2>";

$requiredFiles = [
    'config/db.php',
    'includes/auth.php',
    'includes/language.php',
    'includes/permissions.php',
    'admin_dashboard.php',
    'unified_dashboard.php',
    'teacher_dashboard.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<span style='color: green;'>✓ $file - существует</span><br>";
    } else {
        echo "<span style='color: red;'>✗ $file - НЕ НАЙДЕН!</span><br>";
    }
}
echo "<hr>";

// 3. Проверка подключения к БД
echo "<h2>3. Проверка подключения к базе данных:</h2>";
try {
    if (file_exists('config/db.php')) {
        require_once 'config/db.php';
        echo "<span style='color: green;'>✓ Подключение к БД успешно</span><br>";
        
        // Проверяем таблицу roles
        $stmt = $pdo->query("SHOW TABLES LIKE 'roles'");
        if ($stmt->rowCount() > 0) {
            echo "<span style='color: green;'>✓ Таблица 'roles' существует</span><br>";
            
            // Проверяем количество ролей
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM roles");
            $result = $stmt->fetch();
            echo "Количество ролей в БД: " . $result['cnt'] . "<br>";
        } else {
            echo "<span style='color: red;'>✗ Таблица 'roles' НЕ НАЙДЕНА!</span><br>";
        }
        
    } else {
        echo "<span style='color: red;'>✗ Файл config/db.php не найден</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Ошибка БД: " . $e->getMessage() . "</span><br>";
}
echo "<hr>";

// 4. Проверка синтаксиса файлов
echo "<h2>4. Проверка синтаксиса PHP файлов:</h2>";

$filesToCheck = [
    'includes/auth.php',
    'includes/permissions.php',
    'admin_dashboard.php'
];

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        $output = [];
        $return_var = 0;
        exec("php -l $file 2>&1", $output, $return_var);
        
        if ($return_var === 0 && strpos(implode('', $output), 'No syntax errors') !== false) {
            echo "<span style='color: green;'>✓ $file - синтаксис ОК</span><br>";
        } else {
            echo "<span style='color: red;'>✗ $file - ОШИБКА СИНТАКСИСА:</span><br>";
            echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
        }
    }
}
echo "<hr>";

// 5. Проверка сессии
echo "<h2>5. Проверка сессии:</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "<span style='color: green;'>✓ Сессия запущена</span><br>";
} else {
    echo "<span style='color: orange;'>⚠ Сессия уже активна</span><br>";
}

if (isset($_SESSION['user_id'])) {
    echo "Пользователь в сессии: ID = " . $_SESSION['user_id'] . "<br>";
    echo "Роль: " . ($_SESSION['role'] ?? 'не указана') . "<br>";
} else {
    echo "Пользователь не авторизован<br>";
}
echo "<hr>";

// 6. Тест подключения includes/auth.php
echo "<h2>6. Тест подключения includes/auth.php:</h2>";
try {
    if (file_exists('includes/auth.php')) {
        require_once 'includes/auth.php';
        echo "<span style='color: green;'>✓ includes/auth.php подключен успешно</span><br>";
        
        // Проверяем функцию getCurrentUser
        if (function_exists('getCurrentUser')) {
            echo "<span style='color: green;'>✓ Функция getCurrentUser() существует</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Функция getCurrentUser() НЕ НАЙДЕНА</span><br>";
        }
        
        // Проверяем функцию redirectToDashboard
        if (function_exists('redirectToDashboard')) {
            echo "<span style='color: green;'>✓ Функция redirectToDashboard() существует</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Функция redirectToDashboard() НЕ НАЙДЕНА</span><br>";
        }
        
    } else {
        echo "<span style='color: red;'>✗ Файл includes/auth.php не найден</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Ошибка при подключении auth.php: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
echo "<hr>";

// 7. Тест подключения includes/permissions.php
echo "<h2>7. Тест подключения includes/permissions.php:</h2>";
try {
    if (file_exists('includes/permissions.php')) {
        require_once 'includes/permissions.php';
        echo "<span style='color: green;'>✓ includes/permissions.php подключен успешно</span><br>";
        
        // Проверяем функцию hasPermission
        if (function_exists('hasPermission')) {
            echo "<span style='color: green;'>✓ Функция hasPermission() существует</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Функция hasPermission() НЕ НАЙДЕНА</span><br>";
        }
        
    } else {
        echo "<span style='color: orange;'>⚠ Файл includes/permissions.php не найден (возможно не установлен)</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Ошибка при подключении permissions.php: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
echo "<hr>";

// 8. Проверка логов
echo "<h2>8. Путь к логам ошибок:</h2>";
$error_log = ini_get('error_log');
if ($error_log) {
    echo "Файл логов: " . $error_log . "<br>";
    if (file_exists($error_log) && is_readable($error_log)) {
        echo "<span style='color: green;'>✓ Файл логов доступен для чтения</span><br>";
        echo "<h3>Последние 20 строк лога:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>";
        $lines = file($error_log);
        $last_lines = array_slice($lines, -20);
        echo htmlspecialchars(implode('', $last_lines));
        echo "</pre>";
    } else {
        echo "<span style='color: red;'>✗ Файл логов недоступен</span><br>";
    }
} else {
    echo "Логирование ошибок отключено или путь не указан<br>";
}
echo "<hr>";

echo "<h2>9. Информация о сервере:</h2>";
echo "Сервер: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Операционная система: " . PHP_OS . "<br>";
echo "Корневая папка: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Текущая папка: " . getcwd() . "<br>";

echo "<hr>";
echo "<h2 style='color: blue;'>ИНСТРУКЦИЯ:</h2>";
echo "<ol>";
echo "<li>Сделайте скриншот ВСЕЙ этой страницы</li>";
echo "<li>Отправьте мне скриншот</li>";
echo "<li>Особенно важны красные ✗ метки - они показывают проблему</li>";
echo "</ol>";

echo "<hr>";
echo "<p style='color: green; font-weight: bold;'>Диагностика завершена!</p>";
?>
