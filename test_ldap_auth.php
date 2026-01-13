<?php
/**
 * test_ldap_auth.php
 * Проверка авторизации пользователей через Active Directory
 */

session_start();
header('Content-Type: text/html; charset=utf-8');

// ========================================
// НАСТРОЙКИ - ИЗМЕНИТЕ НА СВОИ!
// ========================================
$config = [
    'ldap_host' => 'shc.local',              // Имя домена или IP контроллера
    'ldap_port' => 389,                       // Порт LDAP (389 или 636 для LDAPS)
    'ldap_base_dn' => 'DC=shc,DC=local',     // Base Distinguished Name
    'ldap_domain' => '@shc.local',           // Домен для пользователей
    'use_ssl' => false,                       // true для LDAPS (порт 636)
];

// Обработка формы
$test_result = null;
$user_info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_auth'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $test_result = [
            'success' => false,
            'message' => 'Заполните все поля!'
        ];
    } else {
        $test_result = testLDAPAuth($username, $password, $config);
    }
}

/**
 * Функция для тестирования LDAP авторизации
 */
function testLDAPAuth($username, $password, $config) {
    $result = [
        'success' => false,
        'message' => '',
        'details' => [],
        'user_info' => null
    ];
    
    // Шаг 1: Подключение к LDAP
    $ldap_uri = ($config['use_ssl'] ? 'ldaps://' : 'ldap://') . $config['ldap_host'];
    
    $result['details'][] = [
        'step' => 'Подключение к LDAP серверу',
        'info' => $ldap_uri . ':' . $config['ldap_port'],
        'status' => 'processing'
    ];
    
    $ldap_conn = @ldap_connect($ldap_uri, $config['ldap_port']);
    
    if (!$ldap_conn) {
        $result['message'] = 'Не удалось создать соединение с LDAP сервером';
        $result['details'][0]['status'] = 'error';
        return $result;
    }
    
    $result['details'][0]['status'] = 'success';
    
    // Шаг 2: Установка опций
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, 10);
    
    $result['details'][] = [
        'step' => 'Настройка параметров',
        'info' => 'Protocol v3, Timeout 10s',
        'status' => 'success'
    ];
    
    // Шаг 3: Попытка авторизации
    // Пробуем разные форматы логина
    $login_formats = [
        $username . $config['ldap_domain'],  // user@domain.local
        $config['ldap_host'] . '\\' . $username,  // DOMAIN\user
        'CN=' . $username . ',CN=Users,' . $config['ldap_base_dn']  // DN формат
    ];
    
    $bind_success = false;
    $used_format = '';
    
    foreach ($login_formats as $login) {
        $result['details'][] = [
            'step' => 'Попытка авторизации',
            'info' => 'Формат: ' . $login,
            'status' => 'processing'
        ];
        
        $bind_result = @ldap_bind($ldap_conn, $login, $password);
        
        if ($bind_result) {
            $bind_success = true;
            $used_format = $login;
            $result['details'][count($result['details']) - 1]['status'] = 'success';
            break;
        } else {
            $error = ldap_error($ldap_conn);
            $result['details'][count($result['details']) - 1]['status'] = 'error';
            $result['details'][count($result['details']) - 1]['error'] = $error;
        }
    }
    
    if (!$bind_success) {
        $result['message'] = 'Авторизация не удалась. Проверьте логин и пароль.';
        ldap_close($ldap_conn);
        return $result;
    }
    
    // Шаг 4: Успешная авторизация - получаем данные пользователя
    $result['details'][] = [
        'step' => 'Авторизация успешна',
        'info' => 'Использован формат: ' . $used_format,
        'status' => 'success'
    ];
    
    // Поиск информации о пользователе
    $search_filter = "(sAMAccountName=$username)";
    $search_result = @ldap_search($ldap_conn, $config['ldap_base_dn'], $search_filter);
    
    if ($search_result) {
        $entries = ldap_get_entries($ldap_conn, $search_result);
        
        if ($entries['count'] > 0) {
            $user_data = $entries[0];
            
            $result['user_info'] = [
                'Логин (sAMAccountName)' => $user_data['samaccountname'][0] ?? 'N/A',
                'Полное имя (CN)' => $user_data['cn'][0] ?? 'N/A',
                'Отображаемое имя' => $user_data['displayname'][0] ?? 'N/A',
                'Email' => $user_data['mail'][0] ?? 'N/A',
                'Отдел' => $user_data['department'][0] ?? 'N/A',
                'Должность' => $user_data['title'][0] ?? 'N/A',
                'Телефон' => $user_data['telephonenumber'][0] ?? 'N/A',
                'Distinguished Name' => $user_data['dn'] ?? 'N/A',
                'User Principal Name' => $user_data['userprincipalname'][0] ?? 'N/A',
                'Когда создан' => $user_data['whencreated'][0] ?? 'N/A',
                'Последний вход' => $user_data['lastlogon'][0] ?? 'N/A'
            ];
            
            // Группы пользователя
            if (isset($user_data['memberof'])) {
                $groups = [];
                for ($i = 0; $i < $user_data['memberof']['count']; $i++) {
                    // Извлекаем только CN из полного DN
                    preg_match('/CN=([^,]+)/', $user_data['memberof'][$i], $matches);
                    $groups[] = $matches[1] ?? $user_data['memberof'][$i];
                }
                $result['user_info']['Группы'] = implode(', ', $groups);
            }
            
            $result['details'][] = [
                'step' => 'Получение данных пользователя',
                'info' => 'Найдено полей: ' . count($result['user_info']),
                'status' => 'success'
            ];
        }
    }
    
    ldap_close($ldap_conn);
    
    $result['success'] = true;
    $result['message'] = 'Авторизация прошла успешно!';
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест авторизации Active Directory</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .config-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        .config-info h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .config-item {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .config-item:last-child {
            border-bottom: none;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .result-box {
            margin-top: 30px;
            padding: 25px;
            border-radius: 10px;
            animation: slideIn 0.5s;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .result-success {
            background: #d4edda;
            border: 2px solid #c3e6cb;
        }
        .result-error {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
        }
        .result-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .result-success .result-title {
            color: #155724;
        }
        .result-error .result-title {
            color: #721c24;
        }
        .timeline {
            margin: 20px 0;
        }
        .timeline-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #ddd;
            background: #f8f9fa;
        }
        .timeline-item.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .timeline-item.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .timeline-item.processing {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .user-info {
            margin-top: 20px;
        }
        .user-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .user-info-table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .user-info-table td:first-child {
            font-weight: 600;
            width: 200px;
            color: #495057;
        }
        .icon {
            font-size: 20px;
            margin-right: 10px;
        }
        .hint {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Тест авторизации Active Directory</h1>
            <p>Проверка входа пользователей через LDAP</p>
        </div>
        
        <div class="content">
            <!-- Настройки -->
            <div class="config-info">
                <h3>⚙️ Настройки подключения</h3>
                <div class="config-item">
                    <strong>LDAP Host:</strong> <?php echo htmlspecialchars($config['ldap_host']); ?>
                </div>
                <div class="config-item">
                    <strong>LDAP Port:</strong> <?php echo $config['ldap_port']; ?>
                </div>
                <div class="config-item">
                    <strong>Base DN:</strong> <?php echo htmlspecialchars($config['ldap_base_dn']); ?>
                </div>
                <div class="config-item">
                    <strong>Домен:</strong> <?php echo htmlspecialchars($config['ldap_domain']); ?>
                </div>
            </div>
            
            <!-- Форма авторизации -->
            <form method="POST">
                <div class="form-group">
                    <label for="username">
                        👤 Логин пользователя
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           placeholder="ivanov" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           required>
                    <small style="color: #6c757d; margin-top: 5px; display: block;">
                        Только логин, без домена (например: ivanov)
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        🔑 Пароль
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="••••••••" 
                           required>
                </div>
                
                <button type="submit" name="test_auth" class="btn">
                    <span class="icon">🚀</span>
                    Проверить авторизацию
                </button>
            </form>
            
            <!-- Результаты теста -->
            <?php if ($test_result !== null): ?>
                <div class="result-box <?php echo $test_result['success'] ? 'result-success' : 'result-error'; ?>">
                    <div class="result-title">
                        <span class="icon"><?php echo $test_result['success'] ? '✅' : '❌'; ?></span>
                        <?php echo htmlspecialchars($test_result['message']); ?>
                    </div>
                    
                    <!-- Процесс авторизации -->
                    <?php if (!empty($test_result['details'])): ?>
                        <div class="timeline">
                            <h4 style="margin-bottom: 15px;">📋 Процесс авторизации:</h4>
                            <?php foreach ($test_result['details'] as $detail): ?>
                                <div class="timeline-item <?php echo $detail['status']; ?>">
                                    <strong><?php echo htmlspecialchars($detail['step']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($detail['info']); ?></small>
                                    <?php if (isset($detail['error'])): ?>
                                        <br><small style="color: #dc3545;">Ошибка: <?php echo htmlspecialchars($detail['error']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Информация о пользователе -->
                    <?php if ($test_result['success'] && !empty($test_result['user_info'])): ?>
                        <div class="user-info">
                            <h4 style="margin-bottom: 15px; color: #155724;">👤 Информация о пользователе:</h4>
                            <table class="user-info-table">
                                <?php foreach ($test_result['user_info'] as $key => $value): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($key); ?>:</td>
                                        <td><?php echo htmlspecialchars($value); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Подсказки -->
            <div class="hint">
                <strong>💡 Подсказки:</strong><br>
                • Используйте логин без домена (например: <code>ivanov</code>, а не <code>ivanov@shc.local</code>)<br>
                • Убедитесь, что пользователь существует в Active Directory<br>
                • Пароль проверяется с учетом регистра<br>
                • При успешной авторизации вы увидите все данные пользователя из AD
            </div>
        </div>
    </div>
    
    <div style="text-align: center; color: white; margin-top: 20px; padding: 20px;">
        <small>
            🕐 <?php echo date('d.m.Y H:i:s'); ?> | 
            💻 <?php echo $_SERVER['SERVER_NAME']; ?>
        </small>
    </div>
</body>
</html>
