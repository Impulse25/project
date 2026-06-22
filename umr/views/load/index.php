<?php
// views/load/index.php

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/partials/init.php';

//  Проверка доступа
if (!$canLoadSummary && !$isPccHead && !$isMethodist) {
    http_response_code(403);
    echo "Доступ запрещён.";
    exit;
}

//  Подключение модели 
require_once BASE_PATH . '/models/baseModel.php';
require_once BASE_PATH . '/models/umr_load.php';

$model = new umr_load($pdo);

$today = date('Y-m-d');

//  Кто имеет полный доступ 
$hasFullAccess = $isAdmin || $isMethodist || $isPccHead;

//  Активный учебный год
$activeSem = $pdo->prepare("
    SELECT year_start FROM edu_semesters
    WHERE start_date <= ? AND end_date >= ?
    ORDER BY year_start DESC LIMIT 1
");

$activeSem->execute([$today, $today]);
$activeSem = $activeSem->fetchColumn();
$defaultYear = $activeSem ? (int)$activeSem : (int)date('Y');

$filterYear = (int)($_GET['academic_year'] ?? $_SESSION['load_year'] ?? $defaultYear);
$_SESSION['load_year'] = $filterYear;
$yearLabel = $filterYear . '/' . ($filterYear + 1);

// Кого смотрим 
$viewId = $userId; // по умолчанию  себя
if ($hasFullAccess && !empty($_GET['teacher_id'])) {
    $viewId = (int)$_GET['teacher_id'];
}

//  Список преподавателей для фильтра
$teacherList = [];
if ($hasFullAccess) {
    $teacherList = $pdo->query(
        "SELECT id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name"
    )->fetchAll();
}

//  Список учебных годов
$allYears = $model->getYearsForTeacher($viewId);
if (empty($allYears)) {
    $allYears = [$defaultYear];
}

// Убедиться, что текущий год есть в списке
if (!in_array($filterYear, $allYears)) {
    array_unshift($allYears, $filterYear);
}

//  Имя просматриваемого преподавателя
$viewName = $model->getTeacherName($viewId) ?: 'Преподаватель';

//  Основной запрос нагрузки
$rows = $model->getTeacherLoad($viewId, $filterYear);

//  Группировка по группе
$byGroup = [];
foreach ($rows as $r) {
    $gid = $r['group_id'];
    if (!isset($byGroup[$gid])) {
        $byGroup[$gid] = [
            'group_name'   => $r['group_name'],
            'course_num'   => $r['course_num'],
            'year_started' => $r['year_started'],
            'rows'         => [],
        ];
    }
    $byGroup[$gid]['rows'][] = $r;
}

//  Сортировка групп
$sortDir = $_GET['sort'] ?? ($_SESSION['load_sort'] ?? 'asc');
$sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';
$_SESSION['load_sort'] = $sortDir;

uasort($byGroup, function ($a, $b) use ($sortDir) {
    $cmp = strcmp($a['group_name'], $b['group_name']);
    return $sortDir === 'desc' ? -$cmp : $cmp;
});

//  Итоги
$totalOdd  = 0;
$totalEven = 0;
foreach ($rows as $r) {
    $totalOdd  += (int)($r['hours_odd']  ?? 0);
    $totalEven += (int)($r['hours_even'] ?? 0);
}
$totalAll     = $totalOdd + $totalEven;
$totalModules = count($rows);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sortLink(int $year, int $teacherId, string $dir): string {
    return '?academic_year=' . $year . '&teacher_id=' . $teacherId . '&sort=' . $dir;
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Нагрузка — УМР — СВГТК Портал</title>
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

    /* KPI */
    .kpi-strip {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(130px,1fr));
      gap: .75rem; margin-bottom: 1.5rem;
    }
    .kpi-card {
      background: var(--color-surface); border: 1px solid var(--color-border);
      border-radius: var(--radius-xl); padding: .85rem 1.1rem; box-shadow: var(--shadow-sm);
    }
    .kpi-card span  { display: block; font-size: .72rem; color: var(--color-text-muted); margin-bottom: .2rem; }
    .kpi-card strong { display: block; font-size: 1.3rem; font-weight: 700; line-height: 1; }

    /* Таблица */
    .load-wrap { overflow-x: auto; }
    .load-table { width: 100%; border-collapse: collapse; font-size: .8125rem; min-width: 700px; }
    .load-table th {
      background: var(--color-surface-2); padding: .5rem .75rem;
      border: 1px solid var(--color-border);
      font-size: .72rem; font-weight: 600; color: var(--color-text-muted);
      white-space: nowrap;
    }
    .load-table th.col-num, .load-table td.col-num { text-align: center; }
    .load-table td {
      padding: .45rem .75rem; border: 1px solid var(--color-divider); vertical-align: middle;
    }
    .load-table tbody tr:nth-child(odd) td  { background: var(--color-surface); }
    .load-table tbody tr:nth-child(even) td { background: var(--color-surface-2); }
    .load-table tbody tr:hover td { background: var(--color-primary-highlight); }

    /* Разделитель группы */
    .row-group td {
      background: var(--color-primary-highlight) !important;
      font-weight: 700; font-size: .82rem; color: var(--color-primary);
      padding: .5rem .75rem; border-top: 2px solid var(--color-primary);
    }
    .row-group .gh-badge {
      display: inline-block; background: var(--color-primary); color: #fff;
      border-radius: var(--radius-full); padding: 1px 9px;
      font-size: .72rem; font-weight: 700; margin-left: .5rem;
    }

    /* Итоговые строки */
    .row-total td {
      font-weight: 700; background: var(--color-surface-2) !important;
      border-top: 2px solid var(--color-border);
      color: var(--color-text);
    }
    .row-grand td {
      font-weight: 700; background: var(--color-primary-highlight) !important;
      border-top: 2px solid var(--color-primary);
      color: var(--color-primary);
    }

    /* Бейджи типов */
    .badge-type { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: .68rem; font-weight: 700; }
    .t-ООД { background: #dbeafe; color: #1d4ed8; }
    .t-БМ  { background: #ede9fe; color: #7c3aed; }
    .t-ПМ  { background: #dcfce7; color: #15803d; }
    .t-ПА  { background: #fef3c7; color: #b45309; }
    .t-К, .t-Ф { background: #f3f4f6; color: #6b7280; }

    .hours-cell  { font-weight: 600; }
    .hours-total { color: var(--color-primary); font-weight: 700; }

    .ta-empty { text-align: center; padding: 3rem 2rem; color: var(--color-text-muted); }
    .ta-empty p:first-child { font-size: 1rem; font-weight: 600; margin-bottom: .25rem; }

    @media print {
      .ta-filters, .page-header .page-actions, .sidebar, #mainWrapper > header { display: none !important; }
      .load-table { font-size: .75rem; }
    }
  </style>
</head>
<body>
<?php $_nav_active_key = 'load';
      require __DIR__ . '/../../partials/sidebar.php'; ?>

<div class="main-wrapper" id="mainWrapper">
  <?php
    $_breadcrumbs = ['УМР' => null, 'Нагрузка' => null];
    require_once BASE_PATH . '/partials/topbar.php';
  ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">
          <?= ($hasFullAccess && $viewId !== $userId) ? 'Нагрузка: ' . e($viewName) : 'Моя нагрузка' ?>
        </h1>
        <p class="page-subtitle">Учебный год <strong><?= e($yearLabel) ?></strong></p>
      </div>

      <div class="page-actions" style="display:flex;gap:.5rem">
        <?php
          $exportTeacherId = $hasFullAccess ? $viewId : $userId;
        ?>

        <a href="actions/export_load.php?academic_year=<?= $filterYear ?>&teacher_id=<?= $exportTeacherId ?>"
           class="btn-export">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Экспорт в Excel
        </a>
      </div>
    </div>

    <!--  Фильтры  -->
    <form method="GET" class="ta-filters">

      <!-- Учебный год -->
      <div class="form-group">
        <label class="form-label">Учебный год</label>
        <select name="academic_year" class="form-control" onchange="this.form.submit()">
          <?php foreach ($allYears as $yr): ?>
          <option value="<?= $yr ?>" <?= $yr == $filterYear ? 'selected' : '' ?>>
            <?= $yr ?>/<?= $yr+1 ?>
          </option>
          <?php endforeach ?>
        </select>
      </div>

      <!-- Список преподавателей-->
      <?php if ($hasFullAccess): ?>

        <div class="form-group">
          <label class="form-label">Преподаватель</label>
          <select name="teacher_id" class="form-control" onchange="this.form.submit()">
            <option value="">— все / себя —</option>
            <?php foreach ($teacherList as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $viewId ? 'selected' : '' ?>>
              <?= e($t['full_name']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>

      <?php else: ?>
        <!-- Обычный преподаватель -->
        <input type="hidden" name="teacher_id" value="<?= $userId ?>">
      <?php endif ?>

      <!-- Сортировка групп -->
      <div class="form-group">
        <label class="form-label">Сортировка групп</label>
        
        <div style="display:flex;gap:.4rem">
          <a href="<?= sortLink($filterYear, $viewId, 'asc') ?>"
             class="sort-btn <?= $sortDir === 'asc' ? 'active' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
            </svg>А → Я
          </a>
          <a href="<?= sortLink($filterYear, $viewId, 'desc') ?>"
             class="sort-btn <?= $sortDir === 'desc' ? 'active' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
            </svg>Я → А
          </a>
        </div>

      </div>

    </form>

    <!--  KPI -->
    <div class="kpi-strip">
      <div class="kpi-card">
        <span>Дисциплин</span>
        <strong><?= $totalModules ?></strong>
      </div>
      <div class="kpi-card">
        <span>1 Семестр</span>
        <strong style="color:var(--color-primary)"><?= $totalOdd ?></strong>
      </div>
      <div class="kpi-card">
        <span>2 Семестр</span>
        <strong style="color:var(--color-primary)"><?= $totalEven ?></strong>
      </div>
      <div class="kpi-card">
        <span>Итого часов</span>
        <strong style="color:#15803d"><?= $totalAll ?></strong>
      </div>
    </div>

    <!--  ТАБЛИЦА -->
    <?php if (empty($rows)): ?>
    <div class="card"><div class="ta-empty">
      <p>Нет нагрузки</p>
      <p>На <?= e($yearLabel) ?> назначений не найдено<?= ($hasFullAccess && $viewId !== $userId) ? ' для ' . e($viewName) : '' ?></p>
    </div></div>
    <?php else: ?>
    <div class="card">
      <div class="load-wrap">
        <table class="load-table" id="loadTable">
          <thead>
            <tr>
              <th class="col-num" style="width:40px">№</th>
              <th>Индекс</th>
              <th style="min-width:220px">Дисциплина</th>
              <th class="col-num">Тип</th>
              <th class="col-num" title="Всего часов по учебной программе">Всего<br><small style="font-weight:400;font-size:.65rem">часов</small></th>
              <th class="col-num">Теория</th>
              <th class="col-num">Практика</th>
              <th class="col-num"
                  title="Часы в нечётном (осеннем) семестре учебного года">
                1 Сем.
              </th>
              <th class="col-num"
                  title="Часы в чётном (весеннем) семестре учебного года">
                2 Сем.
              </th>
              <th class="col-num">Итого<br><small style="font-weight:400;font-size:.65rem">за год</small></th>
              <th>ПЦК</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $rowNum   = 0;
          $grandOdd = $grandEven = 0;
          foreach ($byGroup as $gid => $grp):
            $gOdd = $gEven = 0;
          ?>
          <!-- Разделитель группы -->
          <tr class="row-group">
            <td colspan="11">
              <?= e($grp['group_name']) ?>
              <span class="gh-badge"><?= (int)$grp['course_num'] ?> курс</span>
              <span style="font-weight:400;font-size:.75rem;margin-left:.75rem;opacity:.8">
                поступление <?= (int)$grp['year_started'] ?>
              </span>
            </td>
          </tr>

          <?php foreach ($grp['rows'] as $r):
            $rowNum++;
            $odd   = (int)($r['hours_odd']  ?? 0);
            $even  = (int)($r['hours_even'] ?? 0);
            $total = $odd + $even;
            $gOdd  += $odd;
            $gEven += $even;
          ?>
          <tr>
            <td class="col-num" style="color:var(--color-text-muted)"><?= $rowNum ?></td>
            <td style="font-size:.77rem;font-weight:600;color:var(--color-text-muted);white-space:nowrap">
              <?= e($r['index_code']) ?>
            </td>
            <td><?= e($r['module_name']) ?></td>
            <td class="col-num">
              <span class="badge-type t-<?= e($r['module_type']) ?>"><?= e($r['module_type']) ?></span>
            </td>
            <td class="col-num" style="font-weight:600"><?= $r['total_hours'] ?? '—' ?></td>
            <td class="col-num"><?= $r['theory_hours']   ?? '—' ?></td>
            <td class="col-num"><?= $r['practice_hours'] ?? '—' ?></td>
            <td class="col-num hours-cell"><?= $odd  ?: '—' ?></td>
            <td class="col-num hours-cell"><?= $even ?: '—' ?></td>
            <td class="col-num hours-total"><?= $total ?: '—' ?></td>
            <td style="font-size:.77rem;color:var(--color-text-muted)"><?= e($r['pcc_name']) ?></td>
          </tr>
          <?php endforeach ?>

          <!-- Итого по группе -->
          <?php $gTotal = $gOdd + $gEven; $grandOdd += $gOdd; $grandEven += $gEven; ?>
          <tr class="row-total">
            <td colspan="7" style="text-align:right;font-size:.78rem;padding-right:1rem">
              Итого по группе <?= e($grp['group_name']) ?>:
            </td>
            <td class="col-num"><?= $gOdd  ?: '—' ?></td>
            <td class="col-num"><?= $gEven ?: '—' ?></td>
            <td class="col-num" style="color:var(--color-primary)"><?= $gTotal ?></td>
            <td></td>
          </tr>

          <?php endforeach ?>

          <!-- Итого за год -->
          <tr class="row-grand">
            <td colspan="7" style="text-align:right;font-size:.82rem;padding-right:1rem">
              ИТОГО за <?= e($yearLabel) ?>:
            </td>
            <td class="col-num"><?= $grandOdd ?></td>
            <td class="col-num"><?= $grandEven ?></td>
            <td class="col-num" style="font-size:1rem"><?= $grandOdd + $grandEven ?></td>
            <td></td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif ?>

  </main>
</div>
<script src="../../js/umr.js"></script>
</body>
</html>
