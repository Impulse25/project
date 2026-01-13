<?php
// test_create_task.php - Простой тест создания задачи
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/auth.php';

requireRole('technician');
$user = getCurrentUser();

echo "<h1>Тест создания задачи</h1>";
echo "<p>Пользователь: " . htmlspecialchars($user['full_name']) . " (ID: " . $user['id'] . ")</p>";

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST данные получены:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $title = trim($_POST['task_title'] ?? '');
    $description = trim($_POST['task_description'] ?? '');
    $category = $_POST['task_category'] ?? 'other';
    $priority = $_POST['task_priority'] ?? 'normal';
    $assignTo = $_POST['assign_to'] ?? '';
    
    echo "<h3>Обработанные данные:</h3>";
    echo "Название: " . htmlspecialchars($title) . "<br>";
    echo "Описание: " . htmlspecialchars($description) . "<br>";
    echo "Категория: " . htmlspecialchars($category) . "<br>";
    echo "Приоритет: " . htmlspecialchars($priority) . "<br>";
    echo "Назначить (до): " . htmlspecialchars($assignTo) . "<br>";
    
    // Проверка значения assign_to
    if (empty($assignTo) || $assignTo === 'pool') {
        $assignTo = null;
        echo "Назначить (после): NULL (в пул)<br>";
    } else {
        echo "Назначить (после): " . htmlspecialchars($assignTo) . "<br>";
    }
    
    if ($title) {
        try {
            $sql = "INSERT INTO technician_tasks 
                    (created_by, assigned_to, title, description, category, priority, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            
            echo "<h3>SQL запрос:</h3>";
            echo "<pre>" . $sql . "</pre>";
            echo "<p>Параметры: [" . $user['id'] . ", " . ($assignTo ?? 'NULL') . ", " . htmlspecialchars($title) . ", ...]</p>";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $user['id'],
                $assignTo,
                $title,
                $description,
                $category,
                $priority
            ]);
            
            $taskId = $pdo->lastInsertId();
            
            echo "<h2 style='color: green;'>✅ УСПЕХ!</h2>";
            echo "<p>Задача создана с ID: <strong>" . $taskId . "</strong></p>";
            
            if ($assignTo === null) {
                echo "<p style='color: blue;'>Задача создана в общем пуле IT-отдела</p>";
            } else {
                echo "<p>Задача назначена пользователю ID: " . $assignTo . "</p>";
            }
            
            // Проверяем в базе
            $stmt = $pdo->prepare("SELECT * FROM technician_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            echo "<h3>Данные из базы:</h3>";
            echo "<pre>";
            print_r($task);
            echo "</pre>";
            
        } catch (PDOException $e) {
            echo "<h2 style='color: red;'>❌ ОШИБКА!</h2>";
            echo "<p>" . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Название задачи не может быть пустым</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Тест создания задачи</title>
</head>
<body>
    <h2>Форма создания задачи (тест)</h2>
    <form method="POST">
        <p>
            <label>Название задачи: *</label><br>
            <input type="text" name="task_title" required style="width: 300px;" value="Тестовая задача">
        </p>
        
        <p>
            <label>Категория:</label><br>
            <select name="task_category">
                <option value="maintenance">Обслуживание</option>
                <option value="update">Обновление</option>
                <option value="other">Прочее</option>
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
            <textarea name="task_description" rows="4" style="width: 300px;">Тестовое описание</textarea>
        </p>
        
        <p>
            <label>Назначить задачу:</label><br>
            <select name="assign_to">
                <option value="pool" selected>В общий пул IT-отдела</option>
                <option value="<?php echo $user['id']; ?>">Себе</option>
            </select>
        </p>
        
        <p>
            <button type="submit">Создать задачу</button>
        </p>
    </form>
    
    <hr>
    <p><a href="technician_dashboard.php">← Вернуться к панели</a></p>
</body>
</html>
