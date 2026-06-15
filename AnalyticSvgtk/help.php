<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/icons.php';
include 'includes/header.php';

$role = currentRole();
$roleName = roleTitle();
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="content">
    <?php include 'includes/topbar.php'; ?>

    <section class="page-head">
        <h1 class="page-title">Справка</h1>
        <div class="page-subtitle">Помощь по работе с модулем аналитики и отчётности «СВГТК Портал»</div>
    </section>

    <section class="help-hero">
        <div class="help-hero-icon"><?= svgIcon('help') ?></div>
        <div>
            <div class="help-hero-title">Руководство пользователя</div>
            <div class="help-hero-text">Текущая роль: <strong><?= htmlspecialchars($roleName) ?></strong>. В справке показаны основные действия для запуска системы, работы с Dashboard, группами, оценками, посещаемостью и отчётами.</div>
        </div>
    </section>

    <section class="help-grid">
        <div class="help-card">
            <div class="help-card-icon icon-blue"><?= svgIcon('home') ?></div>
            <div class="help-card-title">Dashboard</div>
            <div class="help-card-text">Просмотр ключевых показателей, средних баллов, посещаемости, выпускников и статусов групп.</div>
        </div>
        <div class="help-card">
            <div class="help-card-icon icon-green"><?= svgIcon('users') ?></div>
            <div class="help-card-title">Группы и фильтры</div>
            <div class="help-card-text">Фильтрация учебных групп, студентов и преподавателей по доступным критериям.</div>
        </div>
        <div class="help-card">
            <div class="help-card-icon icon-orange"><?= svgIcon('journal') ?></div>
            <div class="help-card-title">Оценки</div>
            <div class="help-card-text">Работа с оценками по 100-балльной системе и автоматический вывод эквивалента 2, 3, 4 или 5.</div>
        </div>
        <div class="help-card">
            <div class="help-card-icon icon-yellow"><?= svgIcon('file') ?></div>
            <div class="help-card-title">Отчёты</div>
            <div class="help-card-text">Формирование аналитики, печать отчётов, сохранение в PDF и экспорт таблиц в Excel.</div>
        </div>
    </section>

    <section class="help-accordion">
        <details class="help-item" open>
            <summary>Запуск системы</summary>
            <div class="help-body">
                <ol>
                    <li>Запустить Open Server Panel.</li>
                    <li>Включить Apache и MySQL.</li>
                    <li>Разместить папку проекта в директории локального сервера.</li>
                    <li>Импортировать базу данных через phpMyAdmin.</li>
                    <li>Открыть сайт через браузер и перейти на страницу авторизации.</li>
                </ol>
            </div>
        </details>

        <details class="help-item">
            <summary>Авторизация пользователя</summary>
            <div class="help-body">
                <p>Для входа в систему необходимо указать логин и пароль. После успешной авторизации система определяет роль пользователя и открывает соответствующие разделы.</p>
                <ul>
                    <li>Администратор видит все данные системы.</li>
                    <li>Преподаватель видит только закреплённые группы и предметы.</li>
                    <li>Студент видит только личные оценки, посещаемость и отчёты.</li>
                </ul>
            </div>
        </details>

        <details class="help-item">
            <summary>Работа администратора</summary>
            <div class="help-body">
                <p>Администратор использует систему для общего контроля образовательных данных. Ему доступны Dashboard, студенты, преподаватели, группы, посещаемость, оценки, аналитика и отчёты.</p>
                <p>В разделах «Студенты», «Преподаватели» и «Группы» можно применять критерии и фильтры для быстрого отбора нужных данных.</p>
            </div>
        </details>

        <details class="help-item">
            <summary>Работа преподавателя</summary>
            <div class="help-body">
                <p>Преподаватель работает с закреплёнными учебными группами и предметами. В разделе «Оценки» он выбирает группу, период, предмет и вводит оценки студентов по 100-балльной системе.</p>
                <p>Система автоматически определяет эквивалент оценки: 5 — отлично, 4 — хорошо, 3 — удовлетворительно, 2 — неудовлетворительно.</p>
            </div>
        </details>

        <details class="help-item">
            <summary>Работа студента</summary>
            <div class="help-body">
                <p>Студент просматривает личный кабинет, собственные оценки, посещаемость и индивидуальную аналитику. Доступ к данным других студентов закрыт.</p>
                <p>В личном отчёте отображаются средний балл, критерий успеваемости, посещаемость и рекомендации.</p>
            </div>
        </details>

        <details class="help-item">
            <summary>Просмотр учебных групп</summary>
            <div class="help-body">
                <p>В разделе «Группы» отображаются учебные группы и показатели по ним. Для поиска нужных данных можно использовать критерии: курс, статус, количество студентов, средний балл и процент посещаемости.</p>
            </div>
        </details>

        <details class="help-item">
            <summary>Работа с оценками</summary>
            <div class="help-body">
                <p>Оценки вводятся по шкале от 0 до 100 баллов. Если оценка уже существует, система обновляет запись и не создаёт дубликат.</p>
                <ul>
                    <li>90–100 — 5, отлично;</li>
                    <li>70–89 — 4, хорошо;</li>
                    <li>51–69 — 3, удовлетворительно;</li>
                    <li>0–50 — 2, неудовлетворительно.</li>
                </ul>
            </div>
        </details>

        <details class="help-item">
            <summary>Просмотр посещаемости</summary>
            <div class="help-body">
                <p>Раздел «Посещаемость» предназначен для просмотра информации о посещении занятий. Данные используются при формировании аналитических отчётов и отображаются в виде процентов и критериев.</p>
            </div>
        </details>

        <details class="help-item">
            <summary>Формирование аналитического отчёта</summary>
            <div class="help-body">
                <ol>
                    <li>Открыть раздел «Аналитика и отчёты».</li>
                    <li>Выбрать нужный период и критерии.</li>
                    <li>Просмотреть сформированные карточки, таблицы и диаграммы.</li>
                    <li>При необходимости выполнить печать или экспорт.</li>
                </ol>
            </div>
        </details>

        <details class="help-item">
            <summary>Печать и экспорт отчётов</summary>
            <div class="help-body">
                <p>Кнопка «Печать / сохранить PDF» открывает печатную версию отчёта без лишних элементов интерфейса. Кнопка «Экспорт отчёта Excel» выгружает таблицу отчёта в файл Excel.</p>
            </div>
        </details>

        <details class="help-item">
            <summary>Настройки интерфейса</summary>
            <div class="help-body">
                <p>В разделе «Настройки» можно переключить светлую или тёмную тему оформления. Выбранная тема сохраняется в браузере пользователя.</p>
            </div>
        </details>

        <details class="help-item">
            <summary>Типовые ошибки и рекомендации</summary>
            <div class="help-body">
                <ul>
                    <li>Если сайт не открывается — проверить, запущены ли Apache и MySQL.</li>
                    <li>Если не работает вход — проверить логин, пароль и записи в таблице users.</li>
                    <li>Если нет данных в отчёте — проверить выбранный период и наличие записей в базе.</li>
                    <li>Если не строятся диаграммы — проверить подключение JavaScript-файлов.</li>
                </ul>
            </div>
        </details>
    </section>
</main>
</div>
<?php include 'includes/footer.php'; ?>
