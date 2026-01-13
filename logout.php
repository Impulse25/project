<?php
// logout.php - Выход из системы с логированием

require_once 'config/db.php';
require_once 'includes/auth.php';

// Вызов logout с передачей $pdo для логирования
logout($pdo);
