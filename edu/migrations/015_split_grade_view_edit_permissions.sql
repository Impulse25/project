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

UPDATE roles
SET can_edu_view_grades = 1
WHERE can_edu_grades = 1;

UPDATE roles
SET can_edu_view_grades = 1,
    can_edu_grades = 1
WHERE role_code IN ('admin', 'teacher');

UPDATE roles
SET can_edu_view_grades = 1,
    can_edu_grades = 0
WHERE role_code = 'director';
