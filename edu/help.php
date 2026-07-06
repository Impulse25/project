<?php
// help.php — справочная система модуля «Учебный процесс»
// Формат справки: HTML-страница, содержание построено по структуре ГОСТ 19.505-79
// Разделы: назначение программы, условия выполнения, выполнение программы, сообщения оператору.

require_once 'includes/auth.php';

if (!edu_can_use_edu_module()) {
    header('Location: ' . edu_dashboard_url());
    exit;
}

$userRole = edu_current_role();
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Пользователь';
$dashboardUrl = edu_dashboard_url($userRole);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Справка — модуль «Учебный процесс»</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --bg: #eef3f9;
            --card: #ffffff;
            --text: #0f223d;
            --muted: #5f6f86;
            --border: #d5dfec;
            --primary: #1457d9;
            --primary-light: #e7f0ff;
            --danger: #dc2626;
            --warning: #b7791f;
            --success: #15803d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.55;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .brand strong {
            font-size: 18px;
        }

        .brand span {
            color: var(--muted);
            font-size: 13px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .layout {
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
            gap: 24px;
            max-width: 1440px;
            margin: 0 auto;
            padding: 26px;
        }

        .sidebar {
            position: sticky;
            top: 82px;
            align-self: start;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
        }

        .sidebar h2 {
            margin: 0 0 14px;
            font-size: 17px;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-list a {
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            border: 1px solid transparent;
            font-size: 14px;
        }

        .nav-list a:hover {
            background: var(--primary-light);
            border-color: #bed3ff;
        }

        main {
            min-width: 0;
        }

        .hero {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 26px;
            margin-bottom: 20px;
        }

        .hero h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .hero p {
            margin: 0;
            color: var(--muted);
        }

        .section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 26px;
            margin-bottom: 20px;
        }

        .section h2 {
            margin-top: 0;
            font-size: 24px;
        }

        .section h3 {
            margin-top: 28px;
            font-size: 19px;
        }

        .section h4 {
            margin-bottom: 8px;
            font-size: 16px;
        }

        ul, ol {
            padding-left: 24px;
        }

        li {
            margin: 6px 0;
        }

        .role-grid, .card-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 14px;
        }

        .info-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            background: #fbfdff;
        }

        .info-card h4 {
            margin-top: 0;
        }

        .note {
            border-left: 4px solid var(--primary);
            background: var(--primary-light);
            padding: 12px 14px;
            border-radius: 8px;
            margin: 16px 0;
        }

        .warning {
            border-left-color: var(--warning);
            background: #fff7e6;
        }

        .danger {
            border-left-color: var(--danger);
            background: #fff1f2;
        }

        .success {
            border-left-color: var(--success);
            background: #ecfdf3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 14px;
        }

        th, td {
            border: 1px solid var(--border);
            padding: 10px 12px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f4f7fb;
            font-weight: 700;
        }

        code {
            background: #f3f6fb;
            border: 1px solid #dde6f2;
            border-radius: 5px;
            padding: 2px 5px;
            font-family: Consolas, monospace;
            font-size: 13px;
        }

        .footer {
            color: var(--muted);
            font-size: 13px;
            text-align: center;
            padding: 10px 0 30px;
        }

        @media (max-width: 980px) {
            .layout {
                grid-template-columns: 1fr;
                padding: 16px;
            }

            .sidebar {
                position: static;
            }

            .role-grid, .card-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media print {
            .topbar, .sidebar {
                display: none;
            }

            body {
                background: #fff;
            }

            .layout {
                display: block;
                padding: 0;
            }

            .section, .hero {
                border: none;
                page-break-inside: avoid;
            }

            a {
                color: #000;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">
        <strong>Справка — модуль «Учебный процесс»</strong>
        <span>Руководство оператора по работе с модулем системы «СВГТК Портал»</span>
    </div>
    <div class="top-actions">
        <span><?= h($userName) ?></span>
        <a class="btn" href="<?= h($dashboardUrl) ?>">В админку</a>
        <a class="btn btn-primary" href="index.php">К учебному процессу</a>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">
        <h2>Содержание</h2>
        <nav class="nav-list">
            <a href="#purpose">1. Назначение программы</a>
            <a href="#conditions">2. Условия выполнения программы</a>
            <a href="#execution">3. Выполнение программы</a>
            <a href="#messages">4. Сообщения оператору</a>
            <a href="#roles">5. Права пользователей</a>
            <a href="#contacts">6. Рекомендации</a>
        </nav>
    </aside>

    <main>
        <section class="hero">
            <h1>Руководство оператора</h1>
            <p>
                Справочная система предназначена для пользователей модуля «Учебный процесс».
                Структура справки соответствует основным разделам ГОСТ 19.505-79:
                назначение программы, условия выполнения, выполнение программы и сообщения оператору.
            </p>
        </section>

        <section class="section" id="purpose">
            <h2>1. Назначение программы</h2>
            <p>
                Модуль «Учебный процесс» предназначен для автоматизации работы с учебными данными колледжа.
                Программа используется для хранения, просмотра и обработки сведений о студентах, группах,
                специальностях, рабочих учебных планах, дисциплинах, оценках и учебной документации.
            </p>

            <p>Основные функции программы:</p>
            <ul>
                <li>просмотр списка студентов;</li>
                <li>поиск и фильтрация студентов по ФИО, группе, специальности, году набора и статусу карточки;</li>
                <li>просмотр и редактирование карточки студента;</li>
                <li>управление специальностями и учебными группами;</li>
                <li>импорт рабочих учебных планов из Excel-файлов;</li>
                <li>просмотр паспорта РУПЛ, учебного плана, графика учебного процесса, сводных данных и компетенций;</li>
                <li>привязка РУПЛ к учебным группам;</li>
                <li>выставление итоговых оценок по дисциплинам РУПЛ;</li>
                <li>формирование ведомостей по дисциплине, семестру или всем семестрам;</li>
                <li>формирование личной карточки студента;</li>
                <li>формирование дипломной книги студента;</li>
                <li>экспорт списка студентов в Excel.</li>
            </ul>

            <div class="note">
                Программа работает как веб-модуль портала. Для использования не требуется устанавливать отдельное приложение на компьютер пользователя.
            </div>
        </section>

        <section class="section" id="conditions">
            <h2>2. Условия выполнения программы</h2>
            <p>
                Для выполнения программы пользователь должен иметь доступ к системе «СВГТК Портал»
                и учетную запись с назначенной ролью.
            </p>

            <h3>2.1 Требования к рабочему месту пользователя</h3>
            <ul>
                <li>персональный компьютер или ноутбук;</li>
                <li>современный веб-браузер: Google Chrome, Mozilla Firefox, Microsoft Edge или совместимый;</li>
                <li>доступ к локальной сети колледжа или к серверу, на котором размещен портал;</li>
                <li>возможность скачивания файлов;</li>
                <li>офисное приложение для открытия файлов DOCX, XLS и XLSX.</li>
            </ul>

            <h3>2.2 Требования к серверной части</h3>
            <ul>
                <li>веб-сервер с поддержкой PHP;</li>
                <li>СУБД MariaDB или MySQL;</li>
                <li>настроенное подключение к базе данных;</li>
                <li>установленные библиотеки для работы с DOCX и XLSX;</li>
                <li>права на чтение и запись в каталоги загрузки файлов и формирования документов.</li>
            </ul>

            <h3>2.3 Условия доступа</h3>
            <ul>
                <li>пользователь должен пройти авторизацию;</li>
                <li>доступные функции определяются ролью пользователя;</li>
                <li>преподаватель работает только с доступными ему студентами и группами;</li>
                <li>директор может просматривать оценки и формировать документы, но не изменяет оценки;</li>
                <li>администратор имеет полный доступ к функциям модуля.</li>
            </ul>
        </section>

        <section class="section" id="execution">
            <h2>3. Выполнение программы</h2>
            <p>
                В данном разделе приведен порядок действий оператора при работе с основными функциями модуля.
            </p>

            <h3>3.1 Вход в систему</h3>
            <ol>
                <li>Откройте портал в веб-браузере.</li>
                <li>Введите учетные данные пользователя.</li>
                <li>Нажмите кнопку входа.</li>
                <li>После успешной авторизации откройте раздел «Учебный процесс».</li>
            </ol>

            <h3>3.2 Работа со списком студентов</h3>
            <ol>
                <li>Откройте раздел «Учебный процесс».</li>
                <li>При необходимости заполните поле поиска по ФИО, ИИН или группе.</li>
                <li>Выберите группу, специальность, год набора или статус карточки.</li>
                <li>Нажмите кнопку «Найти».</li>
                <li>Для отмены фильтров нажмите кнопку «Сброс».</li>
                <li>Для открытия карточки студента нажмите кнопку «Открыть» в строке студента.</li>
            </ol>

            <h3>3.3 Редактирование карточки студента</h3>
            <ol>
                <li>Откройте карточку нужного студента.</li>
                <li>Проверьте основные сведения: ФИО, группу, специальность, ИИН и дату рождения.</li>
                <li>Заполните дополнительные данные, необходимые для документов.</li>
                <li>При необходимости загрузите фотографию студента.</li>
                <li>Введите тему диплома, оценку за диплом и оценку ГКК.</li>
                <li>Нажмите кнопку «Сохранить изменения».</li>
            </ol>

            <h3>3.4 Импорт РУПЛ</h3>
            <ol>
                <li>Откройте страницу «Учебные планы (РУПЛ)».</li>
                <li>Нажмите кнопку «Загрузить РУПЛ».</li>
                <li>Выберите Excel-файл рабочего учебного плана.</li>
                <li>Дождитесь проверки данных из файла.</li>
                <li>Проверьте название плана, специальность, квалификацию и количество найденных дисциплин.</li>
                <li>Укажите год поступления, базу образования и специальность из справочника.</li>
                <li>Нажмите кнопку «Подтвердить импорт».</li>
            </ol>

            <div class="note warning">
                Если структура Excel-файла отличается от ожидаемой, импорт может быть выполнен некорректно.
                В этом случае проверьте файл РУПЛ и повторите загрузку.
            </div>

            <h3>3.5 Просмотр РУПЛ</h3>
            <ol>
                <li>Откройте страницу «Учебные планы (РУПЛ)».</li>
                <li>Нажмите кнопку просмотра в строке нужного учебного плана.</li>
                <li>Переключайтесь между вкладками: «Паспорт», «Учебный план», «График учебного процесса», «Сводные данные», «Компетенции», «Группы».</li>
                <li>Для возврата к списку нажмите кнопку «Учебные планы».</li>
            </ol>

            <h3>3.6 Выставление оценок</h3>
            <ol>
                <li>Откройте страницу «Оценки».</li>
                <li>Выберите группу.</li>
                <li>Выберите семестр РУПЛ.</li>
                <li>Выберите дисциплину из списка.</li>
                <li>Нажмите кнопку «Открыть оценки».</li>
                <li>Заполните итоговые оценки студентов.</li>
                <li>Нажмите кнопку «Сохранить оценки».</li>
            </ol>

            <div class="note">
                Директор может открыть оценки только для просмотра. Изменение и сохранение оценок директору недоступно.
            </div>

            <h3>3.7 Формирование ведомости</h3>
            <ol>
                <li>Откройте страницу «Сформировать ведомость».</li>
                <li>Выберите учебную группу.</li>
                <li>Выберите тип формирования ведомости.</li>
                <li>Если требуется, выберите семестр и дисциплину.</li>
                <li>Нажмите кнопку «Сформировать ведомость».</li>
                <li>Скачайте сформированный файл.</li>
            </ol>

            <p>Доступные варианты формирования ведомости:</p>
            <ul>
                <li>по выбранной дисциплине;</li>
                <li>по всем дисциплинам выбранного семестра;</li>
                <li>за все 8 семестров — все дисциплины выводятся в одном XLSX-листе.</li>
            </ul>

            <h3>3.8 Формирование личной карточки</h3>
            <ol>
                <li>Откройте карточку студента.</li>
                <li>Проверьте заполнение персональных данных.</li>
                <li>Проверьте наличие данных группы, специальности и РУПЛ.</li>
                <li>Нажмите кнопку «Личная карточка».</li>
                <li>Скачайте сформированный DOCX-файл.</li>
            </ol>

            <h3>3.9 Формирование дипломной книги</h3>
            <ol>
                <li>Откройте карточку студента.</li>
                <li>Проверьте наличие фотографии, темы диплома и оценки за диплом.</li>
                <li>Проверьте, что по студенту сохранены оценки.</li>
                <li>Нажмите кнопку «Дипломная книга».</li>
                <li>Скачайте сформированный файл.</li>
            </ol>

            <h3>3.10 Экспорт студентов в Excel</h3>
            <ol>
                <li>Откройте главную страницу модуля «Учебный процесс».</li>
                <li>При необходимости примените фильтры.</li>
                <li>Нажмите кнопку «Экспорт Excel».</li>
                <li>Скачайте сформированный файл со списком студентов.</li>
            </ol>
        </section>

        <section class="section" id="messages">
            <h2>4. Сообщения оператору</h2>
            <p>
                В процессе работы программа может выводить информационные сообщения, предупреждения и ошибки.
                В таблице приведены наиболее распространенные сообщения и действия пользователя.
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Сообщение</th>
                        <th>Причина</th>
                        <th>Действия оператора</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Необходимо выбрать группу</td>
                        <td>Операция требует выбранной учебной группы</td>
                        <td>Выберите группу из выпадающего списка и повторите действие</td>
                    </tr>
                    <tr>
                        <td>Необходимо выбрать семестр</td>
                        <td>Не указан семестр РУПЛ</td>
                        <td>Выберите нужный семестр</td>
                    </tr>
                    <tr>
                        <td>Необходимо выбрать дисциплину</td>
                        <td>Не выбрана дисциплина для оценок или ведомости</td>
                        <td>Выберите дисциплину из списка</td>
                    </tr>
                    <tr>
                        <td>У группы не привязан РУПЛ</td>
                        <td>Для выбранной группы отсутствует связь с рабочим учебным планом</td>
                        <td>Привяжите РУПЛ к группе или обратитесь к администратору</td>
                    </tr>
                    <tr>
                        <td>В выбранном семестре нет дисциплин</td>
                        <td>В РУПЛ отсутствуют дисциплины с часами в выбранном семестре</td>
                        <td>Проверьте РУПЛ или выберите другой семестр</td>
                    </tr>
                    <tr>
                        <td>Ошибка импорта файла</td>
                        <td>Файл имеет неподходящий формат или не содержит нужных листов</td>
                        <td>Проверьте Excel-файл и повторите импорт</td>
                    </tr>
                    <tr>
                        <td>Оценки сохранены</td>
                        <td>Данные успешно записаны в базу</td>
                        <td>Дополнительные действия не требуются</td>
                    </tr>
                    <tr>
                        <td>Оценки не заполнены</td>
                        <td>По выбранной дисциплине отсутствуют сохраненные оценки</td>
                        <td>Откройте раздел оценок и заполните данные</td>
                    </tr>
                    <tr>
                        <td>Недостаточно прав доступа</td>
                        <td>Пользователь пытается выполнить запрещенное действие</td>
                        <td>Обратитесь к администратору или войдите под другой ролью</td>
                    </tr>
                    <tr>
                        <td>Файл не может быть сформирован</td>
                        <td>Недостаточно данных для документа или произошла ошибка шаблона</td>
                        <td>Проверьте данные студента, РУПЛ, оценки и повторите формирование</td>
                    </tr>
                    <tr>
                        <td>Данные импортированы</td>
                        <td>Импорт завершен успешно</td>
                        <td>Проверьте список студентов или список РУПЛ</td>
                    </tr>
                    <tr>
                        <td>Студенты не найдены</td>
                        <td>Фильтры не соответствуют имеющимся данным</td>
                        <td>Измените параметры поиска или нажмите «Сброс»</td>
                    </tr>
                </tbody>
            </table>

            <div class="note danger">
                Если ошибка повторяется после проверки данных, необходимо обратиться к администратору системы.
            </div>
        </section>

        <section class="section" id="roles">
            <h2>5. Права пользователей</h2>
            <div class="role-grid">
                <div class="info-card">
                    <h4>Администратор</h4>
                    <ul>
                        <li>полный доступ к модулю;</li>
                        <li>управление студентами, группами и специальностями;</li>
                        <li>импорт РУПЛ;</li>
                        <li>выставление и удаление оценок;</li>
                        <li>формирование документов;</li>
                        <li>экспорт данных.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h4>Преподаватель</h4>
                    <ul>
                        <li>работа с доступными группами;</li>
                        <li>просмотр своих студентов;</li>
                        <li>редактирование данных своих студентов;</li>
                        <li>выставление оценок;</li>
                        <li>формирование ведомостей и документов.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h4>Директор</h4>
                    <ul>
                        <li>просмотр учебной информации;</li>
                        <li>просмотр РУПЛ;</li>
                        <li>просмотр оценок;</li>
                        <li>формирование ведомостей;</li>
                        <li>формирование личной карточки и дипломной книги;</li>
                        <li>без права изменения оценок.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="section" id="contacts">
            <h2>6. Рекомендации оператору</h2>
            <ul>
                <li>Перед формированием ведомостей проверьте, что группа привязана к правильному РУПЛ.</li>
                <li>Перед формированием личной карточки и дипломной книги убедитесь, что оценки студента сохранены.</li>
                <li>При импорте РУПЛ используйте исходный Excel-файл без ручного повреждения структуры листов.</li>
                <li>При ошибках импорта проверьте лист учебного плана, специальность, квалификацию и распределение часов.</li>
                <li>Не закрывайте страницу во время формирования крупного Excel-файла.</li>
                <li>После импорта студентов используйте кнопку «Сброс», если ранее были применены фильтры.</li>
            </ul>

            <div class="note success">
                Справка открывается в браузере и может использоваться одновременно с основной страницей модуля.
            </div>
        </section>

        <div class="footer">
            Справочная система модуля «Учебный процесс». Формат HTML/PHP.
        </div>
    </main>
</div>
</body>
</html>
