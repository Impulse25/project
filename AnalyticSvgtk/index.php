<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';

$role = currentRole();
$allowedGroupIds = getAllowedGroupIds($pdo);
$studentId = isStudent() ? getStudentId($pdo) : null;

$totalStudents = 0;
$totalGroups = 0;
$totalGraduates = 0;
$avgGrade = null;
$avgAttendance = null;
$gradeDist = emptyGradeDistribution();
$groupLabels = [];
$groupAvgGrades = [];
$attendanceLabels = [];
$attendancePresent = [];
$attendanceTotal = [];
$statusCounts = ['Стабильная'=>0,'Требует контроля'=>0,'Проблемная'=>0];

ensurePvtDemoGrades($pdo);

if (isStudent() && $studentId) {
    $totalStudents = 1;
    $totalGroups = count($allowedGroupIds);
    $stmt = $pdo->prepare("SELECT ROUND(AVG(grade),2) FROM grades WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $avgGrade = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT ROUND(AVG(percent),1) FROM attendance WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $avgAttendance = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT grade, COUNT(*) AS cnt FROM grades WHERE student_id = ? GROUP BY grade");
    $stmt->execute([$studentId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $key = gradeCategoryKey($r['grade']); if (isset($gradeDist[$key])) { $gradeDist[$key] += (int)$r['cnt']; } }

    $stmt = $pdo->prepare("SELECT sub.name, gr.grade FROM grades gr JOIN subjects sub ON sub.id = gr.subject_id WHERE gr.student_id = ? ORDER BY sub.name");
    $stmt->execute([$studentId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $groupLabels[] = $r['name']; $groupAvgGrades[] = (float)$r['grade']; }

    $attendanceLabels = ['Моя посещаемость'];
    $attendancePresent = [(int)round(((float)$avgAttendance / 100) * 20)];
    $attendanceTotal = [20];
} else if ($allowedGroupIds) {
    $in = placeholders($allowedGroupIds);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE group_id IN ($in)");
    $stmt->execute($allowedGroupIds);
    $totalStudents = (int)$stmt->fetchColumn();
    $totalGroups = isAdmin() ? (int)$pdo->query("SELECT COUNT(*) FROM groups")->fetchColumn() : count($allowedGroupIds);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM graduates WHERE group_id IN ($in)");
    $stmt->execute($allowedGroupIds);
    $totalGraduates = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT ROUND(AVG(gr.grade),2) FROM grades gr JOIN students s ON s.id=gr.student_id WHERE s.group_id IN ($in)");
    $stmt->execute($allowedGroupIds);
    $avgGrade = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT ROUND(AVG(a.percent),1) FROM attendance a JOIN students s ON s.id=a.student_id WHERE s.group_id IN ($in)");
    $stmt->execute($allowedGroupIds);
    $avgAttendance = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT gr.grade, COUNT(*) AS cnt FROM grades gr JOIN students s ON s.id=gr.student_id WHERE s.group_id IN ($in) GROUP BY gr.grade");
    $stmt->execute($allowedGroupIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $key = gradeCategoryKey($r['grade']); if (isset($gradeDist[$key])) { $gradeDist[$key] += (int)$r['cnt']; } }

    $stmt = $pdo->prepare("SELECT g.name, ROUND(AVG(gr.grade),2) AS avg_grade FROM groups g LEFT JOIN students s ON s.group_id=g.id LEFT JOIN grades gr ON gr.student_id=s.id WHERE g.id IN ($in) GROUP BY g.id,g.name ORDER BY g.name LIMIT 8");
    $stmt->execute($allowedGroupIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $groupLabels[] = $r['name']; $groupAvgGrades[] = (float)($r['avg_grade'] ?? 0); }

    $stmt = $pdo->prepare("SELECT g.name, ROUND(AVG(a.percent),0) AS avg_att FROM groups g LEFT JOIN students s ON s.group_id=g.id LEFT JOIN attendance a ON a.student_id=s.id WHERE g.id IN ($in) GROUP BY g.id,g.name ORDER BY g.name LIMIT 8");
    $stmt->execute($allowedGroupIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $attendanceLabels[] = $r['name']; $attendancePresent[] = (int)round(((float)($r['avg_att'] ?? 0) / 100) * 20); $attendanceTotal[] = 20; }
}

if (!isStudent() && $allowedGroupIds) {
    $inStatus = placeholders($allowedGroupIds);
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM groups WHERE id IN ($inStatus) GROUP BY status");
    $stmt->execute($allowedGroupIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $statusCounts[$r['status']] = (int)$r['cnt'];
    }
}

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="content">
    <?php include 'includes/topbar.php'; ?>

    <section class="page-head">
        <h1 class="page-title"><?= isStudent() ? 'Моя аналитика' : 'Аналитика и отчётность' ?></h1>
        <div class="page-subtitle"><?= isStudent() ? 'Личный дашборд ученика: оценки, посещаемость и критерии' : 'Учебный год 2025-2026 — сводная панель ключевых показателей' ?></div>
    </section>

    <section class="kpi-grid">
        <a class="kpi-card kpi-link" href="students.php"><div class="kpi-icon icon-blue"><?= svgIcon('users') ?></div><div class="kpi-title">Студентов</div><div class="kpi-value"><?= (int)$totalStudents ?></div><div class="kpi-note"><?= isStudent() ? 'Личный профиль' : 'Активных студентов' ?></div></a>
        <div class="kpi-card kpi-hover"><div class="kpi-icon icon-green"><?= svgIcon('trend') ?></div><div class="kpi-title">Средний балл</div><div class="kpi-value"><?= $avgGrade ?: '-' ?></div><div class="kpi-note">По критериям успеваемости</div></div>
        <div class="kpi-card kpi-hover"><div class="kpi-icon icon-orange"><?= svgIcon('calendar') ?></div><div class="kpi-title">Посещаемость</div><div class="kpi-value"><?= $avgAttendance ?: '-' ?>%</div><div class="kpi-note">Явка на занятия</div></div>
        <?php if (isStudent()): ?>
            <div class="kpi-card kpi-hover"><div class="kpi-icon icon-yellow"><?= svgIcon('home') ?></div><div class="kpi-title">Группа</div><div class="kpi-value"><?= (int)$totalGroups ?></div><div class="kpi-note">Моя учебная группа</div></div>
        <?php else: ?>
            <a class="kpi-card kpi-link" href="groups.php?criteria=course&value=graduates"><div class="kpi-icon icon-yellow"><?= svgIcon('cap') ?></div><div class="kpi-title">Выпускников</div><div class="kpi-value"><?= (int)$totalGraduates ?></div><div class="kpi-note"><?= (int)$totalGroups ?> учебных групп</div></a>
        <?php endif; ?>
    </section>

    <?php if (isStudent()): ?>
    <?php $studentStatus = studentStatusByCriteria($avgGrade, $avgAttendance, (int)$gradeDist['unsatisfactory']); ?>
    <div class="quick-actions no-print">
        <a class="btn btn-primary" href="my_grades.php">Мои оценки</a>
        <a class="btn btn-success" href="attendance.php">Моя посещаемость</a>
        <a class="btn btn-warning" href="reports.php">Мой отчёт</a>
    </div>
    <section class="student-risk-grid">
        <div class="risk-card"><div class="risk-title">Статус</div><div class="risk-text"><span class="badge <?= htmlspecialchars($studentStatus[1]) ?>"><?= htmlspecialchars($studentStatus[0]) ?></span></div><div class="risk-note"><?= htmlspecialchars($studentStatus[2]) ?></div></div>
        <div class="risk-card"><div class="risk-title">Критерий успеваемости</div><div class="risk-text"><?= htmlspecialchars(gradeCategoryTitle($avgGrade)) ?></div><div class="risk-note">по среднему баллу</div></div>
        <div class="risk-card"><div class="risk-title">Критерий посещаемости</div><div class="risk-text"><?= ((float)$avgAttendance >= 85) ? 'Норма' : (((float)$avgAttendance >= 70) ? 'Контроль' : 'Риск') ?></div><div class="risk-note">по личному проценту посещаемости</div></div>
    </section>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
    <section class="status-grid">
        <a class="status-card status-link" href="groups.php?criteria=status&value=<?= urlencode('Стабильная') ?>">
            <div class="status-icon icon-green"><?= svgIcon('check') ?></div><div><div class="status-title">Стабильная</div><div class="status-value"><?= (int)$statusCounts['Стабильная'] ?></div><div class="kpi-note">групп с нормальными показателями</div></div>
        </a>
        <a class="status-card status-link" href="groups.php?criteria=status&value=<?= urlencode('Требует контроля') ?>">
            <div class="status-icon icon-orange"><?= svgIcon('alert') ?></div><div><div class="status-title">Требует контроля</div><div class="status-value"><?= (int)$statusCounts['Требует контроля'] ?></div><div class="kpi-note">групп в зоне внимания</div></div>
        </a>
        <a class="status-card status-link" href="groups.php?criteria=status&value=<?= urlencode('Проблемная') ?>">
            <div class="status-icon icon-red"><?= svgIcon('alert') ?></div><div><div class="status-title">Проблемная</div><div class="status-value"><?= (int)$statusCounts['Проблемная'] ?></div><div class="kpi-note">групп с риском</div></div>
        </a>
    </section>
    <?php endif; ?>

    <section class="grid-3 dashboard-charts-only">
        <div class="chart-card">
            <div class="card-head"><span>Критерии успеваемости</span><span class="year-badge">2025-2026</span></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="gradeDistributionChart"></canvas></div></div>
        </div>
        <div class="chart-card">
            <div class="card-head"><span><?= isStudent() ? 'Успеваемость по предметам' : 'Успеваемость по группам' ?></span></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="groupGradeChart"></canvas></div></div>
        </div>
        <div class="chart-card">
            <div class="card-head"><span><?= isStudent() ? 'Моя посещаемость' : 'Посещаемость по группам' ?></span></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="groupAttendanceChart"></canvas></div></div>
        </div>
    </section>
</main>
</div>
<script src="assets/js/charts.js"></script>
<script>
SVGTKCharts.doughnut('gradeDistributionChart', ['5','4','3','2'], [<?= (int)$gradeDist['excellent'] ?>, <?= (int)$gradeDist['good'] ?>, <?= (int)$gradeDist['satisfactory'] ?>, <?= (int)$gradeDist['unsatisfactory'] ?>]);
SVGTKCharts.bar('groupGradeChart', <?= json_encode($groupLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($groupAvgGrades, JSON_UNESCAPED_UNICODE) ?>, 'Средний балл');
SVGTKCharts.groupedBar('groupAttendanceChart', <?= json_encode($attendanceLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($attendancePresent, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($attendanceTotal, JSON_UNESCAPED_UNICODE) ?>);
</script>
<?php include 'includes/footer.php'; ?>
