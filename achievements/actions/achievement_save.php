<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin', 'teacher', 'director');

$user = currentUser();
$pdo  = getPDO();

$tab              = in_array($_POST['tab'] ?? '', ['students','teachers']) ? $_POST['tab'] : 'students';
$eduStudentId     = (int)($_POST['edu_student_id']      ?? 0);
$userId           = (int)($_POST['user_id']             ?? 0);
$coStudentIds     = array_filter(array_map('intval', $_POST['ach_co_student_ids']  ?? []));
$coTeacherIds     = array_filter(array_map('intval', $_POST['ach_co_teacher_ids']  ?? []));
$title            = trim($_POST['title']        ?? '');
$description      = trim($_POST['description']  ?? '');
$category         = trim($_POST['category']     ?? 'other');
$level            = trim($_POST['level']        ?? 'college');
$place            = (isset($_POST['place']) && $_POST['place'] !== '') ? (int)$_POST['place'] : null;
$dateEvent        = (!empty($_POST['date_event'])) ? $_POST['date_event'] : null;

$redirectUrl = SITE_URL . '/achievements.php?tab=achievements';

if (!$title)
    redirectError('Укажите название достижения.', $redirectUrl);

if ($tab === 'students' && !$eduStudentId)
    redirectError('Не выбран студент.', $redirectUrl);

if ($tab === 'teachers' && !$userId)
    $userId = $user['id'];

// Загрузка файла
$filePath = null;
if (!empty($_FILES['pdf_file']['name']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
    $file    = $_FILES['pdf_file'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png'];
    if ($file['size'] <= 10*1024*1024 && in_array($ext, $allowed)) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filePath = 'ach_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filePath))
            $filePath = null;
    } else {
        redirectError('Файл слишком большой или неверный формат.', $redirectUrl);
    }
}

ensureColumn('achievements', 'edu_student_id', 'INT UNSIGNED DEFAULT NULL');

// Функция вставки одного достижения
$insertAch = function($uid, $esid) use ($pdo, $title, $description, $category, $level, $place, $dateEvent, $filePath, $user) {
    $pdo->prepare(
        "INSERT INTO achievements (user_id, edu_student_id, title, description, category, level, place, date_event, file_path, added_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$uid, $esid, $title, $description, $category, $level, $place, $dateEvent, $filePath, $user['id']]);
    if ($uid > 0) recalcRating($uid);
};

if ($tab === 'students') {
    // Основной студент
    $insertAch(0, $eduStudentId);
    // Соавторы-студенты
    foreach ($coStudentIds as $sid) {
        if ($sid !== $eduStudentId) $insertAch(0, $sid);
    }
} else {
    // Основной преподаватель
    $insertAch($userId, null);
    // Соавторы-преподаватели
    foreach ($coTeacherIds as $tid) {
        if ($tid !== $userId) $insertAch($tid, null);
    }
}

redirectSuccess('Достижение успешно добавлено.', $redirectUrl);