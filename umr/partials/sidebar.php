<?php

require_once __DIR__ . '/../partials/init.php';

// Пути для каждой страницы
$root = '';

$_nav = [

    'portal_home'   => $root .  '/',
    'att_module'    => $root .  '/attendance',
    'qr_module'     => $root .   '/qr',
    'ach_module'    => $root .  '/achievements',
    'umr'           => $root .  '/umr',
    'hr_module'     => $root .   '/hr',
    'edu_module'    => $root .  '/edu',
    'rpt_module'    => $root .  '/analytics',
    'qr_module'    => $root .  '/qr',

    'it_requests'   => $root .  '/requests/teacher_dashboard.php',

    'admin_dash'    => $root . '/requests/admin_dashboard.php',
];

//Ключ для активной страницы
function _nav_active(string $key, string $current): string {
    return $key === $current ? ' active' : '';
}
?>

<aside class="sidebar" id="sidebar">

  <div class="sidebar-header">

    <div class="logo">

      <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#1a56db"/>
        <text x="16" y="22" text-anchor="middle" font-family="Montserrat,sans-serif" font-weight="700" font-size="13" fill="white">СП</text>
      </svg>

      <div class="logo-text">
        <span class="logo-title">СВГТК Портал</span>
        <span class="logo-sub">УМР</span>
      </div>

    </div>

    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Свернуть">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>

  </div>

  <nav class="sidebar-nav">

    <div class="nav-section-label">Навигация</div>

    <a href="<?= $_nav['portal_home'] ?>" class="nav-item <?= _nav_active('portal_home', $_nav_active_key) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Главная</span>
    </a>

    <div class="nav-section-label" style="margin-top:1rem">Модули портала</div> 

    <a href="<?= $_nav['edu_module'] ?>" class="nav-item <?= _nav_active('edu_module', $_nav_active_key) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
      <span>Учебный процесс</span>
    </a>

    <a href="<?= $_nav['att_module'] ?>" class="nav-item <?= _nav_active('att_module', $_nav_active_key) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
       <span>Посещаемость</span>
    </a>

    <a href="<?= $_nav['ach_module'] ?>" class="nav-item <?= _nav_active('ach_module', $_nav_active_key) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
      <span>Достижения</span>
    </a>

    <a href="<?= $_nav['umr'] ?>" class="nav-item <?= _nav_active('umr', $_nav_active_key) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
          <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
      </svg>
      <span>УМР</span>
    </a>

    <a href="<?= $_nav['hr_module'] ?>" class="nav-item <?= _nav_active('hr_module', $_nav_active_key) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>HR-аналитика</span>
    </a>

    <a href="<?= $_nav['rpt_module'] ?>" class="nav-item <?= _nav_active('rpt_module', $_nav_active_key) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Аналитика</span>
    </a>

    <a href="<?= $_nav['qr_module'] ?>" class="nav-item <?= _nav_active('qr_module', $_nav_active_key) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h2v2h-2zM18 14h3M14 18h3M18 18h3v3h-3z"/></svg>
      <span>QR-Посещаемость</span>
    </a>

    <div class="nav-section-label">Заявки в ИТ</div>

    <a href="<?= $_nav['it_requests'] ?>" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span>Мои заявки</span>
    </a>

    <?php if($isAdmin): ?>

        <div class="nav-section-label" style="margin-top:1rem">Администрирование</div>

        <a href="<?= $_nav['admin_dash'] ?>" class="nav-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          <span>Дашборд админа</span>
        </a>
        
    <?php endif ?>
  </nav>

  <div class="sidebar-footer">
    <div class="college-info">
      <span>СВГТК им. Абая Кунанбаева</span>
      <span>г. Сарань</span>
    </div>
  </div>
  
</aside>

