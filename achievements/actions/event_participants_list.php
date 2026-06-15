<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) { echo '<p>Не указано мероприятие.</p>'; exit; }

$pdo = getPDO();

// Участники — преподаватели/сотрудники из users
$stmtUsers = $pdo->prepare("SELECT ep.*, u.full_name, u.role,
    'user' AS ptype, '' AS group_name
    FROM event_participants ep
    JOIN users u ON ep.user_id = u.id AND ep.user_id != 0
    WHERE ep.event_id = ?
    ORDER BY u.full_name");
$stmtUsers->execute([$eventId]);
$userParticipants = $stmtUsers->fetchAll();

// Участники — студенты из edu_students (если есть колонка)
$studentParticipants = [];
try {
    $col = (bool)$pdo->query("SHOW COLUMNS FROM event_participants LIKE 'edu_student_id'")->fetch();
    if ($col) {
        $stmtSt = $pdo->prepare("SELECT ep.*,
            CONCAT(es.surname,' ',es.name,
                IF(es.patronymic!='' AND es.patronymic IS NOT NULL,
                   CONCAT(' ',es.patronymic),'')) AS full_name,
            'student' AS ptype,
            g.name AS group_name
            FROM event_participants ep
            JOIN edu_students es ON ep.edu_student_id = es.id
            LEFT JOIN edu_groups g ON es.group_id = g.id
            WHERE ep.event_id = ? AND ep.edu_student_id IS NOT NULL
            ORDER BY es.surname, es.name");
        $stmtSt->execute([$eventId]);
        $studentParticipants = $stmtSt->fetchAll();
    }
} catch (Exception $e) {}

$all = array_merge($studentParticipants, $userParticipants);

if (empty($all)) {
    echo '<p style="color:var(--color-text-muted,#64748b);text-align:center;padding:1rem 0">Участников пока нет.</p>';
    exit;
}

echo '<table style="width:100%;border-collapse:collapse">';
echo '<thead><tr style="background:var(--color-surface-2,#f8fafc)">
  <th style="padding:.5rem .75rem;font-size:.72rem;font-weight:600;text-transform:uppercase;
             color:var(--color-text-muted,#64748b);text-align:left;
             border-bottom:1px solid var(--color-border,#cbd5e1)">ФИО</th>
  <th style="padding:.5rem .75rem;font-size:.72rem;font-weight:600;text-transform:uppercase;
             color:var(--color-text-muted,#64748b);text-align:left;
             border-bottom:1px solid var(--color-border,#cbd5e1)">Тип / Группа</th>
  <th style="padding:.5rem .75rem;font-size:.72rem;font-weight:600;text-transform:uppercase;
             color:var(--color-text-muted,#64748b);text-align:left;
             border-bottom:1px solid var(--color-border,#cbd5e1)">Роль в мероприятии</th>
</tr></thead><tbody>';

foreach ($all as $p) {
    $isStudent = ($p['ptype'] === 'student');
    $color     = $isStudent ? '#16a34a' : '#2563eb';
    $bg        = $isStudent ? '#dcfce7' : '#dbeafe';
    $typeLabel = $isStudent
        ? ('Студент' . ($p['group_name'] ? ' · ' . $p['group_name'] : ''))
        : 'Преподаватель/сотрудник';

    echo '<tr style="border-bottom:1px solid var(--color-border,#cbd5e1)">
      <td style="padding:.5rem .75rem;font-size:.8125rem;font-weight:600">'
        . htmlspecialchars($p['full_name']) . '</td>
      <td style="padding:.5rem .75rem">
        <span style="display:inline-flex;padding:2px 8px;border-radius:9999px;
                     font-size:.72rem;font-weight:600;background:' . $bg . ';color:' . $color . '">'
          . htmlspecialchars($typeLabel) . '</span>
      </td>
      <td style="padding:.5rem .75rem;font-size:.8125rem">'
        . htmlspecialchars($p['role_event'] ?? '—') . '</td>
    </tr>';
}
echo '</tbody></table>';