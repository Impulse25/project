<?php
// simple_create_task.php - Максимально простая форма без всего лишнего
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/auth.php';

requireRole('technician');
$user = getCurrentUser();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['task_title'] ?? '');
    $assignTo = $_POST['assign_to'] ?? '';
    
    if (empty($assignTo) || $assignTo === 'pool') {
        $assignTo = null;
    }
    
    if ($title) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO technician_tasks 
                (created_by, assigned_to, title, description, category, priority, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $user['id'],
                $assignTo,
                $title,
                'Описание по умолчанию',
                'other',
                'normal'
            ]);
            
            $taskId = $pdo->lastInsertId();
            $success = "✅ Задача создана с ID: $taskId";
            
            // Проверяем в базе
            $stmt = $pdo->prepare("SELECT * FROM technician_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            if ($task['assigned_to'] === null) {
                $success .= " - в ОБЩЕМ ПУЛЕ";
            } else {
                $success .= " - назначена ID: " . $task['assigned_to'];
            }
            
        } catch (PDOException $e) {
            $error = "❌ Ошибка: " . $e->getMessage();
        }
    } else {
        $error = "❌ Название не может быть пустым";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Простая форма создания задачи</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
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
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #34495e;
        }
        input[type="text"],
        select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background: #27ae60;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
        }
        button:hover {
            background: #229954;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .link {
            margin-top: 20px;
            text-align: center;
        }
        .link a {
            color: #3498db;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Создать задачу</h1>
        
        <div class="info">
            <strong>Пользователь:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (ID: <?php echo $user['id']; ?>)
        </div>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Название задачи: *</label>
                <input type="text" name="task_title" required placeholder="Введите название задачи">
            </div>
            
            <div class="form-group">
                <label>Назначить:</label>
                <select name="assign_to">
                    <option value="pool" selected>🔀 В общий пул IT-отдела</option>
                    <option value="<?php echo $user['id']; ?>">👤 Себе</option>
                </select>
            </div>
            
            <button type="submit">✅ Создать задачу</button>
        </form>
        
        <div class="link">
            <a href="technician_dashboard.php?tab=my_tasks">← Вернуться к панели</a>
        </div>
    </div>
</body>
</html>
