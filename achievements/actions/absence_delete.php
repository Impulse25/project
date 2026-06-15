<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','teacher');

getPDO()->prepare("DELETE FROM absences WHERE id=?")->execute([(int)$_GET['id']]);
header('Location: ' . SITE_URL . '/profile.php?id=' . (int)$_GET['user_id']);