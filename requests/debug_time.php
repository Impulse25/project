<?php
// debug_time.php - Профилирование загрузки страницы
// Положите этот файл в корень проекта и откройте в браузере

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Засекаем общее время
$startTime = microtime(true);
$checkpoints = [];

function checkpoint($name) {
    global $startTime, $checkpoints;
    $currentTime = microtime(true);
    $elapsed = round(($currentTime - $startTime) * 1000, 2);
    $checkpoints[] = [
        'name' => $name,
        'time' => $elapsed
    ];
}

checkpoint('START');

// ════════════════════════════════════════════════════════════════
// ТЕСТ 1: Подключение к БД
// ════════════════════════════════════════════════════════════════
checkpoint('Before DB connect');

try {
    require_once 'config/database.php';
    checkpoint('After DB connect');
} catch (Exception $e) {
    checkpoint('DB connect FAILED: ' . $e->getMessage());
}

// ════════════════════════════════════════════════════════════════
// ТЕСТ 2: Подключение к LDAP
// ════════════════════════════════════════════════════════════════
checkpoint('Before LDAP config');

try {
    require_once 'config/ldap.php';
    checkpoint('After LDAP config');
    
    // Проверяем подключение к LDAP
    $ldapStart = microtime(true);
    $ldapTest = ldapCheckConnection();
    $ldapTime = round((microtime(true) - $ldapStart) * 1000, 2);
    
    checkpoint('LDAP check connection: ' . ($ldapTest ? 'OK' : 'FAILED') . ' (' . $ldapTime . 'ms)');
} catch (Exception $e) {
    checkpoint('LDAP FAILED: ' . $e->getMessage());
}

// ════════════════════════════════════════════════════════════════
// ТЕСТ 3: Загрузка auth.php
// ════════════════════════════════════════════════════════════════
checkpoint('Before auth.php');

try {
    if (file_exists('auth.php')) {
        require_once 'auth.php';
        checkpoint('After auth.php');
    } else {
        checkpoint('auth.php NOT FOUND');
    }
} catch (Exception $e) {
    checkpoint('auth.php FAILED: ' . $e->getMessage());
}

// ════════════════════════════════════════════════════════════════
// ТЕСТ 4: Симуляция загрузки dashboard
// ════════════════════════════════════════════════════════════════
checkpoint('Before dashboard simulation');

try {
    if (isset($conn)) {
        // Тест запроса к БД
        $queryStart = microtime(true);
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users");
        $stmt->execute();
        $queryTime = round((microtime(true) - $queryStart) * 1000, 2);
        checkpoint('Query users count: ' . $queryTime . 'ms');
        
        // Ещё один тест
        $queryStart = microtime(true);
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM requests");
        $stmt->execute();
        $queryTime = round((microtime(true) - $queryStart) * 1000, 2);
        checkpoint('Query requests count: ' . $queryTime . 'ms');
    }
} catch (Exception $e) {
    checkpoint('DB queries FAILED: ' . $e->getMessage());
}

checkpoint('END');

// ════════════════════════════════════════════════════════════════
// РЕЗУЛЬТАТЫ
// ════════════════════════════════════════════════════════════════

$totalTime = round((microtime(true) - $startTime) * 1000, 2);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профилирование загрузки</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
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
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .total-time {
            font-size: 48px;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .status {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .status.good {
            background: #4caf50;
        }
        
        .status.warning {
            background: #ff9800;
        }
        
        .status.bad {
            background: #f44336;
        }
        
        .content {
            padding: 30px;
        }
        
        .checkpoint {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f5f5f5;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        
        .checkpoint.slow {
            border-left-color: #ff9800;
            background: #fff3e0;
        }
        
        .checkpoint.very-slow {
            border-left-color: #f44336;
            background: #ffebee;
        }
        
        .checkpoint-name {
            flex: 1;
            font-weight: 500;
        }
        
        .checkpoint-time {
            font-weight: bold;
            font-size: 18px;
            padding: 5px 15px;
            background: white;
            border-radius: 20px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .recommendation {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .recommendation h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .recommendation ul {
            margin-left: 20px;
        }
        
        .recommendation li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏱️ Профилирование загрузки</h1>
            <div class="total-time"><?php echo $totalTime; ?> мс</div>
            <div class="status <?php 
                if ($totalTime < 500) echo 'good';
                elseif ($totalTime < 2000) echo 'warning';
                else echo 'bad';
            ?>">
                <?php 
                if ($totalTime < 500) echo '✓ Отлично';
                elseif ($totalTime < 2000) echo '⚠ Медленно';
                else echo '✗ Очень медленно';
                ?>
            </div>
        </div>
        
        <div class="content">
            <div class="section">
                <h2>📊 Контрольные точки</h2>
                <?php 
                $prevTime = 0;
                foreach ($checkpoints as $cp) {
                    $delta = $cp['time'] - $prevTime;
                    $prevTime = $cp['time'];
                    
                    $class = '';
                    if ($delta > 1000) {
                        $class = 'very-slow';
                    } elseif ($delta > 500) {
                        $class = 'slow';
                    }
                    
                    echo '<div class="checkpoint ' . $class . '">';
                    echo '<div class="checkpoint-name">' . htmlspecialchars($cp['name']) . '</div>';
                    echo '<div class="checkpoint-time">' . $cp['time'] . ' мс';
                    if ($delta > 0) {
                        echo ' <small>(+' . round($delta, 2) . ')</small>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
            
            <div class="section">
                <h2>🔍 Анализ</h2>
                
                <?php
                $problems = [];
                $recommendations = [];
                
                // Анализ времени
                foreach ($checkpoints as $i => $cp) {
                    if ($i > 0) {
                        $delta = $cp['time'] - $checkpoints[$i-1]['time'];
                        
                        if (strpos($cp['name'], 'LDAP') !== false && $delta > 500) {
                            $problems[] = "LDAP подключение медленное ({$delta}ms)";
                            $recommendations[] = "Уменьшите LDAP_TIMEOUT в config/ldap.php до 3-5 секунд";
                        }
                        
                        if (strpos($cp['name'], 'DB') !== false && $delta > 200) {
                            $problems[] = "База данных медленная ({$delta}ms)";
                            $recommendations[] = "Проверьте индексы в таблицах и оптимизируйте запросы";
                        }
                        
                        if (strpos($cp['name'], 'auth.php') !== false && $delta > 300) {
                            $problems[] = "auth.php загружается медленно ({$delta}ms)";
                            $recommendations[] = "Проверьте что auth.php не делает запросов к LDAP при каждой загрузке";
                        }
                    }
                }
                
                if (empty($problems)) {
                    echo '<div style="background: #e8f5e9; padding: 15px; border-radius: 5px; border-left: 4px solid #4caf50;">';
                    echo '<strong>✓ Проблем не обнаружено!</strong><br>';
                    echo 'Время загрузки в норме.';
                    echo '</div>';
                } else {
                    echo '<div style="background: #ffebee; padding: 15px; border-radius: 5px; border-left: 4px solid #f44336; margin-bottom: 20px;">';
                    echo '<strong>⚠ Обнаружены проблемы:</strong><ul style="margin: 10px 0 0 20px;">';
                    foreach ($problems as $problem) {
                        echo '<li>' . htmlspecialchars($problem) . '</li>';
                    }
                    echo '</ul></div>';
                }
                
                if (!empty($recommendations)) {
                    echo '<div class="recommendation">';
                    echo '<h3>💡 Рекомендации:</h3>';
                    echo '<ul>';
                    foreach ($recommendations as $rec) {
                        echo '<li>' . htmlspecialchars($rec) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
                ?>
            </div>
            
            <div class="section">
                <h2>📝 Что делать дальше?</h2>
                <ol style="line-height: 1.8;">
                    <li>Если проблема в <strong>LDAP</strong> - пришлите config/ldap.php</li>
                    <li>Если проблема в <strong>БД</strong> - пришлите медленные страницы (dashboard.php и т.д.)</li>
                    <li>Если проблема в <strong>auth.php</strong> - пришлите auth.php</li>
                    <li>Если всё быстро работает здесь, но медленно на других страницах - пришлите конкретную медленную страницу</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>
