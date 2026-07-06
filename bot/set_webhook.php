<?php
// bot/set_webhook.php - Установка webhook для бота

require_once __DIR__ . '/config.php';

// URL вашего webhook (измените на свой!)
// Для локальной разработки используйте ngrok или localtunnel
$webhookUrl = getWebhookUrl();

echo "🔧 Установка webhook для бота...\n\n";
echo "Bot Token: " . BOT_TOKEN . "\n";
echo "Webhook URL: $webhookUrl\n\n";

// Удаляем старый webhook
echo "1. Удаление старого webhook...\n";
$deleteUrl = TELEGRAM_API . 'deleteWebhook';
$ch = curl_init($deleteUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['ok']) {
    echo "   ✅ Старый webhook удалён\n\n";
} else {
    echo "   ❌ Ошибка: " . $result['description'] . "\n\n";
}

// Устанавливаем новый webhook
echo "2. Установка нового webhook...\n";
$setUrl = TELEGRAM_API . 'setWebhook?url=' . urlencode($webhookUrl)
        . '&secret_token=' . urlencode(BOT_WEBHOOK_SECRET);
$ch = curl_init($setUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['ok']) {
    echo "   ✅ Webhook установлен успешно!\n\n";
} else {
    echo "   ❌ Ошибка: " . $result['description'] . "\n\n";
}

// Проверяем webhook
echo "3. Проверка webhook...\n";
$infoUrl = TELEGRAM_API . 'getWebhookInfo';
$ch = curl_init($infoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$info = json_decode($response, true);
if ($info['ok']) {
    echo "   URL: " . ($info['result']['url'] ?? 'не установлен') . "\n";
    echo "   Pending updates: " . ($info['result']['pending_update_count'] ?? 0) . "\n";
    
    if (isset($info['result']['last_error_date'])) {
        echo "   ⚠️  Последняя ошибка: " . $info['result']['last_error_message'] . "\n";
    } else {
        echo "   ✅ Ошибок нет\n";
    }
} else {
    echo "   ❌ Не удалось получить информацию\n";
}

echo "\n✅ Готово!\n\n";
echo "📝 Теперь бот будет получать обновления на: $webhookUrl\n";
echo "🤖 Попробуйте написать боту: " . BOT_USERNAME . "\n";
?>
