<?php
// bot/commands/all.php - Команда /all (все активные заявки)

require_once __DIR__ . '/../helpers/telegram.php';
require_once __DIR__ . '/../helpers/database.php';

function handleAllCommand($chatId, $telegramId) {
    global $pdo;
    
    // Проверяем авторизацию
    $user = getUserByTelegramId($pdo, $telegramId);
    
    if (!$user) {
        sendMessage($chatId, "❌ Вы не авторизованы!\n\nИспользуйте /start для привязки аккаунта.");
        return;
    }
    
    // Получаем все активные заявки
    $requests = getAllActiveRequests($pdo);
    
    if (empty($requests)) {
        sendMessage($chatId, "✅ Нет активных заявок!\n\nВсе заявки завершены.");
        return;
    }
    
    $text = "📋 <b>Все активные заявки (" . count($requests) . ")</b>\n\n";
    $text .= "Заявки в статусе: Одобрена, В работе\n\n";
    
    sendMessage($chatId, $text);
    
    // Отправляем каждую заявку отдельным сообщением
    foreach ($requests as $request) {
        $requestText = formatRequest($request);
        
        // Показываем кнопки только если заявка свободна или это заявка текущего техника
        if ($request['status'] === 'approved' || $request['technician_id'] == $user['id']) {
            $buttons = getRequestButtons($request['id'], $request['status']);
            $keyboard = createInlineKeyboard($buttons);
            sendMessage($chatId, $requestText, $keyboard);
        } else {
            sendMessage($chatId, $requestText);
        }
    }
}
?>
