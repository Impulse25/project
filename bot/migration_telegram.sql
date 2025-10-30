-- Миграция: Добавление поддержки Telegram бота
-- Дата: 2025-10-29
-- Описание: Добавляет поля для связи пользователей с Telegram аккаунтами

-- 1. Добавляем поля в таблицу users
ALTER TABLE users 
ADD COLUMN telegram_id BIGINT NULL COMMENT 'Telegram ID пользователя' AFTER position,
ADD COLUMN telegram_username VARCHAR(100) NULL COMMENT 'Telegram username (@username)' AFTER telegram_id,
ADD COLUMN telegram_notifications BOOLEAN DEFAULT 1 COMMENT 'Включены ли уведомления в Telegram' AFTER telegram_username;

-- 2. Создаём индексы для быстрого поиска
CREATE INDEX idx_telegram_id ON users(telegram_id);
CREATE INDEX idx_telegram_notifications ON users(telegram_notifications);

-- 3. Обновляем существующих системотехников (по желанию)
-- UPDATE users SET telegram_notifications = 1 WHERE role = 'technician';

-- 4. Проверка
SELECT 
    id, 
    username, 
    full_name, 
    role, 
    telegram_id, 
    telegram_username, 
    telegram_notifications
FROM users 
WHERE role = 'technician';

-- Примечания:
-- - telegram_id должен быть уникальным для каждого пользователя
-- - telegram_notifications = 1 означает что пользователь будет получать уведомления
-- - Один Telegram может быть привязан только к одному аккаунту системотехника
