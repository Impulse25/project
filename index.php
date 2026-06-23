<?php
// httpdocs/index.php — Главная портала + форма входа
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/requests/includes/auth.php';
require_once __DIR__ . '/requests/includes/language.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $_SESSION['full_name'] ?? '';
$userRole   = $_SESSION['role'] ?? '';
$nameParts  = explode(' ', trim($userName));
$initials   = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p,0,1)), array_slice($nameParts,0,2)));

$loginError = '';
$redirectTo = $_GET['redirect'] ?? '';
if (preg_match('~^([a-z][a-z0-9+.\-]*:)?//~i', $redirectTo)) {
    $redirectTo = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirectTo = $_POST['redirect'] ?? '';
    if (preg_match('~^([a-z][a-z0-9+.\-]*:)?//~i', $redirectTo)) { $redirectTo = ''; }
    if (!empty($username) && !empty($password)) {
        if (login($pdo, $username, $password)) {
            header('Location: ' . (!empty($redirectTo) ? $redirectTo : '/'));
            redirectToDashboard();
            exit();
        } else {
            $loginError = 'Неверный логин или пароль';
        }
    } else {
        $loginError = 'Введите логин и пароль';
    }
}

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
  background:var(--color-bg);min-height:100dvh;display:flex;flex-direction:column;line-height:1.6}
a{color:inherit;text-decoration:none}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}

/* ── Topbar ── */
.topbar{height:56px;background:var(--color-surface);border-bottom:1px solid var(--color-divider);
  display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;
  position:sticky;top:0;z-index:50;gap:1rem}
.topbar-brand{display:flex;align-items:center;gap:.75rem}
.topbar-logo{width:32px;height:32px;border-radius:8px;background:#1a56db;
  display:flex;align-items:center;justify-content:center;flex-shrink:0}
.topbar-logo-text{font-family:var(--font-display);font-size:.8rem;font-weight:700;color:#fff}
.topbar-title{font-family:var(--font-display);font-size:1.0625rem;font-weight:700;color:var(--color-primary)}
.topbar-sub{font-size:.8125rem;color:var(--color-text-muted)}
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

/* ── Page ── */
.page-content{flex:1;padding:1.5rem;max-width:1400px;margin:0 auto;width:100%}

/* ── Hero ── */
.hero{background:linear-gradient(135deg,#1a56db 0%,#1e40af 100%);
  border-radius:var(--radius-xl);padding:2rem 2.5rem;margin-bottom:1.75rem;
  color:#fff;display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:1.5rem;box-shadow:var(--shadow-lg)}
.hero-title{font-family:var(--font-display);font-size:clamp(1.25rem,2.5vw,1.75rem);
  font-weight:700;margin-bottom:.375rem}
.hero-sub{font-size:.9375rem;opacity:.85;max-width:480px;line-height:1.5}
.hero-badge{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);
  padding:.375rem 1rem;border-radius:var(--radius-full);font-size:.875rem;font-weight:600;
  white-space:nowrap;backdrop-filter:blur(4px)}

/* ── Portal layout ── */
.portal-layout{display:flex;gap:1.5rem;align-items:flex-start}
.portal-main{flex:1;min-width:0}
.portal-aside{width:288px;flex-shrink:0;display:flex;flex-direction:column;gap:1rem}

/* ── Section title ── */
.section-title{font-family:var(--font-display);font-size:1rem;font-weight:700;
  color:var(--color-text);margin-bottom:1rem}

/* ── Module cards ── */
.modules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem}
.module-card{background:var(--color-surface);border:1px solid var(--color-border);
  border-radius:var(--radius-xl);padding:1.25rem;
  transition:box-shadow var(--transition),transform var(--transition),border-color var(--transition);
  box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:.875rem;
  text-decoration:none;color:inherit;position:relative;overflow:hidden}
.module-card:hover{box-shadow:var(--shadow-lg);transform:translateY(-2px);border-color:var(--color-primary-highlight)}
.module-card-accent{position:absolute;top:0;left:0;right:0;height:4px;border-radius:var(--radius-xl) var(--radius-xl) 0 0}
.module-icon{width:44px;height:44px;border-radius:.75rem;display:flex;
  align-items:center;justify-content:center;flex-shrink:0}
.module-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem}
.module-name{font-family:var(--font-display);font-size:.9375rem;font-weight:700;
  color:var(--color-text);margin-bottom:.25rem;line-height:1.3}
.module-desc{font-size:.8125rem;color:var(--color-text-muted);line-height:1.45}
.module-status{display:inline-flex;align-items:center;
  font-size:.7rem;font-weight:600;padding:2px 9px;border-radius:var(--radius-full);white-space:nowrap}
.status-active{background:var(--color-success-highlight);color:var(--color-success)}
.status-dev{background:var(--color-warning-highlight);color:var(--color-warning)}
.status-done{background:#dbeafe;color:#1a56db}
.module-arrow{margin-top:auto;display:flex;justify-content:flex-end;color:var(--color-text-faint)}

/* ── Aside cards ── */
.aside-card{background:var(--color-surface);border:1px solid var(--color-border);
  border-radius:var(--radius-xl);padding:1.25rem;box-shadow:var(--shadow-sm)}
.aside-card-header{display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;
  padding-bottom:.75rem;border-bottom:1px solid var(--color-divider)}
.aside-card-icon{width:32px;height:32px;border-radius:.5rem;display:flex;
  align-items:center;justify-content:center;flex-shrink:0}
.aside-card-title{font-family:var(--font-display);font-size:.9375rem;font-weight:700;color:var(--color-text)}
.aside-card-date{font-size:.75rem;color:var(--color-text-faint);margin-top:1px}

/* Student list */
.student-item{display:flex;align-items:center;justify-content:space-between;
  padding:.5rem 0;border-bottom:1px solid var(--color-divider);gap:.5rem}
.student-item:last-child{border-bottom:none;padding-bottom:0}
.student-module{font-size:.75rem;color:var(--color-text-faint)}
.student-name{font-size:.8125rem;font-weight:500;color:var(--color-text)}
.student-ver{font-size:.7rem;color:var(--color-primary);background:var(--color-primary-highlight);
  padding:1px 6px;border-radius:var(--radius-full);margin-left:.25rem}
.aside-done-badge{margin-top:.875rem;display:flex;align-items:center;justify-content:center;
  gap:.375rem;padding:.5rem;border-radius:var(--radius-md);
  background:var(--color-success-highlight);color:var(--color-success);
  font-size:.8125rem;font-weight:600}

/* Update list */
.update-item{display:flex;align-items:flex-start;gap:.5rem;
  padding:.4rem 0;border-bottom:1px solid var(--color-divider);
  font-size:.8125rem;color:var(--color-text-muted);line-height:1.4}
.update-item:last-child{border-bottom:none;padding-bottom:0}
.update-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;margin-top:.35rem}
.dot-blue{background:var(--color-primary)}
.dot-red{background:var(--color-error)}
.aside-author{display:flex;align-items:center;justify-content:flex-end;gap:.375rem;
  margin-top:.875rem;padding-top:.75rem;border-top:1px solid var(--color-divider);
  font-size:.75rem;color:var(--color-text-faint)}
.aside-author strong{color:var(--color-text-muted);font-weight:600}

/* ── Login Modal ── */
.login-modal-overlay{display:none;position:fixed;inset:0;
  background:rgba(15,23,42,.6);z-index:500;align-items:center;justify-content:center;
  backdrop-filter:blur(4px);animation:fadeIn .2s ease}
.login-modal-overlay.open{display:flex}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.login-modal{background:var(--color-surface);border-radius:var(--radius-xl);
  padding:2.5rem;width:90%;max-width:400px;box-shadow:var(--shadow-lg);
  border:1px solid var(--color-border);animation:slideUp .25s ease;position:relative}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
.login-modal-title{font-family:var(--font-display);font-size:1.25rem;font-weight:700;
  color:var(--color-text);text-align:center;margin-bottom:.25rem}
.login-modal-sub{font-size:.875rem;color:var(--color-text-muted);text-align:center;margin-bottom:1.75rem}
.login-form-group{display:flex;flex-direction:column;gap:.375rem;margin-bottom:1rem}
.login-form-label{font-size:.8125rem;font-weight:500;color:var(--color-text-muted)}
.login-form-input{width:100%;border:1.5px solid var(--color-border);border-radius:var(--radius-md);
  padding:.625rem .875rem;font:inherit;font-size:.9375rem;color:var(--color-text);
  background:var(--color-surface);transition:border-color var(--transition),box-shadow var(--transition)}
.login-form-input:focus{outline:none;border-color:var(--color-primary);
  box-shadow:0 0 0 3px rgba(26,86,219,.15)}
.login-error{background:var(--color-error-highlight);color:var(--color-error);
  border:1px solid #fca5a5;border-radius:var(--radius-md);
  padding:.625rem .875rem;font-size:.875rem;margin-bottom:1rem}
.login-btn{width:100%;padding:.75rem;background:var(--color-primary);color:#fff;
  border:none;border-radius:var(--radius-md);font-size:.9375rem;font-weight:600;
  cursor:pointer;font-family:inherit;transition:background var(--transition);margin-top:.5rem}
.login-btn:hover{background:var(--color-primary-hover)}
.login-close{position:absolute;top:1rem;right:1rem;width:32px;height:32px;
  display:flex;align-items:center;justify-content:center;
  border-radius:var(--radius-md);color:var(--color-text-muted);cursor:pointer;
  transition:background var(--transition)}
.login-close:hover{background:var(--color-surface-offset)}

@media(max-width:1024px){
  .portal-layout{flex-direction:column}
  .portal-aside{width:100%}
}
@media(max-width:640px){
  .hero{padding:1.25rem 1.5rem}
  .modules-grid{grid-template-columns:1fr}
  .page-content{padding:1rem}
}
  </style>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<header class="topbar">
  <div class="topbar-brand">
    <div class="topbar-logo">
      <span class="topbar-logo-text">СП</span>
    </div>
    <div>
      <div class="topbar-title">СВГТК Портал</div>
      <div class="topbar-sub">Информационная система колледжа</div>
    </div>
  </div>
  <div class="topbar-right">
    <button class="theme-toggle" id="themeToggle" title="Тема">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <?php if($isLoggedIn): ?>
      <div class="user-avatar" title="<?= htmlspecialchars($userName) ?>"><?= $initials ?></div>
      <span style="width:1px;height:20px;background:var(--color-divider);flex-shrink:0"></span>
      <a href="/requests/logout.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Выход
      </a>
    <?php else: ?>
      <button class="btn btn-primary btn-sm" onclick="showLogin('/','')" >Войти</button>
    <?php endif ?>
  </div>
</header>

<!-- ══ MAIN ══ -->
<main class="page-content">

  <!-- Hero -->
  <div class="hero">
    <div>
      <div class="hero-title">СВГТК Портал — Информационная система</div>
      <div class="hero-sub">Учебный процесс, посещаемость, достижения, УМР, HR-аналитика и заявки в ИТ-отдел колледжа</div>
    </div>
    <div class="hero-badge">2025 / 2026</div>
  </div>

  <!-- Two-column layout -->
  <div class="portal-layout">

    <!-- LEFT: Modules -->
    <div class="portal-main">
      <div class="section-title">Модули портала</div>
      <div class="modules-grid">

        <!-- Учебный процесс -->
        <a href="#" data-href="/edu/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#1a56db"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#dbeafe;color:#1a56db">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
            </div>
            <span class="module-status status-done">Завершено</span>
          </div>
          <div>
            <div class="module-name">Учебный процесс</div>
            <div class="module-desc">РУПл, ведомости, личная карточка, дипломная книга, выгрузка по критериям</div>
          </div>
          <div class="module-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

        <!-- Посещаемость -->
        <a href="#" data-href="/attendance/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#16a34a"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#dcfce7;color:#16a34a">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <span class="module-status status-done">Завершено</span>
          </div>
          <div>
            <div class="module-name">Посещаемость</div>
            <div class="module-desc">Рапортички, учёт часов, справки, выгрузка Excel по критериям</div>
          </div>
          <div class="module-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

        <!-- QR -->
        <a href="#" data-href="/qr/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#0891b2"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#cffafe;color:#0891b2">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3z"/><path d="M17 17h3v3h-3z"/><path d="M14 20h3"/><path d="M20 14v3"/></svg>
            </div>
            <span class="module-status status-done">Завершено</span>
          </div>
          <div>
            <div class="module-name">QR-посещаемость</div>
            <div class="module-desc">Отметка входа/выхода через QR-код, журнал событий</div>
          </div>
          <div class="module-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

        <!-- Достижения -->
        <a href="#" data-href="/achievements/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#ca8a04"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#fef9c3;color:#ca8a04">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
            </div>
            <span class="module-status status-done">Завершено</span>
          </div>
          <div>
            <div class="module-name">Достижения</div>
            <div class="module-desc">Сертификаты, конкурсы, выгрузка по критериям для студентов и преподавателей</div>
          </div>
          <div class="module-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

        <!-- УМР -->
        <a href="#" data-href="/umr/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#7c3aed"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#ede9fe;color:#7c3aed">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <span class="module-status status-done">Завершено</span>
          </div>
          <div>
            <div class="module-name">УМР</div>
            <div class="module-desc">Рабочие программы, тарификация, журнал регистрации, нагрузка</div>
          </div>
          <div class="module-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

        <!-- HR -->
        <a href="#" data-href="/hr/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#db2777"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#fce7f3;color:#db2777">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <span class="module-status status-done">Завершено</span>
          </div>
          <div>
            <div class="module-name">HR-аналитика</div>
            <div class="module-desc">Трудоустройство выпускников, справки, визуализация по критериям</div>
          </div>
          <div class="module-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

        <!-- Аналитика -->
        <a href="#" data-href="/analytics/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#0284c7"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#e0f2fe;color:#0284c7">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <span class="module-status status-done">Завершено</span>
          </div>
          <div>
            <div class="module-name">Аналитика</div>
            <div class="module-desc">Сводные отчёты, графики успеваемости, посещаемости и активности</div>
          </div>
          <div class="module-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

        <!-- Заявки в ИТ -->
        <a href="#" data-href="/requests/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#1a56db"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#dbeafe;color:#1a56db">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="module-status status-active">Активен</span>
          </div>
          <div>
            <div class="module-name">Заявки в ИТ</div>
            <div class="module-desc">Ремонт, установка ПО, консультации. Отслеживание статусов</div>
          </div>
          <div class="module-arrow" style="color:var(--color-primary)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

        <!-- Расписание -->
        <a href="#" data-href="/schedule/" onclick="handleCardClick(this,event)" class="module-card">
          <div class="module-card-accent" style="background:#0d9488"></div>
          <div class="module-top">
            <div class="module-icon" style="background:#ccfbf1;color:#0d9488">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <span class="module-status status-dev">В разработке</span>
          </div>
          <div>
            <div class="module-name">Расписание</div>
            <div class="module-desc">Просмотр и редактирование расписания, замены, уведомления · июль–август 2026</div>
          </div>
          <div class="module-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </a>

      </div><!-- /modules-grid -->
    </div><!-- /portal-main -->

    <!-- RIGHT: Aside -->
    <aside class="portal-aside">

      <!-- Команда разработчиков -->
      <div class="aside-card">
        <div class="aside-card-header">
          <div class="aside-card-icon" style="background:#dbeafe;color:#1a56db">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div>
            <div class="aside-card-title">Команда разработчиков</div>
            <div class="aside-card-date">Группа ИС-23 · СВГТК</div>
          </div>
        </div>

        <div class="student-item">
          <div>
            <div class="student-module">Учебный процесс</div>
            <div class="student-name">Ломакин Александр <span class="student-ver">v1.0</span></div>
          </div>
        </div>
        <div class="student-item">
          <div>
            <div class="student-module">Посещаемость</div>
            <div class="student-name">Базарбаев Рахат <span class="student-ver">v1.0</span></div>
          </div>
        </div>
        <div class="student-item">
          <div>
            <div class="student-module">QR-посещаемость</div>
            <div class="student-name">Буланков Андрей <span class="student-ver">v1.0</span></div>
          </div>
        </div>
        <div class="student-item">
          <div>
            <div class="student-module">Достижения</div>
            <div class="student-name">Бекболсынов Алмас <span class="student-ver">v1.0</span></div>
          </div>
        </div>
        <div class="student-item">
          <div>
            <div class="student-module">УМР</div>
            <div class="student-name">Голоднев Евгений <span class="student-ver">v1.0</span></div>
          </div>
        </div>
        <div class="student-item">
          <div>
            <div class="student-module">HR-аналитика</div>
            <div class="student-name">Ворожейкин Данил <span class="student-ver">v1.0</span></div>
          </div>
        </div>
        <div class="student-item">
          <div>
            <div class="student-module">Аналитика</div>
            <div class="student-name">Пушкарев Артур <span class="student-ver">v1.0</span></div>
          </div>
        </div>

        <div class="aside-done-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Разработка завершена · 23.06.2026
        </div>
      </div>

      <!-- Обновления 23.06.2026 -->
      <div class="aside-card">
        <div class="aside-card-header">
          <div class="aside-card-icon" style="background:#dcfce7;color:#16a34a">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-4.66"/></svg>
          </div>
          <div>
            <div class="aside-card-title">Обновления портала</div>
            <div class="aside-card-date">23.06.2026</div>
          </div>
        </div>

        <div class="update-item">
          <div class="update-dot dot-blue"></div>
          <span>Единый сайдбар для всех модулей (portal_sidebar.php)</span>
        </div>
        <div class="update-item">
          <div class="update-dot dot-blue"></div>
          <span>Унификация навигации: роли, мобильная адаптация</span>
        </div>
        <div class="update-item">
          <div class="update-dot dot-blue"></div>
          <span>Исправление суб-навигации УМР (pill-кнопки)</span>
        </div>
        <div class="update-item">
          <div class="update-dot dot-blue"></div>
          <span>Исправление ошибки $role в модуле Аналитика</span>
        </div>
        <div class="update-item">
          <div class="update-dot dot-red"></div>
          <span>Безопасность: авторизация в QR-модуле</span>
        </div>
        <div class="update-item">
          <div class="update-dot dot-red"></div>
          <span>Безопасность: CSRF-защита в модуле Заявки</span>
        </div>
        <div class="update-item">
          <div class="update-dot dot-red"></div>
          <span>Безопасность: доступ к медицинским документам</span>
        </div>
        <div class="update-item">
          <div class="update-dot dot-red"></div>
          <span>Безопасность: защита от Open Redirect</span>
        </div>

        <div class="aside-author">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
          Автор: <strong>Бубнов Андрей</strong>
        </div>
      </div>

    </aside><!-- /portal-aside -->

  </div><!-- /portal-layout -->

</main>

<!-- ══ LOGIN MODAL ══ -->
<div class="login-modal-overlay" id="loginModal">
  <div class="login-modal">
    <button class="login-close" onclick="closeLogin()">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div style="display:flex;align-items:center;justify-content:center;gap:.75rem;margin-bottom:1.5rem">
      <div style="width:40px;height:40px;border-radius:10px;background:#1a56db;display:flex;align-items:center;justify-content:center">
        <span style="font-family:var(--font-display);font-size:.9rem;font-weight:700;color:#fff">СП</span>
      </div>
    </div>
    <div class="login-modal-title">Вход в систему</div>
    <div class="login-modal-sub" id="loginModalSub">Введите данные учётной записи</div>
    <form method="POST" action="/">
      <?php if($loginError): ?>
        <div class="login-error"><?= htmlspecialchars($loginError) ?></div>
      <?php endif ?>
      <input type="hidden" name="redirect" id="loginRedirect" value="<?= htmlspecialchars($redirectTo) ?>">
      <div class="login-form-group">
        <label class="login-form-label">Логин</label>
        <input class="login-form-input" type="text" name="username" autocomplete="username" required>
      </div>
      <div class="login-form-group">
        <label class="login-form-label">Пароль</label>
        <input class="login-form-input" type="password" name="password" autocomplete="current-password" required>
      </div>
      <button class="login-btn" type="submit">Войти</button>
    </form>
  </div>
</div>

<script>
const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

function showLogin(url, moduleName) {
  if (isLoggedIn) { window.location.href = url; return; }
  const modal = document.getElementById('loginModal');
  document.getElementById('loginRedirect').value = url;
  document.getElementById('loginModalSub').textContent =
    moduleName ? 'Для доступа к «' + moduleName + '» войдите в систему' : 'Введите данные учётной записи';
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

document.addEventListener('DOMContentLoaded', () => {
  // Открыть форму входа автоматически если ?login=1 или ?redirect=...
  const urlParams = new URLSearchParams(window.location.search);
  if (!isLoggedIn && (urlParams.get('login') === '1' || urlParams.get('redirect'))) {
    document.getElementById('loginModal')?.classList.add('open');
  }

  // Закрытие по клику на overlay
  document.getElementById('loginModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeLogin();
  });

  // Тема
  const themeToggle = document.getElementById('themeToggle');
  const html = document.documentElement;
  const saved = localStorage.getItem('theme');
  if (saved) html.setAttribute('data-theme', saved);

  themeToggle?.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
  });
});
</script>

</body>
</html>
