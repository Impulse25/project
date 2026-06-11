SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'can_edu_view_grades'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE roles ADD COLUMN can_edu_view_grades TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'can_edu_grades'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE roles ADD COLUMN can_edu_grades TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'can_edu_generate_sheets'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE roles ADD COLUMN can_edu_generate_sheets TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'can_edu_export_students'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE roles ADD COLUMN can_edu_export_students TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'can_edu_edit_students'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE roles ADD COLUMN can_edu_edit_students TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'can_edu_student_card'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE roles ADD COLUMN can_edu_student_card TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'can_edu_diploma_book'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE roles ADD COLUMN can_edu_diploma_book TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE roles
SET can_edu_view_grades = 1,
    can_edu_grades = 1,
    can_edu_generate_sheets = 1,
    can_edu_export_students = 1,
    can_edu_edit_students = 1,
    can_edu_student_card = 1,
    can_edu_diploma_book = 1
WHERE role_code IN ('admin', 'teacher');

UPDATE roles
SET can_edu_view_grades = 1,
    can_edu_grades = 0,
    can_edu_generate_sheets = 1,
    can_edu_export_students = 1,
    can_edu_edit_students = 0,
    can_edu_student_card = 1,
    can_edu_diploma_book = 1
WHERE role_code = 'director';
