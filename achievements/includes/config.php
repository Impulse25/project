<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'p-355792_svgtk');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('SITE_NAME',  'СВГТК Портал');
define('SITE_URL',   'http://project/achievements');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
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
define('YANDEX_API_KEY',   'AQVNxFthIU7cPw4ZI3svjaMPiyEgQYZi2FHuC2rs');
define('YANDEX_FOLDER_ID', 'b1gks0clrt4gms94r4nk');