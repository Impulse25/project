CREATE TABLE IF NOT EXISTS edu_curriculum_passport_fields (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    label         VARCHAR(255) NOT NULL DEFAULT '',
    value         MEDIUMTEXT NULL,
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE,
    INDEX idx_ecpf_curriculum (curriculum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edu_curriculum_process_schedule (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    course_label  VARCHAR(10) NOT NULL DEFAULT '',
    week_num      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    month_name    VARCHAR(30) NOT NULL DEFAULT '',
    value_text    VARCHAR(255) NOT NULL DEFAULT '',
    span_weeks    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE,
    INDEX idx_ecps_curriculum (curriculum_id),
    INDEX idx_ecps_course_week (curriculum_id, course_label, week_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_curriculum_process_schedule'
      AND COLUMN_NAME = 'span_weeks'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_curriculum_process_schedule ADD COLUMN span_weeks TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER value_text',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS edu_curriculum_process_legend (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    code          VARCHAR(20) NOT NULL DEFAULT '',
    description   VARCHAR(255) NOT NULL DEFAULT '',
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE,
    INDEX idx_ecpl_curriculum (curriculum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edu_curriculum_summary (
    id                         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id              INT UNSIGNED NOT NULL,
    course_label               VARCHAR(10) NOT NULL DEFAULT '',
    theory_weeks               DECIMAL(5,2) NULL,
    theory_hours               SMALLINT UNSIGNED NULL,
    theory_credits             DECIMAL(5,2) NULL,
    interim_attestation_hours  SMALLINT UNSIGNED NULL,
    production_practice_hours  SMALLINT UNSIGNED NULL,
    diploma_design_hours       SMALLINT UNSIGNED NULL,
    final_attestation_hours    SMALLINT UNSIGNED NULL,
    holiday_hours              SMALLINT UNSIGNED NULL,
    vacation_weeks             SMALLINT UNSIGNED NULL,
    total_weeks                SMALLINT UNSIGNED NULL,
    sort_order                 SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE,
    INDEX idx_ecs_curriculum (curriculum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE edu_curricula
SET specialty_name = TRIM(LEADING '- ' FROM specialty_name)
WHERE specialty_name LIKE '- %';
