<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$user = currentUser();
$pdo  = getPDO();
$redirectUrl = SITE_URL . '/achievements.php?tab=certs';

$ptype         = $_POST['ptype']            ?? 'student';
$eduStudentId  = (int)($_POST['edu_student_id'] ?? 0);
$pdfUserId     = (int)($_POST['pdf_user_id']    ?? 0);
$filename      = basename($_POST['filename']    ?? '');
$title         = trim($_POST['title']           ?? '');
$recipientName = trim($_POST['recipient_name']  ?? '') ?: null;
$position      = trim($_POST['position']        ?? '') ?: null;
$recipientOrg  = trim($_POST['recipient_org']   ?? '') ?: null;
$curatorName   = trim($_POST['curator_name']    ?? '') ?: null;
$issuer        = trim($_POST['issuer']          ?? '');
$eventName     = trim($_POST['event_name']      ?? '') ?: null;
$issueDate     = !empty($_POST['issue_date'])   ? $_POST['issue_date']  : null;
$level         = trim($_POST['level']           ?? '') ?: null;
$place         = trim($_POST['place']           ?? '') ?: null;
$nomination    = trim($_POST['nomination']      ?? '') ?: null;
$docNumber     = trim($_POST['doc_number']      ?? '') ?: null;
$docLang       = trim($_POST['doc_lang']        ?? '') ?: null;
$notes         = trim($_POST['notes']           ?? '') ?: null;

if (!$title || !$filename) {
    redirectError('Ошибка: не все данные заполнены.', $redirectUrl);
}

if (!file_exists(dirname(__DIR__) . '/uploads/' . $filename)) {
    redirectError('Файл не найден на сервере.', $redirectUrl);
}

ensureColumn('certificates', 'edu_student_id', 'INT UNSIGNED DEFAULT NULL');
ensureColumn('certificates', 'place',          'VARCHAR(100) DEFAULT NULL');
ensureColumn('certificates', 'recipient_name', 'VARCHAR(255) DEFAULT NULL');
ensureColumn('certificates', 'curator_name',   'VARCHAR(255) DEFAULT NULL');
ensureColumn('certificates', 'position',       'VARCHAR(255) DEFAULT NULL');
ensureColumn('certificates', 'recipient_org',  'VARCHAR(512) DEFAULT NULL');
ensureColumn('certificates', 'event_name',     'TEXT DEFAULT NULL');
ensureColumn('certificates', 'level',          'VARCHAR(50) DEFAULT NULL');
ensureColumn('certificates', 'nomination',     'VARCHAR(255) DEFAULT NULL');
ensureColumn('certificates', 'doc_number',     'VARCHAR(100) DEFAULT NULL');
ensureColumn('certificates', 'doc_lang',       'VARCHAR(50) DEFAULT NULL');
ensureColumn('certificates', 'notes',          'TEXT DEFAULT NULL');

$fields = '(user_id, edu_student_id, title, issuer, issue_date, place, level,
             recipient_name, curator_name, position, recipient_org, event_name,
             nomination, doc_number, doc_lang, notes, file_path, added_by)';
$placeholders = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$commonVals = [
    $title, $issuer, $issueDate, $place, $level,
    $recipientName, $curatorName, $position, $recipientOrg, $eventName,
    $nomination, $docNumber, $docLang, $notes, $filename, $user['id']
];

if ($ptype === 'teacher') {
    // Если владелец не выбран — используем текущего пользователя
    $finalUserId = $pdfUserId ?: $user['id'];
    $pdo->prepare("INSERT INTO certificates $fields VALUES $placeholders")
        ->execute(array_merge([$finalUserId, 0], $commonVals));
} else {
    $pdo->prepare("INSERT INTO certificates $fields VALUES $placeholders")
        ->execute(array_merge([0, $eduStudentId], $commonVals));
}

unset($_SESSION['cert_parse']);
redirectSuccess("Сертификат «{$title}» успешно добавлен в реестр.", $redirectUrl);