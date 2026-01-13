<?php
// technician_dashboard.php - Панель системного техника (УЛУЧШЕННАЯ)

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('technician');

$user = getCurrentUser();
$technicianId = $user['id'];

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: technician_dashboard.php');
    exit();
}

// Получение текущей вкладки
$tab = $_GET['tab'] ?? 'active';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ОТЛАДКА - удалите после проверки
    error_log("POST REQUEST RECEIVED: " . print_r($_POST, true));
    
    $requestId = $_POST['request_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    if ($action === 'take_to_work') {
        // Взять заявку в работу
        $stmt = $pdo->prepare("UPDATE requests SET status = 'in_progress', assigned_to = ?, assigned_at = NOW(), started_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $requestId]);
        
        // Лог действия
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'assigned', 'pending', 'in_progress', 'Взял заявку в работу')");
        $stmt->execute([$requestId, $user['id']]);
        
        // Редирект на вкладку "В работе"
        header('Location: technician_dashboard.php?tab=in_progress');
        exit();
        
    } elseif ($action === 'complete') {
        // Системотехник завершает работу - отправляет на подтверждение преподавателю
        $comment = $_POST['comment'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'awaiting_approval', approval_requested_at = NOW(), sent_to_director = 0 WHERE id = ?");
        $stmt->execute([$requestId]);
        
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
        }
        
        // Лог
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'sent_for_approval', 'in_progress', 'awaiting_approval', ?)");
        $stmt->execute([$requestId, $user['id'], 'Отправлено на подтверждение преподавателю: ' . $comment]);
        
        // Редирект на вкладку "Ожидают подтверждения"
        header('Location: technician_dashboard.php?tab=awaiting');
        exit();
        
    } elseif ($action === 'add_comment') {
        $comment = $_POST['comment'] ?? '';
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
            
            // Лог
            $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, comment) VALUES (?, ?, 'comment_added', ?)");
            $stmt->execute([$requestId, $user['id'], 'Добавлен комментарий: ' . mb_substr($comment, 0, 100)]);
        }
        
        // Редирект на текущую вкладку
        header('Location: technician_dashboard.php?tab=in_progress');
        exit();
        
    } elseif ($action === 'edit_comment') {
        // Редактирование существующего комментария
        $commentId = $_POST['comment_id'] ?? 0;
        $newComment = $_POST['comment'] ?? '';
        
        // Проверяем что комментарий принадлежит текущему пользователю
        $stmt = $pdo->prepare("SELECT user_id, comment FROM comments WHERE id = ? AND request_id = ?");
        $stmt->execute([$commentId, $requestId]);
        $existingComment = $stmt->fetch();
        
        if ($existingComment && $existingComment['user_id'] == $user['id'] && $newComment) {
            // Обновляем комментарий
            $stmt = $pdo->prepare("UPDATE comments SET comment = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newComment, $commentId]);
            
            // Лог
            $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, comment) VALUES (?, ?, 'comment_edited', ?)");
            $stmt->execute([$requestId, $user['id'], 'Отредактирован комментарий']);
        }
        
        // Редирект на текущую вкладку
        header('Location: technician_dashboard.php?tab=in_progress');
        exit();
        
    } elseif ($action === 'send_to_director') {
        // Отправка на согласование директору
        $comment = $_POST['comment'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'awaiting_approval', sent_to_director = 1, sent_to_director_at = NOW(), approval_requested_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
        
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], $comment]);
        }
        
        // Лог
        $logComment = 'Отправлено директору на согласование' . ($comment ? ': ' . $comment : '');
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'sent_to_director', 'in_progress', 'awaiting_approval', ?)");
        $stmt->execute([$requestId, $user['id'], $logComment]);
        
        // Редирект на вкладку "Ожидают директора"
        header('Location: technician_dashboard.php?tab=awaiting_director');
        exit();
        
    } elseif ($action === 'reject') {
        // Отклонение заявки
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $requestId]);
        
        if ($reason) {
            $stmt = $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$requestId, $user['id'], 'Заявка отклонена. Причина: ' . $reason]);
        }
        
        // Лог
        $logComment = 'Заявка отклонена' . ($reason ? '. Причина: ' . $reason : '');
        $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?, ?, 'rejected', 'pending', 'rejected', ?)");
        $stmt->execute([$requestId, $user['id'], $logComment]);
        
        // Редирект на вкладку "Активные" (заявка удаляется из списка)
        header('Location: technician_dashboard.php?tab=active');
        exit();
        
    } elseif ($action === 'set_deadline') {
        // Установка срока выполнения
        $deadline = $_POST['deadline'] ?? '';
        if ($deadline) {
            // Получаем дату создания заявки для валидации
            $stmt = $pdo->prepare("SELECT created_at FROM requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            
            // Проверка: срок не может быть раньше даты создания заявки
            $createdDate = date('Y-m-d', strtotime($request['created_at']));
            if ($deadline < $createdDate) {
                $_SESSION['error_message'] = 'Ошибка: Срок выполнения не может быть раньше даты создания заявки (' . date('d.m.Y', strtotime($createdDate)) . ')';
            } else {
                $stmt = $pdo->prepare("UPDATE requests SET deadline = ?, deadline_set_by = ? WHERE id = ?");
                $stmt->execute([$deadline, $user['id'], $requestId]);
                
                // Лог
                $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, comment) VALUES (?, ?, 'deadline_set', ?)");
                $stmt->execute([$requestId, $user['id'], 'Установлен срок выполнения: ' . date('d.m.Y H:i', strtotime($deadline))]);
                
                $_SESSION['success_message'] = 'Срок выполнения установлен успешно';
            }
        }
    } elseif ($action === 'extend_deadline') {
        // Продление срока выполнения
        $newDeadline = $_POST['new_deadline'] ?? '';
        $extensionReason = $_POST['extension_reason'] ?? '';
        
        if ($newDeadline) {
            // Получаем текущий срок
            $stmt = $pdo->prepare("SELECT deadline, created_at FROM requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            $oldDeadline = $request['deadline'];
            
            // Проверка: новый срок не может быть раньше даты создания
            $createdDate = date('Y-m-d', strtotime($request['created_at']));
            if ($newDeadline < $createdDate) {
                $_SESSION['error_message'] = 'Ошибка: Новый срок не может быть раньше даты создания заявки';
            } else {
                // Обновляем срок
                $stmt = $pdo->prepare("UPDATE requests SET deadline = ?, deadline_set_by = ? WHERE id = ?");
                $stmt->execute([$newDeadline, $user['id'], $requestId]);
                
                // Лог продления
                $logComment = 'Срок продлен с ' . date('d.m.Y', strtotime($oldDeadline)) . ' на ' . date('d.m.Y', strtotime($newDeadline));
                if ($extensionReason) {
                    $logComment .= '. Причина: ' . $extensionReason;
                }
                $stmt = $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, comment) VALUES (?, ?, 'deadline_extended', ?)");
                $stmt->execute([$requestId, $user['id'], $logComment]);
                
                $_SESSION['success_message'] = 'Срок выполнения продлен';
            }
        }
    }
    
    // Редирект только если обрабатывали заявку (не задачу)
    if ($action) {
        header('Location: technician_dashboard.php?tab=' . $tab);
        exit();
    }
}

// ════════════════════════════════════════════════════════════════
// УПРАВЛЕНИЕ ЗАДАЧАМИ IT-ОТДЕЛА
// ════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_action'])) {
    // ОТЛАДКА - удалите после проверки
    error_log("TASK_ACTION SECTION: action=" . ($_POST['task_action'] ?? 'NOT SET'));
    
    $taskAction = $_POST['task_action'];
    $taskId = $_POST['task_id'] ?? null;
    
    if ($taskAction === 'create_task') {
        // Создание новой задачи
        $title = trim($_POST['task_title'] ?? '');
        $description = trim($_POST['task_description'] ?? '');
        $category = $_POST['task_category'] ?? 'other';
        $priority = $_POST['task_priority'] ?? 'normal';
        $dueDate = $_POST['task_due_date'] ?? null;
        $assignTo = $_POST['assign_to'] ?? '';
        
        // ОТЛАДКА - удалите после проверки
        error_log("CREATE TASK: title=$title, assign_to=$assignTo, due_date=$dueDate");
        
        // ВАЖНО: Если assign_to пустой - оставляем NULL (задача в пуле, не назначена)
        if (empty($assignTo) || $assignTo === 'pool') {
            $assignTo = null;
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
                
                // Логируем создание задачи
                $stmt = $pdo->prepare("
                    INSERT INTO task_logs (task_id, user_id, action, comment) 
                    VALUES (?, ?, 'created', ?)
                ");
                $stmt->execute([$taskId, $user['id'], 'Задача создана' . ($assignTo === null ? ' в общем пуле' : '')]);
                
                if ($assignTo === null) {
                    $_SESSION['success_message'] = 'Задача создана в общем пуле IT-отдела (ID: ' . $taskId . ')';
                } else {
                    $_SESSION['success_message'] = 'Задача создана и назначена (ID: ' . $taskId . ')';
                }
                
                // ОТЛАДКА - удалите после проверки
                error_log("TASK CREATED: ID=$taskId");
                
                // Редирект чтобы избежать повторной отправки формы
                header('Location: technician_dashboard.php?tab=my_tasks');
                exit();
                
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'Ошибка при создании задачи: ' . $e->getMessage();
                // ОТЛАДКА - удалите после проверки
                error_log("CREATE TASK ERROR: " . $e->getMessage());
            }
        } else {
            $_SESSION['error_message'] = 'Название задачи не может быть пустым';
        }
        
    } elseif ($taskAction === 'take_task' && $taskId) {
        // Взять задачу из пула (без назначения) в работу
        $stmt = $pdo->prepare("
            UPDATE technician_tasks 
            SET assigned_to = ?, status = 'in_progress', started_at = NOW(), taken_from_pool_at = NOW() 
            WHERE id = ? AND assigned_to IS NULL AND status = 'pending'
        ");
        $result = $stmt->execute([$user['id'], $taskId]);
        
        if ($stmt->rowCount() > 0) {
            // Логируем взятие из пула
            $stmt = $pdo->prepare("
                INSERT INTO task_logs (task_id, user_id, action, comment) 
                VALUES (?, ?, 'taken_from_pool', 'Задача взята из общего пула')
            ");
            $stmt->execute([$taskId, $user['id']]);
            
            $_SESSION['success_message'] = 'Задача взята в работу';
        } else {
            $_SESSION['error_message'] = 'Задача уже назначена другому системотехнику или не найдена';
        }
        
    } elseif ($taskAction === 'start_task' && $taskId) {
        // Взять задачу в работу
        $stmt = $pdo->prepare("
            UPDATE technician_tasks 
            SET status = 'in_progress', started_at = NOW() 
            WHERE id = ? AND (assigned_to = ? OR created_by = ?)
        ");
        $stmt->execute([$taskId, $user['id'], $user['id']]);
        
        // Логируем начало работы
        $stmt = $pdo->prepare("
            INSERT INTO task_logs (task_id, user_id, action, comment) 
            VALUES (?, ?, 'started', 'Начата работа над задачей')
        ");
        $stmt->execute([$taskId, $user['id']]);
        
        $_SESSION['success_message'] = 'Задача взята в работу';
        
    } elseif ($taskAction === 'complete_task' && $taskId) {
        // Завершить задачу
        $stmt = $pdo->prepare("
            UPDATE technician_tasks 
            SET status = 'completed', completed_at = NOW() 
            WHERE id = ? AND (assigned_to = ? OR created_by = ?)
        ");
        $stmt->execute([$taskId, $user['id'], $user['id']]);
        
        // Логируем завершение
        $stmt = $pdo->prepare("
            INSERT INTO task_logs (task_id, user_id, action, comment) 
            VALUES (?, ?, 'completed', 'Задача завершена')
        ");
        $stmt->execute([$taskId, $user['id']]);
        
        $_SESSION['success_message'] = 'Задача завершена';
        
    } elseif ($taskAction === 'cancel_task' && $taskId) {
        // Отменить задачу
        $cancelReason = trim($_POST['cancel_reason'] ?? '');
        
        $stmt = $pdo->prepare("
            UPDATE technician_tasks 
            SET status = 'cancelled' 
            WHERE id = ? AND created_by = ?
        ");
        $stmt->execute([$taskId, $user['id']]);
        
        // Логируем отмену
        $logComment = $cancelReason ? 'Причина: ' . $cancelReason : 'Без указания причины';
        $stmt = $pdo->prepare("
            INSERT INTO task_logs (task_id, user_id, action, comment) 
            VALUES (?, ?, 'cancelled', ?)
        ");
        $stmt->execute([$taskId, $user['id'], $logComment]);
        
        $_SESSION['success_message'] = 'Задача отменена';
        
    } elseif ($taskAction === 'delete_task' && $taskId) {
        // Удалить задачу (только свои)
        // Сначала удаляем комментарии и логи (каскадное удаление должно сработать, но на всякий случай)
        $stmt = $pdo->prepare("DELETE FROM task_comments WHERE task_id = ?");
        $stmt->execute([$taskId]);
        
        $stmt = $pdo->prepare("DELETE FROM task_logs WHERE task_id = ?");
        $stmt->execute([$taskId]);
        
        $stmt = $pdo->prepare("
            DELETE FROM technician_tasks 
            WHERE id = ? AND created_by = ?
        ");
        $stmt->execute([$taskId, $user['id']]);
        $_SESSION['success_message'] = 'Задача удалена';
        
    } elseif ($taskAction === 'update_deadline' && $taskId) {
        // Изменить срок выполнения задачи
        $newDeadline = $_POST['new_deadline'] ?? null;
        
        // Получаем старый срок для лога
        $stmt = $pdo->prepare("SELECT due_date FROM technician_tasks WHERE id = ? AND (assigned_to = ? OR created_by = ?)");
        $stmt->execute([$taskId, $user['id'], $user['id']]);
        $task = $stmt->fetch();
        
        if ($task) {
            $oldDeadline = $task['due_date'];
            
            $stmt = $pdo->prepare("
                UPDATE technician_tasks 
                SET due_date = ? 
                WHERE id = ? AND (assigned_to = ? OR created_by = ?)
            ");
            $stmt->execute([$newDeadline ?: null, $taskId, $user['id'], $user['id']]);
            
            // Логируем изменение
            $oldStr = $oldDeadline ? date('d.m.Y', strtotime($oldDeadline)) : 'не установлен';
            $newStr = $newDeadline ? date('d.m.Y', strtotime($newDeadline)) : 'не установлен';
            
            $stmt = $pdo->prepare("
                INSERT INTO task_logs (task_id, user_id, action, old_value, new_value, comment) 
                VALUES (?, ?, 'deadline_changed', ?, ?, ?)
            ");
            $stmt->execute([$taskId, $user['id'], $oldStr, $newStr, 'Изменён срок выполнения']);
            
            $_SESSION['success_message'] = 'Срок выполнения обновлён';
        }
        
    } elseif ($taskAction === 'add_task_comment' && $taskId) {
        // Добавить комментарий к задаче
        $comment = trim($_POST['task_comment'] ?? '');
        
        if ($comment) {
            $stmt = $pdo->prepare("
                INSERT INTO task_comments (task_id, user_id, comment) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$taskId, $user['id'], $comment]);
            
            // Логируем
            $stmt = $pdo->prepare("
                INSERT INTO task_logs (task_id, user_id, action, comment) 
                VALUES (?, ?, 'comment_added', ?)
            ");
            $stmt->execute([$taskId, $user['id'], 'Добавлен комментарий']);
            
            $_SESSION['success_message'] = 'Комментарий добавлен';
        }
        
    } elseif ($taskAction === 'update_notes' && $taskId) {
        // Обновить личные заметки
        $notes = trim($_POST['task_notes'] ?? '');
        
        $stmt = $pdo->prepare("
            UPDATE technician_tasks 
            SET notes = ? 
            WHERE id = ? AND (assigned_to = ? OR created_by = ?)
        ");
        $stmt->execute([$notes ?: null, $taskId, $user['id'], $user['id']]);
        
        $_SESSION['success_message'] = 'Заметки сохранены';
        
    } elseif ($taskAction === 'change_priority' && $taskId) {
        // Изменить приоритет
        $newPriority = $_POST['new_priority'] ?? 'normal';
        
        $stmt = $pdo->prepare("SELECT priority FROM technician_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $oldPriority = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            UPDATE technician_tasks 
            SET priority = ? 
            WHERE id = ? AND (assigned_to = ? OR created_by = ?)
        ");
        $stmt->execute([$newPriority, $taskId, $user['id'], $user['id']]);
        
        // Логируем
        $stmt = $pdo->prepare("
            INSERT INTO task_logs (task_id, user_id, action, old_value, new_value) 
            VALUES (?, ?, 'priority_changed', ?, ?)
        ");
        $stmt->execute([$taskId, $user['id'], $oldPriority, $newPriority]);
        
        $_SESSION['success_message'] = 'Приоритет изменён';
    }
    
    header('Location: technician_dashboard.php?tab=my_tasks');
    exit();
}

// ════════════════════════════════════════════════════════════════
// ПОЛУЧЕНИЕ ЗАЯВОК В ЗАВИСИМОСТИ ОТ ВКЛАДКИ
// ════════════════════════════════════════════════════════════════

// Статистика
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE status = 'pending'");
$stmt->execute();
$activeCount = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE (status = 'in_progress' OR status = 'approved') AND assigned_to = ?");
$stmt->execute([$technicianId]);
$myWorkCount = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE status = 'awaiting_approval' AND assigned_to = ? AND sent_to_director = 0");
$stmt->execute([$technicianId]);
$awaitingCount = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE status = 'awaiting_approval' AND assigned_to = ? AND sent_to_director = 1");
$stmt->execute([$technicianId]);
$awaitingDirectorCount = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE status = 'completed' AND assigned_to = ?");
$stmt->execute([$technicianId]);
$archiveCount = $stmt->fetch()['cnt'];

// Статистика задач IT-отдела
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM technician_tasks WHERE (assigned_to = ? OR created_by = ?) AND status IN ('pending', 'in_progress')");
$stmt->execute([$technicianId, $technicianId]);
$myTasksCount = $stmt->fetch()['cnt'];

// Подсчет задач в общем пуле (не назначенные никому)
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM technician_tasks WHERE assigned_to IS NULL AND status = 'pending'");
$stmt->execute();
$poolTasksCount = $stmt->fetch()['cnt'];

// Получение заявок
$requests = [];
$tasks = [];

if ($tab === 'active') {
    // Активные заявки (только новые без назначения)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name,
        FIELD(r.priority, 'urgent', 'high', 'normal', 'low') as priority_order
        FROM requests r 
        WHERE r.status = 'pending' AND r.assigned_to IS NULL
        ORDER BY priority_order ASC, r.created_at ASC
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'in_progress') {
    // Мои заявки в работе (включая одобренные директором)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name,
        FIELD(r.priority, 'urgent', 'high', 'normal', 'low') as priority_order
        FROM requests r 
        WHERE (r.status = 'in_progress' OR r.status = 'approved') AND r.assigned_to = ?
        ORDER BY priority_order ASC, r.started_at ASC
    ");
    $stmt->execute([$technicianId]);
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'awaiting') {
    // Ожидают подтверждения от преподавателя (отправленные системотехником)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name
        FROM requests r 
        WHERE r.status = 'awaiting_approval' AND r.assigned_to = ? AND r.sent_to_director = 0
        ORDER BY r.approval_requested_at DESC
    ");
    $stmt->execute([$technicianId]);
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'awaiting_director') {
    // Ожидают согласования директора (отправленные директору)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name
        FROM requests r 
        WHERE r.status = 'awaiting_approval' AND r.assigned_to = ? AND r.sent_to_director = 1
        ORDER BY r.sent_to_director_at DESC
    ");
    $stmt->execute([$technicianId]);
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'archive') {
    // Архив (мои завершённые)
    $stmt = $pdo->prepare("
        SELECT r.*, r.full_name as creator_name 
        FROM requests r 
        WHERE r.status = 'completed' AND r.assigned_to = ? 
        ORDER BY r.completed_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$technicianId]);
    $requests = $stmt->fetchAll();
    
} elseif ($tab === 'my_tasks') {
    // Мои задачи IT-отдела (только назначенные мне или созданные мной)
    $stmt = $pdo->prepare("
        SELECT t.*, 
               creator.full_name as creator_name,
               assigned.full_name as assigned_name
        FROM technician_tasks t
        LEFT JOIN users creator ON t.created_by = creator.id
        LEFT JOIN users assigned ON t.assigned_to = assigned.id
        WHERE t.assigned_to = ?
        AND t.status IN ('pending', 'in_progress')
        ORDER BY 
            FIELD(t.priority, 'urgent', 'high', 'normal', 'low'),
            t.due_date ASC,
            t.created_at DESC
    ");
    $stmt->execute([$technicianId]);
    $tasks = $stmt->fetchAll();
    
    // Получаем задачи в общем пуле (не назначенные никому)
    $stmt = $pdo->prepare("
        SELECT t.*, 
               creator.full_name as creator_name,
               NULL as assigned_name
        FROM technician_tasks t
        LEFT JOIN users creator ON t.created_by = creator.id
        WHERE t.assigned_to IS NULL
        AND t.status = 'pending'
        ORDER BY 
            FIELD(t.priority, 'urgent', 'high', 'normal', 'low'),
            t.due_date ASC,
            t.created_at DESC
    ");
    $stmt->execute();
    $poolTasks = $stmt->fetchAll();
    
    // Архив задач IT-отдела (завершённые и отменённые)
    $stmt = $pdo->prepare("
        SELECT t.*, 
               creator.full_name as creator_name,
               assigned.full_name as assigned_name
        FROM technician_tasks t
        LEFT JOIN users creator ON t.created_by = creator.id
        LEFT JOIN users assigned ON t.assigned_to = assigned.id
        WHERE t.status IN ('completed', 'cancelled')
        AND (t.assigned_to = ? OR t.created_by = ?)
        ORDER BY t.completed_at DESC, t.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$technicianId, $technicianId]);
    $archivedTasks = $stmt->fetchAll();
    
    // Отладка - показываем количество задач (удалите после проверки)
    if (isset($_GET['debug'])) {
        echo "<!-- DEBUG: Found " . count($tasks) . " tasks for user ID " . $technicianId . " -->";
        echo "<!-- DEBUG: Found " . count($poolTasks) . " tasks in pool -->";
        echo "<!-- SQL: assigned_to=" . $technicianId . " OR created_by=" . $technicianId . " -->";
    }
}

// Получение списка системотехников для назначения задач
$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'technician' AND id != ? ORDER BY full_name");
$stmt->execute([$technicianId]);
$technicians = $stmt->fetchAll();

// Функции для работы с приоритетами
function getPriorityColor($priority) {
    $colors = [
        'low' => 'bg-gray-100 text-gray-800',
        'normal' => 'bg-blue-100 text-blue-800',
        'high' => 'bg-orange-200 text-orange-900',
        'urgent' => 'bg-red-200 text-red-900'
    ];
    return $colors[$priority] ?? 'bg-gray-100 text-gray-700';
}

function getPriorityIcon($priority) {
    $icons = [
        'low' => 'fa-angle-double-down',
        'normal' => 'fa-minus',
        'high' => 'fa-angle-double-up',
        'urgent' => 'fa-exclamation-triangle'
    ];
    return $icons[$priority] ?? 'fa-minus';
}

function getPriorityText($priority) {
    $texts = [
        'low' => 'Низкий',
        'normal' => 'Обычный',
        'high' => 'Высокий',
        'urgent' => '🔥 СРОЧНО'
    ];
    return $texts[$priority] ?? 'Обычный';
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('technician'); ?> - <?php echo t('system_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .priority-low { 
            background: #f3f4f6; 
            color: #1f2937;
            border: 2px solid #6b7280;
        }
        .priority-normal { 
            background: #dbeafe; 
            color: #1e40af;
            border: 2px solid #3b82f6;
        }
        .priority-high { 
            background: #fed7aa; 
            color: #c2410c;
            border: 2px solid #ea580c;
            box-shadow: 0 2px 4px rgba(234, 88, 12, 0.2);
        }
        .priority-urgent { 
            background: #fca5a5; 
            color: #7f1d1d;
            border: 2px solid #dc2626;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { 
                opacity: 1; 
                transform: scale(1);
            }
            50% { 
                opacity: 0.85;
                transform: scale(1.02);
            }
        }
        .deadline-input-group {
            display: flex;
            gap: 8px;
            align-items: center;
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 12px;
        }
        .deadline-input-group input[type="date"] {
            flex: 1;
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .deadline-input-group button {
            padding: 6px 16px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .deadline-input-group button:hover {
            background: #4338ca;
        }
        #rejectModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Шапка -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-cog text-3xl text-indigo-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo t('system_name'); ?></h1>
                    <p class="text-sm text-gray-600"><?php echo !empty($user['position']) ? htmlspecialchars($user['position']) : t('technician'); ?>: <?php echo htmlspecialchars($user['full_name']); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex gap-2">
                    <a href="?lang=ru&tab=<?php echo $tab; ?>" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'ru' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Рус</a>
                    <a href="?lang=kk&tab=<?php echo $tab; ?>" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'kk' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Қаз</a>
                </div>
                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo t('exit'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto p-6">
        
        <!-- Сообщения об ошибках и успехе -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center gap-3">
                <i class="fas fa-check-circle text-xl"></i>
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php 
        // Определяем активную основную вкладку
        $isRequestsTab = in_array($tab, ['active', 'in_progress', 'awaiting', 'awaiting_director', 'archive']);
        $isTasksTab = ($tab === 'my_tasks');
        
        // Общее количество активных заявок
        $totalActiveRequests = $activeCount + $myWorkCount + $awaitingCount + $awaitingDirectorCount;
        ?>
        
        <!-- Основные вкладки -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-4">
                    <a href="?tab=active" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $isRequestsTab ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-ticket-alt"></i>
                        Заявки
                        <?php if ($totalActiveRequests > 0): ?>
                            <span class="px-2 py-1 bg-indigo-100 text-indigo-800 rounded-full text-xs font-semibold">
                                <?php echo $totalActiveRequests; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="?tab=my_tasks" class="border-b-2 py-3 px-4 font-medium flex items-center gap-2 <?php echo $isTasksTab ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        Задачи IT-отдела
                        <?php if ($poolTasksCount > 0): ?>
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                <?php echo $poolTasksCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- ВКЛАДКА ЗАЯВКИ -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <?php if ($isRequestsTab): ?>
            
            <!-- Под-вкладки заявок -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="flex items-center p-4 border-b">
                    <nav class="flex gap-2 flex-wrap">
                        <a href="?tab=active" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'active' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <i class="fas fa-tasks"></i>
                            Активные
                            <?php if ($activeCount > 0): ?>
                                <span class="px-2 py-0.5 <?php echo $tab === 'active' ? 'bg-white/20' : 'bg-indigo-100 text-indigo-800'; ?> rounded-full text-xs"><?php echo $activeCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=in_progress" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'in_progress' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <i class="fas fa-wrench"></i>
                            В работе
                            <?php if ($myWorkCount > 0): ?>
                                <span class="px-2 py-0.5 <?php echo $tab === 'in_progress' ? 'bg-white/20' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs"><?php echo $myWorkCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=awaiting" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'awaiting' ? 'bg-yellow-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <i class="fas fa-hourglass-half"></i>
                            Ожидают подтверждения
                            <?php if ($awaitingCount > 0): ?>
                                <span class="px-2 py-0.5 <?php echo $tab === 'awaiting' ? 'bg-white/20' : 'bg-yellow-100 text-yellow-800'; ?> rounded-full text-xs"><?php echo $awaitingCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=awaiting_director" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'awaiting_director' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <i class="fas fa-user-tie"></i>
                            Ожидают согласования
                            <?php if ($awaitingDirectorCount > 0): ?>
                                <span class="px-2 py-0.5 <?php echo $tab === 'awaiting_director' ? 'bg-white/20' : 'bg-purple-100 text-purple-800'; ?> rounded-full text-xs"><?php echo $awaitingDirectorCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=archive" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $tab === 'archive' ? 'bg-gray-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <i class="fas fa-archive"></i>
                            Архив
                            <?php if ($archiveCount > 0): ?>
                                <span class="px-2 py-0.5 <?php echo $tab === 'archive' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'; ?> rounded-full text-xs"><?php echo $archiveCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </nav>
                </div>
            </div>
        
        <?php endif; ?>
        
        <!-- Заголовок (скрываем для вкладок с под-вкладками) -->
        <?php if (!$isRequestsTab && !$isTasksTab): ?>
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <?php
                switch ($tab) {
                    case 'active':
                        echo 'Активные заявки';
                        break;
                    case 'in_progress':
                        echo 'Мои заявки в работе';
                        break;
                    case 'awaiting':
                        echo 'Ожидают подтверждения от преподавателя';
                        break;
                    case 'awaiting_director':
                        echo 'Ожидают согласования директора';
                        break;
                    case 'archive':
                        echo 'Архив';
                        break;
                }
                ?>
            </h2>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <i class="fas fa-info-circle"></i>
                <span>Всего: <?php echo count($requests); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Заявки -->
        <?php if ($isRequestsTab): ?>
        <?php if (empty($requests)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-500">Нет заявок</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($requests as $req): ?>
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition p-6 border-l-4 <?php 
                        if ($req['priority'] === 'urgent') echo 'border-red-500';
                        elseif ($req['priority'] === 'high') echo 'border-orange-500';
                        elseif ($req['priority'] === 'normal') echo 'border-blue-500';
                        else echo 'border-gray-300';
                    ?>">
                        
                        <!-- Заголовок заявки -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <!-- Приоритет и Категория -->
                                <div class="mb-3">
                                    <!-- Приоритет -->
                                    <div class="mb-2">
                                        <span class="text-xs text-gray-500 uppercase font-semibold">Приоритет:</span>
                                        <div class="mt-1">
                                            <span class="priority-badge priority-<?php echo $req['priority']; ?>">
                                                <i class="fas <?php echo getPriorityIcon($req['priority']); ?>"></i>
                                                <?php echo getPriorityText($req['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Категория / Тип заявки -->
                                    <div>
                                        <span class="text-xs text-gray-500 uppercase font-semibold">Категория:</span>
                                        <div class="mt-1">
                                            <?php if ($req['request_type'] === 'repair'): ?>
                                                <span class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-tools"></i> РЕМОНТ И ОБСЛУЖИВАНИЕ
                                                </span>
                                            <?php elseif ($req['request_type'] === 'software'): ?>
                                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-laptop-code"></i> ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ
                                                </span>
                                            <?php elseif ($req['request_type'] === '1c_database'): ?>
                                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-database"></i> 1С БАЗА ДАННЫХ
                                                </span>
                                            <?php elseif ($req['request_type'] === 'general_question'): ?>
                                                <span class="px-3 py-1 bg-teal-100 text-teal-800 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-question-circle"></i> ОБЩИЕ ВОПРОСЫ / КОНСУЛЬТАЦИЯ
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 text-sm text-gray-600">
                                    <span><i class="fas fa-door-open text-indigo-600"></i> <span class="text-xs text-gray-500">Кабинет:</span> <strong><?php echo htmlspecialchars($req['cabinet'] ?? 'Не указан'); ?></strong></span>
                                    <span><i class="fas fa-user text-gray-500"></i> <?php echo htmlspecialchars($req['creator_name']); ?></span>
                                    <span><i class="fas fa-calendar text-gray-500"></i> <span class="text-xs text-gray-500">Создана:</span> <?php echo date('d.m.Y H:i', strtotime($req['created_at'])); ?></span>
                                    
                                    <?php if ($req['deadline'] && $tab !== 'archive'): ?>
                                        <?php 
                                        $deadline = strtotime($req['deadline']);
                                        $now = time();
                                        $daysLeft = ceil(($deadline - $now) / 86400);
                                        
                                        if ($daysLeft < 0) {
                                            echo '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold"><i class="fas fa-exclamation-triangle"></i> <span class="text-xs">Срок:</span> ' . date('d.m.Y', $deadline) . ' (Просрочено!)</span>';
                                        } elseif ($daysLeft == 0) {
                                            echo '<span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-bold animate-pulse"><i class="fas fa-clock"></i> <span class="text-xs">Срок:</span> ' . date('d.m.Y', $deadline) . ' (СЕГОДНЯ)</span>';
                                        } elseif ($daysLeft <= 3) {
                                            echo '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold"><i class="fas fa-hourglass-half"></i> <span class="text-xs">Срок:</span> ' . date('d.m.Y', $deadline) . ' (Осталось ' . $daysLeft . ' дн.)</span>';
                                        } else {
                                            echo '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs"><i class="fas fa-calendar-check"></i> <span class="text-xs">Срок:</span> ' . date('d.m.Y', $deadline) . ' (Осталось ' . $daysLeft . ' дн.)</span>';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- ID заявки -->
                            <div class="text-right">
                                <span class="text-xs text-gray-500">Заявка №</span>
                                <span class="text-lg font-bold text-gray-800"><?php echo $req['id']; ?></span>
                            </div>
                        </div>
                        
                        <!-- Описание проблемы -->
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                            <div class="text-xs font-semibold text-gray-600 mb-2 uppercase">
                                <i class="fas fa-file-alt"></i> Описание проблемы:
                            </div>
                            <?php 
                            // Выбираем правильное поле в зависимости от типа заявки
                            $displayText = '';
                            
                            if ($req['request_type'] === 'repair') {
                                $displayText = $req['description'] ?? '';
                            } elseif ($req['request_type'] === 'software') {
                                // Для заявок на ПО показываем software_list
                                $displayText = $req['software_list'] ?? '';
                            } elseif ($req['request_type'] === '1c_database') {
                                $displayText = $req['database_purpose'] ?? '';
                            } elseif ($req['request_type'] === 'general_question') {
                                $displayText = $req['question_description'] ?? '';
                            }
                            
                            // Проверяем наличие описания
                            if (empty(trim($displayText))) {
                                echo '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-3 py-2 rounded mb-2 text-sm">';
                                echo '<strong>⚠️ ВНИМАНИЕ:</strong> Описание проблемы отсутствует в базе данных!';
                                echo '<br><small class="text-xs">Тип заявки: ' . htmlspecialchars($req['request_type']) . '</small>';
                                echo '</div>';
                                $displayText = 'Описание не указано';
                            }
                            ?>
                            <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($displayText)); ?></p>
                        </div>
                        
                        <!-- Дополнительная информация для заявок на ПО -->
                        <?php if ($req['request_type'] === 'software'): ?>
                            <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php if (!empty($req['computer_inventory'])): ?>
                                    <div class="p-3 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                                        <div class="text-xs font-semibold text-blue-800 mb-1 uppercase">
                                            <i class="fas fa-desktop"></i> Инвентарный номер ПК:
                                        </div>
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($req['computer_inventory']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($req['justification'])): ?>
                                    <div class="p-3 bg-purple-50 rounded-lg border-l-4 border-purple-500">
                                        <div class="text-xs font-semibold text-purple-800 mb-1 uppercase">
                                            <i class="fas fa-info-circle"></i> Обоснование:
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($req['justification'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Дополнительная информация для заявок на ремонт -->
                        <?php if ($req['request_type'] === 'repair' && !empty($req['equipment_type'])): ?>
                            <div class="mb-4 p-3 bg-orange-50 rounded-lg border-l-4 border-orange-500">
                                <div class="text-xs font-semibold text-orange-800 mb-1 uppercase">
                                    <i class="fas fa-tools"></i> Тип оборудования:
                                </div>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($req['equipment_type']); ?></p>
                                <?php if (!empty($req['inventory_number'])): ?>
                                    <div class="text-xs text-gray-600 mt-2">
                                        <strong>Инв. номер:</strong> <?php echo htmlspecialchars($req['inventory_number']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Дополнительная информация для заявок на БД 1С -->
                        <?php if ($req['request_type'] === '1c_database'): ?>
                            <div class="mb-4">
                                <?php if (!empty($req['group_number'])): ?>
                                    <div class="p-3 bg-green-50 rounded-lg border-l-4 border-green-500 mb-3">
                                        <div class="text-xs font-semibold text-green-800 mb-1 uppercase">
                                            <i class="fas fa-users"></i> Номер группы:
                                        </div>
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($req['group_number']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($req['students_list'])): ?>
                                    <div class="p-3 bg-indigo-50 rounded-lg border-l-4 border-indigo-500">
                                        <div class="text-xs font-semibold text-indigo-800 mb-1 uppercase">
                                            <i class="fas fa-list"></i> Список студентов:
                                        </div>
                                        <?php 
                                        $students = json_decode($req['students_list'], true);
                                        if (is_array($students) && count($students) > 0):
                                        ?>
                                            <ul class="text-sm text-gray-700 list-disc list-inside">
                                                <?php foreach ($students as $student): ?>
                                                    <li><?php echo htmlspecialchars($student); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-500">Список не указан</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Дополнительная информация для общих вопросов -->
                        <?php if ($req['request_type'] === 'general_question' && !empty($req['software_or_system'])): ?>
                            <div class="mb-4 p-3 bg-teal-50 rounded-lg border-l-4 border-teal-500">
                                <div class="text-xs font-semibold text-teal-800 mb-1 uppercase">
                                    <i class="fas fa-cog"></i> ПО / Система:
                                </div>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($req['software_or_system']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Последний комментарий техника (если есть) -->
                        <?php
                        $stmt = $pdo->prepare("SELECT c.id, c.comment, c.created_at, c.updated_at FROM comments c WHERE c.request_id = ? AND c.user_id = ? ORDER BY c.created_at DESC LIMIT 1");
                        $stmt->execute([$req['id'], $user['id']]);
                        $lastComment = $stmt->fetch();
                        
                        if ($lastComment && $tab === 'in_progress'): ?>
                            <div class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-500 rounded-r-lg">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1">
                                        <div class="text-xs font-semibold text-blue-800 mb-1">
                                            <i class="fas fa-comment"></i> МОЙ КОММЕНТАРИЙ:
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($lastComment['comment'])); ?></p>
                                        <div class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-clock"></i> <?php echo date('d.m.Y H:i', strtotime($lastComment['created_at'])); ?>
                                            <?php if ($lastComment['updated_at']): ?>
                                                <span class="ml-2">(изменён: <?php echo date('d.m.Y H:i', strtotime($lastComment['updated_at'])); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button onclick='editComment(<?php echo $req['id']; ?>, <?php echo $lastComment['id']; ?>, <?php echo json_encode($lastComment['comment'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition flex-shrink-0">
                                        <i class="fas fa-edit"></i> Редактировать
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Кнопки действий -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <?php if ($tab === 'active'): ?>
                                <!-- Кнопка "Взять в работу" -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="action" value="take_to_work">
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold">
                                        <i class="fas fa-hand-paper"></i> Взять в работу
                                    </button>
                                </form>
                            <?php elseif ($tab === 'in_progress'): ?>
                                <!-- Кнопка "Добавить комментарий" -->
                                <button onclick="showCommentModal(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold" title="Добавить внутренний комментарий для себя или других техников">
                                    <i class="fas fa-comment"></i> Комментарий
                                </button>
                                
                                <!-- Кнопка "Отправить директору" (только если НЕ одобрено) -->
                                <?php if ($req['status'] !== 'approved'): ?>
                                    <button onclick="showDirectorModal(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold">
                                        <i class="fas fa-paper-plane"></i> На согласование
                                    </button>
                                <?php endif; ?>
                                
                                <!-- Кнопка "Завершить" -->
                                <button onclick="showCompleteModal(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold">
                                    <i class="fas fa-check-circle"></i> Завершить
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($tab === 'active' || $tab === 'in_progress'): ?>
                                <!-- Кнопка "Отклонить" -->
                                <button onclick="showRejectForm(<?php echo $req['id']; ?>)" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                    <i class="fas fa-times-circle"></i> Отклонить
                                </button>
                                
                                <!-- Установка срока (только если НЕ установлен) -->
                                <?php if (empty($req['deadline'])): ?>
                                    <form method="POST" class="inline flex gap-2" onsubmit="return validateDeadline(this)">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="action" value="set_deadline">
                                        <input 
                                            type="date" 
                                            name="deadline" 
                                            class="px-3 py-2 border rounded deadline-input" 
                                            min="<?php echo date('Y-m-d', strtotime($req['created_at'])); ?>"
                                            data-created="<?php echo date('Y-m-d', strtotime($req['created_at'])); ?>"
                                            required>
                                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                            <i class="fas fa-calendar-alt"></i> Установить срок
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Кнопка продления срока для просроченных или скоро истекающих -->
                                    <?php 
                                    $deadline = strtotime($req['deadline']);
                                    $now = time();
                                    $isOverdue = $deadline < $now;
                                    $daysLeft = ceil(($deadline - $now) / 86400);
                                    if ($isOverdue || $daysLeft <= 2): 
                                    ?>
                                        <button onclick="showExtendDeadlineModal(<?php echo $req['id']; ?>, '<?php echo date('Y-m-d', strtotime($req['created_at'])); ?>', '<?php echo date('Y-m-d', strtotime($req['deadline'])); ?>')" 
                                                class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                                            <i class="fas fa-clock"></i> <?php echo $isOverdue ? 'Продлить срок' : 'Изменить срок'; ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- История действий (сворачиваемая) -->
                        <?php
                        // Получаем историю действий из request_logs
                        $stmt = $pdo->prepare("
                            SELECT rl.*, u.full_name, u.role 
                            FROM request_logs rl 
                            JOIN users u ON rl.user_id = u.id 
                            WHERE rl.request_id = ? 
                            ORDER BY rl.created_at ASC
                        ");
                        $stmt->execute([$req['id']]);
                        $logs = $stmt->fetchAll();
                        
                        if ($logs): ?>
                            <div class="mt-3 pt-3 border-t">
                                <button onclick="toggleHistory(<?php echo $req['id']; ?>)" class="text-xs font-semibold text-gray-600 uppercase hover:text-indigo-600 transition flex items-center gap-2">
                                    <i class="fas fa-history"></i> 
                                    <span>История действий (<?php echo count($logs); ?>)</span>
                                    <i id="history-icon-<?php echo $req['id']; ?>" class="fas fa-chevron-down text-xs"></i>
                                </button>
                                
                                <div id="history-<?php echo $req['id']; ?>" style="display: none;" class="mt-2">
                                    <?php foreach ($logs as $log): ?>
                                        <div class="text-sm text-gray-600 mb-1 flex items-start gap-2">
                                            <?php
                                            // Иконки для разных типов действий
                                            switch($log['action']) {
                                                case 'assigned':
                                                    $icon = '<i class="fas fa-user-check text-green-600"></i>';
                                                    break;
                                                case 'sent_for_approval':
                                                    $icon = '<i class="fas fa-paper-plane text-blue-600"></i>';
                                                    break;
                                                case 'sent_to_director':
                                                    $icon = '<i class="fas fa-user-tie text-purple-600"></i>';
                                                    break;
                                                case 'confirmed':
                                                    $icon = '<i class="fas fa-check-circle text-green-600"></i>';
                                                    break;
                                                case 'returned':
                                                    $icon = '<i class="fas fa-undo text-orange-600"></i>';
                                                    break;
                                                case 'rejected':
                                                    $icon = '<i class="fas fa-times-circle text-red-600"></i>';
                                                    break;
                                                case 'comment_added':
                                                    $icon = '<i class="fas fa-comment text-blue-600"></i>';
                                                    break;
                                                case 'deadline_set':
                                                    $icon = '<i class="fas fa-clock text-yellow-600"></i>';
                                                    break;
                                                default:
                                                    $icon = '<i class="fas fa-circle text-gray-400"></i>';
                                                    break;
                                            }
                                            echo $icon;
                                            ?>
                                            <span class="flex-1">
                                                <strong><?php echo date('d.m.Y H:i', strtotime($log['created_at'])); ?></strong>
                                                - <?php echo htmlspecialchars($log['comment']); ?>
                                                <span class="text-gray-500">(<?php echo htmlspecialchars($log['full_name']); ?>)</span>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Кнопка "Просмотр деталей заявки" -->
                                    <div class="mt-3 pt-2 border-t">
                                        <a href="view_request.php?id=<?php echo $req['id']; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm">
                                            <i class="fas fa-file-alt"></i> Просмотр деталей заявки
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Информация об одобрении директором -->
                        <?php if ($req['status'] === 'approved' && $req['approved_at']): ?>
                            <div class="mt-3 p-4 bg-green-50 border-l-4 border-green-500 rounded-r-lg">
                                <div class="flex items-center gap-2 text-green-800 font-semibold mb-2">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Одобрено директором</span>
                                </div>
                                <div class="text-sm text-gray-700">
                                    <i class="fas fa-clock mr-1"></i>
                                    <strong>Время одобрения:</strong> <?php echo date('d.m.Y H:i', strtotime($req['approved_at'])); ?>
                                </div>
                                <?php 
                                // Получить комментарий директора при одобрении
                                $stmt = $pdo->prepare("SELECT c.comment, c.created_at, u.full_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.request_id = ? AND u.role = 'director' ORDER BY c.created_at DESC LIMIT 1");
                                $stmt->execute([$req['id']]);
                                $directorComment = $stmt->fetch();
                                if ($directorComment && strpos($directorComment['comment'], 'отклонена') === false):
                                ?>
                                    <div class="mt-2 text-sm text-gray-700">
                                        <i class="fas fa-comment mr-1"></i>
                                        <strong>Комментарий директора:</strong> <?php echo htmlspecialchars($directorComment['comment']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($req['completed_at'] && $tab === 'archive'): ?>
                            <div class="mt-1 text-sm text-gray-500">
                                <i class="fas fa-check-circle text-green-600"></i> Завершена: <?php echo date('d.m.Y H:i', strtotime($req['completed_at'])); ?>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- ════════════════════════════════════════════════════════ -->
        <!-- ════════════════════════════════════════════════════════ -->
        <!-- ОТОБРАЖЕНИЕ ЗАДАЧ IT-ОТДЕЛА -->
        <!-- ════════════════════════════════════════════════════════ -->
        <?php if ($tab === 'my_tasks'): ?>
            
            <?php 
            // Под-вкладка задач (по умолчанию - пул)
            $taskSubTab = $_GET['subtab'] ?? 'pool';
            ?>
            
            <!-- Под-вкладки задач IT-отдела -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="flex items-center justify-between p-4 border-b">
                    <nav class="flex gap-2">
                        <a href="?tab=my_tasks&subtab=pool" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $taskSubTab === 'pool' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <i class="fas fa-layer-group"></i>
                            Пул задач
                            <?php if (count($poolTasks) > 0): ?>
                                <span class="px-2 py-0.5 <?php echo $taskSubTab === 'pool' ? 'bg-white/20' : 'bg-green-100 text-green-800'; ?> rounded-full text-xs"><?php echo count($poolTasks); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=my_tasks&subtab=my" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $taskSubTab === 'my' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <i class="fas fa-clipboard-check"></i>
                            Мои задачи
                            <?php if (count($tasks) > 0): ?>
                                <span class="px-2 py-0.5 <?php echo $taskSubTab === 'my' ? 'bg-white/20' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs"><?php echo count($tasks); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=my_tasks&subtab=archive" class="px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?php echo $taskSubTab === 'archive' ? 'bg-gray-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            <i class="fas fa-archive"></i>
                            Архив
                            <?php if (count($archivedTasks) > 0): ?>
                                <span class="px-2 py-0.5 <?php echo $taskSubTab === 'archive' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'; ?> rounded-full text-xs"><?php echo count($archivedTasks); ?></span>
                            <?php endif; ?>
                        </a>
                    </nav>
                    
                    <!-- Кнопка создания -->
                    <button onclick="showCreateTaskModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2 font-semibold">
                        <i class="fas fa-plus"></i>
                        Создать задачу
                    </button>
                </div>
            </div>
            
            <!-- ========== ПУЛ ЗАДАЧ ========== -->
            <?php if ($taskSubTab === 'pool'): ?>
                <?php if (!empty($poolTasks)): ?>
                    <div class="space-y-4">
                    <?php foreach ($poolTasks as $task): ?>
                        <div class="bg-white border border-gray-200 border-l-4 <?php 
                            echo $task['priority'] === 'urgent' ? 'border-l-red-500' : 
                                ($task['priority'] === 'high' ? 'border-l-orange-500' : 
                                ($task['priority'] === 'normal' ? 'border-l-green-500' : 'border-l-gray-400')); 
                        ?> rounded-lg shadow-sm p-5 hover:shadow-md transition">
                            
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-3">
                                        <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-mono">#<?php echo $task['id']; ?></span>
                                        <h4 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($task['title']); ?></h4>
                                        
                                        <!-- Категория -->
                                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-semibold">
                                            <?php
                                            $categories = [
                                                'maintenance' => '🔧 Обслуживание',
                                                'update' => '🔄 Обновление',
                                                'purchase' => '🛒 Закупка',
                                                'inventory' => '📊 Инвентаризация',
                                                'installation' => '⚙️ Установка',
                                                'other' => '📋 Прочее'
                                            ];
                                            echo $categories[$task['category']] ?? '📋 Прочее';
                                            ?>
                                        </span>
                                        
                                        <!-- Статус -->
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                            <i class="fas fa-hand-paper"></i> ДОСТУПНА
                                        </span>
                                        
                                        <!-- Приоритет если высокий -->
                                        <?php if ($task['priority'] === 'urgent'): ?>
                                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">⚡ Срочно</span>
                                        <?php elseif ($task['priority'] === 'high'): ?>
                                            <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-bold">🔥 Высокий</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($task['description']): ?>
                                        <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                                        <?php if ($task['due_date']): ?>
                                            <?php
                                            $dueDate = strtotime($task['due_date']);
                                            $daysLeft = ceil(($dueDate - time()) / 86400);
                                            ?>
                                            <span class="flex items-center gap-1">
                                                <i class="fas fa-calendar-alt"></i>
                                                <?php if ($daysLeft < 0): ?>
                                                    <span class="text-red-600 font-bold">⚠️ Просрочено!</span>
                                                <?php elseif ($daysLeft === 0): ?>
                                                    <span class="text-orange-600 font-bold">Сегодня!</span>
                                                <?php else: ?>
                                                    <span>До <?php echo date('d.m.Y', $dueDate); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($task['creator_name']); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo date('d.m.Y H:i', strtotime($task['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Кнопка взятия -->
                                <div class="ml-4">
                                    <form method="POST">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="task_action" value="take_task">
                                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow font-semibold flex items-center gap-2">
                                            <i class="fas fa-hand-paper"></i> Взять в работу
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-md p-12 text-center">
                        <div class="bg-green-100 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-inbox text-4xl text-green-400"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">Пул задач пуст</h3>
                        <p class="text-gray-500">Все задачи взяты в работу или создайте новую</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- ========== МОИ ЗАДАЧИ В РАБОТЕ ========== -->
            <?php if ($taskSubTab === 'my'): ?>
                <?php if (!empty($tasks)): ?>
                <div class="space-y-4">
                    <?php foreach ($tasks as $task): ?>
                        <div class="bg-white border border-gray-200 border-l-4 <?php 
                            echo $task['priority'] === 'urgent' ? 'border-l-red-500' : 
                                ($task['priority'] === 'high' ? 'border-l-orange-500' : 
                                ($task['priority'] === 'normal' ? 'border-l-blue-500' : 'border-l-gray-400')); 
                        ?> rounded-lg shadow-sm p-5 hover:shadow-md transition">
                            
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-mono">#<?php echo $task['id']; ?></span>
                                        <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($task['title']); ?></h3>
                                        
                                        <!-- Статус -->
                                        <?php if ($task['status'] === 'in_progress'): ?>
                                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                                <i class="fas fa-spinner fa-spin"></i> В РАБОТЕ
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-semibold">
                                                <i class="fas fa-clock"></i> ОЖИДАЕТ
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Категория -->
                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs">
                                            <?php echo $categories[$task['category']] ?? '📋 Прочее'; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($task['description']): ?>
                                        <p class="text-gray-600 text-sm mb-3"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                                        <?php if ($task['due_date']): ?>
                                            <?php
                                            $dueDate = strtotime($task['due_date']);
                                            $daysLeft = ceil(($dueDate - time()) / 86400);
                                            ?>
                                            <span class="flex items-center gap-1">
                                                <i class="fas fa-calendar-alt"></i>
                                                <?php if ($daysLeft < 0): ?>
                                                    <span class="text-red-600 font-semibold">Просрочено!</span>
                                                <?php elseif ($daysLeft === 0): ?>
                                                    <span class="text-orange-600 font-semibold">Сегодня!</span>
                                                <?php else: ?>
                                                    <span>До <?php echo date('d.m.Y', $dueDate); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-clock"></i> <?php echo date('d.m.Y H:i', strtotime($task['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Кнопки действий -->
                            <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t">
                                <?php if ($task['status'] === 'pending'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="task_action" value="start_task">
                                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                            <i class="fas fa-play"></i> Начать
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($task['status'] === 'in_progress'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="task_action" value="complete_task">
                                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                            <i class="fas fa-check"></i> Завершить
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button type="button" onclick="showDeadlineModal(<?php echo $task['id']; ?>, '<?php echo $task['due_date'] ?? ''; ?>')" 
                                        class="px-3 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200">
                                    <i class="fas fa-calendar-alt"></i> Срок
                                </button>
                                
                                <button type="button" onclick="showTaskCommentModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title'], ENT_QUOTES); ?>')" 
                                        class="px-3 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200">
                                    <i class="fas fa-comment"></i> Комментарий
                                </button>
                                
                                
                                
                                <button type="button" onclick="toggleTaskHistory(<?php echo $task['id']; ?>)" 
                                        class="px-3 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200">
                                    <i class="fas fa-history"></i> История
                                </button>
                                
                                <?php if ($task['created_by'] == $technicianId): ?>
                                    <button type="button" onclick="showCancelTaskModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title'], ENT_QUOTES); ?>')" 
                                            class="px-3 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    
                                    <button type="button" onclick="showDeleteTaskModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title'], ENT_QUOTES); ?>')" 
                                            class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- История действий -->
                            <?php
                            $stmtLogs = $pdo->prepare("SELECT tl.*, u.full_name FROM task_logs tl LEFT JOIN users u ON tl.user_id = u.id WHERE tl.task_id = ? ORDER BY tl.created_at DESC");
                            $stmtLogs->execute([$task['id']]);
                            $taskLogs = $stmtLogs->fetchAll();
                            
                            $stmtComments = $pdo->prepare("SELECT tc.*, u.full_name FROM task_comments tc LEFT JOIN users u ON tc.user_id = u.id WHERE tc.task_id = ? ORDER BY tc.created_at DESC");
                            $stmtComments->execute([$task['id']]);
                            $taskComments = $stmtComments->fetchAll();
                            ?>
                            
                            <div id="task-history-<?php echo $task['id']; ?>" style="display: none;" class="mt-4 pt-4 border-t">
                                <?php if (!empty($taskComments)): ?>
                                    <div class="mb-4">
                                        <h4 class="font-semibold text-gray-700 mb-2"><i class="fas fa-comments text-yellow-600"></i> Комментарии</h4>
                                        <div class="space-y-2 max-h-48 overflow-y-auto">
                                            <?php foreach ($taskComments as $comment): ?>
                                                <div class="bg-yellow-50 p-3 rounded-lg text-sm">
                                                    <div class="flex justify-between mb-1">
                                                        <span class="font-medium"><?php echo htmlspecialchars($comment['full_name']); ?></span>
                                                        <span class="text-xs text-gray-500"><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></span>
                                                    </div>
                                                    <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($taskLogs)): ?>
                                    <h4 class="font-semibold text-gray-700 mb-2"><i class="fas fa-history text-purple-600"></i> История</h4>
                                    <div class="space-y-1 max-h-48 overflow-y-auto text-sm">
                                        <?php 
                                        $taskActionTexts = [
                                            'created' => 'Создана',
                                            'taken_from_pool' => 'Взята из пула',
                                            'assigned' => 'Назначена',
                                            'started' => 'Начата',
                                            'completed' => 'Завершена',
                                            'cancelled' => 'Отменена',
                                            'returned_to_pool' => 'Возвращена в пул',
                                            'comment_added' => 'Комментарий',
                                            'priority_changed' => 'Приоритет изменён',
                                            'deadline_set' => 'Установлен срок',
                                            'reopened' => 'Переоткрыта'
                                        ];
                                        foreach ($taskLogs as $log): 
                                            $actionText = $taskActionTexts[$log['action']] ?? $log['action'];
                                        ?>
                                            <div class="flex items-center gap-2 py-1 border-b border-gray-100">
                                                <span class="text-gray-600"><?php echo $actionText; ?></span>
                                                <span class="text-gray-400">•</span>
                                                <span class="text-gray-500"><?php echo htmlspecialchars($log['full_name']); ?></span>
                                                <span class="text-xs text-gray-400 ml-auto"><?php echo date('d.m H:i', strtotime($log['created_at'])); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500 italic">История пуста</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Заметки -->
                            <?php if (!empty($task['notes'])): ?>
                                <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm">
                                    <div class="font-semibold text-yellow-800 mb-1"><i class="fas fa-sticky-note"></i> Мои заметки:</div>
                                    <div class="text-gray-700"><?php echo nl2br(htmlspecialchars($task['notes'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-md p-12 text-center">
                        <div class="bg-blue-100 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-clipboard-check text-4xl text-blue-400"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">Нет задач в работе</h3>
                        <p class="text-gray-500">Возьмите задачу из пула или создайте новую</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- ========== АРХИВ ЗАДАЧ IT-ОТДЕЛА ========== -->
            <?php if ($taskSubTab === 'archive'): ?>
                <?php if (!empty($archivedTasks)): ?>
                    <div class="space-y-3">
                        <?php foreach ($archivedTasks as $task): ?>
                            <div class="bg-white border border-gray-200 border-l-4 <?php 
                                echo $task['status'] === 'completed' ? 'border-l-green-500' : 'border-l-gray-400'; 
                            ?> rounded-lg p-4 hover:shadow-md transition">
                                
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <span class="px-2 py-1 bg-gray-200 text-gray-600 rounded text-xs font-mono">#<?php echo $task['id']; ?></span>
                                            <h4 class="text-base font-semibold text-gray-700"><?php echo htmlspecialchars($task['title']); ?></h4>
                                            
                                            <!-- Статус -->
                                            <?php if ($task['status'] === 'completed'): ?>
                                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-check-circle"></i> ЗАВЕРШЕНА
                                                </span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 bg-gray-200 text-gray-600 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-ban"></i> ОТМЕНЕНА
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Категория -->
                                            <span class="px-2 py-1 bg-purple-50 text-purple-600 rounded text-xs">
                                                <?php
                                                $categories = [
                                                    'maintenance' => '🔧 Обслуживание',
                                                    'update' => '🔄 Обновление',
                                                    'purchase' => '🛒 Закупка',
                                                    'inventory' => '📊 Инвентаризация',
                                                    'installation' => '⚙️ Установка',
                                                    'other' => '📋 Прочее'
                                                ];
                                                echo $categories[$task['category']] ?? '📋 Прочее';
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($task['description']): ?>
                                            <p class="text-gray-500 text-sm mb-2"><?php echo mb_substr(htmlspecialchars($task['description']), 0, 100); ?><?php echo mb_strlen($task['description']) > 100 ? '...' : ''; ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($task['creator_name']); ?></span>
                                            <span><i class="fas fa-calendar-plus"></i> Создана: <?php echo date('d.m.Y', strtotime($task['created_at'])); ?></span>
                                            <?php if ($task['completed_at']): ?>
                                                <span><i class="fas fa-calendar-check"></i> <?php echo $task['status'] === 'completed' ? 'Завершена' : 'Отменена'; ?>: <?php echo date('d.m.Y H:i', strtotime($task['completed_at'])); ?></span>
                                            <?php endif; ?>
                                            <?php if ($task['assigned_name']): ?>
                                                <span><i class="fas fa-user-check"></i> Исполнитель: <?php echo htmlspecialchars($task['assigned_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Кнопка истории -->
                                    <button type="button" onclick="toggleTaskHistory(<?php echo $task['id']; ?>)" 
                                            class="px-3 py-2 bg-gray-200 text-gray-600 rounded-lg hover:bg-gray-300 transition text-sm">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </div>
                                
                                <!-- История (скрыта по умолчанию) -->
                                <?php
                                $stmtLogs = $pdo->prepare("SELECT tl.*, u.full_name FROM task_logs tl LEFT JOIN users u ON tl.user_id = u.id WHERE tl.task_id = ? ORDER BY tl.created_at DESC");
                                $stmtLogs->execute([$task['id']]);
                                $taskLogs = $stmtLogs->fetchAll();
                                ?>
                                <div id="task-history-<?php echo $task['id']; ?>" style="display: none;" class="mt-3 pt-3 border-t border-gray-200">
                                    <?php if (!empty($taskLogs)): ?>
                                        <div class="space-y-1 text-xs text-gray-500">
                                            <?php 
                                            $taskActionTexts = [
                                                'created' => 'Создана',
                                                'taken_from_pool' => 'Взята из пула',
                                                'assigned' => 'Назначена',
                                                'started' => 'Начата',
                                                'completed' => 'Завершена',
                                                'cancelled' => 'Отменена',
                                                'returned_to_pool' => 'Возвращена в пул',
                                                'comment_added' => 'Комментарий',
                                                'priority_changed' => 'Приоритет изменён',
                                                'deadline_set' => 'Установлен срок',
                                                'reopened' => 'Переоткрыта'
                                            ];
                                            foreach ($taskLogs as $log): 
                                                $actionText = $taskActionTexts[$log['action']] ?? $log['action'];
                                            ?>
                                                <div class="flex items-center gap-2 py-1">
                                                    <span><?php echo $actionText; ?></span>
                                                    <span class="text-gray-400">•</span>
                                                    <span><?php echo htmlspecialchars($log['full_name']); ?></span>
                                                    <span class="ml-auto text-gray-400"><?php echo date('d.m H:i', strtotime($log['created_at'])); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-xs text-gray-400 italic">История пуста</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-md p-12 text-center">
                        <div class="bg-gray-100 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-archive text-4xl text-gray-400"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">Архив пуст</h3>
                        <p class="text-gray-500">Завершённые задачи появятся здесь</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        
    </div>
    </div>
    
    <!-- Модальное окно отклонения -->
    <div id="rejectModal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">Отклонить заявку</h3>
            <form method="POST">
                <input type="hidden" id="reject_request_id" name="request_id">
                <input type="hidden" name="action" value="reject">
                <textarea name="rejection_reason" rows="4" class="w-full px-3 py-2 border rounded mb-4" placeholder="Укажите причину отклонения..." required></textarea>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Отклонить</button>
                    <button type="button" onclick="closeRejectForm()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно комментария -->
    <div id="commentModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content">
            <h3 id="commentModalTitle" class="text-xl font-bold mb-2">
                <i class="fas fa-comment text-blue-600"></i>
                <span id="commentModalTitleText">Добавить внутренний комментарий</span>
            </h3>
            <p class="text-sm text-gray-600 mb-4">
                <i class="fas fa-info-circle text-blue-500"></i>
                Этот комментарий будет виден только системотехникам. Используйте для заметок о работе.
            </p>
            <form method="POST">
                <input type="hidden" id="comment_request_id" name="request_id">
                <input type="hidden" id="comment_id" name="comment_id" value="">
                <input type="hidden" id="comment_action" name="action" value="add_comment">
                <textarea id="comment_text" name="comment" rows="4" class="w-full px-3 py-2 border rounded mb-4" placeholder="Например: Нужно заказать запчасти, осталось переустановить драйвер..." required></textarea>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-paper-plane"></i> <span id="commentSubmitText">Отправить</span>
                    </button>
                    <button type="button" onclick="closeCommentModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно отправки директору -->
    <div id="directorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-paper-plane text-purple-600"></i>
                Отправить на согласование директору
            </h3>
            <form method="POST">
                <input type="hidden" id="director_request_id" name="request_id">
                <input type="hidden" name="action" value="send_to_director">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Комментарий (обязательно):</label>
                    <textarea name="comment" rows="4" class="w-full px-3 py-2 border rounded" placeholder="Опишите, что нужно согласовать. Например: 'Нужна покупка принтера HP LaserJet за 85 000₸'" required></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-check"></i> Отправить
                    </button>
                    <button type="button" onclick="closeDirectorModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно завершения -->
    <div id="completeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-check-circle text-green-600"></i>
                Завершить заявку
            </h3>
            <form method="POST">
                <input type="hidden" id="complete_request_id" name="request_id">
                <input type="hidden" name="action" value="complete">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Комментарий о выполненной работе (необязательно):</label>
                    <textarea name="comment" rows="4" class="w-full px-3 py-2 border rounded" placeholder="Опишите, что было сделано. Например: 'Заменил мышь, почистил клавиатуру'"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-check"></i> Завершить
                    </button>
                    <button type="button" onclick="closeCompleteModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно создания задачи -->
    <div id="createTaskModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; overflow-y: auto;">
        <div class="modal-content" style="background: white; padding: 24px; border-radius: 12px; max-width: 600px; width: 90%; margin: 20px;">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-plus-circle text-green-600"></i>
                <span>Создать новую задачу</span>
            </h3>
            <form method="POST" id="createTaskForm" onsubmit="console.log('Form submitting...', new FormData(this)); return true;">
                <input type="hidden" name="task_action" value="create_task">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Название задачи: <span class="text-red-500">*</span></label>
                    <input type="text" name="task_title" required class="w-full px-3 py-2 border rounded" placeholder="Например: Профилактика компьютеров 4 этажа">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Категория:</label>
                    <select name="task_category" class="w-full px-3 py-2 border rounded">
                        <option value="maintenance">🔧 Профилактическое обслуживание</option>
                        <option value="update">🔄 Обновление ПО</option>
                        <option value="purchase">🛒 Закупка оборудования</option>
                        <option value="inventory">📊 Инвентаризация</option>
                        <option value="installation">⚙️ Установка оборудования</option>
                        <option value="other">📋 Прочее</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Приоритет:</label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="task_priority" value="low" class="text-gray-600">
                            <span>Низкий</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="task_priority" value="normal" checked class="text-blue-600">
                            <span>Обычный</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="task_priority" value="high" class="text-orange-600">
                            <span>Высокий</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="task_priority" value="urgent" class="text-red-600">
                            <span>Срочно</span>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Срок выполнения:</label>
                    <input type="date" name="task_due_date" min="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border rounded">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Описание:</label>
                    <textarea name="task_description" rows="4" class="w-full px-3 py-2 border rounded" placeholder="Подробное описание задачи..."></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Назначить задачу:</label>
                    <select name="assign_to" class="w-full px-3 py-2 border rounded">
                        <option value="pool" selected>В общий пул IT-отдела (любой системотехник может взять)</option>
                        <option value="<?php echo $user['id']; ?>">Себе (<?php echo htmlspecialchars($user['full_name']); ?>)</option>
                        <?php foreach ($technicians as $tech): ?>
                            <?php if ($tech['id'] != $user['id']): ?>
                                <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['full_name']); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle"></i> Рекомендуется создавать в общий пул, чтобы любой системотехник мог её взять
                    </p>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-plus"></i> Создать задачу
                    </button>
                    <button type="button" onclick="closeCreateTaskModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ===== МОДАЛЬНЫЕ ОКНА ДЛЯ УПРАВЛЕНИЯ ЗАДАЧАМИ ===== -->
    
    <!-- Модальное окно отмены задачи -->
    <div id="cancelTaskModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div style="background: white; padding: 24px; border-radius: 12px; max-width: 450px; width: 90%;">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2 text-gray-700">
                <i class="fas fa-ban text-gray-500"></i>
                <span>Отменить задачу</span>
            </h3>
            <p class="text-gray-600 mb-4">Вы уверены что хотите отменить задачу:</p>
            <p class="font-semibold text-gray-800 mb-4 p-3 bg-gray-100 rounded" id="cancel_task_title"></p>
            <form method="POST">
                <input type="hidden" name="task_id" id="cancel_task_id">
                <input type="hidden" name="task_action" value="cancel_task">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Причина отмены (необязательно):</label>
                    <textarea name="cancel_reason" rows="2" class="w-full px-3 py-2 border rounded" placeholder="Укажите причину отмены..."></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        <i class="fas fa-ban"></i> Отменить задачу
                    </button>
                    <button type="button" onclick="closeCancelTaskModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-times"></i> Назад
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно удаления задачи -->
    <div id="deleteTaskModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div style="background: white; padding: 24px; border-radius: 12px; max-width: 450px; width: 90%;">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2 text-red-600">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Удалить задачу</span>
            </h3>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <p class="text-red-700 font-semibold mb-2">⚠️ Внимание!</p>
                <p class="text-red-600 text-sm">Это действие нельзя отменить. Задача и вся связанная информация (комментарии, история) будут удалены навсегда.</p>
            </div>
            <p class="text-gray-600 mb-2">Удалить задачу:</p>
            <p class="font-semibold text-gray-800 mb-4 p-3 bg-gray-100 rounded" id="delete_task_title"></p>
            <form method="POST">
                <input type="hidden" name="task_id" id="delete_task_id">
                <input type="hidden" name="task_action" value="delete_task">
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-trash"></i> Удалить навсегда
                    </button>
                    <button type="button" onclick="closeDeleteTaskModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно изменения срока задачи -->
    <div id="taskDeadlineModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div style="background: white; padding: 24px; border-radius: 12px; max-width: 400px; width: 90%;">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-alt text-indigo-600"></i>
                <span>Изменить срок выполнения</span>
            </h3>
            <form method="POST">
                <input type="hidden" name="task_id" id="deadline_task_id">
                <input type="hidden" name="task_action" value="update_deadline">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Новый срок:</label>
                    <input type="date" name="new_deadline" id="task_new_deadline" min="<?php echo date('Y-m-d'); ?>" 
                           class="w-full px-3 py-2 border rounded focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <p class="text-xs text-gray-500 mt-1">Оставьте пустым чтобы убрать срок</p>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                    <button type="button" onclick="closeDeadlineModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно комментария к задаче -->
    <div id="taskCommentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div style="background: white; padding: 24px; border-radius: 12px; max-width: 500px; width: 90%;">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-comment text-yellow-600"></i>
                <span>Добавить комментарий</span>
            </h3>
            <p class="text-sm text-gray-600 mb-4">Задача: <strong id="comment_task_title"></strong></p>
            <form method="POST">
                <input type="hidden" name="task_id" id="comment_task_id">
                <input type="hidden" name="task_action" value="add_task_comment">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Комментарий:</label>
                    <textarea name="task_comment" id="task_comment_text" rows="4" required
                              class="w-full px-3 py-2 border rounded focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200"
                              placeholder="Напишите комментарий..."></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                        <i class="fas fa-paper-plane"></i> Добавить
                    </button>
                    <button type="button" onclick="closeTaskCommentModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно заметок к задаче -->
    <div id="taskNotesModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div style="background: white; padding: 24px; border-radius: 12px; max-width: 500px; width: 90%;">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-sticky-note text-gray-600"></i>
                <span>Личные заметки</span>
            </h3>
            <p class="text-xs text-gray-500 mb-4">Эти заметки видны только вам</p>
            <form method="POST">
                <input type="hidden" name="task_id" id="notes_task_id">
                <input type="hidden" name="task_action" value="update_notes">
                
                <div class="mb-4">
                    <textarea name="task_notes" id="task_notes_text" rows="6"
                              class="w-full px-3 py-2 border rounded focus:border-gray-500 focus:ring-2 focus:ring-gray-200"
                              placeholder="Ваши заметки по задаче..."></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                    <button type="button" onclick="closeNotesModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно продления срока -->
    <div id="extendDeadlineModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div class="modal-content" style="background: white; padding: 24px; border-radius: 12px; max-width: 500px; width: 90%;">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-clock text-yellow-600"></i>
                <span id="extendModalTitle">Продление срока выполнения</span>
            </h3>
            <form method="POST" onsubmit="return validateExtendDeadline(this)">
                <input type="hidden" name="request_id" id="extend_request_id">
                <input type="hidden" name="action" value="extend_deadline">
                <input type="hidden" id="extend_created_date">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Текущий срок:</label>
                    <div class="px-3 py-2 bg-gray-100 rounded font-medium" id="current_deadline_display"></div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Новый срок: <span class="text-red-500">*</span></label>
                    <input 
                        type="date" 
                        name="new_deadline" 
                        id="new_deadline_input"
                        class="w-full px-3 py-2 border rounded"
                        required>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle"></i> Срок не может быть раньше даты создания заявки
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Причина продления:</label>
                    <textarea name="extension_reason" rows="3" class="w-full px-3 py-2 border rounded" placeholder="Укажите причину продления срока (необязательно)"></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                        <i class="fas fa-check"></i> Продлить срок
                    </button>
                    <button type="button" onclick="closeExtendDeadlineModal()" class="flex-1 px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Модальное окно отклонения
        function showRejectForm(requestId) {
            const modal = document.getElementById('rejectModal');
            document.getElementById('reject_request_id').value = requestId;
            modal.style.display = 'flex';
        }
        function closeRejectForm() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        // Модальное окно комментария
        function showCommentModal(requestId) {
            const modal = document.getElementById('commentModal');
            document.getElementById('comment_request_id').value = requestId;
            document.getElementById('comment_id').value = '';
            document.getElementById('comment_action').value = 'add_comment';
            document.getElementById('comment_text').value = '';
            document.getElementById('commentModalTitleText').textContent = 'Добавить внутренний комментарий';
            document.getElementById('commentSubmitText').textContent = 'Отправить';
            modal.style.display = 'flex';
        }
        
        function editComment(requestId, commentId, currentText) {
            const modal = document.getElementById('commentModal');
            document.getElementById('comment_request_id').value = requestId;
            document.getElementById('comment_id').value = commentId;
            document.getElementById('comment_action').value = 'edit_comment';
            document.getElementById('comment_text').value = currentText;
            document.getElementById('commentModalTitleText').textContent = 'Редактировать комментарий';
            document.getElementById('commentSubmitText').textContent = 'Сохранить';
            modal.style.display = 'flex';
        }
        
        function closeCommentModal() {
            document.getElementById('commentModal').style.display = 'none';
            document.getElementById('comment_text').value = '';
        }
        
        // Модальное окно отправки директору
        function showDirectorModal(requestId) {
            const modal = document.getElementById('directorModal');
            document.getElementById('director_request_id').value = requestId;
            modal.style.display = 'flex';
        }
        function closeDirectorModal() {
            document.getElementById('directorModal').style.display = 'none';
        }
        
        // Модальное окно завершения
        function showCompleteModal(requestId) {
            const modal = document.getElementById('completeModal');
            document.getElementById('complete_request_id').value = requestId;
            modal.style.display = 'flex';
        }
        function closeCompleteModal() {
            document.getElementById('completeModal').style.display = 'none';
        }
        
        // Функция сворачивания/разворачивания истории
        function toggleHistory(requestId) {
            const historyDiv = document.getElementById('history-' + requestId);
            const icon = document.getElementById('history-icon-' + requestId);
            
            if (historyDiv.style.display === 'none') {
                historyDiv.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                historyDiv.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }
        
        // Валидация срока выполнения
        function validateDeadline(form) {
            const deadlineInput = form.querySelector('.deadline-input');
            const selectedDate = new Date(deadlineInput.value);
            const createdDate = new Date(deadlineInput.dataset.created);
            
            if (selectedDate < createdDate) {
                alert('Ошибка: Срок выполнения не может быть раньше даты создания заявки (' + formatDate(createdDate) + ')');
                return false;
            }
            return true;
        }
        
        // Модальное окно продления срока
        function showExtendDeadlineModal(requestId, createdDate, currentDeadline) {
            const modal = document.getElementById('extendDeadlineModal');
            document.getElementById('extend_request_id').value = requestId;
            document.getElementById('extend_created_date').value = createdDate;
            document.getElementById('new_deadline_input').min = createdDate;
            
            // Отображаем текущий срок
            const deadline = new Date(currentDeadline);
            document.getElementById('current_deadline_display').textContent = formatDate(deadline);
            
            // Устанавливаем минимальную дату как дату создания
            document.getElementById('new_deadline_input').value = '';
            
            modal.style.display = 'flex';
        }
        
        function closeExtendDeadlineModal() {
            document.getElementById('extendDeadlineModal').style.display = 'none';
        }
        
        // Модальное окно создания задачи
        function showCreateTaskModal() {
            document.getElementById('createTaskModal').style.display = 'flex';
        }
        
        function closeCreateTaskModal() {
            document.getElementById('createTaskModal').style.display = 'none';
        }
        
        // Закрытие модальных окон по клику вне них
        window.onclick = function(event) {
            // ОТКЛЮЧЕНО для createTaskModal - иначе форма не отправляется
            // if (event.target.id === 'createTaskModal') {
            //     closeCreateTaskModal();
            // }
            if (event.target.id === 'extendDeadlineModal') {
                closeExtendDeadlineModal();
            }
        }
        
        // Валидация продления срока
        function validateExtendDeadline(form) {
            const newDeadlineInput = form.querySelector('#new_deadline_input');
            const createdDate = new Date(document.getElementById('extend_created_date').value);
            const selectedDate = new Date(newDeadlineInput.value);
            
            if (selectedDate < createdDate) {
                alert('Ошибка: Новый срок не может быть раньше даты создания заявки (' + formatDate(createdDate) + ')');
                return false;
            }
            return true;
        }
        
        // Форматирование даты
        function formatDate(date) {
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return day + '.' + month + '.' + year;
        }
        
        // ===== МОДАЛЬНЫЕ ОКНА ДЛЯ ЗАДАЧ =====
        
        // Изменение срока задачи
        function showDeadlineModal(taskId, currentDeadline) {
            document.getElementById('deadline_task_id').value = taskId;
            document.getElementById('task_new_deadline').value = currentDeadline || '';
            document.getElementById('taskDeadlineModal').style.display = 'flex';
        }
        
        function closeDeadlineModal() {
            document.getElementById('taskDeadlineModal').style.display = 'none';
        }
        
        // Комментарий к задаче
        function showTaskCommentModal(taskId, taskTitle) {
            document.getElementById('comment_task_id').value = taskId;
            document.getElementById('comment_task_title').textContent = taskTitle;
            document.getElementById('task_comment_text').value = '';
            document.getElementById('taskCommentModal').style.display = 'flex';
        }
        
        function closeTaskCommentModal() {
            document.getElementById('taskCommentModal').style.display = 'none';
        }
        
        // Заметки к задаче
        function showNotesModal(taskId, currentNotes) {
            document.getElementById('notes_task_id').value = taskId;
            document.getElementById('task_notes_text').value = currentNotes || '';
            document.getElementById('taskNotesModal').style.display = 'flex';
        }
        
        function closeNotesModal() {
            document.getElementById('taskNotesModal').style.display = 'none';
        }
        
        // Отмена задачи
        function showCancelTaskModal(taskId, taskTitle) {
            document.getElementById('cancel_task_id').value = taskId;
            document.getElementById('cancel_task_title').textContent = taskTitle;
            document.getElementById('cancelTaskModal').style.display = 'flex';
        }
        
        function closeCancelTaskModal() {
            document.getElementById('cancelTaskModal').style.display = 'none';
        }
        
        // Удаление задачи
        function showDeleteTaskModal(taskId, taskTitle) {
            document.getElementById('delete_task_id').value = taskId;
            document.getElementById('delete_task_title').textContent = taskTitle;
            document.getElementById('deleteTaskModal').style.display = 'flex';
        }
        
        function closeDeleteTaskModal() {
            document.getElementById('deleteTaskModal').style.display = 'none';
        }
        
        // Переключение истории задачи
        function toggleTaskHistory(taskId) {
            const historyDiv = document.getElementById('task-history-' + taskId);
            if (historyDiv.style.display === 'none') {
                historyDiv.style.display = 'block';
            } else {
                historyDiv.style.display = 'none';
            }
        }
        
        // Переключение архива задач IT-отдела
        function toggleTaskArchive() {
            const content = document.getElementById('task-archive-content');
            const icon = document.getElementById('archive-toggle-icon');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.style.transform = 'rotate(180deg)';
            } else {
                content.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            }
        }
    </script>
    
</body>
</html>