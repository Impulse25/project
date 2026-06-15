<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';

if (!isStudent()) {
    header('Location: journal.php');
    exit;
}

$studentId = getStudentId($pdo);
if (!$studentId) {
    include 'includes/header.php';
    echo '<div class="layout">'; include 'includes/sidebar.php'; echo '<main class="content">'; include 'includes/topbar.php';
    echo '<section class="page-head"><h1 class="page-title">Мои оценки</h1><div class="page-subtitle">Профиль студента не найден.</div></section></main></div>';
    include 'includes/footer.php';
    exit;
}

$period = $_GET['period'] ?? 'all';
$criterion = $_GET['criterion'] ?? 'all';
$subjectId = (int)($_GET['subject_id'] ?? 0);
$sort = $_GET['sort'] ?? 'date_desc';
$dateTo = date('Y-m-d');
$dateFrom = periodStartDate($period, $dateTo);

$where = ['gr.student_id = ?'];
$params = [$studentId];
if ($subjectId > 0) {
    $where[] = 'gr.subject_id = ?';
    $params[] = $subjectId;
}
if ($dateFrom) {
    $where[] = 'COALESCE(gr.grade_date, CURDATE()) BETWEEN ? AND ?';
    $params[] = $dateFrom;
    $params[] = $dateTo;
}
$range = gradeCriterionRange($criterion);
if ($range) {
    $where[] = 'gr.grade BETWEEN ? AND ?';
    $params[] = $range[0];
    $params[] = $range[1];
}

$orderBy = 'gr.grade_date DESC, gr.id DESC';
if ($sort === 'grade_desc') $orderBy = 'gr.grade DESC, gr.grade_date DESC';
if ($sort === 'grade_asc') $orderBy = 'gr.grade ASC, gr.grade_date DESC';
if ($sort === 'subject') $orderBy = 'sub.name ASC, gr.grade_date DESC';

$stmt = $pdo->prepare('SELECT u.full_name, g.name AS group_name FROM students s JOIN users u ON u.id=s.user_id JOIN groups g ON g.id=s.group_id WHERE s.id=? LIMIT 1');
$stmt->execute([$studentId]);
$studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT DISTINCT sub.id, sub.name FROM grades gr JOIN subjects sub ON sub.id=gr.subject_id WHERE gr.student_id=? ORDER BY sub.name');
$stmt->execute([$studentId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = 'SELECT gr.id, gr.grade, gr.grade_date, sub.name AS subject_name
        FROM grades gr
        JOIN subjects sub ON sub.id = gr.subject_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $orderBy;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$avgGrade = null;
if ($grades) {
    $sum = 0;
    foreach ($grades as $row) { $sum += (int)$row['grade']; }
    $avgGrade = round($sum / count($grades), 2);
}

$dist = emptyGradeDistribution();
foreach ($grades as $row) {
    $key = gradeCategoryKey($row['grade']);
    if (isset($dist[$key])) $dist[$key]++;
}

$subjectLabels = [];
$subjectAverages = [];
$bySubject = [];
foreach ($grades as $row) {
    $name = $row['subject_name'];
    if (!isset($bySubject[$name])) $bySubject[$name] = ['sum' => 0, 'count' => 0];
    $bySubject[$name]['sum'] += (int)$row['grade'];
    $bySubject[$name]['count']++;
}
foreach ($bySubject as $name => $data) {
    $subjectLabels[] = $name;
    $subjectAverages[] = round($data['sum'] / max(1, $data['count']), 2);
}

$badGrades = (int)$dist['unsatisfactory'];
$status = studentStatusByCriteria($avgGrade, null, $badGrades);

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="content">
<?php include 'includes/topbar.php'; ?>
<section class="page-head">
    <h1 class="page-title">Мои оценки</h1>
    <div class="page-subtitle">Личная успеваемость по критериям, предметам и выбранному периоду</div>
</section>

<div class="student-profile-head">
    <div>
        <div class="student-name"><?= htmlspecialchars(formatPersonName($studentInfo['full_name'] ?? 'Студент')) ?></div>
        <div class="student-meta">Группа: <?= htmlspecialchars($studentInfo['group_name'] ?? '-') ?></div>
    </div>
    <span class="badge <?= htmlspecialchars($status[1]) ?>"><?= htmlspecialchars($status[0]) ?></span>
</div>

<div class="filter-card no-print">
    <form class="filter-form student-filter" method="get" action="my_grades.php">
        <div class="filter-field">
            <label>Период</label>
            <select class="form-select" name="period">
                <option value="all" <?= $period==='all'?'selected':'' ?>>За всё время</option>
                <option value="today" <?= $period==='today'?'selected':'' ?>>Сегодня</option>
                <option value="week" <?= $period==='week'?'selected':'' ?>>Текущая неделя</option>
                <option value="month" <?= $period==='month'?'selected':'' ?>>Текущий месяц</option>
                <option value="halfyear" <?= $period==='halfyear'?'selected':'' ?>>Последние 6 месяцев</option>
                <option value="year" <?= $period==='year'?'selected':'' ?>>Последний год</option>
            </select>
        </div>
        <div class="filter-field">
            <label>Предмет</label>
            <select class="form-select" name="subject_id">
                <option value="0">Все предметы</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int)$subject['id'] ?>" <?= (int)$subject['id']===$subjectId?'selected':'' ?>><?= htmlspecialchars($subject['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label>Критерий</label>
            <select class="form-select" name="criterion">
                <option value="all" <?= $criterion==='all'?'selected':'' ?>>Все оценки</option>
                <option value="excellent" <?= $criterion==='excellent'?'selected':'' ?>>5 — отлично</option>
                <option value="good" <?= $criterion==='good'?'selected':'' ?>>4 — хорошо</option>
                <option value="satisfactory" <?= $criterion==='satisfactory'?'selected':'' ?>>3 — удовлетворительно</option>
                <option value="unsatisfactory" <?= $criterion==='unsatisfactory'?'selected':'' ?>>2 — неудовлетворительно</option>
            </select>
        </div>
        <div class="filter-field">
            <label>Сортировка</label>
            <select class="form-select" name="sort">
                <option value="date_desc" <?= $sort==='date_desc'?'selected':'' ?>>Сначала новые</option>
                <option value="subject" <?= $sort==='subject'?'selected':'' ?>>По предмету</option>
                <option value="grade_desc" <?= $sort==='grade_desc'?'selected':'' ?>>По оценке ↓</option>
                <option value="grade_asc" <?= $sort==='grade_asc'?'selected':'' ?>>По оценке ↑</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Показать</button>
    </form>
</div>

<section class="kpi-grid">
    <div class="kpi-card kpi-hover"><div class="kpi-icon icon-green"><?= svgIcon('trend') ?></div><div class="kpi-title">Средний балл</div><div class="kpi-value"><?= $avgGrade !== null ? $avgGrade : '-' ?></div><div class="kpi-note">по выбранным критериям</div></div>
    <div class="kpi-card kpi-hover"><div class="kpi-icon icon-yellow"><?= svgIcon('cap') ?></div><div class="kpi-title">Оценок</div><div class="kpi-value"><?= count($grades) ?></div><div class="kpi-note">найдено в журнале</div></div>
    <div class="kpi-card kpi-hover"><div class="kpi-icon icon-red"><?= svgIcon('alert') ?></div><div class="kpi-title">Зона контроля</div><div class="kpi-value"><?= $badGrades ?></div><div class="kpi-note">неудовлетворительных оценок</div></div>
</section>

<section class="criteria grade-criteria-grid">
    <div class="criteria-card"><div class="criteria-title">5</div><div class="criteria-value"><?= (int)$dist['excellent'] ?></div><div class="criteria-note">90–100</div></div>
    <div class="criteria-card"><div class="criteria-title">4</div><div class="criteria-value"><?= (int)$dist['good'] ?></div><div class="criteria-note">70–89</div></div>
    <div class="criteria-card"><div class="criteria-title">3</div><div class="criteria-value"><?= (int)$dist['satisfactory'] ?></div><div class="criteria-note">51–69</div></div>
    <div class="criteria-card"><div class="criteria-title">2</div><div class="criteria-value"><?= (int)$dist['unsatisfactory'] ?></div><div class="criteria-note">0–50</div></div>
</section>

<section class="grid-2">
    <div class="chart-card">
        <div class="card-head"><span>Распределение по критериям</span></div>
        <div class="card-body"><div class="chart-wrap"><canvas id="studentGradeDist"></canvas></div></div>
    </div>
    <div class="chart-card">
        <div class="card-head"><span>Средний балл по предметам</span></div>
        <div class="card-body"><div class="chart-wrap"><canvas id="studentSubjectAvg"></canvas></div></div>
    </div>
</section>

<div class="table-card">
    <div class="table-header"><span>Список оценок</span><span class="table-muted">Фильтр работает по периоду, предмету и критерию</span></div>
    <div class="table-responsive">
        <table id="myGradesTable">
            <thead><tr><th>Дата</th><th>Предмет</th><th>Оценка</th><th>Эквивалент</th><th>Критерий</th></tr></thead>
            <tbody>
            <?php foreach ($grades as $row): ?>
                <tr>
                    <td><?= $row['grade_date'] ? htmlspecialchars(date('d.m.Y', strtotime($row['grade_date']))) : '—' ?></td>
                    <td><?= htmlspecialchars($row['subject_name']) ?></td>
                    <td><span class="grade-pill <?= htmlspecialchars(gradeCategoryCss($row['grade'])) ?>"><?= (int)$row['grade'] ?></span></td>
                    <td><?= htmlspecialchars(gradeCategoryShortTitle($row['grade'])) ?></td>
                    <td><?= htmlspecialchars(gradeCategoryTitle($row['grade'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$grades): ?><tr><td colspan="5">По выбранным критериям оценок нет.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
</div>
<script src="assets/js/charts.js"></script>
<script>
SVGTKCharts.doughnut('studentGradeDist', ['5','4','3','2'], [<?= (int)$dist['excellent'] ?>, <?= (int)$dist['good'] ?>, <?= (int)$dist['satisfactory'] ?>, <?= (int)$dist['unsatisfactory'] ?>]);
SVGTKCharts.bar('studentSubjectAvg', <?= json_encode($subjectLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($subjectAverages, JSON_UNESCAPED_UNICODE) ?>, 'Средний балл');
</script>
<?php include 'includes/footer.php'; ?>
