-- ============================================================
-- Миграция 003: прямые связи оценок с дисциплинами РУПл
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;


-- Базовая схема у разных выгрузок проекта отличается: в некоторых edu_grade_sheets нет group_id.
-- Для выставления оценок группа должна храниться прямо в edu_grade_sheets.
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

-- Если уже были строки оценок, восстанавливаем группу по студентам в edu_grades.
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

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grades'
      AND COLUMN_NAME = 'curriculum_module_id'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grades ADD COLUMN curriculum_module_id INT UNSIGNED NULL AFTER student_id',
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
      AND COLUMN_NAME = 'curriculum_semester'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grades ADD COLUMN curriculum_semester TINYINT UNSIGNED NULL AFTER curriculum_module_id',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE edu_grades eg
JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id
SET eg.curriculum_module_id = COALESCE(eg.curriculum_module_id, gs.curriculum_module_id),
    eg.curriculum_semester  = COALESCE(eg.curriculum_semester, gs.curriculum_semester)
WHERE eg.curriculum_module_id IS NULL
   OR eg.curriculum_semester IS NULL;

UPDATE edu_grade_sheets gs
LEFT JOIN edu_curriculum_modules m ON m.id = gs.curriculum_module_id
SET gs.curriculum_module_id = NULL
WHERE gs.curriculum_module_id IS NOT NULL
  AND m.id IS NULL;

UPDATE edu_grades eg
LEFT JOIN edu_curriculum_modules m ON m.id = eg.curriculum_module_id
SET eg.curriculum_module_id = NULL
WHERE eg.curriculum_module_id IS NOT NULL
  AND m.id IS NULL;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grades'
      AND INDEX_NAME = 'idx_eg_curriculum_module_semester'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE edu_grades ADD INDEX idx_eg_curriculum_module_semester (curriculum_module_id, curriculum_semester, student_id)',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_egs_curriculum_module'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE edu_grade_sheets ADD CONSTRAINT fk_egs_curriculum_module FOREIGN KEY (curriculum_module_id) REFERENCES edu_curriculum_modules(id) ON DELETE SET NULL',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_eg_curriculum_module'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE edu_grades ADD CONSTRAINT fk_eg_curriculum_module FOREIGN KEY (curriculum_module_id) REFERENCES edu_curriculum_modules(id) ON DELETE SET NULL',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
