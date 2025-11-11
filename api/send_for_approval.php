<?php
// api/send_for_approval.php - Отправить заявку на согласование

require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Проверка авторизации
if (!isLoggedIn() || !hasRole('technician')) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

// Получение данных
$input = json_decode(file_get_contents('php://input'), true);
$requestId = $input['request_id'] ?? null;
$comment = trim($input['comment'] ?? '');

if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'ID заявки не указан']);
    exit;
}

$currentUser = getCurrentUser();
$technicianId = $currentUser['id'];

try {
    // Начинаем транзакцию
    $pdo->beginTransaction();
    
    // Проверяем что заявка принадлежит этому технику и в работе
    $stmt = $pdo->prepare("
        SELECT * FROM requests 
        WHERE id = ? AND status = 'in_progress' AND assigned_to = ?
    ");
    $stmt->execute([$requestId, $technicianId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        throw new Exception('Заявка не найдена или не принадлежит вам');
    }
    
    // Обновляем статус заявки
    $stmt = $pdo->prepare("
        UPDATE requests 
        SET status = 'awaiting_approval',
            approval_requested_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$requestId]);
    
    // Записываем лог действия
    $logComment = 'Отправлена на согласование' . ($comment ? ': ' . $comment : '');
    $stmt = $pdo->prepare("
        INSERT INTO request_logs 
        (request_id, user_id, action, old_status, new_status, comment, created_at)
        VALUES (?, ?, 'sent_for_approval', 'in_progress', 'awaiting_approval', ?, NOW())
    ");
    $stmt->execute([$requestId, $technicianId, $logComment]);
    
    // Подтверждаем транзакцию
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Заявка отправлена на согласование'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
