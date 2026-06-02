-- Изменения БД для актуальной версии edu.
-- В приложенном дампе основные связи уже есть, поэтому ниже только безопасные/рекомендуемые проверки для существующей базы.

-- 1) Оценка хранится как 0..100.
ALTER TABLE edu_grades
    MODIFY grade TINYINT UNSIGNED NULL;

-- Если MySQL 8.0.16+, можно добавить ограничение диапазона.
-- Если ограничение уже есть или версия MySQL старая, этот запрос можно пропустить.
ALTER TABLE edu_grades
    ADD CONSTRAINT chk_edu_grades_grade_0_100 CHECK (grade IS NULL OR (grade >= 0 AND grade <= 100));

-- 2) Быстрый выбор групп преподавателя-куратора.
ALTER TABLE edu_groups
    ADD INDEX idx_edu_groups_curator_id (curator_id);

-- 3) Личная карточка должна удаляться вместе со студентом.
-- В приложенном дампе fk_card_student уже ON DELETE CASCADE. Для старой базы пересоздать так:
ALTER TABLE edu_student_cards
    DROP FOREIGN KEY fk_card_student;

ALTER TABLE edu_student_cards
    ADD CONSTRAINT fk_card_student
    FOREIGN KEY (student_id) REFERENCES edu_students(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- 4) Для корректного ON DUPLICATE KEY UPDATE в карточке студента.
-- В приложенном дампе UNIQUE KEY student_id уже есть. Для старой базы добавить так:
ALTER TABLE edu_student_cards
    ADD UNIQUE KEY uq_edu_student_cards_student_id (student_id);
