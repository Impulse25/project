<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'director') {
    header('Location: index.php');
    exit();
}

// Обработка одобрения/отклонения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $priority = $_POST['priority'] ?? 'normal';
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("
            UPDATE requests 
            SET status = 'approved', 
                priority = ?,
                approved_by = ?, 
                approved_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$priority, $_SESSION['user_id'], $request_id]);
        $message = "Заявка одобрена с приоритетом!";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("
            UPDATE requests 
            SET status = 'rejected', 
                approved_by = ?, 
                approved_at = NOW(),
                rejection_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $rejection_reason, $request_id]);
        $message = "Заявка отклонена";
    }
}

// Получение ожидающих заявок
$stmt = $pdo->query("
    SELECT r.*, u.full_name as creator_name 
    FROM requests r 
    JOIN users u ON r.created_by = u.id 
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
");
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_name = $_SESSION['full_name'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Одобрение заявок</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .user-info {
            color: #666;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .request-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .request-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .request-id {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
        
        .request-date {
            color: #999;
            font-size: 14px;
        }
        
        .request-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .description {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            white-space: pre-wrap;
        }
        
        .deadline-suggested {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        select, textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .priority-select {
            min-width: 200px;
        }
        
        button {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(76, 175, 80, 0.4);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }
        
        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(244, 67, 54, 0.4);
        }
        
        .btn-back {
            background: #f0f0f0;
            color: #333;
            padding: 12px 24px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-back:hover {
            background: #e0e0e0;
        }
        
        .no-requests {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-requests-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Одобрение заявок</h1>
        <div class="user-info">
            <strong>Директор:</strong> <?php echo htmlspecialchars($user_name); ?>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (empty($pending_requests)): ?>
            <div class="no-requests">
                <div class="no-requests-icon">📋</div>
                <h2>Нет заявок на рассмотрение</h2>
                <p>Все заявки обработаны</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending_requests as $req): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div class="request-id">Заявка #<?php echo $req['id']; ?></div>
                        <div class="request-date">
                            <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="request-info">
                        <div class="info-item">
                            <div class="info-label">От кого:</div>
                            <div class="info-value"><?php echo htmlspecialchars($req['creator_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Кабинет:</div>
                            <div class="info-value"><?php echo htmlspecialchars($req['cabinet']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Оборудование:</div>
                            <div class="info-value"><?php echo htmlspecialchars($req['equipment_type']); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($req['deadline_suggested'])): ?>
                        <div class="deadline-suggested">
                            <span>⏰</span>
                            <div>
                                <strong>Желаемый срок:</strong> 
                                <?php echo date('d.m.Y H:i', strtotime($req['deadline_suggested'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="description">
                        <strong>Описание:</strong><br>
                        <?php echo nl2br(htmlspecialchars($req['description'])); ?>
                    </div>
                    
                    <form method="POST" class="action-form">
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                        
                        <div class="form-group">
                            <label for="priority_<?php echo $req['id']; ?>">Приоритет:</label>
                            <select name="priority" id="priority_<?php echo $req['id']; ?>" class="priority-select">
                                <option value="critical">🔴 Критичный (немедленно)</option>
                                <option value="high">🟡 Высокий (1-2 дня)</option>
                                <option value="normal" selected>🟢 Обычный (до недели)</option>
                                <option value="low">⚪ Низкий (когда будет время)</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="action" value="approve" class="btn-approve">
                            ✅ Одобрить
                        </button>
                        
                        <button type="button" onclick="showRejectForm(<?php echo $req['id']; ?>)" class="btn-reject">
                            ❌ Отклонить
                        </button>
                    </form>
                    
                    <div id="reject_form_<?php echo $req['id']; ?>" style="display: none; margin-top: 15px;">
                        <form method="POST">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <div class="form-group">
                                <label>Причина отклонения:</label>
                                <textarea name="rejection_reason" rows="3" required placeholder="Укажите причину отклонения..."></textarea>
                            </div>
                            <button type="submit" class="btn-reject">Подтвердить отклонение</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <a href="dashboard.php" class="btn-back">← Назад к панели</a>
    </div>
    
    <script>
        function showRejectForm(requestId) {
            const form = document.getElementById('reject_form_' + requestId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>