-- migration_add_auth_type_to_logs.sql
-- Добавление поля auth_type в таблицу логов

-- ВАЖНО: Если ваша таблица называется не user_logs, замените название
-- Возможные варианты: activity_logs, audit_logs, logs

-- Добавляем поле auth_type
ALTER TABLE user_logs 
ADD COLUMN IF NOT EXISTS auth_type VARCHAR(10) DEFAULT 'local' 
COMMENT 'Тип авторизации: local или ldap';

-- Добавляем поле success
ALTER TABLE user_logs 
ADD COLUMN IF NOT EXISTS success BOOLEAN DEFAULT 1;

-- Добавляем поле error_message
ALTER TABLE user_logs 
ADD COLUMN IF NOT EXISTS error_message TEXT NULL;

-- Обновляем существующие записи
UPDATE user_logs SET auth_type = 'local' WHERE auth_type IS NULL;
UPDATE user_logs SET success = 1 WHERE success IS NULL;

-- Индексы
ALTER TABLE user_logs ADD INDEX IF NOT EXISTS idx_auth_type (auth_type);
ALTER TABLE user_logs ADD INDEX IF NOT EXISTS idx_success (success);

-- Проверка
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN auth_type='ldap' THEN 1 ELSE 0 END) as ldap_count,
    SUM(CASE WHEN auth_type='local' THEN 1 ELSE 0 END) as local_count
FROM user_logs;
