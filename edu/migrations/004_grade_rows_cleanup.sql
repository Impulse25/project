-- ============================================================
-- Миграция 004: очистка дублей и чужих студентов в оценках
-- ============================================================

-- Удаляем строки оценок, где студент не относится к группе своей записи оценок.
DELETE eg
FROM edu_grades eg
JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id
LEFT JOIN edu_students s ON s.id = eg.student_id AND s.group_id = gs.group_id
WHERE s.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_edu_grades_keep;

CREATE TEMPORARY TABLE tmp_edu_grades_keep AS
SELECT
    eg.grade_sheet_id,
    eg.student_id,
    COALESCE(
        MIN(CASE
            WHEN eg.grade IS NOT NULL
              OR eg.passed <> 0
              OR eg.absent <> 0
              OR COALESCE(eg.comment, '') <> ''
              OR eg.date IS NOT NULL
            THEN eg.id
        END),
        MIN(eg.id)
    ) AS keep_id
FROM edu_grades eg
JOIN edu_grade_sheets gs ON gs.id = eg.grade_sheet_id
JOIN edu_students s ON s.id = eg.student_id AND s.group_id = gs.group_id
GROUP BY eg.grade_sheet_id, eg.student_id;

-- Удаляем дубли внутри одной записи оценок, оставляя строку с введёнными данными.
DELETE eg
FROM edu_grades eg
JOIN tmp_edu_grades_keep k
  ON k.grade_sheet_id = eg.grade_sheet_id
 AND k.student_id = eg.student_id
WHERE eg.id <> k.keep_id;

DROP TEMPORARY TABLE IF EXISTS tmp_edu_grades_keep;

-- Индекс для быстрого поиска строк оценок по ведомости и студенту.
SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'edu_grades'
      AND INDEX_NAME = 'idx_eg_sheet_student'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE edu_grades ADD INDEX idx_eg_sheet_student (grade_sheet_id, student_id)',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
