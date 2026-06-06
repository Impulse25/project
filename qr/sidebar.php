<?php
/**
 * includes/sidebar.php
 * Перед подключением нужно определить $activePage:
 *   'qr'          — QR-Посещаемость
 *   'edu'         — Учебный процесс
 *   'attendance'  — Посещаемость
 *   'achievements'— Достижения
 *   'umr'         — УМР
 *   'hr'          — HR-аналитика
 *   'analytics'   — Аналитика
 *   'requests'    — Мои заявки
 */
$activePage = $activePage ?? '';

function navItem(string $href, string $label, string $icon, string $key, string $active): string {
  $cls = $key === $active ? ' active' : '';
  return "<a href=\"{$href}\" class=\"nav-item{$cls}\">{$icon}<span>{$label}</span></a>";
}

$icons = [
  'home' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  'edu'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  'attendance' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
  'achievements' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>',
  'umr'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
  'hr'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'analytics' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
  'qr'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h2v2h-2zM18 14h3M14 18h3M18 18h3v3h-3z"/></svg>',
  'requests' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
  'admin' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
];
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
        <span class="logo-sub">QR-Посещаемость</span>
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Навигация</div>
    <?= navItem('../', 'Главная', $icons['home'], 'home', $activePage) ?>

    <div class="nav-section-label" style="margin-top:.75rem">Модули портала</div>
    <?= navItem('../edu/',          'Учебный процесс', $icons['edu'],          'edu',          $activePage) ?>
    <?= navItem('../attendance/',   'Посещаемость',    $icons['attendance'],   'attendance',   $activePage) ?>
    <?= navItem('../achievements/', 'Достижения',      $icons['achievements'], 'achievements', $activePage) ?>
    <?= navItem('../umr/',          'УМР',             $icons['umr'],          'umr',          $activePage) ?>
    <?= navItem('../hr/',           'HR-аналитика',    $icons['hr'],           'hr',           $activePage) ?>
    <?= navItem('../analytics/',    'Аналитика',       $icons['analytics'],    'analytics',    $activePage) ?> 
    <?= navItem('../qr/', 'QR-Посещаемость', $icons['qr'], 'qr', $activePage) ?>

    <div class="nav-section-label" style="margin-top:.75rem">Заявки в ИТ</div>
    <?= navItem('../requests/teacher_dashboard.php', 'Мои заявки', $icons['requests'], 'requests', $activePage) ?>

    <?php if ($isAdmin ?? false): ?>
    <div class="nav-section-label" style="margin-top:.75rem">Администрирование</div>
    <?= navItem('../requests/admin_dashboard.php', 'Дашборд админа', $icons['admin'], 'admin', $activePage) ?>
    <?php endif ?>
  </nav>

  <div class="sidebar-footer">
    <div class="college-info">
      <span>СВГТК им. Абая Кунанбаева</span>
      <span>г. Сарань</span>
    </div>
  </div>
</aside>