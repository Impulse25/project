-- ============================================================
-- Миграция 009: AUTO_INCREMENT для всех edu_* таблиц с id
-- ============================================================
-- Исправляет ошибку вида:
-- Field 'id' doesn't have a default value
--
-- Применяется ко всем таблицам модуля edu, где есть числовое поле id,
-- но оно не является AUTO_INCREMENT.

DELIMITER //
CREATE PROCEDURE edu_fix_all_edu_id_autoincrements()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table VARCHAR(128);
    DECLARE v_column_type VARCHAR(255);
    DECLARE v_index_count INT DEFAULT 0;
    DECLARE v_index_name VARCHAR(64);

    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME LIKE 'edu\_%'
          AND COLUMN_NAME = 'id'
          AND DATA_TYPE IN ('tinyint','smallint','mediumint','int','bigint')
          AND EXTRA NOT LIKE '%auto_increment%'
        ORDER BY TABLE_NAME;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    SET FOREIGN_KEY_CHECKS = 0;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_table, v_column_type;
        IF done THEN
            LEAVE read_loop;
        END IF;

        SELECT COUNT(*)
        INTO v_index_count
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = v_table
          AND COLUMN_NAME = 'id';

        IF v_index_count = 0 THEN
            SET v_index_name = CONCAT('idx_', LEFT(v_table, 48), '_id_ai');
            SET @sql = CONCAT(
                'ALTER TABLE `', REPLACE(v_table, '`', '``'),
                '` ADD INDEX `', REPLACE(v_index_name, '`', '``'), '` (`id`)'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;

        SET @sql = CONCAT(
            'ALTER TABLE `', REPLACE(v_table, '`', '``'),
            '` MODIFY `id` ', v_column_type, ' NOT NULL AUTO_INCREMENT'
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;

    SET FOREIGN_KEY_CHECKS = 1;
END//
DELIMITER ;

CALL edu_fix_all_edu_id_autoincrements();
DROP PROCEDURE edu_fix_all_edu_id_autoincrements;

-- edu_student_cards используется через INSERT ... ON DUPLICATE KEY UPDATE.
-- Для корректного обновления одной карточки студента нужен уникальный ключ по student_id.
SET @table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
);

SET @has_student_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'student_id'
);

SET @has_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND COLUMN_NAME = 'id'
);

-- На случай уже накопившихся дублей: оставляем строку с максимальным id.
SET @sql := IF(@table_exists > 0 AND @has_student_id > 0 AND @has_id > 0,
    'DELETE sc1 FROM edu_student_cards sc1 JOIN edu_student_cards sc2 ON sc1.student_id = sc2.student_id AND sc1.id < sc2.id WHERE sc1.student_id IS NOT NULL',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @unique_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_student_cards'
      AND INDEX_NAME = 'uniq_edu_student_cards_student_id'
);

SET @sql := IF(@table_exists > 0 AND @has_student_id > 0 AND @unique_exists = 0,
    'ALTER TABLE edu_student_cards ADD UNIQUE KEY uniq_edu_student_cards_student_id (student_id)',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
