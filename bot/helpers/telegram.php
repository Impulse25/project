<?php
// bot/helpers/telegram.php - Работа с Telegram API

require_once __DIR__ . '/../config.php';

/**
 * Отправка запроса к Telegram API
 */
function telegramRequest($method, $data = []) {
    $url = TELEGRAM_API . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        botLog("Telegram API error: $error", ['method' => $method, 'data' => $data]);
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (!$result['ok']) {
        botLog("Telegram API failed", ['method' => $method, 'response' => $result]);
    }
    
    return $result;
}

/**
 * Отправка сообщения
 */
function sendMessage($chatId, $text, $replyMarkup = null, $parseMode = 'HTML') {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    return telegramRequest('sendMessage', $data);
}

/**
 * Редактирование сообщения
 */
function editMessage($chatId, $messageId, $text, $replyMarkup = null, $parseMode = 'HTML') {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => $parseMode
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    return telegramRequest('editMessageText', $data);
}

/**
 * Ответ на callback query (кнопка нажата)
 */
function answerCallbackQuery($callbackQueryId, $text = '', $showAlert = false) {
    return telegramRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => $showAlert
    ]);
}

/**
 * Создание inline кнопок
 */
function createInlineKeyboard($buttons) {
    return [
        'inline_keyboard' => $buttons
    ];
}

/**
 * Создание обычных кнопок (внизу экрана)
 */
function createReplyKeyboard($buttons, $resize = true, $oneTime = false) {
    return [
        'keyboard' => $buttons,
        'resize_keyboard' => $resize,
        'one_time_keyboard' => $oneTime
    ];
}

/**
 * Удаление клавиатуры
 */
function removeKeyboard() {
    return ['remove_keyboard' => true];
}

/**
 * Экранирование HTML для Telegram
 */
function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Форматирование заявки для отображения
 */
function formatRequest($request) {
    $urgencyEmoji = [
        'low' => '🟢',
        'medium' => '🟡',
        'high' => '🔴'
    ];
    
    $statusEmoji = [
        'new' => '🆕',
        'pending' => '⏳',
        'approved' => '✅',
        'in_progress' => '⚙️',
        'completed' => '✔️',
        'rejected' => '❌'
    ];
    
    $urgency = $urgencyEmoji[$request['urgency']] ?? '⚪';
    $status = $statusEmoji[$request['status']] ?? '❓';
    
    $urgencyText = [
        'low' => 'Низкая',
        'medium' => 'Средняя',
        'high' => 'Высокая'
    ];
    
    $statusText = [
        'new' => 'Новая',
        'pending' => 'Ожидает',
        'approved' => 'Одобрена',
        'in_progress' => 'В работе',
        'completed' => 'Завершена',
        'rejected' => 'Отклонена'
    ];
    
    $text = "<b>$status Заявка #{$request['id']}</b>\n\n";
    $text .= "👤 <b>От:</b> " . escapeHtml($request['full_name']) . "\n";
    $text .= "🏢 <b>Кабинет:</b> " . escapeHtml($request['cabinet']) . "\n";
    $text .= "📋 <b>Тип:</b> " . escapeHtml($request['type']) . "\n";
    $text .= "$urgency <b>Срочность:</b> " . ($urgencyText[$request['urgency']] ?? 'Неизвестно') . "\n";
    $text .= "📊 <b>Статус:</b> " . ($statusText[$request['status']] ?? 'Неизвестно') . "\n\n";
    $text .= "📝 <b>Описание:</b>\n" . escapeHtml($request['description']) . "\n\n";
    $text .= "📅 <b>Создана:</b> " . date('d.m.Y H:i', strtotime($request['created_at']));
    
    if ($request['technician_name']) {
        $text .= "\n🔧 <b>Техник:</b> " . escapeHtml($request['technician_name']);
    }
    
    return $text;
}

/**
 * Получить кнопки для заявки
 */
function getRequestButtons($requestId, $status) {
    $buttons = [];
    
    if ($status === 'new' || $status === 'approved') {
        $buttons[] = [
            ['text' => '✅ Взять в работу', 'callback_data' => "take_$requestId"]
        ];
    }
    
    if ($status === 'in_progress') {
        $buttons[] = [
            ['text' => '✔️ Завершить', 'callback_data' => "done_$requestId"]
        ];
    }
    
    $buttons[] = [
        ['text' => '🔄 Обновить', 'callback_data' => "refresh_$requestId"]
    ];
    
    return $buttons;
}
?>