-- ============================================================
-- Миграция 005: исправление id без AUTO_INCREMENT для оценок
-- ============================================================
-- Ошибка вида "Field 'id' doesn't have a default value" возникает,
-- когда таблица имеет NOT NULL id, но id не является AUTO_INCREMENT.
-- Эти таблицы используются при открытии страницы выставления оценок.

SET FOREIGN_KEY_CHECKS = 0;

-- edu_subjects.id
SET @table_name := 'edu_subjects';
SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id');
SET @column_type := (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id' LIMIT 1);
SET @is_auto := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%');
SET @is_indexed := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id');
SET @sql := IF(@column_exists > 0 AND @is_indexed = 0, 'ALTER TABLE edu_subjects ADD INDEX idx_edu_subjects_id (id)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql := IF(@column_exists > 0 AND @is_auto = 0, CONCAT('ALTER TABLE edu_subjects MODIFY id ', @column_type, ' NOT NULL AUTO_INCREMENT'), 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- edu_semesters.id
SET @table_name := 'edu_semesters';
SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id');
SET @column_type := (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id' LIMIT 1);
SET @is_auto := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%');
SET @is_indexed := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id');
SET @sql := IF(@column_exists > 0 AND @is_indexed = 0, 'ALTER TABLE edu_semesters ADD INDEX idx_edu_semesters_id (id)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql := IF(@column_exists > 0 AND @is_auto = 0, CONCAT('ALTER TABLE edu_semesters MODIFY id ', @column_type, ' NOT NULL AUTO_INCREMENT'), 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- edu_grade_sheets.id
SET @table_name := 'edu_grade_sheets';
SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id');
SET @column_type := (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id' LIMIT 1);
SET @is_auto := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%');
SET @is_indexed := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id');
SET @sql := IF(@column_exists > 0 AND @is_indexed = 0, 'ALTER TABLE edu_grade_sheets ADD INDEX idx_edu_grade_sheets_id (id)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql := IF(@column_exists > 0 AND @is_auto = 0, CONCAT('ALTER TABLE edu_grade_sheets MODIFY id ', @column_type, ' NOT NULL AUTO_INCREMENT'), 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- edu_grades.id
SET @table_name := 'edu_grades';
SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id');
SET @column_type := (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id' LIMIT 1);
SET @is_auto := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%');
SET @is_indexed := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'id');
SET @sql := IF(@column_exists > 0 AND @is_indexed = 0, 'ALTER TABLE edu_grades ADD INDEX idx_edu_grades_id (id)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql := IF(@column_exists > 0 AND @is_auto = 0, CONCAT('ALTER TABLE edu_grades MODIFY id ', @column_type, ' NOT NULL AUTO_INCREMENT'), 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
