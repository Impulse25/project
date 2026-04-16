<?php
// logout.php — Выход из системы
require_once 'config/db.php';
require_once 'includes/auth.php';

logout($pdo);

// Редирект на главную с параметром для открытия формы входа
header('Location: /?login=1');
exit();