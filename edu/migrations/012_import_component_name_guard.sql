-- 012: Дублирующая безопасная миграция для component_name.
-- Нужна, чтобы импорт РУПЛ не падал, если 011 не применился на конкретной базе.

SET @component_name_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_curriculum_modules'
      AND COLUMN_NAME = 'component_name'
);
SET @component_name_sql := IF(@component_name_exists = 0,
    'ALTER TABLE edu_curriculum_modules ADD COLUMN component_name TEXT NULL AFTER module_type',
    'DO 0'
);
PREPARE component_name_stmt FROM @component_name_sql;
EXECUTE component_name_stmt;
DEALLOCATE PREPARE component_name_stmt;
