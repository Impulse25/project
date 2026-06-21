<?php
// models/umr_work_programs.php

require_once BASE_PATH . '/models/baseModel.php';

class umr_work_programs extends baseModel
{
    protected string $table = 'umr_work_programs';

    // Назначения преподавателей вместе с модулями РУПл и текущим статусом
    public function getAssignmentsWithWorkPrograms(
        int $filterYear,
        int $filterSem,
        int $filterGroupId,
        int $filterTeacherId,
        bool $isAdmin,
        bool $isPccHead,
        bool $isMethodist,
        int $userId
    ): array {
        $semCondSql = "g.year_started = (:fy - CEIL(ta.semester_num / 2) + 1)";
        $params = [':fy' => $filterYear];

        if ($filterSem !== 0) {
            $semCondSql .= " AND ta.semester_num = :fsem";
            $params[':fsem'] = $filterSem;
        }


        $hasFullAccess = $isAdmin || $isPccHead || $isMethodist;

        $roleCondSql = $hasFullAccess ? '1=1' : 'ta.teacher_id = :uid';
        if (!$hasFullAccess) {
            $params[':uid'] = $userId;
        }

        $groupCondSql = '';
        if ($filterGroupId > 0) {
            $groupCondSql = ' AND g.id = :gid';
            $params[':gid'] = $filterGroupId;
        }

        $teacherCondSql = '';
        if ($filterTeacherId > 0) {
            $teacherCondSql = ' AND ta.teacher_id = :tid';
            $params[':tid'] = $filterTeacherId;
        }

        $sql = "
            SELECT
                ta.id            AS assignment_id,
                ta.module_id, ta.group_id, ta.semester_num,
                ta.teacher_id,   u.full_name   AS teacher_name,
                ta.pcc_head_id,  pcc.full_name AS pcc_name,
                g.id AS g_id, g.name AS group_name, g.year_started, g.course,
                m.index_code, m.name AS module_name, m.module_type, m.sort_order,
                m.credits, m.total_hours, m.theory_hours, m.practice_hours,
                m.coursework_hours, m.srsp_hours, m.srs_hours,
                m.exam_semester, m.credit_semester,
                m.control_work,
                d.hours AS semester_hours,
                wp.id AS wp_id, wp.version AS wp_version, wp.status AS wp_status,
                wp.file_path AS wp_file_path, wp.reject_reason AS wp_reject_reason
            FROM umr_teacher_assignments ta
            JOIN users u      ON u.id  = ta.teacher_id
            JOIN users pcc    ON pcc.id = ta.pcc_head_id
            JOIN edu_groups g ON g.id  = ta.group_id
            JOIN edu_curriculum_modules m ON m.id = ta.module_id
            LEFT JOIN edu_curriculum_distribution d
                   ON d.module_id = m.id AND d.semester_num = ta.semester_num
            LEFT JOIN umr_work_programs wp ON wp.assignment_id = ta.id
            WHERE $semCondSql
              AND $roleCondSql
              $groupCondSql
              $teacherCondSql
            ORDER BY g.name ASC, ta.semester_num ASC, m.sort_order, m.id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Группы, у которых есть хотя бы одно назначение в выбранном учебном году
    public function getGroupsWithAssignments(
        int $filterYear,
        bool $isAdmin,
        bool $isPccHead,
        bool $isMethodist,
        int $userId
    ): array {
        $params = [':fy' => $filterYear];

        $hasFullAccess = $isAdmin || $isPccHead || $isMethodist;
        $roleCondSql = $hasFullAccess ? '1=1' : 'ta.teacher_id = :uid';
        if (!$hasFullAccess) {
            $params[':uid'] = $userId;
        }

        $sql = "
            SELECT DISTINCT g.id, g.name
            FROM umr_teacher_assignments ta
            JOIN edu_groups g ON g.id = ta.group_id
            WHERE g.year_started = (:fy - CEIL(ta.semester_num / 2) + 1)
              AND $roleCondSql
            ORDER BY g.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAssignmentById(int $assignmentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, teacher_id, pcc_head_id, group_id, module_id, semester_num
            FROM umr_teacher_assignments
            WHERE id = ?
        ");
        $stmt->execute([$assignmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function reassignPccHead(int $assignmentId, int $newPccHeadId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE umr_teacher_assignments
            SET pcc_head_id = ?
            WHERE id = ?
        ");
        return $stmt->execute([$newPccHeadId, $assignmentId]);
    }
}