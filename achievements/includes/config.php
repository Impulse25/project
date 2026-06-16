<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'p-355792_svgtk');
define('DB_PASS', '6btcy2iFPUGKV2N');
define('DB_NAME', 'p-355792_svgtk');
define('DB_CHARSET', 'utf8mb4');
define('SITE_NAME',  'СВГТК Портал');
define('SITE_URL',   'http://portal-svgtk.ru/achievements');

function getPDO(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        require_once BASE_PATH . '/../requests/config/db.php';

        // В db.php уже создаётся объект $pdo
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('Не удалось получить подключение к БД');
        }
    }

    return $pdo;
}

// Google Gemini — основной, 1500 запросов/день
// Ключ: https://aistudio.google.com/apikey
define('GEMINI_API_KEY', 'AQ.Ab8RN6J-ZVACrbh75WsQU9jdGVJ6ffn_u3JzwEOHNzKCXvcg3A');

// OpenRouter — запасной, включается автоматически при лимите Gemini
// Ключ: https://openrouter.ai/settings/keys
define('OPENROUTER_API_KEY', '');  // ← вставь свой ключ

// Yandex Cloud Vision (старый, не используется)
define('YANDEX_FOLDER_ID', 'b1gks0clrt4gms94r4nk');
