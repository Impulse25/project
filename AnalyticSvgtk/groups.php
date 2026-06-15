<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';

if (isStudent()) {
    header("Location: students.php");
    exit;
}

function groupCourseCode(string $name): string
{
    if (preg_match('/-(\d{2})$/u', trim($name), $m)) {
        return [
            '25' => '1',
            '24' => '2',
            '23' => '3',
            '22' => '4',
        ][$m[1]] ?? 'unknown';
    }
    return 'unknown';
}

function groupCourseTitle(string $code): string
{
    return [
        '1' => '1 курс',
        '2' => '2 курс',
        '3' => '3 курс',
        '4' => '4 курс',
        'graduates' => 'Выпускники',
    ][$code] ?? 'Не определён';
}

function matchGroupFilter(array $group, string $criteria, string $value): bool
{
    if ($criteria === '' || $value === '') {
        return true;
    }

    $avgGrade = $group['avg_grade'] === null ? 0 : (float)$group['avg_grade'];
    $avgAttendance = $group['avg_attendance'] === null ? 0 : (float)$group['avg_attendance'];
    $count = (int)$group['students_count'];

    switch ($criteria) {
        case 'course':
            if ($value === 'graduates') {
                return (int)$group['graduates_count'] > 0;
            }
            return $group['course_code'] === $value;

        case 'status':
            return $group['status'] === $value;

        case 'students':
            if ($value === 'to10') return $count <= 10;
            if ($value === '11-20') return $count >= 11 && $count <= 20;
            if ($value === '21-30') return $count >= 21 && $count <= 30;
            if ($value === '31plus') return $count >= 31;
            return true;

        case 'grade':
            if ($value === 'excellent') return $avgGrade >= 90;
            if ($value === 'good') return $avgGrade >= 70 && $avgGrade < 90;
            if ($value === 'control') return $avgGrade >= 51 && $avgGrade < 70;
            if ($value === 'problem') return $avgGrade >= 0 && $avgGrade <= 50;
            return true;

        case 'attendance':
            if ($value === 'excellent') return $avgAttendance >= 85;
            if ($value === 'good') return $avgAttendance >= 75 && $avgAttendance < 85;
            if ($value === 'control') return $avgAttendance >= 65 && $avgAttendance < 75;
            if ($value === 'problem') return $avgAttendance > 0 && $avgAttendance < 65;
            return true;
    }

    return true;
}

$criteria = $_GET['criteria'] ?? '';
$value = $_GET['value'] ?? '';
$allowedCriteria = ['course','status','students','grade','attendance'];
if (!in_array($criteria, $allowedCriteria, true)) {
    $criteria = '';
    $value = '';
}

$filterTitles = [
    'course' => 'по курсам',
    'status' => 'по статусу',
    'students' => 'по количеству студентов',
    'grade' => 'по среднему баллу',
    'attendance' => 'по проценту посещаемости',
];

$valueTitles = [
    'course' => ['1'=>'1 курс','2'=>'2 курс','3'=>'3 курс','4'=>'4 курс','graduates'=>'Выпускники'],
    'status' => ['Стабильная'=>'Стабильная','Требует контроля'=>'Требует контроля','Проблемная'=>'Проблемная'],
    'students' => ['to10'=>'до 10 студентов','11-20'=>'11–20 студентов','21-30'=>'21–30 студентов','31plus'=>'31 и более'],
    'grade' => ['excellent'=>'отлично: 90–100','good'=>'хорошо: 70–89','control'=>'удовлетворительно: 51–69','problem'=>'неудовлетворительно: 0–50'],
    'attendance' => ['excellent'=>'отлично: 85–100%','good'=>'хорошо: 75–84%','control'=>'требует контроля: 65–74%','problem'=>'проблемная: ниже 65%'],
];

$allowedGroupIds = getAllowedGroupIds($pdo);
$groups = [];
$allGroups = [];

if ($allowedGroupIds) {
    $in = placeholders($allowedGroupIds);
    $sql = "
        SELECT
            g.id,
            g.name,
            g.status,
            COUNT(DISTINCT s.id) AS students_count,
            ROUND(AVG(gr.grade),2) AS avg_grade,
            ROUND(AVG(a.percent),0) AS avg_attendance,
            COUNT(DISTINCT grad.id) AS graduates_count
        FROM groups g
        LEFT JOIN students s ON s.group_id = g.id
        LEFT JOIN grades gr ON gr.student_id = s.id
        LEFT JOIN attendance a ON a.student_id = s.id
        LEFT JOIN graduates grad ON grad.group_id = g.id
        WHERE g.id IN ($in)
        GROUP BY g.id, g.name, g.status
        ORDER BY g.name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allowedGroupIds);
    $allGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allGroups as &$group) {
        $group['course_code'] = groupCourseCode($group['name']);
        $group['course_title'] = groupCourseTitle($group['course_code']);
        $group['filter_match'] = matchGroupFilter($group, $criteria, $value);
    }
    unset($group);

    $groups = array_values(array_filter($allGroups, function ($group) {
        return !empty($group['filter_match']);
    }));
}

$activeFilterText = 'Все группы';
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
<h1 class="page-title">Аналитика групп</h1>
<div class="page-subtitle">Группы распределяются по курсам, статусам, количеству студентов, среднему баллу и посещаемости</div>
</section>

<div class="filter-card">
    <form class="filter-form" method="get" action="groups.php">
        <div class="filter-field">
            <label for="criteria">Критерий</label>
            <select class="form-select" id="criteria" name="criteria">
                <option value="" <?= $criteria==='' ? 'selected' : '' ?>>Все критерии</option>
                <option value="course" <?= $criteria==='course' ? 'selected' : '' ?>>По курсам</option>
                <option value="status" <?= $criteria==='status' ? 'selected' : '' ?>>По статусу</option>
                <option value="students" <?= $criteria==='students' ? 'selected' : '' ?>>По количеству студентов</option>
                <option value="grade" <?= $criteria==='grade' ? 'selected' : '' ?>>По среднему баллу</option>
                <option value="attendance" <?= $criteria==='attendance' ? 'selected' : '' ?>>По проценту посещаемости</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="value">Подпункт</label>
            <select class="form-select" id="value" name="value" data-selected="<?= htmlspecialchars($value) ?>">
                <option value="">Все подпункты</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Применить</button>
        <a class="btn" href="groups.php">Сбросить</a>
    </form>
    <div class="filter-summary">
        <span class="badge badge-blue"><?= htmlspecialchars($activeFilterText) ?></span>
        <span class="filter-count">Показано групп: <?= count($groups) ?> из <?= count($allGroups) ?></span>
    </div>
</div>

<div class="table-card">
    <div class="table-header">Список учебных групп</div>
    <div class="table-responsive">
<table>
        <thead>
        <tr>
            <th>Группа</th>
            <th>Курс</th>
            <th>Студентов</th>
            <th>Средний балл</th>
            <th>Посещаемость</th>
            <th>Статус</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($groups as $group): ?>
            <tr>
                <td><?= htmlspecialchars($group['name']) ?></td>
                <td>
                    <span class="badge badge-blue"><?= htmlspecialchars($group['course_title']) ?></span>
                    <?php if ((int)$group['graduates_count'] > 0): ?>
                        <span class="badge badge-warning">Выпускники</span>
                    <?php endif; ?>
                </td>
                <td><?= (int)$group['students_count'] ?></td>
                <td><?= $group['avg_grade'] ?? '-' ?></td>
                <td><?= $group['avg_attendance'] ?? '-' ?>%</td>
                <td>
                    <?php
                    $class = 'badge-warning';
                    if($group['status'] == 'Стабильная') $class = 'badge-success';
                    if($group['status'] == 'Проблемная') $class = 'badge-danger';
                    ?>
                    <span class="badge <?= $class ?>"><?= htmlspecialchars($group['status']) ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$groups): ?>
            <tr><td colspan="6">По выбранному критерию групп нет.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>
</main>
</div>
<script>
(function(){
    const values = {
        course: [
            ['1','1 курс'], ['2','2 курс'], ['3','3 курс'], ['4','4 курс'], ['graduates','Выпускники']
        ],
        status: [
            ['Стабильная','Стабильная'], ['Требует контроля','Требует контроля'], ['Проблемная','Проблемная']
        ],
        students: [
            ['to10','до 10 студентов'], ['11-20','11–20 студентов'], ['21-30','21–30 студентов'], ['31plus','31 и более']
        ],
        grade: [
            ['excellent','отлично: 90–100'], ['good','хорошо: 70–89'], ['control','удовлетворительно: 51–69'], ['problem','неудовлетворительно: 0–50']
        ],
        attendance: [
            ['excellent','отлично: 85–100%'], ['good','хорошо: 75–84%'], ['control','требует контроля: 65–74%'], ['problem','проблемная: ниже 65%']
        ]
    };
    const criteria = document.getElementById('criteria');
    const value = document.getElementById('value');
    const selected = value.dataset.selected || '';
    function fillValues(){
        const list = values[criteria.value] || [];
        value.innerHTML = '<option value="">Все подпункты</option>';
        list.forEach(([val,label]) => {
            const option = document.createElement('option');
            option.value = val;
            option.textContent = label;
            if (val === selected) option.selected = true;
            value.appendChild(option);
        });
        value.disabled = list.length === 0;
    }
    criteria.addEventListener('change', () => { value.dataset.selected = ''; fillValues(); });
    fillValues();
})();
</script>
<?php include 'includes/footer.php'; ?>
