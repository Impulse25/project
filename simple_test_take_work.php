<?php
// simple_test_take_work.php - СУПЕР ПРОСТОЙ ТЕСТ

require_once 'config/db.php';
require_once 'includes/auth.php';

requireRole('technician');
$user = getCurrentUser();

// Обработка
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'take_to_work') {
    $requestId = $_POST['request_id'];
    $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', assigned_to = ?, started_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id'], $requestId]);
    echo "<h1 style='color:green'>✅ УСПЕХ! Заявка #{$requestId} взята в работу</h1>";
    echo "<a href='simple_test_take_work.php'>← Назад</a> | <a href='technician_dashboard.php'>→ К панели</a>";
    exit;
}

// Получаем заявки
$stmt = $pdo->query("SELECT * FROM requests WHERE status = 'new' ORDER BY id DESC");
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Простой тест</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 1000px; margin: 0 auto; }
        .card { border: 2px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #f9f9f9; }
        button { background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <h1>🧪 ПРОСТОЙ ТЕСТ - Взять в работу</h1>
    <p><strong>Техник:</strong> <?php echo $user['full_name']; ?> (ID: <?php echo $user['id']; ?>)</p>
    
    <?php if (empty($requests)): ?>
        <p>Нет новых заявок. <a href="teacher_request.php">Создать заявку</a></p>
    <?php else: ?>
        <h2>Новые заявки (<?php echo count($requests); ?>):</h2>
        
        <?php foreach ($requests as $req): ?>
            <div class="card">
                <h3>Заявка #<?php echo $req['id']; ?> - Кабинет <?php echo $req['cabinet']; ?></h3>
                <p><strong>Статус:</strong> <?php echo $req['status']; ?></p>
                <p><strong>Тип:</strong> <?php echo $req['request_type']; ?></p>
                <p><strong>Создана:</strong> <?php echo $req['created_at']; ?></p>
                
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                    <button type="submit" name="action" value="take_to_work">
                        ▶️ ВЗЯТЬ В РАБОТУ
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <hr>
    <p><a href="technician_dashboard.php">→ Вернуться к основной панели</a></p>
    
</body>
</html>
