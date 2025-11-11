<?php
// test_ldap.php - Проверка работы LDAP в Windows Server
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Тест LDAP</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #22c55e; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .info { color: #3b82f6; }
        h1 { color: #333; }
        pre { background: #f8f8f8; padding: 10px; border-left: 3px solid #3b82f6; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Диагностика LDAP</h1>
    
    <div class="box">
        <h2>1. Проверка расширения LDAP</h2>
        <?php
        if (function_exists('ldap_connect')) {
            echo '<p class="success">✓ Расширение LDAP установлено и загружено!</p>';
            
            // Получаем версию LDAP
            if (function_exists('ldap_get_option')) {
                echo '<p class="info">Функции LDAP доступны для использования.</p>';
            }
        } else {
            echo '<p class="error">✗ Расширение LDAP НЕ установлено!</p>';
            echo '<p>Инструкция по установке:</p>';
            echo '<ol>';
            echo '<li>Найдите файл php.ini</li>';
            echo '<li>Найдите строку: ;extension=ldap</li>';
            echo '<li>Удалите точку с запятой: extension=ldap</li>';
            echo '<li>Сохраните php.ini</li>';
            echo '<li>Выполните в PowerShell: iisreset</li>';
            echo '</ol>';
        }
        ?>
    </div>
    
    <?php if (function_exists('ldap_connect')): ?>
    
    <div class="box">
        <h2>2. Проверка подключения к Active Directory</h2>
        <?php
        // Читаем настройки из config/ldap.php
        if (file_exists('../config/ldap.php')) {
            require_once '../config/ldap.php';
            echo '<p class="info">Настройки LDAP загружены из config/ldap.php</p>';
        } elseif (file_exists('config/ldap.php')) {
            require_once 'config/ldap.php';
            echo '<p class="info">Настройки LDAP загружены из config/ldap.php</p>';
        } else {
            echo '<p class="error">Файл config/ldap.php не найден!</p>';
            echo '<p>Используем настройки по умолчанию для теста:</p>';
            define('LDAP_HOST', 'ldap://dc.svgtk.local');
            define('LDAP_PORT', 389);
            define('LDAP_BASE_DN', 'DC=svgtk,DC=local');
        }
        
        echo '<h3>Параметры подключения:</h3>';
        echo '<pre>';
        echo 'Хост: ' . (defined('LDAP_HOST') ? LDAP_HOST : 'не задан') . "\n";
        echo 'Порт: ' . (defined('LDAP_PORT') ? LDAP_PORT : '389') . "\n";
        echo 'Base DN: ' . (defined('LDAP_BASE_DN') ? LDAP_BASE_DN : 'не задан') . "\n";
        echo '</pre>';
        
        // Попытка подключения
        echo '<h3>Попытка подключения...</h3>';
        
        $ldap_host = defined('LDAP_HOST') ? LDAP_HOST : 'ldap://dc.svgtk.local';
        $ldap_port = defined('LDAP_PORT') ? LDAP_PORT : 389;
        
        $ldap = @ldap_connect($ldap_host, $ldap_port);
        
        if ($ldap) {
            echo '<p class="success">✓ Подключение к LDAP серверу установлено!</p>';
            
            // Устанавливаем опции
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            
            echo '<p class="info">Протокол LDAP v3 настроен.</p>';
            
            // Проверяем доступность сервера
            $bind = @ldap_bind($ldap);
            if ($bind) {
                echo '<p class="success">✓ Анонимное подключение к AD успешно!</p>';
                echo '<p>Это значит что:</p>';
                echo '<ul>';
                echo '<li>Сервер Active Directory доступен</li>';
                echo '<li>Настройки в config/ldap.php правильные</li>';
                echo '<li>LDAP аутентификация должна работать</li>';
                echo '</ul>';
            } else {
                echo '<p class="error">✗ Не удалось выполнить bind к AD</p>';
                echo '<p>Возможные причины:</p>';
                echo '<ul>';
                echo '<li>Неправильный LDAP_HOST в config/ldap.php</li>';
                echo '<li>Firewall блокирует порт 389</li>';
                echo '<li>Сервер Active Directory недоступен</li>';
                echo '</ul>';
                echo '<p>Ошибка: ' . ldap_error($ldap) . '</p>';
            }
            
            ldap_close($ldap);
        } else {
            echo '<p class="error">✗ Не удалось подключиться к LDAP серверу</p>';
            echo '<p>Проверьте:</p>';
            echo '<ul>';
            echo '<li>Правильность адреса сервера: ' . htmlspecialchars($ldap_host) . '</li>';
            echo '<li>Доступность порта: ' . $ldap_port . '</li>';
            echo '<li>Настройки firewall</li>';
            echo '<li>Работает ли служба Active Directory</li>';
            echo '</ul>';
        }
        ?>
    </div>
    
    <div class="box">
        <h2>3. Тест аутентификации (опционально)</h2>
        <p>Если хотите проверить аутентификацию конкретного пользователя:</p>
        <form method="POST">
            <p>
                <label>Логин: <input type="text" name="test_username" placeholder="user"></label>
            </p>
            <p>
                <label>Пароль: <input type="password" name="test_password"></label>
            </p>
            <p>
                <button type="submit">Проверить аутентификацию</button>
            </p>
        </form>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_username'])) {
            $username = $_POST['test_username'];
            $password = $_POST['test_password'];
            
            if (!empty($username) && !empty($password)) {
                echo '<h3>Результат аутентификации:</h3>';
                
                $ldap = @ldap_connect($ldap_host, $ldap_port);
                if ($ldap) {
                    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
                    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
                    
                    $domain = defined('LDAP_DOMAIN') ? LDAP_DOMAIN : 'SVGTK';
                    $userdn = $username . '@' . $domain;
                    
                    $bind = @ldap_bind($ldap, $userdn, $password);
                    
                    if ($bind) {
                        echo '<p class="success">✓ Аутентификация успешна!</p>';
                        echo '<p>Пользователь <strong>' . htmlspecialchars($username) . '</strong> прошел проверку в Active Directory.</p>';
                    } else {
                        echo '<p class="error">✗ Аутентификация не удалась</p>';
                        echo '<p>Причины:</p>';
                        echo '<ul>';
                        echo '<li>Неверный логин или пароль</li>';
                        echo '<li>Пользователь заблокирован в AD</li>';
                        echo '<li>Неправильный домен: ' . htmlspecialchars($domain) . '</li>';
                        echo '</ul>';
                        echo '<p>Ошибка LDAP: ' . ldap_error($ldap) . '</p>';
                    }
                    
                    ldap_close($ldap);
                } else {
                    echo '<p class="error">Не удалось подключиться к LDAP серверу</p>';
                }
            } else {
                echo '<p class="error">Введите логин и пароль</p>';
            }
        }
        ?>
    </div>
    
    <?php endif; ?>
    
    <div class="box">
        <h2>4. Информация о PHP</h2>
        <p><strong>Версия PHP:</strong> <?php echo phpversion(); ?></p>
        <p><strong>Загруженные расширения:</strong></p>
        <pre><?php
        $extensions = get_loaded_extensions();
        sort($extensions);
        foreach ($extensions as $ext) {
            if (stripos($ext, 'ldap') !== false) {
                echo "✓ $ext (LDAP установлен!)\n";
            }
        }
        if (!in_array('ldap', $extensions)) {
            echo "✗ LDAP не найден в загруженных расширениях\n\n";
            echo "Все расширения:\n";
            echo implode(', ', $extensions);
        }
        ?></pre>
    </div>
    
    <div class="box">
        <h2>5. Следующие шаги</h2>
        <?php if (function_exists('ldap_connect')): ?>
            <p class="success">✓ LDAP установлен</p>
            <p>Теперь можете:</p>
            <ol>
                <li>Попробовать войти в систему через <a href="index.php">страницу входа</a></li>
                <li>Если не работает - отправьте скриншот этой страницы администратору</li>
            </ol>
        <?php else: ?>
            <p class="error">✗ LDAP не установлен</p>
            <p>Следуйте инструкции в файле УСТАНОВКА_PHP_LDAP_IIS.txt</p>
        <?php endif; ?>
    </div>
    
    <div class="box">
        <p style="text-align: center; color: #666; font-size: 12px;">
            <a href="index.php">← Вернуться на страницу входа</a> | 
            <a href="check_error.php">Проверка ошибок</a>
        </p>
    </div>
</body>
</html>
