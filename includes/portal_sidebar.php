<?php
// includes/portal_sidebar.php — Единый сайдбар для всех модулей портала
// Использование: $activeModule = 'achievements'; require_once __DIR__ . '/../includes/portal_sidebar.php';
// Значения $activeModule: home, edu, attendance, achievements, umr, hr, analytics, qr, schedule, requests

$activeModule = $activeModule ?? '';
$_role        = $_SESSION['role'] ?? '';
$_isAdmin     = in_array($_role, ['admin', 'director']);
$_isTech      = $_role === 'technician';

function _nav(string $href, string $label, string $svgPath, string $key, string $active): string {
    $cls = $active === $key ? ' active' : '';
    return "<a href=\"{$href}\" class=\"nav-item{$cls}\">
      <svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">{$svgPath}</svg>
      <span>{$label}</span>
    </a>\n";
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
        <span class="logo-sub"><?= htmlspecialchars($moduleTitle ?? 'Портал') ?></span>
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
  </div>

  <nav class="sidebar-nav">

    <div class="nav-section-label">Навигация</div>
    <?= _nav('/', 'Главная',
      '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
      'home', $activeModule) ?>

    <div class="nav-section-label" style="margin-top:1rem">Модули портала</div>
    <?= _nav('/edu/', 'Учебный процесс',
      '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/>',
      'edu', $activeModule) ?>
    <?= _nav('/attendance/', 'Посещаемость',
      '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
      'attendance', $activeModule) ?>
    <?= _nav('/achievements/', 'Достижения',
      '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>',
      'achievements', $activeModule) ?>
    <?= _nav('/umr/', 'УМР',
      '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
      'umr', $activeModule) ?>
    <?= _nav('/hr/', 'HR-аналитика',
      '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
      'hr', $activeModule) ?>
    <?= _nav('/analytics/', 'Аналитика',
      '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
      'analytics', $activeModule) ?>
    <?= _nav('/schedule/', 'Расписание',
      '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
      'schedule', $activeModule) ?>
    <?= _nav('/qr/', 'QR-посещаемость',
      '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3z"/><path d="M17 17h3v3h-3z"/>',
      'qr', $activeModule) ?>

    <div class="nav-section-label" style="margin-top:1rem">Заявки в ИТ</div>
    <?php if ($_isTech): ?>
      <?= _nav('/requests/technician_dashboard.php', 'Мои задачи',
        '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'requests', $activeModule) ?>
    <?php else: ?>
      <?= _nav('/requests/teacher_dashboard.php', 'Мои заявки',
        '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>',
        'requests', $activeModule) ?>
      <?= _nav('/requests/create_request.php', 'Создать заявку',
        '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'create', $activeModule) ?>
    <?php endif ?>

    <?php if ($_isAdmin): ?>
    <div class="nav-section-label" style="margin-top:1rem">Администрирование</div>
    <?= _nav('/requests/admin_dashboard.php', 'Дашборд',
      '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
      'admin', $activeModule) ?>
    <?= _nav('/requests/users.php', 'Пользователи',
      '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
      'users', $activeModule) ?>
    <?= _nav('/requests/admin_requests.php', 'Все заявки',
      '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
      'allrequests', $activeModule) ?>
    <?php endif ?>

  </nav>

  <div class="sidebar-footer">
    <div class="college-info">
      <span>СВГТК им. Абая Кунанбаева</span>
      <span>г. Сарань</span>
    </div>
  </div>
</aside>
