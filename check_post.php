<?php
// check_post.php - Проверка что отправляется при нажатии кнопки

require_once 'config/db.php';
require_once 'includes/auth.php';

requireRole('technician');
$user = getCurrentUser();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Проверка POST</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { color: #2563eb; }
        .log-line { padding: 5px; margin: 5px 0; background: #f9f9f9; border-left: 3px solid #2563eb; padding-left: 10px; font-family: monospace; }
        .error { border-left-color: #dc2626; }
        .success { border-left-color: #16a34a; }
        button { background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #1d4ed8; }
        .btn-clear { background: #dc2626; }
        .btn-clear:hover { background: #b91c1c; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Проверка POST данных</h1>
    
    <div class="section">
        <h2>📊 Информация о текущем пользователе</h2>
        <p><strong>ID:</strong> <?php echo $user['id']; ?></p>
        <p><strong>Имя:</strong> <?php echo $user['full_name']; ?></p>
        <p><strong>Роль:</strong> <?php echo $user['role']; ?></p>
    </div>
    
    <div class="section">
        <h2>📝 Лог debug_technician.log</h2>
        <?php
        $logFile = 'debug_technician.log';
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            if (empty(trim($content))) {
                echo '<p>Лог пуст. Нажмите кнопку "Взять в работу" в системе.</p>';
            } else {
                $lines = explode("\n", $content);
                $lines = array_reverse(array_filter($lines));
                
                echo '<p><strong>Последние 20 записей (сверху новые):</strong></p>';
                
                foreach (array_slice($lines, 0, 20) as $line) {
                    $class = 'log-line';
                    if (strpos($line, 'SUCCESS') !== false) {
                        $class .= ' success';
                    } elseif (strpos($line, 'FAIL') !== false) {
                        $class .= ' error';
                    }
                    echo '<div class="' . $class . '">' . htmlspecialchars($line) . '</div>';
                }
            }
        } else {
            echo '<p style="color: red;">Файл debug_technician.log не найден!</p>';
        }
        ?>
        <br>
        <a href="?clear=1"><button class="btn-clear">🗑️ Очистить лог</button></a>
        <button onclick="location.reload()">🔄 Обновить</button>
    </div>
    
    <?php
    if (isset($_GET['clear'])) {
        file_put_contents('debug_technician.log', '');
        echo '<script>location.href="check_post.php";</script>';
    }
    ?>
    
    <div class="section">
        <h2>🧪 Тестовая форма</h2>
        <p>Нажмите кнопку ниже и посмотрите что попадёт в лог:</p>
        
        <?php
        $stmt = $pdo->query("SELECT * FROM requests WHERE status = 'new' ORDER BY id DESC LIMIT 1");
        $testReq = $stmt->fetch();
        
        if ($testReq):
        ?>
            <form method="POST" action="technician_dashboard.php">
                <input type="hidden" name="request_id" value="<?php echo $testReq['id']; ?>">
                <p><strong>Заявка:</strong> #<?php echo $testReq['id']; ?> - Кабинет <?php echo $testReq['cabinet']; ?></p>
                <button type="submit" name="action" value="take_to_work">
                    ▶️ ВЗЯТЬ В РАБОТУ (ТЕСТ)
                </button>
            </form>
            <p><small>Эта кнопка отправит форму в technician_dashboard.php и запишет данные в лог</small></p>
        <?php else: ?>
            <p>Нет новых заявок для теста</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>📋 Инструкция</h2>
        <ol>
            <li>Откройте эту страницу в одной вкладке</li>
            <li>Откройте technician_dashboard.php в другой вкладке</li>
            <li>Нажмите кнопку "Взять в работу" в панели</li>
            <li>Вернитесь сюда и нажмите "Обновить"</li>
            <li>Посмотрите что записалось в лог</li>
        </ol>
        
        <h3>Что искать в логе:</h3>
        <ul>
            <li><strong>POST: {"request_id":"...","action":"take_to_work"}</strong> - данные отправлены правильно</li>
            <li><strong>SUCCESS</strong> - UPDATE выполнен успешно</li>
            <li><strong>FAIL</strong> - ошибка выполнения</li>
        </ul>
        
        <h3>Если лог пуст:</h3>
        <p style="color: red;">Значит POST запрос вообще НЕ доходит до сервера!</p>
        <p>Проверьте:</p>
        <ul>
            <li>F12 → Console - есть ли ошибки JavaScript?</li>
            <li>F12 → Network - отправляется ли POST запрос?</li>
            <li>Ctrl+U → Есть ли &lt;form method="POST"&gt;?</li>
        </ul>
    </div>
    
    <hr>
    <p><a href="technician_dashboard.php"><button>← Вернуться к панели</button></a></p>
    
</body>
</html>
