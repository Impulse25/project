<?php
// views/work_programs/index.php

define('BASE_PATH', dirname(__DIR__, 2));

// Авторизация
require_once BASE_PATH . '/partials/init.php';

// Проверка прав
if (!$canWorkPrograms && !$isPccHead && !$isMethodist) {
  http_response_code(403);
  echo "Доступ запрещён. У вас нет прав для просмотра этого раздела.";
  exit;
}

// Полный доступ (как у admin) — admin, председатель ПЦК и методист
$hasFullAccess = $isAdmin || $isPccHead || $isMethodist;

// Подключение файлов
require_once BASE_PATH . '/models/baseModel.php';

require_once BASE_PATH . '/models/edu_groups.php';
require_once BASE_PATH . '/models/edu_semesters.php';
require_once BASE_PATH . '/models/users.php';

require_once BASE_PATH . '/models/umr_teacher_assignments.php';
require_once BASE_PATH . '/models/umr_work_programs.php';

// Создание классов моделей
$moduleSem          = new edu_semesters($pdo);
$moduleUsers        = new users($pdo);
$moduleGroups       = new edu_groups($pdo);
$moduleWorkPrograms = new umr_work_programs($pdo);

$today = date('Y-m-d');

// Активный семестр
$activeSem = $moduleSem->getActive($today);

// Все учебные годы
$allAcademicYears = $moduleGroups->getAcademicYears();

// Преподаватели (для фильтра — виден только при полном доступе)
$teachers = $hasFullAccess ? $moduleUsers->getTeachers() : [];

// Фильтры
$filterYear = (int)($_GET['academic_year']
    ?? $_SESSION['ta_filter_year']
    ?? ($activeSem['year_start'] ?? date('Y')));

$_SESSION['ta_filter_year'] = $filterYear;

$defaultSem = $activeSem ? (int)$activeSem['semester_num'] : 1;
$filterSem  = (int)($_GET['semester'] ?? $_SESSION['ta_filter_sem'] ?? $defaultSem);
$filterSem  = ($filterSem >= 0 && $filterSem <= 8) ? $filterSem : 1;

$_SESSION['ta_filter_sem'] = $filterSem;

$filterGroupId = (int)($_GET['group_id'] ?? 0);

// Фильтр по преподавателю доступен только при полном доступе
$filterTeacherId = $hasFullAccess ? (int)($_GET['teacher_id'] ?? 0) : 0;

$sortDir = $_GET['sort'] ?? ($_SESSION['ta_sort'] ?? 'asc');
$sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';
$_SESSION['ta_sort'] = $sortDir;

$academicYearLabel = $filterYear . '/' . ($filterYear + 1);
$semLabel          = $filterSem === 0 ? 'Все семестры' : ($filterSem . ' семестр');

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sortLink(int $year, int $sem, int $groupId, int $teacherId, string $dir): string {
    return '?academic_year=' . $year . '&semester=' . $sem . '&group_id=' . $groupId
        . '&teacher_id=' . $teacherId . '&sort=' . $dir;
}

// ── Группы для дропдауна (зависят от выбранного учебного года) ────────────────
$dropdownGroups = $moduleWorkPrograms->getGroupsWithAssignments(
    $filterYear, $isAdmin, $isPccHead, $isMethodist, $userId
);

// ── Основной запрос (вынесен в модель) ─────────────────────────────────────────
$rows = $moduleWorkPrograms->getAssignmentsWithWorkPrograms(
    $filterYear,
    $filterSem,
    $filterGroupId,
    $filterTeacherId,
    $isAdmin,
    $isPccHead,
    $isMethodist,
    $userId
);

// ── Группировка по группе+семестру и сбор списка дисциплин для мультиселекта ──
$grouped = [];
$allModuleIds = [];
foreach ($rows as $row) {
    $gKey = $row['g_id'] . '|' . $row['semester_num'];
    if (!isset($grouped[$gKey])) {
        $grouped[$gKey] = [
            'group_name'   => $row['group_name'],
            'course'       => $row['course'],
            'semester_num' => $row['semester_num'],
            'rows'         => [],
        ];
    }
    $grouped[$gKey]['rows'][] = $row;

    $mKey = $row['index_code'] . '||' . $row['module_name'];
    if (!isset($allModuleIds[$mKey])) {
        $allModuleIds[$mKey] = [
            'index_code'  => $row['index_code'],
            'name'        => $row['module_name'],
            'module_type' => $row['module_type'],
        ];
    }
}
usort($allModuleIds, fn($a, $b) => strcmp($a['index_code'] . $a['name'], $b['index_code'] . $b['name']));

// Сортировка блоков по семестру (как в teacher_assignments)
uasort($grouped, function($a, $b) use ($sortDir) {
    $cmp = $a['semester_num'] - $b['semester_num'];
    return $sortDir === 'desc' ? -$cmp : $cmp;
});

// ── Статусы РУП
$statusLabels = [
    null       => ['Не загружена', 'wp-st-none'],
    'pending'  => ['На проверке',  'wp-st-pending'],
    'approved' => ['Утверждена',   'wp-st-approved'],
    'rejected' => ['Отклонена',    'wp-st-rejected'],
];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Рабочие программы — УМР — СВГТК Портал</title>
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
    .sort-btn.active {
      background: var(--color-primary-highlight);
      border-color: var(--color-primary); color: var(--color-primary);
    }

    /* ── Мультиселект предметов ── */
    .subject-filter-wrap {
      background: var(--color-surface); border: 1px solid var(--color-border);
      border-radius: var(--radius-xl); margin-bottom: 1.25rem;
      box-shadow: var(--shadow-sm); overflow: hidden;
    }
    .subject-filter-head {
      display: flex; align-items: center; justify-content: space-between;
      padding: .65rem 1.1rem; cursor: pointer;
      background: var(--color-surface-2); border-bottom: 1px solid var(--color-divider);
      user-select: none; gap: .75rem;
    }
    .subject-filter-title {
      font-size: .875rem; font-weight: 600; color: var(--color-text);
      display: flex; align-items: center; gap: .5rem;
    }
    .subject-filter-actions { display: flex; gap: .5rem; align-items: center; }
    .sf-btn {
      font-size: .75rem; padding: 2px 10px; border-radius: var(--radius-full);
      border: 1px solid var(--color-border); background: var(--color-surface);
      cursor: pointer; color: var(--color-text-muted); transition: all var(--transition);
    }
    .sf-btn:hover { border-color: var(--color-primary); color: var(--color-primary); }
    .sf-chevron { transition: transform .2s; flex-shrink: 0; }
    .subject-filter-body {
      display: flex; flex-wrap: wrap; gap: .4rem .75rem;
      padding: .75rem 1.1rem; max-height: 220px; overflow-y: auto;
    }
    .subject-filter-body.collapsed { display: none; }
    .sf-chip {
      display: inline-flex; align-items: center; gap: .3rem;
      padding: 3px 10px; border-radius: var(--radius-full);
      font-size: .78rem; font-weight: 500;
      border: 1.5px solid var(--color-border); background: var(--color-surface-2);
      cursor: pointer; user-select: none; transition: all .15s; white-space: nowrap;
    }
    .sf-chip input[type=checkbox] { display: none; }
    .sf-chip.checked {
      border-color: var(--color-primary);
      background: var(--color-primary-highlight); color: var(--color-primary);
    }
    .sf-chip .chip-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--color-border); flex-shrink: 0;
    }
    .sf-chip.checked .chip-dot { background: var(--color-primary); }

    /* ── Таблица ── */
    .assign-wrap { overflow-x: auto; }
    .assign-table {
      width: 100%; border-collapse: collapse;
      font-size: .8125rem; min-width: 1400px;
    }
    .assign-table th {
      background: var(--color-surface-2); padding: .5rem .6rem;
      border: 1px solid var(--color-border);
      font-size: .7rem; font-weight: 600;
      white-space: nowrap; color: var(--color-text-muted); text-align: left;
    }
    .assign-table td {
      padding: .45rem .6rem; border: 1px solid var(--color-divider);
      vertical-align: middle;
    }
    .assign-table .col-num { text-align: center; white-space: nowrap; }

    .row-group-header td {
      background: var(--color-primary-highlight) !important;
      font-weight: 700; font-size: .82rem; color: var(--color-primary);
      padding: .55rem .85rem; border-top: 2px solid var(--color-primary);
    }
    .row-group-header .gh-sem {
      display: inline-block; background: var(--color-primary); color: #fff;
      border-radius: var(--radius-full); padding: 1px 10px;
      font-size: .72rem; font-weight: 700; margin-left: .5rem;
    }

    .assign-table tbody tr.subject-row:nth-child(odd) td  { background: var(--color-surface); }
    .assign-table tbody tr.subject-row:nth-child(even) td { background: var(--color-surface-2); }
    .assign-table tbody tr.subject-row:hover td { background: var(--color-primary-highlight); }
    .subject-row.sf-hidden { display: none; }

    .badge-type { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: .68rem; font-weight: 700; }
    .t-ООД { background: #dbeafe; color: #1d4ed8; }
    .t-БМ  { background: #ede9fe; color: #7c3aed; }
    .t-ПМ  { background: #dcfce7; color: #15803d; }
    .t-ПА  { background: #fef3c7; color: #b45309; }
    .t-К, .t-Ф { background: #f3f4f6; color: #6b7280; }

    .ex-badge {
      display: inline-block; padding: 1px 8px; border-radius: var(--radius-full);
      font-size: .7rem; font-weight: 700;
    }
    .ex-yes-exam   { background: #fee2e2; color: #b91c1c; }
    .ex-yes-credit { background: #dcfce7; color: #15803d; }
    .ex-no { color: var(--color-text-muted); }

    .wp-badge {
      display: inline-flex; align-items: center; gap: .3rem;
      padding: 2px 9px; border-radius: var(--radius-full);
      font-size: .73rem; font-weight: 600; white-space: nowrap;
    }
    .wp-st-none     { background: #f3f4f6; color: #6b7280; }
    .wp-st-pending  { background: #fef3c7; color: #b45309; }
    .wp-st-approved { background: #dcfce7; color: #15803d; }
    .wp-st-rejected { background: #fee2e2; color: #b91c1c; cursor: help; }

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
    .action-cell { display: flex; flex-direction: column; gap: .3rem; align-items: flex-start; }
    .action-link { font-size: .74rem; color: var(--color-primary); text-decoration: none; }
    .action-link:hover { text-decoration: underline; }

    .ta-empty { text-align: center; padding: 3rem 2rem; color: var(--color-text-muted); }
    .ta-empty p:first-child { font-size: 1rem; font-weight: 600; margin-bottom: .25rem; }

    /* ── Модалка ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45);
      z-index: 1000; align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: var(--color-surface); border-radius: var(--radius-xl);
      padding: 1.5rem; width: 100%; max-width: 420px; box-shadow: var(--shadow-lg);
    }
    .modal-box h3 { margin: 0 0 1rem; font-size: 1.05rem; }
    .modal-row { margin-bottom: .85rem; }
    .modal-row label { display: block; font-size: .8rem; font-weight: 600; margin-bottom: .3rem; color: var(--color-text-muted); }
    .modal-row select, .modal-row input[type=file] {
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
    .modal-reject-reason {
      background: var(--color-error-highlight, #fee2e2); color: var(--color-error, #b91c1c);
      border-radius: var(--radius-md); padding: .5rem .75rem; font-size: .8rem; margin-bottom: .85rem;
    }
    .modal-confirm-text {
      font-size: .85rem; color: var(--color-text); margin-bottom: 1rem; line-height: 1.45;
    }
  </style>
</head>
<body>

<?php $_nav_active_key = 'work_programs';
      require __DIR__ . '/../../partials/sidebar.php'; ?>

<div class="main-wrapper" id="mainWrapper">
  
  <?php
    $_breadcrumbs = ['УМР' => null, 'Рабочие программы' => null];
    require_once BASE_PATH . '/partials/topbar.php';
    require_once BASE_PATH . '/partials/umr_subnav.php';
  ?>

  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Рабочие программы</h1>
        <p class="page-subtitle">
          <?= $isPlainTeacher  ? 'Мои назначенные дисциплины' : 'Рабочие программы преподавателей' ?> —
          <strong><?= e($academicYearLabel) ?></strong>,
          <strong><?= e($semLabel) ?></strong>
        </p>
      </div>
    </div>

    <?php if ($flash): ?>
    <div class="card" style="margin-bottom:1rem;background:var(--color-success-highlight);border-color:#a7f3d0">
      <div class="card-body" style="padding:.75rem 1.5rem;color:var(--color-success)"><?= e($flash) ?></div>
    </div>
    <?php endif ?>

    <!-- ── Фильтры ─────────────────────────────────────────────────────────── -->
    <form method="GET" class="ta-filters" id="filterForm">

      <?php include __DIR__ . '/../../partials/filter_academicYear.php'; ?>

      <?php include __DIR__ . '/../../partials/filter_semester.php'; ?>

      <?php include __DIR__ . '/../../partials/filter_groups.php'; ?>

      <?php if ($hasFullAccess): ?>
      <div class="form-group">
        <label class="form-label">Преподаватель</label>
        <select name="teacher_id" class="form-control" onchange="this.form.submit()">
          <option value="0">Все преподаватели</option>
          <?php foreach ($teachers as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $filterTeacherId ? 'selected' : '' ?>>
            <?= e($t['full_name']) ?>
          </option>
          <?php endforeach ?>
        </select>
      </div>
      <?php endif ?>

      <div class="form-group">
        <label class="form-label">Сортировка</label>
        <div style="display:flex;gap:.4rem">
          <a href="<?= sortLink($filterYear, $filterSem, $filterGroupId, $filterTeacherId, 'asc') ?>"
             class="sort-btn <?= $sortDir === 'asc' ? 'active' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
            </svg>А → Я
          </a>
          <a href="<?= sortLink($filterYear, $filterSem, $filterGroupId, $filterTeacherId, 'desc') ?>"
             class="sort-btn <?= $sortDir === 'desc' ? 'active' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
            </svg>Я → А
          </a>
        </div>
      </div>

    </form>

    <?php if (empty($grouped)): ?>
    <div class="card"><div class="ta-empty">
      <p>Нет данных для отображения</p>
      <p>
        В <?= e($academicYearLabel) ?> учебном году
        <?= $filterSem === 0 ? 'для выбранных семестров' : 'на ' . e($semLabel) ?>
        не найдено назначений
      </p>
    </div></div>
    <?php else: ?>

    <!--  Мультиселект предметов  -->
    <?php require __DIR__ . '/../../partials/multiselect.php'; ?>

    <!--  Сводная таблица  -->
    <div class="card">
      <div class="assign-wrap">
        <table class="assign-table" id="mainTable">
          <thead>
            <tr>
              <th style="width:70px">Индекс</th>
              <th style="min-width:190px">Дисциплина</th>
              <th>Тип</th>
              <th class="col-num">Кред.</th>
              <th class="col-num">Всего<br>часов</th>
              <th class="col-num">Теория</th>
              <th class="col-num">Практика</th>
              <th class="col-num">Курс.р.</th>
              <th class="col-num">СРСП</th>
              <th class="col-num">СРС</th>
              <th class="col-num">Экз.</th>
              <th class="col-num">Зач.</th>
              <th class="col-num">Контр. раб.</th>
              <th class="col-num">Часов<br>в сем.</th>
              <th style="min-width:170px">Назначил (ПЦК)</th>
              <th style="min-width:120px">Статус РУП</th>
              <th style="min-width:140px">Действие</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($grouped as $gKey => $blk): ?>
          <tr class="row-group-header">
            <td colspan="16">
              <?= e($blk['group_name']) ?>
              <span class="gh-sem"><?= $blk['semester_num'] ?> семестр</span>
              <span style="font-weight:400;font-size:.75rem;margin-left:.5rem;opacity:.8">
                <?= (int)$blk['course'] ?> курс
              </span>
            </td>
          </tr>
          <?php foreach ($blk['rows'] as $row):
            $mKey   = e($row['index_code'] . '||' . $row['module_name']);
            $isExam   = (int)($row['exam_semester']   ?? 0) === (int)$row['semester_num'];
            $isCredit = (int)($row['credit_semester'] ?? 0) === (int)$row['semester_num'];
            $cp = $row['coursework_hours'];

            $status = $row['wp_status'];
            [$stLabel, $stClass] = $statusLabels[$status] ?? $statusLabels[null];

            $canManage = $hasFullAccess
                || ((int)$row['teacher_id']  === $userId)
                || ((int)$row['pcc_head_id'] === $userId);
            $canApprove = $hasFullAccess || ((int)$row['pcc_head_id'] === $userId);

            $needsPccConfirm = !$isAdmin && (
                $isMethodist || ($isPccHead && (int)$row['pcc_head_id'] !== $userId)
            );
          ?>
          <tr class="subject-row" data-sf-key="<?= $mKey ?>">
            <td class="col-num">
              <span style="font-size:.77rem;font-weight:600;color:var(--color-text-muted)"><?= e($row['index_code']) ?></span>
            </td>
            <td><?= e($row['module_name']) ?></td>
            <td class="col-num">
              <span class="badge-type t-<?= e($row['module_type']) ?>"><?= e($row['module_type']) ?></span>
            </td>
            <td class="col-num"><?= $row['credits'] ?? '—' ?></td>
            <td class="col-num"><?= $row['total_hours'] ?? '—' ?></td>
            <td class="col-num"><?= $row['theory_hours'] ?? '—' ?></td>
            <td class="col-num"><?= $row['practice_hours'] ?? '—' ?></td>
            <td class="col-num"><?= $cp ? $cp : '—' ?></td>
            <td class="col-num"><?= $row['srsp_hours'] ?? '—' ?></td>
            <td class="col-num"><?= $row['srs_hours'] ?? '—' ?></td>
            
            <td class="col-num">
              <?= $isExam ? '<span class="ex-badge ex-yes-exam">Экз</span>' : '<span class="ex-no">—</span>' ?>
            </td>

            <td class="col-num">
              <?= $isCredit ? '<span class="ex-badge ex-yes-credit">Зач</span>' : '<span class="ex-no">—</span>' ?>
            </td>
             
            <td class="col-num"><?= $row['control_work'] ?? '—' ?></td>

            <td class="col-num" style="font-weight:700;color:var(--color-primary)"><?= $row['semester_hours'] ?? '—' ?></td>
            
            <td><?= e($row['pcc_name']) ?></td>
           
            <td>
              <span class="wp-badge <?= $stClass ?>"
                <?php if ($status === 'rejected' && $row['wp_reject_reason']): ?>
                title="<?= e($row['wp_reject_reason']) ?>"
                <?php endif ?>
              ><?= e($stLabel) ?><?= $row['wp_version'] ? ' (v' . (int)$row['wp_version'] . ')' : '' ?></span>
            </td>

            <td>
                <div class="action-cell">
                    <?php if ($row['wp_file_path']): ?>
                        <a class="action-link" href="../../<?= e($row['wp_file_path']) ?>" target="_blank">Открыть файл</a>
                    <?php endif ?>

                    <?php if (!$canManage): ?>
                        <?php if (!$row['wp_id']): ?>
                            <span style="font-size:.74rem;color:var(--color-text-muted)">—</span>
                        <?php endif ?>

                    <?php elseif (!$row['wp_id']): ?>
                        <!-- Загрузить РП (первый раз) -->
                        <button class="btn-action" onclick='openWpModal(<?= json_encode([
                            "mode" => "upload",
                            "assignment_id"  => (int)$row["assignment_id"],
                            "module_id"      => (int)$row["module_id"],
                            "group_id"       => (int)$row["group_id"],
                            "teacher_name"   => $row["teacher_name"],
                            "title"          => $row["index_code"] . ' ' . $row["module_name"],
                            "needs_pcc_confirm" => $needsPccConfirm,
                            "current_pcc_id"    => (int)$row["pcc_head_id"],
                            "current_pcc_name"  => $row["pcc_name"],
                        ], JSON_UNESCAPED_UNICODE) ?>)'>Загрузить РП</button>

                    <?php else: // Файл уже загружен ?>

                        <?php 
                        $isOwnAssignment = ((int)$row['pcc_head_id'] === $userId);
                        
                        $canReplaceFile = $isAdmin 
                                      || ($isPccHead && $isOwnAssignment) 
                                      || $isMethodist
                                      || ((int)$row['teacher_id'] === $userId);   
                        ?>

                        <?php if ($status === 'pending' && $canApprove): ?>
                          <div style="display:flex;gap:.3rem">

                              <?php 
                              $isOwnAssignment = ((int)$row['pcc_head_id'] === $userId);
                              $needsConfirm    = $isPccHead && !$isOwnAssignment;
                              ?>

                              <!-- Утвердить -->
                              <button class="btn-action" style="background:var(--color-success,#15803d)"
                                      onclick="<?= $needsConfirm 
                                          ? "confirmApproveWp({$row['wp_id']}, '".addslashes(e($row['pcc_name']))."')" 
                                          : "approveWp({$row['wp_id']})" ?>">
                                  Утвердить
                              </button>

                              <!-- Отклонить -->
                              <button class="btn-action" style="background:var(--color-error,#b91c1c)"
                                      onclick="<?= $needsConfirm 
                                          ? "confirmRejectWp({$row['wp_id']}, '".addslashes(e($row['pcc_name']))."')" 
                                          : "rejectWp({$row['wp_id']})" ?>">
                                  Отклонить
                              </button>

                          </div>
                        <?php endif ?>

                        <?php if ($status !== 'approved' || $hasFullAccess): ?>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                            
                            <?php if ($canReplaceFile): ?>
                            <button class="btn-action secondary" onclick='openWpModal(<?= json_encode([
                                "mode"          => "update",
                                "wp_id"         => (int)$row["wp_id"],
                                "assignment_id" => (int)$row["assignment_id"],
                                "teacher_name"  => $row["teacher_name"],
                                "title"         => $row["index_code"] . ' ' . $row["module_name"],
                                "reject_reason" => $row["wp_reject_reason"],
                                "needs_pcc_confirm" => $needsPccConfirm,
                                "current_pcc_id"    => (int)$row["pcc_head_id"],
                                "current_pcc_name"  => $row["pcc_name"],
                            ], JSON_UNESCAPED_UNICODE) ?>)'>Заменить файл</button>
                            <?php endif ?>

                            <!-- Удалить только Админ или свой ПЦК -->
                            <?php if ($isAdmin || ($isPccHead && $isOwnAssignment)): ?>
                            <button class="btn-action secondary" 
                                    style="border-color:var(--color-error,#b91c1c);color:var(--color-error,#b91c1c)"
                                    onclick="deleteWp(<?= (int)$row['wp_id'] ?>)">Удалить</button>
                            <?php endif ?>
                        </div>
                        <?php else: ?>
                            <span style="font-size:.74rem;color:var(--color-text-muted)">Утверждена</span>
                        <?php endif ?>

                    <?php endif ?>
                </div>
            </td>

          </tr>
          <?php endforeach ?>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif ?>

  </main>
</div>

<script>
  // Переменные для JS-модалок
  const isAdmin      = <?= $isAdmin ? 'true' : 'false' ?>;
  const isPccHead    = <?= $isPccHead ? 'true' : 'false' ?>;
  const isMethodist  = <?= $isMethodist ? 'true' : 'false' ?>;
  const currentUserId = <?= (int)$userId ?>;

</script>

<!-- Модалка уведомления "преподаватель назначен другим ПЦК" -->
<div class="modal-overlay" id="pccConfirmOverlay">
  <div class="modal-box">
    <h3>Подтверждение загрузки</h3>
    <p class="modal-confirm-text">
      Вы загружаете файл за преподавателя, закреплённого за другим председателем ПЦК.
      Файл будет записан под другим председателем.
    </p>
    <div class="modal-row">
      <label>Председатель ПЦК</label>
      <div class="modal-readonly" id="pccConfirmName">—</div>
    </div>
    <div class="modal-actions">
      <button class="btn-action secondary" type="button" onclick="closePccConfirmModal()">Отмена</button>
      <button class="btn-action" type="button" onclick="confirmPccAndOpenWpModal()">Подтвердить</button>
    </div>
  </div>
</div>

<!-- Модальное окно подтверждения утверждения (для другого ПЦК) -->
<div class="modal-overlay" id="approveConfirmOverlay">
  <div class="modal-box">
    <h3>Подтверждение утверждения</h3>
    <p class="modal-confirm-text">
      Вы пытаетесь утвердить рабочую программу преподавателя, 
      назначенного <strong>другим</strong> председателем ПЦК.
    </p>
    <div class="modal-row">
      <label>Назначил ПЦК</label>
      <div class="modal-readonly" id="approvePccName">—</div>
    </div>
    <div class="modal-actions">
      <button class="btn-action secondary" onclick="closeApproveConfirmModal()">Отмена</button>
      <button class="btn-action" style="background:var(--color-success,#15803d)" 
              id="confirmApproveBtn">Да, утвердить</button>
    </div>
  </div>
</div>

<!-- Модальное окно подтверждения ОТКЛОНЕНИЯ (для чужой записи) -->
<div class="modal-overlay" id="rejectConfirmOverlay">
  <div class="modal-box">
    <h3>Подтверждение отклонения</h3>
    <p class="modal-confirm-text">
      Вы пытаетесь отклонить рабочую программу преподавателя, 
      назначенного <strong>другим</strong> председателем ПЦК.
    </p>
    <div class="modal-row">
      <label>Назначил ПЦК</label>
      <div class="modal-readonly" id="rejectPccName">—</div>
    </div>
    <div class="modal-actions">
      <button class="btn-action secondary" onclick="closeRejectConfirmModal()">Отмена</button>
      <button class="btn-action" style="background:var(--color-error,#b91c1c)" 
              id="confirmRejectBtn">Да, отклонить</button>
    </div>
  </div>
</div>

<!-- Модалка загрузки/замены РУП -->
<div class="modal-overlay" id="wpModalOverlay">
  <div class="modal-box">
    <h3 id="wpModalTitle">Загрузка рабочей программы</h3>

    <div class="modal-reject-reason" id="wpRejectReason" style="display:none"></div>

    <div class="modal-row">
      <label>Преподаватель</label>
      <div class="modal-readonly" id="wpTeacherName">—</div>
    </div>

    <form id="wpForm">
      <div class="modal-row">
        <label>Файл (.pdf, .doc, .docx, .xls, .xlsx до 20 МБ)</label>
        <input type="file" name="file" id="wpFile" accept=".pdf, .doc, .docx, .xls, .xlsx" required>
      </div>
    </form>

    <div class="modal-error" id="wpModalError"></div>

    <div class="modal-actions">
      <button class="btn-action secondary" type="button" onclick="closeWpModal()">Отмена</button>
      <button class="btn-action" type="button" id="wpSubmitBtn" onclick="submitWpModal()">Загрузить</button>
    </div>
  </div>
</div>

<!-- Загрузка мультиселекта, обработка кнопок, тема -->
<script src="../../js/subject_filter.js"></script>
<script src="../../js/work_programs.js"></script>
<script src="../../js/umr.js"></script>

</body>
</html>
