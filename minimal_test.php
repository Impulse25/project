<?php
// minimal_test.php - Минимальный тест кнопки

require_once 'config/db.php';
require_once 'includes/auth.php';

requireRole('technician');
$user = getCurrentUser();

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h1>✅ POST запрос получен!</h1>";
    echo "<pre>";
    echo "REQUEST_ID: " . ($_POST['request_id'] ?? 'НЕТ') . "\n";
    echo "ACTION: " . ($_POST['action'] ?? 'НЕТ') . "\n";
    echo "USER_ID: " . $user['id'] . "\n\n";
    echo "Все POST данные:\n";
    print_r($_POST);
    echo "</pre>";
    
    if (isset($_POST['action']) && $_POST['action'] === 'take_to_work') {
        $requestId = $_POST['request_id'];
        
        echo "<h2>Попытка UPDATE...</h2>";
        
        try {
            $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', assigned_to = ?, started_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$user['id'], $requestId]);
            
            if ($result) {
                echo "<p style='color: green; font-size: 20px;'>✅ UPDATE выполнен успешно!</p>";
                
                // Проверяем результат
                $stmt = $pdo->prepare("SELECT id, status, assigned_to, started_at FROM requests WHERE id = ?");
                $stmt->execute([$requestId]);
                $req = $stmt->fetch();
                
                echo "<h3>Результат в БД:</h3>";
                echo "<pre>";
                print_r($req);
                echo "</pre>";
            } else {
                echo "<p style='color: red;'>❌ UPDATE вернул FALSE</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Ошибка: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<hr><a href='minimal_test.php'>← Назад к форме</a>";
    exit;
}

// Получаем первую заявку со статусом new
$stmt = $pdo->query("SELECT * FROM requests WHERE status = 'new' ORDER BY id DESC LIMIT 1");
$req = $stmt->fetch();

if (!$req) {
    echo "<h1>❌ Нет заявок со статусом 'new'</h1>";
    echo "<p>Создайте новую заявку сначала</p>";
    echo "<a href='teacher_request.php'>Создать заявку</a>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Минимальный тест</title>
    <style>
        body {
            font-family: Arial;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .card {
            border: 2px solid #ddd;
            padding: 20px;
            border-radius: 10px;
            background: #f9f9f9;
        }
        button {
            background: #2563eb;
            color: white;
            padding: 15px 30px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        button:hover {
            background: #1d4ed8;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>🧪 Минимальный тест кнопки "Взять в работу"</h1>
    
    <div class="info">
        <p><strong>Пользователь:</strong> <?php echo $user['full_name']; ?> (ID: <?php echo $user['id']; ?>)</p>
        <p><strong>Роль:</strong> <?php echo $user['role']; ?></p>
    </div>
    
    <div class="card">
        <h2>Заявка #<?php echo $req['id']; ?></h2>
        <p><strong>Кабинет:</strong> <?php echo $req['cabinet']; ?></p>
        <p><strong>Текущий статус:</strong> <span style="color: blue; font-weight: bold;"><?php echo $req['status']; ?></span></p>
        <p><strong>Тип:</strong> <?php echo $req['request_type']; ?></p>
        <p><strong>Создана:</strong> <?php echo $req['created_at']; ?></p>
        
        <hr>
        
        <form method="POST" id="testForm">
            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
            
            <p><strong>Что произойдёт при нажатии кнопки:</strong></p>
            <ol>
                <li>Отправится POST запрос</li>
                <li>Статус изменится на 'in_progress'</li>
                <li>Появится assigned_to = <?php echo $user['id']; ?></li>
                <li>Установится started_at = NOW()</li>
            </ol>
            
            <button type="submit" name="action" value="take_to_work" id="testButton">
                ▶️ ВЗЯТЬ В РАБОТУ (TEST)
            </button>
        </form>
    </div>
    
    <div class="info" style="margin-top: 20px;">
        <h3>🔍 Отладка:</h3>
        <p>Если кнопка не работает, проверьте:</p>
        <ul>
            <li>Открыта ли консоль браузера (F12)?</li>
            <li>Есть ли ошибки JavaScript?</li>
            <li>Отправляется ли POST запрос (вкладка Network)?</li>
        </ul>
    </div>
    
    <script>
        // Добавляем отладку
        document.getElementById('testForm').addEventListener('submit', function(e) {
            console.log('Form submitting...');
            console.log('Request ID:', document.querySelector('[name="request_id"]').value);
            console.log('Action:', document.querySelector('[name="action"]').value);
        });
        
        document.getElementById('testButton').addEventListener('click', function() {
            console.log('Button clicked!');
        });
    </script>
    
    <hr>
    <p><a href="technician_dashboard.php">← Вернуться к панели техника</a></p>
    <p><a href="view_debug_log.php">📋 Посмотреть debug.log</a></p>
    
</body>
</html>
