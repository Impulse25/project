<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

// Только admin
if (!edu_is_admin()) {
    header('Location: index.php');
    exit;
}

// ── Скачивание файла ───────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $logId = (int)$_GET['download'];
    $row   = $pdo->prepare("SELECT file_name, file_path FROM edu_import_logs WHERE id = ?");
    $row->execute([$logId]);
    $log = $row->fetch(PDO::FETCH_ASSOC);

    if ($log) {
        $fullPath = __DIR__ . '/' . $log['file_path'];
        if (file_exists($fullPath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . rawurlencode($log['file_name']) . '"');
            header('Content-Length: ' . filesize($fullPath));
            header('Cache-Control: no-cache');
            readfile($fullPath);
            exit;
        }
    }
    // Файл не найден — редирект с ошибкой
    header('Location: import_logs.php?err=notfound');
    exit;
}

// ── Список логов ───────────────────────────────────────────────────────────
$logs  = $pdo->query("
    SELECT l.id, l.file_name, l.imported_at, l.file_path,
           u.username, u.full_name
    FROM edu_import_logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.imported_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$total = count($logs);

$errMsg = '';
if (isset($_GET['err']) && $_GET['err'] === 'notfound') {
    $errMsg = 'Файл не найден на сервере.';
}

$pageTitle       = 'История импортов — СВГТК Портал';
$activeNav       = 'edu';
$sidebarSubtitle = 'Учебный процесс';
$breadcrumbs     = [
    ['label' => 'СВГТК',           'href' => '../'],
    ['label' => 'Учебный процесс', 'href' => 'index.php'],
    ['label' => 'История импортов'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <?php require 'includes/head.php' ?>
  <style>
    .data-table { width: 100%; min-width: 600px; }
    .data-table th {
      font-size: .75rem; font-weight: 600; text-transform: uppercase;
      letter-spacing: .05em; color: var(--color-text-muted);
      padding: .75rem 1rem; background: var(--color-surface-2);
      border-bottom: 1px solid var(--color-divider);
      text-align: left; white-space: nowrap;
    }
    .data-table td {
      padding: .75rem 1rem; border-bottom: 1px solid var(--color-divider);
      font-size: .9375rem; vertical-align: middle;
    }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tbody tr { transition: background var(--transition); }
    .data-table tbody tr:hover { background: var(--color-primary-highlight); }
    .table-wrapper { overflow-x: auto; }
    .stats-strip {
      display: flex; gap: 2rem; flex-wrap: wrap;
      padding: 1rem 1.5rem;
      background: var(--color-surface-2);
      border-bottom: 1px solid var(--color-divider);
    }
    .stat-item  { display: flex; flex-direction: column; gap: 2px; }
    .stat-value { font-weight: 700; font-size: 1.125rem; font-variant-numeric: tabular-nums; }
    .stat-label { font-size: .75rem; color: var(--color-text-muted); }
    .empty-state { text-align: center; padding: 3rem 1.5rem; }
    .empty-state-icon { width: 72px; height: 72px; border-radius: 18px; background: var(--color-surface-offset); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; }
    .empty-state-title { font-weight: 600; font-size: 1.125rem; color: var(--color-text); margin-bottom: .5rem; }
    .empty-state-sub   { font-size: .9375rem; color: var(--color-text-muted); }
    .file-missing { color: var(--color-text-faint); font-style: italic; font-size: .8125rem; }
    .user-chip {
      display: inline-flex; align-items: center; gap: .35rem;
      padding: .2rem .65rem; border-radius: 99px;
      background: var(--color-surface-offset);
      font-size: .8125rem; font-weight: 500; color: var(--color-text-muted);
      border: 1px solid var(--color-border);
    }
  </style>
</head>
<body>

<?php require 'includes/sidebar.php' ?>

<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>

  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">История импортов</h1>
        <p class="page-subtitle">Все загруженные файлы студентов</p>
      </div>
      <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Назад к студентам
        </a>
      </div>
    </div>

    <?php if ($errMsg): ?>
    <div class="alert alert-error" style="margin-bottom:1rem">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= htmlspecialchars($errMsg) ?>
    </div>
    <?php endif ?>

    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="3" y1="16" x2="21" y2="16"/><line x1="9" y1="4" x2="9" y2="22"/></svg>
          Журнал импортов
        </span>
        <span style="font-size:.875rem;color:var(--color-text-muted)"><?= $total ?> записей</span>
      </div>

      <?php if ($total > 0): ?>

      <div class="stats-strip">
        <div class="stat-item">
          <span class="stat-value"><?= $total ?></span>
          <span class="stat-label">Всего импортов</span>
        </div>
        <?php
          // Уникальные пользователи
          $uniqueUsers = count(array_unique(array_column($logs, 'user_id')));
          // Последний импорт
          $lastImport  = $logs[0]['imported_at'] ?? '';
        ?>
        <div class="stat-item">
          <span class="stat-value"><?= $uniqueUsers ?></span>
          <span class="stat-label">Пользователей</span>
        </div>
        <?php if ($lastImport): ?>
        <div class="stat-item">
          <span class="stat-value" style="font-size:.9rem"><?= date('d.m.Y H:i', strtotime($lastImport)) ?></span>
          <span class="stat-label">Последний импорт</span>
        </div>
        <?php endif ?>
      </div>

      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Имя файла</th>
              <th>Кто импортировал</th>
              <th>Дата и время</th>
              <th>Файл</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $i => $log): ?>
            <?php
              $fileExists  = file_exists(__DIR__ . '/' . $log['file_path']);
              $importedAt  = date('d.m.Y H:i:s', strtotime($log['imported_at']));
              $displayUser = $log['full_name']
                             ? $log['full_name'] . ' (' . $log['username'] . ')'
                             : ($log['username'] ?? 'ID ' . $log['user_id'] ?? '—');
            ?>
            <tr>
              <td style="color:var(--color-text-muted)"><?= $i + 1 ?></td>

              <td>
                <div style="display:flex;align-items:center;gap:.625rem">
                  <div style="width:34px;height:34px;border-radius:var(--radius-md);background:var(--color-primary-highlight);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  </div>
                  <div>
                    <div style="font-weight:500;font-size:.9375rem"><?= htmlspecialchars($log['file_name']) ?></div>
                    <div style="font-size:.75rem;color:var(--color-text-faint);font-family:monospace"><?= htmlspecialchars($log['file_path']) ?></div>
                  </div>
                </div>
              </td>

              <td>
                <span class="user-chip">
                  <?= htmlspecialchars($displayUser) ?>
                </span>
              </td>

              <td style="white-space:nowrap">
                <div style="font-weight:500"><?= date('d.m.Y', strtotime($log['imported_at'])) ?></div>
                <div style="font-size:.8125rem;color:var(--color-text-muted)"><?= date('H:i:s', strtotime($log['imported_at'])) ?></div>
              </td>

              <td>
                <?php if ($fileExists): ?>
                <a href="import_logs.php?download=<?= $log['id'] ?>" class="btn btn-outline" style="padding:.3rem .7rem;font-size:.8125rem;display:inline-flex;align-items:center;gap:.35rem">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  Скачать
                </a>
                <?php else: ?>
                <span class="file-missing">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:3px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  Файл удалён
                </span>
                <?php endif ?>
              </td>
            </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>

      <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-faint)" stroke-width="1.5">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
          </svg>
        </div>
        <div class="empty-state-title">Нет записей</div>
        <div class="empty-state-sub">Ни одного импорта ещё не было выполнено</div>
      </div>
      <?php endif ?>
    </div>

  </main>
</div>

<script src="assets/app.js"></script>
</body>
</html>
