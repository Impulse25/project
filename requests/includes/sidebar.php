<?php
// includes/sidebar.php — Единый sidebar для всех страниц requests/
// Использование: $activePage = 'dashboard'; require_once 'includes/sidebar.php';

$activePage = $activePage ?? '';
$userRole   = $_SESSION['role'] ?? '';
$isAdmin    = in_array($userRole, ['admin', 'director']);

function nav_item(string $href, string $label, string $icon, string $key, string $active, string $badge = ''): string {
    $ac = $active === $key ? ' active' : '';
    $b  = $badge ? "<span class=\"nav-badge\">{$badge}</span>" : '';
    return "<a href=\"{$href}\" class=\"nav-item{$ac}\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">{$icon}</svg><span>{$label}</span>{$b}</a>\n    ";
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#1a56db"/><text x="16" y="22" text-anchor="middle" font-family="Montserrat,sans-serif" font-weight="700" font-size="13" fill="white">СП</text></svg>
      <div class="logo-text"><span class="logo-title">СВГТК Портал</span><span class="logo-sub"><?= $isAdmin ? 'Администратор' : 'Заявки в ИТ' ?></span></div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
  </div>

  <nav class="sidebar-nav">

    <?php if($isAdmin): ?>
    <!-- ══ ADMIN МЕНЮ ══ -->
    <div class="nav-section-label">Администрирование</div>
    <?= nav_item('admin_dashboard.php',  'Дашборд',             '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',  'admin',    $activePage) ?>
    <?= nav_item('users.php',            'Пользователи и роли', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',   'users',    $activePage) ?>
    <?= nav_item('admin_cabinets.php',   'Кабинеты',            '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',                                                            'cabinets', $activePage) ?>
    <?= nav_item('admin_logs.php',       'Журнал входов',       '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>',                  'logs',     $activePage) ?>

    <div class="nav-section-label" style="margin-top:1rem">Заявки в ИТ</div>
    <?= nav_item('admin_requests.php',    'Все заявки',  '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',                                          'requests',  $activePage) ?>

    <div class="nav-section-label" style="margin-top:1rem">Модули портала</div>
    <?= nav_item('../',              'Главная',         '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',                                                 'home',         $activePage) ?>
    <?= nav_item('../edu/',          'Учебный процесс', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>',                                                              'edu',          $activePage) ?>
    <?= nav_item('../attendance/',   'Посещаемость',    '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',                                           'attendance',   $activePage) ?>
    <?= nav_item('../achievements/', 'Достижения',      '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>',                                                                    'achievements', $activePage) ?>
    <?= nav_item('../umr/',          'УМР',             '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',                                         'umr',          $activePage) ?>
    <?= nav_item('../hr/',           'HR-аналитика',    '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',                                                                  'hr',           $activePage) ?>
    <?= nav_item('../analytics/',    'Аналитика',       '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',                                 'analytics',    $activePage) ?>

    <?php else: ?>
    <!-- ══ TEACHER МЕНЮ ══ -->
    <div class="nav-section-label">Навигация</div>
    <?= nav_item('../', 'Главная', '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>', 'home', $activePage) ?>

    <div class="nav-section-label" style="margin-top:1rem">Модули портала</div>
    <?= nav_item('../edu/',          'Учебный процесс', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>',                                                              'edu',          $activePage) ?>
    <?= nav_item('../attendance/',   'Посещаемость',    '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',                                           'attendance',   $activePage) ?>
    <?= nav_item('../achievements/', 'Достижения',      '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>',                                                                    'achievements', $activePage) ?>
    <?= nav_item('../umr/',          'УМР',             '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',                                         'umr',          $activePage) ?>
    <?= nav_item('../hr/',           'HR-аналитика',    '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',                                                                  'hr',           $activePage) ?>
    <?= nav_item('../analytics/',    'Аналитика',       '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',                                 'analytics',    $activePage) ?>

    <div class="nav-section-label" style="margin-top:1rem">Заявки в ИТ</div>
    <?= nav_item('teacher_dashboard.php', 'Мои заявки',     '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>',  'dashboard', $activePage) ?>
    <?= nav_item('create_request.php',    'Создать заявку', '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',                                       'create',    $activePage) ?>

    <?php endif ?>

  </nav>

  <div class="sidebar-footer">
    <div class="college-info">
      <span>СВГТК им. Абая Кунанбаева</span>
      <span>г. Сарань</span>
    </div>
  </div>
</aside>