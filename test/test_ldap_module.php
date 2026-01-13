<?php
/**
 * test_ldap_module.php
 * Проверка установки и работы PHP LDAP модуля
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест LDAP модуля</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .check-item {
            padding: 10px;
            margin: 10px 0;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .icon {
            font-size: 24px;
            margin-right: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th {
            background: #4CAF50;
            color: white;
            padding: 12px;
            text-align: left;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        table tr:hover {
            background: #f5f5f5;
        }
        .code {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Проверка PHP LDAP модуля</h1>
        
        <?php
        // Проверка 1: Наличие функции ldap_connect
        echo "<h2>✅ Проверка 1: LDAP модуль установлен?</h2>";
        
        if (function_exists('ldap_connect')) {
            echo '<div class="status success">';
            echo '<span class="icon">✅</span>';
            echo 'LDAP модуль установлен и активен!';
            echo '</div>';
            
            $ldap_installed = true;
        } else {
            echo '<div class="status error">';
            echo '<span class="icon">❌</span>';
            echo 'LDAP модуль НЕ установлен или не активирован!';
            echo '</div>';
            
            echo '<div class="info" style="margin-top: 20px;">';
            echo '<h3>🔧 Как исправить:</h3>';
            echo '<p><strong>Для Windows (OpenServer/XAMPP):</strong></p>';
            echo '<div class="code">';
            echo '1. Откройте файл php.ini<br>';
            echo '2. Найдите строку: ;extension=ldap<br>';
            echo '3. Уберите точку с запятой: extension=ldap<br>';
            echo '4. Перезапустите веб-сервер';
            echo '</div>';
            
            echo '<p><strong>Для Linux (Ubuntu/Debian):</strong></p>';
            echo '<div class="code">';
            echo 'sudo apt-get install php-ldap<br>';
            echo 'sudo service apache2 restart';
            echo '</div>';
            echo '</div>';
            
            $ldap_installed = false;
        }
        
        // Проверка 2: Список доступных LDAP функций
        if ($ldap_installed) {
            echo "<h2>📋 Проверка 2: Доступные LDAP функции</h2>";
            
            $ldap_functions = [
                'ldap_connect' => 'Подключение к LDAP серверу',
                'ldap_bind' => 'Авторизация на LDAP сервере',
                'ldap_search' => 'Поиск в LDAP',
                'ldap_get_entries' => 'Получение результатов поиска',
                'ldap_set_option' => 'Установка опций LDAP',
                'ldap_close' => 'Закрытие соединения',
                'ldap_error' => 'Получение текста ошибки',
                'ldap_errno' => 'Получение кода ошибки'
            ];
            
            echo '<table>';
            echo '<tr><th>Функция</th><th>Описание</th><th>Статус</th></tr>';
            
            $all_ok = true;
            foreach ($ldap_functions as $func => $desc) {
                echo '<tr>';
                echo '<td><code>' . $func . '()</code></td>';
                echo '<td>' . $desc . '</td>';
                echo '<td>';
                if (function_exists($func)) {
                    echo '<span style="color: green;">✅ Доступна</span>';
                } else {
                    echo '<span style="color: red;">❌ Недоступна</span>';
                    $all_ok = false;
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            if ($all_ok) {
                echo '<div class="status success">';
                echo '<span class="icon">✅</span>';
                echo 'Все необходимые LDAP функции доступны!';
                echo '</div>';
            }
        }
        
        // Проверка 3: Версия PHP и расширения
        echo "<h2>ℹ️ Проверка 3: Информация о системе</h2>";
        
        echo '<table>';
        echo '<tr><th>Параметр</th><th>Значение</th></tr>';
        echo '<tr><td>Версия PHP</td><td>' . phpversion() . '</td></tr>';
        echo '<tr><td>Операционная система</td><td>' . PHP_OS . '</td></tr>';
        echo '<tr><td>Сервер</td><td>' . $_SERVER['SERVER_SOFTWARE'] . '</td></tr>';
        
        if ($ldap_installed) {
            // Получаем версию LDAP библиотеки
            $ldap_info = [];
            $ldap_temp = ldap_connect('localhost');
            if ($ldap_temp) {
                ldap_get_option($ldap_temp, LDAP_OPT_PROTOCOL_VERSION, $protocol_version);
                $ldap_info['Версия протокола LDAP'] = $protocol_version;
                @ldap_close($ldap_temp);
            }
            
            foreach ($ldap_info as $key => $value) {
                echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
            }
        }
        
        echo '<tr><td>Путь к php.ini</td><td>' . php_ini_loaded_file() . '</td></tr>';
        echo '</table>';
        
        // Проверка 4: Загруженные расширения
        echo "<h2>📦 Проверка 4: Загруженные PHP расширения</h2>";
        
        $extensions = get_loaded_extensions();
        $ldap_found = in_array('ldap', $extensions);
        
        echo '<div class="check-item">';
        echo '<strong>Всего загружено расширений:</strong> ' . count($extensions) . '<br>';
        echo '<strong>LDAP расширение:</strong> ';
        if ($ldap_found) {
            echo '<span style="color: green; font-weight: bold;">✅ Загружено</span>';
        } else {
            echo '<span style="color: red; font-weight: bold;">❌ Не найдено</span>';
        }
        echo '</div>';
        
        // Финальный результат
        echo "<h2>🎯 Итоговый результат</h2>";
        
        if ($ldap_installed && $ldap_found) {
            echo '<div class="status success" style="font-size: 18px;">';
            echo '<span class="icon">🎉</span>';
            echo '<strong>ВСЁ ГОТОВО!</strong> LDAP модуль работает корректно.<br>';
            echo 'Можно переходить к проверке подключения к Active Directory.';
            echo '</div>';
            
            echo '<div class="info" style="margin-top: 20px;">';
            echo '<h3>📌 Следующий шаг:</h3>';
            echo '<p>Запустите скрипт <strong>test_ldap_connection.php</strong> для проверки подключения к вашему контроллеру домена.</p>';
            echo '</div>';
        } else {
            echo '<div class="status error" style="font-size: 18px;">';
            echo '<span class="icon">⚠️</span>';
            echo '<strong>ТРЕБУЕТСЯ НАСТРОЙКА!</strong> LDAP модуль не активен.<br>';
            echo 'Следуйте инструкциям выше для активации модуля.';
            echo '</div>';
        }
        
        // Дополнительная информация
        echo "<h2>📚 Полезные ссылки</h2>";
        echo '<div class="check-item">';
        echo '<ul>';
        echo '<li><a href="https://www.php.net/manual/ru/book.ldap.php" target="_blank">Официальная документация PHP LDAP</a></li>';
        echo '<li><a href="https://www.php.net/manual/ru/ldap.installation.php" target="_blank">Установка LDAP расширения</a></li>';
        echo '<li><a href="https://www.php.net/manual/ru/ldap.configuration.php" target="_blank">Настройка LDAP</a></li>';
        echo '</ul>';
        echo '</div>';
        ?>
        
        <div style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px; text-align: center;">
            <small>
                🕐 Дата проверки: <?php echo date('d.m.Y H:i:s'); ?><br>
                💻 Сервер: <?php echo $_SERVER['SERVER_NAME']; ?>
            </small>
        </div>
    </div>
</body>
</html>
