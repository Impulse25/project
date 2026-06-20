<?php
// models/edu_groups.php
 
require_once BASE_PATH . '/models/baseModel.php';
 
class edu_groups extends baseModel
{
    protected string $table = 'edu_groups';

    //Все учебный года year_started+1
    public function getAcademicYears(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT year_started FROM edu_groups
            WHERE curriculum_id IS NOT NULL
            ORDER BY year_started DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
 
}