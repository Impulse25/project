<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin','teacher');

$groupId  = (int)$_POST['group_id'];
$fullName = trim($_POST['full_name']);
$email    = trim($_POST['email']);
$password = $_POST['password'];
$phone    = trim($_POST['phone'] ?? '');
$studentNum = trim($_POST['student_num'] ?? '');

$pdo = getPDO();
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
$pdo->prepare("INSERT INTO users (full_name,email,password,role) VALUES(?,?,?,'student')")->execute([$fullName,$email,$hash]);
$userId = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO students (user_id,group_id,student_num,phone) VALUES(?,?,?,?)")->execute([$userId,$groupId,$studentNum,$phone]);

$_SESSION['flash'] = "Студент $fullName добавлен.";
header('Location: ' . SITE_URL . '/group_detail.php?id=' . $groupId);