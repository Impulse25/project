<?php
// models/edu_semesters.php
 
require_once BASE_PATH . '/models/baseModel.php';
 
class edu_semesters extends baseModel
{
    protected string $table = 'edu_semesters';

    // Текущий семестр и учебный год
    public function getActive(string $date): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT year_start, year_end, semester_num
            FROM edu_semesters
            WHERE start_date <= ? AND end_date >= ?
            ORDER BY year_start DESC LIMIT 1
        ");
        $stmt->execute([$date, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
 
}