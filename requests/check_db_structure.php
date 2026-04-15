<?php
/**
 * check_db_structure.php
 * Диагностический скрипт для проверки структуры таблицы users
 */

require_once 'config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка структуры БД</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
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
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .code {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 15px 0;
            white-space: pre;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Проверка структуры таблицы users</h1>
        
        <?php
        try {
            // Проверяем структуру таблицы
            echo "<h2>📋 Структура таблицы users</h2>";
            
            $stmt = $pdo->query("DESCRIBE users");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table>';
            echo '<tr><th>Поле</th><th>Тип</th><th>NULL</th><th>Ключ</th><th>По умолчанию</th><th>Extra</th></tr>';
            
            foreach ($columns as $column) {
                $isRequired = $column['Null'] === 'NO' && $column['Default'] === null && $column['Extra'] !== 'auto_increment';
                $rowStyle = $isRequired ? 'background: #fff3cd;' : '';
                
                echo '<tr style="' . $rowStyle . '">';
                echo '<td><strong>' . htmlspecialchars($column['Field']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
                echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($column['Extra']) . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            
            // Проверяем обязательные поля
            echo "<h2>⚠️ Обязательные поля (NOT NULL без значения по умолчанию)</h2>";
            
            $requiredFields = array_filter($columns, function($col) {
                return $col['Null'] === 'NO' 
                    && $col['Default'] === null 
                    && $col['Extra'] !== 'auto_increment';
            });
            
            if (count($requiredFields) > 0) {
                echo '<div class="warning">';
                echo '<strong>Следующие поля ОБЯЗАТЕЛЬНЫ при INSERT:</strong><ul>';
                foreach ($requiredFields as $field) {
                    echo '<li><strong>' . htmlspecialchars($field['Field']) . '</strong> (' . htmlspecialchars($field['Type']) . ')</li>';
                }
                echo '</ul></div>';
            } else {
                echo '<div class="success">Все поля имеют значения по умолчанию или могут быть NULL</div>';
            }
            
            // Проверяем последние 5 записей
            echo "<h2>📊 Последние 5 пользователей</h2>";
            
            $stmt = $pdo->query("SELECT id, username, full_name, email, role, created_at FROM users ORDER BY id DESC LIMIT 5");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($users) > 0) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Created At</th></tr>';
                
                foreach ($users as $user) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($user['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['full_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['role']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['created_at']) . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            } else {
                echo '<div class="warning">Таблица пуста</div>';
            }
            
            // Тестовый INSERT запрос
            echo "<h2>🧪 Тестирование INSERT запроса</h2>";
            
            echo '<div class="code">';
            echo "INSERT INTO users \n";
            echo "  (username, full_name, email, password, role, position, created_at) \n";
            echo "VALUES \n";
            echo "  ('test_ldap_user', 'Тестовый LDAP Пользователь', 'test@shc.local', \n";
            echo "   '[HASHED_PASSWORD]', 'teacher', 'Преподаватель', NOW())";
            echo '</div>';
            
            try {
                $pdo->beginTransaction();
                
                $testPassword = password_hash('dummy_password_12345', PASSWORD_DEFAULT);
                $testStmt = $pdo->prepare("
                    INSERT INTO users (username, full_name, email, password, role, position, created_at) 
                    VALUES (?, ?, ?, ?, 'teacher', 'Преподаватель', NOW())
                ");
                
                $testResult = $testStmt->execute([
                    'test_ldap_' . time(),
                    'Тестовый LDAP Пользователь',
                    'test_' . time() . '@shc.local',
                    $testPassword
                ]);
                
                $pdo->rollBack(); // Откатываем тестовую запись
                
                if ($testResult) {
                    echo '<div class="success">✅ Тестовый INSERT запрос выполнен успешно!</div>';
                } else {
                    echo '<div class="error">❌ Тестовый INSERT запрос не выполнен<br>';
                    echo 'Error: ' . print_r($testStmt->errorInfo(), true) . '</div>';
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo '<div class="error">❌ Ошибка при тестировании INSERT:<br>';
                echo htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            // Проверяем уникальные индексы
            echo "<h2>🔑 Уникальные индексы и ограничения</h2>";
            
            $stmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name != 'PRIMARY'");
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($indexes) > 0) {
                echo '<table>';
                echo '<tr><th>Индекс</th><th>Поле</th><th>Уникальный</th></tr>';
                
                foreach ($indexes as $index) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($index['Key_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($index['Column_name']) . '</td>';
                    echo '<td>' . ($index['Non_unique'] == 0 ? '✅ Да' : 'Нет') . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                
                // Проверяем на дубликаты username
                echo "<h3>Проверка на дубликаты username</h3>";
                $stmt = $pdo->query("
                    SELECT username, COUNT(*) as count 
                    FROM users 
                    GROUP BY username 
                    HAVING count > 1
                ");
                $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($duplicates) > 0) {
                    echo '<div class="error"><strong>❌ Найдены дубликаты:</strong><ul>';
                    foreach ($duplicates as $dup) {
                        echo '<li>' . htmlspecialchars($dup['username']) . ' (' . $dup['count'] . ' раз)</li>';
                    }
                    echo '</ul></div>';
                } else {
                    echo '<div class="success">✅ Дубликатов не найдено</div>';
                }
                
            } else {
                echo '<div class="warning">Дополнительных индексов не найдено</div>';
            }
            
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<strong>❌ Ошибка подключения к БД:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px;">
            <h3>📝 Рекомендации для LDAP авторизации</h3>
            <ol>
                <li>Поле <code>password</code> должно быть заполнено для LDAP пользователей случайным хешем</li>
                <li>Поле <code>email</code> можно генерировать как <code>username@shc.local</code> если отсутствует в AD</li>
                <li>Убедитесь, что username уникален перед INSERT</li>
                <li>Используйте транзакции для безопасности</li>
            </ol>
        </div>
    </div>
</body>
</html>
