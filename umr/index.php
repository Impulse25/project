<?php

require_once __DIR__ . '/partials/init.php';

// admin и director —  обзор всех РУПл
if ($isAdmin || $isDirector) {
    header('Location: views/work_programs/');
    exit;
}

// Председатель ПЦК — назначает преподавателей
if ($isPccHead) {
    header('Location: views/teacher_assignments/');
    exit;
}

// Методист — видит все РУП
if ($isMethodist) {
    header('Location: views/work_programs/');
    exit;
}

// Обычный teacher с правом нагрузки
if ($isPlainTeacher && $canViewLoadSummary) {
    header('Location: views/load/');
    exit;
}

// Обычный teacher с правом только просмотра своих РУП
if ($canViewWorkPrograms) {
    header('Location: views/work_programs/');
    exit;
}

// Нет доступа 
http_response_code(403);
echo "Доступ запрещён. У вас нет прав для просмотра этого раздела.";
exit;
