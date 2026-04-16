<?php
// bot/helpers/database.php - Работа с базой данных

require_once __DIR__ . '/../config.php';

/**
 * Получить пользователя по Telegram ID
 */
function getUserByTelegramId($pdo, $telegramId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE telegram_id = ? AND role = 'technician'
        ");
        $stmt->execute([$telegramId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        botLog("DB Error in getUserByTelegramId: " . $e->getMessage());
        return false;
    }
}

/**
 * Привязать Telegram ID к пользователю
 */
function linkTelegramAccount($pdo, $username, $telegramId, $telegramUsername) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET telegram_id = ?, 
                telegram_username = ?,
                telegram_notifications = 1
            WHERE username = ? AND role = 'technician'
        ");
        
        $result = $stmt->execute([$telegramId, $telegramUsername, $username]);
        
        if ($stmt->rowCount() > 0) {
            botLog("Telegram account linked", [
                'username' => $username,
                'telegram_id' => $telegramId
            ]);
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        botLog("DB Error in linkTelegramAccount: " . $e->getMessage());
        return false;
    }
}

/**
 * Получить новые заявки (одобренные, но не взятые в работу)
 */
function getNewRequests($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT * 
            FROM requests
            WHERE status = 'new'
            ORDER BY priority DESC, created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        botLog("DB Error in getNewRequests: " . $e->getMessage());
        return [];
    }
}

/**
 * Получить заявки конкретного техника
 */
function getTechnicianRequests($pdo, $technicianId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM requests
            WHERE assigned_to = ? AND status = 'in_progress'
            ORDER BY priority DESC, created_at DESC
        ");
        $stmt->execute([$technicianId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        botLog("DB Error in getTechnicianRequests: " . $e->getMessage());
        return [];
    }
}

/**
 * Получить все активные заявки
 */
function getAllActiveRequests($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT r.*, 
                   t.full_name as technician_name
            FROM requests r
            LEFT JOIN users t ON r.assigned_to = t.id
            WHERE r.status IN ('new', 'in_progress')
            ORDER BY r.priority DESC, r.created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        botLog("DB Error in getAllActiveRequests: " . $e->getMessage());
        return [];
    }
}

/**
 * Получить заявку по ID
 */
function getRequestById($pdo, $requestId) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   t.full_name as technician_name,
                   t.username as technician_username
            FROM requests r
            LEFT JOIN users t ON r.assigned_to = t.id
            WHERE r.id = ?
        ");
        $stmt->execute([$requestId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        botLog("DB Error in getRequestById: " . $e->getMessage());
        return false;
    }
}

/**
 * Взять заявку в работу
 */
function takeRequest($pdo, $requestId, $technicianId) {
    try {
        // Проверяем что заявка свободна
        $request = getRequestById($pdo, $requestId);
        
        if (!$request || !in_array($request['status'], ['new', 'approved'])) {
            return ['success' => false, 'message' => 'Заявка уже занята или недоступна'];
        }
        
        $stmt = $pdo->prepare("
            UPDATE requests 
            SET status = 'in_progress',
                assigned_to = ?,
                updated_at = NOW()
            WHERE id = ? AND status IN ('new', 'approved')
        ");
        
        $result = $stmt->execute([$technicianId, $requestId]);
        
        if ($stmt->rowCount() > 0) {
            botLog("Request taken", [
                'request_id' => $requestId,
                'technician_id' => $technicianId
            ]);
            return ['success' => true, 'message' => 'Заявка взята в работу'];
        }
        
        return ['success' => false, 'message' => 'Не удалось взять заявку'];
    } catch (PDOException $e) {
        botLog("DB Error in takeRequest: " . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка БД'];
    }
}

/**
 * Завершить заявку
 */
function completeRequest($pdo, $requestId, $technicianId) {
    try {
        // Проверяем что заявка принадлежит технику
        $request = getRequestById($pdo, $requestId);
        
        if (!$request || $request['assigned_to'] != $technicianId) {
            return ['success' => false, 'message' => 'Это не ваша заявка'];
        }
        
        if ($request['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Заявка не в работе'];
        }
        
        $stmt = $pdo->prepare("
            UPDATE requests 
            SET status = 'completed',
                updated_at = NOW()
            WHERE id = ? AND assigned_to = ?
        ");
        
        $result = $stmt->execute([$requestId, $technicianId]);
        
        if ($stmt->rowCount() > 0) {
            botLog("Request completed", [
                'request_id' => $requestId,
                'technician_id' => $technicianId
            ]);
            return ['success' => true, 'message' => 'Заявка завершена'];
        }
        
        return ['success' => false, 'message' => 'Не удалось завершить заявку'];
    } catch (PDOException $e) {
        botLog("DB Error in completeRequest: " . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка БД'];
    }
}

/**
 * Получить статистику техника
 */
function getTechnicianStats($pdo, $technicianId) {
    try {
        // Всего заявок в работе
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM requests 
            WHERE assigned_to = ? AND status = 'in_progress'
        ");
        $stmt->execute([$technicianId]);
        $inProgress = $stmt->fetch()['count'];
        
        // Завершено за сегодня
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM requests 
            WHERE assigned_to = ? 
              AND status = 'completed' 
              AND DATE(updated_at) = CURDATE()
        ");
        $stmt->execute([$technicianId]);
        $completedToday = $stmt->fetch()['count'];
        
        // Завершено за неделю
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM requests 
            WHERE assigned_to = ? 
              AND status = 'completed' 
              AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$technicianId]);
        $completedWeek = $stmt->fetch()['count'];
        
        // Всего завершено
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM requests 
            WHERE assigned_to = ? AND status = 'completed'
        ");
        $stmt->execute([$technicianId]);
        $completedTotal = $stmt->fetch()['count'];
        
        return [
            'in_progress' => $inProgress,
            'completed_today' => $completedToday,
            'completed_week' => $completedWeek,
            'completed_total' => $completedTotal
        ];
    } catch (PDOException $e) {
        botLog("DB Error in getTechnicianStats: " . $e->getMessage());
        return null;
    }
}

/**
 * Получить всех техников с включенными уведомлениями
 */
function getTechniciansForNotification($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, username, full_name, telegram_id, telegram_username
            FROM users
            WHERE role = 'technician' 
              AND telegram_id IS NOT NULL 
              AND telegram_notifications = 1
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        botLog("DB Error in getTechniciansForNotification: " . $e->getMessage());
        return [];
    }
}
?>