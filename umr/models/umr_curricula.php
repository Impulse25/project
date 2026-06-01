<?php
require_once __DIR__ . '/baseModel.php';

class umr_curricula extends baseModel {
    protected $table = 'umr_curricula';

    //Вся таблица со связями
    public function getAllWithJoin(?string $status = null): array {
        
        $where = $status ? 'WHERE c.status = :status' : '';

        $sql = "
        SELECT c.*, 
            s.name_ru as specialty_name, 
            s.code as specialty_code,
            u.full_name as approved_by_name
        FROM umr_curricula c
            JOIN edu_specialties s ON c.specialty_id = s.id
            LEFT JOIN users u ON c.approved_by = u.id
        $where
        ORDER BY c.year DESC, s.name_ru";
                
        $stmt = $this->pdo->prepare($sql);

        if ($status) $stmt->bindValue(':status', $status);
        
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Одна запись со связями
    public function findWithDetails(int $id): ?array {
       
        $sql = "
            SELECT c.*,
                s.name_ru  AS specialty_name,
                s.code     AS specialty_code,
                u.full_name AS approved_by_name
            FROM umr_curricula c
            JOIN edu_specialties s ON s.id = c.specialty_id
            LEFT JOIN users u      ON u.id = c.approved_by
            WHERE c.id = ?";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    //Количество по статусам
    public function countByStatus(): array {

        $sql="SELECT status, COUNT(*) as cnt FROM umr_curricula GROUP BY status";

        $rows   = $this->pdo->query($sql)
                            ->fetchAll(PDO::FETCH_ASSOC);

        $result = ['draft' => 0, 'approved' => 0, 'archived' => 0];
        
        foreach ($rows as $r) {
            $result[$r['status']] = (int)$r['cnt'];
        }

        $result['all'] = array_sum($result);
        
        return $result;
    }

    //Интерфейс
    public  function statusBadge(string $status): array {
        return match($status) {
            'approved' => ['Утверждён', 'green', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
            'archived' => ['Архив',     'gray', '<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>'],
            default    => ['Черновик',  'amber', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        };
    }
}