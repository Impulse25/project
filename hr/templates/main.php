<?php
// hr/templates/main.php — основное содержимое HR-страницы
$exportBaseParams = $_GET;
unset($exportBaseParams['format'], $exportBaseParams['page'], $exportBaseParams['chart_type'], $exportBaseParams['graph_type'], $exportBaseParams['chart_percent'], $exportBaseParams['chart_items'], $exportBaseParams['export_visuals'], $exportBaseParams['export_scope']);
$exportExcelUrl = 'export.php?' . http_build_query(array_merge($exportBaseParams, ['format' => 'excel', 'export_scope' => 'table']));
$exportCsvUrl   = 'export.php?' . http_build_query(array_merge($exportBaseParams, ['format' => 'csv', 'export_scope' => 'table']));
$exportWordUrl  = 'export.php?' . http_build_query(array_merge($exportBaseParams, ['format' => 'word', 'export_scope' => 'table']));
$visualExportExcelUrl = 'export.php?' . http_build_query(array_merge($exportBaseParams, ['format' => 'excel', 'export_scope' => 'visuals']));
$visualExportWordUrl  = 'export.php?' . http_build_query(array_merge($exportBaseParams, ['format' => 'word', 'export_scope' => 'visuals']));

$paginationBaseParams = $_GET;
unset($paginationBaseParams['page']);
$pageUrl = static function(int $page) use ($paginationBaseParams): string {
    $params = $paginationBaseParams;
    if ($page > 1) {
        $params['page'] = $page;
    } else {
        unset($params['page']);
    }
    $query = http_build_query($params);
    return $query ? '?' . $query : '?';
};

$clearDepartmentParams = $_GET;
unset($clearDepartmentParams['department_id'], $clearDepartmentParams['page']);
if (!isset($clearDepartmentParams['view'])) {
    $clearDepartmentParams['view'] = $hrView;
}
$clearDepartmentUrl = '?' . http_build_query($clearDepartmentParams);
?>
<!-- ═══════════════════ MAIN ══════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="mobile-menu-btn" id="mobileMenuBtn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <nav class="breadcrumb">
        <span class="breadcrumb-root"><a href="../" style="color:inherit">СВГТК</a></span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="breadcrumb-current">HR-Аналитика</span>
      </nav>
    </div>
    <div class="topbar-right">
      <?php if($isSystemAdmin): ?>
      <a href="../requests/admin_dashboard.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        В админку
      </a>
      <?php elseif($isDirector): ?>
      <a href="../requests/director_dashboard.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        К заявкам
      </a>
      <?php endif ?>
      <a href="../requests/logout.php" class="btn btn-outline btn-sm" title="Выйти из системы">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Выход
      </a>
      <div class="user-avatar" title="<?= htmlspecialchars($userName) ?>"><?= $initials ?: 'HR' ?></div>
      <button class="theme-toggle" id="themeToggle">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
    </div>
  </header>

  <!-- Page content -->
  <main class="page-content">

    <!-- Заголовок страницы -->
    <div class="page-header">
      <div>
        <h1 class="page-title">HR-Аналитика</h1>
        <p class="page-subtitle">Трудоустройство выпускников · <?= htmlspecialchars($pageContextTitle) ?> · <?= $total ?> студентов</p>
      </div>
      <div class="page-actions">
        <?php if($canManageHrRecords): ?>
        <button class="btn btn-primary" id="btnAddRecord">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          Добавить запись
        </button>
        <?php endif ?>
        <button class="btn btn-outline" id="btnHelp" type="button" title="Краткая справка по модулю">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 1 1 5.82 1c-.45.78-1.16 1.2-1.91 1.8-.7.56-1 1.1-1 2.2"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Справка
        </button>
        <a class="btn btn-outline" href="<?= htmlspecialchars($exportExcelUrl) ?>" title="Экспорт таблицы студентов в Excel">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Excel
        </a>
        <a class="btn btn-outline" href="<?= htmlspecialchars($exportCsvUrl) ?>" title="Экспорт текущей выборки в CSV">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
          CSV
        </a>
        <a class="btn btn-outline" href="<?= htmlspecialchars($exportWordUrl) ?>" title="Экспорт таблицы студентов в Word">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Word
        </a>
      </div>
    </div>

    <?php if(!empty($flash['message'])): ?>
    <div class="flash-message <?= ($flash['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif ?>

    <?php
      $viewUrl = static function(string $view) : string {
          $params = $_GET;
          $params['view'] = $view;
          unset($params['page']);
          $query = http_build_query($params);
          return $query ? '?' . $query : '?';
      };
    ?>
    <?php if(!empty($viewTabs)): ?>
    <div class="hr-view-tabs">
      <?php foreach($viewTabs as $tab): ?>
      <a class="hr-view-tab <?= $hrView === $tab['key'] ? 'active' : '' ?>" href="<?= htmlspecialchars($viewUrl($tab['key'])) ?>">
        <span><?= htmlspecialchars($tab['label']) ?></span>
        <small><?= htmlspecialchars($tab['hint']) ?></small>
      </a>
      <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if($isRegularTeacher): ?>
    <div class="scope-overview-card">
      <div class="scope-overview-head">
        <div>
          <h2>Группы преподавателя</h2>
        </div>
      </div>
      <?php
        if ($hrView === 'archive') {
            $teacherBlocks = [['title' => 'Архив выпускников', 'items' => $teacherArchiveGroupStats]];
        } elseif ($hrView === 'graduates') {
            $teacherBlocks = [['title' => 'Выпускники за последние 5 лет', 'items' => $teacherPreviousGroupStats]];
        } else {
            $teacherBlocks = [['title' => 'Группы', 'items' => $teacherCurrentGroupStats]];
        }
      ?>
      <?php foreach($teacherBlocks as $block): ?>
      <div class="scope-block">
        <h3><?= htmlspecialchars($block['title']) ?></h3>
        <?php if(empty($block['items'])): ?>
          <div class="scope-empty">Нет групп в этом разделе.</div>
        <?php else: ?>
          <div class="mini-stat-grid">
            <?php foreach($block['items'] as $gstat):
              $gTotal = (int)($gstat['total'] ?? 0);
              $gEmp = (int)($gstat['employed'] ?? 0);
              $gRate = percentOf($gEmp, $gTotal);
            ?>
            <a class="mini-stat-card" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['group_id' => (int)$gstat['group_id'], 'view' => $hrView, 'page' => null]))) ?>">
              <div class="mini-stat-top">
                <strong><?= htmlspecialchars($gstat['group_name']) ?></strong>
                <?= groupStateBadge($gstat['group_state'] ?? '') ?>
              </div>
              <div class="mini-stat-meta">Выпуск: <?= htmlspecialchars((string)($gstat['grad_year'] ?? '—')) ?> · <?= htmlspecialchars($gstat['department_name'] ?? 'Без отделения') ?></div>
              <div class="mini-stat-row"><span>Студентов</span><b><?= $gTotal ?></b></div>
              <div class="mini-stat-row"><span>Трудоустроены</span><b><?= $gEmp ?> / <?= $gRate ?>%</b></div>
              <div class="progress-bar-wrap"><div class="progress-bar success" style="width:<?= min(100, max(0, $gRate)) ?>%"></div></div>
            </a>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>
      <?php endforeach ?>
    </div>
    <?php elseif($isDirector || $isHrDepartmentHead || $isHrPracticeHead || $hrView === 'departments'): ?>
    <div class="scope-overview-card">
      <div class="scope-overview-head">
        <div>
          <h2>Статистика по отделениям</h2>
          <p>
            <?php if($isHrDepartmentHead): ?>
              Доступ ограничен выбранным отделением и его показателями трудоустройства.
            <?php elseif($isHrPracticeHead): ?>
              Доступна сводная статистика по всем отделениям колледжа.
            <?php else: ?>
              Для директора доступен только уровень отделений и сводные показатели трудоустройства.
            <?php endif ?>
          </p>
        </div>
      </div>
      <?php if($fDepartment !== null): ?>
      <div class="scope-selected-filter">
        <span>Выбрано отделение: <b><?= htmlspecialchars($selectedDepartmentName ?? 'Без отделения') ?></b></span>
        <?php if(!$isHrDepartmentHead): ?>
        <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars($clearDepartmentUrl) ?>">Показать все отделения</a>
        <?php endif ?>
      </div>
      <?php endif ?>
      <?php if(empty($departmentStats)): ?>
        <div class="scope-empty">Нет данных по отделениям.</div>
      <?php else: ?>
      <div class="department-stat-list">
        <?php foreach($departmentStats as $dept):
          $dTotal = (int)($dept['total'] ?? 0);
          $dEmp = (int)($dept['employed'] ?? 0);
          $dRate = percentOf($dEmp, $dTotal);
          $deptId = (int)$dept['department_id'];
          $isSelectedDepartment = $fDepartment !== null && $fDepartment === $deptId;
          $deptUrlParams = $_GET;
          $deptUrlParams['department_id'] = $deptId;
          $deptUrlParams['view'] = $hrView;
          unset($deptUrlParams['page']);
        ?>
        <a class="department-stat-row <?= $isSelectedDepartment ? 'active' : '' ?>" href="?<?= htmlspecialchars(http_build_query($deptUrlParams)) ?>">
          <div>
            <strong><?= htmlspecialchars($dept['department_name']) ?></strong>
            <span><?= (int)$dept['groups_count'] ?> групп · <?= $dTotal ?> студентов</span>
          </div>
          <div class="department-stat-progress">
            <b><?= $dEmp ?> трудоустроены · <?= $dRate ?>%</b>
            <div class="progress-bar-wrap"><div class="progress-bar success" style="width:<?= min(100, max(0, $dRate)) ?>%"></div></div>
          </div>
        </a>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </div>
    <?php elseif($isSystemAdmin): ?>
    <div class="scope-overview-card">
      <div class="scope-overview-head">
        <div>
          <h2>Административный обзор</h2>
          <p>Администратор видит все группы, отделения, текущие и архивные данные.</p>
        </div>
      </div>
      <div class="scope-admin-grid">
        <div class="scope-counter"><span>Группы</span><b><?= (int)$scopeCounts['current_groups'] ?></b></div>
        <div class="scope-counter"><span>Выпускники</span><b><?= (int)$scopeCounts['previous_groups'] ?></b></div>
        <div class="scope-counter"><span>Архив выпускников</span><b><?= (int)$scopeCounts['archive_groups'] ?></b></div>
        <div class="scope-counter"><span>Отделения</span><b><?= (int)$scopeCounts['departments'] ?></b></div>
      </div>
    </div>
    <?php endif ?>

    <!-- KPI карточки -->
    <div class="kpi-grid kpi-grid-statuses">
      <div class="kpi-card">
        <div class="kpi-card-header">
          <span class="kpi-label">Всего студентов</span>
          <span class="kpi-icon blue">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </span>
        </div>
        <div class="kpi-value"><?= $total ?></div>
        <div class="kpi-change">в выборке</div>
      </div>

      <?php foreach($statusSummaryCards as $card): ?>
      <div class="kpi-card">
        <div class="kpi-card-header">
          <span class="kpi-label"><?= htmlspecialchars($card['label']) ?></span>
          <span class="kpi-icon <?= htmlspecialchars($card['icon']) ?>">
            <?php if($card['icon'] === 'success'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <?php elseif($card['icon'] === 'gold'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
            <?php elseif($card['icon'] === 'error'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?php elseif($card['icon'] === 'warning'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <?php elseif($card['icon'] === 'primary'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            <?php else: ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php endif ?>
          </span>
        </div>
        <div class="kpi-value"><?= (int)$card['value'] ?></div>
        <div class="kpi-change"><?= htmlspecialchars((string)$card['rate']) ?>% <?= htmlspecialchars($card['hint']) ?></div>
        <div class="progress-bar-wrap" style="margin-top:var(--space-3)">
          <div class="progress-bar <?= htmlspecialchars($card['icon']) ?>" style="width:<?= min(100, max(0, (float)$card['rate'])) ?>%"></div>
        </div>
      </div>
      <?php endforeach ?>
    </div>

    <?php if($canShowHrCharts): ?>
    <div class="card hr-chart-card">
      <div class="card-header">
        <span class="card-title">Диаграммы и графики статистики</span>
        <div class="hr-chart-controls">
          <label>
            <span>Вид диаграммы</span>
            <select class="form-control" id="hrChartType">
              <option value="doughnut">Кольцевая</option>
              <option value="pie">Круговая</option>
              <option value="bar">Столбчатая</option>
            </select>
          </label>
          <label class="hr-graph-type-control" id="hrGraphTypeControl">
            <span>Вид графика</span>
            <select class="form-control" id="hrGraphType">
              <option value="line">Линейный</option>
              <option value="smooth">Сглаженный</option>
              <option value="area">С областями</option>
              <option value="step">Ступенчатый</option>
            </select>
          </label>
          <label class="hr-chart-percent-toggle">
            <input type="checkbox" id="hrChartPercent">
            <span>Показывать проценты</span>
          </label>
          <div class="hr-export-visuals" aria-label="Что добавить в экспорт">
            <span>Показать и экспортировать</span>
            <label>
              <input type="checkbox" id="hrExportChart" checked>
              <span>Диаграмма</span>
            </label>
            <label>
              <input type="checkbox" id="hrExportGraph">
              <span>График</span>
            </label>
          </div>
          <div class="hr-visual-export-actions">
            <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars($visualExportExcelUrl) ?>" data-hr-visual-export-link="1" title="Экспорт диаграмм и графиков в Excel">Excel</a>
            <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars($visualExportWordUrl) ?>" data-hr-visual-export-link="1" title="Экспорт диаграмм и графиков в Word">Word</a>
            <button class="btn btn-outline btn-sm" type="button" id="hrExportPng" title="Экспорт диаграмм и графиков в PNG">PNG</button>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="hr-chart-checks" id="hrChartChecks"></div>
        <div class="hr-chart-layout" id="hrChartPreview">
          <canvas id="hrStatsChart" width="720" height="320" aria-label="Диаграмма HR-статистики"></canvas>
          <div class="hr-chart-legend" id="hrChartLegend"></div>
        </div>
        <div class="hr-chart-layout hr-graph-preview is-hidden" id="hrGraphPreview">
          <canvas id="hrStatsGraph" width="720" height="320" aria-label="График HR-статистики"></canvas>
          <div class="hr-chart-legend" id="hrGraphLegend"></div>
        </div>
      </div>
    </div>
    <?php endif ?>

    <!-- Карточка с фильтрами и таблицей -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Реестр трудоустройства</span>
      </div>
      <div class="card-body">

        <!-- Фильтры -->
        <form method="GET" action="" id="filterForm">
          <input type="hidden" name="view" value="<?= htmlspecialchars($hrView) ?>">
          <div class="filters-bar">
            <div class="form-group">
              <label class="form-label">Поиск по ФИО</label>
              <input type="text" name="search" class="form-control search" placeholder="Фамилия, имя, группа..."
                     value="<?= htmlspecialchars($fSearch) ?>">
            </div>
            <?php if($canShowDepartmentFilter): ?>
            <div class="form-group">
              <label class="form-label">Отделение</label>
              <select name="department_id" class="form-control">
                <?php if(!$isHrDepartmentHead): ?>
                <option value="">Все отделения</option>
                <?php endif ?>
                <?php foreach($departments as $dept): ?>
                <option value="<?= $dept['id'] ?>" <?= $fDepartment === (int)$dept['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($dept['department_name']) ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
            <?php endif ?>
            <?php if($canShowGroupFilter): ?>
            <div class="form-group">
              <label class="form-label">Группа</label>
              <select name="group_id" class="form-control">
                <option value="">Все группы</option>
                <?php foreach($groups as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $fGroup === (int)$g['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($g['name']) ?><?= ($g['group_state'] ?? '') === 'archive' ? ' · архив' : '' ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
            <?php endif ?>
            <div class="form-group">
              <label class="form-label">Специальность</label>
              <select name="specialty_id" class="form-control">
                <option value="">Все специальности</option>
                <?php foreach($specialties as $sp): ?>
                <option value="<?= $sp['id'] ?>" <?= $fSpec === (int)$sp['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars(mb_strimwidth($sp['name_ru'], 0, 40, '…')) ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Год выпуска</label>
              <select name="grad_year" class="form-control">
                <option value="">Все года</option>
                <?php foreach($years as $y): if(!$y) continue; ?>
                <option value="<?= $y ?>" <?= $fYear === (int)$y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Статус</label>
              <select name="status" class="form-control">
                <option value="">Все статусы</option>
                <option value="employed"   <?= $fStatus==='employed'   ?'selected':'' ?>>Трудоустроен</option>
                <option value="unemployed" <?= $fStatus==='unemployed' ?'selected':'' ?>>Не трудоустроен</option>
                <option value="studying"   <?= $fStatus==='studying'   ?'selected':'' ?>>Продолжает учёбу</option>
                <option value="decree"     <?= $fStatus==='decree'     ?'selected':'' ?>>В декрете</option>
                <option value="military"   <?= $fStatus==='military'   ?'selected':'' ?>>Военная служба</option>
                <option value="relocation" <?= $fStatus==='relocation' ?'selected':'' ?>>Выезд на ПМЖ</option>
                <option value="other"      <?= $fStatus==='other'      ?'selected':'' ?>>Прочее</option>
                <option value="unknown"    <?= $fStatus==='unknown'    ?'selected':'' ?>>Неизвестно</option>
              </select>
            </div>
            <div class="form-group" style="flex-direction:row;gap:var(--space-2);align-items:flex-end">
              <button type="submit" class="btn btn-primary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Найти
              </button>
              <a href="?" class="btn btn-outline btn-sm">Сброс</a>
            </div>
          </div>
        </form>

        <!-- Таблица -->
        <?php if(empty($students)): ?>
        <div class="empty-state">
          <div class="empty-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </div>
          <p style="font-weight:600;margin-bottom:var(--space-2)">Ничего не найдено</p>
          <p style="font-size:var(--text-xs)">Попробуйте изменить параметры фильтрации</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>ФИО студента</th>
                <th>Отделение</th>
                <th>Группа</th>
                <th>Статус</th>
                <th>Организация / Должность</th>
                <th>Дата</th>
                <th class="table-center">По спец.</th>
                <th class="table-center">Справки</th>
                <th class="table-center">Действия</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($students as $row): ?>
              <tr>
                <td>
                  <span style="font-weight:500"><?= htmlspecialchars($row['surname'] . ' ' . $row['name']) ?></span>
                  <?php if($row['patronymic']): ?>
                  <br><span style="font-size:var(--text-xs);color:var(--color-text-muted)"><?= htmlspecialchars($row['patronymic']) ?></span>
                  <?php endif ?>
                </td>
                <td>
                  <?php if($row['department_name']): ?>
                  <span class="badge badge-gray"><?= htmlspecialchars($row['department_name']) ?></span>
                  <?php else: ?>
                  <span class="badge badge-gray">—</span>
                  <?php endif ?>
                </td>
                <td>
                  <?php if($row['group_name']): ?>
                  <span class="badge badge-blue"><?= htmlspecialchars($row['group_name']) ?></span>
                  <?php else: ?>
                  <span class="badge badge-gray">—</span>
                  <?php endif ?>
                </td>
                <td><?= statusBadge($row['status'] ?? null) ?></td>
                <td>
                  <?php if($row['employer_name']): ?>
                  <span style="font-weight:500"><?= htmlspecialchars($row['employer_name']) ?></span>
                  <?php if($row['position']): ?>
                  <br><span style="font-size:var(--text-xs);color:var(--color-text-muted)"><?= htmlspecialchars($row['position']) ?></span>
                  <?php endif ?>
                  <?php else: ?>
                  <span style="color:var(--color-text-faint)">—</span>
                  <?php endif ?>
                </td>
                <td style="white-space:nowrap"><?= fmtDate($row['employment_date']) ?></td>
                <td class="table-center">
                  <?php if($row['status'] === 'employed'): ?>
                    <span class="spec-mark <?= $row['is_by_specialty'] ? 'spec-mark-yes' : 'spec-mark-no' ?>" title="<?= $row['is_by_specialty'] ? 'По специальности' : 'Не по специальности' ?>">
                      <?= $row['is_by_specialty'] ? '✓' : '×' ?>
                    </span>
                  <?php else: ?>
                    <span class="spec-mark spec-mark-muted">—</span>
                  <?php endif ?>
                </td>
                <td style="text-align:center">
                  <?php if($row['employment_id'] && $row['doc_count'] > 0): ?>
                  <button class="btn btn-ghost btn-sm" onclick="openDocs(<?= $row['employment_id'] ?>, '<?= htmlspecialchars($row['status'] ?? 'unknown', ENT_QUOTES) ?>')"
                          title="Просмотр справок">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span class="count-chip"><?= $row['doc_count'] ?></span>
                  </button>
                  <?php else: ?>
                  <span style="color:var(--color-text-faint);font-size:var(--text-xs)">—</span>
                  <?php endif ?>
                </td>
                <td style="text-align:center">
                  <?php if($canShowRecordActions && ($isSystemAdmin || (($row['group_state'] ?? '') !== 'archive'))): ?>
                  <div style="display:flex;gap:4px;justify-content:center">
                    <button class="btn btn-ghost btn-sm" title="Редактировать"
                      onclick="openEdit(<?= htmlspecialchars(json_encode([
                        'studentId'      => (int)$row['student_id'],
                        'studentName'    => $row['surname'].' '.$row['name'].' '.($row['patronymic']??''),
                        'recordId'       => $row['employment_id'] ? (int)$row['employment_id'] : null,
                        'status'         => $row['status'] ?? '',
                        'employerName'   => $row['employer_name'] ?? '',
                        'position'       => $row['position'] ?? '',
                        'employmentDate' => $row['employment_date'] ?? '',
                        'employmentType' => $row['employment_type'] ?? 'full_time',
                        'isBySpec'       => (bool)$row['is_by_specialty'],
                        'notes'          => $row['notes'] ?? '',
                      ]), ENT_QUOTES) ?>)">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <?php if($row['employment_id']): ?>
                    <form method="POST" action="actions.php" style="display:inline"
                          onsubmit="return confirm('Удалить запись о трудоустройстве для &quot;<?= htmlspecialchars(addslashes($row['surname'].' '.$row['name'])) ?>&quot;?\n\nВсе прикреплённые документы тоже будут удалены.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="record_id" value="<?= (int)$row['employment_id'] ?>">
                      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
                      <button type="submit" class="btn btn-ghost btn-sm" title="Удалить запись" style="color:var(--color-error)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                      </button>
                    </form>
                    <?php endif ?>
                  </div>
                  <?php else: ?>
                  <span style="color:var(--color-text-faint);font-size:var(--text-xs)">только просмотр</span>
                  <?php endif ?>
                </td>
              </tr>
            <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <div class="pagination-summary">
          Показано: <?= $pageStart ?>–<?= $pageEnd ?> из <?= $total ?> записей
        </div>

        <?php if($totalPages > 1): ?>
        <nav class="pagination" aria-label="Страницы реестра">
          <div class="pagination-info">Страница <?= $currentPage ?> из <?= $totalPages ?></div>
          <div class="pagination-controls">
            <?php if($currentPage > 1): ?>
            <a class="pagination-link" href="<?= htmlspecialchars($pageUrl($currentPage - 1)) ?>">‹</a>
            <?php else: ?>
            <span class="pagination-link is-disabled">‹</span>
            <?php endif ?>

            <?php
              $lastShownPage = 0;
              for ($p = 1; $p <= $totalPages; $p++):
                $shouldShow = $p === 1 || $p === $totalPages || abs($p - $currentPage) <= 2;
                if (!$shouldShow) {
                    continue;
                }
                if ($lastShownPage && $p - $lastShownPage > 1):
            ?>
            <span class="pagination-ellipsis">…</span>
            <?php
                endif;
                $lastShownPage = $p;
                if ($p === $currentPage):
            ?>
            <span class="pagination-link is-active"><?= $p ?></span>
            <?php else: ?>
            <a class="pagination-link" href="<?= htmlspecialchars($pageUrl($p)) ?>"><?= $p ?></a>
            <?php endif; endfor; ?>

            <?php if($currentPage < $totalPages): ?>
            <a class="pagination-link" href="<?= htmlspecialchars($pageUrl($currentPage + 1)) ?>">›</a>
            <?php else: ?>
            <span class="pagination-link is-disabled">›</span>
            <?php endif ?>
          </div>
        </nav>
        <?php endif ?>
        <?php endif ?>

      </div><!-- /card-body -->
    </div><!-- /card -->

  </main>
</div>
