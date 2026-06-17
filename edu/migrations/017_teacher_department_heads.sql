SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'is_department_head'
);

SET @sql := IF(@column_exists = 0,
    'ALTER TABLE users ADD COLUMN is_department_head TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'head_department_id'
);

SET @sql := IF(@column_exists = 0,
    'ALTER TABLE users ADD COLUMN head_department_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_head_department_id'
);

SET @sql := IF(@index_exists = 0,
    'ALTER TABLE users ADD INDEX idx_users_head_department_id (head_department_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE users
SET is_department_head = 0,
    head_department_id = NULL
WHERE LOWER(TRIM(CAST(role AS CHAR))) NOT IN ('teacher', '3', 'преподаватель', 'препод', 'технолог');
