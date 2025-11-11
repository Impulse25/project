<?php
// api/take_request.php - Взять заявку в работу

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

if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'ID заявки не указан']);
    exit;
}

$currentUser = getCurrentUser();
$technicianId = $currentUser['id'];

try {
    // Начинаем транзакцию
    $pdo->beginTransaction();
    
    // Проверяем что заявка существует и не занята
    $stmt = $pdo->prepare("
        SELECT * FROM requests 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        throw new Exception('Заявка не найдена или уже взята в работу');
    }
    
    // Обновляем статус заявки
    $stmt = $pdo->prepare("
        UPDATE requests 
        SET status = 'in_progress',
            assigned_to = ?,
            assigned_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$technicianId, $requestId]);
    
    // Записываем лог действия
    $stmt = $pdo->prepare("
        INSERT INTO request_logs 
        (request_id, user_id, action, old_status, new_status, comment, created_at)
        VALUES (?, ?, 'assigned', 'pending', 'in_progress', 'Взял заявку в работу', NOW())
    ");
    $stmt->execute([$requestId, $technicianId]);
    
    // Подтверждаем транзакцию
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Заявка взята в работу'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
