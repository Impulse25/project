<?php
/**
 * Функции для работы с архивом заявок и системой подтверждения
 * Добавьте этот код в ваш существующий файл с функциями заявок
 * или создайте новый файл api/archive.php
 */

// Предполагаем что у вас есть подключение к БД через $pdo

/**
 * Системотехник завершает работу по заявке
 * @param int $request_id ID заявки
 * @param int $tech_user_id ID системотехника
 * @param string $completion_note Примечание о выполнении
 * @return array
 */
function completeRequestByTech($pdo, $request_id, $tech_user_id, $completion_note = '') {
    try {
        $pdo->beginTransaction();
        
        // Обновляем заявку - ставим статус "ожидает подтверждения"
        $sql = "UPDATE requests 
                SET status = 'waiting_confirmation',
                    completed_at = NOW(),
                    completion_note = :note,
                    updated_at = NOW()
                WHERE id = :request_id 
                AND assigned_to = :tech_id 
                AND status = 'in_progress'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'request_id' => $request_id,
            'tech_id' => $tech_user_id,
            'note' => $completion_note
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Заявка не найдена или уже обработана');
        }
        
        // Логируем изменение статуса
        logStatusChange($pdo, $request_id, 'in_progress', 'waiting_confirmation', $tech_user_id, 'Системотехник завершил работу');
        
        // Отправляем уведомление учителю (опционально)
        notifyTeacherAboutCompletion($pdo, $request_id);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Заявка отправлена на подтверждение учителю'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Учитель подтверждает выполнение заявки
 * @param int $request_id ID заявки
 * @param int $teacher_user_id ID учителя
 * @param string $feedback Отзыв учителя
 * @return array
 */
function confirmRequestByTeacher($pdo, $request_id, $teacher_user_id, $feedback = '') {
    try {
        $pdo->beginTransaction();
        
        // Проверяем что заявка принадлежит этому учителю
        $check_sql = "SELECT created_by FROM requests WHERE id = :request_id AND status = 'waiting_confirmation'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['request_id' => $request_id]);
        $request = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request || $request['created_by'] != $teacher_user_id) {
            throw new Exception('Вы не можете подтвердить эту заявку');
        }
        
        // Подтверждаем и архивируем заявку
        $sql = "UPDATE requests 
                SET status = 'completed',
                    confirmed_at = NOW(),
                    confirmed_by = :teacher_id,
                    teacher_feedback = :feedback,
                    is_archived = 1,
                    updated_at = NOW()
                WHERE id = :request_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'request_id' => $request_id,
            'teacher_id' => $teacher_user_id,
            'feedback' => $feedback
        ]);
        
        // Логируем
        logStatusChange($pdo, $request_id, 'waiting_confirmation', 'completed', $teacher_user_id, 'Учитель подтвердил выполнение');
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Заявка подтверждена и перемещена в архив'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Учитель отклоняет выполнение заявки (возвращает в работу)
 * @param int $request_id ID заявки
 * @param int $teacher_user_id ID учителя
 * @param string $rejection_reason Причина отклонения
 * @return array
 */
function rejectRequestByTeacher($pdo, $request_id, $teacher_user_id, $rejection_reason) {
    try {
        $pdo->beginTransaction();
        
        $sql = "UPDATE requests 
                SET status = 'in_progress',
                    completed_at = NULL,
                    rejection_reason = :reason,
                    updated_at = NOW()
                WHERE id = :request_id 
                AND created_by = :teacher_id 
                AND status = 'waiting_confirmation'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'request_id' => $request_id,
            'teacher_id' => $teacher_user_id,
            'reason' => $rejection_reason
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Заявка не найдена');
        }
        
        logStatusChange($pdo, $request_id, 'waiting_confirmation', 'in_progress', $teacher_user_id, 'Учитель вернул заявку: ' . $rejection_reason);
        
        // Уведомляем системотехника
        notifyTechAboutRejection($pdo, $request_id, $rejection_reason);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Заявка возвращена в работу'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Получить архивные заявки учителя
 * @param int $teacher_id ID учителя
 * @param int $limit Количество записей
 * @param int $offset Смещение для пагинации
 * @return array
 */
function getArchivedRequests($pdo, $teacher_id, $limit = 20, $offset = 0) {
    $sql = "SELECT 
                r.*,
                u_tech.full_name as tech_name,
                u_teacher.full_name as teacher_name,
                DATEDIFF(r.confirmed_at, r.created_at) as days_to_complete
            FROM requests r
            LEFT JOIN users u_tech ON r.assigned_to = u_tech.id
            LEFT JOIN users u_teacher ON r.confirmed_by = u_teacher.id
            WHERE r.created_by = :teacher_id 
            AND r.is_archived = 1
            AND r.status = 'completed'
            ORDER BY r.confirmed_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('teacher_id', $teacher_id, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Получить заявки ожидающие подтверждения
 * @param int $teacher_id ID учителя
 * @return array
 */
function getRequestsWaitingConfirmation($pdo, $teacher_id) {
    $sql = "SELECT 
                r.*,
                u.full_name as tech_name
            FROM requests r
            LEFT JOIN users u ON r.assigned_to = u.id
            WHERE r.created_by = :teacher_id 
            AND r.status = 'waiting_confirmation'
            ORDER BY r.completed_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['teacher_id' => $teacher_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Логирование изменения статуса
 */
function logStatusChange($pdo, $request_id, $old_status, $new_status, $user_id, $comment = '') {
    $sql = "INSERT INTO request_status_history 
            (request_id, old_status, new_status, changed_by, comment) 
            VALUES (:request_id, :old_status, :new_status, :user_id, :comment)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'request_id' => $request_id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'user_id' => $user_id,
        'comment' => $comment
    ]);
}

/**
 * Уведомления (заглушки - реализуйте согласно вашей системе)
 */
function notifyTeacherAboutCompletion($pdo, $request_id) {
    // TODO: Отправить email или push-уведомление учителю
    // Можно использовать вашу существующую систему уведомлений
}

function notifyTechAboutRejection($pdo, $request_id, $reason) {
    // TODO: Уведомить системотехника о возврате заявки
}

/**
 * Получить статистику по архиву
 */
function getArchiveStatistics($pdo, $teacher_id) {
    $sql = "SELECT 
                COUNT(*) as total_archived,
                AVG(DATEDIFF(confirmed_at, created_at)) as avg_completion_days,
                COUNT(CASE WHEN rejection_reason IS NOT NULL THEN 1 END) as rejected_count
            FROM requests 
            WHERE created_by = :teacher_id 
            AND is_archived = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['teacher_id' => $teacher_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
