<?php
// hr/actions.php — обычный POST-обработчик HR-аналитики без фоновых запросов
// Поддерживаемые действия: save, delete, delete_doc. Файлы загружаются вместе с save.

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/app/access.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'hr/'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = hr_normalize_role($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? '');

function intOrNull($v): ?int {
    return (is_numeric($v) && $v !== '') ? (int)$v : null;
}

function strOrNull($v): ?string {
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : null;
}

function dateOrNull($v): ?string {
    $v = trim((string)($v ?? ''));
    if ($v === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}



function isAjaxRequest(): bool {
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    return strcasecmp($requestedWith, 'XMLHttpRequest') === 0
        || ($_POST['ajax'] ?? '') === '1'
        || ($_GET['ajax'] ?? '') === '1'
        || str_contains($accept, 'application/json');
}

function jsonResponse(bool $success, string $message = '', string $type = 'success', array $extra = []): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode(array_merge([
        'success' => $success,
        'type'    => $type === 'error' ? 'error' : 'success',
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function failValidation(string $message): void {
    redirectBack($message, 'error');
}

function hasAllowedChars(?string $value, bool $isNotes = false): bool {
    if ($value === null || $value === '') return true;

    // Разрешены буквы, цифры, пробелы и обычные символы для названий организаций/должностей.
    // Запрещены технические и мусорные знаки: @ # $ % ^ * = + { } [ ] < > | ~ ` и подобные.
    $pattern = $isNotes
        ? '/^[\p{L}\p{N}\s\.\,\-–—\'"«»„“”№\/\(\)!\?;:]+$/u'
        : '/^[\p{L}\p{N}\s\.\,\-–—\'"«»„“”№\/\(\)]+$/u';

    return preg_match($pattern, $value) === 1;
}

function ensureTextField(?string $value, string $label, int $maxLen = 255, bool $required = true, bool $isNotes = false): ?string {
    $value = trim((string)($value ?? ''));
    $value = preg_replace('/\s+/u', ' ', $value);

    if ($value === '') {
        if ($required) failValidation('Заполните поле: ' . $label);
        return null;
    }

    if (mb_strlen($value, 'UTF-8') > $maxLen) {
        failValidation('Поле «' . $label . '» слишком длинное. Максимум: ' . $maxLen . ' символов');
    }

    if (!hasAllowedChars($value, $isNotes)) {
        failValidation('Поле «' . $label . '» содержит запрещённые символы');
    }

    return $value;
}

function calculateStudentGraduationYear(PDO $pdo, int $studentId): ?int {
    if ($studentId <= 0) return null;

    $gradExpr = hr_group_grad_expr('g');
    $stmt = $pdo->prepare("
        SELECT $gradExpr AS graduation_year
        FROM edu_students s
        LEFT JOIN edu_groups g ON g.id = s.group_id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId]);

    $value = $stmt->fetchColumn();
    if ($value === false || $value === null || $value === '') return null;

    $year = (int)$value;
    return ($year >= 2000 && $year <= 2099) ? $year : null;
}

function setFlash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = [
        'message' => $message,
        'type'    => $type === 'error' ? 'error' : 'success',
    ];
}

function safeRedirectTarget(?string $target): string {
    $target = trim((string)$target);
    if ($target === '') return 'index.php';

    // Не даём увести пользователя на внешний адрес.
    if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $target) || substr($target, 0, 2) === '//') {
        return 'index.php';
    }

    return $target;
}

function redirectBack(?string $message = null, string $type = 'success'): void {
    $target = safeRedirectTarget($_POST['redirect_to'] ?? ($_SERVER['HTTP_REFERER'] ?? 'index.php'));

    if (isAjaxRequest()) {
        jsonResponse($type !== 'error', $message ?? '', $type, [
            'redirect' => $target,
        ]);
    }

    if ($message !== null) setFlash($message, $type);
    header('Location: ' . $target);
    exit;
}

function statusAllowsDocuments(string $status): bool {
    return in_array($status, ['employed', 'studying', 'decree', 'military'], true);
}

function normalizeUploadedFiles(array $files): array {
    if (!isset($files['name'])) return [];

    // input name="documents[]"
    if (is_array($files['name'])) {
        $result = [];
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $result[] = [
                'name'     => $name,
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
        }
        return $result;
    }

    // input name="file"
    if (($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [];
    return [$files];
}

function uploadDocuments(PDO $pdo, int $employmentId, int $userId): int {
    $uploadedFiles = [];
    if (isset($_FILES['documents'])) {
        $uploadedFiles = normalizeUploadedFiles($_FILES['documents']);
    } elseif (isset($_FILES['file'])) {
        $uploadedFiles = normalizeUploadedFiles($_FILES['file']);
    }

    if (!$uploadedFiles) return 0;

    $allowedMime = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'];
    $maxSize = 10 * 1024 * 1024;

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $count = 0;
    foreach ($uploadedFiles as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ошибка загрузки файла');
        }

        $origName = (string)$file['name'];
        $tmpName  = (string)$file['tmp_name'];
        $fileSize = (int)$file['size'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Файл не был загружен');
        }
        if ($fileSize > $maxSize) {
            throw new RuntimeException('Файл превышает 10 МБ');
        }
        if (!in_array($ext, $allowedExt, true)) {
            throw new RuntimeException('Недопустимое расширение файла');
        }

        $mimeType = mime_content_type($tmpName) ?: '';
        if (!in_array($mimeType, $allowedMime, true)) {
            throw new RuntimeException('Недопустимый тип файла');
        }

        $newName  = 'hr_' . $employmentId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $uploadDir . $newName;

        if (!move_uploaded_file($tmpName, $destPath)) {
            throw new RuntimeException('Ошибка сохранения файла');
        }

        $stmt = $pdo->prepare("\n            INSERT INTO hr_documents (employment_id, filename, original_name, file_size, mime_type, uploaded_by)\n            VALUES (?, ?, ?, ?, ?, ?)\n        ");
        $stmt->execute([$employmentId, $newName, $origName, $fileSize, $mimeType, $userId]);
        $count++;
    }

    return $count;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Некорректный метод запроса', 'error');
}

$action = $_POST['action'] ?? '';

try {
    // Удаление документа запускается отдельной кнопкой внутри формы редактирования.
    if (isset($_POST['delete_doc_id']) && is_numeric($_POST['delete_doc_id'])) {
        $docId = (int)$_POST['delete_doc_id'];

        $row = $pdo->prepare('SELECT filename, employment_id FROM hr_documents WHERE id = ?');
        $row->execute([$docId]);
        $doc = $row->fetch();

        if ($doc) {
            if (!hr_user_can_manage_record($pdo, (int)$doc['employment_id'], $userId, $userRole)) {
                redirectBack('Нет доступа к удалению этого документа', 'error');
            }

            $path = __DIR__ . '/uploads/' . $doc['filename'];
            if (file_exists($path)) unlink($path);
            $pdo->prepare('DELETE FROM hr_documents WHERE id = ?')->execute([$docId]);
        }

        redirectBack('Документ удалён');
    }

    if ($action === 'save') {
        $studentId = intOrNull($_POST['student_id'] ?? '');
        $status    = trim((string)($_POST['status'] ?? 'unknown'));
        $recordId  = intOrNull($_POST['record_id'] ?? '');

        if (!$studentId) {
            redirectBack('Не выбран студент', 'error');
        }

        if ($recordId) {
            if (!hr_user_can_manage_record($pdo, $recordId, $userId, $userRole)) {
                redirectBack('Нет доступа к изменению этой записи', 'error');
            }
            $ownerStmt = $pdo->prepare('SELECT student_id FROM hr_employment WHERE id = ?');
            $ownerStmt->execute([$recordId]);
            if ((int)$ownerStmt->fetchColumn() !== $studentId) {
                redirectBack('Запись не относится к выбранному студенту', 'error');
            }
        } elseif (!hr_user_can_manage_student($pdo, $studentId, $userId, $userRole)) {
            redirectBack('Нет доступа к добавлению записи для этого студента', 'error');
        }

        $allowedStatus = ['employed', 'unemployed', 'studying', 'decree', 'military', 'relocation', 'other', 'unknown'];
        $allowedType   = ['full_time', 'part_time', 'contract', 'self_employed', 'other'];

        if (!in_array($status, $allowedStatus, true)) {
            redirectBack('Некорректный статус занятости', 'error');
        }

        $isEmployed = $status === 'employed';

        $employerName   = ensureTextField($_POST['employer_name'] ?? '', 'Организация', 255, $isEmployed);
        $position       = ensureTextField($_POST['position'] ?? '', 'Должность', 255, $isEmployed);
        $employmentDate = dateOrNull($_POST['employment_date'] ?? '');
        $employmentType = trim((string)($_POST['employment_type'] ?? 'full_time'));
        $isBySpec       = isset($_POST['is_by_specialty']) ? 1 : 0;
        $graduationYear = calculateStudentGraduationYear($pdo, $studentId);
        $notes          = ensureTextField($_POST['notes'] ?? '', 'Примечание', 2000, false, true);

        if (!in_array($employmentType, $allowedType, true)) {
            redirectBack('Некорректный тип занятости', 'error');
        }

        if ($isEmployed && !$employmentDate) {
            redirectBack('Заполните поле: Дата трудоустройства', 'error');
        }

        if (!$isEmployed) {
            $employerName = null;
            $position = null;
            $employmentDate = null;
            $employmentType = null;
            $isBySpec = 0;
        }

        $isNewRecord = !$recordId;

        if ($recordId) {
            $stmt = $pdo->prepare("\n                UPDATE hr_employment\n                SET status = ?, employer_name = ?, position = ?, employment_date = ?,\n                    employment_type = ?, is_by_specialty = ?, graduation_year = ?, notes = ?, updated_at = NOW()\n                WHERE id = ?\n            ");
            $stmt->execute([
                $status, $employerName, $position, $employmentDate,
                $employmentType, $isBySpec, $graduationYear, $notes, $recordId,
            ]);
        } else {
            $stmt = $pdo->prepare("\n                INSERT INTO hr_employment\n                    (student_id, status, employer_name, position, employment_date,\n                     employment_type, is_by_specialty, graduation_year, notes, added_by)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            $stmt->execute([
                $studentId, $status, $employerName, $position, $employmentDate,
                $employmentType, $isBySpec, $graduationYear, $notes, $userId,
            ]);
            $recordId = (int)$pdo->lastInsertId();
        }

        $uploadedCount = statusAllowsDocuments($status)
            ? uploadDocuments($pdo, $recordId, $userId)
            : 0;
        $message = $isNewRecord ? 'Запись добавлена' : 'Запись сохранена';
        if ($uploadedCount > 0) {
            $message .= '. Загружено файлов: ' . $uploadedCount;
        }
        redirectBack($message);
    }

    if ($action === 'delete') {
        $recordId = intOrNull($_POST['record_id'] ?? '');
        if (!$recordId) {
            redirectBack('Не указан ID записи', 'error');
        }
        if (!hr_user_can_manage_record($pdo, $recordId, $userId, $userRole)) {
            redirectBack('Нет доступа к удалению этой записи', 'error');
        }

        $docs = $pdo->prepare('SELECT filename FROM hr_documents WHERE employment_id = ?');
        $docs->execute([$recordId]);
        foreach ($docs->fetchAll() as $doc) {
            $path = __DIR__ . '/uploads/' . $doc['filename'];
            if (file_exists($path)) unlink($path);
        }

        $pdo->prepare('DELETE FROM hr_documents WHERE employment_id = ?')->execute([$recordId]);
        $pdo->prepare('DELETE FROM hr_employment WHERE id = ?')->execute([$recordId]);

        redirectBack('Запись удалена');
    }

    redirectBack('Неизвестное действие', 'error');
} catch (Throwable $e) {
    redirectBack($e->getMessage(), 'error');
}
