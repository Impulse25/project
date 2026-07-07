<?php
// logout.php — Выход из системы
require_once __DIR__ . '/../config/db.php';
require_once 'includes/auth.php';

// В отличие от корневой копии auth.php, logout() здесь только чистит сессию
// и не делает редирект сама — иначе после выхода была бы пустая страница.
logout($pdo);
header('Location: index.php');
exit();
