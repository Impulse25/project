<?php
// partials/umr_subnav.php

$_umr_tabs = [];

if (!$isTeacher || $isPccHead) {
    $_umr_tabs['teacher_assignments'] = [
        'label' => 'Тарификация',
        'href'  => '../../views/teacher_assignments/',
        'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    ];
}

if (!$isTeacher || $isPccHead || $isMethodist) {
    $_umr_tabs['curricula'] = [
        'label' => 'Планы',
        'href'  => '../../views/curricula/',
        'icon'  => '<path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><polyline points="9 11 12 14 17 9"/><line x1="8" y1="7" x2="16" y2="7"/>',
    ];
}

$_umr_tabs['work_programs'] = [
    'label' => 'РУП',
    'href'  => '../../views/work_programs/',
    'icon'  => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
];

$_umr_tabs['register_journal'] = [
    'label' => 'Журнал',
    'href'  => '../../views/register_journal/',
    'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
];

$_umr_tabs['load'] = [
    'label' => 'Нагрузка',
    'href'  => '../../views/load/',
    'icon'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
];
?>
<nav class="umr-subnav">
  <div class="umr-subnav-inner">
    <?php foreach ($_umr_tabs as $key => $tab): ?>
      <a href="<?= $tab['href'] ?>"
         class="umr-subnav-item<?= ($_nav_active_key ?? '') === $key ? ' active' : '' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <?= $tab['icon'] ?>
        </svg>
        <?= $tab['label'] ?>
      </a>
    <?php endforeach ?>
  </div>
</nav>