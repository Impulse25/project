<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin', 'teacher', 'director');

$redirectUrl  = SITE_URL . '/cert_review.php';
$ptype        = $_POST['ptype']            ?? 'teacher';
$eduStudentId = (int)($_POST['edu_student_id'] ?? 0);
$pdfUserId    = (int)($_POST['pdf_user_id']    ?? 0);

if (empty($_FILES['pdf_file']['name']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirectUrl); exit;
}

$file    = $_FILES['pdf_file'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf','jpg','jpeg','png','webp'];

if (!in_array($ext, $allowed) || $file['size'] > 10*1024*1024) {
    $_SESSION['flash_error'] = 'Неверный формат или размер файла.';
    header('Location: ' . $redirectUrl); exit;
}

$uploadDir = dirname(__DIR__) . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ownerId  = $ptype === 'student' ? $eduStudentId : $pdfUserId;
$filename = 'cert_' . ($ownerId ?: 'tmp') . '_' . time() . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    header('Location: ' . $redirectUrl); exit;
}

$pdo = getPDO();
$ownerName = '';
try {
    if ($ptype === 'student' && $eduStudentId) {
        $s = $pdo->prepare("SELECT CONCAT(surname,' ',name,IF(patronymic!='' AND patronymic IS NOT NULL,CONCAT(' ',patronymic),'')) FROM edu_students WHERE id=?");
        $s->execute([$eduStudentId]);
        $ownerName = $s->fetchColumn() ?: '';
    } elseif ($pdfUserId) {
        $s = $pdo->prepare("SELECT full_name FROM users WHERE id=?");
        $s->execute([$pdfUserId]);
        $ownerName = $s->fetchColumn() ?: '';
    }
} catch (Exception $e) {}

require_once __DIR__ . '/cert_pdf_parse_functions.php';

$result = parseWithGemini($dest, $ext);
if (!empty($result['__fallback'])) {
    $result = parseWithOpenRouter($dest, $ext);
}
unset($result['__fallback'], $result['__skip']);

$_SESSION['cert_parse'] = [
    'edu_student_id' => $ptype === 'student' ? $eduStudentId : 0,
    'user_id'        => $ptype === 'teacher'  ? $pdfUserId   : 0,
    'owner_name'     => $ownerName,
    'ptype'          => $ptype,
    'filename'       => $filename,
    'title'          => mb_strimwidth($result['title']          ?? '', 0, 200, '…'),
    'recipient_name' => mb_strimwidth($result['recipient_name'] ?? '', 0, 255, '…'),
    'position'       => mb_strimwidth($result['position']       ?? '', 0, 255, '…'),
    'recipient_org'  => mb_strimwidth($result['recipient_org']  ?? '', 0, 512, '…'),
    'curator_name'   => mb_strimwidth($result['curator_name']   ?? '', 0, 255, '…'),
    'issuer'         => mb_strimwidth($result['issuer']         ?? '', 0, 255, '…'),
    'event_name'     => $result['event_name']  ?? '',
    'level'          => $result['level']       ?? '',
    'place'          => $result['place']       ?? '',
    'nomination'     => mb_strimwidth($result['nomination'] ?? '', 0, 255, '…'),
    'doc_number'     => mb_strimwidth($result['doc_number'] ?? '', 0, 100, '…'),
    'doc_lang'       => $result['doc_lang']    ?? 'Казахский',
    'date'           => $result['date']        ?? '',
    'notes'          => $result['notes']       ?? '',
    'error'          => $result['error']       ?? '',
];

header('Location: ' . $redirectUrl);