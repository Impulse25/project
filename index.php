<?php
// httpdocs/index.php — Главная портала + форма входа
session_start();

// Подключаем auth из модуля requests
require_once __DIR__ . '/requests/config/db.php';
require_once __DIR__ . '/requests/includes/auth.php';
require_once __DIR__ . '/requests/includes/language.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $_SESSION['full_name'] ?? '';
$userRole   = $_SESSION['role'] ?? '';
$nameParts  = explode(' ', trim($userName));
$initials   = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p,0,1)), array_slice($nameParts,0,2)));

$loginError = '';
$redirectTo = $_GET['redirect'] ?? '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirectTo = $_POST['redirect'] ?? '';

    if (!empty($username) && !empty($password)) {
        if (login($pdo, $username, $password)) {
            // Редирект на нужную страницу или дашборд
            if (!empty($redirectTo)) {
                header('Location: ' . $redirectTo);
            } else {
                redirectToDashboard();
            }
            exit();
        } else {
            $loginError = 'Неверный логин или пароль';
        }
    } else {
        $loginError = 'Введите логин и пароль';
    }
}

// Если уже залогинен и есть редирект — перенаправляем
if ($isLoggedIn && !empty($redirectTo)) {
    header('Location: ' . $redirectTo);
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>СВГТК Портал — Главная</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <style>
:root,[data-theme="light"]{
  --color-bg:#f0f4f8;--color-surface:#ffffff;--color-surface-2:#f8fafc;
  --color-surface-offset:#eef2f7;--color-divider:#e2e8f0;--color-border:#cbd5e1;
  --color-text:#1e293b;--color-text-muted:#64748b;--color-text-faint:#94a3b8;
  --color-text-inverse:#ffffff;
  --color-primary:#1a56db;--color-primary-hover:#1346c2;
  --color-primary-highlight:#dbeafe;
  --color-success:#16a34a;--color-success-highlight:#dcfce7;
  --color-warning:#d97706;--color-warning-highlight:#fef3c7;
  --color-error:#dc2626;--color-error-highlight:#fee2e2;
  --radius-md:.5rem;--radius-lg:.75rem;--radius-xl:1rem;--radius-full:9999px;
  --transition:180ms cubic-bezier(.16,1,.3,1);
  --shadow-sm:0 1px 3px rgba(30,41,59,.08);--shadow-md:0 4px 12px rgba(30,41,59,.10);--shadow-lg:0 12px 32px rgba(30,41,59,.13);
  --font-body:'Inter',-apple-system,sans-serif;--font-display:'Montserrat','Inter',sans-serif;
  --sidebar-width:240px;--topbar-height:56px;
}
[data-theme="dark"]{
  --color-bg:#0f172a;--color-surface:#1e293b;--color-surface-2:#263449;
  --color-surface-offset:#1a2740;--color-divider:#2d3f57;--color-border:#374f6b;
  --color-text:#e2e8f0;--color-text-muted:#94a3b8;--color-text-faint:#64748b;
  --color-text-inverse:#0f172a;
  --color-primary:#3b82f6;--color-primary-highlight:#1e3a5f;
  --shadow-sm:0 1px 3px rgba(0,0,0,.25);--shadow-md:0 4px 12px rgba(0,0,0,.35);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{-webkit-font-smoothing:antialiased}
body{font-family:var(--font-body);font-size:.9375rem;color:var(--color-text);
  background:var(--color-bg);min-height:100dvh;display:flex;line-height:1.6}
a{color:inherit;text-decoration:none}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}

/* ── Sidebar ── */
.sidebar{width:var(--sidebar-width);min-height:100dvh;background:var(--color-surface);
  border-right:1px solid var(--color-divider);display:flex;flex-direction:column;
  position:fixed;top:0;left:0;bottom:0;z-index:100;
  transition:width var(--transition);overflow:hidden}
.sidebar.collapsed{width:60px}
.sidebar.collapsed .logo-text,
.sidebar.collapsed .nav-section-label,
.sidebar.collapsed .nav-item span,
.sidebar.collapsed .sidebar-footer{opacity:0;pointer-events:none}
.sidebar.collapsed .nav-item{justify-content:center;padding-inline:0}
.sidebar.collapsed .sidebar-toggle{transform:rotate(180deg)}
.sidebar-header{display:flex;align-items:center;justify-content:space-between;
  padding:1rem;border-bottom:1px solid var(--color-divider);
  min-height:var(--topbar-height);gap:.5rem}
.logo{display:flex;align-items:center;gap:.75rem;min-width:0}
.logo-text{display:flex;flex-direction:column;line-height:1.2;min-width:0}
.logo-title{font-family:var(--font-display);font-size:1.0625rem;font-weight:700;
  color:var(--color-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.logo-sub{font-size:.8125rem;color:var(--color-text-muted);white-space:nowrap}
.sidebar-toggle{width:28px;height:28px;flex-shrink:0;border-radius:.5rem;
  display:flex;align-items:center;justify-content:center;color:var(--color-text-muted);
  transition:background var(--transition),transform var(--transition)}
.sidebar-toggle:hover{background:var(--color-surface-offset)}
.sidebar-nav{flex:1;padding:.75rem;overflow-y:auto;overflow-x:hidden}
.nav-section-label{font-size:.8125rem;font-weight:600;text-transform:uppercase;
  letter-spacing:.08em;color:var(--color-text-faint);
  padding:1rem .75rem .5rem;white-space:nowrap;overflow:hidden}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.5rem .75rem;
  border-radius:.5rem;color:var(--color-text-muted);font-size:.9375rem;
  transition:background var(--transition),color var(--transition);
  white-space:nowrap;margin-bottom:.25rem;overflow:hidden}
.nav-item:hover{background:var(--color-surface-offset);color:var(--color-text)}
.nav-item.active{background:var(--color-primary-highlight);color:var(--color-primary);font-weight:600}
.nav-item svg{flex-shrink:0}
.sidebar-footer{padding:1rem;border-top:1px solid var(--color-divider);overflow:hidden}
.college-info{display:flex;flex-direction:column;gap:2px}
.college-info span{font-size:.8125rem;color:var(--color-text-faint);white-space:nowrap}

/* ── Main ── */
.main-wrapper{margin-left:var(--sidebar-width);flex:1;display:flex;flex-direction:column;
  min-height:100dvh;min-width:0;transition:margin-left var(--transition)}
.main-wrapper.sidebar-collapsed{margin-left:60px}

/* ── Topbar ── */
.topbar{height:var(--topbar-height);background:var(--color-surface);
  border-bottom:1px solid var(--color-divider);display:flex;align-items:center;
  justify-content:space-between;padding:0 1.5rem;position:sticky;top:0;z-index:50}
.topbar-left{display:flex;align-items:center;gap:1rem;min-width:0}
.breadcrumb{display:flex;align-items:center;gap:.5rem;font-size:.9375rem}
.breadcrumb-root{color:var(--color-text-muted)}
.breadcrumb svg{color:var(--color-text-faint);flex-shrink:0}
.breadcrumb-current{color:var(--color-text);font-weight:500}
.topbar-right{display:flex;align-items:center;gap:.75rem;flex-shrink:0}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.375rem 1rem;
  border-radius:.5rem;font-size:.9375rem;font-weight:500;border:1px solid transparent;
  white-space:nowrap;transition:all var(--transition)}
.btn-primary{background:var(--color-primary);color:#fff;border-color:var(--color-primary)}
.btn-primary:hover{background:var(--color-primary-hover)}
.btn-outline{background:transparent;color:var(--color-text);border-color:var(--color-border)}
.btn-outline:hover{background:var(--color-surface-offset)}
.btn-sm{padding:.25rem .75rem;font-size:.8125rem}
.theme-toggle{width:36px;height:36px;display:flex;align-items:center;justify-content:center;
  border-radius:.5rem;color:var(--color-text-muted);transition:background var(--transition)}
.theme-toggle:hover{background:var(--color-surface-offset)}
.user-avatar{width:32px;height:32px;border-radius:var(--radius-full);
  background:var(--color-primary);color:#fff;display:flex;align-items:center;
  justify-content:center;font-size:.8125rem;font-weight:700;flex-shrink:0}

/* ── Page content ── */
.page-content{flex:1;padding:1.5rem;min-width:0}
.page-header{margin-bottom:2rem}
.page-title{font-family:var(--font-display);font-size:clamp(1.5rem,2vw,2rem);
  font-weight:700;color:var(--color-text);line-height:1.2}
.page-subtitle{font-size:.9375rem;color:var(--color-text-muted);margin-top:.25rem}

/* ── Modules grid ── */
.modules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem}

.module-card{background:var(--color-surface);border:1px solid var(--color-border);
  border-radius:var(--radius-xl);padding:1.5rem;
  transition:box-shadow var(--transition),transform var(--transition),border-color var(--transition);
  box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:1rem;
  text-decoration:none;color:inherit;position:relative;overflow:hidden}
.module-card:hover{box-shadow:var(--shadow-lg);transform:translateY(-3px);border-color:var(--color-primary-highlight)}
.module-card.disabled{opacity:.55;pointer-events:none;cursor:default}

.module-card-accent{position:absolute;top:0;left:0;right:0;height:4px;border-radius:var(--radius-xl) var(--radius-xl) 0 0}

.module-icon{width:48px;height:48px;border-radius:.75rem;display:flex;
  align-items:center;justify-content:center;flex-shrink:0}

.module-top{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}
.module-name{font-family:var(--font-display);font-size:1.0625rem;font-weight:700;
  color:var(--color-text);margin-bottom:.25rem}
.module-desc{font-size:.875rem;color:var(--color-text-muted);line-height:1.5}

.module-footer{display:flex;align-items:center;justify-content:space-between;
  margin-top:auto;padding-top:1rem;border-top:1px solid var(--color-divider)}
.module-student{font-size:.8125rem;color:var(--color-text-muted)}
.module-status{display:inline-flex;align-items:center;gap:4px;
  font-size:.75rem;font-weight:600;padding:3px 10px;border-radius:var(--radius-full)}
.status-active{background:var(--color-success-highlight);color:var(--color-success)}
.status-dev{background:var(--color-warning-highlight);color:var(--color-warning)}
.status-plan{background:var(--color-surface-offset);color:var(--color-text-faint)}

/* ── Hero ── */
.hero{background:linear-gradient(135deg,#1a56db 0%,#1e40af 100%);
  border-radius:var(--radius-xl);padding:2rem 2.5rem;margin-bottom:2rem;
  color:#fff;display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:1.5rem;box-shadow:var(--shadow-lg)}
.hero-title{font-family:var(--font-display);font-size:clamp(1.25rem,2.5vw,1.75rem);
  font-weight:700;margin-bottom:.5rem}
.hero-sub{font-size:.9375rem;opacity:.85;max-width:480px;line-height:1.5}
.hero-year{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);
  padding:.375rem 1rem;border-radius:var(--radius-full);font-size:.875rem;font-weight:600;
  white-space:nowrap;backdrop-filter:blur(4px)}

@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.mobile-open{transform:translateX(0);box-shadow:var(--shadow-lg)}
  .main-wrapper{margin-left:0!important}
  .hero{padding:1.5rem}
  .modules-grid{grid-template-columns:1fr}
}

/* ── Login Modal ── */
.login-modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(15,23,42,.6);z-index:500;
  align-items:center;justify-content:center;
  backdrop-filter:blur(4px);
  animation:fadeIn .2s ease
}
.login-modal-overlay.open{display:flex}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

.login-modal{
  background:var(--color-surface);border-radius:var(--radius-xl);
  padding:2.5rem;width:90%;max-width:400px;
  box-shadow:var(--shadow-lg);border:1px solid var(--color-border);
  animation:slideUp .25s ease
}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}

.login-modal-logo{
  display:flex;align-items:center;justify-content:center;
  gap:.75rem;margin-bottom:1.5rem
}
.login-modal-title{
  font-family:var(--font-display);font-size:1.25rem;font-weight:700;
  color:var(--color-text);text-align:center;margin-bottom:.25rem
}
.login-modal-sub{
  font-size:.875rem;color:var(--color-text-muted);
  text-align:center;margin-bottom:1.75rem
}
.login-form-group{display:flex;flex-direction:column;gap:.375rem;margin-bottom:1rem}
.login-form-label{font-size:.8125rem;font-weight:500;color:var(--color-text-muted)}
.login-form-input{
  width:100%;border:1.5px solid var(--color-border);border-radius:var(--radius-md);
  padding:.625rem .875rem;font:inherit;font-size:.9375rem;color:var(--color-text);
  background:var(--color-surface);transition:border-color var(--transition),box-shadow var(--transition)
}
.login-form-input:focus{
  outline:none;border-color:var(--color-primary);
  box-shadow:0 0 0 3px rgba(26,86,219,.15)
}
.login-error{
  background:var(--color-error-highlight);color:var(--color-error);
  border:1px solid #fca5a5;border-radius:var(--radius-md);
  padding:.625rem .875rem;font-size:.875rem;margin-bottom:1rem;
  display:flex;align-items:center;gap:.5rem
}
.login-btn{
  width:100%;padding:.75rem;background:var(--color-primary);color:#fff;
  border:none;border-radius:var(--radius-md);font-size:.9375rem;font-weight:600;
  cursor:pointer;font-family:inherit;transition:background var(--transition);
  margin-top:.5rem
}
.login-btn:hover{background:var(--color-primary-hover)}
.login-close{
  position:absolute;top:1rem;right:1rem;
  width:32px;height:32px;display:flex;align-items:center;justify-content:center;
  border-radius:var(--radius-md);color:var(--color-text-muted);cursor:pointer;
  transition:background var(--transition)
}
.login-close:hover{background:var(--color-surface-offset)}
.login-modal{position:relative}
  </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#1a56db"/>
        <text x="16" y="22" text-anchor="middle" font-family="Montserrat,sans-serif" font-weight="700" font-size="13" fill="white">СП</text>
      </svg>
      <div class="logo-text">
        <span class="logo-title">СВГТК Портал</span>
        <span class="logo-sub">Главная</span>
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Навигация</div>
    <a href="/" class="nav-item active">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Главная</span>
    </a>

    <div class="nav-section-label" style="margin-top:1rem">Модули портала</div>
    <a href="#" data-href="/edu/" onclick="handleCardClick(this, event)" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
      <span>Учебный процесс</span>
    </a>
    <a href="#" data-href="/attendance/" onclick="handleCardClick(this, event)" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <span>Посещаемость</span>
    </a>
    <a href="#" data-href="/achievements/" onclick="handleCardClick(this, event)" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
      <span>Достижения</span>
    </a>
    <a href="#" data-href="/umr/" onclick="handleCardClick(this, event)" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <span>УМР</span>
    </a>
    <a href="#" data-href="/hr/" onclick="handleCardClick(this, event)" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>HR-аналитика</span>
    </a>
    <a href="#" data-href="/analytics/" onclick="handleCardClick(this, event)" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Аналитика</span>
    </a>
    <a href="#" data-href="/requests/" onclick="handleCardClick(this, event)" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span>Заявки в ИТ</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="college-info">
      <span>СВГТК им. Абая Кунанбаева</span>
      <span>г. Сарань</span>
    </div>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main-wrapper" id="mainWrapper">

  <header class="topbar">
    <div class="topbar-left">
      <div class="breadcrumb">
        <span class="breadcrumb-root">СВГТК</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="breadcrumb-current">Главная</span>
      </div>
    </div>
    <div class="topbar-right">
      <button class="theme-toggle" id="themeToggle" title="Тема">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <?php if($isLoggedIn): ?>
        <div class="user-avatar" title="<?= htmlspecialchars($userName) ?>"><?= $initials ?></div>
        <span style="width:1px;height:20px;background:var(--color-divider);flex-shrink:0"></span>
        <a href="#" data-href="/requests/logout.php" onclick="handleCardClick(this, event)" class="btn btn-outline btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Выход
        </a>
      <?php else: ?>
        <a href="#" data-href="/requests/" onclick="handleCardClick(this, event)" class="btn btn-primary btn-sm">Войти</a>
      <?php endif ?>
    </div>
  </header>

  <main class="page-content">

    <!-- Page header -->
    <div class="page-header">
      <h1 class="page-title">Модули портала</h1>
      <p class="page-subtitle">Выберите нужный раздел системы</p>
    </div>

    <!-- Modules grid -->
    <div class="modules-grid">

      <!-- Учебный процесс -->
      <a href="#" data-href="/edu/" onclick="handleCardClick(this, event)" class="module-card">
        <div class="module-card-accent" style="background:#1a56db"></div>
        <div class="module-top">
          <div class="module-icon" style="background:#dbeafe;color:#1a56db">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
          </div>
          <span class="module-status status-dev">В разработке</span>
        </div>
        <div>
          <div class="module-name">Разработка приложения для управления учебным процессом в системе «СВГТК Портал»</div>
          <div class="module-desc">БД РУПл + Загрузка РУПл на группу; формирование ведомостей за семестр, по предмету, по весь период обучения (8 семестров), личная карточка, дипломная книга; шаблон для заполнения дипломов и приложений; выгрузка по студентам (по критериям)</div>
        </div>
        <div class="module-footer">
          <span class="module-student">Ломакин Александр</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-text-faint)"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>

      <!-- Посещаемость -->
      <a href="#" data-href="/attendance/" onclick="handleCardClick(this, event)" class="module-card">
        <div class="module-card-accent" style="background:#16a34a"></div>
        <div class="module-top">
          <div class="module-icon" style="background:#dcfce7;color:#16a34a">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <span class="module-status status-dev">В разработке</span>
        </div>
        <div>
          <div class="module-name">Разработка приложения для учёта посещаемости в системе «СВГТК Портал»</div>
          <div class="module-desc">БД Студенты (НОБД), Рапортички за неделя, месяц, за указанный срок; подсчет часов по уважительной и неуваж причине, прикрепление справок; отчет по критериям, печать с программы, выгрузка Excel (студенты просмотр, преподаватели редактор)</div>
        </div>
        <div class="module-footer">
          <span class="module-student">Базарбаев Рахат</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-text-faint)"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>

      <!-- QR -->
      <a href="#" data-href="/qr/" onclick="handleCardClick(this, event)" class="module-card">
        <div class="module-card-accent" style="background:#0891b2"></div>
        <div class="module-top">
          <div class="module-icon" style="background:#cffafe;color:#0891b2">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3z"/><path d="M17 17h3v3h-3z"/><path d="M14 20h3"/><path d="M20 14v3"/></svg>
          </div>
          <span class="module-status status-dev">В разработке</span>
        </div>
        <div>
          <div class="module-name">Разработка приложения для учёта посещаемости студентов с использованием QR-кодов в системе «СВГТК Портал»</div>
          <div class="module-desc">(на входе, на выход; данные с турникета; выгрузка по критериям; и сверка «Рапортичками - Рахата»)</div>
        </div>
        <div class="module-footer">
          <span class="module-student">Буланков Андрей</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-text-faint)"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>

      <!-- Достижения -->
      <a href="#" data-href="/achievements/" onclick="handleCardClick(this, event)" class="module-card">
        <div class="module-card-accent" style="background:#ca8a04"></div>
        <div class="module-top">
          <div class="module-icon" style="background:#fef9c3;color:#ca8a04">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
          </div>
          <span class="module-status status-dev">В разработке</span>
        </div>
        <div>
          <div class="module-name">Разработка приложения для учёта достижений в системе «СВГТК Портал»</div>
          <div class="module-desc">Считывать сертификат пдф (курсов, конкурсов и т.п.) для студентов и преподавателей, выгрузка по критериям (кабинет преподавателя).</div>
        </div>
        <div class="module-footer">
          <span class="module-student">Бекболсынов Алмас</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-text-faint)"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>

      <!-- УМР -->
      <a href="#" data-href="/umr/" onclick="handleCardClick(this, event)" class="module-card">
        <div class="module-card-accent" style="background:#7c3aed"></div>
        <div class="module-top">
          <div class="module-icon" style="background:#ede9fe;color:#7c3aed">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
          </div>
          <span class="module-status status-dev">В разработке</span>
        </div>
        <div>
          <div class="module-name">Разработка приложения для учёта учебно-методической работы в системе «СВГТК Портал»</div>
          <div class="module-desc">На основе РУПл- Ломакина: загрузка РУПов ворд(модуль) преподавателями согласно РУПЛ Ломакина, по ПЦК с ведением общего журнала регистрации, с выгрузкой по критериям; Журнал нагрузки на учебный год/семестр (по критериям</div>
        </div>
        <div class="module-footer">
          <span class="module-student">Голоднев Евгений</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-text-faint)"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>

      <!-- HR -->
      <a href="#" data-href="/hr/" onclick="handleCardClick(this, event)" class="module-card">
        <div class="module-card-accent" style="background:#db2777"></div>
        <div class="module-top">
          <div class="module-icon" style="background:#fce7f3;color:#db2777">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <span class="module-status status-dev">В разработке</span>
        </div>
        <div>
          <div class="module-name">Разработка приложения для HR-аналитики в системе «СВГТК Портал»</div>
          <div class="module-desc">трудоустройство куратор прикрепляет справки, и визуализация по критериям.</div>
        </div>
        <div class="module-footer">
          <span class="module-student">Ворожейкин Данил </span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-text-faint)"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>

      <!-- Аналитика -->
      <a href="#" data-href="/analytics/" onclick="handleCardClick(this, event)" class="module-card">
        <div class="module-card-accent" style="background:#0284c7"></div>
        <div class="module-top">
          <div class="module-icon" style="background:#e0f2fe;color:#0284c7">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          </div>
          <span class="module-status status-dev">В разработке</span>
        </div>
        <div>
          <div class="module-name">Разработка приложения аналитики и отчётности в системе «СВГТК Портал»</div>
          <div class="module-desc">Сводные отчёты по всем модулям, графики успеваемости, посещаемости и активности.</div>
        </div>
        <div class="module-footer">
          <span class="module-student">Пушкарев Артур </span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-text-faint)"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>

      <!-- Заявки в ИТ -->
      <a href="#" data-href="/requests/" onclick="handleCardClick(this, event)" class="module-card">
        <div class="module-card-accent" style="background:#1a56db"></div>
        <div class="module-top">
          <div class="module-icon" style="background:#dbeafe;color:#1a56db">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <span class="module-status status-active">Активен</span>
        </div>
        <div>
          <div class="module-name">Заявки в ИТ</div>
          <div class="module-desc">Система заявок в ИТ-отдел: ремонт, установка ПО, консультации. Отслеживание статусов.</div>
        </div>
        <div class="module-footer">
          <span class="module-student">Бубнов А.В.</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-primary)"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>

    </div><!-- /modules-grid -->
  </main>
</div>

<script>
// Sidebar
const sidebar=document.getElementById('sidebar');
const mainWrapper=document.getElementById('mainWrapper');
document.getElementById('sidebarToggle').addEventListener('click',()=>{
  sidebar.classList.toggle('collapsed');
  mainWrapper.classList.toggle('sidebar-collapsed');
});

// Dark mode
const html=document.documentElement;
html.setAttribute('data-theme',localStorage.getItem('theme')||'light');
document.getElementById('themeToggle').addEventListener('click',()=>{
  const next=html.getAttribute('data-theme')==='dark'?'light':'dark';
  html.setAttribute('data-theme',next);localStorage.setItem('theme',next);
});

// Login modal — инициализируем после загрузки DOM
const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

function showLogin(url, moduleName) {
  if (isLoggedIn) {
    window.location.href = url;
    return;
  }
  const modal = document.getElementById('loginModal');
  document.getElementById('loginRedirect').value = url;
  if (moduleName) {
    document.getElementById('loginModalSub').textContent = 'Для доступа к «' + moduleName + '» войдите в систему';
  }
  modal.classList.add('open');
  setTimeout(() => modal.querySelector('input[name="username"]').focus(), 100);
}

function closeLogin() {
  document.getElementById('loginModal').classList.remove('open');
}

function handleCardClick(el, e) {
  e.preventDefault();
  const url = el.getAttribute('data-href');
  const name = el.querySelector('.module-name')?.textContent?.trim() || '';
  showLogin(url, name);
}

function handleNavClick(el, e) {
  e.preventDefault();
  const url = el.getAttribute('data-href');
  const name = el.querySelector('span')?.textContent?.trim() || '';
  showLogin(url, name);
}

document.addEventListener('DOMContentLoaded', () => {
  // Открываем форму входа если пришли после logout или есть ?login=1
  const urlParams = new URLSearchParams(window.location.search);
  if (!isLoggedIn && (urlParams.get('login') === '1' || urlParams.get('redirect'))) {
    const modal = document.getElementById('loginModal');
    if (modal) modal.classList.add('open');
  }

  const modal = document.getElementById('loginModal');
  if (!modal) return;

  // Закрытие по клику на фон
  modal.addEventListener('click', e => {
    if (e.target === modal) closeLogin();
  });

  // Закрытие по Esc
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLogin();
  });

  <?php if ($loginError): ?>
  modal.classList.add('open');
  <?php endif ?>

  <?php if (!$isLoggedIn && !empty($redirectTo)): ?>
  showLogin('<?= htmlspecialchars($redirectTo) ?>');
  <?php endif ?>
});
</script>

<!-- ══ LOGIN MODAL ══ -->
<div class="login-modal-overlay" id="loginModal">
  <div class="login-modal">
    <button class="login-close" onclick="closeLogin()" title="Закрыть">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    <div class="login-modal-logo">
      <svg width="36" height="36" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#1a56db"/>
        <text x="16" y="22" text-anchor="middle" font-family="Montserrat,sans-serif" font-weight="700" font-size="13" fill="white">СП</text>
      </svg>
    </div>
    <div class="login-modal-title">Вход в СВГТК Портал</div>
    <div class="login-modal-sub" id="loginModalSub">Введите данные для входа</div>

    <?php if ($loginError): ?>
    <div class="login-error">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($loginError) ?>
    </div>
    <?php endif ?>

    <form method="POST" id="loginForm">
      <input type="hidden" name="redirect" id="loginRedirect" value="<?= htmlspecialchars($redirectTo) ?>">

      <div class="login-form-group">
        <label class="login-form-label">Логин</label>
        <input type="text" name="username" class="login-form-input" placeholder="Введите логин" required autofocus>
      </div>

      <div class="login-form-group">
        <label class="login-form-label">Пароль</label>
        <input type="password" name="password" class="login-form-input" placeholder="Введите пароль" required>
      </div>

      <button type="submit" class="login-btn">Войти</button>
    </form>
  </div>
</div>

</body>
</html>