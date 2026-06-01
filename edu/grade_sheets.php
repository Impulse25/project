<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$role    = $_SESSION['role']    ?? 'guest';
$userId  = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $role === 'admin';

// Доступ: только admin и teacher
if (!in_array($role, ['admin', 'teacher'])) {
    header('Location: index.php');
    exit;
}

// ── Определяем группы, к которым пользователь имеет доступ ──────────────
// teacher видит только группы, где он куратор
// admin видит все группы
if ($isAdmin) {
    $myGroups = $pdo->query("SELECT id FROM edu_groups")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $myGroups = $pdo->prepare("SELECT id FROM edu_groups WHERE curator_id = ?");
    $myGroups->execute([$userId]);
    $myGroups = $myGroups->fetchAll(PDO::FETCH_COLUMN);
}

$message     = '';
$messageType = '';

// ── Удаление ведомости (только admin или владелец в статусе draft) ────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $row = $pdo->prepare("SELECT * FROM edu_grade_sheets WHERE id = ?");
    $row->execute([$id]);
    $row = $row->fetch(PDO::FETCH_ASSOC);
    if ($row && ($isAdmin || ($row['teacher_id'] == $userId && $row['status'] === 'draft'))) {
        $pdo->prepare("DELETE FROM edu_grade_sheets WHERE id = ?")->execute([$id]);
        $message = 'Ведомость удалена.'; $messageType = 'success';
    } else {
        $message = 'Нет доступа для удаления.'; $messageType = 'error';
    }
}

// ── Смена статуса ─────────────────────────────────────────────────────────
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id        = (int)$_GET['id'];
    $newStatus = $_GET['status'];
    $allowed   = ['submitted','approved','rejected','draft'];
    if (in_array($newStatus, $allowed)) {
        $row = $pdo->prepare("SELECT * FROM edu_grade_sheets WHERE id = ?");
        $row->execute([$id]);
        $row = $row->fetch(PDO::FETCH_ASSOC);
        $canChange = $isAdmin
            || ($row && $row['teacher_id'] == $userId && $newStatus === 'submitted' && $row['status'] === 'draft');
        if ($canChange) {
            $pdo->prepare("UPDATE edu_grade_sheets SET status=? WHERE id=?")->execute([$newStatus, $id]);
            $message = 'Статус обновлён.'; $messageType = 'success';
        }
    }
}

// ── Создание ведомости ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sheet'])) {
    $groupId    = (int)$_POST['group_id'];
    $subjectId  = (int)$_POST['subject_id'];
    $semesterId = (int)$_POST['semester_id'];
    $type       = $_POST['type'] ?? 'current';
    $teacherId  = $isAdmin ? ((int)($_POST['teacher_id'] ?? $userId)) : $userId;

    // Проверяем доступ к группе
    if (!in_array($groupId, $myGroups)) {
        $message = 'Нет доступа к выбранной группе.'; $messageType = 'error';
    } else {
        try {
            $pdo->prepare("
                INSERT INTO edu_grade_sheets (group_id, subject_id, semester_id, teacher_id, type)
                VALUES (?,?,?,?,?)
            ")->execute([$groupId, $subjectId, $semesterId, $teacherId, $type]);

            // Автозаполнение строк для всех студентов группы
            $sheetId  = $pdo->lastInsertId();
            $students = $pdo->prepare("SELECT id FROM edu_students WHERE group_id = ?");
            $students->execute([$groupId]);
            $ins = $pdo->prepare("INSERT IGNORE INTO edu_grades (grade_sheet_id, student_id) VALUES (?,?)");
            foreach ($students->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                $ins->execute([$sheetId, $sid]);
            }
            $message = 'Ведомость создана и заполнена студентами группы.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Ошибка: ' . $e->getMessage(); $messageType = 'error';
        }
    }
}

// ── Данные для форм ───────────────────────────────────────────────────────
if ($isAdmin) {
    $groups = $pdo->query("SELECT id, name FROM edu_groups ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $teachers = $pdo->query("SELECT id, username, full_name FROM users WHERE role IN ('teacher','admin') ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    if ($myGroups) {
        $in = implode(',', array_map('intval', $myGroups));
        $groups = $pdo->query("SELECT id, name FROM edu_groups WHERE id IN ($in) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $groups = [];
    }
    $teachers = [];
}
$subjects  = $pdo->query("SELECT id, code, name_ru FROM edu_subjects ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC);
$semesters = $pdo->query("SELECT id, year_start, year_end, semester_num FROM edu_semesters ORDER BY year_start DESC, semester_num")->fetchAll(PDO::FETCH_ASSOC);

// ── Список ведомостей ─────────────────────────────────────────────────────
$sqlSheets = "
    SELECT gs.*, g.name AS group_name,
           sub.name_ru AS subject_name, sub.code AS subject_code,
           sem.year_start, sem.year_end, sem.semester_num,
           u.full_name AS teacher_name, u.username AS teacher_login,
           (SELECT COUNT(*) FROM edu_grades eg WHERE eg.grade_sheet_id = gs.id) AS student_count,
           (SELECT COUNT(*) FROM edu_grades eg WHERE eg.grade_sheet_id = gs.id AND (eg.grade IS NOT NULL OR eg.passed = 1 OR eg.absent = 1)) AS graded_count
    FROM edu_grade_sheets gs
    LEFT JOIN edu_groups      g   ON g.id   = gs.group_id
    LEFT JOIN edu_subjects    sub ON sub.id = gs.subject_id
    LEFT JOIN edu_semesters   sem ON sem.id = gs.semester_id
    LEFT JOIN users           u   ON u.id   = gs.teacher_id
";
if (!$isAdmin) {
    if ($myGroups) {
        $in = implode(',', array_map('intval', $myGroups));
        $sqlSheets .= " WHERE gs.group_id IN ($in) AND gs.teacher_id = $userId";
    } else {
        $sqlSheets .= " WHERE 1=0";
    }
}
$sqlSheets .= " ORDER BY gs.created_at DESC";
$sheets = $pdo->query($sqlSheets)->fetchAll(PDO::FETCH_ASSOC);
$total  = count($sheets);

$TYPE_LABELS   = ['exam'=>'Экзамен','credit'=>'Зачёт','coursework'=>'Курсовая','practice'=>'Практика','current'=>'Тек. контроль'];
$STATUS_LABELS = ['draft'=>'Черновик','submitted'=>'На проверке','approved'=>'Утверждена','rejected'=>'Доработка'];
$STATUS_BADGE  = ['draft'=>'badge-gray','submitted'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'];

$pageTitle       = 'Ведомости — СВГТК Портал';
$activeNav       = 'edu';
$sidebarSubtitle = 'Учебный процесс';
$breadcrumbs     = [
    ['label'=>'СВГТК',           'href'=>'../'],
    ['label'=>'Учебный процесс', 'href'=>'index.php'],
    ['label'=>'Ведомости'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <?php require 'includes/head.php' ?>
  <style>
    .data-table { width:100%; min-width:700px; }
    .data-table th { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-muted); padding:.75rem 1rem; background:var(--color-surface-2); border-bottom:1px solid var(--color-divider); text-align:left; white-space:nowrap; }
    .data-table td { padding:.75rem 1rem; border-bottom:1px solid var(--color-divider); font-size:.9375rem; vertical-align:middle; }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tbody tr:hover { background:var(--color-primary-highlight); }
    .table-wrapper { overflow-x:auto; }
    .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; }
    .form-group { display:flex; flex-direction:column; gap:.375rem; }
    .form-group label { font-size:.8125rem; font-weight:500; color:var(--color-text-muted); }
    .form-group input, .form-group select { padding:.5rem .75rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); color:var(--color-text); font-size:.9375rem; transition:border-color var(--transition); }
    .form-group select:focus, .form-group input:focus { outline:none; border-color:var(--color-primary); }
    .progress-bar-wrap { width:80px; height:6px; background:var(--color-divider); border-radius:3px; overflow:hidden; display:inline-block; vertical-align:middle; margin-left:6px; }
    .progress-bar-fill { height:100%; background:var(--color-primary); border-radius:3px; transition:width .3s; }
    .empty-state { text-align:center; padding:3rem 1.5rem; }
    .empty-state-icon { width:72px; height:72px; border-radius:18px; background:var(--color-surface-offset); display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem; }
    .empty-state-title { font-weight:600; font-size:1.125rem; color:var(--color-text); margin-bottom:.5rem; }
    .empty-state-sub { font-size:.9375rem; color:var(--color-text-muted); }
    .action-btns { display:flex; gap:.375rem; flex-wrap:wrap; }
    .no-groups-warn { padding:1.25rem 1.5rem; background:color-mix(in srgb,var(--color-warning) 12%,transparent); border-radius:var(--radius-md); color:var(--color-text-muted); font-size:.9375rem; }
  </style>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Ведомости</h1>
        <p class="page-subtitle">Создание и управление экзаменационными ведомостями</p>
      </div>
      <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Назад к студентам
        </a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="margin-bottom:1rem">
      <?php if ($messageType==='success'): ?>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php else: ?>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?php endif ?>
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif ?>

    <?php if (!$isAdmin && empty($myGroups)): ?>
    <div class="no-groups-warn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Вы не назначены куратором ни одной группы. Обратитесь к администратору.
    </div>
    <?php else: ?>

    <!-- Форма создания -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          Создать ведомость
        </span>
      </div>
      <div class="card-body">
        <form method="POST" action="grade_sheets.php">
          <input type="hidden" name="create_sheet" value="1">
          <div class="form-grid">
            <div class="form-group">
              <label>Группа <span style="color:var(--color-danger)">*</span></label>
              <select name="group_id" required>
                <option value="">— выберите —</option>
                <?php foreach ($groups as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label>Дисциплина <span style="color:var(--color-danger)">*</span></label>
              <select name="subject_id" required>
                <option value="">— выберите —</option>
                <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code'].' — '.$s['name_ru']) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label>Семестр <span style="color:var(--color-danger)">*</span></label>
              <select name="semester_id" required>
                <option value="">— выберите —</option>
                <?php foreach ($semesters as $sem): ?>
                <option value="<?= $sem['id'] ?>"><?= $sem['year_start'].'/'.$sem['year_end'].' — '.$sem['semester_num'].' сем.' ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label>Тип ведомости <span style="color:var(--color-danger)">*</span></label>
              <select name="type" required>
                <?php foreach ($TYPE_LABELS as $k=>$v): ?>
                <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <?php if ($isAdmin && $teachers): ?>
            <div class="form-group">
              <label>Преподаватель</label>
              <select name="teacher_id">
                <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $t['id']==$userId?'selected':'' ?>>
                  <?= htmlspecialchars($t['full_name'] ?: $t['username']) ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
            <?php endif ?>
          </div>
          <div style="margin-top:1.25rem">
            <button type="submit" class="btn btn-primary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Создать ведомость
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endif ?>

    <!-- Список ведомостей -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="3" y1="16" x2="21" y2="16"/><line x1="9" y1="4" x2="9" y2="22"/></svg>
          Список ведомостей
        </span>
        <span style="font-size:.875rem;color:var(--color-text-muted)"><?= $total ?> записей</span>
      </div>
      <?php if ($total > 0): ?>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>Группа</th><th>Дисциплина</th><th>Семестр</th>
              <th>Тип</th><th>Прогресс</th><th>Статус</th>
              <?php if ($isAdmin): ?><th>Преподаватель</th><?php endif ?>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sheets as $i => $sh):
              $pct = $sh['student_count'] > 0 ? round($sh['graded_count'] / $sh['student_count'] * 100) : 0;
              $canEdit = $isAdmin || ($sh['teacher_id'] == $userId && $sh['status'] === 'draft');
            ?>
            <tr>
              <td style="color:var(--color-text-muted)"><?= $i+1 ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($sh['group_name']) ?></td>
              <td>
                <div style="font-weight:500"><?= htmlspecialchars($sh['subject_name']) ?></div>
                <div style="font-size:.75rem;color:var(--color-text-muted)"><?= htmlspecialchars($sh['subject_code']) ?></div>
              </td>
              <td style="white-space:nowrap"><?= $sh['year_start'].'/'.$sh['year_end'] ?> · <?= $sh['semester_num'] ?> сем.</td>
              <td><span class="badge badge-gray"><?= $TYPE_LABELS[$sh['type']] ?? $sh['type'] ?></span></td>
              <td style="white-space:nowrap">
                <span style="font-size:.8125rem;font-variant-numeric:tabular-nums"><?= $sh['graded_count'] ?>/<?= $sh['student_count'] ?></span>
                <span class="progress-bar-wrap"><span class="progress-bar-fill" style="width:<?= $pct ?>%"></span></span>
              </td>
              <td><span class="badge <?= $STATUS_BADGE[$sh['status']] ?? 'badge-gray' ?>"><?= $STATUS_LABELS[$sh['status']] ?? $sh['status'] ?></span></td>
              <?php if ($isAdmin): ?>
              <td style="font-size:.875rem;color:var(--color-text-muted)"><?= htmlspecialchars($sh['teacher_name'] ?: $sh['teacher_login']) ?></td>
              <?php endif ?>
              <td>
                <div class="action-btns">
                  <a href="export_grade_sheet.php?sheet_id=<?= $sh['id'] ?>" class="btn btn-outline" style="padding:.3rem .7rem;font-size:.8125rem" title="Экспорт зачетной ведомости">DOCX</a>
                  <a href="export_semester_summary.php?group_id=<?= $sh['group_id'] ?>&semester_id=<?= $sh['semester_id'] ?>" class="btn btn-outline" style="padding:.3rem .7rem;font-size:.8125rem" title="Итоговая за семестр">XLSX</a>

                  <?php if ($canEdit): ?>
                  <a href="grades.php?sheet_id=<?= $sh['id'] ?>" class="btn btn-primary" style="padding:.3rem .7rem;font-size:.8125rem">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Заполнить
                  </a>
                  <?php else: ?>
                  <a href="grades.php?sheet_id=<?= $sh['id'] ?>" class="btn btn-outline" style="padding:.3rem .7rem;font-size:.8125rem">Просмотр</a>
                  <?php endif ?>

                  <?php if ($sh['status'] === 'draft' && $sh['teacher_id'] == $userId): ?>
                  <a href="grade_sheets.php?id=<?= $sh['id'] ?>&status=submitted"
                     class="btn btn-outline" style="padding:.3rem .6rem;font-size:.8125rem"
                     title="Сдать на проверку"
                     onclick="return confirm('Сдать ведомость на проверку? После этого редактирование будет закрыто.')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M3 17v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/></svg>
                  </a>
                  <?php endif ?>

                  <?php if ($isAdmin): ?>
                  <?php if ($sh['status'] === 'submitted'): ?>
                  <a href="grade_sheets.php?id=<?= $sh['id'] ?>&status=approved" class="btn btn-success" style="padding:.3rem .6rem;font-size:.8125rem" title="Утвердить">✓</a>
                  <a href="grade_sheets.php?id=<?= $sh['id'] ?>&status=rejected" class="btn btn-outline" style="padding:.3rem .6rem;font-size:.8125rem" title="Вернуть">↩</a>
                  <?php endif ?>
                  <a href="grade_sheets.php?delete=<?= $sh['id'] ?>"
                     class="btn btn-danger" style="padding:.3rem .6rem;font-size:.8125rem"
                     onclick="return confirm('Удалить ведомость?')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                  </a>
                  <?php endif ?>
                </div>
              </td>
            </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-faint)" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="empty-state-title">Нет ведомостей</div>
        <div class="empty-state-sub">Создайте первую ведомость с помощью формы выше</div>
      </div>
      <?php endif ?>
    </div>

  </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
