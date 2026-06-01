<?php
require_once __DIR__ . '/baseModel.php';

class umr_work_programs extends baseModel {
    protected $table = 'umr_work_programs';

    //Вся таблица со связями
    public function getAllWithJoin(?int $pck_id = null): ?array {

        $where = $pck_id ? "WHERE wp.pck_id = " . (int)$pck_id : '';

        $sql="
            SELECT wp.*,
                s.name_ru    AS subject_name,
                s.code       AS subject_code,
                p.name_ru    AS pck_name,
                a.full_name  AS author_name,
                ab.full_name AS approved_by_name
            FROM umr_work_programs wp
            JOIN edu_subjects s  ON s.id  = wp.subject_id
            JOIN umr_pck     p  ON p.id  = wp.pck_id
            JOIN users       a  ON a.id  = wp.author_id
            LEFT JOIN users  ab ON ab.id = wp.approved_by
            $where
            ORDER BY wp.created_at DESC";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

   //Одна запись со связями
    public function findWithDetails(int $id): ?array {
        $sql="
            SELECT wp.*,
                s.name_ru    AS subject_name,
                s.code       AS subject_code,
                p.name_ru    AS pck_name,
                a.full_name  AS author_name,
                ab.full_name AS approved_by_name
            FROM umr_work_programs wp
            JOIN edu_subjects s  ON s.id  = wp.subject_id
            JOIN umr_pck     p  ON p.id  = wp.pck_id
            JOIN users       a  ON a.id  = wp.author_id
            LEFT JOIN users  ab ON ab.id = wp.approved_by
            WHERE wp.id = ?";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // РПД автора
    public function getByAuthor(int $author_id): ?array {
        $sql="
            SELECT wp.*,
                s.name_ru AS subject_name,
                p.name_ru AS pck_name
            FROM umr_work_programs wp
            JOIN edu_subjects s ON s.id = wp.subject_id
            JOIN umr_pck     p ON p.id = wp.pck_id
            WHERE wp.author_id = ?
            ORDER BY wp.created_at DESC";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$author_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Отправить на утверждение
    public function submit($id) {
        return $this->update($id, ['status' => 'submitted']);
    }

    //Утвердить
    public function approve($id, $user_id) {
        return $this->update($id, [
            'status'      => 'approved',
            'approved_by' => $user_id,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    //Отклонить (вернуть в черновик)
    public function reject($id) {
        return $this->update($id, ['status' => 'rejected']);
    }

    //Интерфейс
    public static function statusBadge($status) {
        return match($status) {
            'submitted' => ['На утверждении', 'info'],
            'approved'  => ['Утверждена',     'success'],
            'rejected'  => ['Отклонена',      'danger'],
            default     => ['Черновик',        'warning'],
        };
    }
}
