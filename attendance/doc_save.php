<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
});
set_error_handler(function($no, $str, $file, $line) {
    throw new ErrorException($str, 0, $no, $file, $line);
});

// ── Подключение к БД ─────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=p-355792_svgtk;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
    exit;
}

$teacher_id = $_SESSION['user_id'] ?? 1;
$action     = $_POST['action'] ?? $_GET['action'] ?? '';

// ══════════════════════════════════════════════════════════════════════════
// ACTION: upload — загрузка новой справки
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'upload') {

    $student_id = (int)($_POST['student_id'] ?? 0);
    $group_id   = (int)($_POST['group_id']   ?? 0);
    $reason_id  = (int)($_POST['reason_id']  ?? 0) ?: null;
    $date_from  = $_POST['date_from'] ?? '';
    $date_to    = $_POST['date_to']   ?? '';
    $note       = trim($_POST['note'] ?? '');

    // Валидация
    if (!$student_id || !$group_id) {
        echo json_encode(['success' => false, 'error' => 'Не выбран студент']); exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        echo json_encode(['success' => false, 'error' => 'Некорректные даты']); exit;
    }
    if ($date_from > $date_to) {
        echo json_encode(['success' => false, 'error' => 'Дата «с» не может быть позже даты «по»']); exit;
    }

    // Файл обязателен
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Файл превышает upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'Файл превышает MAX_FILE_SIZE формы',
            UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
            UPLOAD_ERR_NO_FILE    => 'Файл не выбран',
            UPLOAD_ERR_NO_TMP_DIR => 'Нет временной папки',
            UPLOAD_ERR_CANT_WRITE => 'Ошибка записи на диск',
        ];
        $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        echo json_encode(['success' => false, 'error' => $uploadErrors[$errCode] ?? 'Ошибка загрузки файла']);
        exit;
    }

    $file     = $_FILES['file'];
    $origName = basename($file['name']);
    $fileSize = $file['size'];
    $tmpPath  = $file['tmp_name'];

    // Проверка размера (5 МБ)
    if ($fileSize > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Файл превышает 5 МБ']); exit;
    }

    // Проверка типа через finfo
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);
    $allowed  = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($mimeType, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Разрешены только PDF, JPG, PNG']); exit;
    }

    // Папка хранения
    $uploadDir = __DIR__ . '/uploads/documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Уникальное имя файла
    $ext      = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mimeType];
    $safeName = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . $safeName;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл на сервер']); exit;
    }

    // Сохраняем в БД
    $stmt = $pdo->prepare("
        INSERT INTO att_documents
            (student_id, group_id, reason_id, date_from, date_to, file_name, file_orig, file_size, file_type, status, note, teacher_id)
        VALUES
            (:sid, :gid, :rid, :df, :dt, :fn, :fo, :fs, :ft, 'pending', :note, :tid)
    ");
    $stmt->execute([
        ':sid'  => $student_id,
        ':gid'  => $group_id,
        ':rid'  => $reason_id,
        ':df'   => $date_from,
        ':dt'   => $date_to,
        ':fn'   => $safeName,
        ':fo'   => $origName,
        ':fs'   => $fileSize,
        ':ft'   => $mimeType,
        ':note' => $note,
        ':tid'  => $teacher_id,
    ]);
    $docId = $pdo->lastInsertId();

    // Автоматически обновляем статус в журнале посещаемости за период
    _applyDocumentToAttendance($pdo, $student_id, $date_from, $date_to, $reason_id, $teacher_id);

    echo json_encode(['success' => true, 'id' => $docId, 'file' => $safeName]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: verify — подтвердить справку
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'verify') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id   = (int)($data['id'] ?? 0);
    $note = trim($data['note'] ?? '');

    if (!$id) { echo json_encode(['success'=>false,'error'=>'Не указан ID']); exit; }

    $stmt = $pdo->prepare("UPDATE att_documents SET status='verified', note=:note, teacher_id=:tid, updated_at=NOW() WHERE id=:id");
    $stmt->execute([':note'=>$note, ':tid'=>$teacher_id, ':id'=>$id]);

    // Обновляем запись в журнале
    $doc = $pdo->prepare("SELECT * FROM att_documents WHERE id=:id")->execute([':id'=>$id]);
    $docRow = $pdo->query("SELECT * FROM att_documents WHERE id=$id")->fetch();
    if ($docRow) {
        _applyDocumentToAttendance($pdo, $docRow['student_id'], $docRow['date_from'], $docRow['date_to'], $docRow['reason_id'], $teacher_id);
    }

    echo json_encode(['success' => true]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: reject — отклонить справку
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'reject') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id   = (int)($data['id'] ?? 0);
    $note = trim($data['note'] ?? '');

    if (!$id) { echo json_encode(['success'=>false,'error'=>'Не указан ID']); exit; }

    $stmt = $pdo->prepare("UPDATE att_documents SET status='rejected', note=:note, teacher_id=:tid, updated_at=NOW() WHERE id=:id");
    $stmt->execute([':note'=>$note, ':tid'=>$teacher_id, ':id'=>$id]);

    echo json_encode(['success' => true]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: delete — удалить справку
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'delete') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id   = (int)($data['id'] ?? 0);

    if (!$id) { echo json_encode(['success'=>false,'error'=>'Не указан ID']); exit; }

    $row = $pdo->query("SELECT file_name FROM att_documents WHERE id=$id")->fetch();
    if ($row) {
        $filePath = __DIR__ . '/uploads/documents/' . $row['file_name'];
        if (file_exists($filePath)) unlink($filePath);
    }

    $pdo->prepare("DELETE FROM att_documents WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success' => true]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: view — отдать файл для просмотра
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'view') {
    $id  = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(404); exit; }

    $row = $pdo->query("SELECT * FROM att_documents WHERE id=$id")->fetch();
    if (!$row) { http_response_code(404); exit; }

    $filePath = __DIR__ . '/uploads/documents/' . $row['file_name'];
    if (!file_exists($filePath)) { http_response_code(404); echo 'Файл не найден'; exit; }

    header('Content-Type: ' . ($row['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . rawurlencode($row['file_orig']) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Неизвестное действие: ' . $action]);

// ══════════════════════════════════════════════════════════════════════════
// Хелпер: применить справку к записям журнала посещаемости
// Для каждого рабочего дня в диапазоне — ставим status='excused'
// ══════════════════════════════════════════════════════════════════════════
function _applyDocumentToAttendance(PDO $pdo, int $student_id, string $date_from, string $date_to, ?int $reason_id, int $teacher_id): void
{
    $cursor = strtotime($date_from);
    $end    = strtotime($date_to);

    $stmt = $pdo->prepare("
        INSERT INTO att_attendance (student_id, date, status, hours_missed, reason_id, teacher_id)
        VALUES (:sid, :date, 'excused', 6, :rid, :tid)
        ON DUPLICATE KEY UPDATE
            status     = 'excused',
            reason_id  = VALUES(reason_id),
            teacher_id = VALUES(teacher_id)
    ");

    while ($cursor <= $end) {
        $dow = (int)date('N', $cursor);
        if ($dow <= 5) { // только пн–пт
            $stmt->execute([
                ':sid'  => $student_id,
                ':date' => date('Y-m-d', $cursor),
                ':rid'  => $reason_id,
                ':tid'  => $teacher_id,
            ]);
        }
        $cursor += 86400;
    }
}
