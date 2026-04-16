<?php
// bot/commands/my.php - Команда /my (мои заявки)

require_once __DIR__ . '/../helpers/telegram.php';
require_once __DIR__ . '/../helpers/database.php';

function handleMyCommand($chatId, $telegramId) {
    global $pdo;
    
    // Проверяем авторизацию
    $user = getUserByTelegramId($pdo, $telegramId);
    
    if (!$user) {
        sendMessage($chatId, "❌ Вы не авторизованы!\n\nИспользуйте /start для привязки аккаунта.");
        return;
    }
    
    // Получаем заявки техника
    $requests = getTechnicianRequests($pdo, $user['id']);
    
    if (empty($requests)) {
        sendMessage($chatId, "📭 У вас нет заявок в работе.\n\nИспользуйте /new чтобы посмотреть новые заявки.");
        return;
    }
    
    $text = "⚙️ <b>Мои заявки (" . count($requests) . ")</b>\n\n";
    $text .= "Заявки, которые вы взяли в работу:\n\n";
    
    sendMessage($chatId, $text);
    
    // Отправляем каждую заявку отдельным сообщением с кнопками
    foreach ($requests as $request) {
        $requestText = formatRequest($request);
        $buttons = getRequestButtons($request['id'], $request['status']);
        $keyboard = createInlineKeyboard($buttons);
        
        sendMessage($chatId, $requestText, $keyboard);
    }
}
?>
