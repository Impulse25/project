<?php
/**
 * Единый источник данных посещаемости.
 * Сейчас используется локальная демо-таблица, чтобы модуль аналитики работал автономно.
 * При интеграции с отдельным модулем учета посещаемости нужно заменить SQL внутри этого файла,
 * а страницы аналитики/отчетов менять не придется.
 */

function ensureAttendanceSource(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS external_attendance_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        group_id INT NOT NULL,
        record_date DATE NOT NULL,
        hours INT NOT NULL DEFAULT 2,
        status VARCHAR(20) NOT NULL DEFAULT 'present',
        reason_type VARCHAR(20) NOT NULL DEFAULT 'none',
        certificate_file VARCHAR(255) NULL,
        source_name VARCHAR(80) NOT NULL DEFAULT 'demo_attendance_module',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY student_id (student_id),
        KEY group_id (group_id),
        KEY record_date (record_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM external_attendance_records")->fetchColumn();
    if ($count > 0) {
        return;
    }

    $students = $pdo->query("SELECT id, group_id FROM students ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (!$students) {
        return;
    }

    $insert = $pdo->prepare("INSERT INTO external_attendance_records
        (student_id, group_id, record_date, hours, status, reason_type, certificate_file)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    $base = strtotime('2025-09-01');
    foreach ($students as $student) {
        $studentId = (int)$student['id'];
        $groupId = (int)$student['group_id'];
        for ($i = 0; $i < 18; $i++) {
            $date = date('Y-m-d', strtotime('+' . ($i * 7) . ' days', $base));
            $mod = ($studentId + $i) % 10;
            if ($mod <= 6) {
                $status = 'present';
                $reason = 'none';
                $hours = 2;
                $file = null;
            } elseif ($mod <= 8) {
                $status = 'late';
                $reason = 'none';
                $hours = 1;
                $file = null;
            } else {
                $status = 'absent';
                $reason = ($studentId % 3 === 0) ? 'valid' : 'invalid';
                $hours = 2;
                $file = ($reason === 'valid') ? 'spravka_student_' . $studentId . '.pdf' : null;
            }
            $insert->execute([$studentId, $groupId, $date, $hours, $status, $reason, $file]);
        }
    }
}

function getAttendanceRecords(PDO $pdo, array $groupIds, ?int $studentId, string $dateFrom, string $dateTo): array
{
    ensureAttendanceSource($pdo);
    $where = ['r.record_date BETWEEN ? AND ?'];
    $params = [$dateFrom, $dateTo];

    if ($studentId) {
        $where[] = 'r.student_id = ?';
        $params[] = $studentId;
    } elseif ($groupIds) {
        $where[] = 'r.group_id IN (' . placeholders($groupIds) . ')';
        $params = array_merge($params, $groupIds);
    } else {
        return [];
    }

    $sql = "SELECT r.*, u.full_name, g.name AS group_name
            FROM external_attendance_records r
            JOIN students s ON s.id = r.student_id
            JOIN users u ON u.id = s.user_id
            JOIN groups g ON g.id = r.group_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.record_date DESC, g.name, u.full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateAttendanceStats(array $records): array
{
    $stats = [
        'total_hours' => 0,
        'present_hours' => 0,
        'late_hours' => 0,
        'absent_hours' => 0,
        'valid_absent_hours' => 0,
        'invalid_absent_hours' => 0,
        'certificates' => 0,
        'percent' => 0,
    ];

    foreach ($records as $record) {
        $hours = (int)$record['hours'];
        $stats['total_hours'] += $hours;
        if ($record['status'] === 'present') {
            $stats['present_hours'] += $hours;
        } elseif ($record['status'] === 'late') {
            $stats['late_hours'] += $hours;
        } elseif ($record['status'] === 'absent') {
            $stats['absent_hours'] += $hours;
            if ($record['reason_type'] === 'valid') {
                $stats['valid_absent_hours'] += $hours;
            } else {
                $stats['invalid_absent_hours'] += $hours;
            }
        }
        if (!empty($record['certificate_file'])) {
            $stats['certificates']++;
        }
    }

    if ($stats['total_hours'] > 0) {
        $score = $stats['present_hours'] + round($stats['late_hours'] * 0.7);
        $stats['percent'] = (int)round(($score / $stats['total_hours']) * 100);
    }

    return $stats;
}

function attendanceStatusTitle(string $status): string
{
    $map = [
        'present' => 'Пришёл без опоздания',
        'late' => 'Пришёл, но опоздал',
        'absent' => 'Отсутствовал',
    ];
    return $map[$status] ?? 'Нет данных';
}

function attendanceReasonTitle(string $reason): string
{
    $map = [
        'none' => 'Не требуется',
        'valid' => 'Уважительная причина',
        'invalid' => 'Неуважительная причина',
    ];
    return $map[$reason] ?? 'Не указано';
}
