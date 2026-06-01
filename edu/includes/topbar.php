<?php
/**
 * includes/topbar.php
 * Верхняя панель (хлебные крошки + аватар + переключатель темы).
 *
 * Ожидает массив $breadcrumbs:
 *   [
 *     ['label' => 'СВГТК', 'href' => '../'],
 *     ['label' => 'Учебный процесс', 'href' => 'index.php'],
 *     ['label' => 'Иванов Иван'],   // последний элемент — без href
 *   ]
 *
 * А также $isLoggedIn, $initials, $userName из includes/auth.php.
 */
$breadcrumbs = $breadcrumbs ?? [];
$chevron = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>';
?>
<header class="topbar">
  <div class="topbar-left">
    <div class="breadcrumb">
      <?php foreach ($breadcrumbs as $i => $crumb): ?>
        <?php if ($i > 0): ?><?= $chevron ?><?php endif ?>
        <?php if (!empty($crumb['href'])): ?>
          <a href="<?= htmlspecialchars($crumb['href']) ?>" class="breadcrumb-root"><?= htmlspecialchars($crumb['label']) ?></a>
        <?php else: ?>
          <span class="breadcrumb-current"><?= htmlspecialchars($crumb['label']) ?></span>
        <?php endif ?>
      <?php endforeach ?>
    </div>
  </div>
  <div class="topbar-right">
    <?php if ($isLoggedIn): ?>
    <div class="user-avatar" title="<?= htmlspecialchars($userName) ?>"><?= $initials ?: 'U' ?></div>
    <?php endif ?>
    <button class="theme-toggle" id="themeToggle">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
    </button>
  </div>
</header>
