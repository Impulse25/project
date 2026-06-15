<?php
/**
 * edu/curricula.php — Список рабочих учебных планов (РУПл)
 */
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$role    = edu_current_role();
$isAdmin = edu_is_admin();

if (!in_array($role, ['admin', 'director', 'teacher'], true)) {
    header('Location: index.php'); exit;
}

$message = ''; $msgType = '';

// ── Удаление (только admin) ───────────────────────────────────────────────────
if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM edu_curricula WHERE id = ?")->execute([$id]);
        $message = 'Учебный план удалён.'; $msgType = 'success';
    } catch (PDOException $e) {
        $message = 'Ошибка: ' . $e->getMessage(); $msgType = 'error';
    }
}

// ── Список планов ─────────────────────────────────────────────────────────────
$curricula = $pdo->query("
    SELECT c.*,
           s.name_ru AS specialty_name_db,
           (SELECT COUNT(*)
              FROM edu_curriculum_modules m
             WHERE m.curriculum_id = c.id
               AND m.is_summary = 0
               AND NOT (REPLACE(REPLACE(UPPER(COALESCE(m.index_code, '')), ' ', ''), CHAR(194,160), '') REGEXP '^(ООМ|БМ|ПМ)([0-9]+\\.?)?$')
           ) AS module_count,
           (SELECT COUNT(*) FROM edu_groups g WHERE g.curriculum_id = c.id) AS groups_count
    FROM edu_curricula c
    LEFT JOIN edu_specialties s ON s.id = c.speciality_id
    ORDER BY c.enrollment_year DESC, c.name
")->fetchAll();

$pageTitle = 'Учебные планы (РУПл)';
$activeNav = 'edu';
$sidebarSubtitle = 'Учебный процесс';
$breadcrumbs = [
    ['label' => 'СВГТК', 'href' => '../'],
    ['label' => 'Учебный процесс', 'href' => 'index.php'],
    ['label' => 'Учебные планы (РУПл)'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — СВГТК</title>
  <?php require 'includes/head.php' ?>
  <style>
    .curricula-actions{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center}
    .curricula-table{width:100%;min-width:1120px;table-layout:auto}
    .curricula-table th,.curricula-table td{vertical-align:middle}
    .curricula-table .col-name{width:300px}.curricula-table .col-spec{min-width:420px}.curricula-table .col-base{width:95px}.curricula-table .col-year{width:115px}.curricula-table .col-num{width:92px;text-align:center}.curricula-table .col-date{width:125px}.curricula-table .col-actions{width:120px;text-align:right}
    .curricula-name{display:flex;flex-direction:column;gap:.15rem;min-width:0}
    .curricula-name a{font-weight:700;color:var(--color-primary);line-height:1.25;word-break:break-word}
    .curricula-name small{color:var(--color-text-muted);font-size:.8rem;line-height:1.2}
    .curricula-specialty{line-height:1.35;word-break:break-word}
    .table-actions{display:flex;gap:.45rem;justify-content:flex-end;align-items:center;white-space:nowrap}
  </style>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Учебные планы (РУПл)</h1>
        <p class="page-subtitle">Импорт, просмотр и привязка рабочих учебных планов к группам</p>
      </div>
      <div class="page-actions curricula-actions">
        <a href="index.php" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Назад к студентам
        </a>
        <?php if ($isAdmin): ?>
        <a href="curriculum_import.php" class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Загрузить РУПл
        </a>
        <?php endif ?>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:1rem">
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif ?>

    <?php if (empty($curricula)): ?>
    <div class="card">
      <div class="card-body" style="text-align:center;padding:3rem;color:var(--color-text-muted)">
        <p style="font-size:1.125rem;margin-bottom:1rem">Учебные планы ещё не загружены</p>
        <?php if ($isAdmin): ?>
        <a href="curriculum_import.php" class="btn btn-primary">Загрузить первый РУПл</a>
        <?php endif ?>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Список учебных планов</span>
        <span style="font-size:.875rem;color:var(--color-text-muted)"><?= count($curricula) ?> записей</span>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table curricula-table">
          <thead>
            <tr>
              <th class="col-name">Название</th>
              <th class="col-spec">Специальность</th>
              <th class="col-base">База</th>
              <th class="col-year">Год поступл.</th>
              <th class="col-num">Дисциплин</th>
              <th class="col-num">Групп</th>
              <th class="col-date">Импортирован</th>
              <th class="col-actions"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($curricula as $c): ?>
          <tr>
            <td class="col-name">
              <div class="curricula-name">
                <a href="curriculum_view.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a>
                <small><?= htmlspecialchars($c['specialty_code']) ?></small>
              </div>
            </td>
            <td class="col-spec"><div class="curricula-specialty"><?= htmlspecialchars($c['specialty_name_db'] ?? $c['specialty_name']) ?></div></td>
            <td class="col-base"><span class="badge badge-gray"><?= htmlspecialchars($c['base_education']) ?></span></td>
            <td class="col-year" style="font-variant-numeric:tabular-nums"><?= $c['enrollment_year'] ?></td>
            <td class="col-num">
              <span class="badge badge-blue"><?= $c['module_count'] ?></span>
            </td>
            <td class="col-num">
              <?php if ($c['groups_count'] > 0): ?>
              <span class="badge badge-green"><?= $c['groups_count'] ?></span>
              <?php else: ?>
              <span style="color:var(--color-text-faint)">—</span>
              <?php endif ?>
            </td>
            <td class="col-date" style="font-size:.8125rem;color:var(--color-text-muted)">
              <?= $c['imported_at'] ? date('d.m.Y', strtotime($c['imported_at'])) : '—' ?>
            </td>
            <td class="col-actions"><div class="table-actions">
              <a href="curriculum_view.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" title="Просмотр">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </a>
              <?php if ($isAdmin): ?>
              <a href="?delete=<?= $c['id'] ?>"
                 onclick="return confirm('Удалить план «<?= htmlspecialchars(addslashes($c['name'])) ?>»? Все данные будут потеряны.')"
                 class="btn btn-danger" style="padding:.3rem .6rem;font-size:.8125rem" title="Удалить">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </a>
              <?php endif ?>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif ?>

  </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
