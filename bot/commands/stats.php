<?php
// bot/commands/stats.php - Команда /stats (статистика)

require_once __DIR__ . '/../helpers/telegram.php';
require_once __DIR__ . '/../helpers/database.php';

function handleStatsCommand($chatId, $telegramId) {
    global $pdo;
    
    // Проверяем авторизацию
    $user = getUserByTelegramId($pdo, $telegramId);
    
    if (!$user) {
        sendMessage($chatId, "❌ Вы не авторизованы!\n\nИспользуйте /start для привязки аккаунта.");
        return;
    }
    
    // Получаем статистику
    $stats = getTechnicianStats($pdo, $user['id']);
    
    if (!$stats) {
        sendMessage($chatId, "❌ Ошибка получения статистики.");
        return;
    }
    
    $text = "📊 <b>Ваша статистика</b>\n\n";
    $text .= "👤 <b>Техник:</b> {$user['full_name']}\n\n";
    $text .= "⚙️ <b>В работе:</b> {$stats['in_progress']} заявок\n";
    $text .= "✅ <b>Выполнено сегодня:</b> {$stats['completed_today']}\n";
    $text .= "📅 <b>Выполнено за неделю:</b> {$stats['completed_week']}\n";
    $text .= "🏆 <b>Всего выполнено:</b> {$stats['completed_total']}\n\n";
    
    if ($stats['in_progress'] > 0) {
        $text .= "💡 У вас есть заявки в работе. Используйте /my чтобы посмотреть.";
    } else {
        $text .= "✨ Отличная работа! Все заявки завершены.";
    }
    
    sendMessage($chatId, $text);
}
?>
