<?php
require_once __DIR__ . '/baseModel.php';

class umr_pck extends baseModel {
    protected $table = 'umr_pck';

    //Вся таблица со связями
    public function getAllWithJoin(): array {
        $sql = "
            SELECT p.*,
                   u.full_name AS head_name,
                   d.name      AS department_name
            FROM umr_pck p
            LEFT JOIN users       u ON u.id = p.head_user_id
            LEFT JOIN departments d ON d.id = p.department_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Одна запись со связями
    public function findWithDetails(int $id): ?array {
        $sql ="
            SELECT p.*,
                   u.full_name AS head_name,
                   d.name      AS department_name
            FROM umr_pck p
            LEFT JOIN users       u ON u.id = p.head_user_id
            LEFT JOIN departments d ON d.id = p.department_id
            WHERE p.id = ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}