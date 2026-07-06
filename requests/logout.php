<?php
// logout.php — Выход из системы
require_once __DIR__ . '/../config/db.php';
require_once 'includes/auth.php';

logout($pdo);
