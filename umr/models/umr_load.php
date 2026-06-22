<?php
// models/umr_load.php

require_once BASE_PATH . '/models/baseModel.php';

class umr_load extends baseModel
{
    protected string $table = 'umr_teacher_assignments';

    public function getTeacherName(int $teacherId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$teacherId]);
        $name = $stmt->fetchColumn();
        return $name !== false ? $name : null;
    }

    // Все учебные года в которых у преподавателя есть хотя бы одно назначение
    public function getYearsForTeacher(int $teacherId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT
                g.year_started + FLOOR((ta.semester_num - 1) / 2) AS academic_year
            FROM umr_teacher_assignments ta
            JOIN edu_groups g ON g.id = ta.group_id
            WHERE ta.teacher_id = ?
            ORDER BY academic_year DESC
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Все преподаватели колледжа (для фильтра admin / методиста / pcc_head)
    public function getAllTeachers(): array
    {
        return $this->pdo
            ->query("SELECT id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    // Нагрузка преподавателя за учебный год (часы по 1/2 семестру и итого), отсортированная по группам.
    /*
     * UNION ALL из двух частей:
     *   1) Дисциплины с нечётным 1 семестром как якорем — к ним LEFT JOIN чётный.
     *   2) Дисциплины, у которых ТОЛЬКО чётный семестр (без нечётного), чтобы не потерять.
     */
    public function getTeacherLoad(int $teacherId, int $filterYear): array
    {

        $sql = "
            SELECT
                m.id           AS module_id,
                m.index_code,
                m.name         AS module_name,
                m.module_type,
                m.theory_hours,
                m.practice_hours,
                m.total_hours,
                d_odd.hours    AS hours_odd,
                ta_odd.semester_num AS sem_odd,
                d_even.hours   AS hours_even,
                ta_even.semester_num AS sem_even,
                g.id           AS group_id,
                g.name         AS group_name,
                g.year_started,
                (CAST(:fy1 AS SIGNED) - CAST(g.year_started AS SIGNED) + 1) AS course_num,
                ph.full_name   AS pcc_name
            FROM umr_teacher_assignments ta_odd
            JOIN edu_groups g             ON g.id  = ta_odd.group_id
            JOIN edu_curriculum_modules m ON m.id  = ta_odd.module_id
            JOIN edu_curriculum_distribution d_odd
                   ON d_odd.module_id    = m.id
                  AND d_odd.semester_num = ta_odd.semester_num
            LEFT JOIN umr_teacher_assignments ta_even
                   ON ta_even.module_id    = ta_odd.module_id
                  AND ta_even.group_id     = ta_odd.group_id
                  AND ta_even.teacher_id   = ta_odd.teacher_id
                  AND ta_even.semester_num = ta_odd.semester_num + 1
            LEFT JOIN edu_curriculum_distribution d_even
                   ON d_even.module_id    = m.id
                  AND d_even.semester_num = ta_odd.semester_num + 1
            JOIN users ph ON ph.id = ta_odd.pcc_head_id
            WHERE ta_odd.teacher_id = :tid1
              AND g.year_started <= :fy_guard1
              AND ta_odd.semester_num % 2 = 1
              AND (CAST(:fy2 AS SIGNED) - CAST(g.year_started AS SIGNED) + 1) BETWEEN 1 AND 8
              AND ta_odd.semester_num = (CAST(:fy3 AS SIGNED) - CAST(g.year_started AS SIGNED) + 1) * 2 - 1

            UNION ALL

            SELECT
                m.id, m.index_code, m.name, m.module_type,
                m.theory_hours, m.practice_hours, m.total_hours,
                NULL AS hours_odd,  NULL AS sem_odd,
                d_even.hours AS hours_even, ta_even.semester_num AS sem_even,
                g.id, g.name, g.year_started,
                (CAST(:fy4 AS SIGNED) - CAST(g.year_started AS SIGNED) + 1) AS course_num,
                ph.full_name
            FROM umr_teacher_assignments ta_even
            JOIN edu_groups g             ON g.id  = ta_even.group_id
            JOIN edu_curriculum_modules m ON m.id  = ta_even.module_id
            JOIN edu_curriculum_distribution d_even
                   ON d_even.module_id    = m.id
                  AND d_even.semester_num = ta_even.semester_num
            JOIN users ph ON ph.id = ta_even.pcc_head_id
            WHERE ta_even.teacher_id = :tid2
              AND g.year_started <= :fy_guard2
              AND ta_even.semester_num % 2 = 0
              AND (CAST(:fy5 AS SIGNED) - CAST(g.year_started AS SIGNED) + 1) BETWEEN 1 AND 8
              AND ta_even.semester_num = (CAST(:fy6 AS SIGNED) - CAST(g.year_started AS SIGNED) + 1) * 2
              AND NOT EXISTS (
                  SELECT 1 FROM umr_teacher_assignments x
                  WHERE x.module_id    = ta_even.module_id
                    AND x.group_id     = ta_even.group_id
                    AND x.teacher_id   = ta_even.teacher_id
                    AND x.semester_num = ta_even.semester_num - 1
              )

            ORDER BY year_started DESC, group_name, module_type, index_code
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':fy1'       => $filterYear,
            ':tid1'      => $teacherId,
            ':fy_guard1' => $filterYear,
            ':fy2'       => $filterYear,
            ':fy3'       => $filterYear,
            ':fy4'       => $filterYear,
            ':tid2'      => $teacherId,
            ':fy_guard2' => $filterYear,
            ':fy5'       => $filterYear,
            ':fy6'       => $filterYear,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
