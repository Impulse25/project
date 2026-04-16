<?php
// bot/config.php - Конфигурация Telegram бота

// Telegram Bot API
define('BOT_TOKEN', '8301492026:AAHZX_gZtOQvm6xHfuvMGn7uBPj5s2_d1aw');
define('BOT_USERNAME', '@svgtk_zayavki_bot');
define('TELEGRAM_API', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Подключение к БД (используем родительский config)
require_once __DIR__ . '/../config/db.php';

// Админ для тестирования (ваш Telegram ID)
define('ADMIN_TELEGRAM_ID', 7260883641);

// Настройки уведомлений
define('NOTIFY_ON_NEW_REQUEST', true);      // Уведомлять о новых заявках
define('NOTIFY_ON_STATUS_CHANGE', true);    // Уведомлять при смене статуса
define('NOTIFY_ON_COMMENT', true);          // Уведомлять о новых комментариях

// Часовой пояс
date_default_timezone_set('Asia/Almaty');

// Логирование (для отладки)
define('BOT_LOG_FILE', __DIR__ . '/bot.log');
define('BOT_DEBUG', true); // Включить подробные логи

/**
 * Логирование действий бота
 */
function botLog($message, $data = null) {
    if (!BOT_DEBUG) return;
    
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    
    if ($data) {
        $logMessage .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    $logMessage .= PHP_EOL;
    
    file_put_contents(BOT_LOG_FILE, $logMessage, FILE_APPEND);
}

/**
 * Получить URL для webhook
 */
function getWebhookUrl() {
    // Измените на ваш домен когда будет продакшн
    // Например: https://yourdomain.com/bot/webhook.php
    return 'https://example.com/bot/webhook.php';
}
?>
