-- ============================================================
-- Миграция 008: дополнительные поля личной карточки студента
-- ============================================================

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'gender'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN gender VARCHAR(20) NULL AFTER notes',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'birth_place'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN birth_place VARCHAR(255) NULL AFTER gender',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'enrollment_order'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN enrollment_order VARCHAR(255) NULL AFTER birth_place',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'previous_education'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN previous_education VARCHAR(255) NULL AFTER enrollment_order',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'school_finished'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN school_finished VARCHAR(255) NULL AFTER previous_education',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'promotion_orders'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN promotion_orders TEXT NULL AFTER school_finished',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'graduation_order'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN graduation_order VARCHAR(255) NULL AFTER promotion_orders',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'job_assignment'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN job_assignment TEXT NULL AFTER graduation_order',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'coursework_topic'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN coursework_topic TEXT NULL AFTER job_assignment',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'coursework_grade'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN coursework_grade VARCHAR(255) NULL AFTER coursework_topic',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'state_exam_1'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN state_exam_1 VARCHAR(255) NULL AFTER coursework_grade',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'state_exam_2'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN state_exam_2 VARCHAR(255) NULL AFTER state_exam_1',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'state_exam_3'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN state_exam_3 VARCHAR(255) NULL AFTER state_exam_2',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
