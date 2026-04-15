<?php
/**
 * test_ldap_connection.php
 * Проверка подключения к Active Directory контроллеру домена
 */

header('Content-Type: text/html; charset=utf-8');

// ========================================
// НАСТРОЙКИ - ИЗМЕНИТЕ НА СВОИ!
// ========================================
$config = [
    'ldap_host' => 'shc.local',           // Имя домена или IP контроллера
    'ldap_port' => 389,                    // Порт LDAP (389 или 636 для LDAPS)
    'ldap_base_dn' => 'DC=shc,DC=local',  // Base Distinguished Name
    'use_ssl' => false,                    // true для LDAPS (порт 636)
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест подключения к Active Directory</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
            border-bottom: 3px solid #2196F3;
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
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .config-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            margin: 20px 0;
        }
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #fff;
            border: 1px solid #ddd;
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
            background: #2196F3;
            color: white;
            padding: 12px;
            text-align: left;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
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
        .step {
            background: #e3f2fd;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #2196F3;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌐 Проверка подключения к Active Directory</h1>
        
        <!-- Текущие настройки -->
        <h2>⚙️ Текущие настройки</h2>
        <div class="config-box">
            <table>
                <tr>
                    <th>Параметр</th>
                    <th>Значение</th>
                </tr>
                <tr>
                    <td><strong>LDAP Host</strong></td>
                    <td><?php echo htmlspecialchars($config['ldap_host']); ?></td>
                </tr>
                <tr>
                    <td><strong>LDAP Port</strong></td>
                    <td><?php echo $config['ldap_port']; ?></td>
                </tr>
                <tr>
                    <td><strong>Base DN</strong></td>
                    <td><?php echo htmlspecialchars($config['ldap_base_dn']); ?></td>
                </tr>
                <tr>
                    <td><strong>Использовать SSL</strong></td>
                    <td><?php echo $config['use_ssl'] ? 'Да (LDAPS)' : 'Нет (LDAP)'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="warning">
            <strong>⚠️ Внимание!</strong> Если настройки неверные, отредактируйте их в начале файла test_ldap_connection.php
        </div>
        
        <?php
        // Проверка 1: LDAP модуль установлен?
        echo "<h2>✅ Шаг 1: Проверка LDAP модуля</h2>";
        
        if (!function_exists('ldap_connect')) {
            echo '<div class="status error">';
            echo '<span class="icon">❌</span>';
            echo 'LDAP модуль не установлен! Сначала запустите test_ldap_module.php';
            echo '</div>';
            exit;
        }
        
        echo '<div class="status success">';
        echo '<span class="icon">✅</span>';
        echo 'LDAP модуль установлен и готов к работе';
        echo '</div>';
        
        // Проверка 2: DNS резолвинг
        echo "<h2>🔍 Шаг 2: Проверка DNS резолвинга</h2>";
        
        $ip_address = gethostbyname($config['ldap_host']);
        
        echo '<div class="test-result">';
        echo '<strong>Попытка разрешить:</strong> ' . htmlspecialchars($config['ldap_host']) . '<br>';
        
        if ($ip_address === $config['ldap_host']) {
            echo '<div class="status error" style="margin-top: 10px;">';
            echo '<span class="icon">❌</span>';
            echo 'DNS не может разрешить имя хоста!<br>';
            echo '<small>Возможно, сервер не находится в домене или DNS настроен неправильно.</small>';
            echo '</div>';
            
            echo '<div class="info" style="margin-top: 15px;">';
            echo '<strong>🔧 Попробуйте:</strong><br>';
            echo '1. Использовать IP адрес контроллера домена вместо имени<br>';
            echo '2. Проверить DNS настройки сервера<br>';
            echo '3. Добавить запись в hosts файл';
            echo '</div>';
        } else {
            echo '<strong>Результат:</strong> <span style="color: green;">✅ ' . $ip_address . '</span><br>';
            echo '<div class="status success" style="margin-top: 10px;">';
            echo '<span class="icon">✅</span>';
            echo 'DNS успешно разрешил имя хоста!';
            echo '</div>';
        }
        echo '</div>';
        
        // Проверка 3: Проверка доступности порта
        echo "<h2>🔌 Шаг 3: Проверка доступности порта</h2>";
        
        $ldap_uri = ($config['use_ssl'] ? 'ldaps://' : 'ldap://') . $config['ldap_host'];
        
        echo '<div class="test-result">';
        echo '<strong>Попытка подключения к:</strong> ' . $ldap_uri . ':' . $config['ldap_port'] . '<br><br>';
        
        $connection = @fsockopen($config['ldap_host'], $config['ldap_port'], $errno, $errstr, 5);
        
        if ($connection) {
            fclose($connection);
            echo '<div class="status success">';
            echo '<span class="icon">✅</span>';
            echo 'Порт ' . $config['ldap_port'] . ' доступен!';
            echo '</div>';
            $port_ok = true;
        } else {
            echo '<div class="status error">';
            echo '<span class="icon">❌</span>';
            echo 'Не удалось подключиться к порту ' . $config['ldap_port'] . '<br>';
            echo '<small>Ошибка: ' . htmlspecialchars($errstr) . ' (код: ' . $errno . ')</small>';
            echo '</div>';
            
            echo '<div class="info" style="margin-top: 15px;">';
            echo '<strong>🔧 Возможные причины:</strong><br>';
            echo '• Firewall блокирует порт ' . $config['ldap_port'] . '<br>';
            echo '• Контроллер домена недоступен<br>';
            echo '• Неверный IP адрес или имя хоста<br>';
            echo '• LDAP служба не запущена на контроллере';
            echo '</div>';
            $port_ok = false;
        }
        echo '</div>';
        
        // Проверка 4: Подключение к LDAP
        if ($port_ok) {
            echo "<h2>🔗 Шаг 4: Подключение к LDAP серверу</h2>";
            
            echo '<div class="test-result">';
            
            $ldap_conn = @ldap_connect($ldap_uri, $config['ldap_port']);
            
            if ($ldap_conn) {
                echo '<strong>Создание соединения:</strong> <span style="color: green;">✅ Успешно</span><br><br>';
                
                // Установка опций LDAP
                ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
                ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, 5);
                
                echo '<strong>Установленные опции:</strong><br>';
                echo '<div class="code">';
                echo 'LDAP_OPT_PROTOCOL_VERSION = 3<br>';
                echo 'LDAP_OPT_REFERRALS = 0<br>';
                echo 'LDAP_OPT_NETWORK_TIMEOUT = 5 секунд';
                echo '</div>';
                
                // Попытка анонимного bind
                echo '<strong>Попытка анонимного подключения:</strong><br>';
                
                $bind_result = @ldap_bind($ldap_conn);
                
                if ($bind_result) {
                    echo '<div class="status success" style="margin-top: 10px;">';
                    echo '<span class="icon">🎉</span>';
                    echo '<strong>ОТЛИЧНО!</strong> Подключение к Active Directory успешно установлено!';
                    echo '</div>';
                    
                    // Пробуем получить информацию о домене
                    echo '<br><strong>Информация о домене:</strong><br>';
                    $search = @ldap_read($ldap_conn, "", "(objectClass=*)", ["*", "+"]);
                    if ($search) {
                        $entries = ldap_get_entries($ldap_conn, $search);
                        if ($entries['count'] > 0) {
                            echo '<div class="code">';
                            echo 'Naming Context: ' . ($entries[0]['namingcontexts'][0] ?? 'N/A') . '<br>';
                            echo 'Default Naming Context: ' . ($entries[0]['defaultnamingcontext'][0] ?? 'N/A') . '<br>';
                            echo 'DNS Hostname: ' . ($entries[0]['dnshostname'][0] ?? 'N/A');
                            echo '</div>';
                        }
                    }
                } else {
                    $error = ldap_error($ldap_conn);
                    $errno = ldap_errno($ldap_conn);
                    
                    echo '<div class="status warning" style="margin-top: 10px;">';
                    echo '<span class="icon">⚠️</span>';
                    echo 'Анонимное подключение отклонено (это нормально для AD)<br>';
                    echo '<small>Ошибка: ' . htmlspecialchars($error) . ' (код: ' . $errno . ')</small>';
                    echo '</div>';
                    
                    echo '<div class="info" style="margin-top: 15px;">';
                    echo '<strong>ℹ️ Это нормально!</strong><br>';
                    echo 'Active Directory обычно не разрешает анонимные подключения.<br>';
                    echo 'Для полноценной авторизации нужны учетные данные пользователя.<br>';
                    echo 'Перейдите к следующему тесту: <strong>test_ldap_auth.php</strong>';
                    echo '</div>';
                }
                
                ldap_close($ldap_conn);
                
            } else {
                $error = ldap_error($ldap_conn);
                
                echo '<div class="status error">';
                echo '<span class="icon">❌</span>';
                echo 'Не удалось создать LDAP соединение<br>';
                echo '<small>Ошибка: ' . htmlspecialchars($error) . '</small>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        // Итоговый результат
        echo "<h2>🎯 Итоговый результат</h2>";
        
        if ($port_ok) {
            echo '<div class="status success" style="font-size: 18px;">';
            echo '<span class="icon">🎉</span>';
            echo '<strong>УСПЕШНО!</strong> Подключение к Active Directory работает!<br>';
            echo 'Можно переходить к тестированию авторизации.';
            echo '</div>';
            
            echo '<div class="info" style="margin-top: 20px;">';
            echo '<h3>📌 Следующий шаг:</h3>';
            echo '<p>Запустите скрипт <strong>test_ldap_auth.php</strong> для проверки авторизации с реальными учетными данными.</p>';
            echo '</div>';
        } else {
            echo '<div class="status error" style="font-size: 18px;">';
            echo '<span class="icon">⚠️</span>';
            echo '<strong>ТРЕБУЕТСЯ НАСТРОЙКА!</strong> Подключение не установлено.<br>';
            echo 'Проверьте настройки сети и firewall.';
            echo '</div>';
        }
        
        // Диагностическая информация
        echo "<h2>🛠️ Диагностическая информация</h2>";
        echo '<div class="code">';
        echo '<strong>Команды для диагностики (Windows):</strong><br><br>';
        echo '# Проверка DNS:<br>';
        echo 'nslookup ' . htmlspecialchars($config['ldap_host']) . '<br><br>';
        echo '# Проверка доступности порта:<br>';
        echo 'Test-NetConnection -ComputerName ' . htmlspecialchars($config['ldap_host']) . ' -Port ' . $config['ldap_port'] . '<br><br>';
        echo '# Проверка домена:<br>';
        echo 'echo %USERDNSDOMAIN%<br><br>';
        echo '# Ping контроллера домена:<br>';
        echo 'ping ' . htmlspecialchars($config['ldap_host']);
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
