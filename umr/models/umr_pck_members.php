<?php
require_once __DIR__ . '/baseModel.php';

class umr_pck_members extends baseModel {
    protected $table = 'umr_pck_members';

    //Состав по ПЦК
    public function getByPck(int $pck_id): ?array {
        $sql = "
            SELECT pm.*, 
                u.full_name, 
                u.position 
            FROM umr_pck_members pm
            JOIN users u ON pm.user_id = u.id
            WHERE pm.pck_id = ?
            ORDER BY pm.role DESC, u.full_name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pck_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Убрать пользователя из ПЦК
    public function removeFromPck($pck_id, $user_id) : bool {
        $sql = "DELETE FROM umr_pck_members WHERE pck_id = ? AND user_id = ?";
       
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$pck_id, $user_id]);
    }

    //Уже ли пользователь в ПЦК
    public function exists($pck_id, $user_id) : bool {
        $sql ="SELECT COUNT(*) FROM umr_pck_members WHERE pck_id = ? AND user_id = ?";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$pck_id, $user_id]);
        return (bool)$stmt->fetchColumn();
    }

    //Интерфейс
    public static function roleLabel($role) : array {
        return match($role) {
            'chair'     => 'Председатель',
            'secretary' => 'Секретарь',
            default     => 'Член комиссии',
        };
    }
}