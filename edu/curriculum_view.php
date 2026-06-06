<?php
/**
 * edu/curriculum_view.php — Просмотр одного РУПл
 */
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$role    = edu_current_role();
$isAdmin = edu_is_admin();

if (!in_array($role, ['admin', 'director', 'teacher'], true)) {
    header('Location: index.php'); exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: curricula.php'); exit; }

// Загружаем план
$curriculum = $pdo->prepare("
    SELECT c.*, s.name_ru AS specialty_db
    FROM edu_curricula c
    LEFT JOIN edu_specialties s ON s.id = c.speciality_id
    WHERE c.id = ?
");
$curriculum->execute([$id]);
$curriculum = $curriculum->fetch();
if (!$curriculum) { header('Location: curricula.php'); exit; }

// Модули
$modulesRaw = $pdo->prepare("
    SELECT m.*
    FROM edu_curriculum_modules m
    WHERE m.curriculum_id = ?
    ORDER BY m.sort_order
");
$modulesRaw->execute([$id]);
$modulesRaw = $modulesRaw->fetchAll();

// Распределение по семестрам
$distributions = [];
if ($modulesRaw) {
    $moduleIds = array_column($modulesRaw, 'id');
    $in = implode(',', array_fill(0, count($moduleIds), '?'));
    $distRows = $pdo->prepare("SELECT * FROM edu_curriculum_distribution WHERE module_id IN ($in) ORDER BY semester_num");
    $distRows->execute($moduleIds);
    foreach ($distRows->fetchAll() as $d) {
        $distributions[$d['module_id']][$d['semester_num']] = $d['hours'];
    }
}

// Компетенции
$competencies = $pdo->prepare("SELECT * FROM edu_competencies WHERE curriculum_id = ? ORDER BY sort_order");
$competencies->execute([$id]);
$competencies = $competencies->fetchAll();

// Привязанные группы
$groups = $pdo->prepare("
    SELECT g.*, u.full_name AS curator_name
    FROM edu_groups g
    LEFT JOIN users u ON u.id = g.curator_id
    WHERE g.curriculum_id = ?
    ORDER BY g.name
");
$groups->execute([$id]);
$groups = $groups->fetchAll();

$passportRows = [];
$calendarRows = [];
$summaryRows = [];
$semesterMetaRows = [];
$calendarLegend = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM edu_curriculum_passport_fields WHERE curriculum_id = ? ORDER BY sort_order, id");
    $stmt->execute([$id]);
    $passportRows = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM edu_curriculum_process_schedule WHERE curriculum_id = ? ORDER BY FIELD(course_label,'I','II','III','IV','V','VI'), week_num");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $row) {
        $calendarRows[$row['course_label']][(int)$row['week_num']] = $row;
    }

    $stmt = $pdo->prepare("SELECT * FROM edu_curriculum_summary WHERE curriculum_id = ? ORDER BY sort_order, id");
    $stmt->execute([$id]);
    $summaryRows = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM edu_curriculum_semester_meta WHERE curriculum_id = ? ORDER BY semester_num");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $row) {
        $semesterMetaRows[(int)$row['semester_num']] = $row;
    }

    $stmt = $pdo->prepare("SELECT * FROM edu_curriculum_process_legend WHERE curriculum_id = ? ORDER BY sort_order, id");
    $stmt->execute([$id]);
    $calendarLegend = $stmt->fetchAll();
} catch (Throwable $e) {
    // Таблицы появятся после применения миграции 002.
}

$calendarMonths = [];
foreach ($calendarRows as $courseRows) {
    foreach ($courseRows as $weekNum => $row) {
        $monthValue = trim((string)($row['month_name'] ?? ''));
        if (!isset($calendarMonths[$weekNum]) && $monthValue !== '') {
            $calendarMonths[$weekNum] = $monthValue;
        }
    }
}

/**
 * В таблице графика учебного процесса месяцы должны идти по строке недель,
 * а не по ячейкам самих периодов обучения. В старом импорте month_name
 * сохранялся только в стартовой ячейке периода, поэтому в просмотре появлялись
 * разрывы: «сентябрь», потом «—», потом «январь» и т.п. Если карта месяцев
 * неполная, используем стандартную сетку РУПл на 52 недели.
 */
function edu_curriculum_graph_months(array $calendarMonths): array
{
    $nonEmpty = array_filter(array_map('trim', $calendarMonths), static fn($v) => $v !== '');
    $isSparse = count($nonEmpty) < 10 || !isset($calendarMonths[2], $calendarMonths[6], $calendarMonths[10]);

    if ($isSparse) {
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

    $map = [];
    $current = '';
    for ($w = 1; $w <= 52; $w++) {
        if (isset($calendarMonths[$w]) && trim((string)$calendarMonths[$w]) !== '') {
            $current = trim((string)$calendarMonths[$w]);
        }
        $map[$w] = $current;
    }
    return $map;
}

$calendarMonthMap = edu_curriculum_graph_months($calendarMonths);


function edu_graph_cell_span(?array $cell, int $week): int
{
    if (!$cell) return 1;
    $span = (int)($cell['span_weeks'] ?? 1);
    $value = trim((string)($cell['value_text'] ?? ''));

    // Резерв для уже импортированных РУПл: если span_weeks ещё старый (=1),
    // пытаемся восстановить объединение по числу в ячейке: "ТО 18", "ПП 6" и т.п.
    if ($span <= 1 && preg_match('/(?:^|\s)(\d{1,2})(?:\s*)$/u', $value, $m)) {
        $n = (int)$m[1];
        if ($n > 1 && $n <= 52) $span = $n;
    }

    return max(1, min(52 - $week + 1, $span));
}


function edu_curriculum_norm_token($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["Â ", ' '], '', $value);
    if (function_exists('mb_strtoupper')) return mb_strtoupper($value, 'UTF-8');
    return strtoupper($value);
}

function edu_curriculum_is_parent_section_code($value): bool
{
    $code = edu_curriculum_norm_token($value);
    return $code !== '' && (bool)preg_match('/^(ООМ|БМ|ПМ)\d+$/u', $code);
}

// Строим дерево: top-level и дочерние
$tree = [];
$byId = [];
foreach ($modulesRaw as $m) {
    $byId[$m['id']] = $m;
}
foreach ($modulesRaw as $m) {
    if ($m['parent_id'] === null) {
        $tree[] = $m['id'];
    }
}

// Считаем итоги по типам
$statsByType = [];
foreach ($modulesRaw as $m) {
    if ($m['is_summary']) continue;
    if (edu_curriculum_is_parent_section_code($m['index_code'] ?? '')) continue;
    $t = $m['module_type'];
    if (!isset($statsByType[$t])) $statsByType[$t] = ['count' => 0, 'hours' => 0, 'credits' => 0];
    $statsByType[$t]['count']++;
    $statsByType[$t]['hours']   += (int)$m['total_hours'];
    $statsByType[$t]['credits'] += (float)$m['credits'];
}

$pageTitle = htmlspecialchars($curriculum['name']);
$activeNav = 'edu';
$sidebarSubtitle = 'Учебный процесс';
$breadcrumbs = [
    ['label' => 'СВГТК', 'href' => '../'],
    ['label' => 'Учебный процесс', 'href' => 'index.php'],
    ['label' => 'Учебные планы (РУПл)', 'href' => 'curricula.php'],
    ['label' => (string)$curriculum['name']],
];
$activeTab = $_GET['tab'] ?? 'plan';
$allowedTabs = ['passport','plan','calendar','summary','competencies','groups'];
if (!in_array($activeTab, $allowedTabs, true)) $activeTab = 'plan';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — СВГТК</title>
  <?php require 'includes/head.php' ?>
  <style>
    .tab-bar{display:flex;gap:0;border-bottom:2px solid var(--color-border);margin-bottom:1.5rem;min-height:44px;align-items:flex-end}
    .tab{padding:.625rem 1.25rem;font-size:1rem;font-weight:500;color:var(--color-text-muted);border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;text-decoration:none;transition:color var(--transition);line-height:1.25;flex:0 0 auto}
    .tab:hover{color:var(--color-text)}
    .tab.active{color:var(--color-primary);border-bottom-color:var(--color-primary)}
    .plan-table{width:100%;font-size:.8125rem;border-collapse:collapse}
    .plan-table th{background:var(--color-surface-2);padding:.5rem .75rem;border:1px solid var(--color-border);white-space:nowrap;font-size:.75rem;text-align:center}
    .plan-table td{padding:.4rem .75rem;border:1px solid var(--color-divider);vertical-align:middle}
    .plan-table .col-name{text-align:left;max-width:280px}
    .plan-table .col-num{text-align:center}
    .row-module td{font-weight:700;background:var(--color-primary-highlight)}
    .row-summary td{font-weight:700;background:var(--color-surface-offset);font-style:italic}
    .row-subject td{background:#fff}
    .row-subject.even td{background:var(--color-surface-2)}
    .indent-1{padding-left:1.5rem!important}
    .indent-2{padding-left:2.5rem!important}
    .badge-type{display:inline-block;padding:1px 6px;border-radius:3px;font-size:.7rem;font-weight:600}
    .t-ООД{background:#dbeafe;color:#1d4ed8}
    .t-БМ{background:#ede9fe;color:#7c3aed}
    .t-ПМ{background:#dcfce7;color:#15803d}
    .t-ПА,.t-ИА,.t-ДП{background:#fef3c7;color:#b45309}
    .t-К,.t-Ф{background:#f3f4f6;color:#6b7280}
    .graph-table{width:max-content;min-width:100%;border-collapse:collapse;font-size:.75rem}
    .graph-table th,.graph-table td{border:1px solid var(--color-border);padding:.35rem .45rem;text-align:center;min-width:38px;vertical-align:middle}
    .graph-table th{background:var(--color-surface-2);font-weight:600}
    .graph-table .course{font-weight:700;background:var(--color-primary-highlight);position:sticky;left:0;z-index:1}
    .kv-grid{display:grid;grid-template-columns:260px 1fr;gap:0;border-top:1px solid var(--color-divider)}
    .kv-grid div{padding:.75rem 1rem;border-bottom:1px solid var(--color-divider)}
    .kv-grid .k{font-weight:600;background:var(--color-surface-2)}

    .module-actions{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center}
    .kpi-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.75rem;padding:1rem 1.25rem;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-xl);margin-bottom:1.5rem;box-shadow:var(--shadow-sm)}
    .kpi-item span{display:block;font-size:.75rem;color:var(--color-text-muted);line-height:1.3}.kpi-item strong{display:block;font-size:1.15rem;line-height:1.35}
    .graph-table{width:max-content;min-width:100%;border-collapse:collapse;font-size:.75rem;background:var(--color-surface)}
    .graph-table th,.graph-table td{border:1px solid var(--color-border);padding:.35rem .45rem;text-align:center;min-width:34px;height:30px;vertical-align:middle;white-space:nowrap}
    .graph-table th{background:var(--color-surface-2);font-weight:600;color:var(--color-text-muted)}
    .graph-table .month-head{background:var(--color-primary-highlight);color:var(--color-primary);font-weight:700}
    .graph-table .course{font-weight:700;background:var(--color-primary-highlight);position:sticky;left:0;z-index:2;min-width:70px;color:var(--color-primary)}
    .graph-table .graph-filled{font-weight:700;background:#eff6ff;color:#1d4ed8;border-left:2px solid var(--color-primary);border-right:2px solid var(--color-primary)}
    .graph-table .graph-empty{background:#fff;color:var(--color-text-faint)}
    .legend-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.5rem 1rem;padding:1rem 1.25rem;border-top:1px solid var(--color-divider);background:var(--color-surface-2)}
    .legend-item{display:flex;gap:.65rem;align-items:flex-start;font-size:.875rem}.legend-code{display:inline-flex;min-width:42px;justify-content:center;font-weight:700;color:var(--color-primary);background:var(--color-primary-highlight);border-radius:var(--radius-md);padding:2px 8px}
    .curriculum-view-tabs{height:44px;min-height:44px;overflow-x:auto;overflow-y:hidden;white-space:nowrap;scrollbar-width:none;-ms-overflow-style:none}
    .curriculum-view-tabs::-webkit-scrollbar{display:none;width:0;height:0}
    .curriculum-view-tabs .tab{height:44px;display:inline-flex;align-items:center}
  </style>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <!-- Заголовок -->
    <div class="page-header">
      <div>
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <p class="page-subtitle">
          <?= htmlspecialchars($curriculum['specialty_code']) ?> ·
          <?= htmlspecialchars($curriculum['specialty_db'] ?? $curriculum['specialty_name']) ?> ·
          <?= htmlspecialchars($curriculum['base_education']) ?> · <?= $curriculum['enrollment_year'] ?> г.п.
        </p>
      </div>
      <div class="page-actions module-actions">
        <a href="index.php" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Назад к студентам
        </a>
        <a href="curricula.php" class="btn btn-outline">Учебные планы</a>
        <?php if ($isAdmin): ?>
        <a href="curriculum_import.php" class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Загрузить РУПл
        </a>
        <?php endif ?>
      </div>
    </div>

    <!-- KPI-полоска -->
    <div class="kpi-strip">
      <div class="kpi-item"><span>Кредитов</span><strong><?= $curriculum['total_credits'] ?></strong></div>
      <div class="kpi-item"><span>Часов</span><strong><?= number_format($curriculum['total_hours']) ?></strong></div>
      <div class="kpi-item"><span>Лет обучения</span><strong><?= $curriculum['duration_years'] ?></strong></div>
      <div class="kpi-item"><span>Дисциплин</span><strong><?= array_sum(array_column($statsByType, 'count')) ?></strong></div>
      <div class="kpi-item"><span>Компетенций</span><strong><?= count($competencies) ?></strong></div>
      <div class="kpi-item"><span>Групп</span><strong><?= count($groups) ?></strong></div>
    </div>

    <!-- Вкладки -->
    <div class="tab-bar curriculum-view-tabs">
      <a href="?id=<?= $id ?>&tab=passport"      class="tab <?= $activeTab==='passport' ? 'active' : '' ?>">Паспорт</a>
      <a href="?id=<?= $id ?>&tab=plan"          class="tab <?= $activeTab==='plan' ? 'active' : '' ?>">Учебный план</a>
      <a href="?id=<?= $id ?>&tab=calendar"      class="tab <?= $activeTab==='calendar' ? 'active' : '' ?>">График учебного процесса</a>
      <a href="?id=<?= $id ?>&tab=summary"       class="tab <?= $activeTab==='summary' ? 'active' : '' ?>">Сводные данные</a>
      <a href="?id=<?= $id ?>&tab=competencies"  class="tab <?= $activeTab==='competencies' ? 'active' : '' ?>">Компетенции (<?= count($competencies) ?>)</a>
      <a href="?id=<?= $id ?>&tab=groups"        class="tab <?= $activeTab==='groups' ? 'active' : '' ?>">Группы (<?= count($groups) ?>)</a>
    </div>

    <?php if ($activeTab === 'passport'): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Паспорт образовательной программы</span></div>
      <div class="card-body" style="padding:0">
        <div class="kv-grid">
          <div class="k">Код специальности</div><div><?= htmlspecialchars($curriculum['specialty_code'] ?: '—') ?></div>
          <div class="k">Специальность</div><div><?= htmlspecialchars($curriculum['specialty_name'] ?: ($curriculum['specialty_db'] ?? '—')) ?></div>
          <div class="k">Квалификация</div><div><?php $q=json_decode($curriculum['qualification'] ?? '[]', true); echo htmlspecialchars(is_array($q) ? implode('; ', $q) : (string)($curriculum['qualification'] ?? '—')); ?></div>
          <?php foreach ($passportRows as $row): ?>
          <div class="k"><?= htmlspecialchars($row['label']) ?></div>
          <div style="white-space:pre-wrap"><?= htmlspecialchars($row['value'] ?? '') ?></div>
          <?php endforeach ?>
        </div>
        <?php if (empty($passportRows)): ?>
        <div class="empty-cell" style="padding:1rem">Детальные поля паспорта появятся после повторного импорта РУПл с миграцией 002.</div>
        <?php endif ?>
      </div>
    </div>

    <?php elseif ($activeTab === 'plan'): ?>
    <!-- ── ВКЛАДКА: УЧЕБНЫЙ ПЛАН ──────────────────────────────────────── -->
    <div class="card">
      <div style="overflow-x:auto">
        <table class="plan-table">
          <thead>
            <tr>
              <th rowspan="2" style="width:90px">Индекс</th>
              <th rowspan="2" style="min-width:160px">Модуль / дисциплина</th>
              <th rowspan="2" class="col-name">Наименование</th>
              <th rowspan="2">Тип</th>
              <th rowspan="2">Кред.</th>
              <th rowspan="2">Часов</th>
              <th rowspan="2">Теория</th>
              <th rowspan="2">Практика</th>
              <th rowspan="2">Курс.р.</th>
              <th rowspan="2">СРСП</th>
              <th rowspan="2">СРС</th>
              <th rowspan="2">Экз.</th>
              <th rowspan="2">Зач.</th>
              <th colspan="8" style="background:var(--color-primary-highlight);color:var(--color-primary)">Семестры (часов)</th>
            </tr>
            <tr>
              <?php for ($s = 1; $s <= 8; $s++): ?>
              <th style="background:var(--color-primary-highlight);color:var(--color-primary)"><?= $s ?></th>
              <?php endfor ?>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($semesterMetaRows)): ?>
          <tr class="row-summary">
            <td colspan="13" class="col-name">Количество учебных недель</td>
            <?php for ($s = 1; $s <= 8; $s++): ?>
            <td class="col-num"><?= isset($semesterMetaRows[$s]['study_weeks']) ? htmlspecialchars((string)$semesterMetaRows[$s]['study_weeks']) : '' ?></td>
            <?php endfor ?>
          </tr>
          <tr class="row-summary">
            <td colspan="13" class="col-name">Итого в неделю</td>
            <?php for ($s = 1; $s <= 8; $s++): ?>
            <td class="col-num"><?= isset($semesterMetaRows[$s]['weekly_hours']) ? htmlspecialchars((string)$semesterMetaRows[$s]['weekly_hours']) : '' ?></td>
            <?php endfor ?>
          </tr>
          <?php endif ?>
          <?php
          $subjCount = 0;
          foreach ($modulesRaw as $m):
            $isTopLevel = ($m['parent_id'] === null && !$m['is_summary']);
            $depth = 0;
            if ($m['parent_id'] !== null) {
                // Проверяем глубину
                $par = $byId[$m['parent_id']] ?? null;
                $depth = $par && $par['parent_id'] !== null ? 2 : 1;
            }
            $rowClass = $m['is_summary'] ? 'row-summary' : ($isTopLevel ? 'row-module' : ('row-subject ' . ($subjCount++ % 2 === 0 ? '' : 'even')));
            $dist = $distributions[$m['id']] ?? [];
          ?>
          <tr class="<?= $rowClass ?>">
            <td><?= htmlspecialchars($m['index_code']) ?></td>
            <td><?= htmlspecialchars(mb_strimwidth((string)($m['component_name'] ?? ''), 0, 70, '…')) ?></td>
            <td class="col-name <?= $depth > 0 ? 'indent-' . $depth : '' ?>">
              <?= htmlspecialchars(mb_strimwidth($m['name'], 0, 90, '…')) ?>
            </td>
            <td class="col-num">
              <?php if (!$m['is_summary']): ?>
              <span class="badge-type t-<?= htmlspecialchars($m['module_type']) ?>"><?= htmlspecialchars($m['module_type']) ?></span>
              <?php endif ?>
            </td>
            <td class="col-num"><?= $m['credits'] !== null ? $m['credits'] : '' ?></td>
            <td class="col-num"><?= $m['total_hours'] ?? '' ?></td>
            <td class="col-num"><?= $m['theory_hours'] ?? '' ?></td>
            <td class="col-num"><?= $m['practice_hours'] ?? '' ?></td>
            <td class="col-num"><?= $m['coursework_hours'] ?? '' ?></td>
            <td class="col-num"><?= $m['srsp_hours'] ?? '' ?></td>
            <td class="col-num"><?= $m['srs_hours'] ?? '' ?></td>
            <td class="col-num" style="color:var(--color-error)"><?= htmlspecialchars($m['exam_semester'] ?? '') ?></td>
            <td class="col-num" style="color:var(--color-success)"><?= htmlspecialchars($m['credit_semester'] ?? '') ?></td>
            <?php for ($s = 1; $s <= 8; $s++): ?>
            <td class="col-num" style="font-size:.75rem"><?= $dist[$s] ?? '' ?></td>
            <?php endfor ?>
          </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php elseif ($activeTab === 'calendar'): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">График учебного процесса</span></div>
      <div style="overflow-x:auto">
        <table class="graph-table">
          <thead>
            <tr>
              <th class="course">Курс</th>
              <?php
              $w = 1;
              while ($w <= 52):
                  $month = $calendarMonthMap[$w] ?? '';
                  $span = 1;
                  while (($w + $span) <= 52 && (($calendarMonthMap[$w + $span] ?? '') === $month)) $span++;
              ?>
              <th class="month-head" colspan="<?= $span ?>"><?= htmlspecialchars($month ?: '—') ?></th>
              <?php $w += $span; endwhile ?>
            </tr>
            <tr><th class="course">недели</th><?php for ($w=1;$w<=52;$w++): ?><th><?= $w ?></th><?php endfor ?></tr>
          </thead>
          <tbody>
          <?php foreach (['I','II','III','IV','V','VI'] as $course): if (empty($calendarRows[$course])) continue; ?>
            <tr>
              <td class="course"><?= $course ?></td>
              <?php
              $w = 1;
              while ($w <= 52):
                  $cell = $calendarRows[$course][$w] ?? null;
                  $span = edu_graph_cell_span($cell, $w);
                  $value = trim((string)($cell['value_text'] ?? ''));
                  $class = $value !== '' ? 'graph-filled' : 'graph-empty';
              ?>
              <td class="<?= $class ?>" colspan="<?= $span ?>" title="<?= htmlspecialchars($cell['month_name'] ?? '') ?>"><?= htmlspecialchars($value) ?></td>
              <?php $w += $span; endwhile ?>
            </tr>
          <?php endforeach ?>
          <?php if (empty($calendarRows)): ?><tr><td colspan="53" class="empty-cell">График не импортирован. Примените миграцию 002 и повторно загрузите РУПл.</td></tr><?php endif ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($calendarLegend)): ?>
      <div class="legend-grid">
        <?php foreach ($calendarLegend as $legend): ?>
        <div class="legend-item"><span class="legend-code"><?= htmlspecialchars($legend['code']) ?></span><span><?= htmlspecialchars($legend['description']) ?></span></div>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </div>

    <?php elseif ($activeTab === 'summary'): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Сводные данные по бюджету времени</span></div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Курс</th><th>Теор. недель</th><th>Теор. часов</th><th>Теор. кредитов</th><th>ПА</th><th>ПО/практика</th><th>ДП</th><th>ИА</th><th>Праздники</th><th>Каникулы</th><th>Всего недель</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($summaryRows as $r): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['course_label']) ?></strong></td>
              <td><?= htmlspecialchars((string)$r['theory_weeks']) ?></td>
              <td><?= htmlspecialchars((string)$r['theory_hours']) ?></td>
              <td><?= htmlspecialchars((string)$r['theory_credits']) ?></td>
              <td><?= htmlspecialchars((string)$r['interim_attestation_hours']) ?></td>
              <td><?= htmlspecialchars((string)$r['production_practice_hours']) ?></td>
              <td><?= htmlspecialchars((string)$r['diploma_design_hours']) ?></td>
              <td><?= htmlspecialchars((string)$r['final_attestation_hours']) ?></td>
              <td><?= htmlspecialchars((string)$r['holiday_hours']) ?></td>
              <td><?= htmlspecialchars((string)$r['vacation_weeks']) ?></td>
              <td><?= htmlspecialchars((string)$r['total_weeks']) ?></td>
            </tr>
          <?php endforeach ?>
          <?php if (empty($summaryRows)): ?><tr><td colspan="11" class="empty-cell">Сводные данные не импортированы. Примените миграцию 002 и повторно загрузите РУПл.</td></tr><?php endif ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php elseif ($activeTab === 'competencies'): ?>
    <!-- ── ВКЛАДКА: КОМПЕТЕНЦИИ ─────────────────────────────────────────── -->
    <div class="card">
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr><th style="width:70px">Код</th><th>Описание компетенции</th></tr>
          </thead>
          <tbody>
          <?php foreach ($competencies as $c): ?>
          <tr>
            <td><span class="badge badge-blue"><?= htmlspecialchars($c['code']) ?></span></td>
            <td style="font-size:.875rem;line-height:1.5"><?= htmlspecialchars($c['name']) ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($competencies)): ?>
          <tr><td colspan="2" class="empty-cell">Компетенции не найдены</td></tr>
          <?php endif ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php elseif ($activeTab === 'groups'): ?>
    <!-- ── ВКЛАДКА: ГРУППЫ ──────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:1rem">
      <div class="card-header">
        <span class="card-title">Группы, привязанные к этому плану</span>
        <?php if ($isAdmin): ?>
        <a href="groups.php" class="btn btn-outline btn-sm">Управление группами →</a>
        <?php endif ?>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr><th>Группа</th><th>Курс</th><th>Год поступления</th><th>Куратор</th></tr>
          </thead>
          <tbody>
          <?php foreach ($groups as $g): ?>
          <tr>
            <td><strong><?= htmlspecialchars($g['name']) ?></strong></td>
            <td><?= $g['course'] ?? '—' ?></td>
            <td><?= $g['year_started'] ?? '—' ?></td>
            <td style="font-size:.875rem;color:var(--color-text-muted)"><?= htmlspecialchars($g['curator_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($groups)): ?>
          <tr>
            <td colspan="4" class="empty-cell">
              Группы ещё не привязаны к этому плану.
              <?php if ($isAdmin): ?>
              Перейдите в <a href="groups.php">управление группами</a> и укажите план.
              <?php endif ?>
            </td>
          </tr>
          <?php endif ?>
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
