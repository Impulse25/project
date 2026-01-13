<?php
// debug_post.php - Отладка POST запросов
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/auth.php';

requireRole('technician');
$user = getCurrentUser();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Отладка POST</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        pre { background: #f5f5f5; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Отладка создания задачи</h1>
    <p>Пользователь: <?php echo htmlspecialchars($user['full_name']); ?> (ID: <?php echo $user['id']; ?>)</p>
    
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <h2>POST запрос получен!</h2>
        <h3>$_POST:</h3>
        <pre><?php print_r($_POST); ?></pre>
        
        <h3>Проверка isset():</h3>
        <p>isset($_POST['task_action']): <?php echo isset($_POST['task_action']) ? 'YES' : 'NO'; ?></p>
        <p>$_POST['task_action'] значение: <?php echo $_POST['task_action'] ?? 'NOT SET'; ?></p>
        
        <?php
        if (isset($_POST['task_action']) && $_POST['task_action'] === 'create_task') {
            echo "<p class='success'>✅ Условие if (isset(\$_POST['task_action'])) выполнено!</p>";
            
            $title = trim($_POST['task_title'] ?? '');
            $description = trim($_POST['task_description'] ?? '');
            $category = $_POST['task_category'] ?? 'other';
            $priority = $_POST['task_priority'] ?? 'normal';
            $dueDate = $_POST['task_due_date'] ?? null;
            $assignTo = $_POST['assign_to'] ?? '';
            
            echo "<h3>Обработанные данные:</h3>";
            echo "<p>Название: " . htmlspecialchars($title) . "</p>";
            echo "<p>Категория: " . htmlspecialchars($category) . "</p>";
            echo "<p>Приоритет: " . htmlspecialchars($priority) . "</p>";
            echo "<p>assign_to (до): " . htmlspecialchars($assignTo) . "</p>";
            
            if (empty($assignTo) || $assignTo === 'pool') {
                $assignTo = null;
                echo "<p>assign_to (после): NULL</p>";
            } else {
                echo "<p>assign_to (после): " . htmlspecialchars($assignTo) . "</p>";
            }
            
            if ($title) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO technician_tasks 
                        (created_by, assigned_to, title, description, category, priority, due_date, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $user['id'],
                        $assignTo,
                        $title,
                        $description,
                        $category,
                        $priority,
                        $dueDate ?: null
                    ]);
                    
                    $taskId = $pdo->lastInsertId();
                    echo "<p class='success'>✅ УСПЕХ! Задача создана с ID: $taskId</p>";
                    
                    if ($assignTo === null) {
                        echo "<p class='success'>Задача в общем пуле IT-отдела</p>";
                    }
                    
                } catch (PDOException $e) {
                    echo "<p class='error'>❌ ОШИБКА: " . $e->getMessage() . "</p>";
                }
            }
        } else {
            echo "<p class='error'>❌ Условие НЕ выполнено!</p>";
            echo "<p>task_action = '" . ($_POST['task_action'] ?? 'NOT SET') . "'</p>";
        }
        ?>
    <?php else: ?>
        <p>Нет POST запроса. Заполните форму ниже.</p>
    <?php endif; ?>
    
    <hr>
    
    <h2>Форма создания задачи</h2>
    <form method="POST" action="">
        <p>
            <label>Название: *</label><br>
            <input type="text" name="task_title" required value="Тестовая задача через форму">
        </p>
        
        <p>
            <label>Категория:</label><br>
            <select name="task_category">
                <option value="maintenance">🔧 Обслуживание</option>
                <option value="update">🔄 Обновление</option>
                <option value="other">📋 Прочее</option>
            </select>
        </p>
        
        <p>
            <label>Приоритет:</label><br>
            <input type="radio" name="task_priority" value="low"> Низкий
            <input type="radio" name="task_priority" value="normal" checked> Обычный
            <input type="radio" name="task_priority" value="high"> Высокий
            <input type="radio" name="task_priority" value="urgent"> Срочно
        </p>
        
        <p>
            <label>Описание:</label><br>
            <textarea name="task_description" rows="3">Тестовое описание</textarea>
        </p>
        
        <p>
            <label>Назначить:</label><br>
            <select name="assign_to">
                <option value="pool" selected>В общий пул</option>
                <option value="<?php echo $user['id']; ?>">Себе</option>
            </select>
        </p>
        
        <input type="hidden" name="task_action" value="create_task">
        
        <p>
            <button type="submit">Создать задачу</button>
        </p>
    </form>
    
    <hr>
    <p><a href="technician_dashboard.php?tab=my_tasks">← Вернуться</a></p>
</body>
</html>
