<?php
require_once __DIR__ . '/иaseModel.php';

class umr_module_permissions extends baseModel
{
    protected $table = 'umr_module_permissions';

    //Получить запись прав пользователя (с учётом PCK)
    public function getRecord(int $user_id, ?int $pck_id = null): ?array
    {
        $sql = "
            SELECT *
            FROM umr_module_permissions
            WHERE user_id = ?
              AND (pck_id = ? OR pck_id IS NULL)
            ORDER BY pck_id DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id, $pck_id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    //Универсальная проверка права
    public function can(int $user_id, string $permission, ?int $pck_id = null): bool
    {
        $row = $this->getRecord($user_id, $pck_id);

        if (!$row) {
            return false;
        }

        if (($row['role'] ?? null) === 'admin') {
            return true;
        }

        return (bool)($row[$permission] ?? false);
    }

    public function canView(int $user_id, ?int $pck_id = null): bool
    {
        return $this->can($user_id, 'can_view', $pck_id);
    }

    public function canCreate(int $user_id, ?int $pck_id = null): bool
    {
        return $this->can($user_id, 'can_create', $pck_id);
    }

    public function canEdit(int $user_id, ?int $pck_id = null): bool
    {
        return $this->can($user_id, 'can_edit', $pck_id);
    }

    public function canDelete(int $user_id, ?int $pck_id = null): bool
    {
        return $this->can($user_id, 'can_delete', $pck_id);
    }

    public function canApprove(int $user_id, ?int $pck_id = null): bool
    {
        return $this->can($user_id, 'can_approve', $pck_id);
    }

    public function canExport(int $user_id, ?int $pck_id = null): bool
    {
        return $this->can($user_id, 'can_export', $pck_id);
    }

    public function getRole(int $user_id, ?int $pck_id = null): string
    {
        $row = $this->getRecord($user_id, $pck_id);

        if (!empty($row['role'])) {
            return $row['role'];
        }

        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        return $stmt->fetchColumn() ?: 'teacher';
    }

    public function all(int $user_id, ?int $pck_id = null): array
    {
        return [
            'view'    => $this->canView($user_id, $pck_id),
            'create'  => $this->canCreate($user_id, $pck_id),
            'edit'    => $this->canEdit($user_id, $pck_id),
            'delete'  => $this->canDelete($user_id, $pck_id),
            'approve' => $this->canApprove($user_id, $pck_id),
            'export'  => $this->canExport($user_id, $pck_id),
            'role'    => $this->getRole($user_id, $pck_id),
        ];
    }

    public function require(int $user_id, string $permission, ?int $pck_id = null): void
    {
        if (!$this->can($user_id, $permission, $pck_id)) {
            http_response_code(403);
            die('<p style="font-family:sans-serif;color:red;padding:2rem">
                    Нет доступа
                </p>');
        }
    }
}