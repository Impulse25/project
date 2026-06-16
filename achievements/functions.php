<?php
require_once __DIR__ . '/config.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirectError(string $message, string $url): void {
    $_SESSION['flash_error'] = $message;
    header('Location: ' . $url);
    exit;
}

function redirectSuccess(string $message, string $url): void {
    $_SESSION['flash'] = $message;
    header('Location: ' . $url);
    exit;
}

function uploadFile(string $prefix, string $redirectUrl): ?string {
    if (empty($_FILES['pdf_file']['name']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file    = $_FILES['pdf_file'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed) || $file['size'] > 10 * 1024 * 1024) {
        redirectError('Неверный формат или размер файла (макс. 10 МБ, PDF/JPG/PNG).', $redirectUrl);
    }
    $uploadDir = dirname(__DIR__) . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        redirectError('Ошибка при сохранении файла на сервере.', $redirectUrl);
    }
    return $filename;
}

function ensureColumn(string $table, string $column, string $definition): void {
    $pdo = getPDO();
    try {
        $exists = (bool)$pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    } catch (Exception $e) {}
}

function getAllGroups(): array {
    return getPDO()->query(
        "SELECT g.id, g.name, g.year_started,
                u.full_name AS teacher_name, u.id AS teacher_id
         FROM edu_groups g
         LEFT JOIN users u ON g.curator_id = u.id
         ORDER BY g.name"
    )->fetchAll();
}

function getAchievements(int $userId): array {
    $s = getPDO()->prepare(
        "SELECT a.*, u.full_name AS added_by_name
         FROM achievements a
         LEFT JOIN users u ON a.added_by = u.id
         WHERE a.user_id = ?
         ORDER BY a.date_event DESC, a.created_at DESC"
    );
    $s->execute([$userId]);
    return $s->fetchAll();
}

function getCertificates(int $userId): array {
    $s = getPDO()->prepare(
        "SELECT * FROM certificates
         WHERE user_id = ?
         ORDER BY issue_date DESC, created_at DESC"
    );
    $s->execute([$userId]);
    return $s->fetchAll();
}

function recalcRating(int $userId): void {
    if ($userId <= 0) return;
    try {
        $pdo = getPDO();

        // На этом хостинге таблицы ratings/events могут отсутствовать —
        // тогда пересчёт рейтинга просто пропускается, удаление не падает.
        $hasRatings = (bool)$pdo->query("SHOW TABLES LIKE 'ratings'")->fetch();
        if (!$hasRatings) return;

        $counts = ['achievements' => 0, 'certificates' => 0, 'events' => 0];
        foreach ([
            'achievements'       => 'achievements',
            'certificates'       => 'certificates',
            'event_participants' => 'events',
        ] as $table => $key) {
            try {
                $s = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE user_id = ?");
                $s->execute([$userId]);
                $counts[$key] = (int)$s->fetchColumn();
            } catch (Exception $e) {
                // таблицы нет — считаем 0 и идём дальше
            }
        }

        $points = $counts['achievements'] * 30
                + $counts['certificates'] * 20
                + $counts['events']       * 10;

        $check = $pdo->prepare("SELECT id FROM ratings WHERE user_id = ?");
        $check->execute([$userId]);
        if ($check->fetch()) {
            $pdo->prepare(
                "UPDATE ratings SET total_points=?, achievements_count=?, certificates_count=?, events_count=? WHERE user_id=?"
            )->execute([$points, $counts['achievements'], $counts['certificates'], $counts['events'], $userId]);
        } else {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM ratings")->fetchColumn();
            $pdo->prepare(
                "INSERT INTO ratings (id,user_id,total_points,achievements_count,certificates_count,events_count) VALUES (?,?,?,?,?,?)"
            )->execute([$maxId+1, $userId, $points, $counts['achievements'], $counts['certificates'], $counts['events']]);
        }
    } catch (Exception $e) {
        // Рейтинг не критичен для удаления — молча игнорируем любые ошибки.
    }
}
function categoryLabel(string $category): string {
    return [
        'olympiad'   => 'Олимпиада',
        'conference' => 'Конференция',
        'sport'      => 'Спорт',
        'art'        => 'Творчество',
        'science'    => 'Наука',
        'other'      => 'Другое',
    ][$category] ?? $category;
}

function levelLabel(string $level): string {
    return [
        'college'       => 'Колледж',
        'city'          => 'Город',
        'regional'      => 'Регион',
        'national'      => 'Республика',
        'international' => 'Международный',
    ][$level] ?? $level;
}

function absenceReasonLabel(string $reason): string {
    return [
        'sick'      => 'По болезни',
        'no_reason' => 'Без причины',
        'excused'   => 'Уважительная',
    ][$reason] ?? $reason;
}