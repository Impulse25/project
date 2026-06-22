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
        $status = (!\$avg && !\$attPct && \$avg!==0 && \$attPct!==0) ? 'Нет данных' : 'Стабильная';
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


// Данные для графиков Dashboard
$gradeDistribution = ['90-100'=>0,'70-89'=>0,'51-69'=>0,'0-50'=>0];
if ($hasGrades && $allowedGroupIds) {
    $where = "s.group_id IN ($inGroups) AND eg.grade IS NOT NULL";
    $params = [];
    if (an_column_exists($pdo, 'edu_grades', 'updated_at')) {
        $where .= " AND DATE(COALESCE(eg.updated_at, eg.created_at, NOW())) BETWEEN ? AND ?";
        $params[] = $dateFrom; $params[] = $dateTo;
    } elseif (an_column_exists($pdo, 'edu_grades', 'created_at')) {
        $where .= " AND DATE(eg.created_at) BETWEEN ? AND ?";
        $params[] = $dateFrom; $params[] = $dateTo;
    }
    $stmt = $pdo->prepare("\n        SELECT\n          SUM(CASE WHEN eg.grade >= 90 THEN 1 ELSE 0 END) AS g5,\n          SUM(CASE WHEN eg.grade >= 70 AND eg.grade < 90 THEN 1 ELSE 0 END) AS g4,\n          SUM(CASE WHEN eg.grade >= 51 AND eg.grade < 70 THEN 1 ELSE 0 END) AS g3,\n          SUM(CASE WHEN eg.grade < 51 THEN 1 ELSE 0 END) AS g2\n        FROM edu_grades eg\n        JOIN edu_students s ON s.id = eg.student_id\n        WHERE $where\n    ");
    $stmt->execute($params);
    $gd = $stmt->fetch() ?: [];
    $gradeDistribution = ['90-100'=>(int)($gd['g5']??0),'70-89'=>(int)($gd['g4']??0),'51-69'=>(int)($gd['g3']??0),'0-50'=>(int)($gd['g2']??0)];
}

$courseBuckets = [];
foreach ($groupRows as $r) {
    $course = $r['course'] ?: 'Не определён';
    if (!isset($courseBuckets[$course])) $courseBuckets[$course] = ['grades'=>[], 'attendance'=>[]];
    if ($r['avg_grade'] !== null) $courseBuckets[$course]['grades'][] = (float)$r['avg_grade'];
    if ($r['attendance'] !== null) $courseBuckets[$course]['attendance'][] = (float)$r['attendance'];
}
uksort($courseBuckets, function($a,$b){ return strnatcmp($a,$b); });
$courseLabels = array_keys($courseBuckets);
$courseGradeData = [];
$courseAttendanceData = [];
foreach ($courseBuckets as $bucket) {
    $courseGradeData[] = count($bucket['grades']) ? round(array_sum($bucket['grades']) / count($bucket['grades']), 1) : 0;
    $courseAttendanceData[] = count($bucket['attendance']) ? round(array_sum($bucket['attendance']) / count($bucket['attendance']), 1) : 0;
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
<link rel="stylesheet" href="assets/css/analytics.css">
<script>
(function(){
  var t = localStorage.getItem('theme') || localStorage.getItem('svgtkAnalyticsTheme') || 'light';
  document.documentElement.setAttribute('data-theme', t);
})();
</script>
</head>
<body>
<?php
$activeModule = 'analytics';
$moduleTitle = 'Аналитика';
require_once __DIR__ . '/../includes/portal_sidebar.php';
?>
<div class="main-wrapper" id="mainWrapper">
  <header class="topbar no-print">
    <div class="topbar-left">
      <div class="breadcrumb">
        <span class="breadcrumb-root"><a href="/">СВГТК</a></span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="breadcrumb-current">Аналитика</span>
      </div>
    </div>
    <div class="topbar-right">
      <button class="theme-toggle" id="themeToggle" type="button" title="Сменить тему">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <div class="user-avatar" title="<?= h($userName ?: $roleTitle) ?>"><?= h(mb_substr($userName ?: $roleTitle,0,1,'UTF-8')) ?></div>
      <div class="user-meta"><b><?= h($userName ?: $roleTitle) ?></b><br><span><?= h($roleTitle) ?></span></div>
      <a class="logout-link" href="/requests/logout.php" title="Выйти">↪</a>
    </div>
  </header>

  <main class="page-content">
    <div class="analytics-wrap" id="printArea">
      <div class="page-header-block">
        <div>
          <h1>Аналитика и отчётность</h1>
          <p>Модуль информационной системы «СВГТК Портал»</p>
        </div>
      </div>

      <div class="tabs no-print">
        <?php foreach (['dashboard'=>'Dashboard','grades'=>'Успеваемость','attendance'=>'Посещаемость','graduates'=>'Выпуск','report'=>'Итоговый отчёт','help'=>'Справка'] as $key=>$title): ?>
          <a class="<?= $section===$key?'active':'' ?>" href="?section=<?= h($key) ?>"><?= h($title) ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (!$allowedGroupIds): ?>
        <div class="card"><h3>Нет доступных групп</h3><p class="muted">Для текущего пользователя не найдены группы. Проверьте роль, куратора группы или права заведующего отделением.</p></div>
      <?php else: ?>

      <div class="grid kpi">
        <div class="card kpi-card"><div class="muted">Студенты</div><div class="num"><?= (int)$totalStudents ?></div><div class="muted">по доступным группам</div></div>
        <div class="card kpi-card"><div class="muted">Средний балл</div><div class="num"><?= $avgGrade !== null ? h($avgGrade) : '—' ?></div><div class="muted"><?= $hasGrades ? 'по ведомостям' : 'таблица оценок не найдена' ?></div></div>
        <div class="card kpi-card"><div class="muted">Посещаемость</div><div class="num"><?= $attendanceAvg !== null ? h($attendanceAvg).'%' : '—' ?></div><div class="muted"><?= h($periodTitles[$period] ?? '') ?></div></div>
        <div class="card kpi-card"><div class="muted">Выпускники</div><div class="num"><?= (int)$graduates ?></div><div class="muted">по выпускным группам</div></div>
      </div>

      <?php if ($section !== 'help'): ?>
      <form method="get" class="card filter-card no-print">
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
        <div class="filters top-filters">
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
          <div class="field action-field"><button class="btn" type="submit">Показать выборку</button></div>
          <div class="field action-field"><button class="btn light" type="button" onclick="window.print()">Печать / PDF</button></div>
          <div class="field action-field"><button class="btn light" type="button" onclick="exportTable()">Excel</button></div>
        </div>
        <?php else: ?>
          <div class="filter-actions"><button class="btn" type="submit">Показать выборку</button> <button class="btn light" type="button" onclick="window.print()">Печать / PDF</button></div>
        <?php endif; ?>
      </form>
      <?php endif; ?>

      <?php if ($section === 'dashboard'): ?>
        <div class="grid three chart-grid">
          <div class="card chart-card"><h3>Распределение оценок</h3><p class="muted">Оценки 5, 4, 3 и 2 за выбранный период</p><canvas id="gradesDonut" height="230"></canvas></div>
          <div class="card chart-card"><h3>Средний балл по курсам</h3><p class="muted">Сводка по учебным группам</p><canvas id="courseGradeChart" height="230"></canvas></div>
          <div class="card chart-card"><h3>Посещаемость по курсам</h3><p class="muted">Средний процент посещаемости</p><canvas id="courseAttendanceChart" height="230"></canvas></div>
        </div>
        <div class="grid two dashboard-grid">
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
        <div class="card top-card">
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
        <div class="card table-card">
          <h3><?= $section==='report' ? 'Итоговый отчёт по выбранным критериям' : 'Данные по учебным группам' ?></h3>
          <div class="table-wrap">
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
          </div>
          <?php if (!$filteredRows): ?><p class="muted">По выбранному критерию данные не найдены.</p><?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($section === 'help'): ?>
        <div class="card help"><h3>Справка по модулю</h3>
          <details><summary>Запуск модуля</summary><p>Модуль открывается из основного меню «СВГТК Портал». Пользователь должен быть авторизован в портале.</p></details>
          <details><summary>Dashboard</summary><p>Показывает ключевые показатели: количество студентов, средний балл, посещаемость, выпускные группы и графики.</p></details>
          <details><summary>Критерии и выборка</summary><p>Фильтры позволяют выбрать статус группы, курс, успеваемость, посещаемость, выпуск и конкретную группу.</p></details>
          <details><summary>Топы</summary><p>Доступны топ прогульщиков, топ по посещаемости, топ отличников и топ студентов с низкими оценками. Можно выбрать Топ 3, 5, 10 или своё число.</p></details>
          <details><summary>Печать и Excel</summary><p>Печать и экспорт выполняются по текущей выбранной выборке, а не по всем данным сразу.</p></details>
        </div>
      <?php endif; ?>

      <?php endif; ?>
    </div>
  </main>

  <footer class="page-footer no-print">
    <span>СВГТК Портал · Модуль «Аналитика и отчётность»</span>
    <span><?= date('Y') ?></span>
  </footer>
</div>

<script>
window.analyticsChartData = <?= json_encode([
  'gradeDistribution' => $gradeDistribution,
  'courseLabels' => $courseLabels,
  'courseGradeData' => $courseGradeData,
  'courseAttendanceData' => $courseAttendanceData,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/analytics.js"></script>
</body>
</html>
