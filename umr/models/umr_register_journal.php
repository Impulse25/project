<?php
// models/umr_register_journal.php

require_once BASE_PATH . '/models/baseModel.php';

class umr_register_journal extends baseModel
{
    protected string $table = 'umr_register_journal';

    private function semCondition(int $filterYear, int $filterSem, array &$params): string
    {
        $sql = "g.year_started = (:fy - CEIL(ta.semester_num / 2) + 1)";
        $params[':fy'] = $filterYear;

        if ($filterSem !== 0) {
            $sql .= " AND ta.semester_num = :fsem";
            $params[':fsem'] = $filterSem;
        }

        return $sql;
    }

    private function roleCondition(bool $isAdmin, bool $isMethodist, bool $isPccHead, int $userId, array &$params): string
    {
        if ($isAdmin || $isMethodist || $isPccHead) {
            return '1=1';
        }
        $params[':uid'] = $userId;
        return 'ta.teacher_id = :uid';
    }

    // Список преподавателей для фильтра 
    public function getTeacherOptions(
        int $filterYear,
        int $filterSem,
        bool $isAdmin,
        bool $isMethodist,
        bool $isPccHead,
        int $userId
    ): array {
        $params = [];
        $semCond  = $this->semCondition($filterYear, $filterSem, $params);
        $roleCond = $this->roleCondition($isAdmin, $isMethodist, $isPccHead, $userId, $params);

        $sql = "
            SELECT DISTINCT u.id, u.full_name
            FROM umr_teacher_assignments ta
            JOIN users u      ON u.id = ta.teacher_id
            JOIN edu_groups g ON g.id = ta.group_id
            WHERE $semCond AND $roleCond
            ORDER BY u.full_name
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Список групп для фильтра
    public function getGroupOptions(int $filterYear, int $filterSem, int $filterTeacherId): array
    {
        $params = [];
        $semCond = $this->semCondition($filterYear, $filterSem, $params);

        $sql = "
            SELECT DISTINCT g.id, g.name
            FROM umr_teacher_assignments ta
            JOIN edu_groups g ON g.id = ta.group_id
            WHERE $semCond
        ";

        if ($filterTeacherId > 0) {
            $sql .= " AND ta.teacher_id = :tid";
            $params[':tid'] = $filterTeacherId;
        }

        $sql .= " ORDER BY g.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Утверждённые рабочие программы, ещё не зарегистрированные в журнале.
    public function getPendingWorkPrograms(
        int $filterYear,
        int $filterSem,
        bool $isAdmin,
        bool $isMethodist,
        bool $isPccHead,
        int $userId,
        int $filterTeacherId,
        int $filterGroupId
    ): array {
        $params = [];
        $semCond  = $this->semCondition($filterYear, $filterSem, $params);
        $roleCond = $this->roleCondition($isAdmin, $isMethodist, $isPccHead, $userId, $params);

        $sql = "
            SELECT
                wp.id AS wp_id, wp.version,
                ta.id AS assignment_id, ta.semester_num, ta.teacher_id,
                u.full_name AS teacher_name,
                ta.pcc_head_id, pcc.full_name AS pcc_name,
                g.id AS g_id, g.name AS group_name,
                m.index_code, m.name AS module_name, m.module_type
            FROM umr_work_programs wp
            JOIN umr_teacher_assignments ta ON ta.id = wp.assignment_id
            JOIN users u      ON u.id = ta.teacher_id
            JOIN users pcc    ON pcc.id = ta.pcc_head_id
            JOIN edu_groups g ON g.id = ta.group_id
            JOIN edu_curriculum_modules m ON m.id = ta.module_id
            WHERE wp.status = 'approved'
              AND $semCond AND $roleCond
              AND NOT EXISTS (
                  SELECT 1 FROM umr_register_journal rj WHERE rj.work_program_id = wp.id
              )
        ";

        if ($filterTeacherId > 0) {
            $sql .= " AND ta.teacher_id = :tid";
            $params[':tid'] = $filterTeacherId;
        }
        if ($filterGroupId > 0) {
            $sql .= " AND ta.group_id = :gid";
            $params[':gid'] = $filterGroupId;
        }

        $sql .= " ORDER BY g.name, ta.semester_num, m.sort_order";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Журнал
    public function getJournalRows(
        int $filterYear,
        ?int $onlyMineUserId,
        string $sortDir
    ): array {
        $params = [];
        $semCond = $this->semCondition($filterYear, 0, $params);

        $sql = "
            SELECT
                rj.id AS journal_id,
                rj.work_program_id,
                rj.registered_at,
                rj.created_at,
                creator.full_name AS created_by_name,
                wp.version, wp.file_path,
                ta.id AS assignment_id, ta.semester_num, ta.teacher_id,
                u.full_name AS teacher_name,
                ta.pcc_head_id, pcc.full_name AS pcc_name,
                g.id AS g_id, g.name AS group_name,
                m.index_code, m.name AS module_name, m.module_type
            FROM umr_register_journal rj
            JOIN umr_work_programs wp       ON wp.id  = rj.work_program_id
            JOIN umr_teacher_assignments ta ON ta.id  = wp.assignment_id
            JOIN users u        ON u.id   = ta.teacher_id
            JOIN users pcc      ON pcc.id = ta.pcc_head_id
            JOIN users creator  ON creator.id = rj.created_by
            JOIN edu_groups g   ON g.id   = ta.group_id
            JOIN edu_curriculum_modules m ON m.id = ta.module_id
            WHERE $semCond
        ";

        if ($onlyMineUserId !== null) {
            $sql .= " AND ta.teacher_id = :only_uid";
            $params[':only_uid'] = $onlyMineUserId;
        }

        $sql .= " ORDER BY " . ($sortDir === 'asc'
            ? 'rj.registered_at ASC, rj.created_at ASC'
            : 'rj.registered_at DESC, rj.created_at DESC');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRegistrationNumbers(int $filterYear): array
    {
        $params = [];
        $semCond = $this->semCondition($filterYear, 0, $params);

        $sql = "
            SELECT rj.id,
                   ROW_NUMBER() OVER (ORDER BY rj.registered_at ASC, rj.created_at ASC) AS reg_num
            FROM umr_register_journal rj
            JOIN umr_work_programs wp       ON wp.id = rj.work_program_id
            JOIN umr_teacher_assignments ta ON ta.id = wp.assignment_id
            JOIN edu_groups g ON g.id = ta.group_id
            WHERE $semCond
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $regNums = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $regNums[$row['id']] = (int)$row['reg_num'];
        }
        return $regNums;
    }

    public function getWorkProgramForRegistration(int $wpId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT wp.id, wp.status, ta.teacher_id, ta.pcc_head_id
            FROM umr_work_programs wp
            JOIN umr_teacher_assignments ta ON ta.id = wp.assignment_id
            WHERE wp.id = ?
        ");
        $stmt->execute([$wpId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function isAlreadyRegistered(int $wpId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM umr_register_journal WHERE work_program_id = ?");
        $stmt->execute([$wpId]);
        return (bool)$stmt->fetch();
    }

    public function registerWorkProgram(int $wpId, string $registeredAt, int $createdBy): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO umr_register_journal (work_program_id, registered_at, created_by)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$wpId, $registeredAt, $createdBy]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getJournalEntryForDeletion(int $journalId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT rj.id, ta.pcc_head_id
            FROM umr_register_journal rj
            JOIN umr_work_programs wp ON wp.id = rj.work_program_id
            JOIN umr_teacher_assignments ta ON ta.id = wp.assignment_id
            WHERE rj.id = ?
        ");
        $stmt->execute([$journalId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteJournalEntry(int $journalId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM umr_register_journal WHERE id = ?");
        return $stmt->execute([$journalId]);
    }

    public function getExportRows(int $filterYear): array
    {
        $params = [];
        $semCond = $this->semCondition($filterYear, 0, $params);

        $sql = "
            SELECT
                rj.registered_at,
                ROW_NUMBER() OVER (ORDER BY rj.registered_at ASC, rj.created_at ASC) AS reg_num,
                g.name AS group_name,
                ta.semester_num,
                m.index_code,
                m.name AS module_name,
                m.module_type,
                u.full_name AS teacher_name
            FROM umr_register_journal rj
            JOIN umr_work_programs wp       ON wp.id = rj.work_program_id
            JOIN umr_teacher_assignments ta ON ta.id = wp.assignment_id
            JOIN users u        ON u.id = ta.teacher_id
            JOIN edu_groups g   ON g.id = ta.group_id
            JOIN edu_curriculum_modules m ON m.id = ta.module_id
            WHERE $semCond
            ORDER BY rj.registered_at ASC, rj.created_at ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}