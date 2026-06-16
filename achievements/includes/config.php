<?php
/* ============================================================
 *  includes/config.php — конфигурация модуля «Учёт достижений»
 *
 *  • Подключение к БД вынесено в общий файл  <корень сайта>/config/db.php
 *    (как во всех остальных модулях портала).
 *  • Базовый адрес сайта (SITE_URL) больше не «зашит» жёстко,
 *    а вычисляется автоматически по окружению — поэтому сайт
 *    одинаково работает и на локальной машине, и на хостинге,
 *    в любой папке.
 * ============================================================ */

/* ---------- Общие настройки ---------- */
define('SITE_NAME', 'СВГТК Портал');

/* ---------- Базовый URL модуля (вычисляется автоматически) ----------
 * Раньше было:  define('SITE_URL', 'http://project/achievements');
 * Теперь адрес собирается из протокола + хоста + пути модуля,
 * так что жёсткий путь от локалки больше не мешает.
 */
if (!defined('SITE_URL')) {
    // 1) протокол (http / https), с учётом прокси хостинга
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';

    // 2) хост (домен)
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    // 3) путь модуля относительно корня сайта (напр. "/achievements" или "")
    $basePath   = '';
    $docRoot    = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
    $moduleRoot = realpath(dirname(__DIR__)); // папка модуля (на уровень выше includes/)
    if ($docRoot && $moduleRoot) {
        $docRoot    = rtrim(str_replace('\\', '/', $docRoot), '/');
        $moduleRoot = str_replace('\\', '/', $moduleRoot);
        if (strpos($moduleRoot, $docRoot) === 0) {
            $basePath = substr($moduleRoot, strlen($docRoot));
        }
    }
    $basePath = '/' . trim($basePath, '/');
    if ($basePath === '/') {
        $basePath = '';
    }

    define('SITE_URL', $scheme . '://' . $host . $basePath);
}

/* ---------- Подключение к базе данных ----------
 * Соединение берётся из общего файла config/db.php, который лежит
 * на уровень выше корня модуля:
 *
 *     <корень сайта>/config/db.php      ← общий файл подключения
 *     <корень сайта>/achievements/...   ← этот модуль
 *
 * Файл db.php сам создаёт PDO-подключение (переменная $pdo).
 * Функция getPDO() остаётся единой точкой доступа к БД — её
 * используют все 46 файлов модуля, поэтому больше ничего менять
 * не нужно.
 */
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbFile = __DIR__ . '/../../config/db.php';   // <корень сайта>/config/db.php
    if (!is_file($dbFile)) {
        throw new RuntimeException('Файл подключения к БД не найден: ' . $dbFile);
    }
    require $dbFile;

    // Определяем готовое PDO-подключение, созданное внутри config/db.php.
    $conn = null;
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;                              // самый частый вариант — $pdo
    } else {
        foreach (['db', 'conn', 'connection', 'dbh'] as $name) {
            if (isset($$name) && $$name instanceof PDO) {
                $conn = $$name;
                break;
            }
        }
    }

    // Запасной вариант: config/db.php задаёт только параметры (DSN / логин / пароль).
    if (!$conn instanceof PDO) {
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (defined('DB_DSN')) {
            $conn = new PDO(
                DB_DSN,
                defined('DB_USER') ? DB_USER : '',
                defined('DB_PASS') ? DB_PASS : '',
                $opts
            );
        } elseif (defined('DB_HOST') && defined('DB_NAME')) {
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            $conn = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . $charset,
                defined('DB_USER') ? DB_USER : '',
                defined('DB_PASS') ? DB_PASS : '',
                $opts
            );
        }
    }

    if (!$conn instanceof PDO) {
        throw new RuntimeException(
            'config/db.php не предоставил PDO-подключение ($pdo) и параметры БД.'
        );
    }

    // Гарантируем единое поведение модуля независимо от настроек db.php.
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo = $conn;
    return $pdo;
}

/* ---------- Ключи внешних сервисов (распознавание документов) ---------- */

// Google Gemini — основной, 1500 запросов/день
// Ключ: https://aistudio.google.com/apikey
define('GEMINI_API_KEY', 'AQ.Ab8RN6J-ZVACrbh75WsQU9jdGVJ6ffn_u3JzwEOHNzKCXvcg3A');

// OpenRouter — запасной, включается автоматически при лимите Gemini
// Ключ: https://openrouter.ai/settings/keys
define('OPENROUTER_API_KEY', '');  // ← вставь свой ключ

// Yandex Cloud Vision (старый, не используется)
define('YANDEX_API_KEY',   'AQVNxFthIU7cPw4ZI3svjaMPiyEgQYZi2FHuC2rs');
define('YANDEX_FOLDER_ID', 'b1gks0clrt4gms94r4nk');
