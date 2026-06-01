<?php
/**
 * includes/sidebar.php
 * Левая навигационная панель.
 *
 * Ожидает:
 *   $isAdmin      — bool, показывать ли раздел «Администрирование»
 *   $activeNav    — string, ключ активного пункта меню, например 'edu'
 *   $sidebarSubtitle — string, подпись под логотипом (по умолчанию '')
 */
$activeNav       = $activeNav       ?? '';
$sidebarSubtitle = $sidebarSubtitle ?? 'Учебный процесс';

$navItems = [
    'home' => [
        'href'  => '../',
        'label' => 'Главная',
        'icon'  => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    ],
];

$modules = [
    'edu' => [
        'href'  => '../edu/',
        'label' => 'Учебный процесс',
        'icon'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>',
    ],
    'attendance' => [
        'href'  => '../attendance/',
        'label' => 'Посещаемость',
        'icon'  => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    ],
    'achievements' => [
        'href'  => '../achievements/',
        'label' => 'Достижения',
        'icon'  => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>',
    ],
    'umr' => [
        'href'  => '../umr/',
        'label' => 'УМР',
        'icon'  => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
    ],
    'hr' => [
        'href'  => '../hr/',
        'label' => 'HR-аналитика',
        'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
    ],
    'analytics' => [
        'href'  => '../analytics/',
        'label' => 'Аналитика',
        'icon'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
    ],
];

$svgBase = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
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
        <span class="logo-sub"><?= htmlspecialchars($sidebarSubtitle) ?></span>
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Навигация</div>
    <?php foreach ($navItems as $key => $item): ?>
    <a href="<?= $item['href'] ?>" class="nav-item<?= $activeNav === $key ? ' active' : '' ?>">
      <svg <?= $svgBase ?>><?= $item['icon'] ?></svg>
      <span><?= htmlspecialchars($item['label']) ?></span>
    </a>
    <?php endforeach ?>

    <div class="nav-section-label" style="margin-top:1rem">Модули портала</div>
    <?php foreach ($modules as $key => $item): ?>
    <a href="<?= $item['href'] ?>" class="nav-item<?= $activeNav === $key ? ' active' : '' ?>">
      <svg <?= $svgBase ?>><?= $item['icon'] ?></svg>
      <span><?= htmlspecialchars($item['label']) ?></span>
    </a>
    <?php endforeach ?>

    <div class="nav-section-label" style="margin-top:1rem">Заявки в ИТ</div>
    <a href="../requests/teacher_dashboard.php" class="nav-item<?= $activeNav === 'requests' ? ' active' : '' ?>">
      <svg <?= $svgBase ?>><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span>Мои заявки</span>
    </a>

    <?php if ($isAdmin): ?>
    <div class="nav-section-label" style="margin-top:1rem">Администрирование</div>
    <a href="../requests/admin_dashboard.php" class="nav-item<?= $activeNav === 'admin' ? ' active' : '' ?>">
      <svg <?= $svgBase ?>><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
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
