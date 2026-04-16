<?php
// bot/webhook.php - Главный обработчик сообщений от Telegram

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/telegram.php';
require_once __DIR__ . '/helpers/database.php';

// Подключаем команды
require_once __DIR__ . '/commands/start.php';
require_once __DIR__ . '/commands/new.php';
require_once __DIR__ . '/commands/my.php';
require_once __DIR__ . '/commands/all.php';
require_once __DIR__ . '/commands/stats.php';
require_once __DIR__ . '/commands/help.php';

// Получаем данные от Telegram
$content = file_get_contents('php://input');
$update = json_decode($content, true);

botLog("Webhook received", $update);

// Обработка обычных сообщений
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $telegramUsername = $message['from']['username'] ?? 'unknown';
    $telegramId = $message['from']['id'];
    
    botLog("Processing message", [
        'chat_id' => $chatId,
        'text' => $text,
        'from' => $telegramUsername
    ]);
    
    // Роутинг команд
    if (strpos($text, '/start') === 0) {
        handleStartCommand($chatId, $telegramUsername, $telegramId, $text);
    } elseif ($text === '/new') {
        handleNewCommand($chatId, $telegramId);
    } elseif ($text === '/my') {
        handleMyCommand($chatId, $telegramId);
    } elseif ($text === '/all') {
        handleAllCommand($chatId, $telegramId);
    } elseif ($text === '/stats') {
        handleStatsCommand($chatId, $telegramId);
    } elseif ($text === '/help') {
        handleHelpCommand($chatId);
    } else {
        // Неизвестная команда
        sendMessage($chatId, "❓ Неизвестная команда.\n\nИспользуйте /help для просмотра доступных команд.");
    }
}

// Обработка нажатий на кнопки (callback query)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    $callbackData = $callbackQuery['data'];
    $telegramId = $callbackQuery['from']['id'];
    
    botLog("Processing callback", [
        'chat_id' => $chatId,
        'callback_data' => $callbackData
    ]);
    
    // Проверяем авторизацию
    $user = getUserByTelegramId($pdo, $telegramId);
    
    if (!$user) {
        answerCallbackQuery($callbackQuery['id'], "❌ Вы не авторизованы!", true);
        return;
    }
    
    // Парсим callback data
    list($action, $requestId) = explode('_', $callbackData);
    
    if ($action === 'take') {
        // Взять заявку в работу
        $result = takeRequest($pdo, $requestId, $user['id']);
        
        if ($result['success']) {
            // Обновляем сообщение
            $request = getRequestById($pdo, $requestId);
            $text = formatRequest($request);
            $buttons = getRequestButtons($request['id'], 'in_progress');
            $keyboard = createInlineKeyboard($buttons);
            
            editMessage($chatId, $messageId, $text, $keyboard);
            answerCallbackQuery($callbackQuery['id'], "✅ Заявка взята в работу!");
            
            botLog("Request taken via button", [
                'request_id' => $requestId,
                'technician' => $user['username']
            ]);
        } else {
            answerCallbackQuery($callbackQuery['id'], "❌ " . $result['message'], true);
        }
    } elseif ($action === 'done') {
        // Завершить заявку
        $result = completeRequest($pdo, $requestId, $user['id']);
        
        if ($result['success']) {
            // Обновляем сообщение
            $request = getRequestById($pdo, $requestId);
            $text = formatRequest($request);
            $text .= "\n\n✅ <b>Заявка завершена!</b>";
            
            editMessage($chatId, $messageId, $text);
            answerCallbackQuery($callbackQuery['id'], "✅ Заявка завершена!");
            
            botLog("Request completed via button", [
                'request_id' => $requestId,
                'technician' => $user['username']
            ]);
        } else {
            answerCallbackQuery($callbackQuery['id'], "❌ " . $result['message'], true);
        }
    } elseif ($action === 'refresh') {
        // Обновить информацию о заявке
        $request = getRequestById($pdo, $requestId);
        
        if ($request) {
            $text = formatRequest($request);
            
            // Показываем кнопки только если это заявка техника или свободная
            if ($request['status'] === 'approved' || $request['technician_id'] == $user['id']) {
                $buttons = getRequestButtons($request['id'], $request['status']);
                $keyboard = createInlineKeyboard($buttons);
                editMessage($chatId, $messageId, $text, $keyboard);
            } else {
                editMessage($chatId, $messageId, $text);
            }
            
            answerCallbackQuery($callbackQuery['id'], "🔄 Обновлено");
        } else {
            answerCallbackQuery($callbackQuery['id'], "❌ Заявка не найдена", true);
        }
    }
}

http_response_code(200);
?>
