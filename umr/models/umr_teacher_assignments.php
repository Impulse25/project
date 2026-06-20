<?php
// models/umr_teacher_assignments.php
 
require_once BASE_PATH . '/models/baseModel.php';
 
class umr_teacher_assignments extends baseModel
{
    protected string $table = 'umr_teacher_assignments';
    public const MAX_SEMESTER_NUM = 8;

    //Группы с учебным планом для нужного семестра и года поступления
    public function getGroupsBySemester(int $neededCourse, int $sem, int $neededYearStart, string $sortDir = 'asc'): array
    {
        $dir = $sortDir === 'desc' ? 'DESC' : 'ASC';
        $stmt = $this->pdo->prepare("
            SELECT
                g.id, g.name, g.year_started, g.curriculum_id, g.course,
                c.specialty_name, c.specialty_code, c.name AS curriculum_name,
                c.duration_years,
                :needed_course AS current_course,
                :filter_sem    AS current_semester
            FROM edu_groups g
            JOIN edu_curricula c ON c.id = g.curriculum_id
            WHERE g.year_started = :needed_year_start
              AND g.curriculum_id IS NOT NULL
            ORDER BY g.name $dir
        ");
        $stmt->execute([
            ':needed_course'     => $neededCourse,
            ':filter_sem'        => $sem,
            ':needed_year_start' => $neededYearStart,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Модули учебного плана для группы в семестре
     public function getModulesByCurriculumAndSemester(int $curriculumId, int $sem): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                m.id, m.index_code, m.name, m.module_type,
                m.total_hours, m.theory_hours, m.practice_hours,
                m.credits,
                m.coursework_hours,
                m.srsp_hours,
                m.srs_hours,
                m.exam_semester,
                m.credit_semester,
                m.control_work,
                d.hours AS semester_hours
            FROM edu_curriculum_distribution d
            JOIN edu_curriculum_modules m ON m.id = d.module_id
            WHERE m.curriculum_id = ?
              AND d.semester_num = ?
              AND m.is_summary = 0
              AND m.module_type NOT IN ('ИТОГО','ИА','ДП')
            ORDER BY m.sort_order
        ");
        $stmt->execute([$curriculumId, $sem]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Назначения преподавателей
    public function getAssignmentsByModules(array $moduleIds, int $groupId, int $sem): array
    {
        if (empty($moduleIds)) return [];

        $in     = implode(',', array_fill(0, count($moduleIds), '?'));
        $params = array_merge($moduleIds, [$groupId, $sem]);

        $stmt = $this->pdo->prepare("
            SELECT ta.id AS assignment_id, ta.module_id,
                   ta.teacher_id, u.full_name AS teacher_name,
                   ta.pcc_head_id, p.full_name AS pcc_name
            FROM umr_teacher_assignments ta
            JOIN users u ON u.id = ta.teacher_id
            LEFT JOIN users p ON p.id = ta.pcc_head_id
            WHERE ta.module_id IN ($in)
              AND ta.group_id = ?
              AND ta.semester_num = ?
            ORDER BY ta.created_at
        ");
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['module_id']][] = $row;
        }
        return $result;
    }
}