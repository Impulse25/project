CREATE TABLE IF NOT EXISTS edu_curriculum_semester_meta (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    semester_num  TINYINT UNSIGNED NOT NULL,
    study_weeks   DECIMAL(5,2) NULL,
    weekly_hours  SMALLINT UNSIGNED NULL,
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_ecsm_curriculum_semester (curriculum_id, semester_num),
    INDEX idx_ecsm_curriculum (curriculum_id),
    CONSTRAINT fk_ecsm_curriculum FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
