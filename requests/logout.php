<?php
// logout.php — Выход из системы
// После выхода — редирект на главную страницу портала

require_once 'config/db.php';
require_once 'includes/auth.php';

logout($pdo);

// Редирект на главную страницу портала
header('Location: /');
exit();