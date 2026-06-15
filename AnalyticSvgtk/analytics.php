<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
header('Location: reports.php');
exit;
?>
