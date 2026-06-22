<?php
// views/teacher_assignments/index.php

define('BASE_PATH', dirname(__DIR__, 2));

// Авторизация
require_once BASE_PATH . '/partials/init.php';

// Проверка прав
if (!$canTeacherAssignments && !$isPccHead) {
  http_response_code(403);
  echo "Доступ запрещён. У вас нет прав для просмотра этого раздела.";
  exit;
}

// Подключение файлов
require_once BASE_PATH . '/models/baseModel.php';

require_once BASE_PATH . '/models/edu_groups.php';
require_once BASE_PATH . '/models/edu_semesters.php';
require_once BASE_PATH . '/models/users.php';

require_once BASE_PATH . '/models/umr_teacher_assignments.php';

// Создание классов моделей
$moduleSem = new edu_semesters($pdo);
$moduleUsers  = new users($pdo);
$moduleGroups = new edu_groups($pdo);
$moduleTeacherAssignments = new umr_teacher_assignments($pdo);

$today = date('Y-m-d');

// Активный семестр
$activeSem = $moduleSem->getActive($today);

// Все учебные годы
$allAcademicYears = $moduleGroups->getAcademicYears();

// Преподаватели
$teachers = $moduleUsers->getTeachers();

// Председатели ПЦК (только для admin)
$pccHeads = [];
if ($isAdmin || $isPccHead) {
    $pccHeads = $moduleUsers->getPccHeads();
}

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

$sortDir = $_GET['sort'] ?? ($_SESSION['ta_sort'] ?? 'asc');
$sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'asc';
$_SESSION['ta_sort'] = $sortDir;

if (isset($_GET['fs'])) {
    $onlyMine = isset($_GET['only_mine']);
} else {
    $onlyMine = (bool)($_SESSION['ta_only_mine'] ?? false);
}
$_SESSION['ta_only_mine'] = $onlyMine;

$academicYearLabel = $filterYear . '/' . ($filterYear + 1);
$semLabel          = $filterSem === 0 ? 'Все семестры' : ($filterSem . ' семестр');
$semestersToShow   = $filterSem === 0 ? range(1, 8) : [$filterSem];

// Запрос групп и дисциплин
$groupData      = [];
$allModuleIds   = [];
$dropdownGroups = [];

foreach ($semestersToShow as $sem) {
    $neededCourse    = (int)ceil($sem / 2);
    $neededYearStart = $filterYear - $neededCourse + 1;

    $semGroups = $moduleTeacherAssignments->getGroupsBySemester($neededCourse, $neededYearStart, $neededYearStart);
  
    foreach ($semGroups as $g) {
        if (!isset($dropdownGroups[$g['id']])) {
            $dropdownGroups[$g['id']] = $g;
        }
    }

    $semGroupsFiltered = $filterGroupId
        ? array_values(array_filter($semGroups, fn($g) => (int)$g['id'] === $filterGroupId))
        : $semGroups;

    foreach ($semGroupsFiltered as $g) {
        $gId   = (int)$g['id'];
        $curId = (int)$g['curriculum_id'];

        //Модули
        $modules = $moduleTeacherAssignments->getModulesByCurriculumAndSemester($curId, $sem);

        $assigned = [];
        if ($modules) {
            $mids   = array_column($modules, 'id');
            $assigned = $moduleTeacherAssignments->getAssignmentsByModules($mids, $gId, $sem);
        }

        if ($onlyMine) {
            $modules = array_values(array_filter($modules, function ($m) use ($assigned, $userId) {
                $mId = (int)$m['id'];
                if (empty($assigned[$mId])) return false;
                foreach ($assigned[$mId] as $row) {
                    if ((int)$row['pcc_head_id'] === (int)$userId) return true;
                }
                return false;
            }));
        }

        if (empty($modules)) continue;

        foreach ($modules as $m) {
            $key = $m['index_code'] . '||' . $m['name'];
            if (!isset($allModuleIds[$key])) {
                $allModuleIds[$key] = [
                    'index_code'  => $m['index_code'],
                    'name'        => $m['name'],
                    'module_type' => $m['module_type'],
                ];
            }
        }

        $groupData[] = [
            'group'    => $g,
            'semester' => $sem,
            'modules'  => $modules,
            'assigned' => $assigned,
        ];
    }
}

usort($allModuleIds, fn($a, $b) => strcmp($a['index_code'] . $a['name'], $b['index_code'] . $b['name']));
usort($dropdownGroups, fn($a, $b) => strcmp($a['name'], $b['name']));
usort($groupData, function($a, $b) use ($sortDir) {
    $cmp = $a['semester'] - $b['semester'];
    return $sortDir === 'desc' ? -$cmp : $cmp;
});

function sortLink(int $year, int $sem, int $groupId, string $dir, bool $onlyMine): string {
    return '?academic_year=' . $year . '&semester=' . $sem . '&group_id=' . $groupId
        . '&sort=' . $dir . '&fs=1' . ($onlyMine ? '&only_mine=1' : '');
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Тарификация — УМР — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../css/style.css">
</head>
<body>

<!-- Sidebar-->
<?php $_nav_active_key = 'teacher_assignments';
      require __DIR__ . '/../../partials/sidebar.php'; ?>

<div class="main-wrapper" id="mainWrapper">

  <!-- Topbar-->
  <?php
    $_breadcrumbs = [
      'УМР' => null, 
      'Тарификация' => null];
    require_once BASE_PATH . '/partials/topbar.php';
    require_once BASE_PATH . '/partials/umr_subnav.php';
  ?>

  <main class="page-content">

    <div class="page-header">
      
      <div>
        <h1 class="page-title">Тарификация</h1>
        <p class="page-subtitle">
          Распределение дисциплин РУПл —
          <strong><?= e($academicYearLabel) ?></strong>,
          <strong><?= e($semLabel) ?></strong>
        </p>
      </div>

      <!--Кнопка экспорта тарификации-->
      <div style="margin-left:auto">

        <?php if ($isAdmin || $isPccHead): ?>
          <button type="button" class="btn-export" onclick="openExportModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
              <polyline points="10 9 9 9 8 9"/>
            </svg>
            Экспорт тарификации <?= e($academicYearLabel) ?>
          </button>

          <button type="button" class="btn-export" style="background:#8b5cf6;" onclick="submitExportTestsModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 17v-2m3 2v-4m3 4v-6m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2z"/>
            </svg>
            Экспорт контрольных работ
        </button>
        <?php endif ?>
      </div>

    </div>

    <?php if ($flash): ?>
      <div class="card" style="margin-bottom:1rem;background:var(--color-success-highlight);border-color:#a7f3d0">
        <div class="card-body" style="padding:.75rem 1.5rem;color:var(--color-success)"><?= e($flash) ?></div>
      </div>
    <?php endif ?>

    <!-- Фильтры -->
    <form method="GET" class="ta-filters" id="filterForm">
      <input type="hidden" name="fs" value="1">

      <!-- Учебный год -->
      <?php require __DIR__ . '/../../partials/filter_academicYear.php'; ?>

      <!-- Семестр -->
      <?php require __DIR__ . '/../../partials/filter_semester.php'; ?>

      <!-- Группа -->
      <?php require __DIR__ . '/../../partials/filter_groups.php'; ?>
      
      <!-- Сортировка -->
      <?php require __DIR__ . '/../../partials/filter_sorting.php'; ?>
      
      <!-- Чекбокс: назначенные мной -->
      <?php if (!$isAdmin): ?>
        <div class="form-group">
          <label class="form-label">&nbsp;</label>
          <label class="checkbox-filter <?= $onlyMine ? 'active' : '' ?>">
            <input type="checkbox" name="only_mine" value="1"
                  <?= $onlyMine ? 'checked' : '' ?>
                  onchange="this.form.submit()">
            Назначенные мной
          </label>
        </div>
      <?php endif ?>
      </form>
    
    <!--Если пустая таблица -->
    <?php if (empty($groupData)): ?>
    <div class="card">
        <div class="ta-empty">
          <p>Нет данных для отображения</p>
          <p>
            В <?= e($academicYearLabel) ?> учебном году
            <?= $filterSem === 0 ? 'для выбранных семестров' : 'на ' . e($semLabel) ?>
            не найдено групп с привязанным учебным планом
            <?= $onlyMine ? 'и назначениями, сделанными вами' : '' ?>
          </p>
        </div>
    </div>
    <?php else: ?>

    <!--  Мультиселект предметов  -->
    <?php require __DIR__ . '/../../partials/multiselect.php'; ?>

    <!-- Сводная таблица -->
    <div class="card">
      <div class="assign-wrap">
        <table class="assign-table" id="mainTable">
          
          <thead>
            <tr>
              <th style="width:70px">Индекс</th>
              <th style="min-width:200px;text-align:left">Дисциплина</th>
              <th>Тип</th>
              <th>Кред.</th>
              <th>Всего<br>часов</th>
              <th>Теория</th>
              <th>Практика</th>
              <th>Курс.р.</th>
              <th>СРСП</th>
              <th>СРС</th>
              <th>Экз.</th>
              <th>Зач.</th>
              <th>Контр. раб.</th>
              <th>Часов<br>в сем.</th>
              <th style="min-width:140px;text-align:left">Назначил (ПЦК)</th>
              <th style="min-width:220px;text-align:left">Преподаватель(и)</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($groupData as $idx => $data):
              $g        = $data['group'];
              $sem      = $data['semester'];
              $modules  = $data['modules'];
              $assigned = $data['assigned'];
              $course   = (int)$g['current_course'];
              $gId      = (int)$g['id'];
              if (empty($modules)) continue;
            ?>

            <tr class="row-group-header">
              <td colspan="15">
                <?= e($g['name']) ?>
                <span class="gh-sem"><?= $sem ?> семестр</span>
                <span style="font-weight:400;font-size:.75rem;margin-left:.5rem;opacity:.8">
                  <?= $course ?> курс · <?= e($g['specialty_name'] ?: $g['curriculum_name']) ?>
                </span>
              </td>
            </tr>

            <?php foreach ($modules as $m):
              $mId    = (int)$m['id'];
              $mKey   = e($m['index_code'] . '||' . $m['name']);
              $myRows = $assigned[$mId] ?? [];
              $cellKey = $gId . '-' . $sem . '-' . $mId;

              // Экз / Зач в этом семестре
              $isExam   = false;
              $isCredit = false;
              if (!empty($m['exam_semester'])) {
                  $esems = array_map('trim', explode(',', (string)$m['exam_semester']));
                  $isExam = in_array((string)$sem, $esems);
              }
              if (!empty($m['credit_semester'])) {
                  $csems = array_map('trim', explode(',', (string)$m['credit_semester']));
                  $isCredit = in_array((string)$sem, $csems);
              }
            ?>

            <tr class="subject-row" data-sf-key="<?= $mKey ?>">
              <td class="col-num">
                <span style="font-size:.77rem;font-weight:600;color:var(--color-text-muted)"><?= e($m['index_code']) ?></span>
              </td>
              <td><?= e($m['name']) ?></td>
              <td class="col-num">
                <span class="badge-type t-<?= e($m['module_type']) ?>"><?= e($m['module_type']) ?></span>
              </td>
              <td class="col-num"><?= $m['credits'] !== null ? number_format((float)$m['credits'], 2) : '—' ?></td>
              <td class="col-num"><?= $m['total_hours'] ?? '—' ?></td>
              <td class="col-num"><?= $m['theory_hours'] ?? '—' ?></td>
              <td class="col-num"><?= $m['practice_hours'] ?? '—' ?></td>
              <td class="col-num"><?= $m['coursework_hours'] ? $m['coursework_hours'] : '—' ?></td>
              <td class="col-num"><?= $m['srsp_hours'] ?? '—' ?></td>
              <td class="col-num"><?= $m['srs_hours'] ?? '—' ?></td>
              
              <td class="col-num">
                <?php if ($isExam): ?>
                <span class="ctrl-badge ctrl-exam">Экз</span>
                <?php else: ?>—<?php endif ?>
              </td>

              <td class="col-num">
                <?php if ($isCredit): ?>
                <span class="ctrl-badge ctrl-credit">Зач</span>
                <?php else: ?>—<?php endif ?>
              </td>

              <td class="col-num"><?= $m['control_work'] ?? '—' ?></td>   

              <td class="col-num" style="font-weight:700;color:var(--color-primary)"><?= $m['semester_hours'] ?></td>
              
              <td class="pcc-cell">
                <?php
                  $pccNames = array_unique(array_filter(array_column($myRows, 'pcc_name')));
                  foreach ($pccNames as $pccName):
                ?>
                <div class="pcc-label">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                  </svg>
                  <?= e($pccName) ?>
                </div>
                <?php endforeach ?>
              </td>

              <td>
                <div class="teacher-list" id="tlist-<?= $cellKey ?>">
                  
                  <?php foreach ($myRows as $row):
                    //Снять преподавателя может только админ или кто назначил == pcc_head_id
                    $canRemove = $isAdmin || ((int)$row['pcc_head_id'] === $userId);
                  ?>

                    <span class="teacher-chip">
                      <?= e($row['teacher_name']) ?>
                      <?php if ($canRemove): ?>
                      <button class="chip-remove" title="Снять"
                        onclick="removeAssignment(<?= (int)$row['assignment_id'] ?>,'<?= $cellKey ?>')">×</button>
                      <?php endif ?>
                    </span>

                  <?php endforeach ?>

                  <button class="btn-add-teacher"
                    onclick="openAssignModal('<?= $cellKey ?>',<?= $mId ?>,<?= $gId ?>,<?= $sem ?>)">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Добавить
                  </button>

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

  //Загрузка переменных для AJAX в модаль
  const isPccHead    = <?= $isPccHead ? 'true' : 'false' ?>;
  const isAdmin  = <?= $isAdmin ? 'true' : 'false' ?>;
  const pccHeads   = <?= json_encode(
      array_values(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['full_name']], $pccHeads ?? [])),
      JSON_UNESCAPED_UNICODE
  ) ?>;
  const teachers   = <?= json_encode(
      array_values(array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['full_name']], $teachers)),
      JSON_UNESCAPED_UNICODE
  ) ?>;
  const filterYear = <?= (int)$filterYear ?>;

</script>

<!-- Модальное окно: Назначение преподавателя-->
<div class="modal-overlay" id="modalAssign" onclick="if(event.target===this)closeAssignModal()">
  <div class="modal-box">
    <div class="modal-header">
      
      <span class="modal-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
        </svg>
        Назначить преподавателя
      </span>

      <button class="modal-close" onclick="closeAssignModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" id="modalAssignBody">
      <!--  JS -->
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeAssignModal()">Отмена</button>
      <button class="btn btn-primary" id="modalAssignSubmit" onclick="submitAssignModal()">Назначить</button>
    </div>
  </div>
</div>

<!--Модальное окно для админа тарификации-->
<!--Модальное окно для админа тарификации-->
<?php if ($isAdmin || $isPccHead): ?>
<div class="modal-overlay" id="modalExport" onclick="if(event.target===this)closeExportModal()">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
        Экспорт тарификации <?= e($academicYearLabel) ?>
      </span>
      <button class="modal-close" onclick="closeExportModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:.875rem;color:var(--color-text-muted);margin-bottom:1rem">
        Выберите председателя ПЦК, чьи назначения войдут в тарификационные ведомости.
      </p>
      <div class="modal-field">
        <label class="modal-label">Председатель ПЦК <span style="color:var(--color-error)">*</span></label>
        <select id="meSelectPcc" class="modal-select">
          <option value="">— выберите председателя —</option>
          <option value="all">— Все ПЦК —</option>
          <?php foreach ($pccHeads as $ph): ?>
          <option value="<?= (int)$ph['id'] ?>"><?= e($ph['full_name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeExportModal()">Отмена</button>
      <button class="btn btn-export" onclick="submitExportModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Скачать Excel
      </button>
    </div>
  </div>
</div>
<?php endif ?>

<!-- Загрузка мультиселекта, обработка кнопок, тема -->
<script src="../../js/subject_filter.js"></script>
<script src="../../js/teacher_assignments.js"></script>
<script src="../../js/umr.js"></script>

</body>
</html>
