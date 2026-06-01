<?php
require_once __DIR__ . '/baseModel.php';

class umr_curriculum_subjects extends baseModel {
    protected $table = 'umr_curriculum_subjects';

    //Вся таблица со связями
    public function getAllWithJoin(): array {

        $sql = "
        SELECT cs.*, 
            sb.name_ru as subject,
            sm.semester_num as semester
        FROM umr_curriculum_subjects cs
            JOIN edu_subjects sb ON cs.subject_id = sb.id
            LEFT JOIN edu_semesters sm ON cs.semester_id = sm.id
            JOIN umr_curricula s ON cs.curriculum_id = s.id
        ORDER BY cs.id DESC, sb.name_ru";
                
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Одна запись со связями
    public function findWithDetails(int $id): ?array {
       
    $sql = "
        SELECT cs.*, 
            sb.name_ru as subject,
            sm.semester_num as semester
        FROM umr_curriculum_subjects cs
            JOIN edu_subjects sb ON cs.subject_id = sb.id
            LEFT JOIN edu_semesters sm ON cs.semester_id = sm.id
            JOIN umr_curricula s ON cs.curriculum_id = s.id
        WHERE cs.id = ?";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

}