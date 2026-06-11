SET @departments_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
);

SET @departments_id_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
      AND COLUMN_NAME = 'id'
);

SET @dept_id_type := (
    SELECT COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
      AND COLUMN_NAME = 'id'
    LIMIT 1
);

SET @dept_id_type := IFNULL(@dept_id_type, 'INT');

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_groups'
      AND COLUMN_NAME = 'department_id'
);

SET @sql := IF(@column_exists = 0,
    CONCAT('ALTER TABLE edu_groups ADD COLUMN department_id ', @dept_id_type, ' NULL'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @current_department_type := (
    SELECT COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_groups'
      AND COLUMN_NAME = 'department_id'
    LIMIT 1
);

SET @sql := IF(@departments_table_exists > 0 AND @departments_id_exists > 0 AND @current_department_type <> @dept_id_type,
    CONCAT('ALTER TABLE edu_groups MODIFY COLUMN department_id ', @dept_id_type, ' NULL'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_groups'
      AND INDEX_NAME = 'idx_edu_groups_department_id'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE edu_groups ADD INDEX idx_edu_groups_department_id (department_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @departments_id_index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
      AND COLUMN_NAME = 'id'
      AND SEQ_IN_INDEX = 1
);
SET @sql := IF(@departments_table_exists > 0 AND @departments_id_exists > 0 AND @departments_id_index_exists = 0,
    'ALTER TABLE departments ADD INDEX idx_departments_id (id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @edu_groups_engine := (
    SELECT ENGINE
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_groups'
    LIMIT 1
);
SET @departments_engine := (
    SELECT ENGINE
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
    LIMIT 1
);

SET @sql := IF(@edu_groups_engine IS NOT NULL AND UPPER(@edu_groups_engine) <> 'INNODB',
    'ALTER TABLE edu_groups ENGINE=InnoDB',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@departments_table_exists > 0 AND @departments_engine IS NOT NULL AND UPPER(@departments_engine) <> 'INNODB',
    'ALTER TABLE departments ENGINE=InnoDB',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE edu_groups g
LEFT JOIN departments d ON d.id = g.department_id
SET g.department_id = NULL
WHERE g.department_id IS NOT NULL
  AND d.id IS NULL;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_groups'
      AND COLUMN_NAME = 'department_id'
      AND REFERENCED_TABLE_NAME = 'departments'
);

SET @sql := IF(@departments_table_exists > 0 AND @departments_id_exists > 0 AND @fk_exists = 0,
    'ALTER TABLE edu_groups ADD CONSTRAINT fk_edu_groups_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
