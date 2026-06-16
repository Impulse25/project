<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$user        = currentUser();
$pdo         = getPDO();
$redirectUrl = SITE_URL . '/achievements.php?tab=certs';

$certType        = $_POST['cert_type']       ?? 'student';
$eduStudentId    = (int)($_POST['edu_student_id']       ?? 0);
$userId          = (int)($_POST['user_id']              ?? 0);
$coOwnerIds      = array_filter(array_map('intval', $_POST['co_owner_ids']         ?? []));
$coStudentIds    = array_filter(array_map('intval', $_POST['cert_co_student_ids']  ?? []));
$title           = trim($_POST['title']       ?? '');
$issuer          = trim($_POST['issuer']      ?? '');
$issueDate       = !empty($_POST['issue_date'])  ? $_POST['issue_date']  : null;
$expiryDate      = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
$place           = trim($_POST['place']       ?? '') ?: null;

if (!$title)
    redirectError('Укажите название сертификата.', $redirectUrl);

$filePath = uploadFile('cert', $redirectUrl);

ensureColumn('certificates', 'edu_student_id', 'INT UNSIGNED DEFAULT NULL');
ensureColumn('certificates', 'place',          'VARCHAR(100) DEFAULT NULL');

// Создаём таблицу cert_owners если нет
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cert_owners` (
        `id` int NOT NULL AUTO_INCREMENT,
        `cert_id` int NOT NULL,
        `user_id` int DEFAULT NULL,
        `edu_student_id` int UNSIGNED DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `cert_id` (`cert_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Функция вставки одного сертификата
$insertCert = function($uid, $esid) use ($pdo, $title, $issuer, $issueDate, $expiryDate, $place, $filePath, $user) {
    $pdo->prepare(
        "INSERT INTO certificates (user_id, edu_student_id, title, issuer, issue_date, expiry_date, place, file_path, added_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$uid, $esid, $title, $issuer, $issueDate, $expiryDate, $place, $filePath, $user['id']]);
    $certId = (int)$pdo->lastInsertId();
    if ($uid > 0) recalcRating($uid);
    return $certId;
};

if ($certType === 'student') {
    // Основной студент
    if (!$eduStudentId) redirectError('Не выбран студент.', $redirectUrl);
    $insertCert(0, $eduStudentId);
    // Соавторы-студенты
    foreach ($coStudentIds as $sid) {
        if ($sid !== $eduStudentId) $insertCert(0, $sid);
    }
} else {
    // Основной преподаватель
    $finalUserId = $userId ?: $user['id'];
    $insertCert($finalUserId, null);
    // Соавторы-преподаватели
    foreach ($coOwnerIds as $coId) {
        if ($coId !== $finalUserId) $insertCert($coId, null);
    }
}

redirectSuccess('Сертификат добавлен.', $redirectUrl);