<?php
// config/db.php — Подключение к базе данных
// ═══════════════════════════════════════════════════════════════
//  ШАГ 1: ПОМЕНЯЙ ОДНУ СТРОКУ ПЕРЕД ЗАЛИВКОЙ
//
//  'local'   → OpenServer дома или в классе
//  'hosting' → portal-svgtk.ru (hoster.kz)
//  'college' → Сервер колледжа (боевой, с LDAP)
// ═══════════════════════════════════════════════════════════════
define('APP_ENV', 'hosting');

switch (APP_ENV) {

    case 'college':
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_NAME', 'svgtk_requests');
        define('LDAP_ACTIVE', true);
        break;

    case 'hosting':
        define('DB_HOST', 'localhost');
        define('DB_USER', 'p-355792_svgtk');
        define('DB_PASS', '6btcy2iFPUGKV2N');
        define('DB_NAME', 'p-355792_svgtk');
        define('LDAP_ACTIVE', false);
        break;

    default: // local
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_NAME', 'svgtk_portal');
        define('LDAP_ACTIVE', false);
        break;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '+05:00'");
} catch (PDOException $e) {
    if (APP_ENV === 'college') {
        error_log("DB error: " . $e->getMessage());
        die("Ошибка подключения к БД. Обратитесь к администратору.");
    } else {
        die("Ошибка подключения к БД: " . $e->getMessage());
    }
}