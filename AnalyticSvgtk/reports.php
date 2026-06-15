<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';

$allowedGroupIds = getAllowedGroupIds($pdo);
$studentId = isStudent() ? getStudentId($pdo) : null;
$reportRows = [];
$gradeLabels = ['5','4','3','2'];
$gradeData = [0,0,0,0];
$gradeDist = emptyGradeDistribution();
$successLabels = [];
$successData = [];
$attendanceLabels = [];
$attendanceData = [];
$attendancePresent = [];
$attendanceTotal = [];
$avgGrade = null;
$avgAttendance = null;
$excellent = 0;
$riskCount = 0;

if (isStudent() && $studentId) {
    $stmt = $pdo->prepare("SELECT ROUND(AVG(grade),2) FROM grades WHERE student_id=?");
    $stmt->execute([$studentId]);
    $avgGrade = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT ROUND(AVG(percent),1) FROM attendance WHERE student_id=?");
    $stmt->execute([$studentId]);
    $avgAttendance = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE student_id=? AND grade BETWEEN 90 AND 100");
    $stmt->execute([$studentId]);
    $excellent = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=? AND percent < 70");
    $stmt->execute([$studentId]);
    $riskCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT grade, COUNT(*) AS cnt FROM grades WHERE student_id=? GROUP BY grade");
    $stmt->execute([$studentId]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){ $key = gradeCategoryKey($r['grade']); if (isset($gradeDist[$key])) { $gradeDist[$key] += (int)$r['cnt']; } }

    $stmt = $pdo->prepare("SELECT u.full_name, g.name AS group_name, sub.name AS subject_name, gr.grade, COALESCE(a.percent,0) AS attendance FROM students s JOIN users u ON u.id=s.user_id JOIN groups g ON g.id=s.group_id LEFT JOIN grades gr ON gr.student_id=s.id LEFT JOIN subjects sub ON sub.id=gr.subject_id LEFT JOIN attendance a ON a.student_id=s.id WHERE s.id=? ORDER BY sub.name");
    $stmt->execute([$studentId]);
    $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($reportRows as $row){
        $successLabels[] = $row['subject_name'] ?: 'Предмет';
        $successData[] = (float)$row['grade'];
    }
    $attendanceLabels = ['Личная посещаемость'];
    $attendanceData = [(float)$avgAttendance];
    $attendancePresent = [(int)round(((float)$avgAttendance / 100) * 20)];
    $attendanceTotal = [20];
} else if ($allowedGroupIds) {
    $in = placeholders($allowedGroupIds);

    $stmt = $pdo->prepare("SELECT ROUND(AVG(gr.grade),2) FROM grades gr JOIN students s ON s.id=gr.student_id WHERE s.group_id IN ($in)");
    $stmt->execute($allowedGroupIds);
    $avgGrade = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT ROUND(AVG(a.percent),1) FROM attendance a JOIN students s ON s.id=a.student_id WHERE s.group_id IN ($in)");
    $stmt->execute($allowedGroupIds);
    $avgAttendance = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT gr.student_id) FROM grades gr JOIN students s ON s.id=gr.student_id WHERE s.group_id IN ($in) AND gr.grade BETWEEN 90 AND 100");
    $stmt->execute($allowedGroupIds);
    $excellent = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id WHERE s.group_id IN ($in) AND a.percent < 70");
    $stmt->execute($allowedGroupIds);
    $riskCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT gr.grade, COUNT(*) AS cnt FROM grades gr JOIN students s ON s.id=gr.student_id WHERE s.group_id IN ($in) GROUP BY gr.grade");
    $stmt->execute($allowedGroupIds);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){ $key = gradeCategoryKey($r['grade']); if (isset($gradeDist[$key])) { $gradeDist[$key] += (int)$r['cnt']; } }

    $stmt = $pdo->prepare("SELECT g.name, g.status, COUNT(DISTINCT s.id) AS students_count, ROUND(AVG(gr.grade),2) AS avg_grade, ROUND(AVG(a.percent),1) AS avg_attendance FROM groups g LEFT JOIN students s ON s.group_id=g.id LEFT JOIN grades gr ON gr.student_id=s.id LEFT JOIN attendance a ON a.student_id=s.id WHERE g.id IN ($in) GROUP BY g.id,g.name,g.status ORDER BY g.name");
    $stmt->execute($allowedGroupIds);
    $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($reportRows as $row){
        $successLabels[] = $row['name'];
        $successData[] = (float)($row['avg_grade'] ?? 0);
        $attendanceLabels[] = $row['name'];
        $attendanceData[] = (float)($row['avg_attendance'] ?? 0);
        $attendancePresent[] = (int)round(((float)($row['avg_attendance'] ?? 0) / 100) * 20);
        $attendanceTotal[] = 20;
    }
}
$gradeData = [(int)$gradeDist['excellent'], (int)$gradeDist['good'], (int)$gradeDist['satisfactory'], (int)$gradeDist['unsatisfactory']];

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="content">
    <?php include 'includes/topbar.php'; ?>

    <section class="page-head">
        <h1 class="page-title">Аналитика и отчёты по посещаемости и успеваемости</h1>
        <div class="page-subtitle">Разработка приложения аналитики и отчётности в системе «СВГТК Портал»</div>
    </section>

    <div class="actions">
        <button class="btn btn-primary" onclick="printReport()"><?= svgIcon('printer') ?> Печать / сохранить PDF</button>
        <button class="btn btn-success" onclick="exportTableToExcel('reportTable','svgtk-report.xls')"><?= svgIcon('download') ?> Экспорт отчёта Excel</button>
    </div>

    <div class="print-only">
        <h2>Отчёт СВГТК Портал</h2>
        <p>Период: учебный год 2025-2026. Роль: <?= htmlspecialchars(roleTitle()) ?>.</p>
    </div>

    <section class="criteria">
        <div class="criteria-card"><div class="criteria-title">Критерий успеваемости</div><div class="criteria-value">Средний балл <?= $avgGrade ?: '-' ?></div></div>
        <div class="criteria-card"><div class="criteria-title">Критерий посещаемости</div><div class="criteria-value"><?= $avgAttendance ?: '-' ?>%</div></div>
        <div class="criteria-card"><div class="criteria-title">Зона контроля</div><div class="criteria-value"><?= (int)$riskCount ?> показателей</div></div>
    </section>

    <section class="grid-3">
        <div class="chart-card">
            <div class="card-head"><span>Отчёт: критерии успеваемости</span><span class="year-badge">Успеваемость</span></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="reportGradeDistribution"></canvas></div></div>
        </div>
        <div class="chart-card">
            <div class="card-head"><span><?= isStudent() ? 'Оценки по предметам' : 'Средний балл по группам' ?></span></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="reportSuccessChart"></canvas></div></div>
        </div>
        <div class="chart-card">
            <div class="card-head"><span><?= isStudent() ? 'Личная посещаемость' : 'Посещаемость по группам' ?></span></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="reportAttendanceChart"></canvas></div></div>
        </div>
    </section>

    <section class="report-card">
        <div class="table-header"><span><?= isStudent() ? 'Личный отчёт студента' : 'Сводный отчёт по доступным группам' ?></span><span class="badge badge-blue">Экспортируется</span></div>
        <div class="table-responsive">
            <table id="reportTable">
                <?php if (isStudent()): ?>
                <thead><tr><th>ФИО</th><th>Группа</th><th>Предмет</th><th>Оценка</th><th>Посещаемость</th><th>Критерий</th></tr></thead>
                <tbody>
                    <?php foreach($reportRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars(formatPersonName($row['full_name'])) ?></td>
                        <td><?= htmlspecialchars($row['group_name']) ?></td>
                        <td><?= htmlspecialchars($row['subject_name'] ?? '-') ?></td>
                        <td><?= $row['grade'] ?? '-' ?></td>
                        <td><?= $row['attendance'] ?? '-' ?>%</td>
                        <td><span class="badge <?= ((float)$row['attendance'] >= 70 && (int)$row['grade'] >= 70) ? 'badge-success' : 'badge-warning' ?>"><?= ((float)$row['attendance'] >= 70 && (int)$row['grade'] >= 70) ? 'Норма' : 'Контроль' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <thead><tr><th>Группа</th><th>Студентов</th><th>Средний балл</th><th>Посещаемость</th><th>Статус</th><th>Критерий</th></tr></thead>
                <tbody>
                    <?php foreach($reportRows as $row): ?>
                    <?php $ok = ((float)$row['avg_grade'] >= 70 && (float)$row['avg_attendance'] >= 70); ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= (int)$row['students_count'] ?></td>
                        <td><?= $row['avg_grade'] ?? '-' ?></td>
                        <td><?= $row['avg_attendance'] ?? '-' ?>%</td>
                        <td><span class="badge <?= $row['status']==='Стабильная' ? 'badge-success' : ($row['status']==='Проблемная' ? 'badge-danger' : 'badge-warning') ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                        <td><span class="badge <?= $ok ? 'badge-success' : 'badge-warning' ?>"><?= $ok ? 'Норма' : 'Контроль' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if(!$reportRows): ?><tr><td colspan="6">Нет доступных отчётов для этой роли.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
<script src="assets/js/charts.js"></script>
<script>
SVGTKCharts.doughnut('reportGradeDistribution', <?= json_encode($gradeLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($gradeData, JSON_UNESCAPED_UNICODE) ?>);
SVGTKCharts.bar('reportSuccessChart', <?= json_encode($successLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($successData, JSON_UNESCAPED_UNICODE) ?>, 'Средний балл');
SVGTKCharts.groupedBar('reportAttendanceChart', <?= json_encode($attendanceLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($attendancePresent, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($attendanceTotal, JSON_UNESCAPED_UNICODE) ?>);
</script>
<?php include 'includes/footer.php'; ?>
