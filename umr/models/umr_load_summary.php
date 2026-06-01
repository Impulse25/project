<?php
require_once __DIR__ . '/baseModel.php';

class umr_load_summary extends baseModel {
    protected $table = 'umr_load_summary';

    //Сводка за учебный год все преподаватели
    public function getByYear(int $year): ?array{
        $stmt = $this->pdo->prepare("
            SELECT ls.*,
                   u.full_name AS teacher_name,
                   u.position  AS teacher_position
                   s.semester_num AS semester
            FROM umr_load_summary ls
            LEFT JOIN users u ON u.id = ls.teacher_id
            LEFT JOIN edu_semesters s ON s.id = ls.semester_id
            WHERE ls.year = ?
            ORDER BY ls.semester, u.full_name
        ");
        $stmt->execute([$year]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    //Сводка одного преподавателя за все годы
    public function getByTeacher(int $teacher_id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT ls.*,
                s.semester_num AS semester
            FROM umr_load_summary ls
            LEFT JOIN edu_semesters s ON s.id = ls.semester_id
            WHERE teacher_id = ?
            ORDER BY year DESC, semester
        ");

        $stmt->execute([$teacher_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
