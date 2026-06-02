-- Не обязательное изменение схемы, а исправление старых значений ролей.
-- В коде теперь тоже есть совместимость с role='1'/'2'/'3', но лучше хранить роли текстом.

UPDATE users SET role = 'admin' WHERE role = '1';
UPDATE users SET role = 'director' WHERE role = '2';
UPDATE users SET role = 'teacher' WHERE role = '3';
UPDATE users SET role = 'technician' WHERE role = '4';

-- Проверка конкретного преподавателя из примера:
-- SELECT id, username, full_name, role FROM users WHERE id = 247;
-- SELECT id, name, curator_id FROM edu_groups WHERE curator_id = 247;
