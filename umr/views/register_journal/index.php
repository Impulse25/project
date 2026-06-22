<?php
// views/register_journal/index.php

define('BASE_PATH', dirname(__DIR__, 2));

// Авторизация
require_once BASE_PATH . '/partials/init.php';

// Проверка прав
if (!$canRegisterJournal && !$isPccHead && !$isMethodist) {
    http_response_code(403);
    echo "Доступ запрещён. У вас нет прав для просмотра этого раздела.";
    exit;
}

// Полный доступ
$hasFullAccess = $isAdmin || $isPccHead || $isMethodist;

// Производные флаги отображения
$isTeacherOnly = $isPlainTeacher;
$showAsManager = $hasFullAccess;

// Фильтры «Семестр» и «Группа»
$showSemGroupFilters = true;

// Подключение файлов
require_once BASE_PATH . '/models/baseModel.php';

require_once BASE_PATH . '/models/edu_groups.php';
require_once BASE_PATH . '/models/edu_semesters.php';
require_once BASE_PATH . '/models/users.php';

require_once BASE_PATH . '/models/umr_register_journal.php';

// Создание классов моделей
$moduleSem             = new edu_semesters($pdo);
$moduleUsers           = new users($pdo);
$moduleGroups          = new edu_groups($pdo);
$moduleRegisterJournal = new umr_register_journal($pdo);

$today = date('Y-m-d');

// Активный семестр
$activeSem = $moduleSem->getActive($today);

// Все учебные годы
$allAcademicYears = $moduleGroups->getAcademicYears();

// Фильтры из GET / сессии
$filterYear = (int)($_GET['academic_year']
    ?? $_SESSION['rj_filter_year']
    ?? ($activeSem['year_start'] ?? date('Y')));
$_SESSION['rj_filter_year'] = $filterYear;

$defaultSem = $activeSem ? (int)$activeSem['semester_num'] : 1;
$filterSem  = (int)($_GET['semester'] ?? $_SESSION['rj_filter_sem'] ?? $defaultSem);
$filterSem  = ($filterSem >= 0 && $filterSem <= 8) ? $filterSem : 1;
$_SESSION['rj_filter_sem'] = $filterSem;

$filterGroupId   = (int)($_GET['group_id']   ?? 0);
$filterTeacherId = $showAsManager ? (int)($_GET['teacher_id'] ?? 0) : 0;

//  Чекбокс «Только мои» для преподавателя 
$onlyMine = false;
if ($isTeacherOnly) {
    if (isset($_GET['fs'])) {
        $onlyMine = isset($_GET['only_mine']);
    } else {
        $onlyMine = (bool)($_SESSION['rj_only_mine'] ?? false);
    }
    $_SESSION['rj_only_mine'] = $onlyMine;
}

$academicYearLabel = $filterYear . '/' . ($filterYear + 1);
$semLabel          = $filterSem === 0 ? 'Все семестры' : ($filterSem . ' семестр');

$sortDir = $_GET['sort'] ?? ($_SESSION['rj_sort'] ?? 'desc');
$sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';
$_SESSION['rj_sort'] = $sortDir;

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sortLink(int $year, int $sem, int $groupId, int $teacherId, string $dir, bool $onlyMine): string {
    return '?academic_year=' . $year . '&semester=' . $sem . '&group_id=' . $groupId
        . '&teacher_id=' . $teacherId . '&sort=' . $dir . '&fs=1'
        . ($onlyMine ? '&only_mine=1' : '');
}

//  Список преподавателей для фильтра
$teacherOptions = $showAsManager
    ? $moduleRegisterJournal->getTeacherOptions($filterYear, $filterSem, $isAdmin, $isMethodist, $isPccHead, $userId)
    : [];

//  Список групп для фильтра
$dropdownGroups = $moduleRegisterJournal->getGroupOptions($filterYear, $filterSem, $filterTeacherId);

//  Утверждённые РП без записи в журнале
$approvedWps = $moduleRegisterJournal->getPendingWorkPrograms(
    $filterYear, $filterSem, $isAdmin, $isMethodist, $isPccHead, $userId,
    $filterTeacherId, $filterGroupId
);

$onlyMineUserId = ($isTeacherOnly && $onlyMine) ? $userId : null;
$journalRows = $moduleRegisterJournal->getJournalRows($filterYear, $onlyMineUserId, $sortDir);

//  Порядковые номера
$regNums = $moduleRegisterJournal->getRegistrationNumbers($filterYear);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$canExport = $showAsManager;
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Журнал регистрации РП — УМР — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../css/style.css">
  <style>
    
      .ta-filters {
        display: flex; flex-wrap: wrap; gap: var(--space-3);
        align-items: flex-end; margin-bottom: var(--space-5);
      }
      .sort-btn {
        display: inline-flex; align-items: center; gap: 4px;
        padding: var(--space-2) var(--space-3); border-radius: var(--radius-md);
        font-size: var(--text-xs); font-weight: 500;
        border: 1px solid var(--color-border); background: var(--color-surface);
        color: var(--color-text-muted); cursor: pointer; transition: all var(--transition);
        white-space: nowrap; text-decoration: none;
      }
      .sort-btn:hover { background: var(--color-surface-offset); color: var(--color-text); }
      .sort-btn.active { background: var(--color-primary-highlight); border-color: var(--color-primary); color: var(--color-primary); }

      /* Чекбокс «Только мои» */
      .checkbox-filter {
        display: flex; align-items: center; gap: .4rem;
        padding: .55rem .85rem; border: 1px solid var(--color-border);
        border-radius: var(--radius-md); background: var(--color-surface);
        cursor: pointer; font-size: .85rem; font-weight: 500;
        color: var(--color-text); white-space: nowrap;
      }
      .checkbox-filter input { width: 16px; height: 16px; cursor: pointer; }
      .checkbox-filter.active {
        border-color: var(--color-primary); color: var(--color-primary);
        background: var(--color-primary-highlight);
      }

      /* ── Блок незарегистрированных РП ── */
      .pending-reg-wrap {
        background: var(--color-surface); border: 1px solid var(--color-border);
        border-radius: var(--radius-xl); margin-bottom: 1.5rem;
        box-shadow: var(--shadow-sm); overflow: hidden;
      }
      .pending-reg-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: .7rem 1.1rem; cursor: pointer;
        background: var(--color-surface-2); border-bottom: 1px solid var(--color-divider);
        user-select: none;
      }
      .pending-reg-title {
        font-size: .875rem; font-weight: 600; color: var(--color-text);
        display: flex; align-items: center; gap: .5rem;
      }
      .pending-badge {
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--color-primary); color: #fff;
        border-radius: var(--radius-full); min-width: 20px; height: 20px;
        font-size: .72rem; font-weight: 700; padding: 0 6px;
      }
      .sf-chevron { transition: transform .2s; flex-shrink: 0; }
      .pending-reg-body { padding: 0; }
      .pending-reg-body.collapsed { display: none; }

      .pending-table {
        width: 100%; border-collapse: collapse; font-size: .8125rem;
      }
      .pending-table th {
        background: var(--color-surface-2); padding: .45rem .75rem;
        border: 1px solid var(--color-border);
        font-size: .7rem; font-weight: 600; color: var(--color-text-muted);
        text-align: left; white-space: nowrap;
      }
      .pending-table td {
        padding: .4rem .75rem; border: 1px solid var(--color-divider);
        vertical-align: middle;
      }
      .pending-table tbody tr:hover td { background: var(--color-primary-highlight); }

      /* ── Журнал ── */
      .journal-table {
        width: 100%; border-collapse: collapse; font-size: .8125rem; min-width: 900px;
      }
      .journal-table th {
        background: var(--color-surface-2); padding: .5rem .75rem;
        border: 1px solid var(--color-border);
        font-size: .72rem; font-weight: 600; color: var(--color-text-muted);
        text-align: left; white-space: nowrap;
      }
      .journal-table td {
        padding: .45rem .75rem; border: 1px solid var(--color-divider);
        vertical-align: middle;
      }
      .journal-table tbody tr:nth-child(odd) td  { background: var(--color-surface); }
      .journal-table tbody tr:nth-child(even) td { background: var(--color-surface-2); }
      .journal-table tbody tr:hover td { background: var(--color-primary-highlight); }
      .journal-table .col-num { text-align: center; white-space: nowrap; }
      /* Строки «моих» РП выделены синей полосой слева */
      .mine-row td { border-left: 3px solid var(--color-primary); }

      .badge-type { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: .68rem; font-weight: 700; }
      .t-ООД { background: #dbeafe; color: #1d4ed8; }
      .t-БМ  { background: #ede9fe; color: #7c3aed; }
      .t-ПМ  { background: #dcfce7; color: #15803d; }
      .t-ПА  { background: #fef3c7; color: #b45309; }
      .t-К, .t-Ф { background: #f3f4f6; color: #6b7280; }

      .btn-action {
        padding: .28rem .65rem; border-radius: var(--radius-md); font-size: .76rem;
        font-weight: 600; background: var(--color-primary); color: #fff;
        border: none; cursor: pointer; white-space: nowrap;
      }
      .btn-action:hover { opacity: .88; }
      .btn-action.secondary {
        background: transparent; color: var(--color-primary);
        border: 1px solid var(--color-primary);
      }
      .btn-danger {
        background: transparent; color: var(--color-error, #b91c1c);
        border: 1px solid var(--color-error, #b91c1c);
        padding: .28rem .65rem; border-radius: var(--radius-md); font-size: .76rem;
        font-weight: 600; cursor: pointer; white-space: nowrap;
      }
      .btn-danger:hover { background: #fee2e2; }

      .ta-empty { text-align: center; padding: 3rem 2rem; color: var(--color-text-muted); }
      .ta-empty p:first-child { font-size: 1rem; font-weight: 600; margin-bottom: .25rem; }

      /* ── Модалка регистрации ── */
      .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45);
        z-index: 1000; align-items: center; justify-content: center;
      }
      .modal-overlay.open { display: flex; }
      .modal-box {
        background: var(--color-surface); border-radius: var(--radius-xl);
        padding: 1.5rem; width: 100%; max-width: 440px; box-shadow: var(--shadow-lg);
      }
      .modal-box h3 { margin: 0 0 1rem; font-size: 1.05rem; }
      .modal-row { margin-bottom: .85rem; }
      .modal-row label { display: block; font-size: .8rem; font-weight: 600; margin-bottom: .3rem; color: var(--color-text-muted); }
      .modal-row input[type=date], .modal-row select {
        width: 100%; padding: .45rem .6rem; border: 1px solid var(--color-border);
        border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);
        font-size: .85rem;
      }
      .modal-readonly {
        padding: .45rem .6rem; border: 1px solid var(--color-border);
        border-radius: var(--radius-md); background: var(--color-surface-2);
        font-size: .85rem; color: var(--color-text);
      }
      .modal-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1.25rem; }
      .modal-error { color: var(--color-error); font-size: .8rem; margin-top: .5rem; display: none; }
      .modal-confirm-text {
        font-size: .85rem; color: var(--color-text); margin-bottom: 1rem; line-height: 1.45;
      }

  </style>
</head>
<body>
<?php $_nav_active_key = 'register_journal';
      require __DIR__ . '/../../partials/sidebar.php'; ?>

<div class="main-wrapper" id="mainWrapper">
  <?php
    $_breadcrumbs = ['УМР' => null, 'Журнал регистрации' => null];
    require_once BASE_PATH . '/partials/topbar.php';
    require_once BASE_PATH . '/partials/umr_subnav.php';
  ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Журнал регистрации РП</h1>
        <p class="page-subtitle">
          <strong><?= e($academicYearLabel) ?></strong>,
          <strong><?= e($semLabel) ?></strong>
        </p>
      </div>
      <?php if ($canExport): ?>
      <div style="display:flex;gap:.5rem;align-items:center">
        <a href="actions/export_journal.php?academic_year=<?= $filterYear ?>" class="btn-export">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Экспорт в Excel
        </a>
      </div>
      <?php endif ?>
    </div>

    <?php if ($flash): ?>
    <div class="card" style="margin-bottom:1rem;background:var(--color-success-highlight);border-color:#a7f3d0">
      <div class="card-body" style="padding:.75rem 1.5rem;color:var(--color-success)"><?= e($flash) ?></div>
    </div>
    <?php endif ?>

    <!-- Фильтры -->
    <form method="GET" class="ta-filters" id="filterForm">
      <input type="hidden" name="fs" value="1">

      <?php require __DIR__ . '/../../partials/filter_academicYear.php'; ?>

      <?php if ($showSemGroupFilters): ?>
        <?php require __DIR__ . '/../../partials/filter_semester.php'; ?>
        <?php require __DIR__ . '/../../partials/filter_groups.php'; ?>
      <?php endif ?>

      <?php if ($showAsManager): ?>
      <div class="form-group">
        <label class="form-label">Преподаватель</label>
        <select name="teacher_id" class="form-control" onchange="this.form.submit()">
          <option value="0">Все преподаватели</option>
          <?php foreach ($teacherOptions as $t): ?>
          <option value="<?= $t['id'] ?>" <?= (int)$t['id'] === $filterTeacherId ? 'selected' : '' ?>>
            <?= e($t['full_name']) ?>
          </option>
          <?php endforeach ?>
        </select>
      </div>
      <?php endif ?>

      <div class="form-group">
        <label class="form-label">Сортировка по дате</label>
        <div style="display:flex;gap:.4rem">
          <a href="<?= sortLink($filterYear, $filterSem, $filterGroupId, $filterTeacherId, 'asc', $onlyMine) ?>"
             class="sort-btn <?= $sortDir === 'asc' ? 'active' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
            </svg>Старые
          </a>
          <a href="<?= sortLink($filterYear, $filterSem, $filterGroupId, $filterTeacherId, 'desc', $onlyMine) ?>"
             class="sort-btn <?= $sortDir === 'desc' ? 'active' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
            </svg>Новые
          </a>
        </div>
      </div>

      <?php if ($isTeacherOnly): ?>
      <div class="form-group">
        <label class="form-label">&nbsp;</label>
        <label class="checkbox-filter <?= $onlyMine ? 'active' : '' ?>">
          <input type="checkbox" name="only_mine" value="1"
                 <?= $onlyMine ? 'checked' : '' ?>
                 onchange="this.form.submit()">
          Только мои
        </label>
      </div>
      <?php endif ?>
    </form>

    <!-- ── Блок: утверждённые РП без регистрации  -->
    <?php if (!empty($approvedWps)): ?>
    <div class="pending-reg-wrap">
      <div class="pending-reg-head" onclick="togglePending()">
        <span class="pending-reg-title">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          Утверждённые РП — ожидают регистрации
          <span class="pending-badge"><?= count($approvedWps) ?></span>
        </span>
        <svg class="sf-chevron" id="pendingChevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>
      <div class="pending-reg-body" id="pendingBody">
        <div style="overflow-x:auto">
        <table class="pending-table">
          <thead>
            <tr>
              <th>Группа</th>
              <th>Сем.</th>
              <th>Индекс</th>
              <th>Дисциплина</th>
              <th>Тип</th>
              <th>Преподаватель</th>
              <th>Версия РП</th>
              <th>Действие</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($approvedWps as $wp): ?>
          <tr>
            <td><?= e($wp['group_name']) ?></td>
            <td style="text-align:center;font-weight:600"><?= (int)$wp['semester_num'] ?></td>
            <td style="font-size:.77rem;color:var(--color-text-muted);font-weight:600"><?= e($wp['index_code']) ?></td>
            <td><?= e($wp['module_name']) ?></td>
            <td><span class="badge-type t-<?= e($wp['module_type']) ?>"><?= e($wp['module_type']) ?></span></td>
            <td><?= e($wp['teacher_name']) ?></td>
            <td style="text-align:center">v<?= (int)$wp['version'] ?></td>
            <td>

              <?php
                $isOwnAssignment  = ((int)$wp['pcc_head_id'] === $userId);
                $needsPccConfirm  = ($isAdmin || $isMethodist)
                    ? true
                    : ($isPccHead && !$isOwnAssignment);
              ?>

              <button class="btn-action" onclick='maybeOpenRegModal(<?= json_encode([
                  "wp_id"             => (int)$wp["wp_id"],
                  "title"             => $wp["index_code"] . " " . $wp["module_name"],
                  "group_name"        => $wp["group_name"],
                  "teacher_name"      => $wp["teacher_name"],
                  "needs_pcc_confirm" => $needsPccConfirm,
                  "pcc_id"            => (int)$wp["pcc_head_id"],
                  "pcc_name"          => $wp["pcc_name"],
              ], JSON_UNESCAPED_UNICODE) ?>)'>
                Зарегистрировать
              </button>

            </td>
          </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
    <?php endif ?>

    <!--  Журнал регистрации  -->
    <div class="card">
      <?php if (empty($journalRows)): ?>
      <div class="ta-empty">
        <p>Журнал пуст</p>
        <p>Нет зарегистрированных рабочих программ за выбранный период</p>
      </div>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table class="journal-table" id="journalTable">
          <thead>
            <tr>
              <th style="width:50px;text-align:center">№</th>
              <th>Дата рег.</th>
              <th>Группа</th>
              <th style="text-align:center">Сем.</th>
              <th>Индекс</th>
              <th style="min-width:200px">Дисциплина</th>
              <th>Тип</th>
              <th>Преподаватель</th>
              <?php if ($showAsManager): ?>
              <th>Назначил (ПЦК)</th>
              <th>Версия РП</th>
              <th>Внёс</th>
              <?php endif ?>
              <th>Действие</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($journalRows as $jr):
            $isMine = (int)$jr['teacher_id'] === $userId;
          ?>
          <tr data-mine="<?= $isMine ? '1' : '0' ?>"
              class="<?= $isMine ? 'mine-row' : '' ?>">
            <td class="col-num" style="font-weight:700;color:var(--color-primary)">
              <?= $regNums[$jr['journal_id']] ?? '—' ?>
            </td>
            <td style="white-space:nowrap">
              <?= e(date('d.m.Y', strtotime($jr['registered_at']))) ?>
            </td>
            <td><?= e($jr['group_name']) ?></td>
            <td class="col-num"><?= (int)$jr['semester_num'] ?></td>
            <td style="font-size:.77rem;color:var(--color-text-muted);font-weight:600"><?= e($jr['index_code']) ?></td>
            <td><?= e($jr['module_name']) ?></td>
            <td class="col-num">
              <span class="badge-type t-<?= e($jr['module_type']) ?>"><?= e($jr['module_type']) ?></span>
            </td>
            <td><?= e($jr['teacher_name']) ?></td>
            <?php if ($showAsManager): ?>
            <td><?= e($jr['pcc_name']) ?></td>
            <td class="col-num">v<?= (int)$jr['version'] ?></td>
            <td style="font-size:.78rem;color:var(--color-text-muted)"><?= e($jr['created_by_name']) ?></td>
            <?php endif ?>
            <td>
              <div style="display:flex;gap:.3rem;align-items:center">
                <?php if ($jr['file_path'] && ($showAsManager || ($isTeacherOnly && $isMine))): ?>
                <a href="../../<?= e($jr['file_path']) ?>" target="_blank"
                   style="font-size:.74rem;color:var(--color-primary);text-decoration:none">Файл</a>
                <?php endif ?>
                <?php if ($showAsManager): ?>
                <button class="btn-danger"
                        onclick="deleteJournal(<?= (int)$jr['journal_id'] ?>)">Удалить</button>
                <?php endif ?>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php endif ?>
    </div>

  </main>
</div>

<!-- Модалка подтверждения назначения (другой председатель ПЦК) -->
<div class="modal-overlay" id="pccConfirmOverlay">
  <div class="modal-box">
    <h3>Подтверждение регистрации</h3>
    <p class="modal-confirm-text">
      Эта рабочая программа назначена преподавателю другим председателем ПЦК.
      Вы регистрируете запись не из своей зоны ответственности.
    </p>
    <div class="modal-row">
      <label>Председатель ПЦК</label>
      <div class="modal-readonly" id="pccConfirmName">—</div>
    </div>
    <div class="modal-actions">
      <button class="btn-action secondary" type="button" onclick="closePccConfirmModal()">Отмена</button>
      <button class="btn-action" type="button" onclick="confirmPccAndOpenRegModal()">Продолжить</button>
    </div>
  </div>
</div>

<!--  Модалка регистрации  -->
<div class="modal-overlay" id="regModalOverlay">
  <div class="modal-box">
    <h3 id="regModalTitle">Регистрация рабочей программы</h3>
    <div class="modal-row">
      <label>Дисциплина</label>
      <div class="modal-readonly" id="regTitle">—</div>
    </div>
    <div class="modal-row">
      <label>Группа</label>
      <div class="modal-readonly" id="regGroup">—</div>
    </div>
    <div class="modal-row">
      <label>Преподаватель</label>
      <div class="modal-readonly" id="regTeacher">—</div>
    </div>
    <div class="modal-row">
        <label>Дата регистрации</label>
        <input type="date" 
        id="regDate" 
        value="<?= $today ?>" 
        min="<?= $today ?>" 
        max="<?= $today ?>" 
        readonly>
    </div>
    <div class="modal-error" id="regModalError"></div>
    <div class="modal-actions">
      <button class="btn-action secondary" type="button" onclick="closeRegModal()">Отмена</button>
      <button class="btn-action" type="button" onclick="submitReg()">Зарегистрировать</button>
    </div>
  </div>
</div>

<!-- Загрузка обработчиков страницы, тема -->
<script src="../../js/register_journal.js"></script>
<script src="../../js/umr.js"></script>

</body>
</html>
