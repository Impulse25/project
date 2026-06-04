<?php
/**
 * attendance/db.php — подключение к БД для модуля посещаемости.
 *
 * Использует общий config/db.php проекта (одно место с паролями),
 * а не собственные захардкоженные параметры.
 *
 * Путь ../config/db.php корректен: attendance/ находится рядом с config/.
 */

// Подключаем общий конфиг только если PDO-соединение ещё не создано
// (config/db.php создаёт $pdo в глобальной области — переиспользуем его)
if (!isset($GLOBALS['_attendance_db_loaded'])) {
    require_once __DIR__ . '/../config/db.php';
    $GLOBALS['_attendance_db_loaded'] = true;
}

/**
 * Возвращает PDO-соединение (то же, что создал config/db.php).
 * Если файл подключается из контекста где $pdo уже есть — возвращает его.
 */
function getDbConnection(): PDO {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        // Форс-подключение на случай прямого вызова без config/db.php
        require_once __DIR__ . '/../config/db.php';
    }
    return $pdo;
}
