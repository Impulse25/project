-- 011: Добавляет отдельное поле для колонки между индексом и наименованием РУПЛ.
-- В некоторых РУПЛ эта колонка содержит название дисциплины/практики,
-- а следующая колонка содержит РО/описание. Без неё терялись производственная
-- и преддипломная практики.

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_curriculum_modules'
      AND COLUMN_NAME = 'component_name'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_curriculum_modules ADD COLUMN component_name TEXT NULL AFTER module_type',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
