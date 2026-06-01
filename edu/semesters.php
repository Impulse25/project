<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$message     = '';
$messageType = '';

// ── Удаление ───────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM edu_semesters WHERE id = ?")->execute([(int)$_GET['delete']]);
        $message     = 'Семестр удалён.';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message     = 'Ошибка удаления: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ── Создание / редактирование ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $year_start   = (int)($_POST['year_start']   ?? 0);
    $year_end     = (int)($_POST['year_end']     ?? 0);
    $semester_num = (int)($_POST['semester_num'] ?? 0);
    $start_date   = trim($_POST['start_date']    ?? '');
    $end_date     = trim($_POST['end_date']      ?? '');

    if ($year_start < 2000 || $year_end < 2000 || !in_array($semester_num, [1,2]) || $start_date === '' || $end_date === '') {
        $message     = 'Заполните все обязательные поля корректно.';
        $messageType = 'error';
    } elseif ($year_end < $year_start) {
        $message     = 'Год окончания не может быть меньше года начала.';
        $messageType = 'error';
    } elseif ($end_date < $start_date) {
        $message     = 'Дата окончания не может быть раньше даты начала.';
        $messageType = 'error';
    } else {
        try {
            if ($_POST['action'] === 'create') {
                $pdo->prepare("INSERT INTO edu_semesters (year_start, year_end, semester_num, start_date, end_date) VALUES (?,?,?,?,?)")
                    ->execute([$year_start, $year_end, $semester_num, $start_date, $end_date]);
                $message = "Семестр {$year_start}/{$year_end} — {$semester_num} добавлен.";
            } else {
                $editId = (int)$_POST['edit_id'];
                $pdo->prepare("UPDATE edu_semesters SET year_start=?, year_end=?, semester_num=?, start_date=?, end_date=? WHERE id=?")
                    ->execute([$year_start, $year_end, $semester_num, $start_date, $end_date, $editId]);
                $message = "Семестр обновлён.";
            }
            $messageType = 'success';
        } catch (PDOException $e) {
            $message     = 'Ошибка: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM edu_semesters WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

$rows  = $pdo->query("SELECT * FROM edu_semesters ORDER BY year_start DESC, semester_num")->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);

// Определяем текущий семестр (по датам)
$today = date('Y-m-d');

$pageTitle       = 'Семестры — СВГТК Портал';
$activeNav       = 'edu';
$sidebarSubtitle = 'Учебный процесс';
$breadcrumbs     = [
    ['label' => 'СВГТК',          'href' => '../'],
    ['label' => 'Учебный процесс', 'href' => 'index.php'],
    ['label' => 'Семестры'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <?php require 'includes/head.php' ?>
  <style>
    .data-table { width:100%; min-width:600px; }
    .data-table th { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-muted); padding:.75rem 1rem; background:var(--color-surface-2); border-bottom:1px solid var(--color-divider); text-align:left; white-space:nowrap; }
    .data-table td { padding:.75rem 1rem; border-bottom:1px solid var(--color-divider); font-size:.9375rem; vertical-align:middle; }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tbody tr:hover { background:var(--color-primary-highlight); }
    .table-wrapper { overflow-x:auto; }
    .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:1rem; }
    .form-group { display:flex; flex-direction:column; gap:.375rem; }
    .form-group label { font-size:.8125rem; font-weight:500; color:var(--color-text-muted); }
    .form-group input, .form-group select { padding:.5rem .75rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); color:var(--color-text); font-size:.9375rem; transition:border-color var(--transition); }
    .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--color-primary); }
    .empty-state { text-align:center; padding:3rem 1.5rem; }
    .empty-state-icon { width:72px; height:72px; border-radius:18px; background:var(--color-surface-offset); display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem; }
    .empty-state-title { font-weight:600; font-size:1.125rem; color:var(--color-text); margin-bottom:.5rem; }
    .empty-state-sub { font-size:.9375rem; color:var(--color-text-muted); }
    .action-btns { display:flex; gap:.5rem; }
    .current-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.15rem .55rem; border-radius:99px; background:color-mix(in srgb,var(--color-success) 15%,transparent); color:var(--color-success); font-size:.75rem; font-weight:600; }
  </style>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Семестры</h1>
        <p class="page-subtitle">Справочник учебных семестров</p>
      </div>
      <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Назад к студентам
        </a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
      <?php if ($messageType === 'success'): ?>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php else: ?>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?php endif ?>
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif ?>

    <!-- Форма -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <?php if ($editRow): ?>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Редактировать семестр
          <?php else: ?>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          Добавить семестр
          <?php endif ?>
        </span>
        <?php if ($editRow): ?>
        <a href="semesters.php" class="btn btn-outline" style="padding:.3rem .75rem;font-size:.8125rem">Отмена</a>
        <?php endif ?>
      </div>
      <div class="card-body">
        <form method="POST" action="semesters.php">
          <input type="hidden" name="action"  value="<?= $editRow ? 'edit' : 'create' ?>">
          <?php if ($editRow): ?>
          <input type="hidden" name="edit_id" value="<?= $editRow['id'] ?>">
          <?php endif ?>
          <div class="form-grid">
            <div class="form-group">
              <label for="f_ys">Год начала уч. года <span style="color:var(--color-danger)">*</span></label>
              <input type="number" id="f_ys" name="year_start" min="2000" max="2099" required
                     placeholder="<?= date('Y') ?>"
                     value="<?= htmlspecialchars($editRow['year_start'] ?? date('Y')) ?>">
            </div>
            <div class="form-group">
              <label for="f_ye">Год окончания уч. года <span style="color:var(--color-danger)">*</span></label>
              <input type="number" id="f_ye" name="year_end" min="2000" max="2099" required
                     placeholder="<?= date('Y') + 1 ?>"
                     value="<?= htmlspecialchars($editRow['year_end'] ?? date('Y') + 1) ?>">
            </div>
            <div class="form-group">
              <label for="f_num">Номер семестра <span style="color:var(--color-danger)">*</span></label>
              <select id="f_num" name="semester_num" required>
                <option value="1" <?= ($editRow['semester_num'] ?? 1) == 1 ? 'selected' : '' ?>>1 семестр (осень)</option>
                <option value="2" <?= ($editRow['semester_num'] ?? 1) == 2 ? 'selected' : '' ?>>2 семестр (весна)</option>
              </select>
            </div>
            <div class="form-group">
              <label for="f_sd">Дата начала <span style="color:var(--color-danger)">*</span></label>
              <input type="date" id="f_sd" name="start_date" required
                     value="<?= htmlspecialchars($editRow['start_date'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="f_ed">Дата окончания <span style="color:var(--color-danger)">*</span></label>
              <input type="date" id="f_ed" name="end_date" required
                     value="<?= htmlspecialchars($editRow['end_date'] ?? '') ?>">
            </div>
          </div>
          <div style="margin-top:1.25rem">
            <button type="submit" class="btn btn-primary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              <?= $editRow ? 'Сохранить изменения' : 'Добавить семестр' ?>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Список -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="3" y1="16" x2="21" y2="16"/><line x1="9" y1="4" x2="9" y2="22"/></svg>
          Список семестров
        </span>
        <span style="font-size:.875rem;color:var(--color-text-muted)"><?= $total ?> записей</span>
      </div>
      <?php if ($total > 0): ?>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>Учебный год</th><th>Семестр</th>
              <th>Начало</th><th>Конец</th><th>Статус</th><th style="text-align:right">Действия</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r): ?>
            <?php $isCurrent = $today >= $r['start_date'] && $today <= $r['end_date']; ?>
            <tr <?= $isCurrent ? 'style="background:color-mix(in srgb,var(--color-success) 6%,transparent)"' : '' ?>>
              <td style="color:var(--color-text-muted)"><?= $i + 1 ?></td>
              <td style="font-weight:600"><?= $r['year_start'] ?>/<?= $r['year_end'] ?></td>
              <td>
                <span class="badge <?= $r['semester_num'] == 1 ? 'badge-blue' : 'badge-amber' ?>">
                  <?= $r['semester_num'] ?> сем.
                </span>
              </td>
              <td><?= htmlspecialchars($r['start_date']) ?></td>
              <td><?= htmlspecialchars($r['end_date']) ?></td>
              <td>
                <?php if ($isCurrent): ?>
                <span class="current-badge">
                  <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
                  Текущий
                </span>
                <?php elseif ($today > $r['end_date']): ?>
                <span style="font-size:.8125rem;color:var(--color-text-faint)">Завершён</span>
                <?php else: ?>
                <span style="font-size:.8125rem;color:var(--color-text-muted)">Предстоит</span>
                <?php endif ?>
              </td>
              <td>
                <div class="action-btns" style="justify-content:flex-end">
                  <a href="semesters.php?edit=<?= $r['id'] ?>" class="btn btn-outline" style="padding:.3rem .6rem;font-size:.8125rem">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </a>
                  <a href="semesters.php?delete=<?= $r['id'] ?>"
                     class="btn btn-danger" style="padding:.3rem .6rem;font-size:.8125rem"
                     onclick="return confirm('Удалить семестр <?= $r['year_start'].'/'.$r['year_end'] ?> — <?= $r['semester_num'] ?> сем.?')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                  </a>
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
        <div class="empty-state-title">Нет семестров</div>
        <div class="empty-state-sub">Добавьте первый семестр с помощью формы выше</div>
      </div>
      <?php endif ?>
    </div>

  </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
