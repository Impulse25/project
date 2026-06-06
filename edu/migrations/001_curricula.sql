-- ============================================================
-- Миграция 001: РУПл — вставить в phpMyAdmin → вкладка SQL
-- База: p-355792_svgtk
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS edu_curricula (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    speciality_id    INT NULL,
    specialty_code   VARCHAR(20) NOT NULL DEFAULT '',
    specialty_name   VARCHAR(255) NOT NULL DEFAULT '',
    qualification    VARCHAR(512) NULL,
    base_education   ENUM('9 класс','11 класс') NOT NULL DEFAULT '9 класс',
    enrollment_year  YEAR NOT NULL DEFAULT 2023,
    duration_years   TINYINT UNSIGNED NOT NULL DEFAULT 4,
    total_credits    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    total_hours      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    name             VARCHAR(255) NOT NULL DEFAULT '',
    file_path        VARCHAR(500) NULL,
    imported_at      DATETIME NULL,
    imported_by      INT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (speciality_id) REFERENCES edu_specialties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edu_curriculum_modules (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id    INT UNSIGNED NOT NULL,
    parent_id        INT UNSIGNED NULL,
    index_code       VARCHAR(20) NOT NULL DEFAULT '',
    module_type      ENUM('ООД','БМ','ПМ','ПА','ИА','ДП','К','Ф','ИТОГО') NOT NULL DEFAULT 'ООД',
    component_name   TEXT NULL,
    name             TEXT NOT NULL,
    credits          DECIMAL(5,2) NULL,
    total_hours      SMALLINT UNSIGNED NULL,
    theory_hours     SMALLINT UNSIGNED NULL,
    practice_hours   SMALLINT UNSIGNED NULL,
    coursework_hours SMALLINT UNSIGNED NULL,
    srsp_hours       SMALLINT UNSIGNED NULL,
    srs_hours        SMALLINT UNSIGNED NULL,
    production_hours SMALLINT UNSIGNED NULL,
    individual_hours SMALLINT UNSIGNED NULL,
    exam_semester    VARCHAR(20) NULL,
    credit_semester  VARCHAR(20) NULL,
    control_work     VARCHAR(20) NULL,
    is_summary       TINYINT(1) NOT NULL DEFAULT 0,
    sort_order       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    subject_id       INT NULL,
    FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id)     REFERENCES edu_curriculum_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id)    REFERENCES edu_subjects(id) ON DELETE SET NULL,
    INDEX idx_ecm_curriculum (curriculum_id),
    INDEX idx_ecm_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edu_curriculum_distribution (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id    INT UNSIGNED NOT NULL,
    semester_num TINYINT UNSIGNED NOT NULL,
    hours        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_ecd_module_sem (module_id, semester_num),
    FOREIGN KEY (module_id) REFERENCES edu_curriculum_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edu_competencies (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    code          VARCHAR(10) NOT NULL DEFAULT '',
    name          TEXT NOT NULL,
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE,
    INDEX idx_ec_curriculum (curriculum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edu_module_competencies (
    module_id     INT UNSIGNED NOT NULL,
    competency_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (module_id, competency_id),
    FOREIGN KEY (module_id)     REFERENCES edu_curriculum_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (competency_id) REFERENCES edu_competencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edu_learning_outcomes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id   INT UNSIGNED NOT NULL,
    code        VARCHAR(20) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (module_id) REFERENCES edu_curriculum_modules(id) ON DELETE CASCADE,
    INDEX idx_elo_module (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edu_diploma_records (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id          INT NOT NULL,
    diploma_number      VARCHAR(50) NULL,
    registration_number VARCHAR(50) NULL,
    protocol_number     VARCHAR(50) NULL,
    issue_date          DATE NULL,
    qualification_code  VARCHAR(20) NULL,
    qualification_name  VARCHAR(255) NULL,
    thesis_topic        TEXT NULL,
    thesis_grade        TINYINT UNSIGNED NULL,
    gpa                 DECIMAL(4,2) NULL,
    gpa_letter          VARCHAR(5) NULL,
    distinction         TINYINT(1) NOT NULL DEFAULT 0,
    notes               TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_edr_student (student_id),
    FOREIGN KEY (student_id) REFERENCES edu_students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_groups'
      AND COLUMN_NAME = 'curriculum_id'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_groups ADD COLUMN curriculum_id INT UNSIGNED NULL',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_groups'
      AND COLUMN_NAME = 'base_education'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_groups ADD COLUMN base_education ENUM(''9 класс'',''11 класс'') NULL',
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
      AND COLUMN_NAME = 'curriculum_module_id'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grade_sheets ADD COLUMN curriculum_module_id INT UNSIGNED NULL',
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
      AND COLUMN_NAME = 'curriculum_semester'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE edu_grade_sheets ADD COLUMN curriculum_semester TINYINT UNSIGNED NULL',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
