<?php
// bot/polling.php - Long Polling для локальной разработки
// Запускайте через: php polling.php

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

echo "🤖 Бот запущен в режиме Long Polling\n";
echo "Bot: " . BOT_USERNAME . "\n";
echo "Нажмите Ctrl+C для остановки\n\n";

// Удаляем webhook если установлен
telegramRequest('deleteWebhook');

$offset = 0;

while (true) {
    try {
        // Получаем обновления
        $response = telegramRequest('getUpdates', [
            'offset' => $offset,
            'timeout' => 30
        ]);
        
        if (!$response || !$response['ok']) {
            echo "❌ Ошибка получения обновлений\n";
            sleep(5);
            continue;
        }
        
        $updates = $response['result'];
        
        foreach ($updates as $update) {
            $offset = $update['update_id'] + 1;
            
            echo "[" . date('H:i:s') . "] Получено обновление #" . $update['update_id'] . "\n";
            
            // Обработка обычных сообщений
            if (isset($update['message'])) {
                $message = $update['message'];
                $chatId = $message['chat']['id'];
                $text = $message['text'] ?? '';
                $telegramUsername = $message['from']['username'] ?? 'unknown';
                $telegramId = $message['from']['id'];
                
                echo "  📨 Сообщение от @$telegramUsername: $text\n";
                
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
                    sendMessage($chatId, "❓ Неизвестная команда.\n\nИспользуйте /help для просмотра доступных команд.");
                }
            }
            
            // Обработка callback (кнопки)
            if (isset($update['callback_query'])) {
                $callbackQuery = $update['callback_query'];
                $chatId = $callbackQuery['message']['chat']['id'];
                $messageId = $callbackQuery['message']['message_id'];
                $callbackData = $callbackQuery['data'];
                $telegramId = $callbackQuery['from']['id'];
                $telegramUsername = $callbackQuery['from']['username'] ?? 'unknown';
                
                echo "  🔘 Кнопка от @$telegramUsername: $callbackData\n";
                
                // Проверяем авторизацию
                $user = getUserByTelegramId($pdo, $telegramId);
                
                if (!$user) {
                    answerCallbackQuery($callbackQuery['id'], "❌ Вы не авторизованы!", true);
                    continue;
                }
                
                // Парсим callback data
                list($action, $requestId) = explode('_', $callbackData);
                
                if ($action === 'take') {
                    $result = takeRequest($pdo, $requestId, $user['id']);
                    
                    if ($result['success']) {
                        $request = getRequestById($pdo, $requestId);
                        $text = formatRequest($request);
                        $buttons = getRequestButtons($request['id'], 'in_progress');
                        $keyboard = createInlineKeyboard($buttons);
                        
                        editMessage($chatId, $messageId, $text, $keyboard);
                        answerCallbackQuery($callbackQuery['id'], "✅ Заявка взята в работу!");
                        
                        echo "    ✅ Заявка #$requestId взята в работу\n";
                    } else {
                        answerCallbackQuery($callbackQuery['id'], "❌ " . $result['message'], true);
                    }
                } elseif ($action === 'done') {
                    $result = completeRequest($pdo, $requestId, $user['id']);
                    
                    if ($result['success']) {
                        $request = getRequestById($pdo, $requestId);
                        $text = formatRequest($request);
                        $text .= "\n\n✅ <b>Заявка завершена!</b>";
                        
                        editMessage($chatId, $messageId, $text);
                        answerCallbackQuery($callbackQuery['id'], "✅ Заявка завершена!");
                        
                        echo "    ✅ Заявка #$requestId завершена\n";
                    } else {
                        answerCallbackQuery($callbackQuery['id'], "❌ " . $result['message'], true);
                    }
                } elseif ($action === 'refresh') {
                    $request = getRequestById($pdo, $requestId);
                    
                    if ($request) {
                        $text = formatRequest($request);
                        
                        if ($request['status'] === 'approved' || $request['technician_id'] == $user['id']) {
                            $buttons = getRequestButtons($request['id'], $request['status']);
                            $keyboard = createInlineKeyboard($buttons);
                            editMessage($chatId, $messageId, $text, $keyboard);
                        } else {
                            editMessage($chatId, $messageId, $text);
                        }
                        
                        answerCallbackQuery($callbackQuery['id'], "🔄 Обновлено");
                        echo "    🔄 Заявка #$requestId обновлена\n";
                    } else {
                        answerCallbackQuery($callbackQuery['id'], "❌ Заявка не найдена", true);
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Ошибка: " . $e->getMessage() . "\n";
        sleep(5);
    }
}
?>
