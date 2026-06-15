<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';
require_once 'includes/attendance_provider.php';

if (isStudent()) {
    header('Location: students.php');
    exit;
}

$teacherId = getTeacherId($pdo);
$allowedGroupIds = getAllowedGroupIds($pdo);
$message = '';
$error = '';

// Очистка старых демонстрационных оценок из прошлой 5-балльной версии журнала.
// Сейчас журнал работает только по 100-балльной системе, поэтому значения 2, 3, 4 и 5 удаляются как старые тестовые данные.
try {
    $pdo->exec("DELETE FROM grades WHERE grade IN (2, 3, 4, 5)");
} catch (Throwable $e) {
    // Если таблица ещё не создана при первом импорте, просто продолжаем загрузку страницы.
}

// Поле даты нужно для фильтра "Период" в сохранённых оценках.
// Если проект запускается на уже импортированной базе, колонка добавится автоматически.
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM grades LIKE 'grade_date'");
    if (!$columnCheck->fetch()) {
        $pdo->exec("ALTER TABLE grades ADD grade_date DATE NULL AFTER grade");
        $pdo->exec("UPDATE grades SET grade_date = CURDATE() WHERE grade_date IS NULL");
    }
} catch (Throwable $e) {
    // Для первого запуска без таблицы grades просто продолжаем загрузку страницы.
}

function allowedValueInt($value, array $allowed): int
{
    $id = (int)$value;
    return in_array($id, $allowed, true) ? $id : 0;
}

function groupCourseKeyFromName(string $name): string
{
    if (preg_match('/-(25|24|23|22)$/', trim($name), $m)) {
        return ['25' => '1', '24' => '2', '23' => '3', '22' => '4'][$m[1]] ?? 'unknown';
    }
    return 'unknown';
}

function groupCourseTitleFromName(string $name): string
{
    $key = groupCourseKeyFromName($name);
    return ['1' => '1 курс', '2' => '2 курс', '3' => '3 курс', '4' => '4 курс'][$key] ?? 'Не указан';
}

function gradeCriteriaSql(string $level): string
{
    if ($level === 'excellent') return 'gr.grade BETWEEN 90 AND 100';
    if ($level === 'good') return 'gr.grade BETWEEN 70 AND 89';
    if ($level === 'satisfactory') return 'gr.grade BETWEEN 51 AND 69';
    if ($level === 'unsatisfactory') return 'gr.grade BETWEEN 0 AND 50';
    return '1=1';
}

function periodFilterSql(string $period): string
{
    if ($period === 'today') return 'gr.grade_date = CURDATE()';
    if ($period === 'week') return 'YEARWEEK(gr.grade_date, 1) = YEARWEEK(CURDATE(), 1)';
    if ($period === 'month') return 'YEAR(gr.grade_date) = YEAR(CURDATE()) AND MONTH(gr.grade_date) = MONTH(CURDATE())';
    if ($period === 'term') return 'gr.grade_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)';
    if ($period === 'year') return 'gr.grade_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)';
    return '1=1';
}

$groupId = allowedValueInt($_GET['group_id'] ?? ($_POST['group_id'] ?? 0), $allowedGroupIds);
if (!$groupId && $allowedGroupIds) {
    $groupId = (int)$allowedGroupIds[0];
}
$lessonDate = $_GET['lesson_date'] ?? ($_POST['lesson_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lessonDate)) {
    $lessonDate = date('Y-m-d');
}

$groups = [];
if ($allowedGroupIds) {
    $in = placeholders($allowedGroupIds);
    $stmt = $pdo->prepare("SELECT id, name FROM groups WHERE id IN ($in) ORDER BY name");
    $stmt->execute($allowedGroupIds);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$subjects = [];
if ($groupId) {
    if (isTeacher() && $teacherId) {
        $stmt = $pdo->prepare("SELECT DISTINCT sub.id, sub.name FROM subjects sub JOIN subject_groups sg ON sg.subject_id = sub.id WHERE sg.group_id = ? AND sub.teacher_id = ? ORDER BY sub.name");
        $stmt->execute([$groupId, $teacherId]);
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT sub.id, sub.name FROM subjects sub JOIN subject_groups sg ON sg.subject_id = sub.id WHERE sg.group_id = ? ORDER BY sub.name");
        $stmt->execute([$groupId]);
    }
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$subjectIds = [];
foreach ($subjects as $subjectRow) {
    $subjectIds[] = (int)$subjectRow['id'];
}
$subjectId = allowedValueInt($_GET['subject_id'] ?? ($_POST['subject_id'] ?? 0), $subjectIds);
if (!$subjectId && $subjectIds) {
    $subjectId = (int)$subjectIds[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $groupId && $subjectId) {
    $studentIds = [];
    $stmt = $pdo->prepare("SELECT id FROM students WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $studentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $grades = $_POST['grades'] ?? [];
    $savedGrades = 0;

    foreach ($studentIds as $studentId) {
        $grade = trim((string)($grades[$studentId] ?? ''));
        if ($grade !== '') {
            $gradeValue = filter_var($grade, FILTER_VALIDATE_INT);
            if ($gradeValue !== false && $gradeValue >= 0 && $gradeValue <= 100) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE student_id = ? AND subject_id = ?");
                $check->execute([$studentId, $subjectId]);
                $exists = (int)$check->fetchColumn() > 0;

                if ($exists) {
                    $upd = $pdo->prepare("UPDATE grades SET grade = ?, grade_date = ? WHERE student_id = ? AND subject_id = ?");
                    $upd->execute([(int)$gradeValue, $lessonDate, $studentId, $subjectId]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO grades (student_id, subject_id, grade, grade_date) VALUES (?, ?, ?, ?)");
                    $ins->execute([$studentId, $subjectId, (int)$gradeValue, $lessonDate]);
                }
                $savedGrades++;
            } else {
                $error = 'Оценка должна быть целым числом от 0 до 100.';
            }
        }
    }
    if (!$error) {
        $message = 'Сохранено оценок: ' . $savedGrades . '. Данные перенесены в базу данных.';
    }
}

$students = [];
if ($groupId) {
    $stmt = $pdo->prepare("SELECT s.id, u.full_name, g.name AS group_name, ROUND(AVG(gr.grade),2) AS avg_grade, MAX(a.percent) AS attendance_percent
        FROM students s
        JOIN users u ON u.id = s.user_id
        JOIN groups g ON g.id = s.group_id
        LEFT JOIN grades gr ON gr.student_id = s.id
        LEFT JOIN attendance a ON a.student_id = s.id
        WHERE s.group_id = ?
        GROUP BY s.id, u.full_name, g.name
        ORDER BY u.full_name");
    $stmt->execute([$groupId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentGrades = [];
if ($groupId && $subjectId && $students) {
    $studentKeys = [];
    foreach ($students as $studentRow) {
        $studentKeys[] = (int)$studentRow['id'];
    }
    if ($studentKeys) {
        $inStudents = placeholders($studentKeys);
        $stmt = $pdo->prepare("SELECT gr.student_id, gr.grade
            FROM grades gr
            JOIN (SELECT student_id, subject_id, MAX(id) AS max_id FROM grades WHERE subject_id = ? AND student_id IN ($inStudents) GROUP BY student_id, subject_id) last_gr
                ON last_gr.max_id = gr.id");
        $stmt->execute(array_merge([$subjectId], $studentKeys));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $currentGrades[(int)$row['student_id']] = (int)$row['grade'];
        }
    }
}

$attendanceByStudent = [];
if ($groupId && $students) {
    $records = getAttendanceRecords($pdo, [$groupId], null, $lessonDate, $lessonDate);
    foreach ($records as $record) {
        $attendanceByStudent[(int)$record['student_id']] = $record;
    }
}


$savedGroupId = allowedValueInt($_GET['saved_group_id'] ?? 0, $allowedGroupIds);
$savedPeriod = $_GET['saved_period'] ?? 'all';
$allowedPeriods = ['all', 'today', 'week', 'month', 'term', 'year'];
if (!in_array($savedPeriod, $allowedPeriods, true)) {
    $savedPeriod = 'all';
}
$savedLevel = $_GET['saved_level'] ?? 'all';
$allowedLevels = ['all', 'excellent', 'good', 'satisfactory', 'unsatisfactory'];
if (!in_array($savedLevel, $allowedLevels, true)) {
    $savedLevel = 'all';
}

$savedSubjectOptions = [];
if ($allowedGroupIds) {
    $inGroups = placeholders($allowedGroupIds);
    if (isTeacher() && $teacherId) {
        $stmt = $pdo->prepare("SELECT DISTINCT sub.id, sub.name FROM subjects sub JOIN subject_groups sg ON sg.subject_id = sub.id WHERE sg.group_id IN ($inGroups) AND sub.teacher_id = ? ORDER BY sub.name");
        $stmt->execute(array_merge($allowedGroupIds, [$teacherId]));
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT sub.id, sub.name FROM subjects sub JOIN subject_groups sg ON sg.subject_id = sub.id WHERE sg.group_id IN ($inGroups) ORDER BY sub.name");
        $stmt->execute($allowedGroupIds);
    }
    $savedSubjectOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$savedSubjectIds = [];
foreach ($savedSubjectOptions as $subjectRow) {
    $savedSubjectIds[] = (int)$subjectRow['id'];
}
$savedSubjectId = allowedValueInt($_GET['saved_subject_id'] ?? 0, $savedSubjectIds);

$savedGradesList = [];
if ($allowedGroupIds) {
    $where = [];
    $params = [];
    $where[] = 's.group_id IN (' . placeholders($allowedGroupIds) . ')';
    $params = array_merge($params, $allowedGroupIds);

    if (isTeacher() && $teacherId) {
        $where[] = 'sub.teacher_id = ?';
        $params[] = $teacherId;
    }
    if ($savedGroupId) {
        $where[] = 's.group_id = ?';
        $params[] = $savedGroupId;
    }
    if ($savedSubjectId) {
        $where[] = 'sub.id = ?';
        $params[] = $savedSubjectId;
    }
    $periodSql = periodFilterSql($savedPeriod);
    if ($periodSql !== '1=1') {
        $where[] = $periodSql;
    }
    $levelSql = gradeCriteriaSql($savedLevel);
    if ($levelSql !== '1=1') {
        $where[] = $levelSql;
    }

    $sql = "SELECT gr.id, gr.grade, gr.grade_date, u.full_name, g.name AS group_name, sub.name AS subject_name
        FROM grades gr
        JOIN (SELECT student_id, subject_id, MAX(id) AS max_id FROM grades GROUP BY student_id, subject_id) last_gr
            ON last_gr.max_id = gr.id
        JOIN students s ON s.id = gr.student_id
        JOIN users u ON u.id = s.user_id
        JOIN groups g ON g.id = s.group_id
        JOIN subjects sub ON sub.id = gr.subject_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY g.name, sub.name, u.full_name
        LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $savedGradesList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="content">
<?php include 'includes/topbar.php'; ?>
<section class="page-head">
    <h1 class="page-title">Журнал оценок преподавателя</h1>
</section>

<?php if ($message): ?><div class="notice notice-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice notice-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="filter-card">
    <form class="filter-form journal-filter" method="get" action="journal.php">
        <div class="filter-field">
            <label>Группа</label>
            <select class="form-select" name="group_id" onchange="this.form.submit()">
                <?php foreach ($groups as $group): ?>
                    <option value="<?= (int)$group['id'] ?>" <?= (int)$group['id'] === $groupId ? 'selected' : '' ?>><?= htmlspecialchars($group['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label>Предмет</label>
            <select class="form-select" name="subject_id">
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int)$subject['id'] ?>" <?= (int)$subject['id'] === $subjectId ? 'selected' : '' ?>><?= htmlspecialchars($subject['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label>Дата занятия</label>
            <input class="form-control" type="date" name="lesson_date" value="<?= htmlspecialchars($lessonDate) ?>">
        </div>
        <button class="btn btn-primary" type="submit">Открыть</button>
    </form>
</div>

<div class="criteria attendance-legend">
    <div class="criteria-card"><div class="legend-dot legend-green"></div><div><div class="criteria-title">Зелёная отметка</div><div class="criteria-value">Пришёл без опоздания</div></div></div>
    <div class="criteria-card"><div class="legend-dot legend-yellow"></div><div><div class="criteria-title">Жёлтая отметка</div><div class="criteria-value">Пришёл, но опоздал</div></div></div>
    <div class="criteria-card"><div class="legend-dot legend-red"></div><div><div class="criteria-title">Красная отметка</div><div class="criteria-value">Отсутствовал</div></div></div>
</div>

<?php if (!$groups): ?>
    <div class="table-card"><div class="table-header">Нет доступных групп</div><div class="card-body">Для текущего преподавателя не назначены группы.</div></div>
<?php elseif (!$subjects): ?>
    <div class="table-card"><div class="table-header">Нет доступных предметов</div><div class="card-body">Для выбранной группы у преподавателя нет назначенного предмета.</div></div>
<?php else: ?>
<form method="post" action="journal.php" class="journal-form">
    <input type="hidden" name="group_id" value="<?= (int)$groupId ?>">
    <input type="hidden" name="subject_id" value="<?= (int)$subjectId ?>">
    <input type="hidden" name="lesson_date" value="<?= htmlspecialchars($lessonDate) ?>">
    <div class="table-card">
        <div class="table-header">
            <span>Оценки и просмотр посещаемости</span>
            <button class="btn btn-primary" type="submit">Сохранить оценки</button>
        </div>
        <div class="table-responsive">
            <table class="journal-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Студент</th>
                    <th class="metric-col">Средний балл</th>
                    <th class="metric-col">Посещаемость</th>
                    <th>Посещаемость из модуля</th>
                    <th>Оценка, 0–100</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $student): $sid = (int)$student['id']; $record = $attendanceByStudent[$sid] ?? null; ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars(formatPersonName($student['full_name'])) ?></td>
                        <td class="metric-cell"><div class="metric-box"><span class="metric-value"><?= $student['avg_grade'] ?: '-' ?></span><span class="metric-note">из 100</span></div></td>
                        <td class="metric-cell"><div class="metric-box"><span class="metric-value"><?= $student['attendance_percent'] ?: '-' ?>%</span><span class="metric-note">общая</span></div></td>
                        <td>
                            <?php if ($record): ?>
                                <span class="attendance-badge attendance-<?= htmlspecialchars($record['status']) ?>"><?= htmlspecialchars(attendanceStatusTitle($record['status'])) ?></span>
                            <?php else: ?>
                                <span class="attendance-badge attendance-empty">Нет данных</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input class="form-control grade-input" type="number" name="grades[<?= $sid ?>]" min="0" max="100" step="1" placeholder="0–100" value="<?= isset($currentGrades[$sid]) ? (int)$currentGrades[$sid] : '' ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$students): ?>
                    <tr><td colspan="6">В выбранной группе нет студентов.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
<?php endif; ?>

<div class="table-card saved-grades-card">
    <div class="table-header">
        <span>Сохранённые оценки</span>
        <span class="table-note">Показываются только оценки по доступным преподавателю предметам</span>
    </div>
    <div class="filter-card compact-filter">
        <form class="filter-form" method="get" action="journal.php">
            <input type="hidden" name="group_id" value="<?= (int)$groupId ?>">
            <input type="hidden" name="subject_id" value="<?= (int)$subjectId ?>">
            <input type="hidden" name="lesson_date" value="<?= htmlspecialchars($lessonDate) ?>">
            <div class="filter-field">
                <label>По группе</label>
                <select class="form-select" name="saved_group_id">
                    <option value="0">Все группы</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int)$group['id'] ?>" <?= (int)$group['id'] === $savedGroupId ? 'selected' : '' ?>><?= htmlspecialchars($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Период</label>
                <select class="form-select" name="saved_period">
                    <option value="all" <?= $savedPeriod === 'all' ? 'selected' : '' ?>>За всё время</option>
                    <option value="today" <?= $savedPeriod === 'today' ? 'selected' : '' ?>>Сегодня</option>
                    <option value="week" <?= $savedPeriod === 'week' ? 'selected' : '' ?>>Текущая неделя</option>
                    <option value="month" <?= $savedPeriod === 'month' ? 'selected' : '' ?>>Текущий месяц</option>
                    <option value="term" <?= $savedPeriod === 'term' ? 'selected' : '' ?>>Последние 6 месяцев</option>
                    <option value="year" <?= $savedPeriod === 'year' ? 'selected' : '' ?>>Последний год</option>
                </select>
            </div>
            <div class="filter-field">
                <label>По предмету</label>
                <select class="form-select" name="saved_subject_id">
                    <option value="0">Все предметы</option>
                    <?php foreach ($savedSubjectOptions as $subject): ?>
                        <option value="<?= (int)$subject['id'] ?>" <?= (int)$subject['id'] === $savedSubjectId ? 'selected' : '' ?>><?= htmlspecialchars($subject['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>По критерию</label>
                <select class="form-select" name="saved_level">
                    <option value="all" <?= $savedLevel === 'all' ? 'selected' : '' ?>>Все оценки</option>
                    <option value="excellent" <?= $savedLevel === 'excellent' ? 'selected' : '' ?>>5 — отлично</option>
                    <option value="good" <?= $savedLevel === 'good' ? 'selected' : '' ?>>4 — хорошо</option>
                    <option value="satisfactory" <?= $savedLevel === 'satisfactory' ? 'selected' : '' ?>>3 — удовлетворительно</option>
                    <option value="unsatisfactory" <?= $savedLevel === 'unsatisfactory' ? 'selected' : '' ?>>2 — неудовлетворительно</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Показать</button>
            <a class="btn btn-secondary" href="journal.php?group_id=<?= (int)$groupId ?>&subject_id=<?= (int)$subjectId ?>&lesson_date=<?= htmlspecialchars($lessonDate) ?>">Сбросить</a>
        </form>
    </div>
    <div class="table-responsive">
        <table class="journal-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Студент</th>
                <th>Группа</th>
                <th>Дата</th>
                <th>Предмет</th>
                <th>Баллы</th>
                <th>Эквивалент</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($savedGradesList as $i => $gradeRow): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars(formatPersonName($gradeRow['full_name'])) ?></td>
                    <td><?= htmlspecialchars($gradeRow['group_name']) ?></td>
                    <td><?= htmlspecialchars($gradeRow['grade_date'] ? date('d.m.Y', strtotime($gradeRow['grade_date'])) : 'Не указана') ?></td>
                    <td><?= htmlspecialchars($gradeRow['subject_name']) ?></td>
                    <td><span class="grade-pill grade-<?= htmlspecialchars(gradeCategoryKey($gradeRow['grade'])) ?>"><?= (int)$gradeRow['grade'] ?></span></td>
                    <td><?= htmlspecialchars(gradeCategoryTitle($gradeRow['grade'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$savedGradesList): ?>
                <tr><td colspan="7">По выбранным критериям оценок нет.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
</div>
<?php include 'includes/footer.php'; ?>
