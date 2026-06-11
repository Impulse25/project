<?php
/**
 * help.php — Справочная система модуля «Учёт посещаемости»
 * СВГТК Портал · Тема 2: «Разработка модуля «Учёт посещаемости»»
 *
 * Подключение к основному модулю:
 *   Добавьте в layout.php кнопку:
 *   <a href="help.php" class="btn btn-outline btn-sm" target="_blank">
 *     <svg ...>...</svg> Помощь
 *   </a>
 */

session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: /requests/login.php');
    exit;
}

$userName  = $_SESSION['full_name'] ?? 'Пользователь';
$userRole  = $_SESSION['role']      ?? 'teacher';
$isAdmin   = in_array($userRole, ['admin', 'director']);
$nameParts = explode(' ', trim($userName));
$initials  = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($nameParts, 0, 2)));

// Версия программы
define('APP_VERSION', '4.0');
define('APP_YEAR',    '2025');
define('APP_AUTHOR',  'Дипломная работа — СВГТК им. Абая Кунанбаева');
define('APP_THEME',   'Тема 2: «Разработка модуля «Учёт посещаемости» с формированием отчётности»');
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Справка — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script>
    (function(){
      var t = localStorage.getItem('theme');
      if (t) document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
  <style>
    /* ── Специфичные стили страницы справки ── */
    .help-layout {
      display: grid;
      grid-template-columns: 260px 1fr;
      gap: var(--space-6);
      max-width: 1100px;
      margin: 0 auto;
    }
    @media (max-width: 768px) {
      .help-layout { grid-template-columns: 1fr; }
      .help-toc    { display: none; }
    }

    /* Содержание (TOC) */
    .help-toc {
      position: sticky;
      top: calc(var(--topbar-height) + var(--space-6));
      height: fit-content;
      background: var(--color-surface);
      border: 1px solid var(--color-divider);
      border-radius: var(--radius-xl);
      padding: var(--space-5);
      font-size: var(--text-xs);
    }
    .help-toc-title {
      font-family: var(--font-display);
      font-size: var(--text-sm);
      font-weight: 700;
      color: var(--color-text);
      margin-bottom: var(--space-3);
      padding-bottom: var(--space-3);
      border-bottom: 1px solid var(--color-divider);
    }
    .toc-group { margin-bottom: var(--space-2); }
    .toc-group-label {
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--color-text-faint);
      font-size: 10px;
      padding: var(--space-2) 0 var(--space-1);
    }
    .toc-link {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-1) var(--space-2);
      border-radius: var(--radius-md);
      color: var(--color-text-muted);
      font-size: var(--text-xs);
      transition: background var(--transition), color var(--transition);
      cursor: pointer;
    }
    .toc-link:hover, .toc-link.active {
      background: var(--color-primary-highlight);
      color: var(--color-primary);
      font-weight: 500;
    }
    .toc-dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: currentColor;
      flex-shrink: 0;
      opacity: .5;
    }

    /* Основной контент */
    .help-content { min-width: 0; }

    /* Секции */
    .help-section {
      background: var(--color-surface);
      border: 1px solid var(--color-divider);
      border-radius: var(--radius-xl);
      margin-bottom: var(--space-6);
      overflow: hidden;
    }
    .help-section-header {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      padding: var(--space-5) var(--space-6);
      border-bottom: 1px solid var(--color-divider);
      background: var(--color-surface-2);
    }
    .help-section-icon {
      width: 40px; height: 40px;
      border-radius: var(--radius-lg);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .help-section-title {
      font-family: var(--font-display);
      font-size: var(--text-base);
      font-weight: 700;
      color: var(--color-text);
    }
    .help-section-sub {
      font-size: var(--text-xs);
      color: var(--color-text-muted);
      margin-top: 2px;
    }
    .help-section-body { padding: var(--space-6); }

    /* FAQ аккордеон */
    .faq-item {
      border: 1px solid var(--color-divider);
      border-radius: var(--radius-lg);
      margin-bottom: var(--space-3);
      overflow: hidden;
      transition: box-shadow var(--transition);
    }
    .faq-item:last-child { margin-bottom: 0; }
    .faq-item:hover { box-shadow: var(--shadow-sm); }
    .faq-q {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: var(--space-3);
      padding: var(--space-4) var(--space-5);
      cursor: pointer;
      user-select: none;
      font-weight: 500;
      font-size: var(--text-sm);
      color: var(--color-text);
      transition: background var(--transition);
    }
    .faq-q:hover { background: var(--color-surface-2); }
    .faq-q.open   { background: var(--color-primary-highlight); color: var(--color-primary); }
    .faq-chevron {
      width: 18px; height: 18px;
      flex-shrink: 0;
      transition: transform var(--transition);
    }
    .faq-q.open .faq-chevron { transform: rotate(180deg); }
    .faq-a {
      display: none;
      padding: 0 var(--space-5) var(--space-4);
      font-size: var(--text-sm);
      color: var(--color-text-muted);
      line-height: 1.7;
      border-top: 1px solid var(--color-divider);
    }
    .faq-a.open  { display: block; }
    .faq-a ul    { padding-left: var(--space-5); margin-top: var(--space-2); }
    .faq-a li    { margin-bottom: var(--space-1); }
    .faq-a strong { color: var(--color-text); font-weight: 600; }
    .faq-a .badge-demo {
      display: inline-flex; align-items: center;
      padding: 2px 8px; border-radius: var(--radius-full);
      font-size: 11px; font-weight: 600;
    }

    /* О программе */
    .about-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: var(--space-4);
      margin-top: var(--space-5);
    }
    .about-card {
      background: var(--color-surface-2);
      border: 1px solid var(--color-divider);
      border-radius: var(--radius-lg);
      padding: var(--space-4);
    }
    .about-card-label {
      font-size: var(--text-xs);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--color-text-faint);
      margin-bottom: var(--space-2);
    }
    .about-card-value {
      font-size: var(--text-sm);
      font-weight: 500;
      color: var(--color-text);
    }

    /* Стек технологий */
    .tech-list {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-2);
      margin-top: var(--space-3);
    }
    .tech-tag {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-1) var(--space-3);
      background: var(--color-surface-offset);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-full);
      font-size: var(--text-xs);
      font-weight: 500;
      color: var(--color-text-muted);
    }
    .tech-dot { width: 7px; height: 7px; border-radius: 50%; }

    /* Ролевые карточки */
    .role-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: var(--space-4);
    }
    .role-card {
      border: 1px solid var(--color-divider);
      border-radius: var(--radius-lg);
      padding: var(--space-4);
      background: var(--color-surface-2);
    }
    .role-name {
      font-weight: 600;
      font-size: var(--text-sm);
      margin-bottom: var(--space-2);
      color: var(--color-text);
    }
    .role-perm {
      font-size: var(--text-xs);
      color: var(--color-text-muted);
      line-height: 1.6;
    }
    .role-perm li { list-style: none; padding-left: 0; margin-bottom: 2px; }
    .role-perm li::before { content: '✓ '; color: var(--color-success); font-weight: 700; }

    /* Горячие клавиши */
    kbd {
      display: inline-block;
      padding: 2px 7px;
      border-radius: var(--radius-sm);
      background: var(--color-surface-offset);
      border: 1px solid var(--color-border);
      font-family: monospace;
      font-size: 11px;
      color: var(--color-text);
      box-shadow: 0 1px 0 var(--color-border);
    }
    .shortcut-table { width: 100%; border-collapse: collapse; font-size: var(--text-xs); }
    .shortcut-table td { padding: var(--space-2) var(--space-3); border-bottom: 1px solid var(--color-divider); }
    .shortcut-table tr:last-child td { border-bottom: none; }
    .shortcut-table td:first-child { white-space: nowrap; width: 180px; }

    /* Статусы-легенда */
    .status-legend {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: var(--space-3);
    }
    .status-legend-item {
      display: flex;
      align-items: flex-start;
      gap: var(--space-3);
      padding: var(--space-3) var(--space-4);
      background: var(--color-surface-2);
      border-radius: var(--radius-lg);
      border: 1px solid var(--color-divider);
    }

    /* Кнопка «назад» */
    .help-back-btn {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      color: var(--color-text-muted);
      font-size: var(--text-sm);
      font-weight: 500;
      padding: var(--space-2) 0;
      transition: color var(--transition);
      margin-bottom: var(--space-4);
    }
    .help-back-btn:hover { color: var(--color-primary); }

    /* Поиск */
    .help-search-wrap {
      position: relative;
      margin-bottom: var(--space-6);
    }
    .help-search {
      width: 100%;
      padding: var(--space-3) var(--space-5) var(--space-3) 42px;
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-xl);
      font-size: var(--text-sm);
      color: var(--color-text);
      transition: border-color var(--transition), box-shadow var(--transition);
    }
    .help-search:focus {
      outline: none;
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px var(--color-primary-highlight);
    }
    .help-search-icon {
      position: absolute;
      left: 14px; top: 50%;
      transform: translateY(-50%);
      color: var(--color-text-faint);
      pointer-events: none;
    }
    .search-no-results {
      display: none;
      text-align: center;
      padding: var(--space-8);
      color: var(--color-text-muted);
      font-size: var(--text-sm);
    }

    /* Hero-шапка */
    .help-hero {
      background: linear-gradient(135deg, var(--color-primary) 0%, #2563eb 100%);
      border-radius: var(--radius-xl);
      padding: var(--space-8) var(--space-8);
      margin-bottom: var(--space-6);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: var(--space-6);
      flex-wrap: wrap;
    }
    .help-hero-title {
      font-family: var(--font-display);
      font-size: clamp(1.3rem, 2vw, 1.75rem);
      font-weight: 700;
      margin-bottom: var(--space-2);
    }
    .help-hero-sub { opacity: .85; font-size: var(--text-sm); }
    .help-hero-badge {
      background: rgba(255,255,255,.15);
      border: 1px solid rgba(255,255,255,.25);
      border-radius: var(--radius-full);
      padding: var(--space-1) var(--space-3);
      font-size: var(--text-xs);
      font-weight: 600;
      margin-top: var(--space-2);
      display: inline-block;
    }
    .help-hero-icon {
      width: 80px; height: 80px;
      background: rgba(255,255,255,.15);
      border-radius: var(--radius-xl);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    /* Шаги */
    .steps-list { counter-reset: step-counter; }
    .step-item {
      display: flex;
      gap: var(--space-4);
      margin-bottom: var(--space-4);
      padding: var(--space-4) var(--space-5);
      background: var(--color-surface-2);
      border-radius: var(--radius-lg);
      border: 1px solid var(--color-divider);
    }
    .step-num {
      width: 28px; height: 28px;
      background: var(--color-primary);
      color: #fff;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: var(--text-xs);
      font-weight: 700;
      flex-shrink: 0;
      margin-top: 2px;
    }
    .step-body { min-width: 0; }
    .step-title {
      font-weight: 600;
      font-size: var(--text-sm);
      color: var(--color-text);
      margin-bottom: var(--space-1);
    }
    .step-desc {
      font-size: var(--text-xs);
      color: var(--color-text-muted);
      line-height: 1.6;
    }
  </style>
</head>
<body>

<!-- ═══════════════════════ SIDEBAR ═══════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#1a56db"/>
        <text x="16" y="22" text-anchor="middle" font-family="Montserrat,sans-serif" font-weight="700" font-size="13" fill="white">СП</text>
      </svg>
      <div class="logo-text">
        <span class="logo-title">СВГТК Портал</span>
        <span class="logo-sub">Справочная система</span>
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Навигация</div>
    <a href="../" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Главная</span>
    </a>
    <a href="./index.php" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <span>Посещаемость</span>
    </a>
    <a href="./help.php" class="nav-item active">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Справка</span>
    </a>
    <div class="nav-section-label" style="margin-top:1rem">Разделы справки</div>
    <a class="nav-item toc-link" onclick="scrollTo('sec-start')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      <span>Начало работы</span>
    </a>
    <a class="nav-item toc-link" onclick="scrollTo('sec-journal')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span>Журнал посещаемости</span>
    </a>
    <a class="nav-item toc-link" onclick="scrollTo('sec-docs')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span>Справки и документы</span>
    </a>
    <a class="nav-item toc-link" onclick="scrollTo('sec-report')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Рапортичка и экспорт</span>
    </a>
    <a class="nav-item toc-link" onclick="scrollTo('sec-roles')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      <span>Роли пользователей</span>
    </a>
    <a class="nav-item toc-link" onclick="scrollTo('sec-about')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>О программе</span>
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="college-info">
      <span>СВГТК им. Абая Кунанбаева</span>
      <span>г. Сарань</span>
    </div>
  </div>
</aside>

<!-- ═══════════════════════ MAIN ═══════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">

  <header class="topbar">
    <div class="topbar-left">
      <button class="mobile-menu-btn" id="mobileMenuBtn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="breadcrumb">
        <span class="breadcrumb-root"><a href="../" style="color:inherit">СВГТК</a></span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <a href="./index.php" class="breadcrumb-root">Посещаемость</a>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="breadcrumb-current">Справка</span>
      </div>
    </div>
    <div class="topbar-right">
      <a href="./index.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Вернуться в модуль
      </a>
      <div class="user-avatar" title="<?= htmlspecialchars($userName) ?>"><?= $initials ?></div>
      <button class="theme-toggle" id="themeToggle">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
    </div>
  </header>

  <main class="page-content">

    <!-- Hero -->
    <div class="help-hero">
      <div>
        <div class="help-hero-title">Справочная система</div>
        <div class="help-hero-sub">Модуль «Учёт посещаемости» — СВГТК Портал</div>
        <span class="help-hero-badge">Версия <?= APP_VERSION ?> · <?= APP_YEAR ?></span>
      </div>
      <div class="help-hero-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
    </div>

    <!-- Поиск -->
    <div class="help-search-wrap">
      <svg class="help-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="helpSearch" class="help-search" placeholder="Поиск по справке...">
    </div>
    <div class="search-no-results" id="searchNoResults">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto var(--space-3)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Ничего не найдено по запросу
    </div>

    <div class="help-layout">

      <!-- TOC (десктоп) -->
      <aside class="help-toc">
        <div class="help-toc-title">Содержание</div>
        <div class="toc-group">
          <div class="toc-group-label">Общее</div>
          <div class="toc-link" onclick="scrollTo('sec-start')"><span class="toc-dot"></span>Начало работы</div>
          <div class="toc-link" onclick="scrollTo('sec-roles')"><span class="toc-dot"></span>Роли пользователей</div>
        </div>
        <div class="toc-group">
          <div class="toc-group-label">Функции</div>
          <div class="toc-link" onclick="scrollTo('sec-journal')"><span class="toc-dot"></span>Журнал посещаемости</div>
          <div class="toc-link" onclick="scrollTo('sec-docs')"><span class="toc-dot"></span>Справки и документы</div>
          <div class="toc-link" onclick="scrollTo('sec-report')"><span class="toc-dot"></span>Рапортичка и экспорт</div>
          <div class="toc-link" onclick="scrollTo('sec-analytics')"><span class="toc-dot"></span>Аналитика</div>
        </div>
        <div class="toc-group">
          <div class="toc-group-label">О системе</div>
          <div class="toc-link" onclick="scrollTo('sec-about')"><span class="toc-dot"></span>О программе</div>
          <div class="toc-link" onclick="scrollTo('sec-shortcuts')"><span class="toc-dot"></span>Горячие клавиши</div>
        </div>
      </aside>

      <!-- Контент -->
      <div class="help-content" id="helpContent">

        <!-- ──────────────── НАЧАЛО РАБОТЫ ──────────────── -->
        <section class="help-section" id="sec-start">
          <div class="help-section-header">
            <div class="help-section-icon" style="background:var(--color-primary-highlight);color:var(--color-primary)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </div>
            <div>
              <div class="help-section-title">Начало работы</div>
              <div class="help-section-sub">Вход в систему и первые шаги</div>
            </div>
          </div>
          <div class="help-section-body">

            <div class="steps-list">
              <div class="step-item">
                <div class="step-num">1</div>
                <div class="step-body">
                  <div class="step-title">Войдите в систему</div>
                  <div class="step-desc">Откройте страницу входа по адресу <strong>/requests/login.php</strong>. Введите логин и пароль, выданные администратором.</div>
                </div>
              </div>
              <div class="step-item">
                <div class="step-num">2</div>
                <div class="step-body">
                  <div class="step-title">Перейдите в модуль «Посещаемость»</div>
                  <div class="step-desc">В левом меню портала выберите пункт <strong>«Посещаемость»</strong> или перейдите напрямую по адресу <strong>/attendance/</strong>.</div>
                </div>
              </div>
              <div class="step-item">
                <div class="step-num">3</div>
                <div class="step-body">
                  <div class="step-title">Выберите группу и дату</div>
                  <div class="step-desc">В панели фильтров вверху выберите нужную группу из списка и установите дату. По умолчанию загружается сегодняшняя дата.</div>
                </div>
              </div>
              <div class="step-item">
                <div class="step-num">4</div>
                <div class="step-body">
                  <div class="step-title">Отметьте посещаемость и сохраните</div>
                  <div class="step-desc">В таблице журнала для каждого студента выберите статус и при необходимости укажите часы и причину. Нажмите кнопку <strong>«Сохранить журнал»</strong>.</div>
                </div>
              </div>
            </div>

            <div class="faq-item" style="margin-top:var(--space-4)">
              <div class="faq-q" onclick="toggleFaq(this)">
                Я вижу только некоторые группы — это нормально?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                <strong>Да, это нормально.</strong> Преподаватели видят только те группы, в которых они назначены куратором. Администратор и директор видят все группы колледжа.
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Что делать если список групп пустой?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Это означает, что в базе данных за вашим аккаунтом не закреплено ни одной группы. Обратитесь к администратору, чтобы он назначил вас куратором нужной группы в разделе управления группами.
              </div>
            </div>
          </div>
        </section>

        <!-- ──────────────── ЖУРНАЛ ──────────────── -->
        <section class="help-section" id="sec-journal">
          <div class="help-section-header">
            <div class="help-section-icon" style="background:var(--color-success-highlight);color:var(--color-success)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
              <div class="help-section-title">Журнал посещаемости</div>
              <div class="help-section-sub">Вкладка «Журнал» — ежедневный учёт</div>
            </div>
          </div>
          <div class="help-section-body">

            <p style="font-size:var(--text-sm);color:var(--color-text-muted);margin-bottom:var(--space-5)">
              Журнал — основной инструмент модуля. Здесь вы отмечаете присутствие или отсутствие каждого студента группы за выбранный день.
            </p>

            <!-- Статусы -->
            <p style="font-size:var(--text-xs);font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-faint);margin-bottom:var(--space-3)">Статусы студента</p>
            <div class="status-legend" style="margin-bottom:var(--space-5)">
              <div class="status-legend-item">
                <span class="badge badge-present">Присутствует</span>
                <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Студент на занятии. Часы пропусков не считаются.</div>
              </div>
              <div class="status-legend-item">
                <span class="badge badge-absent">Отсутствует</span>
                <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Неявка без причины. Укажите количество пропущенных часов.</div>
              </div>
              <div class="status-legend-item">
                <span class="badge badge-excused">Уваж. причина</span>
                <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Отсутствие по уважительной причине (больничный, справка и т.д.).</div>
              </div>
              <div class="status-legend-item">
                <span class="badge badge-late">Опоздал</span>
                <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Студент опоздал на занятие. Можно указать часы.</div>
              </div>
            </div>

            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Как отметить сразу всех студентов одним статусом?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Над таблицей журнала находятся кнопки быстрой разметки: <strong>«Все присутствуют»</strong>, <strong>«Все отсутствуют»</strong> и т.д. Нажмите нужную — статус проставится для всего списка. После этого вы можете вручную изменить отдельных студентов.
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Как указать количество пропущенных часов?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                В строке студента, у которого выбран статус «Отсутствует» или «Уваж. причина», появляется числовое поле <strong>«Часов»</strong>. Введите значение от 0 до 8. По умолчанию система подставляет 2 часа.
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Можно ли изменить уже сохранённый журнал?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Да. Откройте нужную дату с помощью поля «Дата» в панели фильтров, внесите изменения и снова нажмите <strong>«Сохранить журнал»</strong>. Данные обновятся (INSERT … ON DUPLICATE KEY UPDATE).
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Что показывают карточки статистики над журналом?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Пять карточек отображают <strong>в режиме реального времени</strong> (без перезагрузки страницы):
                <ul>
                  <li>Общее число студентов в группе</li>
                  <li>Количество и процент присутствующих</li>
                  <li>Количество и процент отсутствующих</li>
                  <li>Количество с уважительной причиной</li>
                  <li>Количество опоздавших</li>
                </ul>
                Данные пересчитываются при каждом изменении статуса.
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Для чего нужно поле «Причина»?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Поле «Причина» связано с таблицей <code>att_absence_reasons</code> и заполняется для статусов «Отсутствует» и «Уваж. причина». Доступные причины:
                <ul>
                  <li>Больничный лист</li>
                  <li>Заявление родителей</li>
                  <li>Справка от врача</li>
                  <li>Соревнования / олимпиада</li>
                  <li>Семейные обстоятельства</li>
                  <li>Прочее (уважительная)</li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <!-- ──────────────── СПРАВКИ ──────────────── -->
        <section class="help-section" id="sec-docs">
          <div class="help-section-header">
            <div class="help-section-icon" style="background:var(--color-warning-highlight);color:var(--color-warning)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div>
              <div class="help-section-title">Справки и документы</div>
              <div class="help-section-sub">Вкладка «Документы» — загрузка и проверка справок</div>
            </div>
          </div>
          <div class="help-section-body">

            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Как загрузить справку студента?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Перейдите на вкладку <strong>«Документы»</strong>. В форме слева:
                <ul>
                  <li>Выберите студента из выпадающего списка</li>
                  <li>Укажите период (дата с — по)</li>
                  <li>Выберите вид причины из списка</li>
                  <li>Загрузите файл (PDF, JPG или PNG, не более 5 МБ) — перетащите в зону или нажмите для выбора</li>
                  <li>При желании добавьте комментарий</li>
                  <li>Нажмите <strong>«Сохранить справку»</strong></li>
                </ul>
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Какие форматы файлов допускаются?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Допустимые форматы: <strong>PDF, JPG, PNG</strong>. Максимальный размер файла — <strong>5 МБ</strong>. Тип файла проверяется по MIME-типу на сервере (не только по расширению).
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Что означают статусы справок?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                <ul>
                  <li><strong>На проверке</strong> — справка загружена, ожидает проверки куратором или администратором</li>
                  <li><strong>Принята</strong> — документ проверен и подтверждён. Статус посещаемости за период автоматически изменяется на «Уваж. причина»</li>
                  <li><strong>Отклонена</strong> — документ отклонён. В поле примечания указывается причина отказа</li>
                </ul>
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Кто может принимать и отклонять справки?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Кнопки «Принять» и «Отклонить» доступны только пользователям с ролью <strong>куратор, администратор</strong> или <strong>директор</strong>. Обычный преподаватель может только загружать справки.
              </div>
            </div>
          </div>
        </section>

        <!-- ──────────────── РАПОРТИЧКА ──────────────── -->
        <section class="help-section" id="sec-report">
          <div class="help-section-header">
            <div class="help-section-icon" style="background:#e0f2fe;color:#0284c7">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <div>
              <div class="help-section-title">Рапортичка и экспорт</div>
              <div class="help-section-sub">Вкладки «Рапортичка» и «Отчёт»</div>
            </div>
          </div>
          <div class="help-section-body">

            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Что такое «Рапортичка»?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Рапортичка — сводная ведомость посещаемости за выбранный период (неделю, месяц или произвольный диапазон). В таблице по горизонтали расположены дни месяца, по вертикали — список студентов. В каждой ячейке отображается статус присутствия за тот день. Жирным выделены итоги (количество пропущенных часов).
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Как скачать ведомость в Word (.docx)?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                На вкладке «Рапортичка» в правом верхнем углу находятся кнопки экспорта. Нажмите <strong>«Скачать DOCX»</strong> — система сформирует готовую ведомость с шапкой, таблицей студентов и итогами. Файл будет скачан автоматически.
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Как выгрузить данные в Excel (CSV)?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Нажмите кнопку <strong>«Скачать CSV»</strong>. Файл сохраняется в кодировке UTF-8 с BOM (корректно открывается в Microsoft Excel). Разделитель — точка с запятой.
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Как распечатать рапортичку?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Нажмите кнопку <strong>«Печать»</strong> — откроется диалог печати браузера с предварительным просмотром. Рекомендуется ориентация <strong>альбомная</strong>, масштаб — «Вписать в страницу».
              </div>
            </div>
          </div>
        </section>

        <!-- ──────────────── АНАЛИТИКА ──────────────── -->
        <section class="help-section" id="sec-analytics">
          <div class="help-section-header">
            <div class="help-section-icon" style="background:var(--color-gold-highlight);color:var(--color-gold)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
            </div>
            <div>
              <div class="help-section-title">Аналитика</div>
              <div class="help-section-sub">Вкладка «Аналитика» — сводные показатели</div>
            </div>
          </div>
          <div class="help-section-body">
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Что показывает вкладка «Аналитика»?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                На вкладке «Аналитика» отображаются сводные показатели группы за выбранный период:
                <ul>
                  <li>Процент посещаемости группы в целом</li>
                  <li>Топ студентов с наибольшим числом пропусков</li>
                  <li>Динамика посещаемости по дням</li>
                  <li>Разбивка пропусков по причинам</li>
                </ul>
              </div>
            </div>
            <div class="faq-item">
              <div class="faq-q" onclick="toggleFaq(this)">
                Кто видит аналитику по всем группам?
                <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div class="faq-a">
                Сводную аналитику по <strong>всем группам колледжа</strong> видят пользователи с ролью <strong>администратор</strong> или <strong>директор</strong>. Преподаватель видит аналитику только по своим группам.
              </div>
            </div>
          </div>
        </section>

        <!-- ──────────────── РОЛИ ──────────────── -->
        <section class="help-section" id="sec-roles">
          <div class="help-section-header">
            <div class="help-section-icon" style="background:var(--color-error-highlight);color:var(--color-error)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
              <div class="help-section-title">Роли пользователей</div>
              <div class="help-section-sub">Права доступа в системе</div>
            </div>
          </div>
          <div class="help-section-body">
            <div class="role-grid">
              <div class="role-card">
                <div class="role-name">
                  <span class="badge badge-present" style="margin-bottom:var(--space-2);display:inline-flex">Преподаватель</span><br>
                  teacher
                </div>
                <ul class="role-perm">
                  <li>Просмотр своих групп</li>
                  <li>Отметка посещаемости</li>
                  <li>Загрузка справок</li>
                  <li>Просмотр рапортички</li>
                  <li>Экспорт (CSV, DOCX)</li>
                </ul>
              </div>
              <div class="role-card">
                <div class="role-name">
                  <span class="badge badge-late" style="margin-bottom:var(--space-2);display:inline-flex">Администратор</span><br>
                  admin
                </div>
                <ul class="role-perm">
                  <li>Все права преподавателя</li>
                  <li>Все группы колледжа</li>
                  <li>Принятие / отклонение справок</li>
                  <li>Сводная аналитика</li>
                  <li>Управление пользователями</li>
                </ul>
              </div>
              <div class="role-card">
                <div class="role-name">
                  <span class="badge badge-absent" style="margin-bottom:var(--space-2);display:inline-flex">Директор</span><br>
                  director
                </div>
                <ul class="role-perm">
                  <li>Все права администратора</li>
                  <li>Просмотр всех отчётов</li>
                  <li>Принятие справок</li>
                  <li>Аналитика по колледжу</li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <!-- ──────────────── ГОРЯЧИЕ КЛАВИШИ ──────────────── -->
        <section class="help-section" id="sec-shortcuts">
          <div class="help-section-header">
            <div class="help-section-icon" style="background:var(--color-surface-offset);color:var(--color-text-muted)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="10" y1="12" x2="10.01" y2="12"/><line x1="14" y1="12" x2="14.01" y2="12"/></svg>
            </div>
            <div>
              <div class="help-section-title">Горячие клавиши</div>
              <div class="help-section-sub">Для быстрой работы с модулем</div>
            </div>
          </div>
          <div class="help-section-body">
            <table class="shortcut-table">
              <tr><td><kbd>Ctrl</kbd> + <kbd>S</kbd></td><td>Сохранить журнал посещаемости</td></tr>
              <tr><td><kbd>Ctrl</kbd> + <kbd>E</kbd></td><td>Экспортировать текущий вид в CSV</td></tr>
              <tr><td><kbd>?</kbd></td><td>Открыть справку (эту страницу)</td></tr>
              <tr><td><kbd>Esc</kbd></td><td>Закрыть модальное окно</td></tr>
              <tr><td><kbd>Alt</kbd> + <kbd>T</kbd></td><td>Переключить тему (светлая / тёмная)</td></tr>
              <tr><td><kbd>Tab</kbd></td><td>Переход между полями в журнале</td></tr>
            </table>
          </div>
        </section>

        <!-- ──────────────── О ПРОГРАММЕ ──────────────── -->
        <section class="help-section" id="sec-about">
          <div class="help-section-header">
            <div class="help-section-icon" style="background:var(--color-primary-highlight);color:var(--color-primary)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
              <div class="help-section-title">О программе</div>
              <div class="help-section-sub">Сведения о системе</div>
            </div>
          </div>
          <div class="help-section-body">

            <!-- Логотип / заголовок -->
            <div style="display:flex;align-items:center;gap:var(--space-4);margin-bottom:var(--space-5);padding:var(--space-5);background:var(--color-surface-2);border-radius:var(--radius-lg);border:1px solid var(--color-divider)">
              <div style="width:56px;height:56px;background:var(--color-primary);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="30" height="30" viewBox="0 0 32 32" fill="none">
                  <text x="16" y="23" text-anchor="middle" font-family="Montserrat,sans-serif" font-weight="700" font-size="13" fill="white">СП</text>
                </svg>
              </div>
              <div>
                <div style="font-family:var(--font-display);font-size:var(--text-base);font-weight:700;color:var(--color-text)">СВГТК Портал</div>
                <div style="font-size:var(--text-xs);color:var(--color-text-muted);margin-top:2px">Модуль «Учёт посещаемости»</div>
                <div style="font-size:var(--text-xs);color:var(--color-primary);margin-top:var(--space-1);font-weight:600"><?= APP_THEME ?></div>
              </div>
            </div>

            <div class="about-grid">
              <div class="about-card">
                <div class="about-card-label">Версия</div>
                <div class="about-card-value"><?= APP_VERSION ?></div>
              </div>
              <div class="about-card">
                <div class="about-card-label">Год разработки</div>
                <div class="about-card-value"><?= APP_YEAR ?></div>
              </div>
              <div class="about-card">
                <div class="about-card-label">Учебное заведение</div>
                <div class="about-card-value">СВГТК им. Абая Кунанбаева, г. Сарань</div>
              </div>
              <div class="about-card">
                <div class="about-card-label">Разработчик</div>
                <div class="about-card-value"><?= htmlspecialchars($userName) ?></div>
              </div>
              <div class="about-card">
                <div class="about-card-label">Тип работы</div>
                <div class="about-card-value">Дипломная работа</div>
              </div>
              <div class="about-card">
                <div class="about-card-label">База данных</div>
                <div class="about-card-value">MySQL 5.7 / MariaDB</div>
              </div>
            </div>

            <!-- Стек технологий -->
            <p style="font-size:var(--text-xs);font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-faint);margin:var(--space-5) 0 var(--space-3)">Стек технологий</p>
            <div class="tech-list">
              <span class="tech-tag"><span class="tech-dot" style="background:#777bb4"></span>PHP 8.1</span>
              <span class="tech-tag"><span class="tech-dot" style="background:#4479a1"></span>MySQL 5.7</span>
              <span class="tech-tag"><span class="tech-dot" style="background:#f7df1e"></span>JavaScript ES2021</span>
              <span class="tech-tag"><span class="tech-dot" style="background:#264de4"></span>CSS3</span>
              <span class="tech-tag"><span class="tech-dot" style="background:#e44d26"></span>HTML5</span>
              <span class="tech-tag"><span class="tech-dot" style="background:#0ea5e9"></span>PDO / OOXML</span>
              <span class="tech-tag"><span class="tech-dot" style="background:#22c55e"></span>Fetch API</span>
              <span class="tech-tag"><span class="tech-dot" style="background:#a855f7"></span>Inter / Montserrat</span>
            </div>

            <!-- Назначение -->
            <p style="font-size:var(--text-xs);font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-faint);margin:var(--space-5) 0 var(--space-3)">Назначение системы</p>
            <p style="font-size:var(--text-sm);color:var(--color-text-muted);line-height:1.7">
              Модуль предназначен для автоматизации учёта посещаемости студентов СВГТК им. Абая Кунанбаева.
              Система позволяет преподавателям вести ежедневный журнал присутствия, хранить и проверять
              документы об уважительных причинах пропусков, формировать сводные ведомости и экспортировать
              отчёты в форматы DOCX и CSV для передачи в деканат.
            </p>

            <!-- Системные требования -->
            <p style="font-size:var(--text-xs);font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-faint);margin:var(--space-5) 0 var(--space-3)">Системные требования</p>
            <table class="shortcut-table">
              <tr><td style="color:var(--color-text-muted);font-weight:500">Сервер</td><td>PHP ≥ 8.0, MySQL ≥ 5.7</td></tr>
              <tr><td style="color:var(--color-text-muted);font-weight:500">Браузер</td><td>Chrome ≥ 90, Firefox ≥ 88, Edge ≥ 90, Safari ≥ 14</td></tr>
              <tr><td style="color:var(--color-text-muted);font-weight:500">Экран</td><td>Минимум 768 × 600 пикселей</td></tr>
              <tr><td style="color:var(--color-text-muted);font-weight:500">Интернет</td><td>Требуется для Google Fonts (опционально)</td></tr>
            </table>

          </div>
        </section>

      </div><!-- /help-content -->
    </div><!-- /help-layout -->

  </main>

  <footer class="page-footer" style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-4) var(--space-6);border-top:1px solid var(--color-divider);font-size:var(--text-xs);color:var(--color-text-faint)">
    <span>СВГТК Портал · Справочная система</span>
    <span>Версия <?= APP_VERSION ?> · <?= APP_YEAR ?></span>
  </footer>

</div>

<script>
// Тема
(function(){
  var t = localStorage.getItem('theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
})();

document.addEventListener('DOMContentLoaded', function() {

  // Sidebar
  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('collapsed');
    document.getElementById('mainWrapper')?.classList.toggle('sidebar-collapsed');
  });
  document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('mobile-open');
  });

  // Тема
  document.getElementById('themeToggle')?.addEventListener('click', () => {
    const html = document.documentElement;
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
  });

  // Поиск
  document.getElementById('helpSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    const items = document.querySelectorAll('.faq-item');
    const sections = document.querySelectorAll('.help-section');
    const noRes = document.getElementById('searchNoResults');

    if (!q) {
      items.forEach(i => i.style.display = '');
      sections.forEach(s => s.style.display = '');
      noRes.style.display = 'none';
      return;
    }

    let found = 0;
    items.forEach(item => {
      const text = item.textContent.toLowerCase();
      item.style.display = text.includes(q) ? '' : 'none';
      if (text.includes(q)) found++;
    });

    // Скрыть секции без видимых FAQ
    sections.forEach(sec => {
      const visible = [...sec.querySelectorAll('.faq-item')].some(i => i.style.display !== 'none');
      sec.style.display = visible ? '' : 'none';
    });

    noRes.style.display = found === 0 ? 'block' : 'none';
  });

  // Горячая клавиша: ? → открыть справку (уже здесь)
  document.addEventListener('keydown', e => {
    if (e.key === '?' && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) {
      document.getElementById('helpSearch')?.focus();
    }
    if (e.key === 'Escape') {
      document.getElementById('helpSearch').value = '';
      document.getElementById('helpSearch').dispatchEvent(new Event('input'));
    }
  });
});

// FAQ аккордеон
function toggleFaq(btn) {
  const answer = btn.nextElementSibling;
  const isOpen = btn.classList.contains('open');

  // Закрыть все в этой секции
  const section = btn.closest('.help-section');
  section.querySelectorAll('.faq-q.open').forEach(q => {
    q.classList.remove('open');
    q.nextElementSibling.classList.remove('open');
  });

  // Открыть текущий если был закрыт
  if (!isOpen) {
    btn.classList.add('open');
    answer.classList.add('open');
    // Скролл
    setTimeout(() => btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
  }
}

// Скролл к секции
function scrollTo(id) {
  const el = document.getElementById(id);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    // Подсветить активный TOC
    document.querySelectorAll('.toc-link').forEach(l => l.classList.remove('active'));
    event.currentTarget?.classList.add('active');
  }
}

// Подсветка TOC при скролле
const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const id = entry.target.id;
      document.querySelectorAll('.toc-link').forEach(l => l.classList.remove('active'));
      document.querySelectorAll(`.toc-link[onclick*="${id}"]`).forEach(l => l.classList.add('active'));
    }
  });
}, { threshold: 0.3, rootMargin: '-60px 0px -40% 0px' });

document.querySelectorAll('.help-section').forEach(s => observer.observe(s));
</script>
</body>
</html>
