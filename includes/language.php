<?php
// includes/language.php - Многоязычность (Расширенная версия)

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ru';
}

function setLanguage($lang) {
    if (in_array($lang, ['ru', 'kk'])) {
        $_SESSION['lang'] = $lang;
    }
}

function getCurrentLanguage() {
    return $_SESSION['lang'] ?? 'ru';
}

$translations = [
    'ru' => [
        // Основные
        'system_name' => 'Система заявок СВГТК',
        'login' => 'Логин',
        'password' => 'Пароль',
        'enter' => 'Войти',
        'exit' => 'Выход',
        'create' => 'Создать',
        'cancel' => 'Отмена',
        'send' => 'Отправить',
        'save' => 'Сохранить',
        'delete' => 'Удалить',
        'edit' => 'Редактировать',
        'details' => 'Подробнее',
        'back' => 'Назад',
        
        // Роли
        'teacher' => 'Преподаватель',
        'director' => 'Директор СВГТК',
        'technician' => 'Системный техник',
        'admin' => 'Администратор',
        
        // Заявки
        'my_requests' => 'Мои заявки',
        'create_request' => 'Создать заявку',
        'new_request' => 'Новая заявка',
        'request_type' => 'Тип заявки',
        'all_requests' => 'Все заявки',
        'approved_requests' => 'Одобренные заявки',
        'pending_approval' => 'Заявки на одобрение',
        
        // Типы заявок
        'repair' => 'Ремонт и обслуживание',
        'software' => 'Установка ПО',
        '1c_database' => 'Создание БД 1С',
        
        // Статусы
        'pending' => 'Ожидает одобрения',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        'in_progress' => 'В работе',
        'completed' => 'Завершена',
        'new' => 'Новая',
        'waiting_confirmation' => 'Ожидает подтверждения',
        
        // Поля
        'full_name' => 'ФИО',
        'position' => 'Должность',
        'cabinet' => 'Кабинет',
        'select_cabinet' => 'Выберите кабинет',
        'equipment_type' => 'Тип оборудования',
        'inventory_number' => 'Инвентарный номер',
        'description' => 'Описание проблемы',
        'software_list' => 'Программное обеспечение',
        'justification' => 'Обоснование',
        'group_number' => 'Номер группы',
        'students_list' => 'Список студентов',
        'database_purpose' => 'Цель создания БД',
        'comment' => 'Комментарий',
        
        // Действия
        'approve' => 'Одобрить',
        'reject' => 'Отклонить',
        'take_to_work' => 'Взять в работу',
        'complete_work' => 'Завершить работу',
        'add_comment' => 'Добавить комментарий',
        'actions' => 'Действия',
        'view' => 'Просмотр',
        'confirm' => 'Подтвердить',
        'close' => 'Закрыть',
        'open' => 'Открыть',
        
        // Сообщения
        'no_requests' => 'Нет заявок',
        'no_pending_requests' => 'Нет заявок, ожидающих одобрения',
        'no_active_requests' => 'Нет активных заявок',
        'login_error' => 'Неверный логин или пароль',
        'success' => 'Успешно',
        'error' => 'Ошибка',
        'warning' => 'Предупреждение',
        'info' => 'Информация',
        'loading' => 'Загрузка...',
        
        // Даты и время
        'created' => 'Создана',
        'from' => 'От',
        'date' => 'Дата',
        'updated_at' => 'Обновлено',
        'completed_at' => 'Завершено',
        'deadline' => 'Срок выполнения',
        
        // Интерфейс
        'dashboard' => 'Панель управления',
        'archive' => 'Архив',
        'statistics' => 'Статистика',
        'users' => 'Пользователи',
        'settings' => 'Настройки',
        'profile' => 'Профиль',
        'reports' => 'Отчеты',
        'notifications' => 'Уведомления',
        'search' => 'Поиск',
        'filter' => 'Фильтр',
        'sort' => 'Сортировка',
        
        // Приоритеты
        'priority' => 'Приоритет',
        'urgent' => 'Срочно',
        'high' => 'Высокий',
        'normal' => 'Обычный',
        'low' => 'Низкий',
        
        // Прочее
        'status' => 'Статус',
        'type' => 'Тип',
        'total' => 'Всего',
        'active' => 'Активные',
        'closed' => 'Закрытые',
        'assigned_to' => 'Назначено',
        'created_by' => 'Создал',
        'yes' => 'Да',
        'no' => 'Нет',
        'show_more' => 'Показать больше',
        'show_less' => 'Показать меньше',
        'export' => 'Экспорт',
        'import' => 'Импорт',
        'print' => 'Печать',
        'download' => 'Скачать',
        'upload' => 'Загрузить',
        
        // Дополнительные фразы
        'repair_maintenance' => 'Обслуживание и ремонт',
        'software_installation' => 'Установка программ',
        'database_1c' => 'База данных 1С',
        'cabinet_placeholder' => 'Например: 101, Актовый зал, Библиотека, Спортзал',
        'cabinet_hint' => 'Укажите номер кабинета или название помещения',
        'priority_label' => 'Приоритет заявки',
        'low_priority' => 'Низкий',
        'normal_priority' => 'Обычный',
        'high_priority' => 'Высокий',
        'urgent_priority' => 'Срочно',
        'not_urgent' => 'Не срочно',
        'standard' => 'Стандартно',
        'important' => 'Важно',
        'very_urgent' => 'Очень срочно',
        'system_unit' => 'Системный блок',
        'monitor' => 'Монитор',
        'keyboard' => 'Клавиатура',
        'mouse' => 'Мышь',
        'printer' => 'Принтер',
        'scanner' => 'Сканер',
        'projector' => 'Проектор',
        'other' => 'Другое',
        'submit' => 'Отправить',
        'waiting_confirmation' => 'Ожидают подтверждения',
        'no_waiting_confirmation' => 'Нет заявок, ожидающих подтверждения',
        'admin_panel' => 'Панель администратора',
        'total_requests' => 'Всего заявок',
        'waiting_approval' => 'Ожидают одобрения',
        'in_work' => 'В работе',
        'finished' => 'Завершено',
        'main' => 'Главная',
        'requests' => 'Заявки',
        'roles' => 'Роли',
        'departments_cabinets' => 'Отделения и кабинеты',
        'logs' => 'Логи',
        'last_20_requests' => 'Последние 20 заявок',
        'id' => 'ID',
        'from_whom' => 'От',
        'actions_column' => 'Действия',
        'priority_processing_order' => 'Системотехники будут обрабатывать заявки в порядке приоритета',
        'archive_empty' => 'Архив пуст',
    ],
    'kk' => [
        // Основные
        'system_name' => 'СВГТК өтінімдер жүйесі',
        'login' => 'Логин',
        'password' => 'Құпия сөз',
        'enter' => 'Кіру',
        'exit' => 'Шығу',
        'create' => 'Жасау',
        'cancel' => 'Болдырмау',
        'send' => 'Жіберу',
        'save' => 'Сақтау',
        'delete' => 'Жою',
        'edit' => 'Өңдеу',
        'details' => 'Толығырақ',
        'back' => 'Артқа',
        
        // Роли
        'teacher' => 'Оқытушы',
        'director' => 'СВГТК директоры',
        'technician' => 'Жүйелік техник',
        'admin' => 'Әкімші',
        
        // Заявки
        'my_requests' => 'Менің өтінімдерім',
        'create_request' => 'Өтінім жасау',
        'new_request' => 'Жаңа өтінім',
        'request_type' => 'Өтінім түрі',
        'all_requests' => 'Барлық өтінімдер',
        'approved_requests' => 'Бекітілген өтінімдер',
        'pending_approval' => 'Бекітуді күтуде',
        
        // Типы заявок
        'repair' => 'Жөндеу және қызмет көрсету',
        'software' => 'Бағдарлама орнату',
        '1c_database' => '1С дерекқорын құру',
        
        // Статусы
        'pending' => 'Бекітуді күтуде',
        'approved' => 'Бекітілді',
        'rejected' => 'Қабылданбады',
        'in_progress' => 'Орындалуда',
        'completed' => 'Аяқталды',
        'new' => 'Жаңа',
        'waiting_confirmation' => 'Растауды күтуде',
        
        // Поля
        'full_name' => 'Аты-жөні',
        'position' => 'Лауазымы',
        'cabinet' => 'Кабинет',
        'select_cabinet' => 'Кабинетті таңдаңыз',
        'equipment_type' => 'Жабдық түрі',
        'inventory_number' => 'Инвентарлық нөмірі',
        'description' => 'Ақаулық сипаттамасы',
        'software_list' => 'Бағдарламалық қамтамасыз ету',
        'justification' => 'Негіздеме',
        'group_number' => 'Топ нөмірі',
        'students_list' => 'Студенттер тізімі',
        'database_purpose' => 'Дерекқор құру мақсаты',
        'comment' => 'Түсініктеме',
        
        // Действия
        'approve' => 'Бекіту',
        'reject' => 'Қабылдамау',
        'take_to_work' => 'Жұмысқа алу',
        'complete_work' => 'Жұмысты аяқтау',
        'add_comment' => 'Түсініктеме қосу',
        'actions' => 'Әрекеттер',
        'view' => 'Қарау',
        'confirm' => 'Растау',
        'close' => 'Жабу',
        'open' => 'Ашу',
        
        // Сообщения
        'no_requests' => 'Өтінімдер жоқ',
        'no_pending_requests' => 'Бекітуді күтетін өтінімдер жоқ',
        'no_active_requests' => 'Белсенді өтінімдер жоқ',
        'login_error' => 'Логин немесе құпия сөз дұрыс емес',
        'success' => 'Сәтті',
        'error' => 'Қате',
        'warning' => 'Ескерту',
        'info' => 'Ақпарат',
        'loading' => 'Жүктелуде...',
        
        // Даты и время
        'created' => 'Жасалды',
        'from' => 'Кімнен',
        'date' => 'Күні',
        'updated_at' => 'Жаңартылды',
        'completed_at' => 'Аяқталды',
        'deadline' => 'Орындау мерзімі',
        
        // Интерфейс
        'dashboard' => 'Басқару панелі',
        'archive' => 'Мұрағат',
        'statistics' => 'Статистика',
        'users' => 'Қолданушылар',
        'settings' => 'Баптаулар',
        'profile' => 'Профиль',
        'reports' => 'Есептер',
        'notifications' => 'Хабарламалар',
        'search' => 'Іздеу',
        'filter' => 'Сүзгі',
        'sort' => 'Сұрыптау',
        
        // Приоритеты
        'priority' => 'Басымдық',
        'urgent' => 'Шұғыл',
        'high' => 'Жоғары',
        'normal' => 'Қалыпты',
        'low' => 'Төмен',
        
        // Прочее
        'status' => 'Күйі',
        'type' => 'Түрі',
        'total' => 'Барлығы',
        'active' => 'Белсенді',
        'closed' => 'Жабылған',
        'assigned_to' => 'Тағайындалды',
        'created_by' => 'Жасаған',
        'yes' => 'Иә',
        'no' => 'Жоқ',
        'show_more' => 'Көбірек көрсету',
        'show_less' => 'Азырақ көрсету',
        'export' => 'Экспорт',
        'import' => 'Импорт',
        'print' => 'Басып шығару',
        'download' => 'Жүктеп алу',
        'upload' => 'Жүктеу',
        
        // Дополнительные фразы
        'repair_maintenance' => 'Жөндеу және қызмет көрсету',
        'software_installation' => 'Бағдарламаларды орнату',
        'database_1c' => '1С дерекқоры',
        'cabinet_placeholder' => 'Мысалы: 101, Жиналыс залы, Кітапхана, Спорт залы',
        'cabinet_hint' => 'Кабинет нөмірін немесе бөлме атауын көрсетіңіз',
        'priority_label' => 'Өтінім басымдығы',
        'low_priority' => 'Төмен',
        'normal_priority' => 'Қалыпты',
        'high_priority' => 'Жоғары',
        'urgent_priority' => 'Шұғыл',
        'not_urgent' => 'Шұғыл емес',
        'standard' => 'Стандартты',
        'important' => 'Маңызды',
        'very_urgent' => 'Өте шұғыл',
        'system_unit' => 'Жүйелік блок',
        'monitor' => 'Монитор',
        'keyboard' => 'Пернетақта',
        'mouse' => 'Тышқан',
        'printer' => 'Принтер',
        'scanner' => 'Сканер',
        'projector' => 'Проектор',
        'other' => 'Басқа',
        'submit' => 'Жіберу',
        'waiting_confirmation' => 'Растауды күтуде',
        'no_waiting_confirmation' => 'Растауды күтетін өтінімдер жоқ',
        'admin_panel' => 'Әкімші панелі',
        'total_requests' => 'Барлық өтінімдер',
        'waiting_approval' => 'Бекітуді күтуде',
        'in_work' => 'Орындалуда',
        'finished' => 'Аяқталды',
        'main' => 'Басты',
        'requests' => 'Өтінімдер',
        'roles' => 'Рөлдер',
        'departments_cabinets' => 'Бөлімдер мен кабинеттер',
        'logs' => 'Логтар',
        'last_20_requests' => 'Соңғы 20 өтінім',
        'id' => 'ID',
        'from_whom' => 'Кімнен',
        'actions_column' => 'Әрекеттер',
        'priority_processing_order' => 'Жүйелік техниктер өтінімдерді басымдық ретімен өңдейді',
        'archive_empty' => 'Мұрағат бос',
    ]
];

function t($key) {
    global $translations;
    $lang = getCurrentLanguage();
    return $translations[$lang][$key] ?? $key;
}
?>
