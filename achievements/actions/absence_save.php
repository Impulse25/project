<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','teacher');

getPDO()->prepare("INSERT INTO absences (student_id,absent_date,subject,reason,hours,teacher_id) VALUES(?,?,?,?,?,?)")
    ->execute([(int)$_POST['student_id'], $_POST['absent_date'], $_POST['subject'] ?? '', $_POST['reason'], (int)$_POST['hours'], $user['id']]);

header('Location: ' . SITE_URL . '/profile.php?id=' . (int)$_POST['user_id']);