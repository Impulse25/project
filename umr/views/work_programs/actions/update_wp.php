<?php
// views/work_programs/actions/update_wp.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!$canWorkPrograms && !$isPccHead && !$isMethodist) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}

$wpId         = (int)($_POST['wp_id']         ?? 0);
$assignmentId = (int)($_POST['assignment_id'] ?? 0);
$confirmedPccId = (int)($_POST['confirmed_pcc_id'] ?? 0);

if (!$wpId || !$assignmentId) {
    echo json_encode(['ok' => false, 'error' => 'Неверные параметры']); exit;
}

// Загружаем текущую запись РП
$cur = $pdo->prepare("
    SELECT wp.*, ta.teacher_id, ta.semester_num
    FROM umr_work_programs wp
    JOIN umr_teacher_assignments ta ON ta.id = wp.assignment_id
    WHERE wp.id = ?
");
$cur->execute([$wpId]);
$wp = $cur->fetch();

if (!$wp) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit;
}

// Сам преподаватель, председатель ПЦК (назначивший), методист, или admin
$isOwnerTeacher = ((int)$wp['teacher_id'] === $userId);
$pccRow = $pdo->prepare("SELECT pcc_head_id FROM umr_teacher_assignments WHERE id = ?");
$pccRow->execute([$wp['assignment_id']]);
$pccHeadId = (int)($pccRow->fetchColumn() ?: 0);
$isOwnerPcc = ($pccHeadId === $userId);
$hasFullAccess = $isAdmin || $isPccHead || $isMethodist;

if (!$hasFullAccess && !$isOwnerTeacher && !$isOwnerPcc) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}

// Методист или "чужой" ПЦК — должен подтвердить, за какого председателя учитывается замена
$needsPccConfirm = !$isAdmin && (
    $isMethodist || ($isPccHead && !$isOwnerPcc)
);

if ($needsPccConfirm) {
    if (!$confirmedPccId) {
        echo json_encode(['ok' => false, 'error' => 'Выберите председателя ПЦК для подтверждения']); exit;
    }
    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_pcc_head = 1");
    $chk->execute([$confirmedPccId]);
    if ($chk->fetch()) {
        $reassign = $pdo->prepare("UPDATE umr_teacher_assignments SET pcc_head_id = ? WHERE id = ?");
        $reassign->execute([$confirmedPccId, $wp['assignment_id']]);
    }
}

// Нельзя заменить уже утверждённую
if ($wp['status'] === 'approved') {
    echo json_encode(['ok' => false, 'error' => 'Утверждённую программу нельзя заменить']); exit;
}

// Проверяем файл
$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'Файл превышает upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'Файл превышает MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
        UPLOAD_ERR_NO_FILE    => 'Файл не выбран',
        UPLOAD_ERR_NO_TMP_DIR => 'Нет временной папки',
        UPLOAD_ERR_CANT_WRITE => 'Ошибка записи на диск',
    ];
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['ok' => false, 'error' => $errMap[$code] ?? 'Ошибка загрузки']); exit;
}

// Проверка типа файла
$allowed = [
    'application/pdf',                                           // .pdf
    'application/msword',                                        // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/vnd.ms-excel',                                  // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'        // .xlsx
];

$mimeType = mime_content_type($file['tmp_name']);

if (!in_array($mimeType, $allowed)) {
    echo json_encode([
        'ok' => false, 
        'error' => 'Разрешены только файлы: PDF, DOC, DOCX, XLS, XLSX'
    ]);
    exit;
}


// Проверка размера (20 МБ)
if ($file['size'] > 20 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'Файл слишком большой (макс. 20 МБ)']); exit;
}

// Сохраняем новый файл
$newVersion = (int)$wp['version'] + 1;
$ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeName   = sprintf('wp_%d_v%d_%s.%s', $assignmentId, $newVersion, bin2hex(random_bytes(4)), $ext);
$uploadDir  = BASE_PATH . '/uploads/work_programs/';


$destPath = $uploadDir . $safeName;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'Ошибка сохранения файла']); exit;
}

// Удаляем старый файл (если есть)
$oldFile = BASE_PATH . '/' . $wp['file_path'];
if ($wp['file_path'] && file_exists($oldFile)) {
    @unlink($oldFile);
}

$relPath = 'uploads/work_programs/' . $safeName;

// Обновляем запись: новая версия, статус → pending, сброс причины отклонения
$upd = $pdo->prepare("
    UPDATE umr_work_programs
    SET file_path = ?, version = ?, status = 'pending',
        reject_reason = NULL, approved_by = NULL, approved_at = NULL
    WHERE id = ?
");
$upd->execute([$relPath, $newVersion, $wpId]);

echo json_encode(['ok' => true, 'version' => $newVersion, 'file_path' => $relPath]);