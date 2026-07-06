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

// [ИСПРАВЛЕНИЕ #2] Проверка авторизации
require_once __DIR__ . '/auth_check.php';

// [ИСПРАВЛЕНИЕ #6] Централизованное подключение к БД
require_once __DIR__ . '/../config/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Не удалось подключиться к базе данных');
}

$teacher_id = (int)$_SESSION['user_id'];
$userRole   = $_SESSION['role'] ?? 'teacher';
$action     = $_POST['action'] ?? $_GET['action'] ?? '';

// [ИСПРАВЛЕНИЕ #3] Вспомогательная функция: только куратор/администратор может
// подтверждать и отклонять справки
function requireCuratorOrAdmin(string $role): void {
    $allowed = ['admin', 'director', 'teacher'];
    if (!in_array($role, $allowed)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Недостаточно прав для этого действия']);
        exit;
    }
}

// Если пользователь — обычный преподаватель (не admin/director), проверяем,
// что он действительно куратор группы, к которой относится документ.
function requireDocumentAccess(PDO $pdo, string $role, int $userId, int $groupId): void {
    if (in_array($role, ['admin', 'director'], true)) {
        return;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM edu_groups WHERE id = ? AND curator_id = ?");
    $stmt->execute([$groupId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Вы не куратор этой группы']);
        exit;
    }
}

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

    if (!$student_id || !$group_id) {
        echo json_encode(['success' => false, 'error' => 'Не выбран студент']); exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        echo json_encode(['success' => false, 'error' => 'Некорректные даты']); exit;
    }
    if ($date_from > $date_to) {
        echo json_encode(['success' => false, 'error' => 'Дата «с» не может быть позже даты «по»']); exit;
    }

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

    if ($fileSize > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Файл превышает 5 МБ']); exit;
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);
    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($mimeType, $allowedMimes)) {
        echo json_encode(['success' => false, 'error' => 'Разрешены только PDF, JPG, PNG']); exit;
    }

    $uploadDir = __DIR__ . '/uploads/documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext      = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mimeType];
    $safeName = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . $safeName;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл на сервер']); exit;
    }

    try {
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
    } catch (Throwable $e) {
        // Запись в БД не удалась — не оставляем файл-сироту на диске
        if (file_exists($destPath)) unlink($destPath);
        throw $e;
    }

    _applyDocumentToAttendance($pdo, $student_id, $date_from, $date_to, $reason_id, $teacher_id);

    echo json_encode(['success' => true, 'id' => $docId, 'file' => $safeName]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: verify — подтвердить справку
// [ИСПРАВЛЕНИЕ #3] Только куратор/администратор
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'verify') {
    requireCuratorOrAdmin($userRole);

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id   = (int)($data['id'] ?? 0);
    $note = trim($data['note'] ?? '');

    if (!$id) { echo json_encode(['success'=>false,'error'=>'Не указан ID']); exit; }

    $docStmt = $pdo->prepare("SELECT * FROM att_documents WHERE id = :id");
    $docStmt->execute([':id' => $id]);
    $docRow = $docStmt->fetch();
    if (!$docRow) { echo json_encode(['success'=>false,'error'=>'Документ не найден']); exit; }
    requireDocumentAccess($pdo, $userRole, $teacher_id, (int)$docRow['group_id']);

    $stmt = $pdo->prepare("UPDATE att_documents SET status='verified', note=:note, teacher_id=:tid, updated_at=NOW() WHERE id=:id");
    $stmt->execute([':note'=>$note, ':tid'=>$teacher_id, ':id'=>$id]);

    _applyDocumentToAttendance($pdo, $docRow['student_id'], $docRow['date_from'], $docRow['date_to'], $docRow['reason_id'], $teacher_id);

    echo json_encode(['success' => true]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: reject — отклонить справку
// [ИСПРАВЛЕНИЕ #3] Только куратор/администратор
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'reject') {
    requireCuratorOrAdmin($userRole);

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id   = (int)($data['id'] ?? 0);
    $note = trim($data['note'] ?? '');

    if (!$id) { echo json_encode(['success'=>false,'error'=>'Не указан ID']); exit; }

    $docStmt = $pdo->prepare("SELECT group_id FROM att_documents WHERE id = :id");
    $docStmt->execute([':id' => $id]);
    $docRow = $docStmt->fetch();
    if (!$docRow) { echo json_encode(['success'=>false,'error'=>'Документ не найден']); exit; }
    requireDocumentAccess($pdo, $userRole, $teacher_id, (int)$docRow['group_id']);

    $stmt = $pdo->prepare("UPDATE att_documents SET status='rejected', note=:note, teacher_id=:tid, updated_at=NOW() WHERE id=:id");
    $stmt->execute([':note'=>$note, ':tid'=>$teacher_id, ':id'=>$id]);

    echo json_encode(['success' => true]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: delete — удалить справку
// [ИСПРАВЛЕНИЕ #3 + #7] Только куратор/администратор; используем prepare
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'delete') {
    requireCuratorOrAdmin($userRole);

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id   = (int)($data['id'] ?? 0);

    if (!$id) { echo json_encode(['success'=>false,'error'=>'Не указан ID']); exit; }

    // [ИСПРАВЛЕНИЕ #7] prepare вместо прямого query("...WHERE id=$id")
    $rowStmt = $pdo->prepare("SELECT file_name, group_id FROM att_documents WHERE id = :id");
    $rowStmt->execute([':id' => $id]);
    $row = $rowStmt->fetch();
    if (!$row) { echo json_encode(['success'=>false,'error'=>'Документ не найден']); exit; }
    requireDocumentAccess($pdo, $userRole, $teacher_id, (int)$row['group_id']);

    $filePath = __DIR__ . '/uploads/documents/' . $row['file_name'];
    if (file_exists($filePath)) unlink($filePath);

    $pdo->prepare("DELETE FROM att_documents WHERE id = :id")->execute([':id' => $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: view — отдать файл для просмотра
// [ИСПРАВЛЕНИЕ #7] prepare вместо прямого query("...WHERE id=$id")
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(404); exit; }

    // [ИСПРАВЛЕНИЕ #7] prepare вместо прямого query("...WHERE id=$id")
    $rowStmt = $pdo->prepare("SELECT * FROM att_documents WHERE id = :id");
    $rowStmt->execute([':id' => $id]);
    $row = $rowStmt->fetch();
    if (!$row) { http_response_code(404); exit; }

    // Доступ: только куратор группы или admin/director
    $viewerRole = $_SESSION['role'] ?? '';
    if (!in_array($viewerRole, ['admin', 'director'])) {
        $accessCheck = $pdo->prepare("SELECT 1 FROM edu_groups WHERE id = ? AND curator_id = ?");
        $accessCheck->execute([$row['group_id'], $_SESSION['user_id'] ?? 0]);
        if (!$accessCheck->fetch()) {
            http_response_code(403);
            exit;
        }
    }

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
// ══════════════════════════════════════════════════════════════════════════
function _applyDocumentToAttendance(PDO $pdo, int $student_id, string $date_from, string $date_to, ?int $reason_id, int $teacher_id): void
{
    $cursor = strtotime($date_from);
    $end    = strtotime($date_to);

    $hasSemester = false;
    $hasGroupId = false;
    try {
        foreach ($pdo->query("DESCRIBE att_attendance")->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (($col['Field'] ?? '') === 'semester_id') $hasSemester = true;
            if (($col['Field'] ?? '') === 'group_id') $hasGroupId = true;
        }
    } catch (Throwable $e) {}

    $studentGroupId = null;
    try {
        $stmtGroup = $pdo->prepare("SELECT group_id FROM edu_students WHERE id = :sid LIMIT 1");
        $stmtGroup->execute([':sid' => $student_id]);
        $studentGroupId = (int)($stmtGroup->fetchColumn() ?: 0) ?: null;
    } catch (Throwable $e) {}

    $columns = ['student_id'];
    $values  = [':sid'];
    if ($hasGroupId) { $columns[] = 'group_id'; $values[] = ':gid'; }
    if ($hasSemester) { $columns[] = 'semester_id'; $values[] = ':semid'; }
    $columns = array_merge($columns, ['date','status','hours_missed','reason_id','teacher_id']);
    $values  = array_merge($values,  [':date',"'excused'",'6',':rid',':tid']);

    $stmt = $pdo->prepare("
        INSERT INTO att_attendance (" . implode(',', $columns) . ")
        VALUES (" . implode(',', $values) . ")
        ON DUPLICATE KEY UPDATE
            " . ($hasGroupId ? "group_id = VALUES(group_id)," : "") . "
            " . ($hasSemester ? "semester_id = VALUES(semester_id)," : "") . "
            status     = 'excused',
            reason_id  = VALUES(reason_id),
            teacher_id = VALUES(teacher_id)
    ");

    while ($cursor <= $end) {
        $dow = (int)date('N', $cursor);
        if ($dow <= 5) {
            $dt = date('Y-m-d', $cursor);
            $semId = null;
            if ($hasSemester) {
                try {
                    $stmtSem = $pdo->prepare("SELECT id FROM edu_semesters WHERE :dt BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1");
                    $stmtSem->execute([':dt' => $dt]);
                    $semId = (int)($stmtSem->fetchColumn() ?: 0) ?: null;
                } catch (Throwable $e) {}
            }
            $params = [
                ':sid'  => $student_id,
                ':date' => $dt,
                ':rid'  => $reason_id,
                ':tid'  => $teacher_id,
            ];
            if ($hasGroupId) $params[':gid'] = $studentGroupId;
            if ($hasSemester) $params[':semid'] = $semId;
            $stmt->execute($params);
        }
        $cursor += 86400;
    }
}
