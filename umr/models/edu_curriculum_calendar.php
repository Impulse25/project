<?php
// models/edu_curriculum_calendar.php

require_once BASE_PATH . '/models/baseModel.php';

class edu_curriculum_calendar extends baseModel
{
    protected string $table = 'edu_groups';

    public static function courseLabel(int $course): ?string
    {
        if ($course < 1) {
            return null;
        }

        // римское представление
        $values = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
        $result = '';
        $n = $course;
        foreach ($values as $value => $numeral) {
            while ($n >= $value) {
                $result .= $numeral;
                $n -= $value;
            }
        }
        return $result;
    }

    //Группы, у которых на момент учебного года $filterYear есть действующий курс
    public function getActiveGroupsForYear(int $filterYear, int $filterGroupId = 0, string $sortDir = 'asc'): array
    {
        $dir = $sortDir === 'desc' ? 'DESC' : 'ASC';

        $sql = "
            SELECT
                g.id, g.name, g.year_started, g.curriculum_id,
                c.specialty_name, c.specialty_code, c.name AS curriculum_name,
                c.duration_years,
                (:filter_year1 - CAST(g.year_started AS SIGNED) + 1) AS current_course
            FROM edu_groups g
            JOIN edu_curricula c ON c.id = g.curriculum_id
            WHERE g.curriculum_id IS NOT NULL
              AND g.year_started <= :filter_year2
              AND (g.year_started + c.duration_years) > :filter_year3
        ";
        $params = [
            ':filter_year1' => $filterYear,
            ':filter_year2' => $filterYear,
            ':filter_year3' => $filterYear,
        ];

        if ($filterGroupId > 0) {
            $sql .= " AND g.id = :group_id";
            $params[':group_id'] = $filterGroupId;
        }

        $sql .= " ORDER BY g.name {$dir}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScheduleRows(int $curriculumId, string $courseLabel): array
    {
        $stmt = $this->pdo->prepare("
            SELECT week_num, month_name, value_text, span_weeks
            FROM edu_curriculum_process_schedule
            WHERE curriculum_id = ?
              AND course_label = ?
            ORDER BY week_num
        ");
        $stmt->execute([$curriculumId, $courseLabel]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int)$row['week_num']] = $row;
        }
        return $rows;
    }

    public function getMonthMapForCurricula(array $curriculumIds): array
    {
        if (empty($curriculumIds)) return [];

        $in = implode(',', array_fill(0, count($curriculumIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT week_num, month_name
            FROM edu_curriculum_process_schedule
            WHERE curriculum_id IN ($in)
              AND month_name <> ''
            GROUP BY week_num, month_name
            ORDER BY week_num
        ");
        $stmt->execute($curriculumIds);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $w = (int)$row['week_num'];
            if (!isset($map[$w])) {
                $map[$w] = $row['month_name'];
            }
        }
        return $map;
    }

    public function getLegendForCurricula(array $curriculumIds): array
    {
        if (empty($curriculumIds)) return [];

        $in = implode(',', array_fill(0, count($curriculumIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT code, description, MIN(sort_order) AS sort_order
            FROM edu_curriculum_process_legend
            WHERE curriculum_id IN ($in)
            GROUP BY code, description
            ORDER BY sort_order, code
        ");
        $stmt->execute($curriculumIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}