<?php require_once __DIR__ . '/icons.php'; ?>
<div class="topbar">
    <div class="burger"><?= svgIcon('menu') ?></div>
    <div class="user-chip">
        <div class="user-avatar"><?= svgIcon('user') ?></div>
        <button class="theme-toggle-btn" type="button" data-theme-toggle title="Сменить тему" aria-label="Сменить тему">
            <?= svgIcon('sun') ?>
        </button>
        <span><?= htmlspecialchars(roleTitle()) ?></span>
        <?= svgIcon('chevron-down') ?>
    </div>
</div>
