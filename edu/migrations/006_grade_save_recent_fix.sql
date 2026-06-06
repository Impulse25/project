-- ============================================================
-- Миграция 006: корректное сохранение и отображение последних записей оценок
-- ============================================================

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grade_sheets'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grade_sheets ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grade_sheets'
      AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grade_sheets ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grades'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grades ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grades'
      AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grades ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Обновляем дату изменения у записей, где уже есть выставленные оценки.
UPDATE edu_grade_sheets gs
JOIN (
    SELECT grade_sheet_id, MAX(updated_at) AS max_updated_at
    FROM edu_grades
    WHERE grade IS NOT NULL OR passed = 1 OR absent = 1 OR COALESCE(comment, '') <> '' OR date IS NOT NULL
    GROUP BY grade_sheet_id
) x ON x.grade_sheet_id = gs.id
SET gs.updated_at = COALESCE(x.max_updated_at, gs.updated_at);

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grade_sheets'
      AND INDEX_NAME = 'idx_egs_updated_at'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE edu_grade_sheets ADD INDEX idx_egs_updated_at (updated_at)',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
