-- 013: поля для дипломной книги студента

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'diploma_topic'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN diploma_topic TEXT NULL AFTER state_exam_3',
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
      AND COLUMN_NAME = 'diploma_score'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_student_cards ADD COLUMN diploma_score DECIMAL(5,2) NULL AFTER diploma_topic',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
