<?php
// views/my_work_programs/actions/upload_wp.php

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/partials/init.php';
require_once BASE_PATH . '/models/baseModel.php';
require_once BASE_PATH . '/models/umr_work_programs.php';

header('Content-Type: application/json; charset=utf-8');

if (!$canWorkPrograms && !$isPccHead && !$isMethodist) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа']); exit;
}

$moduleWorkPrograms = new umr_work_programs($pdo);

$assignmentId    = (int)($_POST['assignment_id'] ?? 0);
$moduleId        = (int)($_POST['module_id']     ?? 0);
$groupId         = (int)($_POST['group_id']      ?? 0);
$confirmedPccId  = (int)($_POST['confirmed_pcc_id'] ?? 0);

if (!$assignmentId || !$moduleId || !$groupId) {
    echo json_encode(['ok' => false, 'error' => 'Неверные параметры']); exit;
}

// Проверяем что это назначение существует и пользователь имеет право загрузить РП
$assignment = $moduleWorkPrograms->getAssignmentById($assignmentId);

if (!$assignment) {
    echo json_encode(['ok' => false, 'error' => 'Назначение не найдено']); exit;
}

$isOwnerTeacher = ((int)$assignment['teacher_id']  === $userId);
$isOwnerPcc     = ((int)$assignment['pcc_head_id'] === $userId);
$hasFullAccess  = $isAdmin || $isPccHead || $isMethodist;

if (!$hasFullAccess && !$isOwnerTeacher && !$isOwnerPcc) {
    echo json_encode(['ok' => false, 'error' => 'Нет доступа к этому назначению']); exit;
}

// Если методист или "чужой" ПЦК загружает файл должен быть подтверждён выбор
$needsPccConfirm = !$isAdmin && (
    $isMethodist || ($isPccHead && !$isOwnerPcc)
);

if ($needsPccConfirm) {
    if (!$confirmedPccId) {
        echo json_encode(['ok' => false, 'error' => 'Выберите председателя ПЦК для подтверждения']); exit;
    }
    $moduleWorkPrograms->reassignPccHead($assignmentId, $confirmedPccId);
}

$semesterNum = (int)$assignment['semester_num'];

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

// Проверка расширения независимо от MIME
$allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExts, true)) {
    echo json_encode(['ok' => false, 'error' => 'Недопустимое расширение файла']); exit;
}

// Проверка размера (20 МБ)
if ($file['size'] > 20 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'Файл слишком большой (макс. 20 МБ)']); exit;
}

// Проверяем, нет ли уже загруженной программы для этого назначения
$existCheck = $pdo->prepare("
    SELECT id FROM umr_work_programs WHERE assignment_id = ?
");
$existCheck->execute([$assignmentId]);
if ($existCheck->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'РП уже существует. Используйте редактирование для загрузки новой версии']); exit;
}

// Сохраняем файл
$safeName  = sprintf('wp_%d_%d_%s.%s', $assignmentId, time(), bin2hex(random_bytes(4)), $ext);
$uploadDir = BASE_PATH . '/uploads/work_programs/';


$destPath = $uploadDir . $safeName;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'Ошибка сохранения файла']); exit;
}

// Путь относительно BASE_PATH (для хранения в БД и ссылок в браузере)
$relPath = 'uploads/work_programs/' . $safeName;

// Пишем в БД
$ins = $pdo->prepare("
    INSERT INTO umr_work_programs
        (assignment_id, module_id, teacher_id, group_id, semester_num,
         version, status, file_path, created_by)
    VALUES (?, ?, ?, ?, ?, 1, 'pending', ?, ?)
");
$ins->execute([$assignmentId, $moduleId, (int)$assignment['teacher_id'], $groupId, $semesterNum, $relPath, $userId]);

echo json_encode(['ok' => true, 'file_path' => $relPath]);