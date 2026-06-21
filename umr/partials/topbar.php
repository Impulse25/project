  <header class="topbar">

    <div class="topbar-left">

      <div class="breadcrumb">

        <span class="breadcrumb-root">
          <a href="../../../" style="color:inherit">СВГТК</a>
        </span>

        <?php foreach ($_breadcrumbs ?? [] as $label => $url): ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          <?php if ($url): ?>
            <span class="breadcrumb-root"><a href="<?= $url ?>" style="color:inherit"><?= $label ?></a></span>
          <?php else: ?>
            <span class="breadcrumb-current"><?= $label ?></span>
          <?php endif ?>
        <?php endforeach ?>

      </div>

    </div>

    <div class="topbar-right">

      <?php if($isLoggedIn): ?>
        <div class="user-avatar" title="<?= e($userName) ?>"><?= e($initials) ?></div>
      <?php endif ?>
      
      <span style="width:1px;height:20px;background:var(--color-divider);flex-shrink:0"></span>

      <button class="theme-toggle" id="themeToggle" title="Сменить тему">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>

      <span style="width:1px;height:20px;background:var(--color-divider);flex-shrink:0"></span>

      <?php if($isLoggedIn && $isAdmin): ?>
        <a href="/../requests/admin_dashboard.php" class="btn btn-outline btn-sm">В админку</a>
      <?php endif ?>

      <span style="width:1px;height:20px;background:var(--color-divider);flex-shrink:0"></span>
      
      <a href="/../umr/views/help/" class="btn btn-outline btn-sm">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Справка
      </a>

      <a href="/../requests/logout.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Выход
      </a>

    </div>
  </header>