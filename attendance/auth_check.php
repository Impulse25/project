<?php
/**
 * auth_check.php — общая проверка авторизации
 * Подключается в начале каждого API-файла.
 *
 * Ожидает, что session_start() уже был вызван до подключения этого файла.
 */

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}
