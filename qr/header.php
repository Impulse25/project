<?php
/**
 * qr/header.php — Верхняя панель (topbar)
 *
 * Перед подключением определи:
 *   $breadcrumbCurrent  — название текущей страницы, например 'QR-Посещаемость'
 *   $breadcrumbLink     — ссылка на родителя (по умолчанию '/')
 *
 * Переменные $isLoggedIn, $isAdmin, $userName, $initials
 * берутся из session_start() в начале файла страницы.
 */
$breadcrumbCurrent = $breadcrumbCurrent ?? 'Портал';
$breadcrumbLink    = $breadcrumbLink    ?? '/';
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Открыть меню">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="6"  x2="21" y2="6"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <nav class="breadcrumb">
      <span class="breadcrumb-root">
        <a href="<?= htmlspecialchars($breadcrumbLink) ?>" style="color:inherit">СВГТК</a>
      </span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
      </svg>
      <span class="breadcrumb-current"><?= htmlspecialchars($breadcrumbCurrent) ?></span>
    </nav>

    <div class="live-dot" title="Система активна"></div>
  </div>

  <div class="topbar-right">

    <!-- Кнопка темы -->
    <button class="theme-toggle" id="themeToggle" title="Сменить тему">
      <svg id="themeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
    </button>

    <?php if ($isLoggedIn ?? false): ?>

      <!-- Аватар пользователя -->
      <div class="user-avatar" title="<?= htmlspecialchars($userName ?? '') ?>">
        <?= htmlspecialchars($initials ?: 'U') ?>
      </div>

      <span style="width:1px;height:20px;background:var(--color-divider);flex-shrink:0"></span>

      <?php if ($isAdmin ?? false): ?>
        <a href="/requests/admin_dashboard.php" class="btn btn-outline btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          В админку
        </a>
      <?php endif ?>

      <!-- Выход -->
      <a href="/requests/logout.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Выход
      </a>

    <?php else: ?>
      <!-- Не залогинен — кнопка Войти как на главной -->
      <a href="/?login=1" class="btn btn-primary btn-sm">Войти</a>
    <?php endif ?>

  </div>
</header>