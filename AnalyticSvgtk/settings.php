<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';
include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="content">
    <?php include 'includes/topbar.php'; ?>
    <section class="page-head">
        <h1 class="page-title">Настройки</h1>
        <div class="page-subtitle">Персональная настройка интерфейса «СВГТК Портал»</div>
    </section>

    <section class="settings-grid">
        <div class="settings-card">
            <div class="settings-title">Тема оформления</div>
            <div class="settings-text">Можно переключить сайт между тёмной темой как на дашборде и светлой темой для печати, проверки и дневной работы.</div>
            <div class="theme-options">
                <button class="theme-option" type="button" data-theme-option="dark">
                    <?= svgIcon('moon') ?>
                    <div class="theme-option-title">Тёмная тема</div>
                    <div class="theme-option-note">основной стиль портала</div>
                </button>
                <button class="theme-option" type="button" data-theme-option="light">
                    <?= svgIcon('sun') ?>
                    <div class="theme-option-title">Светлая тема</div>
                    <div class="theme-option-note">белый интерфейс</div>
                </button>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-title">Права доступа</div>
            <div class="settings-text">Текущая роль: <strong><?= htmlspecialchars(roleTitle()) ?></strong>. Данные на страницах фильтруются автоматически по роли пользователя.</div>
            <div class="criteria-card">
                <div class="criteria-title">Режим доступа</div>
                <div class="criteria-value"><?= isAdmin() ? 'Все данные' : (isTeacher() ? 'Свои группы' : 'Личные отчёты') ?></div>
            </div>
        </div>

        <div class="settings-card settings-card-wide">
            <div class="settings-title">Будущие обновления системы</div>
            <div class="settings-text">Модуль аналитики и отчётности в системе «СВГТК Портал» разработана как дипломный проект, но архитектура оставлена открытой для дальнейшего развития. В будущем можно добавить новые виды отчётов, расширенные критерии аналитики, уведомления, экспорт в дополнительные форматы и новые роли пользователей.</div>
            <div class="criteria-card">
                <div class="criteria-title">Статус развития</div>
                <div class="criteria-value">Возможны будущие обновления</div>
            </div>
        </div>
    </section>
</main>
</div>
<?php include 'includes/footer.php'; ?>
