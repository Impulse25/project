-- ============================================================
-- Миграция 007: совместимость edu_grade_sheets с выставлением оценок
-- ============================================================

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grade_sheets'
      AND COLUMN_NAME = 'group_id'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grade_sheets ADD COLUMN group_id INT NULL AFTER id',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE edu_grade_sheets gs
JOIN (
    SELECT eg.grade_sheet_id, MIN(s.group_id) AS group_id
    FROM edu_grades eg
    JOIN edu_students s ON s.id = eg.student_id
    WHERE s.group_id IS NOT NULL
    GROUP BY eg.grade_sheet_id
) x ON x.grade_sheet_id = gs.id
SET gs.group_id = x.group_id
WHERE gs.group_id IS NULL OR gs.group_id = 0;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grade_sheets'
      AND INDEX_NAME = 'idx_egs_group_module_semester'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE edu_grade_sheets ADD INDEX idx_egs_group_module_semester (group_id, curriculum_module_id, curriculum_semester)',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
