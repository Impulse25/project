<?php
// models/users.php
 
require_once BASE_PATH . '/models/baseModel.php';
 
class users extends baseModel
{
    protected string $table = 'users';

    //Все имена users
    public function getUsers(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, full_name FROM users
            ORDER BY full_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Все имена users = преподаватель
    public function getTeachers(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, full_name FROM users
            WHERE role = 'teacher'
            ORDER BY full_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    //Все имена users = ПЦК
    public function getPccHeads(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, full_name FROM users
            WHERE is_pcc_head = 1
            ORDER BY full_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
 
}