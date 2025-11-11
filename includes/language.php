<?php
// includes/language.php - Многоязычность

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
        'teacher' => 'Преподаватель',
        'director' => 'Директор СВГТК',
        'technician' => 'Системный техник',
        'admin' => 'Администратор',
        'my_requests' => 'Мои заявки',
        'create_request' => 'Создать заявку',
        'new_request' => 'Новая заявка',
        'request_type' => 'Тип заявки',
        'all_requests' => 'Все заявки',
        'approved_requests' => 'Одобренные заявки',
        'pending_approval' => 'Заявки на одобрение',
        'repair' => 'Ремонт и обслуживание',
        'software' => 'Установка ПО',
        '1c_database' => 'Создание БД 1С',
        'pending' => 'Ожидает одобрения',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        'in_progress' => 'В работе',
        'completed' => 'Завершена',
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
        'approve' => 'Одобрить',
        'reject' => 'Отклонить',
        'take_to_work' => 'Взять в работу',
        'complete_work' => 'Завершить работу',
        'add_comment' => 'Добавить комментарий',
        'no_requests' => 'Нет заявок',
        'no_pending_requests' => 'Нет заявок, ожидающих одобрения',
        'no_active_requests' => 'Нет активных заявок',
        'login_error' => 'Неверный логин или пароль',
        'created' => 'Создана',
        'from' => 'От',
        'date' => 'Дата',
    ],
    'kk' => [
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
        'teacher' => 'Оқытушы',
        'director' => 'СВГТК директоры',
        'technician' => 'Жүйелік техник',
        'admin' => 'Әкімші',
        'my_requests' => 'Менің өтінімдерім',
        'create_request' => 'Өтінім жасау',
        'new_request' => 'Жаңа өтінім',
        'request_type' => 'Өтінім түрі',
        'all_requests' => 'Барлық өтінімдер',
        'approved_requests' => 'Бекітілген өтінімдер',
        'pending_approval' => 'Бекітуді күтуде',
        'repair' => 'Жөндеу және қызмет көрсету',
        'software' => 'Бағдарлама орнату',
        '1c_database' => '1С дерекқорын құру',
        'pending' => 'Бекітуді күтуде',
        'approved' => 'Бекітілді',
        'rejected' => 'Қабылданбады',
        'in_progress' => 'Орындалуда',
        'completed' => 'Аяқталды',
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
        'approve' => 'Бекіту',
        'reject' => 'Қабылдамау',
        'take_to_work' => 'Жұмысқа алу',
        'complete_work' => 'Жұмысты аяқтау',
        'add_comment' => 'Түсініктеме қосу',
        'no_requests' => 'Өтінімдер жоқ',
        'no_pending_requests' => 'Бекітуді күтетін өтінімдер жоқ',
        'no_active_requests' => 'Белсенді өтінімдер жоқ',
        'login_error' => 'Логин немесе құпия сөз дұрыс емес',
        'created' => 'Жасалды',
        'from' => 'Кімнен',
        'date' => 'Күні',
    ]
];

function t($key) {
    global $translations;
    $lang = getCurrentLanguage();
    return $translations[$lang][$key] ?? $key;
}
?>