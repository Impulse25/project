<?php
/**
 * Вспомогательные функции модуля «Аналитика и отчётность».
 * Файл вынесен отдельно, чтобы структура модуля соответствовала описанию в отчёте.
 */
function an_role($role): string {
    $role = mb_strtolower(trim((string)$role), 'UTF-8');
    $role = str_replace([' ', '-'], '_', $role);
    $map = [
        '1' => 'admin',
        '2' => 'director',
        '3' => 'teacher',
        'admin' => 'admin',
        'administrator' => 'admin',
        'админ' => 'admin',
        'администратор' => 'admin',
        'director' => 'director',
        'директор' => 'director',
        'teacher' => 'teacher',
        'prepod' => 'teacher',
        'преподаватель' => 'teacher',
        'препод' => 'teacher',
        'технолог' => 'teacher',
        '4' => 'student',
        'student' => 'student',
        'студент' => 'student',
    ];
    return $map[$role] ?? $role;
}

$role = an_role($userRoleRaw);
if (!in_array($role, ['admin', 'director', 'teacher'], true)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="assets/css/analytics.css"><h2>Доступ ограничен</h2><p>Модуль аналитики доступен администратору, директору и преподавателю.</p>';
    exit;
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function an_column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function an_table_exists(PDO $pdo, string $table): bool {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function an_department_head(PDO $pdo, int $userId): array {
    if ($userId <= 0 || !an_column_exists($pdo, 'users', 'is_department_head') || !an_column_exists($pdo, 'users', 'head_department_id')) {
        return ['is_head' => false, 'department_id' => null];
    }
    $stmt = $pdo->prepare("SELECT is_department_head, head_department_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return [
        'is_head' => ((int)($row['is_department_head'] ?? 0) === 1 && (int)($row['head_department_id'] ?? 0) > 0),
        'department_id' => (int)($row['head_department_id'] ?? 0),
    ];
}

function an_accessible_group_ids(PDO $pdo, int $userId, string $role): array {
    if (in_array($role, ['admin', 'director'], true)) {
        return array_map('intval', $pdo->query("SELECT id FROM edu_groups ORDER BY name")->fetchAll(PDO::FETCH_COLUMN));
    }

    if ($role === 'teacher') {
        $head = an_department_head($pdo, $userId);
        if (!empty($head['is_head']) && !empty($head['department_id']) && an_column_exists($pdo, 'edu_groups', 'department_id')) {
            $stmt = $pdo->prepare("SELECT id FROM edu_groups WHERE department_id = ? ORDER BY name");
            $stmt->execute([(int)$head['department_id']]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        if (an_column_exists($pdo, 'edu_groups', 'curator_id')) {
            $stmt = $pdo->prepare("SELECT id FROM edu_groups WHERE curator_id = ? ORDER BY name");
            $stmt->execute([$userId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    return [];
}

function an_in(array $ids): string {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    return $ids ? implode(',', $ids) : '0';
}

function an_date_range(string $period, string $from, string $to): array {
    $today = date('Y-m-d');
    if ($period === 'week') return [date('Y-m-d', strtotime($today . ' -6 days')), $today];
    if ($period === 'month') return [date('Y-m-d', strtotime($today . ' -30 days')), $today];
    if ($period === 'semester') return [date('Y-m-d', strtotime($today . ' -6 months')), $today];
    if ($period === 'year') return [date('Y-m-d', strtotime($today . ' -1 year')), $today];
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $today;
    if ($from > $to) [$from, $to] = [$to, $from];
    return [$from, $to];
}

function an_course_from_group(string $name): string {
    /*
     * В названиях групп портала часто есть два числа: например «БДТ-11-23».
     * Первое число — база набора/специальности, поэтому нельзя брать первое совпадение «-11».
     * Берём последние две цифры в конце названия: «...-23», «...-24», «...-25».
     *
     * Важно: курс НЕ должен автоматически превращаться в «Выпускники».
     * В портале могут оставаться старые или тестовые группы, и из-за этого карточка
     * «Выпускники» ошибочно показывала 1. Выпуск считается отдельно только
     * по явному признаку выпуска, а не по возрасту группы.
     */
    $name = trim($name);
    if (preg_match('/-(\d{2})\s*$/u', $name, $m)) {
        $startYear = 2000 + (int)$m[1];
        $course = (int)date('Y') - $startYear;
        if ((int)date('n') >= 9) $course++;
        if ($course < 1) $course = 1;
        if ($course > 4) $course = 4;
        return $course . ' курс';
    }
    return 'Не указан';
}

function an_group_is_graduate(string $name): bool {
    /*
     * Считаем выпускной только группу, где это явно указано в названии.
     * Не используем an_course_from_group(), чтобы группы старых годов не попадали
     * в выпуск автоматически.
     */
    $name = trim($name);
    return (bool)preg_match('/выпуск|выпускн|graduate/ui', $name);
}

function an_grade_category($grade): string {
    if ($grade === null || $grade === '') return 'none';
    $v = (float)$grade;
    if ($v >= 90) return 'excellent';
    if ($v >= 70) return 'good';
    if ($v >= 51) return 'satisfactory';
    if ($v >= 0) return 'bad';
    return 'none';
}
