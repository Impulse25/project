<?php
/**
 * СВГТК Портал — Модуль «Учёт посещаемости»
 * Тема 2: «Разработка модуля «Учёт посещаемости» с формированием отчётности»
 *
 * Файл: attendance/index.php  ← точка входа, только логика
 * Зависимости: style.css, layout.php, tabs.php, app.js
 */

// ── Показываем ВСЕ ошибки (убрать на проде) ──────────────────────────────
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ловим фатальные ошибки которые обрезают HTML
ob_start();
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo '<div style="position:fixed;bottom:0;left:0;right:0;background:#7f1d1d;color:#fca5a5;font-family:monospace;font-size:13px;padding:16px;z-index:9999;white-space:pre-wrap">';
        echo '🔴 PHP FATAL: ' . htmlspecialchars($err['message']);
        echo "\nФайл: " . htmlspecialchars($err['file']) . ' строка ' . $err['line'];
        echo '</div>';
    }
    ob_end_flush();
});

session_start();

// ── Подключение API ядра (предоставляет руководитель) ─────────────────────────
// require_once '../core_api.php';
// checkAuth();
// $user = getCurrentUser();
// $pdo  = getDbConnection();

// ── Читаем пользователя из сессии ────────────────────────────────────────────
// Если сессии нет — демо-режим (убрать на проде)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id']   = 39;
    $_SESSION['role']      = 'admin';
    $_SESSION['full_name'] = 'Бубнов Андрей Валерьевич';
}
$user = [
    'id'        => (int)$_SESSION['user_id'],
    'full_name' => $_SESSION['full_name'] ?? '',
    'role'      => $_SESSION['role']      ?? 'teacher',
];

$userRole  = $user['role'];
$userName  = $user['full_name'];
$isAdmin   = in_array($userRole, ['admin', 'director']);
$isTeacher = ($userRole === 'teacher');
$nameParts = explode(' ', trim($userName));
$initials  = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($nameParts, 0, 2)));

// ── Параметры запроса ─────────────────────────────────────────────────────────
$activeTab    = $_GET['tab']    ?? 'journal';
$rawDate      = $_GET['date']   ?? date('Y-m-d');
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : date('Y-m-d');
$reportPeriod = $_GET['period'] ?? 'month';
$rawMonth     = $_GET['month']  ?? date('Y-m');
$reportMonth  = preg_match('/^\d{4}-\d{2}$/', $rawMonth) ? $rawMonth : date('Y-m');

// ── Подключение к БД ─────────────────────────────────────────────────────────
$pdo = new PDO(
    'mysql:host=localhost;dbname=p-355792_svgtk;charset=utf8mb4',
    'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// ── Группы: admin видит все, teacher — только свои (curator_id) ───────────────
if ($isAdmin) {
    $stmtGrp = $pdo->query("
        SELECT g.id, g.name, g.curator_id,
               COALESCE(sp.name_ru, '') AS specialty,
               COALESCE(u.full_name, '') AS curator_name
        FROM edu_groups g
        LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
        LEFT JOIN users u ON u.id = g.curator_id
        ORDER BY g.name
    ");
    $groups = [];
    foreach ($stmtGrp->fetchAll() as $g) $groups[$g['id']] = $g;
} else {
    // teacher: только группы где curator_id = его user_id
    $stmtGrp = $pdo->prepare("
        SELECT g.id, g.name, g.curator_id,
               COALESCE(sp.name_ru, '') AS specialty,
               COALESCE(u.full_name, '') AS curator_name
        FROM edu_groups g
        LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
        LEFT JOIN users u ON u.id = g.curator_id
        WHERE g.curator_id = :uid
        ORDER BY g.name
    ");
    $stmtGrp->execute([':uid' => $user['id']]);
    $groups = [];
    foreach ($stmtGrp->fetchAll() as $g) $groups[$g['id']] = $g;
}

$noGroupsWarning = (!$isAdmin && empty($groups));

// Выбранная группа: если teacher и запрошенная группа не его — берём первую свою
$selectedGrp = (int)($_GET['group'] ?? 0);
if (!isset($groups[$selectedGrp])) {
    $first       = reset($groups);
    $selectedGrp = $first ? (int)$first['id'] : 0;
}

// ── Причины отсутствия из БД ─────────────────────────────────────────────────
$stmt = $pdo->query("SELECT * FROM att_absence_reasons ORDER BY id");
$reasons = [];
foreach ($stmt->fetchAll() as $r) {
    $reasons[$r['id']] = $r;
}

// ── Студенты выбранной группы из БД ──────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.id, s.iin,
           CONCAT(s.surname, ' ', s.name, ' ', s.patronymic) AS full_name,
           s.surname, s.name AS first_name, s.patronymic,
           g.name AS group_name,
           COALESCE(a.status, 'present')  AS status,
           COALESCE(a.hours_missed, 0)    AS hours_missed,
           a.reason_id
    FROM edu_students s
    LEFT JOIN edu_groups g ON g.id = s.group_id
    LEFT JOIN att_attendance a
           ON a.student_id = s.id AND a.date = :date
    WHERE s.group_id = :group_id
    ORDER BY s.surname, s.name, s.patronymic
");
$stmt->execute([':date' => $selectedDate, ':group_id' => $selectedGrp]);
$students = $stmt->fetchAll();

// ── Данные текущей группы ─────────────────────────────────────────────────────
$groupInfo = $groups[$selectedGrp] ?? ($groups ? reset($groups) : ['name'=>'—','specialty'=>'','curator_name'=>'','curator_id'=>null]);

// Статистика за день
$total   = count($students);
$present = count(array_filter($students, fn($s) => $s['status'] === 'present'));
$absent  = count(array_filter($students, fn($s) => $s['status'] === 'absent'));
$excused = count(array_filter($students, fn($s) => $s['status'] === 'excused'));
$late    = count(array_filter($students, fn($s) => $s['status'] === 'late'));
$pct     = $total > 0 ? round($present / $total * 100) : 0;

// ── Рапортичка: вычисляем диапазон дат по периоду ───────────────────────
$daysInMonth = (int)date('t', strtotime($reportMonth . '-01'));
$today_d     = (date('Y-m') === $reportMonth) ? (int)date('d') : $daysInMonth;

[$rapYear, $rapMonthNum] = array_map('intval', explode('-', $reportMonth));

switch ($reportPeriod) {
    case 'week':
        $refDay     = (date('Y-m') === $reportMonth) ? (int)date('d') : $daysInMonth;
        $refTs      = mktime(0,0,0,$rapMonthNum,$refDay,$rapYear);
        $dow        = (int)date('N', $refTs);
        $rapDateFrom = date('Y-m-d', $refTs - ($dow-1)*86400);
        $rapDateTo   = date('Y-m-d', $refTs + (7-$dow)*86400);
        $rapDateFrom = max($rapDateFrom, "$rapYear-" . sprintf('%02d',$rapMonthNum) . '-01');
        $rapDateTo   = min($rapDateTo,   "$rapYear-" . sprintf('%02d',$rapMonthNum) . "-$daysInMonth");
        break;

    case 'semester':
        if ($rapMonthNum >= 9) {
            $rapDateFrom = "$rapYear-09-01";
            $rapDateTo   = ($rapYear+1) . "-01-31";
        } else {
            $rapDateFrom = "$rapYear-02-01";
            $rapDateTo   = "$rapYear-06-30";
        }
        break;

    case 'year':
        // Учебный год: сентябрь текущего или прошлого года — по сегодня
        $yearStart   = ($rapMonthNum >= 9) ? $rapYear : $rapYear - 1;
        $rapDateFrom = $yearStart . "-09-01";
        $rapDateTo   = ($yearStart + 1) . "-08-31";
        break;

    default: // month
        $rapDateFrom = "$rapYear-" . sprintf('%02d',$rapMonthNum) . '-01';
        $rapDateTo   = "$rapYear-" . sprintf('%02d',$rapMonthNum) . "-$daysInMonth";
}

$todayStr  = date('Y-m-d');
$rapDateTo = min($rapDateTo, $todayStr);

if ($rapDateFrom > $rapDateTo) {
    $rapDateFrom = date('Y-m-01');
    $rapDateTo   = $todayStr;
}

// Список дат внутри диапазона (для колонок таблицы)
$rapDates = [];
$cursor   = strtotime($rapDateFrom);
$endTs    = strtotime($rapDateTo);
while ($cursor <= $endTs) {
    $rapDates[] = date('Y-m-d', $cursor);
    $cursor += 86400;
}

// ── Загрузка посещаемости из БД ──────────────────────────────────────────
$rapData = [];
if (!empty($rapDates)) {
    $placeholders = implode(',', array_fill(0, count($rapDates), '?'));
    $stmtRap = $pdo->prepare("
        SELECT a.student_id,
               a.date,
               a.status,
               a.hours_missed
        FROM att_attendance a
        INNER JOIN edu_students s ON s.id = a.student_id
        WHERE s.group_id = ?
          AND a.date IN ($placeholders)
    ");
    $stmtRap->execute(array_merge([(int)$selectedGrp], $rapDates));
    foreach ($stmtRap->fetchAll() as $row) {
        $rapData[$row['student_id']][$row['date']] = [
            'status' => $row['status'],
            'hours'  => (int)$row['hours_missed'],
        ];
    }
}

// ── Итоги по каждому студенту ─────────────────────────────────────────────
$rapTotals = [];
foreach ($students as $st) {
    $sid = $st['id'];
    $abH = 0; $exH = 0; $ltH = 0; $days = 0;
    foreach ($rapDates as $dt) {
        $dow = (int)date('N', strtotime($dt));
        if ($dow >= 6) continue;
        $days++;
        if (!isset($rapData[$sid][$dt])) continue;
        $rec = $rapData[$sid][$dt];
        if ($rec['status'] === 'absent')  $abH += $rec['hours'];
        if ($rec['status'] === 'excused') $exH += $rec['hours'];
        if ($rec['status'] === 'late')    $ltH += $rec['hours'];
    }
    $maxH    = $days * 6;
    $stPctR  = $maxH > 0 ? max(0, round((1 - $abH / $maxH) * 100)) : 100;
    $rapTotals[$sid] = ['absent_h' => $abH, 'excused_h' => $exH, 'late_h' => $ltH, 'pct' => $stPctR];
}

// ── Итоговая строка по группе ─────────────────────────────────────────────
$rapGroupAbsH   = array_sum(array_column($rapTotals, 'absent_h'));
$rapGroupExcH   = array_sum(array_column($rapTotals, 'excused_h'));
$rapGroupLateH  = array_sum(array_column($rapTotals, 'late_h'));
$rapGroupAvgPct = count($rapTotals) > 0 ? round(array_sum(array_column($rapTotals,'pct')) / count($rapTotals)) : 100;

// ── Справки: загрузка из БД ───────────────────────────────────────────────
$docsTableExists = false;
try {
    $pdo->query("SELECT 1 FROM att_documents LIMIT 1");
    $docsTableExists = true;
} catch (PDOException $e) { /* таблица не создана */ }

$documents  = [];
$docsPending = 0;
if ($docsTableExists) {
    $stmtDocs = $pdo->prepare("
        SELECT d.*,
               s.surname, s.name AS first_name, s.patronymic,
               r.name_ru AS reason_name
        FROM att_documents d
        INNER JOIN edu_students s ON s.id = d.student_id
        LEFT JOIN  att_absence_reasons r ON r.id = d.reason_id
        WHERE d.group_id = :gid
        ORDER BY d.created_at DESC
        LIMIT 100
    ");
    $stmtDocs->execute([':gid' => $selectedGrp]);
    $documents   = $stmtDocs->fetchAll();
    $docsPending = count(array_filter($documents, fn($d) => $d['status'] === 'pending'));
}

// ── АНАЛИТИКА: загрузка данных из БД ─────────────────────────────────────
$anPeriod    = $_GET['an_period'] ?? 'month';
$anMonth     = preg_match('/^\d{4}-\d{2}$/', $_GET['an_month'] ?? '') ? $_GET['an_month'] : date('Y-m');
[$anY, $anM] = array_map('intval', explode('-', $anMonth));

if ($anPeriod === 'year') {
    $anYear    = $anM >= 9 ? $anY : $anY - 1;
    $anDateFrom = $anYear    . '-09-01';
    $anDateTo   = ($anYear+1) . '-08-31';
} else {
    $anDateFrom = "$anY-" . sprintf('%02d', $anM) . '-01';
    $anDateTo   = "$anY-" . sprintf('%02d', $anM) . '-' . date('t', mktime(0,0,0,$anM,1,$anY));
}
$anDateTo = min($anDateTo, date('Y-m-d'));

$anGroupIds = $isAdmin ? array_keys($groups) : array_keys($groups);
if (empty($anGroupIds)) $anGroupIds = [0];
$anIn = implode(',', array_map('intval', $anGroupIds));

// ── 1. Посещаемость по группам ───────────────────────────────────────────
$anGroups = $pdo->query("
    SELECT
        g.id,
        g.name                          AS group_name,
        COALESCE(sp.name_ru,'')         AS specialty,
        COUNT(DISTINCT s.id)            AS total_students,
        COUNT(DISTINCT CASE WHEN a.status='absent'  THEN a.id END) AS absent_records,
        COUNT(DISTINCT CASE WHEN a.status='excused' THEN a.id END) AS excused_records,
        COUNT(DISTINCT CASE WHEN a.status='late'    THEN a.id END) AS late_records,
        COALESCE(SUM(CASE WHEN a.status='absent'  THEN a.hours_missed ELSE 0 END),0) AS absent_hours,
        COALESCE(SUM(CASE WHEN a.status='excused' THEN a.hours_missed ELSE 0 END),0) AS excused_hours,
        COALESCE(u.full_name, '')        AS curator_name
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    LEFT JOIN edu_students s     ON s.group_id = g.id
    LEFT JOIN att_attendance a   ON a.student_id = s.id
                                AND a.date BETWEEN '$anDateFrom' AND '$anDateTo'
    LEFT JOIN users u            ON u.id = g.curator_id
    WHERE g.id IN ($anIn)
    GROUP BY g.id, g.name, sp.name_ru, u.full_name
    ORDER BY g.name
")->fetchAll();

$workDaysAn = 0;
$cur = strtotime($anDateFrom);
while ($cur <= strtotime($anDateTo)) {
    if ((int)date('N',$cur) <= 5) $workDaysAn++;
    $cur += 86400;
}
foreach ($anGroups as &$ag) {
    $maxH = max(1, $ag['total_students'] * $workDaysAn * 6);
    $ag['pct'] = max(0, round((1 - $ag['absent_hours'] / $maxH) * 100));
}
unset($ag);
usort($anGroups, fn($a,$b) => $a['pct'] - $b['pct']);

// ── 2. Студенты с пропусками (топ 30) ───────────────────────────────────
$anRiskStudents = $pdo->prepare("
    SELECT
        s.id, s.surname, s.name AS first_name, s.patronymic,
        g.name AS group_name,
        COALESCE(SUM(CASE WHEN a.status='absent'  THEN a.hours_missed ELSE 0 END),0) AS absent_h,
        COALESCE(SUM(CASE WHEN a.status='excused' THEN a.hours_missed ELSE 0 END),0) AS excused_h,
        COALESCE(SUM(CASE WHEN a.status='late'    THEN a.hours_missed ELSE 0 END),0) AS late_h,
        COUNT(DISTINCT CASE WHEN a.status IN ('absent','late') THEN a.date END) AS missed_days
    FROM edu_students s
    INNER JOIN edu_groups g ON g.id = s.group_id
    LEFT JOIN att_attendance a ON a.student_id = s.id
                               AND a.date BETWEEN :df AND :dt
    WHERE g.id IN ($anIn)
    GROUP BY s.id, s.surname, s.name, s.patronymic, g.name
    HAVING absent_h > 0
    ORDER BY absent_h DESC
    LIMIT 30
");
$anRiskStudents->execute([':df'=>$anDateFrom,':dt'=>$anDateTo]);
$anRiskStudents = $anRiskStudents->fetchAll();

$maxHperStudent = max(1, $workDaysAn * 6);
foreach ($anRiskStudents as &$rs) {
    $rs['pct'] = max(0, round((1 - $rs['absent_h'] / $maxHperStudent) * 100));
}
unset($rs);

// ── 3. Динамика по дням (текущая группа) ────────────────────────────────
$anTrend = $pdo->prepare("
    SELECT
        a.date,
        COUNT(DISTINCT s.id)  AS total,
        SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN a.status='absent'  THEN 1 ELSE 0 END) AS absent,
        SUM(CASE WHEN a.status='excused' THEN 1 ELSE 0 END) AS excused,
        SUM(CASE WHEN a.status='late'    THEN 1 ELSE 0 END) AS late
    FROM att_attendance a
    INNER JOIN edu_students s ON s.id = a.student_id
    WHERE s.group_id = :gid
      AND a.date BETWEEN :df AND :dt
    GROUP BY a.date
    ORDER BY a.date ASC
");
$anTrend->execute([':gid'=>$selectedGrp, ':df'=>$anDateFrom, ':dt'=>$anDateTo]);
$anTrend = $anTrend->fetchAll();

// ── 4. Пропуски по причинам ──────────────────────────────────────────────
$anReasons = $pdo->prepare("
    SELECT
        COALESCE(r.name_ru, 'Без причины') AS reason_name,
        COUNT(*) AS cnt,
        SUM(a.hours_missed) AS hours
    FROM att_attendance a
    LEFT JOIN att_absence_reasons r ON r.id = a.reason_id
    INNER JOIN edu_students s ON s.id = a.student_id
    WHERE s.group_id IN ($anIn)
      AND a.status IN ('absent','excused')
      AND a.date BETWEEN :df AND :dt
    GROUP BY r.id, r.name_ru
    ORDER BY hours DESC
");
$anReasons->execute([':df'=>$anDateFrom,':dt'=>$anDateTo]);
$anReasons = $anReasons->fetchAll();
$anReasonsTotal = max(1, array_sum(array_column($anReasons, 'hours')));

// ── 5. Сводные KPI для аналитики ────────────────────────────────────────
$anTotalStudents = array_sum(array_column($anGroups, 'total_students'));
$anTotalAbsH     = array_sum(array_column($anGroups, 'absent_hours'));
$anTotalExcH     = array_sum(array_column($anGroups, 'excused_hours'));
$anAvgPct        = count($anGroups) > 0
    ? round(array_sum(array_column($anGroups,'pct')) / count($anGroups))
    : 100;
$anRiskCount     = count(array_filter($anGroups, fn($g) => $g['pct'] < 75));

// ── Рендер страницы ──────────────────────────────────────────────────────
require_once 'layout.php';
