<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();
$user = currentUser();
$role = $user['role'];

$roleLabel = [
    'admin'    => 'Администратор',
    'teacher'  => 'Преподаватель',
    'director' => 'Директор',
    'student'  => 'Студент',
][$role] ?? ucfirst($role);

$initials = implode('', array_map(
    fn($w) => mb_strtoupper(mb_substr($w, 0, 1)),
    array_slice(explode(' ', $user['full_name']), 0, 2)
));

$pageNames = [
    'dashboard'    => 'Главная',
    'achievements' => 'Достижения',
    'certificates' => 'Сертификаты',
    'events'       => 'Мероприятия',
    'rating'       => 'Рейтинг',
    'users'        => 'Пользователи',
    'profile'      => 'Профиль',
    'cert_review'  => 'Проверка документа',
    'groups'       => 'Группы',
    'export'       => 'Выгрузка',
];
$curPage  = basename($_SERVER['PHP_SELF'], '.php');
$pageName = $pageNames[$curPage] ?? '';

function tabItem(string $href, string $icon, string $label): string {
    $cur  = basename($_SERVER['PHP_SELF'], '.php');
    $page = basename(parse_url($href, PHP_URL_PATH), '.php');
    $isAch = in_array($cur, ['achievements','certificates']) && in_array($page, ['achievements','certificates']);
    $cls   = ($cur === $page || $isAch) ? ' active' : '';
    return "<a href=\"$href\" class=\"ach-nav-item$cls\">$icon <span>$label</span></a>";
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageName ? $pageName.' — '.SITE_NAME : SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css?v=15">
<style>
:root {
  --color-bg:#f0f4f8;--color-surface:#ffffff;--color-surface-2:#f8fafc;
  --color-surface-offset:#eef2f7;--color-divider:#e2e8f0;--color-border:#cbd5e1;
  --color-text:#1e293b;--color-text-muted:#64748b;--color-text-faint:#94a3b8;
  --color-primary:#1a56db;--color-primary-hover:#1346c2;--color-primary-highlight:#dbeafe;
  --radius-md:.5rem;--radius-lg:.75rem;--radius-xl:1rem;
  --transition:180ms cubic-bezier(.16,1,.3,1);
  --shadow-sm:0 1px 3px rgba(30,41,59,.08);--shadow-md:0 4px 12px rgba(30,41,59,.10);
  --font-body:'Inter',-apple-system,sans-serif;--font-display:'Montserrat','Inter',sans-serif;
  --portal-sidebar-width:240px;--topbar-height:56px;
}
[data-theme="dark"] {
  --color-bg:#0f172a;--color-surface:#1e293b;--color-surface-2:#263449;
  --color-surface-offset:#1a2740;--color-divider:#2d3f57;--color-border:#374f6b;
  --color-text:#e2e8f0;--color-text-muted:#94a3b8;--color-text-faint:#64748b;
  --color-primary:#3b82f6;--color-primary-highlight:#1e3a5f;
}
*, *::before, *::after { box-sizing: border-box; }
body { font-family:var(--font-body);background:var(--color-bg);color:var(--color-text);margin:0;display:flex;min-height:100dvh; }
.portal-sidebar { width:var(--portal-sidebar-width);min-height:100dvh;background:var(--color-surface);border-right:1px solid var(--color-divider);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:width var(--transition);overflow:hidden; }
.portal-sidebar.collapsed { width:60px; }
.portal-sidebar.collapsed .ps-logo-text,.portal-sidebar.collapsed .ps-section-label,.portal-sidebar.collapsed .ps-nav-item span,.portal-sidebar.collapsed .ps-footer { opacity:0;pointer-events:none; }
.portal-sidebar.collapsed .ps-nav-item { justify-content:center;padding-inline:0; }
.portal-sidebar.collapsed .ps-toggle { transform:rotate(180deg); }
.ps-header { display:flex;align-items:center;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--color-divider);min-height:var(--topbar-height);gap:.5rem; }
.ps-logo { display:flex;align-items:center;gap:.75rem;min-width:0;text-decoration:none; }
.ps-logo-text { display:flex;flex-direction:column;line-height:1.2;min-width:0; }
.ps-logo-title { font-family:var(--font-display);font-size:1.0625rem;font-weight:700;color:var(--color-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.ps-logo-sub { font-size:.8125rem;color:var(--color-text-muted);white-space:nowrap; }
.ps-toggle { width:28px;height:28px;flex-shrink:0;border-radius:.5rem;display:flex;align-items:center;justify-content:center;color:var(--color-text-muted);cursor:pointer;background:none;border:none;transition:background var(--transition),transform var(--transition); }
.ps-toggle:hover { background:var(--color-surface-offset); }
.ps-nav { flex:1;padding:.75rem;overflow-y:auto;overflow-x:hidden; }
.ps-section-label { font-size:.8125rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--color-text-faint);padding:1rem .75rem .5rem;white-space:nowrap;overflow:hidden; }
.ps-nav-item { display:flex;align-items:center;gap:.75rem;padding:.5rem .75rem;border-radius:.5rem;color:var(--color-text-muted);font-size:.9375rem;text-decoration:none;transition:background var(--transition),color var(--transition);white-space:nowrap;margin-bottom:.25rem;overflow:hidden; }
.ps-nav-item:hover { background:var(--color-surface-offset);color:var(--color-text); }
.ps-nav-item.active { background:var(--color-primary-highlight);color:var(--color-primary);font-weight:600; }
.ps-nav-item svg { flex-shrink:0; }
.ps-footer { padding:1rem;border-top:1px solid var(--color-divider);overflow:hidden; }
.ps-college-info { display:flex;flex-direction:column;gap:2px; }
.ps-college-info span { font-size:.8125rem;color:var(--color-text-faint);white-space:nowrap; }
.portal-main-wrapper { margin-left:var(--portal-sidebar-width);flex:1;display:flex;flex-direction:column;min-height:100dvh;min-width:0;transition:margin-left var(--transition); }
.portal-main-wrapper.sidebar-collapsed { margin-left:60px; }
.portal-topbar { height:var(--topbar-height);background:var(--color-surface);border-bottom:1px solid var(--color-divider);display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;position:sticky;top:0;z-index:100; }
.portal-breadcrumb { display:flex;align-items:center;gap:.5rem;font-size:.9375rem; }
.portal-breadcrumb a { color:var(--color-text-muted);text-decoration:none; }
.portal-breadcrumb a:hover { color:var(--color-text); }
.portal-breadcrumb .sep { color:var(--color-text-faint); }
.portal-breadcrumb .current { color:var(--color-text);font-weight:500; }
.portal-topbar-right { display:flex;align-items:center;gap:.75rem; }
.portal-user-avatar { width:32px;height:32px;border-radius:9999px;background:var(--color-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8125rem;font-weight:700; }
.portal-theme-btn { width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:.5rem;color:var(--color-text-muted);cursor:pointer;background:none;border:none;transition:background var(--transition); }
.portal-theme-btn:hover { background:var(--color-surface-offset); }
.ach-module-nav { background:var(--color-surface);border-bottom:1px solid var(--color-divider);padding:0 1.5rem;display:flex;gap:4px;overflow-x:auto; }
.ach-nav-item { display:flex;align-items:center;gap:6px;padding:.75rem 1rem;font-size:.9rem;font-weight:500;color:var(--color-text-muted);text-decoration:none;border-bottom:2px solid transparent;white-space:nowrap;transition:color var(--transition),border-color var(--transition); }
.ach-nav-item:hover { color:var(--color-text); }
.ach-nav-item.active { color:var(--color-primary);border-bottom-color:var(--color-primary);font-weight:600; }
.ach-nav-item svg { flex-shrink:0; }
.portal-page-content { flex:1;padding:1.5rem;min-width:0; }
@media (max-width:768px) {
  .portal-sidebar { transform:translateX(-100%); }
  .portal-sidebar.mobile-open { transform:translateX(0);box-shadow:0 12px 32px rgba(30,41,59,.13); }
  .portal-main-wrapper { margin-left:0 !important; }
}
</style>
</head>
<body>

<aside class="portal-sidebar" id="portalSidebar">
  <div class="ps-header">
    <a href="/" class="ps-logo">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#1a56db"/>
        <text x="16" y="22" text-anchor="middle" font-family="Montserrat,sans-serif" font-weight="700" font-size="13" fill="white">СП</text>
      </svg>
      <div class="ps-logo-text">
        <span class="ps-logo-title">СВГТК Портал</span>
        <span class="ps-logo-sub">Достижения</span>
      </div>
    </a>
    <button class="ps-toggle" id="portalSidebarToggle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
  </div>
  <nav class="ps-nav">
    <div class="ps-section-label">Навигация</div>
    <a href="/" class="ps-nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Главная</span>
    </a>
    <div class="ps-section-label" style="margin-top:1rem">Модули портала</div>
    <a href="/edu/" class="ps-nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
      <span>Учебный процесс</span>
    </a>
    <a href="/attendance/" class="ps-nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <span>Посещаемость</span>
    </a>
    <a href="/achievements/" class="ps-nav-item active">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
      <span>Достижения</span>
    </a>
    <a href="/umr/" class="ps-nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <span>УМР</span>
    </a>
    <a href="/hr/" class="ps-nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>HR-аналитика</span>
    </a>
    <a href="/analytics/" class="ps-nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Аналитика</span>
    </a>
    <a href="/requests/" class="ps-nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span>Заявки в ИТ</span>
    </a>
  </nav>
  <div class="ps-footer">
    <div class="ps-college-info">
      <span>СВГТК им. Абая Кунанбаева</span>
      <span>г. Сарань</span>
    </div>
  </div>
</aside>

<div class="portal-main-wrapper" id="portalMainWrapper">
  <header class="portal-topbar">
    <div class="portal-breadcrumb">
      <a href="/">СВГТК</a>
      <span class="sep"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
      <a href="<?= SITE_URL ?>/dashboard.php">Достижения</a>
      <?php if ($pageName && $curPage !== 'dashboard'): ?>
      <span class="sep"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
      <span class="current"><?= h($pageName) ?></span>
      <?php endif; ?>
    </div>
    <div class="portal-topbar-right">
      <button class="portal-theme-btn" id="portalThemeToggle" title="Тема">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <span style="font-size:.875rem;color:var(--color-text-muted)"><?= h($roleLabel) ?></span>
      <div class="portal-user-avatar" title="<?= h($user['full_name']) ?>"><?= h($initials) ?></div>
      <a href="<?= SITE_URL ?>/logout.php" title="Выйти" style="display:flex;align-items:center;color:var(--color-text-muted);padding:.25rem">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </header>

  <nav class="ach-module-nav">
    <?= tabItem(SITE_URL.'/dashboard.php',
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
      'Главная') ?>
    <?= tabItem(SITE_URL.'/achievements.php',
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>',
      'Достижения') ?>
  </nav>

  <div class="portal-page-content">