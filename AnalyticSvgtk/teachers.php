<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isAdmin()) {
    header("Location: index.php");
    exit;
}

$criteria = $_GET['criteria'] ?? '';
$value = $_GET['value'] ?? '';
$allowedCriteria = ['group','subjects','groups_count','workload'];
if (!in_array($criteria, $allowedCriteria, true)) {
    $criteria = '';
    $value = '';
}

function teacherMatchesFilter(array $teacher, string $criteria, string $value): bool
{
    if ($criteria === '' || $value === '') {
        return true;
    }

    $subjectsCount = (int)$teacher['subjects_count'];
    $groupsCount = (int)$teacher['groups_count'];
    $groupIds = array_filter(explode(',', (string)($teacher['group_ids'] ?? '')));

    switch ($criteria) {
        case 'group':
            return in_array((string)$value, $groupIds, true);

        case 'subjects':
            if ($value === 'none') return $subjectsCount === 0;
            if ($value === 'one') return $subjectsCount === 1;
            if ($value === 'two') return $subjectsCount === 2;
            if ($value === 'threeplus') return $subjectsCount >= 3;
            return true;

        case 'groups_count':
            if ($value === 'none') return $groupsCount === 0;
            if ($value === 'one') return $groupsCount === 1;
            if ($value === 'two') return $groupsCount === 2;
            if ($value === 'threeplus') return $groupsCount >= 3;
            return true;

        case 'workload':
            if ($value === 'low') return $groupsCount <= 1;
            if ($value === 'medium') return $groupsCount >= 2 && $groupsCount <= 3;
            if ($value === 'high') return $groupsCount >= 4;
            return true;
    }

    return true;
}

$availableGroups = $pdo->query("SELECT id, name FROM groups ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$sql = "
SELECT
    t.id,
    u.full_name,
    COUNT(DISTINCT tg.group_id) AS groups_count,
    COUNT(DISTINCT s.id) AS subjects_count,
    GROUP_CONCAT(DISTINCT tg.group_id ORDER BY tg.group_id SEPARATOR ',') AS group_ids,
    GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS group_names
FROM teachers t
JOIN users u ON t.user_id = u.id
LEFT JOIN teacher_groups tg ON tg.teacher_id = t.id
LEFT JOIN groups g ON g.id = tg.group_id
LEFT JOIN subjects s ON s.teacher_id = t.id
GROUP BY t.id, u.full_name
ORDER BY u.full_name
";

$allTeachers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$teachers = array_values(array_filter($allTeachers, function ($teacher) use ($criteria, $value) {
    return teacherMatchesFilter($teacher, $criteria, $value);
}));

$filterTitles = [
    'group' => 'по группе',
    'subjects' => 'по количеству предметов',
    'groups_count' => 'по количеству групп',
    'workload' => 'по нагрузке',
];

$valueTitles = [
    'subjects' => [
        'none' => 'нет предметов',
        'one' => '1 предмет',
        'two' => '2 предмета',
        'threeplus' => '3 и более',
    ],
    'groups_count' => [
        'none' => 'нет групп',
        'one' => '1 группа',
        'two' => '2 группы',
        'threeplus' => '3 и более',
    ],
    'workload' => [
        'low' => 'низкая: до 1 группы',
        'medium' => 'средняя: 2–3 группы',
        'high' => 'высокая: 4 и более групп',
    ],
];
foreach ($availableGroups as $group) {
    $valueTitles['group'][(string)$group['id']] = $group['name'];
}

$activeFilterText = 'Все преподаватели';
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
<h1 class="page-title">Преподаватели</h1>
<div class="page-subtitle">Данные отображаются с учётом прав доступа текущей роли</div>
</section>

<div class="filter-card">
    <form class="filter-form" method="get" action="teachers.php">
        <div class="filter-field">
            <label for="criteria">Критерий</label>
            <select class="form-select" id="criteria" name="criteria">
                <option value="" <?= $criteria==='' ? 'selected' : '' ?>>Все критерии</option>
                <option value="group" <?= $criteria==='group' ? 'selected' : '' ?>>По группе</option>
                <option value="subjects" <?= $criteria==='subjects' ? 'selected' : '' ?>>По количеству предметов</option>
                <option value="groups_count" <?= $criteria==='groups_count' ? 'selected' : '' ?>>По количеству групп</option>
                <option value="workload" <?= $criteria==='workload' ? 'selected' : '' ?>>По нагрузке</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="value">Подпункт</label>
            <select class="form-select" id="value" name="value" data-selected="<?= htmlspecialchars($value) ?>">
                <option value="">Все подпункты</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Применить</button>
        <a class="btn" href="teachers.php">Сбросить</a>
    </form>
    <div class="filter-summary">
        <span class="badge badge-blue"><?= htmlspecialchars($activeFilterText) ?></span>
        <span class="filter-count">Показано преподавателей: <?= count($teachers) ?> из <?= count($allTeachers) ?></span>
    </div>
</div>

<div class="table-card">
<div class="table-header">Список преподавателей</div>
<div class="table-responsive">
<table>
<thead>
<tr>
    <th>ФИО</th>
    <th>Предметов</th>
    <th>Групп</th>
    <th>Закреплённые группы</th>
</tr>
</thead>
<tbody>
<?php foreach($teachers as $teacher): ?>
<tr>
<td><?= htmlspecialchars(formatPersonName($teacher['full_name'])) ?></td>
<td><?= (int)$teacher['subjects_count'] ?></td>
<td><?= (int)$teacher['groups_count'] ?></td>
<td><?= htmlspecialchars($teacher['group_names'] ?: 'Не закреплены') ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$teachers): ?>
<tr><td colspan="4">По выбранному критерию преподавателей нет.</td></tr>
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
