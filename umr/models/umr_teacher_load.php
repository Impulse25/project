<?php
require_once __DIR__ . '/baseModel.php';

class umr_teacher_load extends baseModel {
    protected $table = 'umr_teacher_load';

    public function getTeacherLoad(int $teacher_id, ?int $year = null, ?int $semester = null): array {
        $sql = "
            SELECT tl.*,
                u.full_name AS teacher_name,
                sm.semester_num AS semester_num,
                sm.year_start,
                sm.year_end,
                sb.name_ru as subject_name, 
                g.name as group_name
            FROM umr_teacher_load tl
            JOIN users u ON tl.teacher_id = u.id
            LEFT JOIN edu_semesters sm ON tl.semester_id = sm.id
            JOIN edu_subjects sb ON tl.subject_id = sb.id
            JOIN edu_groups g ON tl.group_id = g.id
            WHERE tl.teacher_id = ?";
        

        $params = [$teacher_id];

        if ($year !== null) {
            $sql .= " AND sm.year_start = ?";
            $params[] = $year;
        }

        if ($semester !== null) {
            $sql .= " AND sm.semester_num = ?";
            $params[] = $semester;
        }

        $sql .= " ORDER BY sm.year_start DESC, sm.semester_num DESC, sb.name_ru";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Нагрузка всех преподавателей за семестр
    public function getAllBySemester(int $semester_id): array {
        $sql = "
            SELECT tl.*,
                u.full_name AS teacher_name,
                u.position AS teacher_position,
                sm.semester_num,
                sm.year_start,
                sm.year_end,
                sb.name_ru AS subject_name,
                g.name AS group_name
            FROM umr_teacher_load tl
            JOIN users u ON u.id = tl.teacher_id
            LEFT JOIN edu_semesters sm ON sm.id = tl.semester_id
            JOIN edu_subjects sb ON sb.id = tl.subject_id
            JOIN edu_groups g ON g.id = tl.group_id
            WHERE tl.semester_id = ?
            ORDER BY u.full_name, sb.name_ru";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$semester_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Суммарные часы преподавателя за семестр
    public function getTotalHours(int $teacher_id, int $semester_id): int {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(hours), 0)
            FROM umr_teacher_load
            WHERE teacher_id = ? AND semester_id = ?
        ");
        $stmt->execute([$teacher_id, $semester_id]);
        return (int)$stmt->fetchColumn();
    }

    //Расчеты ставки
    public function recalcSummary($teacher_id, $semester_id) {
        $total = $this->getTotalHours($teacher_id, $semester_id);
        $rate  = $total > 0 ? round($total / 720, 2) : 0;

        $sem = $this->pdo->prepare("SELECT year_start FROM edu_semesters WHERE id = ?");
        $sem->execute([$semester_id]);
        $semData = $sem->fetch(PDO::FETCH_ASSOC);

        $this->pdo->prepare("
            INSERT INTO umr_load_summary (teacher_id, semester_id, year, total_hours, rate)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_hours = VALUES(total_hours),
                rate        = VALUES(rate)
        ")->execute([
            $teacher_id,
            $semester_id,
            $semData['year_start'],
            $total,
            $rate,
        ]);
    }

    //Интерфейс
    public static function loadTypeLabel($type) {
        return match($type) {
            'practice' => 'Практика',
            'self'     => 'Самост. работа',
            default    => 'Лекции',
        };
    }
}