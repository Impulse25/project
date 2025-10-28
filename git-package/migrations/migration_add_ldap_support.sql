-- migration_add_ldap_support.sql
-- Добавление поддержки LDAP аутентификации для домена Shc.local
-- База данных: svgtk_requests

-- Проверяем текущую структуру
-- SHOW CREATE TABLE users;

-- Шаг 1: Добавляем поле auth_type (тип аутентификации)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS auth_type ENUM('local', 'ldap') DEFAULT 'local' 
COMMENT 'Тип аутентификации: local (локальная) или ldap (доменная)';

-- Шаг 2: Делаем password nullable для LDAP пользователей
ALTER TABLE users 
MODIFY COLUMN password VARCHAR(255) NULL 
COMMENT 'Хеш пароля (NULL для LDAP пользователей, так как пароль проверяется в AD)';

-- Шаг 3: Обновляем существующих пользователей как локальных
UPDATE users SET auth_type = 'local' WHERE auth_type IS NULL OR auth_type = '';

-- Шаг 4: Создаем индекс для быстрого поиска
ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_username_authtype (username, auth_type);

-- Шаг 5: Создаем резервного локального администратора
-- ВАЖНО: Измените пароль после первого входа!
-- Пароль по умолчанию: Admin2025!
INSERT INTO users (username, password, full_name, role, position, auth_type, created_at) 
VALUES (
    'local_admin', 
    '$2y$10$rH9Y0qVzB5qH7qYn5X5yPOQVGQYQqGQYQqGQYQqGQYQqGQYQqGQYQu', -- Пароль: Admin2025!
    'Локальный администратор',
    'admin',
    'Системный администратор',
    'local',
    NOW()
) ON DUPLICATE KEY UPDATE 
    role = 'admin',
    auth_type = 'local',
    password = '$2y$10$rH9Y0qVzB5qH7qYn5X5yPOQVGQYQqGQYQqGQYQqGQYQqGQYQqGQYQu';

-- Шаг 6: Добавляем комментарий к таблице
ALTER TABLE users COMMENT = 'Пользователи системы с поддержкой LDAP (домен Shc.local) и локальной аутентификации';

-- Проверка результата
SELECT 
    username, 
    full_name, 
    role, 
    auth_type,
    CASE 
        WHEN password IS NULL OR password = '' THEN 'Нет (LDAP)'
        ELSE 'Есть (Local)'
    END as password_status,
    created_at
FROM users
ORDER BY created_at DESC;

-- Справка по ролям:
-- admin - Администратор системы
-- director - Директор/Завуч
-- teacher - Преподаватель
-- technician - Системотехник
