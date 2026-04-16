<?php
// includes/language.php - Многоязычность
// Конфликты слияния git устранены. Версия: 2.0

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        'system_name'           => 'Система заявок СВГТК',
        'login'                 => 'Логин',
        'password'              => 'Пароль',
        'enter'                 => 'Войти',
        'exit'                  => 'Выход',
        'create'                => 'Создать',
        'cancel'                => 'Отмена',
        'send'                  => 'Отправить',
        'save'                  => 'Сохранить',
        'delete'                => 'Удалить',
        'edit'                  => 'Редактировать',
        'details'               => 'Подробнее',
        'back'                  => 'Назад',
        'close'                 => 'Закрыть',
        'yes'                   => 'Да',
        'no'                    => 'Нет',
        'search'                => 'Поиск',
        'filter'                => 'Фильтр',
        'all'                   => 'Все',
        'actions'               => 'Действия',
        'loading'               => 'Загрузка...',
        'print'                 => 'Печать',
        'export'                => 'Экспорт',
        // Роли
        'teacher'               => 'Преподаватель',
        'director'              => 'Директор',
        'technician'            => 'Системотехник',
        'admin'                 => 'Администратор',
        // Меню
        'main'                  => 'Главная',
        'dashboard'             => 'Панель управления',
        'my_requests'           => 'Мои заявки',
        'create_request'        => 'Создать заявку',
        'new_request'           => 'Новая заявка',
        'all_requests'          => 'Все заявки',
        'requests'              => 'Заявки',
        'users'                 => 'Пользователи',
        'roles'                 => 'Роли',
        'departments_cabinets'  => 'Отделения и кабинеты',
        'logs'                  => 'Логи',
        'settings'              => 'Настройки',
        'statistics'            => 'Статистика',
        'approved_requests'     => 'Одобренные заявки',
        'pending_approval'      => 'Заявки на одобрение',
        // Типы заявок
        'request_type'          => 'Тип заявки',
        'repair'                => 'Ремонт и обслуживание',
        'software'              => 'Установка ПО',
        '1c_database'           => 'Создание БД 1С',
        'general_question'      => 'Общие вопросы / Консультация',
        // Статусы
        'status'                => 'Статус',
        'pending'               => 'Ожидает',
        'approved'              => 'Одобрена',
        'rejected'              => 'Отклонена',
        'in_progress'           => 'В работе',
        'completed'             => 'Завершена',
        'awaiting_approval'     => 'Ожидает подтверждения',
        'awaiting_director'     => 'Ожидает директора',
        'waiting_confirmation'  => 'Ожидает подтверждения',
        // Приоритеты
        'priority'              => 'Приоритет',
        'priority_low'          => 'Низкий',
        'priority_normal'       => 'Обычный',
        'priority_high'         => 'Высокий',
        'priority_urgent'       => 'Срочный',
        'low'                   => 'Низкий',
        'normal'                => 'Обычный',
        'high'                  => 'Высокий',
        'urgent'                => 'Срочный',
        // Поля формы
        'full_name'             => 'ФИО',
        'position'              => 'Должность',
        'cabinet'               => 'Кабинет',
        'select_cabinet'        => 'Выберите кабинет',
        'equipment_type'        => 'Тип оборудования',
        'inventory_number'      => 'Инвентарный номер',
        'description'           => 'Описание проблемы',
        'software_list'         => 'Программное обеспечение',
        'justification'         => 'Обоснование',
        'group_number'          => 'Номер группы',
        'students_list'         => 'Список студентов',
        'database_purpose'      => 'Цель создания БД',
        'question_description'  => 'Описание вопроса/проблемы',
        'software_or_system'    => 'Программа/Система',
        'comment'               => 'Комментарий',
        'deadline'              => 'Срок выполнения',
        'created_at'            => 'Дата создания',
        'updated_at'            => 'Дата обновления',
        // Действия
        'approve'               => 'Одобрить',
        'reject'                => 'Отклонить',
        'take_to_work'          => 'Взять в работу',
        'complete_work'         => 'Завершить работу',
        'send_for_approval'     => 'Отправить на подтверждение',
        'send_to_director'      => 'Отправить директору',
        'add_comment'           => 'Добавить комментарий',
        'set_deadline'          => 'Установить срок',
        'confirm'               => 'Подтвердить',
        'return_to_work'        => 'Вернуть в работу',
        'view_details'          => 'Просмотр деталей',
        // Вкладки
        'tab_active'            => 'В работе',
        'tab_pending'           => 'Ожидают одобрения',
        'tab_approval'          => 'Ожидают подтверждения',
        'tab_archive'           => 'Архив',
        'tab_new'               => 'Новые заявки',
        'tab_my_work'           => 'Мои заявки',
        'tab_all'               => 'Все заявки',
        // Сообщения
        'no_requests'           => 'Нет заявок',
        'no_pending_requests'   => 'Нет заявок, ожидающих одобрения',
        'no_active_requests'    => 'Нет активных заявок',
        'no_archive_requests'   => 'Архив пуст',
        'login_error'           => 'Неверный логин или пароль',
        'request_created'       => 'Заявка успешно создана',
        'request_updated'       => 'Заявка обновлена',
        'request_deleted'       => 'Заявка удалена',
        'comment_added'         => 'Комментарий добавлен',
        'work_completed'        => 'Работа завершена',
        'sent_for_confirmation' => 'Отправлено на подтверждение',
        // Информация
        'created'               => 'Создана',
        'from'                  => 'От',
        'date'                  => 'Дата',
        'request_number'        => 'Заявка №',
        'assigned_to'           => 'Исполнитель',
        'approved_by'           => 'Одобрил',
        'completed_at'          => 'Завершена',
        'days'                  => 'дней',
        'hours'                 => 'часов',
        'comments_count'        => 'Комментариев',
        'history'               => 'История',
        'total_days'            => 'Всего дней',
        'waiting_time'          => 'Ожидание',
        'work_time'             => 'В работе',
        // Модальные окна
        'confirm_completion'    => 'Подтвердить выполнение работы?',
        'feedback_optional'     => 'Отзыв о работе (необязательно)',
        'return_reason'         => 'Причина возврата',
        'rejection_reason'      => 'Причина отклонения',
        'completion_note'       => 'Примечание о завершении',
        'teacher_feedback'      => 'Отзыв преподавателя',
        'technician_note'       => 'Примечание системотехника',
        'director_comment'      => 'Комментарий директора',
        // Дополнительно
        'ldap_active'           => 'LDAP авторизация активна',
        'return_back'           => 'Вернуться назад',
        'additional_info'       => 'Дополнительная информация',
        'request_details'       => 'Детали заявки',
        'quick_actions'         => 'Быстрые действия',
    ],
    'kk' => [
        'system_name'           => 'СВГТК өтінімдер жүйесі',
        'login'                 => 'Логин',
        'password'              => 'Құпия сөз',
        'enter'                 => 'Кіру',
        'exit'                  => 'Шығу',
        'create'                => 'Жасау',
        'cancel'                => 'Болдырмау',
        'send'                  => 'Жіберу',
        'save'                  => 'Сақтау',
        'delete'                => 'Жою',
        'edit'                  => 'Өңдеу',
        'details'               => 'Толығырақ',
        'back'                  => 'Артқа',
        'close'                 => 'Жабу',
        'yes'                   => 'Иә',
        'no'                    => 'Жоқ',
        'search'                => 'Іздеу',
        'filter'                => 'Сүзгі',
        'all'                   => 'Барлығы',
        'actions'               => 'Әрекеттер',
        'loading'               => 'Жүктелуде...',
        'print'                 => 'Басып шығару',
        'export'                => 'Экспорт',
        // Рөлдер
        'teacher'               => 'Оқытушы',
        'director'              => 'Директор',
        'technician'            => 'Жүйелік техник',
        'admin'                 => 'Әкімші',
        // Мәзір
        'main'                  => 'Басты бет',
        'dashboard'             => 'Басқару тақтасы',
        'my_requests'           => 'Менің өтінімдерім',
        'create_request'        => 'Өтінім жасау',
        'new_request'           => 'Жаңа өтінім',
        'all_requests'          => 'Барлық өтінімдер',
        'requests'              => 'Өтінімдер',
        'users'                 => 'Пайдаланушылар',
        'roles'                 => 'Рөлдер',
        'departments_cabinets'  => 'Бөлімдер мен кабинеттер',
        'logs'                  => 'Журналдар',
        'settings'              => 'Баптаулар',
        'statistics'            => 'Статистика',
        'approved_requests'     => 'Бекітілген өтінімдер',
        'pending_approval'      => 'Бекітуді күтуде',
        // Өтінім түрлері
        'request_type'          => 'Өтінім түрі',
        'repair'                => 'Жөндеу және қызмет көрсету',
        'software'              => 'Бағдарлама орнату',
        '1c_database'           => '1С дерекқорын құру',
        'general_question'      => 'Жалпы сұрақтар / Консультация',
        // Күйлер
        'status'                => 'Күйі',
        'pending'               => 'Күтуде',
        'approved'              => 'Бекітілді',
        'rejected'              => 'Қабылданбады',
        'in_progress'           => 'Орындалуда',
        'completed'             => 'Аяқталды',
        'awaiting_approval'     => 'Растауды күтуде',
        'awaiting_director'     => 'Директорды күтуде',
        'waiting_confirmation'  => 'Растауды күтуде',
        // Басымдықтар
        'priority'              => 'Басымдық',
        'priority_low'          => 'Төмен',
        'priority_normal'       => 'Қалыпты',
        'priority_high'         => 'Жоғары',
        'priority_urgent'       => 'Шұғыл',
        'low'                   => 'Төмен',
        'normal'                => 'Қалыпты',
        'high'                  => 'Жоғары',
        'urgent'                => 'Шұғыл',
        // Форма өрістері
        'full_name'             => 'Аты-жөні',
        'position'              => 'Лауазымы',
        'cabinet'               => 'Кабинет',
        'select_cabinet'        => 'Кабинетті таңдаңыз',
        'equipment_type'        => 'Жабдық түрі',
        'inventory_number'      => 'Инвентарлық нөмірі',
        'description'           => 'Мәселе сипаттамасы',
        'software_list'         => 'Бағдарламалық қамтамасыз ету',
        'justification'         => 'Негіздеме',
        'group_number'          => 'Топ нөмірі',
        'students_list'         => 'Студенттер тізімі',
        'database_purpose'      => 'Дерекқор құру мақсаты',
        'question_description'  => 'Сұрақ/мәселе сипаттамасы',
        'software_or_system'    => 'Бағдарлама/Жүйе',
        'comment'               => 'Түсініктеме',
        'deadline'              => 'Орындау мерзімі',
        'created_at'            => 'Құрылған күні',
        'updated_at'            => 'Жаңартылған күні',
        // Әрекеттер
        'approve'               => 'Бекіту',
        'reject'                => 'Қабылдамау',
        'take_to_work'          => 'Жұмысқа алу',
        'complete_work'         => 'Жұмысты аяқтау',
        'send_for_approval'     => 'Растауға жіберу',
        'send_to_director'      => 'Директорға жіберу',
        'add_comment'           => 'Түсініктеме қосу',
        'set_deadline'          => 'Мерзім белгілеу',
        'confirm'               => 'Растау',
        'return_to_work'        => 'Жұмысқа қайтару',
        'view_details'          => 'Толық қарау',
        // Қойындылар
        'tab_active'            => 'Орындалуда',
        'tab_pending'           => 'Бекітуді күтуде',
        'tab_approval'          => 'Растауды күтуде',
        'tab_archive'           => 'Мұрағат',
        'tab_new'               => 'Жаңа өтінімдер',
        'tab_my_work'           => 'Менің өтінімдерім',
        'tab_all'               => 'Барлық өтінімдер',
        // Хабарламалар
        'no_requests'           => 'Өтінімдер жоқ',
        'no_pending_requests'   => 'Бекітуді күтетін өтінімдер жоқ',
        'no_active_requests'    => 'Белсенді өтінімдер жоқ',
        'no_archive_requests'   => 'Мұрағат бос',
        'login_error'           => 'Логин немесе құпия сөз дұрыс емес',
        'request_created'       => 'Өтінім сәтті жасалды',
        'request_updated'       => 'Өтінім жаңартылды',
        'request_deleted'       => 'Өтінім жойылды',
        'comment_added'         => 'Түсініктеме қосылды',
        'work_completed'        => 'Жұмыс аяқталды',
        'sent_for_confirmation' => 'Растауға жіберілді',
        // Ақпарат
        'created'               => 'Жасалды',
        'from'                  => 'Кімнен',
        'date'                  => 'Күні',
        'request_number'        => 'Өтінім №',
        'assigned_to'           => 'Орындаушы',
        'approved_by'           => 'Бекіткен',
        'completed_at'          => 'Аяқталды',
        'days'                  => 'күн',
        'hours'                 => 'сағат',
        'comments_count'        => 'Түсініктемелер',
        'history'               => 'Тарих',
        'total_days'            => 'Барлық күндер',
        'waiting_time'          => 'Күту уақыты',
        'work_time'             => 'Жұмыс уақыты',
        // Модалдық терезелер
        'confirm_completion'    => 'Жұмыстың орындалуын растайсыз ба?',
        'feedback_optional'     => 'Жұмыс туралы пікір (міндетті емес)',
        'return_reason'         => 'Қайтару себебі',
        'rejection_reason'      => 'Бас тарту себебі',
        'completion_note'       => 'Аяқтау туралы ескерту',
        'teacher_feedback'      => 'Оқытушы пікірі',
        'technician_note'       => 'Техник ескертуі',
        'director_comment'      => 'Директор түсініктемесі',
        // Қосымша
        'ldap_active'           => 'LDAP авторизациясы белсенді',
        'return_back'           => 'Артқа қайту',
        'additional_info'       => 'Қосымша ақпарат',
        'request_details'       => 'Өтінім мәліметтері',
        'quick_actions'         => 'Жылдам әрекеттер',
    ],
];

function t($key) {
    global $translations;
    $lang = getCurrentLanguage();
    return $translations[$lang][$key] ?? $key;
}

function tPriority($priority) {
    return t('priority_' . $priority);
}

function tStatus($status) {
    return t($status);
}

function tRequestType($type) {
    return t($type);
}

function tRole($role) {
    return t($role);
}