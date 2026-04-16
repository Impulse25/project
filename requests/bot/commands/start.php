<?php
// bot/commands/start.php - Команда /start (привязка аккаунта)

require_once __DIR__ . '/../helpers/telegram.php';
require_once __DIR__ . '/../helpers/database.php';

function handleStartCommand($chatId, $telegramUsername, $telegramId, $messageText) {
    global $pdo;
    
    // Проверяем уже привязан ли аккаунт
    $user = getUserByTelegramId($pdo, $telegramId);
    
    if ($user) {
        $text = "✅ <b>Вы уже авторизованы!</b>\n\n";
        $text .= "👤 <b>Пользователь:</b> {$user['full_name']}\n";
        $text .= "🔧 <b>Роль:</b> Системный техник\n\n";
        $text .= "Используйте команды:\n";
        $text .= "/new - Новые заявки\n";
        $text .= "/my - Мои заявки\n";
        $text .= "/all - Все активные заявки\n";
        $text .= "/stats - Моя статистика\n";
        $text .= "/help - Помощь";
        
        sendMessage($chatId, $text);
        return;
    }
    
    // Проверяем есть ли username после команды (для привязки)
    $parts = explode(' ', $messageText);
    
    if (count($parts) < 2) {
        // Показываем инструкцию
        $text = "👋 <b>Добро пожаловать в бот СВГТК!</b>\n\n";
        $text .= "Для работы нужно привязать ваш аккаунт.\n\n";
        $text .= "📝 <b>Инструкция:</b>\n";
        $text .= "1. Узнайте ваш логин из системы заявок\n";
        $text .= "2. Отправьте команду:\n";
        $text .= "<code>/start ВАШ_ЛОГИН</code>\n\n";
        $text .= "Например:\n";
        $text .= "<code>/start ivanov</code>\n\n";
        $text .= "⚠️ Работает только для системных техников!";
        
        sendMessage($chatId, $text);
        return;
    }
    
    // Пытаемся привязать аккаунт
    $username = trim($parts[1]);
    
    $result = linkTelegramAccount($pdo, $username, $telegramId, $telegramUsername);
    
    if ($result) {
        $text = "✅ <b>Аккаунт успешно привязан!</b>\n\n";
        $text .= "👤 <b>Логин:</b> $username\n";
        $text .= "📱 <b>Telegram:</b> @$telegramUsername\n\n";
        $text .= "<b>Доступные команды:</b>\n";
        $text .= "/new - Новые заявки (не взятые в работу)\n";
        $text .= "/my - Мои заявки (в работе)\n";
        $text .= "/all - Все активные заявки\n";
        $text .= "/stats - Моя статистика\n";
        $text .= "/help - Помощь\n\n";
        $text .= "🔔 Уведомления о новых заявках включены!";
        
        sendMessage($chatId, $text);
        
        botLog("Account linked successfully", [
            'username' => $username,
            'telegram_id' => $telegramId,
            'telegram_username' => $telegramUsername
        ]);
    } else {
        $text = "❌ <b>Ошибка привязки аккаунта!</b>\n\n";
        $text .= "Возможные причины:\n";
        $text .= "• Неверный логин\n";
        $text .= "• Вы не системный техник\n";
        $text .= "• Аккаунт уже привязан к другому Telegram\n\n";
        $text .= "Проверьте логин и попробуйте снова:\n";
        $text .= "<code>/start ВАШ_ЛОГИН</code>";
        
        sendMessage($chatId, $text);
        
        botLog("Account link failed", [
            'username' => $username,
            'telegram_id' => $telegramId
        ]);
    }
}
?>
