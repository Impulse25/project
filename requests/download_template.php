<?php
// download_template.php - Скачивание шаблона CSV

require_once 'includes/auth.php';
requireRole('admin');

// Создание шаблона CSV
$template = "Логин,ФИО,Роль,Должность,Пароль\n";
$template .= "ivanov,Иванов Иван Иванович,teacher,Учитель математики,password123\n";
$template .= "petrov,Петров Петр Петрович,laborant,Лаборант,password456\n";
$template .= "sidorov,Сидоров Сидор Сидорович,administration,Зам.директора,password789\n";

// Отправка файла
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_users.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Добавляем BOM для корректного отображения кириллицы в Excel
echo "\xEF\xBB\xBF";
echo $template;
exit();
?>
