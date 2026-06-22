<?php
// views/curricula/index.php

define('BASE_PATH', dirname(__DIR__, 2));

// Авторизация
require_once BASE_PATH . '/partials/init.php';

// Проверка прав
if (!$canCurricula && !$isPccHead && !$isMethodist) {
    http_response_code(403);
    echo "Доступ запрещён. У вас нет прав для просмотра этого раздела.";
    exit;
}

// Подключение файлов
require_once BASE_PATH . '/models/baseModel.php';

require_once BASE_PATH . '/models/edu_groups.php';
require_once BASE_PATH . '/models/edu_semesters.php';

require_once BASE_PATH . '/models/edu_curriculum_calendar.php';

// Создание классов моделей
$moduleSem      = new edu_semesters($pdo);
$moduleGroups   = new edu_groups($pdo);
$moduleCalendar = new edu_curriculum_calendar($pdo);

$today = date('Y-m-d');

// Активный семестр (нужен только для значения по умолчанию для года)
$activeSem = $moduleSem->getActive($today);

// Все учебные годы для дропдауна
$allAcademicYears = $moduleGroups->getAcademicYears();

// Фильтры
$filterYear = (int)($_GET['academic_year']
    ?? $_SESSION['cc_filter_year']
    ?? ($activeSem['year_start'] ?? date('Y')));
$_SESSION['cc_filter_year'] = $filterYear;

$filterGroupId = (int)($_GET['group_id'] ?? 0);

$sortDir = $_GET['sort'] ?? ($_SESSION['cc_sort'] ?? 'asc');
$sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';
$_SESSION['cc_sort'] = $sortDir;

$academicYearLabel = $filterYear . '/' . ($filterYear + 1);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sortLink(int $year, int $groupId, string $dir): string
{
    return '?academic_year=' . $year . '&group_id=' . $groupId . '&sort=' . $dir;
}

// Сетка месяцев 
function edu_curriculum_calendar_default_months(): array
{
    $ranges = [
        'сентябрь' => [1, 5],
        'октябрь'  => [6, 9],
        'ноябрь'   => [10, 13],
        'декабрь'  => [14, 18],
        'январь'   => [19, 23],
        'февраль'  => [24, 27],
        'март'     => [28, 32],
        'апрель'   => [33, 36],
        'май'      => [37, 40],
        'июнь'     => [41, 44],
        'июль'     => [45, 48],
        'август'   => [49, 52],
    ];
    $map = [];
    foreach ($ranges as $month => [$from, $to]) {
        for ($w = $from; $w <= $to; $w++) $map[$w] = $month;
    }
    return $map;
}

function edu_graph_cell_span(?array $cell, int $week): int
{
    if (!$cell) return 1;
    $span = (int)($cell['span_weeks'] ?? 1);
    $value = trim((string)($cell['value_text'] ?? ''));

    if ($span <= 1 && preg_match('/(?:^|\s)(\d{1,2})(?:\s*)$/u', $value, $m)) {
        $n = (int)$m[1];
        if ($n > 1 && $n <= 52) $span = $n;
    }

    return max(1, min(52 - $week + 1, $span));
}

// Группы, всплывающие в выбранном учебном году
$dropdownGroups = $moduleCalendar->getActiveGroupsForYear($filterYear, 0, 'asc');

//  Группы попадающие под фильтр 
$activeGroups = $filterGroupId
    ? array_values(array_filter($dropdownGroups, fn($g) => (int)$g['id'] === $filterGroupId))
    : $dropdownGroups;

usort($activeGroups, function ($a, $b) use ($sortDir) {
    $cmp = strcmp($a['name'], $b['name']);
    return $sortDir === 'desc' ? -$cmp : $cmp;
});

// Расписание для каждой группы 
$curriculumIds = array_values(array_unique(array_map(fn($g) => (int)$g['curriculum_id'], $activeGroups)));
$monthMap      = $moduleCalendar->getMonthMapForCurricula($curriculumIds);
$legend        = $moduleCalendar->getLegendForCurricula($curriculumIds);

// Если по выбранным планам совсем нет месяцев
$nonEmptyMonths = array_filter(array_map('trim', $monthMap), static fn($v) => $v !== '');
if (count($nonEmptyMonths) < 10) {
    $monthMap = edu_curriculum_calendar_default_months();
}

$rows = [];
foreach ($activeGroups as $g) {
    $course = (int)$g['current_course'];
    $label  = edu_curriculum_calendar::courseLabel($course);
    if ($label === null) continue;

    $schedule = $moduleCalendar->getScheduleRows((int)$g['curriculum_id'], $label);

    $rows[] = [
        'group'         => $g,
        'course_label'  => $label,
        'schedule'      => $schedule,
        'has_schedule'  => !empty($schedule),
    ];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>

<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>График учебного процесса — УМР — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../css/style.css">
  <style>
  .graph-table { 
    border-collapse: collapse; 
    width: max-content; 
    font-size: 0.82rem; 
    margin-bottom: 1.5rem;
  }
  
  .graph-table th, .graph-table td {
    border: 1px solid var(--color-border, #e2e8f0);
    padding: 6px 4px;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
  }

  .graph-table thead th { 
    background: var(--color-bg-soft, #f8fafc); 
    font-weight: 600; 
  }

  /* Фиксированные колонки */
  .graph-table .group-cell {
    position: sticky; 
    left: 0; 
    z-index: 3;
    background: #fff; 
    text-align: left; 
    font-weight: 600;
    min-width: 160px;
    padding-left: 12px;
  }
  
  .graph-table .course-cell {
    position: sticky; 
    left: 160px; 
    z-index: 3;
    background: #fff; 
    min-width: 50px;
  }

  .graph-table thead .group-cell,
  .graph-table thead .course-cell { 
    background: #f1f5f9; 
    z-index: 4; 
  }

  /* Месяцы в шапке */
  .graph-table thead tr:first-child th[colspan] {
    font-weight: 700;
    color: #1e2937;
    background: #f8fafc;
    padding: 8px 4px;
  }

  .graph-filled { 
    background: #eef2ff; 
    font-weight: 600; 
    color: var(--color-primary);
  }
  
  .graph-empty { 
    color: #94a3b8; 
  }

  .graph-no-schedule td { 
    color: #64748b; 
    font-style: italic; 
    text-align: left; 
    padding: 12px 16px !important;
  }

  
  .form-group {
    margin-bottom: 0;
  }

  /* Легенда внизу */
  .legend-grid { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 1rem 2rem; 
    margin-top: 2rem;
    padding: 1rem 1.25rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
  }
  
  .legend-item { 
    display: flex; 
    align-items: center; 
    gap: .5rem; 
    font-size: 0.85rem;
  }
  
  .legend-code { 
    background: #e0e7ff; 
    color: var(--color-primary); 
    font-weight: 700; 
    padding: 2px 7px; 
    border-radius: 5px;
    font-size: 0.8rem;
  }
</style>
</head>
<body>

<!-- Sidebar-->
<?php $_nav_active_key = 'curricula';
      require __DIR__ . '/../../partials/sidebar.php'; ?>

<div class="main-wrapper" id="mainWrapper">

  <!-- Topbar-->
  <?php
    $_breadcrumbs = [
      'УМР' => null,
      'График учебного процесса' => null];
    require_once BASE_PATH . '/partials/topbar.php';
  ?>

  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">График учебного процесса</h1>
        <p class="page-subtitle">
          Все группы — <strong><?= e($academicYearLabel) ?></strong>
        </p>
      </div>

      <div style="margin-left:auto">
        <a class="btn-export"
           href="actions/export.php?academic_year=<?= (int)$filterYear ?>&group_id=<?= (int)$filterGroupId ?>&sort=<?= e($sortDir) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Экспорт в Excel
        </a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="flash flash-<?= e($flash['type'] ?? 'info') ?>"><?= e($flash['message'] ?? '') ?></div>
    <?php endif ?>

    <!-- Фильтры -->
      <form method="GET" class="ta-filters" id="filterForm">

        <?php include __DIR__ . '/../../partials/filter_academicYear.php'; ?>
        
        <?php include __DIR__ . '/../../partials/filter_groups.php'; ?>


        <div class="form-group">
          <label class="form-label">Сортировка</label>
          <div style="display:flex;gap:.4rem">
            <a href="<?= sortLink($filterYear, $filterGroupId, 'asc') ?>"
               class="sort-btn <?= $sortDir === 'asc' ? 'active' : '' ?>">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
              </svg>А → Я
            </a>
            <a href="<?= sortLink($filterYear, $filterGroupId, 'desc') ?>"
               class="sort-btn <?= $sortDir === 'desc' ? 'active' : '' ?>">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
              </svg>Я → А
            </a>
          </div>
        </div>

      </form>
    

    <!-- Таблица графика -->
    <div class="card">
      <div class="card-header"><span class="card-title">График учебного процесса</span></div>
      <div style="overflow-x:auto">
        <table class="graph-table">
          <thead>
            <tr>
              <th class="group-cell" rowspan="2">Группа</th>
              <th class="course-cell" rowspan="2">Курс</th>
              
              <?php
              $w = 1;
              $defaultMonths = edu_curriculum_calendar_default_months();

              while ($w <= 52):
                  // Берём месяц из базы, если пусто  из стандартной сетки
                  $month = trim($monthMap[$w] ?? '');
                  if (empty($month)) {
                      $month = $defaultMonths[$w] ?? '—';
                  }

                  // Считаем, сколько недель подряд этот месяц
                  $span = 1;
                  while (($w + $span) <= 52) {
                      $nextMonth = trim($monthMap[$w + $span] ?? '');
                      if (empty($nextMonth)) {
                          $nextMonth = $defaultMonths[$w + $span] ?? '';
                      }
                      if ($nextMonth !== $month) break;
                      $span++;
                  }
              ?>
                <th colspan="<?= $span ?>" style="font-weight: 600; color: #1e2937;">
                  <?= e($month) ?>
                </th>
              <?php 
                  $w += $span; 
              endwhile ?>
            </tr>

            <!-- Номера недель -->
            <tr>
              <?php for ($w = 1; $w <= 52; $w++): ?>
                <th style="font-size: 0.85rem;"><?= $w ?></th>
              <?php endfor ?>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($rows as $r):
                $g        = $r['group'];
                $schedule = $r['schedule'];
            ?>

              <?php if (!$r['has_schedule']): ?>

                <tr class="graph-no-schedule">
                  <td class="group-cell"><?= e($g['name']) ?></td>
                  <td class="course-cell"><?= e($r['course_label']) ?></td>
                  <td colspan="52">
                    График не импортирован для плана «<?= e($g['curriculum_name'] ?? $g['specialty_name']) ?>».
                  </td>
                </tr>

              <?php else: ?>

                <tr>
                  <td class="group-cell"><?= e($g['name']) ?></td>
                  <td class="course-cell"><?= e($r['course_label']) ?></td>
                  <?php
                  $w = 1;
                  while ($w <= 52):
                      $cell = $schedule[$w] ?? null;
                      $span = edu_graph_cell_span($cell, $w);
                      $value = trim((string)($cell['value_text'] ?? ''));
                      $class = $value !== '' ? 'graph-filled' : 'graph-empty';
                  ?>
                  <td class="<?= $class ?>" colspan="<?= $span ?>"><?= e($value) ?></td>
                  <?php $w += $span; endwhile ?>
                </tr>

              <?php endif ?>

            <?php endforeach ?>

          <?php if (empty($rows)): ?>
            <tr><td colspan="54" class="empty-cell">Группы для выбранного учебного года не найдены.</td></tr>
          <?php endif ?>

        </tbody>
        </table>
      </div>

      <?php if (!empty($legend)): ?>
      <div class="legend-grid">
        <?php foreach ($legend as $item): ?>
        <div class="legend-item">
          <span class="legend-code"><?= e($item['code']) ?></span>
          <span><?= e($item['description']) ?></span>
        </div>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </div>

  </main>
</div>
<script src="../../js/umr.js"></script>
</body>
</html>
