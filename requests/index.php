<?php
// index.php — старая отдельная страница входа. Оставлена только как редирект
// на настоящую точку входа — корневой index.php (главная + модальная форма входа),
// чтобы все существующие ссылки вида requests/index.php (редиректы из
// requireLogin()/requireRole() и т.п.) продолжали работать.

$redirect = $_GET['redirect'] ?? '';
if (preg_match('~^([a-z][a-z0-9+.\-]*:)?//~i', $redirect)) {
    $redirect = '';
}

$target = '../index.php';
if ($redirect !== '') {
    $target .= '?redirect=' . urlencode($redirect);
}

header('Location: ' . $target);
exit();
