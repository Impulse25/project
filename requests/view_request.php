<?php
// view_request.php — Просмотр заявки

require_once __DIR__ . '/../config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireLogin();

$user      = getCurrentUser();
$requestId = (int)($_GET['id'] ?? 0);

if (!$requestId) {
    header('Location: index.php');
    exit();
}

// Получение заявки
$stmt = $pdo->prepare("
    SELECT r.*,
           creator.full_name  as creator_name,
           creator.position   as creator_position,
           tech.full_name     as tech_name,
           approver.full_name as approver_name
    FROM requests r
    LEFT JOIN users creator  ON r.created_by  = creator.id
    LEFT JOIN users tech     ON r.assigned_to  = tech.id
    LEFT JOIN users approver ON r.approved_by  = approver.id
    WHERE r.id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: index.php');
    exit();
}

// Проверка прав доступа
$canView = false;
if (in_array($user['role'], ['director', 'technician', 'admin'])) {
    $canView = true;
} elseif ($user['role'] === 'teacher' && $request['created_by'] == $user['id']) {
    $canView = true;
}
if (!$canView) {
    header('Location: index.php');
    exit();
}

// Комментарии
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name, u.role
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.request_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$requestId]);
$comments = $stmt->fetchAll();

// Хелперы
function priorityLabel(string $p): string {
    return ['low'=>'Низкий','normal'=>'Обычный','high'=>'Высокий','urgent'=>'СРОЧНО'][$p] ?? 'Обычный';
}
function priorityClass(string $p): string {
    return ['low'=>'badge-gray','normal'=>'badge-blue','high'=>'badge-amber','urgent'=>'badge-red'][$p] ?? 'badge-gray';
}
function statusLabel(string $s): string {
    return ['new'=>'Новая','pending'=>'Ожидает одобрения','approved'=>'Одобрена',
            'in_progress'=>'В работе','awaiting_approval'=>'Ожидает подтверждения',
            'completed'=>'Завершена','rejected'=>'Отклонена'][$s] ?? $s;
}
function statusClass(string $s): string {
    return ['new'=>'badge-blue','pending'=>'badge-amber','approved'=>'badge-blue',
            'in_progress'=>'badge-blue','awaiting_approval'=>'badge-gray',
            'completed'=>'badge-green','rejected'=>'badge-red'][$s] ?? 'badge-gray';
}
function typeLabel(string $t): string {
    return ['repair'=>'Ремонт и обслуживание','software'=>'Установка ПО',
            '1c_database'=>'База данных 1С','general_question'=>'Вопрос / Консультация'][$t] ?? $t;
}
function roleName(string $r): string {
    return ['teacher'=>'Преподаватель','technician'=>'Системотехник',
            'director'=>'Директор','admin'=>'Администратор'][$r] ?? 'Пользователь';
}

$priority  = $request['priority'] ?? 'normal';
$students  = !empty($request['students_list']) ? json_decode($request['students_list'], true) : [];
$currentLang = getCurrentLanguage();

// Бэклинк
$backLink = match($user['role']) {
    'admin'      => 'admin_requests.php',
    'director'   => 'director_dashboard.php',
    'technician' => 'technician_dashboard.php',
    default      => 'teacher_dashboard.php',
};

// Время
$createdTs   = strtotime($request['created_at']);
$nowTs       = time();
$completedTs = $request['completed_at'] ? strtotime($request['completed_at']) : null;
$startedTs   = $request['started_at']   ? strtotime($request['started_at'])   : null;
$totalDays   = round((($completedTs ?? $nowTs) - $createdTs) / 86400, 1);
$waitDays    = $startedTs ? round(($startedTs - $createdTs) / 86400, 1) : null;
$workDays    = $startedTs ? round((($completedTs ?? $nowTs) - $startedTs) / 86400, 1) : null;

$nameParts = explode(' ', trim($user['full_name']));
$initials  = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($nameParts, 0, 2)));
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Заявка #<?= $requestId ?> — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <style>
:root,[data-theme="light"]{
  --color-bg:#f0f4f8;--color-surface:#fff;--color-surface-2:#f8fafc;
  --color-surface-offset:#eef2f7;--color-divider:#e2e8f0;--color-border:#cbd5e1;
  --color-text:#1e293b;--color-text-muted:#64748b;--color-text-faint:#94a3b8;
  --color-text-inverse:#fff;
  --color-primary:#1a56db;--color-primary-hover:#1346c2;--color-primary-highlight:#dbeafe;
  --color-success:#16a34a;--color-success-highlight:#dcfce7;
  --color-warning:#d97706;--color-warning-highlight:#fef3c7;
  --color-error:#dc2626;--color-error-highlight:#fee2e2;
  --color-gold:#ca8a04;--color-gold-highlight:#fef9c3;
  --radius-sm:.375rem;--radius-md:.5rem;--radius-lg:.75rem;--radius-xl:1rem;--radius-full:9999px;
  --transition:180ms cubic-bezier(.16,1,.3,1);
  --shadow-sm:0 1px 3px rgba(30,41,59,.08);--shadow-md:0 4px 12px rgba(30,41,59,.10);--shadow-lg:0 12px 32px rgba(30,41,59,.13);
  --font-body:'Inter',-apple-system,sans-serif;--font-display:'Montserrat','Inter',sans-serif;
  --sidebar-width:240px;--topbar-height:56px;
}
[data-theme="dark"]{
  --color-bg:#0f172a;--color-surface:#1e293b;--color-surface-2:#263449;
  --color-surface-offset:#1a2740;--color-divider:#2d3f57;--color-border:#374f6b;
  --color-text:#e2e8f0;--color-text-muted:#94a3b8;--color-text-faint:#64748b;--color-text-inverse:#0f172a;
  --color-primary:#3b82f6;--color-primary-highlight:#1e3a5f;
  --shadow-sm:0 1px 3px rgba(0,0,0,.25);--shadow-md:0 4px 12px rgba(0,0,0,.35);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{-webkit-font-smoothing:antialiased}
body{font-family:var(--font-body);font-size:.9375rem;color:var(--color-text);background:var(--color-bg);min-height:100dvh;display:flex;line-height:1.6}
a{color:inherit;text-decoration:none}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}

/* Sidebar */
.sidebar{width:var(--sidebar-width);min-height:100dvh;background:var(--color-surface);border-right:1px solid var(--color-divider);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;transition:width var(--transition);overflow:hidden}
.sidebar.collapsed{width:60px}
.sidebar.collapsed .logo-text,.sidebar.collapsed .nav-section-label,.sidebar.collapsed .nav-item span,.sidebar.collapsed .sidebar-footer{opacity:0;pointer-events:none}
.sidebar.collapsed .nav-item{justify-content:center;padding-inline:0}
.sidebar.collapsed .sidebar-toggle{transform:rotate(180deg)}
.sidebar-header{display:flex;align-items:center;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--color-divider);min-height:var(--topbar-height);gap:.5rem}
.logo{display:flex;align-items:center;gap:.75rem;min-width:0}
.logo-text{display:flex;flex-direction:column;line-height:1.2;min-width:0}
.logo-title{font-family:var(--font-display);font-size:1.0625rem;font-weight:700;color:var(--color-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.logo-sub{font-size:.8125rem;color:var(--color-text-muted);white-space:nowrap}
.sidebar-toggle{width:28px;height:28px;flex-shrink:0;border-radius:.5rem;display:flex;align-items:center;justify-content:center;color:var(--color-text-muted);transition:background var(--transition),transform var(--transition)}
.sidebar-toggle:hover{background:var(--color-surface-offset)}
.sidebar-nav{flex:1;padding:.75rem;overflow-y:auto;overflow-x:hidden}
.nav-section-label{font-size:.8125rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--color-text-faint);padding:1rem .75rem .5rem;white-space:nowrap;overflow:hidden}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.5rem .75rem;border-radius:.5rem;color:var(--color-text-muted);font-size:.9375rem;transition:background var(--transition),color var(--transition);white-space:nowrap;margin-bottom:.25rem;overflow:hidden}
.nav-item:hover{background:var(--color-surface-offset);color:var(--color-text)}
.nav-item.active{background:var(--color-primary-highlight);color:var(--color-primary);font-weight:600}
.nav-item svg{flex-shrink:0}
.sidebar-footer{padding:1rem;border-top:1px solid var(--color-divider);overflow:hidden}
.college-info{display:flex;flex-direction:column;gap:2px}
.college-info span{font-size:.8125rem;color:var(--color-text-faint);white-space:nowrap}

/* Main */
.main-wrapper{margin-left:var(--sidebar-width);flex:1;display:flex;flex-direction:column;min-height:100dvh;min-width:0;transition:margin-left var(--transition)}
.main-wrapper.sidebar-collapsed{margin-left:60px}

/* Topbar */
.topbar{height:var(--topbar-height);background:var(--color-surface);border-bottom:1px solid var(--color-divider);display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;position:sticky;top:0;z-index:50;gap:1rem}
.topbar-left{display:flex;align-items:center;gap:1rem;min-width:0}
.breadcrumb{display:flex;align-items:center;gap:.5rem;font-size:.9375rem}
.breadcrumb-root{color:var(--color-text-muted)}
.breadcrumb svg{color:var(--color-text-faint);flex-shrink:0}
.breadcrumb-current{color:var(--color-text);font-weight:500}
.topbar-right{display:flex;align-items:center;gap:.75rem;flex-shrink:0}
.user-avatar{width:32px;height:32px;border-radius:var(--radius-full);background:var(--color-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8125rem;font-weight:700;flex-shrink:0}
.theme-toggle{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:.5rem;color:var(--color-text-muted);transition:background var(--transition)}
.theme-toggle:hover{background:var(--color-surface-offset)}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.375rem 1rem;border-radius:.5rem;font-size:.9375rem;font-weight:500;border:1px solid transparent;white-space:nowrap;transition:all var(--transition);cursor:pointer}
.btn-primary{background:var(--color-primary);color:#fff;border-color:var(--color-primary)}
.btn-primary:hover{background:var(--color-primary-hover)}
.btn-outline{background:transparent;color:var(--color-text);border-color:var(--color-border)}
.btn-outline:hover{background:var(--color-surface-offset)}
.btn-sm{padding:.25rem .75rem;font-size:.875rem}

/* Badges */
.badge{display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .625rem;border-radius:var(--radius-full);font-size:.8125rem;font-weight:600;white-space:nowrap}
.badge-blue{background:var(--color-primary-highlight);color:var(--color-primary)}
.badge-green{background:var(--color-success-highlight);color:var(--color-success)}
.badge-amber{background:var(--color-warning-highlight);color:var(--color-warning)}
.badge-red{background:var(--color-error-highlight);color:var(--color-error)}
.badge-gray{background:var(--color-surface-offset);color:var(--color-text-muted)}

/* Page */
.page-content{flex:1;padding:1.5rem;min-width:0}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap}
.page-title{font-family:var(--font-display);font-size:clamp(1.25rem,2vw,1.75rem);font-weight:700;color:var(--color-text);line-height:1.2}
.page-subtitle{font-size:.9375rem;color:var(--color-text-muted);margin-top:.25rem}
.page-actions{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}

/* Cards */
.card{background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-xl);box-shadow:var(--shadow-sm)}
.card-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--color-divider);display:flex;align-items:center;gap:.75rem}
.card-title{font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--color-text)}
.card-body{padding:1.25rem 1.5rem}

/* Info rows */
.info-row{display:grid;grid-template-columns:180px 1fr;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--color-divider)}
.info-row:last-child{border-bottom:none}
.info-label{font-weight:600;color:var(--color-text-muted);font-size:.875rem;display:flex;align-items:center;gap:.5rem}
.info-value{color:var(--color-text);display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}

/* Stat card */
.stat-row{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-radius:var(--radius-md);margin-bottom:.5rem}
.stat-row:last-child{margin-bottom:0}

/* Description block */
.desc-block{background:var(--color-surface-2);border:1px solid var(--color-divider);border-radius:var(--radius-lg);padding:1rem;color:var(--color-text);white-space:pre-wrap;line-height:1.7}

/* Timeline */
.timeline{display:flex;flex-direction:column;gap:1rem}
.timeline-item{display:flex;gap:1rem}
.timeline-dot{width:10px;height:10px;border-radius:50%;background:var(--color-primary);flex-shrink:0;margin-top:.4rem}
.timeline-body{flex:1;background:var(--color-surface-2);border:1px solid var(--color-divider);border-radius:var(--radius-lg);padding:1rem}
.timeline-meta{display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;flex-wrap:wrap}
.timeline-author{font-weight:600;color:var(--color-text)}
.timeline-date{font-size:.8125rem;color:var(--color-text-faint);margin-left:auto}
.timeline-text{color:var(--color-text);white-space:pre-wrap;line-height:1.6}

/* Grid layout */
.view-grid{display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start}
@media(max-width:900px){.view-grid{grid-template-columns:1fr}}

/* Print */
@media print{.sidebar,.topbar,.page-actions,.no-print{display:none!important}.main-wrapper{margin-left:0}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.sidebar.mobile-open{transform:translateX(0);box-shadow:var(--shadow-lg)}.main-wrapper{margin-left:0!important}.info-row{grid-template-columns:1fr}}
  </style>
</head>
<body>

<?php
$activePage = 'dashboard';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-wrapper" id="mainWrapper">
  <header class="topbar">
    <div class="topbar-left">
      <div class="breadcrumb">
        <span class="breadcrumb-root">СВГТК</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <a href="<?= $backLink ?>" class="breadcrumb-root">Заявки</a>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="breadcrumb-current">Заявка #<?= $requestId ?></span>
      </div>
    </div>
    <div class="topbar-right">
      <button class="theme-toggle" id="themeToggle" title="Тема">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <div class="user-avatar" title="<?= htmlspecialchars($user['full_name']) ?>"><?= $initials ?></div>
      <span style="width:1px;height:20px;background:var(--color-divider);flex-shrink:0"></span>
      <a href="logout.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Выход
      </a>
    </div>
  </header>

  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Заявка #<?= $requestId ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($request['creator_name'] ?? '') ?></p>
      </div>
      <div class="page-actions no-print">
        <button onclick="window.print()" class="btn btn-outline btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Печать
        </button>
        <a href="<?= $backLink ?>" class="btn btn-primary btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Назад
        </a>
      </div>
    </div>

    <div class="view-grid">

      <!-- ══ ЛЕВАЯ КОЛОНКА ══ -->
      <div style="display:flex;flex-direction:column;gap:1.5rem">

        <!-- Основная информация -->
        <div class="card">
          <div class="card-header">
            <span class="badge <?= priorityClass($priority) ?>"><?= priorityLabel($priority) ?></span>
            <span class="badge badge-gray"><?= htmlspecialchars(typeLabel($request['request_type'])) ?></span>
            <span class="badge <?= statusClass($request['status']) ?>"><?= statusLabel($request['status']) ?></span>
            <?php if ($request['deadline']): ?>
              <span class="badge badge-amber" style="margin-left:auto">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Срок: <?= date('d.m.Y', strtotime($request['deadline'])) ?>
              </span>
            <?php endif ?>
          </div>
          <div class="card-body">
            <h2 style="font-family:var(--font-display);font-size:1.25rem;font-weight:700;margin-bottom:1rem">
              Кабинет: <?= htmlspecialchars($request['cabinet'] ?? '') ?>
              <?php
              if ($request['request_type'] === 'repair')
                  echo ' — ' . htmlspecialchars($request['equipment_type'] ?? '');
              elseif ($request['request_type'] === '1c_database')
                  echo ' — База данных 1С';
              elseif ($request['request_type'] === 'software')
                  echo ' — ' . htmlspecialchars($request['software_name'] ?? '');
              elseif ($request['request_type'] === 'general_question') {
                  echo ' — Общие вопросы / Консультация';
                  if (!empty($request['software_or_system']))
                      echo ' (' . htmlspecialchars($request['software_or_system']) . ')';
              }
              ?>
            </h2>

            <p style="font-weight:600;color:var(--color-text-muted);margin-bottom:.5rem">Описание:</p>
            <div class="desc-block">
              <?php
              if ($request['request_type'] === 'repair')
                  echo htmlspecialchars($request['problem_description'] ?? '');
              elseif ($request['request_type'] === 'software')
                  echo htmlspecialchars($request['justification'] ?? '');
              elseif ($request['request_type'] === '1c_database')
                  echo htmlspecialchars($request['database_purpose'] ?? '');
              elseif ($request['request_type'] === 'general_question')
                  echo htmlspecialchars($request['question_description'] ?? '');
              ?>
            </div>

            <?php if (!empty($students)): ?>
              <div style="margin-top:1rem;padding:1rem;background:var(--color-primary-highlight);border-radius:var(--radius-lg)">
                <p style="font-weight:600;margin-bottom:.75rem;color:var(--color-primary)">
                  Список студентов (<?= count($students) ?>):
                </p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem">
                  <?php foreach ($students as $student): ?>
                    <span style="display:flex;align-items:center;gap:.5rem;font-size:.875rem">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                      <?= htmlspecialchars($student) ?>
                    </span>
                  <?php endforeach ?>
                </div>
              </div>
            <?php endif ?>
          </div>
        </div>

        <!-- Детальная информация -->
        <div class="card">
          <div class="card-header">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span class="card-title">Детальная информация</span>
          </div>
          <div class="card-body">

            <div class="info-row">
              <div class="info-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Создал
              </div>
              <div class="info-value">
                <?= htmlspecialchars($request['creator_name'] ?? '') ?>
                <?php if ($request['creator_position']): ?>
                  <span style="color:var(--color-text-muted);font-size:.875rem">(<?= htmlspecialchars($request['creator_position']) ?>)</span>
                <?php endif ?>
              </div>
            </div>

            <div class="info-row">
              <div class="info-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Кабинет
              </div>
              <div class="info-value"><?= htmlspecialchars($request['cabinet'] ?? '') ?></div>
            </div>

            <?php if ($request['inventory_number']): ?>
            <div class="info-row">
              <div class="info-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg>
                Инвентарный номер
              </div>
              <div class="info-value"><?= htmlspecialchars($request['inventory_number']) ?></div>
            </div>
            <?php endif ?>

            <?php if ($request['request_type'] === '1c_database' && $request['group_number']): ?>
            <div class="info-row">
              <div class="info-label">Номер группы</div>
              <div class="info-value"><?= htmlspecialchars($request['group_number']) ?></div>
            </div>
            <?php endif ?>

            <div class="info-row">
              <div class="info-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Дата создания
              </div>
              <div class="info-value"><?= date('d.m.Y H:i', $createdTs) ?></div>
            </div>

            <?php if ($request['tech_name']): ?>
            <div class="info-row">
              <div class="info-label">Системотехник</div>
              <div class="info-value"><?= htmlspecialchars($request['tech_name']) ?></div>
            </div>
            <?php endif ?>

            <?php if ($request['started_at']): ?>
            <div class="info-row">
              <div class="info-label">Начато</div>
              <div class="info-value"><?= date('d.m.Y H:i', $startedTs) ?></div>
            </div>
            <?php endif ?>

            <?php if ($request['completed_at']): ?>
            <div class="info-row">
              <div class="info-label">Завершено</div>
              <div class="info-value"><?= date('d.m.Y H:i', $completedTs) ?></div>
            </div>
            <?php endif ?>

            <?php if ($request['approver_name']): ?>
            <div class="info-row">
              <div class="info-label">Одобрил</div>
              <div class="info-value"><?= htmlspecialchars($request['approver_name']) ?></div>
            </div>
            <?php endif ?>

            <?php if ($request['rejection_reason']): ?>
            <div class="info-row">
              <div class="info-label" style="color:var(--color-error)">Причина отклонения</div>
              <div class="info-value" style="color:var(--color-error)"><?= htmlspecialchars($request['rejection_reason']) ?></div>
            </div>
            <?php endif ?>

          </div>
        </div>

        <!-- Доп. информация -->
        <?php if ($request['teacher_feedback'] || $request['completion_note']): ?>
        <div class="card">
          <div class="card-header">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="card-title">Дополнительная информация</span>
          </div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:1rem">
            <?php if ($request['teacher_feedback']): ?>
              <div style="border-left:3px solid var(--color-primary);padding-left:1rem">
                <p style="font-weight:600;margin-bottom:.5rem;color:var(--color-primary)">Отзыв преподавателя:</p>
                <p style="white-space:pre-wrap"><?= nl2br(htmlspecialchars($request['teacher_feedback'])) ?></p>
              </div>
            <?php endif ?>
            <?php if ($request['completion_note']): ?>
              <div style="border-left:3px solid var(--color-success);padding-left:1rem">
                <p style="font-weight:600;margin-bottom:.5rem;color:var(--color-success)">Примечание техника:</p>
                <p style="white-space:pre-wrap"><?= nl2br(htmlspecialchars($request['completion_note'])) ?></p>
              </div>
            <?php endif ?>
          </div>
        </div>
        <?php endif ?>

        <!-- Комментарии -->
        <?php if (!empty($comments)): ?>
        <div class="card">
          <div class="card-header">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="card-title">Комментарии (<?= count($comments) ?>)</span>
          </div>
          <div class="card-body">
            <div class="timeline">
              <?php foreach ($comments as $c): ?>
                <div class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-body">
                    <div class="timeline-meta">
                      <span class="timeline-author"><?= htmlspecialchars($c['full_name']) ?></span>
                      <span class="badge badge-gray" style="font-size:.75rem"><?= roleName($c['role']) ?></span>
                      <span class="timeline-date"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></span>
                    </div>
                    <div class="timeline-text"><?= nl2br(htmlspecialchars($c['comment'])) ?></div>
                  </div>
                </div>
              <?php endforeach ?>
            </div>
          </div>
        </div>
        <?php endif ?>

      </div><!-- /left -->

      <!-- ══ ПРАВАЯ КОЛОНКА ══ -->
      <div style="display:flex;flex-direction:column;gap:1.5rem">

        <!-- Статистика -->
        <div class="card">
          <div class="card-header">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span class="card-title">Статистика</span>
          </div>
          <div class="card-body">
            <div class="stat-row" style="background:var(--color-primary-highlight)">
              <span style="font-size:.875rem;color:var(--color-text-muted)">Всего дней</span>
              <strong style="color:var(--color-primary)"><?= $totalDays ?></strong>
            </div>
            <?php if ($waitDays !== null): ?>
            <div class="stat-row" style="background:var(--color-warning-highlight)">
              <span style="font-size:.875rem;color:var(--color-text-muted)">Ожидание</span>
              <strong style="color:var(--color-warning)"><?= $waitDays ?> дн.</strong>
            </div>
            <?php endif ?>
            <?php if ($workDays !== null): ?>
            <div class="stat-row" style="background:var(--color-success-highlight)">
              <span style="font-size:.875rem;color:var(--color-text-muted)">В работе</span>
              <strong style="color:var(--color-success)"><?= $workDays ?> дн.</strong>
            </div>
            <?php endif ?>
            <div class="stat-row" style="background:var(--color-surface-offset)">
              <span style="font-size:.875rem;color:var(--color-text-muted)">Комментариев</span>
              <strong style="color:var(--color-text)"><?= count($comments) ?></strong>
            </div>
          </div>
        </div>

        <!-- Действия -->
        <div class="card no-print">
          <div class="card-header">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 0 0 4.93 19.07M4.93 4.93a10 10 0 0 0 14.14 14.14"/></svg>
            <span class="card-title">Действия</span>
          </div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem">
            <button onclick="window.print()" class="btn btn-outline" style="width:100%;justify-content:center">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
              Печать
            </button>
            <a href="<?= $backLink ?>" class="btn btn-primary" style="width:100%;justify-content:center">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
              Вернуться назад
            </a>
          </div>
        </div>

      </div><!-- /right -->
    </div><!-- /view-grid -->
  </main>
</div><!-- /main-wrapper -->

<script>
// Sidebar
const sidebar = document.getElementById('sidebar');
const mainWrapper = document.getElementById('mainWrapper');
document.getElementById('sidebarToggle').addEventListener('click', () => {
  const c = sidebar.classList.toggle('collapsed');
  mainWrapper.classList.toggle('sidebar-collapsed', c);
  localStorage.setItem('sidebarCollapsed', c);
});
if (localStorage.getItem('sidebarCollapsed') === 'true') {
  sidebar.classList.add('collapsed');
  mainWrapper.classList.add('sidebar-collapsed');
}
// Dark mode
const html = document.documentElement;
html.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
document.getElementById('themeToggle').addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
});
</script>
</body>
</html>
