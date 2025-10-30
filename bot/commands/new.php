<?php
// bot/commands/new.php - Команда /new (новые заявки)

require_once __DIR__ . '/../helpers/telegram.php';
require_once __DIR__ . '/../helpers/database.php';

function handleNewCommand($chatId, $telegramId) {
    global $pdo;
    
    // DEBUG
    sendMessage($chatId, "🔍 DEBUG: Начинаю проверку...");
    
    // Проверяем авторизацию
    $user = getUserByTelegramId($pdo, $telegramId);
    
    if (!$user) {
        sendMessage($chatId, "❌ Вы не авторизованы!\n\nИспользуйте /start для привязки аккаунта.");
        return;
    }
    
    sendMessage($chatId, "🔍 DEBUG: Пользователь найден: " . $user['username']);
    
    // Получаем новые заявки
    $requests = getNewRequests($pdo);
    
    sendMessage($chatId, "🔍 DEBUG: Найдено заявок: " . count($requests));
    
    if (empty($requests)) {
        sendMessage($chatId, "✅ Нет новых заявок!\n\nВсе заявки либо взяты в работу, либо завершены.");
        return;
    }
    
    $text = "🆕 <b>Новые заявки (" . count($requests) . ")</b>\n\n";
    $text .= "Заявки одобрены и ожидают системотехника:\n\n";
    
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