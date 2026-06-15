<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';
require_once 'includes/attendance_provider.php';

$allowedGroupIds = getAllowedGroupIds($pdo);
$studentId = getStudentId($pdo);

$period = $_GET['period'] ?? 'month';
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}
$dateFrom = $_GET['date_from'] ?? '';
if ($period === 'week') {
    $dateFrom = date('Y-m-d', strtotime($dateTo . ' -6 days'));
} elseif ($period === 'month') {
    $dateFrom = date('Y-m-d', strtotime($dateTo . ' -30 days'));
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-d', strtotime($dateTo . ' -30 days'));
    }
}

$groupId = (int)($_GET['group_id'] ?? 0);
if ($groupId && !in_array($groupId, $allowedGroupIds, true)) {
    $groupId = 0;
}
$selectedGroups = $groupId ? [$groupId] : $allowedGroupIds;
if (isStudent() && $studentId) {
    $selectedGroups = $allowedGroupIds;
}

$groups = [];
if ($allowedGroupIds) {
    $stmt = $pdo->prepare('SELECT id, name FROM groups WHERE id IN (' . placeholders($allowedGroupIds) . ') ORDER BY name');
    $stmt->execute($allowedGroupIds);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$records = getAttendanceRecords($pdo, $selectedGroups, $studentId, $dateFrom, $dateTo);
$stats = calculateAttendanceStats($records);

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="content">
<?php include 'includes/topbar.php'; ?>
<section class="page-head">
    <h1 class="page-title">Посещаемость</h1>
    <div class="page-subtitle">Просмотр данных из подключаемого модуля учёта посещаемости. Редактирование выполняется во внешнем модуле.</div>
</section>

<div class="notice notice-info">
    В этой версии используется демо-источник данных. После объединения проектов сюда можно подключить базу модуля посещаемости без изменения интерфейса аналитики.
</div>

<div class="print-only attendance-print-title">
    <h2>Отчёт по посещаемости</h2>
    <p>Период: <?= htmlspecialchars(date('d.m.Y', strtotime($dateFrom))) ?> — <?= htmlspecialchars(date('d.m.Y', strtotime($dateTo))) ?>. Роль: <?= htmlspecialchars(roleTitle()) ?>.</p>
</div>

<div class="filter-card no-print">
    <form class="filter-form attendance-filter" method="get" action="attendance.php">
        <?php if (!isStudent()): ?>
        <div class="filter-field">
            <label>Группа</label>
            <select class="form-select" name="group_id">
                <option value="0">Все доступные группы</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= (int)$group['id'] ?>" <?= (int)$group['id'] === $groupId ? 'selected' : '' ?>><?= htmlspecialchars($group['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-field">
            <label>Период</label>
            <select class="form-select" name="period" onchange="document.body.dataset.customPeriod=this.value==='custom'?'1':'0'">
                <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Неделя</option>
                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Месяц</option>
                <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Указанный срок</option>
            </select>
        </div>
        <div class="filter-field custom-date-field">
            <label>Начало</label>
            <input class="form-control" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="filter-field">
            <label>Конец</label>
            <input class="form-control" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <button class="btn btn-primary" type="submit">Показать</button>
        <button class="btn btn-secondary" type="button" onclick="printReport()">Печать</button>
    </form>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-title">Посещаемость</div><div class="kpi-value"><?= (int)$stats['percent'] ?>%</div><div class="kpi-note">по выбранному периоду</div></div>
    <div class="kpi-card"><div class="kpi-title">Всего часов</div><div class="kpi-value"><?= (int)$stats['total_hours'] ?></div><div class="kpi-note">по данным источника</div></div>
    <div class="kpi-card"><div class="kpi-title">Уважительные часы</div><div class="kpi-value"><?= (int)$stats['valid_absent_hours'] ?></div><div class="kpi-note">со справками и причинами</div></div>
    <div class="kpi-card"><div class="kpi-title">Неуважительные часы</div><div class="kpi-value"><?= (int)$stats['invalid_absent_hours'] ?></div><div class="kpi-note">требуют контроля</div></div>
</div>

<div class="criteria attendance-legend">
    <div class="criteria-card"><div class="legend-dot legend-green"></div><div><div class="criteria-title">Зелёная</div><div class="criteria-value">пришёл без опоздания</div></div></div>
    <div class="criteria-card"><div class="legend-dot legend-yellow"></div><div><div class="criteria-title">Жёлтая</div><div class="criteria-value">пришёл, но опоздал</div></div></div>
    <div class="criteria-card"><div class="legend-dot legend-red"></div><div><div class="criteria-title">Красная</div><div class="criteria-value">отсутствовал</div></div></div>
</div>

<div class="table-card">
    <div class="table-header">
        <span>Рапортички за период</span>
        <span class="table-muted">Источник: демо-модуль посещаемости</span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Студент</th>
                    <th>Группа</th>
                    <th>Часы</th>
                    <th>Статус</th>
                    <th>Причина</th>
                    <th>Справка</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d.m.Y', strtotime($record['record_date']))) ?></td>
                    <td><?= htmlspecialchars(formatPersonName($record['full_name'])) ?></td>
                    <td><?= htmlspecialchars($record['group_name']) ?></td>
                    <td><?= (int)$record['hours'] ?></td>
                    <td><span class="attendance-badge attendance-<?= htmlspecialchars($record['status']) ?>"><?= htmlspecialchars(attendanceStatusTitle($record['status'])) ?></span></td>
                    <td><?= htmlspecialchars(attendanceReasonTitle($record['reason_type'])) ?></td>
                    <td><?= $record['certificate_file'] ? htmlspecialchars($record['certificate_file']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?>
                <tr><td colspan="7">За выбранный период данных нет.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
</div>
<script>
document.body.dataset.customPeriod = <?= json_encode($period === 'custom' ? '1' : '0') ?>;
</script>
<?php include 'includes/footer.php'; ?>
