<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','teacher');

getPDO()->prepare("INSERT INTO grades (student_id,subject,grade,period,teacher_id) VALUES(?,?,?,?,?)")
    ->execute([(int)$_POST['student_id'], $_POST['subject'], (int)$_POST['grade'], $_POST['period'] ?? '', $user['id']]);

header('Location: ' . SITE_URL . '/profile.php?id=' . (int)$_POST['user_id']);