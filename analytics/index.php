<?php
/**
 * СВГТК Портал — модуль «Аналитика и отчётность»
 * Подключён к реальным таблицам портала:
 * edu_groups, edu_students, users, edu_grades, edu_grade_sheets, att_attendance.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../requests/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRoleRaw = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? '';
$userName = trim((string)($_SESSION['full_name'] ?? ''));

require_once __DIR__ . '/includes/helpers.php';

$allowedGroupIds = an_accessible_group_ids($pdo, $userId, $role);
$inGroups = an_in($allowedGroupIds);

$period = $_GET['period'] ?? 'month';
if (!in_array($period, ['week', 'month', 'semester', 'year', 'custom'], true)) $period = 'month';
[$dateFrom, $dateTo] = an_date_range($period, $_GET['date_from'] ?? '', $_GET['date_to'] ?? '');

$section = $_GET['section'] ?? 'dashboard';
if (!in_array($section, ['dashboard', 'grades', 'attendance', 'graduates', 'report', 'help'], true)) $section = 'dashboard';

$criterion = $_GET['criterion'] ?? 'all';
$sub = $_GET['sub'] ?? 'all';
$groupId = (int)($_GET['group_id'] ?? 0);
if ($groupId > 0 && !in_array($groupId, $allowedGroupIds, true)) $groupId = 0;
$topMode = $_GET['top_mode'] ?? 'attendance_low';
$topNMode = $_GET['top_n'] ?? '5';
$topCustom = max(1, min(100, (int)($_GET['top_custom'] ?? 5)));
$topN = in_array($topNMode, ['3','5','10'], true) ? (int)$topNMode : $topCustom;

$groups = [];
if ($allowedGroupIds) {
    $stmt = $pdo->query("
        SELECT g.id, g.name,
               COUNT(s.id) AS students_count
        FROM edu_groups g
        LEFT JOIN edu_students s ON s.group_id = g.id
        WHERE g.id IN ($inGroups)
        GROUP BY g.id, g.name
        ORDER BY g.name
    ");
    $groups = $stmt->fetchAll();
}

$totalStudents = 0;
foreach ($groups as $g) $totalStudents += (int)$g['students_count'];

$hasGrades = an_table_exists($pdo, 'edu_grades') && an_table_exists($pdo, 'edu_grade_sheets');
$hasAttendance = an_table_exists($pdo, 'att_attendance');

$avgGrade = null; $excellentCount = 0; $riskGrades = 0;
if ($hasGrades && $allowedGroupIds) {
    $where = "s.group_id IN ($inGroups) AND eg.grade IS NOT NULL AND (gs.status IS NULL OR gs.status <> 'rejected')";
    $params = [];
    if (an_column_exists($pdo, 'edu_grades', 'updated_at')) {
        $where .= " AND DATE(COALESCE(eg.updated_at, eg.created_at, NOW())) BETWEEN ? AND ?";
        $params[] = $dateFrom; $params[] = $dateTo;
    }
    $stmt = $pdo->prepare("
        SELECT AVG(eg.grade) AS avg_grade,
               SUM(CASE WHEN eg.grade >= 90 THEN 1 ELSE 0 END) AS excellent_count,
               SUM(CASE WHEN eg.grade <= 50 THEN 1 ELSE 0 END) AS risk_count
        FROM edu_grades eg
        JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id
        JOIN edu_students s ON s.id = eg.student_id
        WHERE $where
    ");
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];
    $avgGrade = $row['avg_grade'] !== null ? round((float)$row['avg_grade'], 1) : null;
    $excellentCount = (int)($row['excellent_count'] ?? 0);
    $riskGrades = (int)($row['risk_count'] ?? 0);
}

$attendanceAvg = null; $absenceHours = 0;
if ($hasAttendance && $allowedGroupIds) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN a.status IN ('absent','late') THEN a.hours_missed ELSE 0 END), 0) AS absent_hours,
               COUNT(DISTINCT s.id) AS st_count
        FROM edu_students s
        LEFT JOIN att_attendance a ON a.student_id = s.id AND a.date BETWEEN ? AND ?
        WHERE s.group_id IN ($inGroups)
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $row = $stmt->fetch() ?: [];
    $absenceHours = (int)($row['absent_hours'] ?? 0);
    $days = max(1, (int)round((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1);
    $plannedHours = max(1, (int)($row['st_count'] ?? 0) * $days * 6);
    $attendanceAvg = round(max(0, ($plannedHours - $absenceHours) / $plannedHours * 100), 1);
}

$graduates = 0;
foreach ($groups as $g) if (an_group_is_graduate((string)$g['name'])) $graduates += (int)$g['students_count'];

$groupRows = [];
if ($allowedGroupIds) {
    $gradeJoin = $hasGrades ? "
        LEFT JOIN edu_grades eg ON eg.student_id = s.id
        LEFT JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id AND (gs.status IS NULL OR gs.status <> 'rejected')
    " : "";
    $attJoin = $hasAttendance ? "
        LEFT JOIN att_attendance a ON a.student_id = s.id AND a.date BETWEEN :df AND :dt
    " : "";
    $sql = "
        SELECT g.id, g.name,
               COUNT(DISTINCT s.id) AS students_count
               " . ($hasGrades ? ", AVG(eg.grade) AS avg_grade" : ", NULL AS avg_grade") . "
               " . ($hasAttendance ? ", COALESCE(SUM(CASE WHEN a.status IN ('absent','late') THEN a.hours_missed ELSE 0 END),0) AS absent_hours" : ", 0 AS absent_hours") . "
        FROM edu_groups g
        LEFT JOIN edu_students s ON s.group_id = g.id
        $gradeJoin
        $attJoin
        WHERE g.id IN ($inGroups)
        GROUP BY g.id, g.name
        ORDER BY g.name
    ";
    $stmt = $pdo->prepare($sql);
    $hasAttendance ? $stmt->execute([':df' => $dateFrom, ':dt' => $dateTo]) : $stmt->execute();
    foreach ($stmt->fetchAll() as $r) {
        $course = an_course_from_group((string)$r['name']);
        $days = max(1, (int)round((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1);
        $plan = max(1, (int)$r['students_count'] * $days * 6);
        $attPct = $hasAttendance ? round(max(0, ($plan - (int)$r['absent_hours']) / $plan * 100), 1) : null;
        $avg = $r['avg_grade'] !== null ? round((float)$r['avg_grade'], 1) : null;
        $status = 'Стабильная';
        if (($avg !== null && $avg < 60) || ($attPct !== null && $attPct < 65)) $status = 'Проблемная';
        elseif (($avg !== null && $avg < 70) || ($attPct !== null && $attPct < 75)) $status = 'Требует контроля';
        $groupRows[] = [
            'id' => (int)$r['id'],
            'name' => (string)$r['name'],
            'course' => $course,
            'students_count' => (int)$r['students_count'],
            'avg_grade' => $avg,
            'attendance' => $attPct,
            'graduates' => an_group_is_graduate((string)$r['name']) ? (int)$r['students_count'] : 0,
            'status' => $status,
        ];
    }
}

$filteredRows = array_values(array_filter($groupRows, function($r) use ($criterion, $sub, $groupId) {
    if ($groupId > 0 && (int)$r['id'] !== $groupId) return false;
    if ($criterion === 'status' && $sub !== 'all') return $r['status'] === $sub;
    if ($criterion === 'course' && $sub !== 'all') return $r['course'] === $sub;
    if ($criterion === 'graduates') return $r['graduates'] > 0;
    if ($criterion === 'grade') {
        if ($sub === 'excellent') return $r['avg_grade'] !== null && $r['avg_grade'] >= 90;
        if ($sub === 'good') return $r['avg_grade'] !== null && $r['avg_grade'] >= 70 && $r['avg_grade'] < 90;
        if ($sub === 'risk') return $r['avg_grade'] !== null && $r['avg_grade'] < 60;
    }
    if ($criterion === 'attendance') {
        if ($sub === 'high') return $r['attendance'] !== null && $r['attendance'] >= 85;
        if ($sub === 'control') return $r['attendance'] !== null && $r['attendance'] >= 65 && $r['attendance'] < 75;
        if ($sub === 'low') return $r['attendance'] !== null && $r['attendance'] < 65;
    }
    return true;
}));

$topRows = [];
if ($allowedGroupIds && in_array($section, ['grades', 'attendance', 'report'], true)) {
    if (str_starts_with($topMode, 'attendance') && $hasAttendance) {
        $dir = $topMode === 'attendance_high' ? 'DESC' : 'ASC';
        $sql = "
            SELECT s.id, CONCAT(s.surname,' ',s.name, IF(s.patronymic IS NULL OR s.patronymic='', '', CONCAT(' ',s.patronymic))) AS full_name,
                   g.name AS group_name,
                   COALESCE(SUM(CASE WHEN a.status IN ('absent','late') THEN a.hours_missed ELSE 0 END),0) AS absent_hours
            FROM edu_students s
            JOIN edu_groups g ON g.id = s.group_id
            LEFT JOIN att_attendance a ON a.student_id = s.id AND a.date BETWEEN :df AND :dt
            WHERE s.group_id IN ($inGroups) " . ($groupId > 0 ? "AND g.id = :gid" : "") . "
            GROUP BY s.id, s.surname, s.name, s.patronymic, g.name
        ";
        $stmt = $pdo->prepare($sql);
        $params = [':df' => $dateFrom, ':dt' => $dateTo];
        if ($groupId > 0) $params[':gid'] = $groupId;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $days = max(1, (int)round((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1);
        foreach ($rows as &$r) {
            $plan = max(1, $days * 6);
            $r['value'] = round(max(0, ($plan - (int)$r['absent_hours']) / $plan * 100), 1);
            $r['value_title'] = $r['value'] . '% посещаемости';
        }
        usort($rows, fn($a, $b) => $dir === 'ASC' ? ($a['value'] <=> $b['value']) : ($b['value'] <=> $a['value']));
        $topRows = array_slice($rows, 0, $topN);
    } elseif (str_starts_with($topMode, 'grades') && $hasGrades) {
        $dir = $topMode === 'grades_best' ? 'DESC' : 'ASC';
        $sql = "
            SELECT s.id, CONCAT(s.surname,' ',s.name, IF(s.patronymic IS NULL OR s.patronymic='', '', CONCAT(' ',s.patronymic))) AS full_name,
                   g.name AS group_name,
                   AVG(eg.grade) AS avg_grade
            FROM edu_students s
            JOIN edu_groups g ON g.id = s.group_id
            JOIN edu_grades eg ON eg.student_id = s.id
            JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id
            WHERE s.group_id IN ($inGroups) AND eg.grade IS NOT NULL AND (gs.status IS NULL OR gs.status <> 'rejected')
              " . ($groupId > 0 ? "AND g.id = :gid" : "") . "
            GROUP BY s.id, s.surname, s.name, s.patronymic, g.name
            ORDER BY avg_grade $dir
            LIMIT $topN
        ";
        $stmt = $pdo->prepare($sql);
        $params = [];
        if ($groupId > 0) $params[':gid'] = $groupId;
        $stmt->execute($params);
        $topRows = $stmt->fetchAll();
        foreach ($topRows as &$r) {
            $r['value'] = round((float)$r['avg_grade'], 1);
            $r['value_title'] = $r['value'] . ' средний балл';
        }
    }
}

$periodTitles = ['week'=>'неделя','month'=>'месяц','semester'=>'семестр','year'=>'год','custom'=>'свой срок'];
$roleTitle = ['admin'=>'Администратор','director'=>'Директор','teacher'=>'Преподаватель'][$role] ?? 'Пользователь';
?>
<!doctype html>
<html lang="ru" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Аналитика и отчётность — СВГТК Портал</title>
<style>
:root{
  --bg:#f4f7fb;--panel:#fff;--panel2:#f8fafc;--text:#172033;--muted:#607089;--line:#d8e0ea;
  --primary:#1d4ed8;--primary2:#e7efff;--good:#138a43;--warn:#b7791f;--bad:#c53030;
  --shadow:0 8px 28px rgba(20,35,60,.08);--radius:16px
}
[data-theme="dark"]{--bg:#111827;--panel:#172033;--panel2:#1f2b43;--text:#f1f5f9;--muted:#a9b7cc;--line:#31415d;--primary:#60a5fa;--primary2:#1e3a5f;--good:#4ade80;--warn:#fbbf24;--bad:#f87171;--shadow:0 8px 28px rgba(0,0,0,.22)}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Arial,system-ui,sans-serif;font-size:15px}.wrap{max-width:1280px;margin:0 auto;padding:24px}.top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.brand h1{margin:0;font-size:30px}.brand p{margin:6px 0 0;color:var(--muted)}.user{display:flex;gap:10px;align-items:center}.avatar{width:42px;height:42px;border-radius:50%;background:var(--primary2);display:grid;place-items:center;color:var(--primary);font-weight:700}.theme{border:1px solid var(--line);background:var(--panel);color:var(--text);border-radius:10px;padding:10px 14px;cursor:pointer}.nav{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0 20px}.nav a{color:var(--text);text-decoration:none;background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:11px 14px}.nav a.active{background:var(--primary);border-color:var(--primary);color:#fff}.grid{display:grid;gap:16px}.kpi{grid-template-columns:repeat(4,minmax(0,1fr))}.card{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px}.card h3{margin:0 0 10px;font-size:17px}.kpi .num{font-size:32px;font-weight:800;margin:8px 0}.muted{color:var(--muted)}.filters{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;align-items:end}.field label{display:block;color:var(--muted);font-size:13px;margin:0 0 6px}.field select,.field input{width:100%;border:1px solid var(--line);background:var(--panel2);color:var(--text);border-radius:10px;padding:10px}.btn{border:0;background:var(--primary);color:white;border-radius:10px;padding:11px 16px;cursor:pointer;text-decoration:none;display:inline-block}.btn.light{background:var(--panel2);color:var(--text);border:1px solid var(--line)}.two{grid-template-columns:1.1fr .9fr}.three{grid-template-columns:repeat(3,minmax(0,1fr))}.bar{height:12px;background:var(--panel2);border:1px solid var(--line);border-radius:999px;overflow:hidden}.fill{height:100%;background:var(--primary)}table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid var(--line);text-align:left}th{color:var(--muted);font-weight:700;background:var(--panel2)}.badge{display:inline-block;padding:5px 9px;border-radius:999px;border:1px solid var(--line);font-size:13px}.badge.good{color:var(--good);background:rgba(19,138,67,.10)}.badge.warn{color:var(--warn);background:rgba(183,121,31,.12)}.badge.bad{color:var(--bad);background:rgba(197,48,48,.10)}.toplist{display:grid;grid-template-columns:1fr 1fr;gap:12px}.topitem{border:1px solid var(--line);background:var(--panel2);border-radius:14px;padding:14px;display:flex;justify-content:space-between;gap:10px}.rank{font-weight:800;color:var(--primary);font-size:20px}.help details{border:1px solid var(--line);border-radius:12px;background:var(--panel2);padding:12px;margin-bottom:10px}.help summary{font-weight:700;cursor:pointer}.print-title{display:none}@media(max-width:900px){.kpi,.two,.three,.filters{grid-template-columns:1fr}.toplist{grid-template-columns:1fr}}@media print{.nav,.theme,.filters,.btn,.no-print{display:none!important}body{background:white;color:black}.card{box-shadow:none;border:1px solid #bbb}.print-title{display:block}.wrap{max-width:100%;padding:0}}
</style>
</head>
<body>
<div class="wrap" id="printArea">
  <div class="top">
    <div class="brand">
      <h1>Аналитика и отчётность</h1>
      <p>Модуль информационной системы «СВГТК Портал»</p>
    </div>
    <div class="user no-print">
      <button class="theme" id="themeBtn">Сменить тему</button>
      <div class="avatar"><?= h(mb_substr($userName ?: $roleTitle,0,1,'UTF-8')) ?></div>
      <div><b><?= h($userName ?: $roleTitle) ?></b><br><span class="muted"><?= h($roleTitle) ?></span></div>
    </div>
  </div>

  <div class="nav no-print">
    <?php foreach (['dashboard'=>'Dashboard','grades'=>'Успеваемость','attendance'=>'Посещаемость','graduates'=>'Выпуск','report'=>'Итоговый отчёт','help'=>'Справка'] as $key=>$title): ?>
      <a class="<?= $section===$key?'active':'' ?>" href="?section=<?= h($key) ?>"><?= h($title) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$allowedGroupIds): ?>
    <div class="card"><h3>Нет доступных групп</h3><p class="muted">Для текущего пользователя не найдены группы. Проверьте роль, куратора группы или права заведующего отделением.</p></div>
  <?php else: ?>

  <div class="grid kpi">
    <div class="card"><div class="muted">Студенты</div><div class="num"><?= (int)$totalStudents ?></div><div class="muted">по доступным группам</div></div>
    <div class="card"><div class="muted">Средний балл</div><div class="num"><?= $avgGrade !== null ? h($avgGrade) : '—' ?></div><div class="muted"><?= $hasGrades ? 'по ведомостям' : 'таблица оценок не найдена' ?></div></div>
    <div class="card"><div class="muted">Посещаемость</div><div class="num"><?= $attendanceAvg !== null ? h($attendanceAvg).'%' : '—' ?></div><div class="muted"><?= h($periodTitles[$period] ?? '') ?></div></div>
    <div class="card"><div class="muted">Выпускники</div><div class="num"><?= (int)$graduates ?></div><div class="muted">по выпускным группам</div></div>
  </div>

  <?php if ($section !== 'help'): ?>
  <form method="get" class="card no-print" style="margin-top:16px">
    <input type="hidden" name="section" value="<?= h($section) ?>">
    <div class="filters">
      <div class="field"><label>Критерий</label><select name="criterion" id="criterion">
        <option value="all" <?= $criterion==='all'?'selected':'' ?>>Все данные</option>
        <option value="status" <?= $criterion==='status'?'selected':'' ?>>Статус группы</option>
        <option value="course" <?= $criterion==='course'?'selected':'' ?>>Курс</option>
        <option value="grade" <?= $criterion==='grade'?'selected':'' ?>>Успеваемость</option>
        <option value="attendance" <?= $criterion==='attendance'?'selected':'' ?>>Посещаемость</option>
        <option value="graduates" <?= $criterion==='graduates'?'selected':'' ?>>Выпуск</option>
      </select></div>
      <div class="field"><label>Подвыбор</label><select name="sub">
        <option value="all">Все</option>
        <option value="Стабильная" <?= $sub==='Стабильная'?'selected':'' ?>>Стабильные группы</option>
        <option value="Требует контроля" <?= $sub==='Требует контроля'?'selected':'' ?>>Требуют контроля</option>
        <option value="Проблемная" <?= $sub==='Проблемная'?'selected':'' ?>>Проблемные</option>
        <option value="1 курс" <?= $sub==='1 курс'?'selected':'' ?>>1 курс</option>
        <option value="2 курс" <?= $sub==='2 курс'?'selected':'' ?>>2 курс</option>
        <option value="3 курс" <?= $sub==='3 курс'?'selected':'' ?>>3 курс</option>
        <option value="4 курс" <?= $sub==='4 курс'?'selected':'' ?>>4 курс</option>
        <option value="Выпускники" <?= $sub==='Выпускники'?'selected':'' ?>>Выпускники</option>
        <option value="excellent" <?= $sub==='excellent'?'selected':'' ?>>Отличные показатели</option>
        <option value="good" <?= $sub==='good'?'selected':'' ?>>Хорошие показатели</option>
        <option value="risk" <?= $sub==='risk'?'selected':'' ?>>Зона риска</option>
        <option value="high" <?= $sub==='high'?'selected':'' ?>>Высокая посещаемость</option>
        <option value="control" <?= $sub==='control'?'selected':'' ?>>Контроль посещаемости</option>
        <option value="low" <?= $sub==='low'?'selected':'' ?>>Низкая посещаемость</option>
      </select></div>
      <div class="field"><label>Группа</label><select name="group_id">
        <option value="0">Все группы</option>
        <?php foreach($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $groupId===(int)$g['id']?'selected':'' ?>><?= h($g['name']) ?></option><?php endforeach; ?>
      </select></div>
      <div class="field"><label>Период</label><select name="period">
        <option value="week" <?= $period==='week'?'selected':'' ?>>Неделя</option>
        <option value="month" <?= $period==='month'?'selected':'' ?>>Месяц</option>
        <option value="semester" <?= $period==='semester'?'selected':'' ?>>Семестр</option>
        <option value="year" <?= $period==='year'?'selected':'' ?>>Год</option>
        <option value="custom" <?= $period==='custom'?'selected':'' ?>>Свой срок</option>
      </select></div>
      <div class="field"><label>С</label><input type="date" name="date_from" value="<?= h($dateFrom) ?>"></div>
      <div class="field"><label>По</label><input type="date" name="date_to" value="<?= h($dateTo) ?>"></div>
    </div>

    <?php if (in_array($section, ['grades','attendance','report'], true)): ?>
    <div class="filters" style="margin-top:12px">
      <div class="field"><label>Топ-выборка</label><select name="top_mode">
        <option value="attendance_low" <?= $topMode==='attendance_low'?'selected':'' ?>>Топ прогульщиков</option>
        <option value="attendance_high" <?= $topMode==='attendance_high'?'selected':'' ?>>Топ по посещаемости</option>
        <option value="grades_best" <?= $topMode==='grades_best'?'selected':'' ?>>Топ отличников</option>
        <option value="grades_bad" <?= $topMode==='grades_bad'?'selected':'' ?>>Топ по низким оценкам</option>
      </select></div>
      <div class="field"><label>Количество</label><select name="top_n" id="topN">
        <option value="3" <?= $topNMode==='3'?'selected':'' ?>>Топ 3</option>
        <option value="5" <?= $topNMode==='5'?'selected':'' ?>>Топ 5</option>
        <option value="10" <?= $topNMode==='10'?'selected':'' ?>>Топ 10</option>
        <option value="custom" <?= $topNMode==='custom'?'selected':'' ?>>Свой вариант</option>
      </select></div>
      <div class="field"><label>Своё число</label><input type="number" min="1" max="100" name="top_custom" value="<?= (int)$topCustom ?>"></div>
      <div class="field"><label>&nbsp;</label><button class="btn" type="submit">Показать выборку</button></div>
      <div class="field"><label>&nbsp;</label><button class="btn light" type="button" onclick="window.print()">Печать / PDF</button></div>
      <div class="field"><label>&nbsp;</label><button class="btn light" type="button" onclick="exportTable()">Excel</button></div>
    </div>
    <?php else: ?>
      <div style="margin-top:12px"><button class="btn" type="submit">Показать выборку</button> <button class="btn light" type="button" onclick="window.print()">Печать / PDF</button></div>
    <?php endif; ?>
  </form>
  <?php endif; ?>

  <?php if ($section === 'dashboard'): ?>
    <div class="grid two" style="margin-top:16px">
      <div class="card"><h3>Состояние групп</h3>
        <?php $statusCounts=['Стабильная'=>0,'Требует контроля'=>0,'Проблемная'=>0]; foreach($groupRows as $r){$statusCounts[$r['status']]++;} ?>
        <?php foreach($statusCounts as $name=>$cnt): $p=count($groupRows)?round($cnt/count($groupRows)*100):0; ?>
          <p><b><?= h($name) ?></b> <span class="muted"><?= $cnt ?> групп</span></p><div class="bar"><div class="fill" style="width:<?= $p ?>%"></div></div>
        <?php endforeach; ?>
      </div>
      <div class="card"><h3>Краткий вывод</h3>
        <p>Модуль собирает данные из учебного блока, посещаемости и ведомостей оценок. Администратор видит общую картину по колледжу, преподаватель — только доступные группы.</p>
        <p class="muted">Текущий период анализа: <?= h($dateFrom) ?> — <?= h($dateTo) ?>.</p>
      </div>
    </div>
  <?php endif; ?>

  <?php if (in_array($section, ['grades','attendance','report'], true)): ?>
    <div class="card" style="margin-top:16px">
      <h3>
        <?php if ($topMode==='attendance_low'): ?>Топ прогульщиков<?php elseif($topMode==='attendance_high'): ?>Топ по посещаемости<?php elseif($topMode==='grades_best'): ?>Топ отличников<?php else: ?>Топ студентов по низким оценкам<?php endif; ?>
      </h3>
      <?php if (!$topRows): ?><p class="muted">Нет данных для выбранной топ-выборки.</p><?php else: ?>
      <div class="toplist">
        <?php foreach($topRows as $i=>$r): ?>
          <div class="topitem"><div><div class="rank">Топ <?= $i+1 ?></div><b><?= h($r['full_name']) ?></b><br><span class="muted"><?= h($r['group_name']) ?></span></div><div><b><?= h($r['value_title']) ?></b></div></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($section !== 'help'): ?>
    <div class="card" style="margin-top:16px">
      <h3><?= $section==='report' ? 'Итоговый отчёт по выбранным критериям' : 'Данные по учебным группам' ?></h3>
      <table id="reportTable">
        <thead><tr><th>Группа</th><th>Курс</th><th>Студентов</th><th>Средний балл</th><th>Посещаемость</th><th>Выпускники</th><th>Статус</th></tr></thead>
        <tbody>
          <?php foreach($filteredRows as $r): ?>
          <tr>
            <td><?= h($r['name']) ?></td><td><?= h($r['course']) ?></td><td><?= (int)$r['students_count'] ?></td>
            <td><?= $r['avg_grade']!==null?h($r['avg_grade']):'—' ?></td><td><?= $r['attendance']!==null?h($r['attendance']).'%':'—' ?></td><td><?= (int)$r['graduates'] ?></td>
            <td><span class="badge <?= $r['status']==='Проблемная'?'bad':($r['status']==='Требует контроля'?'warn':'good') ?>"><?= h($r['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$filteredRows): ?><p class="muted">По выбранному критерию данные не найдены.</p><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($section === 'help'): ?>
    <div class="card help" style="margin-top:16px"><h3>Справка по модулю</h3>
      <details><summary>Запуск модуля</summary><p>Модуль открывается из основного меню «СВГТК Портал». Пользователь должен быть авторизован в портале.</p></details>
      <details><summary>Dashboard</summary><p>Показывает ключевые показатели: количество студентов, средний балл, посещаемость и выпускные группы.</p></details>
      <details><summary>Критерии и выборка</summary><p>Фильтры позволяют выбрать статус группы, курс, успеваемость, посещаемость, выпуск и конкретную группу.</p></details>
      <details><summary>Топы</summary><p>Доступны топ прогульщиков, топ по посещаемости, топ отличников и топ студентов с низкими оценками. Можно выбрать Топ 3, 5, 10 или своё число.</p></details>
      <details><summary>Печать и Excel</summary><p>Печать и экспорт выполняются по текущей выбранной выборке, а не по всем данным сразу.</p></details>
    </div>
  <?php endif; ?>

  <?php endif; ?>
</div>
<script src="assets/js/analytics.js"></script>
</body>
</html>
