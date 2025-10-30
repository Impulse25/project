<?php
// bot/test_bot.php - Тест работы бота

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/telegram.php';
require_once __DIR__ . '/helpers/database.php';

echo "🧪 Тестирование Telegram бота\n\n";

// Тест 1: Подключение к БД
echo "1️⃣  Тест подключения к БД...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'technician'");
    $count = $stmt->fetch()['count'];
    echo "   ✅ Подключено! Найдено $count системотехников\n\n";
} catch (PDOException $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Тест 2: Проверка полей Telegram
echo "2️⃣  Проверка Telegram полей...\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'telegram%'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required = ['telegram_id', 'telegram_username', 'telegram_notifications'];
    $missing = array_diff($required, $columns);
    
    if (empty($missing)) {
        echo "   ✅ Все поля на месте:\n";
        foreach ($columns as $col) {
            echo "      - $col\n";
        }
        echo "\n";
    } else {
        echo "   ❌ Отсутствуют поля: " . implode(', ', $missing) . "\n";
        echo "   Выполните миграцию: migration_telegram.sql\n\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Тест 3: Проверка Telegram API
echo "3️⃣  Проверка Telegram API...\n";
$response = telegramRequest('getMe');

if ($response && $response['ok']) {
    $bot = $response['result'];
    echo "   ✅ Telegram API работает!\n";
    echo "   Bot ID: {$bot['id']}\n";
    echo "   Bot Name: {$bot['first_name']}\n";
    echo "   Bot Username: @{$bot['username']}\n\n";
} else {
    echo "   ❌ Ошибка подключения к Telegram API\n";
    echo "   Проверьте BOT_TOKEN в config.php\n\n";
    exit(1);
}

// Тест 4: Проверка webhook
echo "4️⃣  Проверка webhook...\n";
$info = telegramRequest('getWebhookInfo');

if ($info && $info['ok']) {
    $webhookInfo = $info['result'];
    
    if (empty($webhookInfo['url'])) {
        echo "   ℹ️  Webhook не установлен (используйте polling.php)\n\n";
    } else {
        echo "   ✅ Webhook установлен: {$webhookInfo['url']}\n";
        echo "   Pending updates: {$webhookInfo['pending_update_count']}\n";
        
        if (isset($webhookInfo['last_error_date'])) {
            echo "   ⚠️  Последняя ошибка: {$webhookInfo['last_error_message']}\n";
        }
        echo "\n";
    }
}

// Тест 5: Проверка привязанных аккаунтов
echo "5️⃣  Проверка привязанных аккаунтов...\n";
try {
    $stmt = $pdo->query("
        SELECT username, full_name, telegram_id, telegram_username, telegram_notifications
        FROM users 
        WHERE role = 'technician' AND telegram_id IS NOT NULL
    ");
    $linked = $stmt->fetchAll();
    
    if (empty($linked)) {
        echo "   ℹ️  Нет привязанных аккаунтов\n";
        echo "   Используйте /start в боте для привязки\n\n";
    } else {
        echo "   ✅ Привязано аккаунтов: " . count($linked) . "\n";
        foreach ($linked as $user) {
            echo "      - {$user['full_name']} (@{$user['telegram_username']}) - уведомления: " . ($user['telegram_notifications'] ? 'вкл' : 'выкл') . "\n";
        }
        echo "\n";
    }
} catch (PDOException $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Тест 6: Проверка заявок
echo "6️⃣  Проверка заявок...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM requests WHERE status = 'approved'");
    $newCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM requests WHERE status = 'in_progress'");
    $inProgressCount = $stmt->fetch()['count'];
    
    echo "   ✅ Заявок в статусе 'approved': $newCount\n";
    echo "   ✅ Заявок в статусе 'in_progress': $inProgressCount\n\n";
} catch (PDOException $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Тест 7: Проверка файлов
echo "7️⃣  Проверка файлов...\n";
$requiredFiles = [
    'config.php',
    'webhook.php',
    'polling.php',
    'commands/start.php',
    'commands/new.php',
    'commands/my.php',
    'commands/all.php',
    'commands/stats.php',
    'commands/help.php',
    'helpers/telegram.php',
    'helpers/database.php',
    'notifications/send.php'
];

$allExist = true;
foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        echo "   ❌ Отсутствует: $file\n";
        $allExist = false;
    }
}

if ($allExist) {
    echo "   ✅ Все файлы на месте\n\n";
}

// Итоги
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "✅ Бот готов к работе!\n\n";
echo "📝 Следующие шаги:\n";
echo "1. Запустите: php polling.php\n";
echo "2. Откройте бота: @svgtk_zayavki_bot\n";
echo "3. Привяжите аккаунт: /start ВАШ_ЛОГИН\n";
echo "4. Проверьте команды: /new, /my, /all\n\n";
echo "📖 Документация: INSTALLATION.md\n";
?>
