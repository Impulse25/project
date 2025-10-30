<?php
// bot/notifications/send.php - Отправка уведомлений техникам

require_once __DIR__ . '/../helpers/telegram.php';
require_once __DIR__ . '/../helpers/database.php';

/**
 * Отправить уведомление о новой заявке всем техникам
 */
function notifyNewRequest($requestId) {
    global $pdo;
    
    if (!NOTIFY_ON_NEW_REQUEST) {
        return;
    }
    
    // Получаем заявку
    $request = getRequestById($pdo, $requestId);
    
    if (!$request) {
        botLog("Request not found for notification", ['request_id' => $requestId]);
        return;
    }
    
    // Получаем всех техников с уведомлениями
    $technicians = getTechniciansForNotification($pdo);
    
    if (empty($technicians)) {
        botLog("No technicians with notifications enabled");
        return;
    }
    
    // Формируем сообщение
    $text = "🆕 <b>Новая заявка!</b>\n\n";
    $text .= formatRequest($request);
    $text .= "\n\n💡 Используйте /new для просмотра всех новых заявок";
    
    // Кнопки
    $buttons = getRequestButtons($request['id'], $request['status']);
    $keyboard = createInlineKeyboard($buttons);
    
    // Отправляем всем техникам
    $sent = 0;
    foreach ($technicians as $tech) {
        $result = sendMessage($tech['telegram_id'], $text, $keyboard);
        if ($result && $result['ok']) {
            $sent++;
        }
    }
    
    botLog("New request notification sent", [
        'request_id' => $requestId,
        'technicians_count' => count($technicians),
        'sent_count' => $sent
    ]);
    
    return $sent;
}

/**
 * Отправить уведомление о смене статуса заявки
 */
function notifyStatusChange($requestId, $oldStatus, $newStatus) {
    global $pdo;
    
    if (!NOTIFY_ON_STATUS_CHANGE) {
        return;
    }
    
    // Получаем заявку
    $request = getRequestById($pdo, $requestId);
    
    if (!$request) {
        return;
    }
    
    $statusNames = [
        'pending' => 'Ожидает',
        'approved' => 'Одобрена',
        'in_progress' => 'В работе',
        'completed' => 'Завершена',
        'rejected' => 'Отклонена'
    ];
    
    $text = "🔔 <b>Изменение статуса заявки #{$request['id']}</b>\n\n";
    $text .= "📊 <b>Было:</b> {$statusNames[$oldStatus]}\n";
    $text .= "📊 <b>Стало:</b> {$statusNames[$newStatus]}\n\n";
    $text .= "👤 <b>От:</b> {$request['full_name']}\n";
    $text .= "📋 <b>Тип:</b> {$request['type']}\n";
    $text .= "🏢 <b>Кабинет:</b> {$request['cabinet']}";
    
    // Если заявка взята в работу или завершена - уведомляем создателя
    // Если одобрена - уведомляем техников
    
    if ($newStatus === 'approved') {
        // Уведомляем всех техников
        $technicians = getTechniciansForNotification($pdo);
        foreach ($technicians as $tech) {
            sendMessage($tech['telegram_id'], $text);
        }
    }
    
    // TODO: Уведомление создателя заявки (если у него есть telegram_id)
    
    botLog("Status change notification sent", [
        'request_id' => $requestId,
        'old_status' => $oldStatus,
        'new_status' => $newStatus
    ]);
}
?>
