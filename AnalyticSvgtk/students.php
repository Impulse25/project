<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';

if (isStudent()) {
    $studentId = getStudentId($pdo);
    $profile = null;
    $avgGrade = null;
    $avgAttendance = null;
    $gradeDist = emptyGradeDistribution();
    $recentGrades = [];
    $recommendations = [];

    if ($studentId) {
        $stmt = $pdo->prepare("SELECT s.id, u.full_name, u.login AS username, g.name AS group_name, g.status AS group_status FROM students s JOIN users u ON u.id=s.user_id JOIN groups g ON g.id=s.group_id WHERE s.id=? LIMIT 1");
        $stmt->execute([$studentId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT ROUND(AVG(grade),2) FROM grades WHERE student_id=?");
        $stmt->execute([$studentId]);
        $avgGrade = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT ROUND(AVG(percent),1) FROM attendance WHERE student_id=?");
        $stmt->execute([$studentId]);
        $avgAttendance = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT grade, COUNT(*) AS cnt FROM grades WHERE student_id=? GROUP BY grade");
        $stmt->execute([$studentId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = gradeCategoryKey($r['grade']);
            if (isset($gradeDist[$key])) $gradeDist[$key] += (int)$r['cnt'];
        }

        $stmt = $pdo->prepare("SELECT gr.grade, gr.grade_date, sub.name AS subject_name FROM grades gr JOIN subjects sub ON sub.id=gr.subject_id WHERE gr.student_id=? ORDER BY gr.grade_date DESC, gr.id DESC LIMIT 5");
        $stmt->execute([$studentId]);
        $recentGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ((float)$avgGrade < 70) $recommendations[] = ['Успеваемость', 'Средний балл ниже 70', 'Повысить результаты по предметам с оценками 2 и 3'];
        else $recommendations[] = ['Успеваемость', 'Показатель в норме', 'Поддерживать текущий уровень'];
        if ((float)$avgAttendance < 75) $recommendations[] = ['Посещаемость', 'Требует внимания', 'Снизить количество пропусков и опозданий'];
        else $recommendations[] = ['Посещаемость', 'Показатель в норме', 'Продолжать посещать занятия стабильно'];
        if ((int)$gradeDist['unsatisfactory'] > 0) $recommendations[] = ['Критерий 2', 'Есть неудовлетворительные оценки', 'Закрыть слабые темы и пересдать работы'];
    }

    $status = studentStatusByCriteria($avgGrade, $avgAttendance, (int)$gradeDist['unsatisfactory']);
    include 'includes/header.php';
    ?>
    <div class="layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
    <?php include 'includes/topbar.php'; ?>
    <section class="page-head">
        <h1 class="page-title">Мой профиль</h1>
        <div class="page-subtitle">Личный кабинет студента: успеваемость, посещаемость, критерии и рекомендации</div>
    </section>

    <?php if ($profile): ?>
    <div class="student-profile-head">
        <div>
            <div class="student-name"><?= htmlspecialchars(formatPersonName($profile['full_name'])) ?></div>
            <div class="student-meta">Группа: <?= htmlspecialchars($profile['group_name']) ?> · Логин: <?= htmlspecialchars($profile['username']) ?></div>
        </div>
        <span class="badge <?= htmlspecialchars($status[1]) ?>"><?= htmlspecialchars($status[0]) ?></span>
    </div>

    <section class="kpi-grid">
        <a class="kpi-card kpi-link" href="my_grades.php"><div class="kpi-icon icon-green"><?= svgIcon('trend') ?></div><div class="kpi-title">Средний балл</div><div class="kpi-value"><?= $avgGrade ?: '-' ?></div><div class="kpi-note">по 100-бальной системе</div></a>
        <a class="kpi-card kpi-link" href="attendance.php"><div class="kpi-icon icon-orange"><?= svgIcon('calendar') ?></div><div class="kpi-title">Посещаемость</div><div class="kpi-value"><?= $avgAttendance ?: '-' ?>%</div><div class="kpi-note">личная посещаемость</div></a>
        <a class="kpi-card kpi-link" href="my_grades.php?criterion=unsatisfactory"><div class="kpi-icon icon-red"><?= svgIcon('alert') ?></div><div class="kpi-title">Критерий 2</div><div class="kpi-value"><?= (int)$gradeDist['unsatisfactory'] ?></div><div class="kpi-note">неудовлетворительных оценок</div></a>
        <a class="kpi-card kpi-link" href="reports.php"><div class="kpi-icon icon-blue"><?= svgIcon('file') ?></div><div class="kpi-title">Личный отчёт</div><div class="kpi-value">PDF</div><div class="kpi-note">печать и Excel</div></a>
    </section>

    <div class="quick-actions no-print">
        <a class="btn btn-primary" href="my_grades.php">Открыть мои оценки</a>
        <a class="btn btn-success" href="attendance.php">Открыть посещаемость</a>
        <a class="btn btn-warning" href="reports.php">Сформировать отчёт</a>
    </div>

    <section class="criteria grade-criteria-grid">
        <a class="criteria-card kpi-link" href="my_grades.php?criterion=excellent"><div class="criteria-title">5</div><div class="criteria-value"><?= (int)$gradeDist['excellent'] ?></div><div class="criteria-note">90–100</div></a>
        <a class="criteria-card kpi-link" href="my_grades.php?criterion=good"><div class="criteria-title">4</div><div class="criteria-value"><?= (int)$gradeDist['good'] ?></div><div class="criteria-note">70–89</div></a>
        <a class="criteria-card kpi-link" href="my_grades.php?criterion=satisfactory"><div class="criteria-title">3</div><div class="criteria-value"><?= (int)$gradeDist['satisfactory'] ?></div><div class="criteria-note">51–69</div></a>
        <a class="criteria-card kpi-link" href="my_grades.php?criterion=unsatisfactory"><div class="criteria-title">2</div><div class="criteria-value"><?= (int)$gradeDist['unsatisfactory'] ?></div><div class="criteria-note">0–50</div></a>
    </section>

    <div class="profile-grid">
        <div class="profile-card">
            <div class="table-header" style="padding:0 0 14px;border-bottom:1px solid var(--line);margin-bottom:8px">Рекомендации по критериям</div>
            <div class="mini-list">
                <?php foreach ($recommendations as $item): ?>
                <div class="mini-item"><div><div class="mini-title"><?= htmlspecialchars($item[0]) ?> — <?= htmlspecialchars($item[1]) ?></div><div class="mini-sub"><?= htmlspecialchars($item[2]) ?></div></div></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="profile-card">
            <div class="table-header" style="padding:0 0 14px;border-bottom:1px solid var(--line);margin-bottom:8px">Последние оценки</div>
            <div class="mini-list">
                <?php foreach ($recentGrades as $row): ?>
                <div class="mini-item"><div><div class="mini-title"><?= htmlspecialchars($row['subject_name']) ?></div><div class="mini-sub"><?= $row['grade_date'] ? htmlspecialchars(date('d.m.Y', strtotime($row['grade_date']))) : 'Дата не указана' ?></div></div><span class="grade-pill <?= htmlspecialchars(gradeCategoryCss($row['grade'])) ?>"><?= (int)$row['grade'] ?></span></div>
                <?php endforeach; ?>
                <?php if (!$recentGrades): ?><div class="mini-item"><div class="mini-title">Оценок пока нет</div></div><?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="notice notice-info">Профиль студента не найден.</div>
    <?php endif; ?>
    </main>
    </div>
    <?php include 'includes/footer.php';
    exit;
}

$students = [];
$allStudents = [];
$allowedGroupIds = getAllowedGroupIds($pdo);

$criteria = $_GET['criteria'] ?? '';
$value = $_GET['value'] ?? '';
$allowedCriteria = ['group','grade','attendance','status'];
if (!in_array($criteria, $allowedCriteria, true)) {
    $criteria = '';
    $value = '';
}

function studentMatchesFilter(array $student, string $criteria, string $value): bool
{
    if ($criteria === '' || $value === '') {
        return true;
    }

    $avgGrade = $student['avg_grade'] === null ? 0 : (float)$student['avg_grade'];
    $attendance = $student['attendance'] === null ? 0 : (float)$student['attendance'];
    $badGrades = (int)($student['bad_grades'] ?? 0);
    $status = studentStatusByCriteria($avgGrade, $attendance, $badGrades)[0];

    switch ($criteria) {
        case 'group':
            return (string)$student['group_id'] === (string)$value;

        case 'grade':
            if ($value === 'excellent') return $avgGrade >= 90;
            if ($value === 'good') return $avgGrade >= 70 && $avgGrade < 90;
            if ($value === 'satisfactory') return $avgGrade >= 51 && $avgGrade < 70;
            if ($value === 'unsatisfactory') return $avgGrade > 0 && $avgGrade <= 50;
            if ($value === 'none') return $student['avg_grade'] === null;
            return true;

        case 'attendance':
            if ($value === 'excellent') return $attendance >= 85;
            if ($value === 'good') return $attendance >= 75 && $attendance < 85;
            if ($value === 'control') return $attendance >= 65 && $attendance < 75;
            if ($value === 'problem') return $attendance > 0 && $attendance < 65;
            if ($value === 'none') return $student['attendance'] === null;
            return true;

        case 'status':
            return $status === $value;
    }

    return true;
}

$availableGroups = [];
if ($allowedGroupIds) {
    $in = placeholders($allowedGroupIds);
    $stmt = $pdo->prepare("SELECT id, name FROM groups WHERE id IN ($in) ORDER BY name");
    $stmt->execute($allowedGroupIds);
    $availableGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "
        SELECT
            s.id,
            s.group_id,
            u.full_name,
            g.name AS group_name,
            ROUND(AVG(gr.grade),2) AS avg_grade,
            ROUND(AVG(a.percent),0) AS attendance,
            SUM(CASE WHEN gr.grade BETWEEN 0 AND 50 THEN 1 ELSE 0 END) AS bad_grades
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN groups g ON s.group_id = g.id
        LEFT JOIN grades gr ON gr.student_id = s.id
        LEFT JOIN attendance a ON a.student_id = s.id
        WHERE s.group_id IN ($in)
        GROUP BY s.id, s.group_id, u.full_name, g.name
        ORDER BY g.name, u.full_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allowedGroupIds);
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $students = array_values(array_filter($allStudents, function ($student) use ($criteria, $value) {
        return studentMatchesFilter($student, $criteria, $value);
    }));
}

$filterTitles = [
    'group' => 'по группе',
    'grade' => 'по среднему баллу',
    'attendance' => 'по посещаемости',
    'status' => 'по статусу студента',
];

$valueTitles = [
    'grade' => [
        'excellent' => 'отлично: 90–100',
        'good' => 'хорошо: 70–89',
        'satisfactory' => 'удовлетворительно: 51–69',
        'unsatisfactory' => 'неудовлетворительно: 0–50',
        'none' => 'без оценок',
    ],
    'attendance' => [
        'excellent' => 'отлично: 85–100%',
        'good' => 'хорошо: 75–84%',
        'control' => 'требует контроля: 65–74%',
        'problem' => 'проблемная: ниже 65%',
        'none' => 'без данных',
    ],
    'status' => [
        'Стабильно' => 'Стабильно',
        'Требует внимания' => 'Требует внимания',
        'Риск' => 'Риск',
    ],
];
foreach ($availableGroups as $group) {
    $valueTitles['group'][(string)$group['id']] = $group['name'];
}

$activeFilterText = 'Все студенты';
if ($criteria && $value) {
    $activeFilterText = 'Критерий: ' . ($filterTitles[$criteria] ?? '') . ' / ' . ($valueTitles[$criteria][$value] ?? $value);
}

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="content">
<?php include 'includes/topbar.php'; ?>
<section class="page-head">
<h1 class="page-title">Студенты</h1>
<div class="page-subtitle">Данные отображаются с учётом прав доступа текущей роли</div>
</section>

<div class="filter-card">
    <form class="filter-form" method="get" action="students.php">
        <div class="filter-field">
            <label for="criteria">Критерий</label>
            <select class="form-select" id="criteria" name="criteria">
                <option value="" <?= $criteria==='' ? 'selected' : '' ?>>Все критерии</option>
                <option value="group" <?= $criteria==='group' ? 'selected' : '' ?>>По группе</option>
                <option value="grade" <?= $criteria==='grade' ? 'selected' : '' ?>>По среднему баллу</option>
                <option value="attendance" <?= $criteria==='attendance' ? 'selected' : '' ?>>По посещаемости</option>
                <option value="status" <?= $criteria==='status' ? 'selected' : '' ?>>По статусу студента</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="value">Подпункт</label>
            <select class="form-select" id="value" name="value" data-selected="<?= htmlspecialchars($value) ?>">
                <option value="">Все подпункты</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Применить</button>
        <a class="btn" href="students.php">Сбросить</a>
    </form>
    <div class="filter-summary">
        <span class="badge badge-blue"><?= htmlspecialchars($activeFilterText) ?></span>
        <span class="filter-count">Показано студентов: <?= count($students) ?> из <?= count($allStudents) ?></span>
    </div>
</div>

<div class="table-card">
<div class="table-header">Список студентов</div>
<div class="table-responsive">
<table>
<thead>
<tr>
    <th>ФИО</th>
    <th>Группа</th>
    <th class="metric-col">Средний балл</th>
    <th class="metric-col">Посещаемость</th>
    <th>Статус</th>
</tr>
</thead>
<tbody>
<?php foreach($students as $student): ?>
<tr>
    <td><?= htmlspecialchars(formatPersonName($student['full_name'])) ?></td>
    <td><?= htmlspecialchars($student['group_name']) ?></td>
    <td class="metric-cell"><div class="metric-box"><span class="metric-value"><?= $student['avg_grade'] ?? '-' ?></span><span class="metric-note">из 100</span></div></td>
    <td class="metric-cell"><div class="metric-box"><span class="metric-value"><?= $student['attendance'] ?? '-' ?>%</span></div></td>
    <?php $studentStatus = studentStatusByCriteria($student['avg_grade'], $student['attendance'], (int)($student['bad_grades'] ?? 0)); ?>
    <td><span class="badge <?= htmlspecialchars($studentStatus[1]) ?>"><?= htmlspecialchars($studentStatus[0]) ?></span></td>
</tr>
<?php endforeach; ?>
<?php if (!$students): ?>
<tr><td colspan="5">Нет доступных студентов для этой роли.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</main>
</div>
<script>
(function(){
    const values = <?= json_encode($valueTitles, JSON_UNESCAPED_UNICODE) ?>;
    const criteria = document.getElementById('criteria');
    const value = document.getElementById('value');
    if (!criteria || !value) return;
    const selected = value.dataset.selected || '';
    function fillValues(){
        const list = values[criteria.value] || {};
        value.innerHTML = '<option value="">Все подпункты</option>';
        Object.entries(list).forEach(([val,label]) => {
            const option = document.createElement('option');
            option.value = val;
            option.textContent = label;
            if (val === selected) option.selected = true;
            value.appendChild(option);
        });
        value.disabled = Object.keys(list).length === 0;
    }
    criteria.addEventListener('change', () => { value.dataset.selected = ''; fillValues(); });
    fillValues();
})();
</script>
<?php include 'includes/footer.php'; ?>
