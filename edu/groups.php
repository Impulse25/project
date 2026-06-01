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
        $pdo->prepare("DELETE FROM edu_groups WHERE id = ?")->execute([(int)$_GET['delete']]);
        $message     = 'Группа удалена.';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message     = 'Ошибка удаления: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ── Создание / редактирование ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name         = trim($_POST['name']         ?? '');
    $specialty_id = (int)($_POST['specialty_id'] ?? 0);
    $course       = (int)($_POST['course']       ?? 0);
    $curator_id   = trim($_POST['curator_id']    ?? '') !== '' ? (int)$_POST['curator_id'] : null;
    $year_started = (int)($_POST['year_started'] ?? 0);

    if ($name === '' || $specialty_id === 0 || $course < 1 || $course > 4 || $year_started < 2000) {
        $message     = 'Заполните все обязательные поля корректно.';
        $messageType = 'error';
    } else {
        try {
            if ($_POST['action'] === 'create') {
                $pdo->prepare("INSERT INTO edu_groups (name, specialty_id, course, curator_id, year_started) VALUES (?,?,?,?,?)")
                    ->execute([$name, $specialty_id, $course, $curator_id, $year_started]);
                $message = "Группа «{$name}» добавлена.";
            } else {
                $editId = (int)$_POST['edit_id'];
                $pdo->prepare("UPDATE edu_groups SET name=?, specialty_id=?, course=?, curator_id=?, year_started=? WHERE id=?")
                    ->execute([$name, $specialty_id, $course, $curator_id, $year_started, $editId]);
                $message = "Группа «{$name}» обновлена.";
            }
            $messageType = 'success';
        } catch (PDOException $e) {
            $message     = 'Ошибка: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Режим редактирования
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM edu_groups WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Данные для выпадающих списков
$specialties = $pdo->query("SELECT id, code, name_ru FROM edu_specialties ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC);

// Список групп с JOIN
$rows  = $pdo->query("
    SELECT g.*, sp.name_ru AS specialty_name, sp.code AS specialty_code
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    ORDER BY g.name
")->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);

$pageTitle       = 'Группы — СВГТК Портал';
$activeNav       = 'edu';
$sidebarSubtitle = 'Учебный процесс';
$breadcrumbs     = [
    ['label' => 'СВГТК',          'href' => '../'],
    ['label' => 'Учебный процесс', 'href' => 'index.php'],
    ['label' => 'Группы'],
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
    .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; }
    .form-group { display:flex; flex-direction:column; gap:.375rem; }
    .form-group label { font-size:.8125rem; font-weight:500; color:var(--color-text-muted); }
    .form-group input, .form-group select { padding:.5rem .75rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); color:var(--color-text); font-size:.9375rem; transition:border-color var(--transition); }
    .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--color-primary); }
    .empty-state { text-align:center; padding:3rem 1.5rem; }
    .empty-state-icon { width:72px; height:72px; border-radius:18px; background:var(--color-surface-offset); display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem; }
    .empty-state-title { font-weight:600; font-size:1.125rem; color:var(--color-text); margin-bottom:.5rem; }
    .empty-state-sub { font-size:.9375rem; color:var(--color-text-muted); }
    .action-btns { display:flex; gap:.5rem; }
  </style>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Группы</h1>
        <p class="page-subtitle">Справочник учебных групп</p>
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
          Редактировать группу
          <?php else: ?>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          Добавить группу
          <?php endif ?>
        </span>
        <?php if ($editRow): ?>
        <a href="groups.php" class="btn btn-outline" style="padding:.3rem .75rem;font-size:.8125rem">Отмена</a>
        <?php endif ?>
      </div>
      <div class="card-body">
        <form method="POST" action="groups.php">
          <input type="hidden" name="action"  value="<?= $editRow ? 'edit' : 'create' ?>">
          <?php if ($editRow): ?>
          <input type="hidden" name="edit_id" value="<?= $editRow['id'] ?>">
          <?php endif ?>
          <div class="form-grid">
            <div class="form-group">
              <label for="f_name">Название группы <span style="color:var(--color-danger)">*</span></label>
              <input type="text" id="f_name" name="name" maxlength="50" required
                     placeholder="Например: ИТ-231"
                     value="<?= htmlspecialchars($editRow['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="f_spec">Специальность <span style="color:var(--color-danger)">*</span></label>
              <select id="f_spec" name="specialty_id" required>
                <option value="">— выберите —</option>
                <?php foreach ($specialties as $sp): ?>
                <option value="<?= $sp['id'] ?>" <?= ($editRow['specialty_id'] ?? 0) == $sp['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sp['code'] . ' — ' . $sp['name_ru']) ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label for="f_course">Курс (1–4) <span style="color:var(--color-danger)">*</span></label>
              <select id="f_course" name="course" required>
                <?php for ($c = 1; $c <= 4; $c++): ?>
                <option value="<?= $c ?>" <?= ($editRow['course'] ?? 1) == $c ? 'selected' : '' ?>><?= $c ?> курс</option>
                <?php endfor ?>
              </select>
            </div>
            <div class="form-group">
              <label for="f_year">Год начала обучения <span style="color:var(--color-danger)">*</span></label>
              <input type="number" id="f_year" name="year_started" min="2000" max="<?= date('Y') ?>" required
                     placeholder="<?= date('Y') ?>"
                     value="<?= htmlspecialchars($editRow['year_started'] ?? date('Y')) ?>">
            </div>
            <div class="form-group">
              <label for="f_curator">ID куратора</label>
              <input type="number" id="f_curator" name="curator_id" min="1"
                     placeholder="ID пользователя"
                     value="<?= htmlspecialchars($editRow['curator_id'] ?? '') ?>">
            </div>
          </div>
          <div style="margin-top:1.25rem">
            <button type="submit" class="btn btn-primary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              <?= $editRow ? 'Сохранить изменения' : 'Добавить группу' ?>
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
          Список групп
        </span>
        <span style="font-size:.875rem;color:var(--color-text-muted)"><?= $total ?> записей</span>
      </div>
      <?php if ($total > 0): ?>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>Название</th><th>Специальность</th><th>Курс</th>
              <th>Год набора</th><th>ID куратора</th><th style="text-align:right">Действия</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td style="color:var(--color-text-muted)"><?= $i + 1 ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($r['name']) ?></td>
              <td>
                <span class="badge badge-gray"><?= htmlspecialchars($r['specialty_code'] ?? '') ?></span>
                <span style="font-size:.875rem;color:var(--color-text-muted);margin-left:.375rem"><?= htmlspecialchars($r['specialty_name'] ?? '—') ?></span>
              </td>
              <td><span class="badge badge-blue"><?= $r['course'] ?> курс</span></td>
              <td><?= htmlspecialchars($r['year_started']) ?></td>
              <td style="font-family:monospace;color:var(--color-text-muted)"><?= $r['curator_id'] ?? '—' ?></td>
              <td>
                <div class="action-btns" style="justify-content:flex-end">
                  <a href="groups.php?edit=<?= $r['id'] ?>" class="btn btn-outline" style="padding:.3rem .6rem;font-size:.8125rem">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </a>
                  <a href="groups.php?delete=<?= $r['id'] ?>"
                     class="btn btn-danger" style="padding:.3rem .6rem;font-size:.8125rem"
                     onclick="return confirm('Удалить группу «<?= addslashes(htmlspecialchars($r['name'])) ?>»?')">
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
        <div class="empty-state-title">Нет групп</div>
        <div class="empty-state-sub">Добавьте первую группу с помощью формы выше</div>
      </div>
      <?php endif ?>
    </div>

  </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
