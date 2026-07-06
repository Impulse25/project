<?php
// hr/app/access.php — роль пользователя, область видимости и проверки доступа HR-модуля

if (!function_exists('hr_normalize_role')) {
    function hr_normalize_role($role): string
    {
        $role = trim((string)$role);
        $role = function_exists('mb_strtolower') ? mb_strtolower($role, 'UTF-8') : strtolower($role);
        $role = str_replace([' ', '-'], '_', $role);

        $map = [
            '1' => 'admin',
            '2' => 'director',
            '3' => 'teacher',
            '4' => 'technician',
            'admin' => 'admin',
            'administrator' => 'admin',
            'админ' => 'admin',
            'администратор' => 'admin',
            'director' => 'director',
            'директор' => 'director',
            'teacher' => 'teacher',
            'prepod' => 'teacher',
            'препод' => 'teacher',
            'преподаватель' => 'teacher',
            'технолог' => 'teacher',
        ];

        return $map[$role] ?? $role;
    }
}

if (!function_exists('hr_archive_limit_year')) {
    function hr_archive_limit_year(?int $currentYear = null): int
    {
        return ($currentYear ?: (int)date('Y')) - 5;
    }
}

if (!function_exists('hr_group_duration_expr')) {
    function hr_group_duration_expr(string $groupAlias = 'g'): string
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $groupAlias) ?: 'g';
        $curriculumDuration = "(SELECT ec.duration_years FROM edu_curricula ec WHERE ec.id = $alias.curriculum_id LIMIT 1)";

        return "(CASE
            WHEN $curriculumDuration IS NOT NULL AND CAST($curriculumDuration AS UNSIGNED) > 0 THEN CAST($curriculumDuration AS UNSIGNED)
            WHEN $alias.base_education = '9 класс' THEN 4
            WHEN $alias.base_education = '11 класс' THEN 3
            WHEN $alias.name LIKE '%-9-%' OR $alias.name REGEXP '-9[[:alpha:]]-' THEN 4
            WHEN $alias.name LIKE '%-11-%' OR $alias.name LIKE '%-3к-%' OR $alias.name LIKE '%-3К-%' THEN 3
            ELSE CAST($alias.course AS UNSIGNED)
        END)";
    }
}

if (!function_exists('hr_group_grad_expr')) {
    function hr_group_grad_expr(string $groupAlias = 'g'): string
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $groupAlias) ?: 'g';
        $durationExpr = hr_group_duration_expr($alias);
        return "(CAST($alias.year_started AS UNSIGNED) + $durationExpr)";
    }
}

if (!function_exists('hr_group_state_sql')) {
    function hr_group_state_sql(string $groupAlias = 'g', ?int $currentYear = null): string
    {
        $currentYear = $currentYear ?: (int)date('Y');
        $archiveLimit = hr_archive_limit_year($currentYear);
        $gradExpr = hr_group_grad_expr($groupAlias);

        return "CASE
            WHEN $gradExpr <= $archiveLimit THEN 'archive'
            WHEN $gradExpr < $currentYear THEN 'previous'
            ELSE 'current'
        END";
    }
}

if (!function_exists('hr_table_column_exists')) {
    function hr_table_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('hr_user_scope')) {
    function hr_user_scope(PDO $pdo, int $userId, string $role): array
    {
        $scope = [
            'role' => hr_normalize_role($role),
            'department_head' => false,
            'department_id' => null,
            'practice_head' => false,
        ];

        if ($userId <= 0) {
            return $scope;
        }

        $columns = ['id'];
        foreach (['is_department_head', 'head_department_id', 'is_practice_director'] as $column) {
            if (hr_table_column_exists($pdo, 'users', $column)) {
                $columns[] = $column;
            }
        }

        try {
            $stmt = $pdo->prepare('SELECT ' . implode(', ', $columns) . ' FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $scope;
        }

        $departmentId = (int)($user['head_department_id'] ?? 0);
        $scope['department_head'] = ((int)($user['is_department_head'] ?? 0) === 1) && $departmentId > 0;
        $scope['department_id'] = $scope['department_head'] ? $departmentId : null;
        $scope['practice_head'] = ((int)($user['is_practice_director'] ?? 0) === 1);

        return $scope;
    }
}

if (!function_exists('hr_apply_group_state_scope')) {
    function hr_apply_group_state_scope(array &$conditions, array &$params, string $groupAlias, string $viewMode, int $currentYear): void
    {
        $archiveLimit = hr_archive_limit_year($currentYear);
        $gradExpr = hr_group_grad_expr($groupAlias);
        $isGraduatesView = in_array($viewMode, ['graduates', 'previous'], true);

        if ($viewMode === 'archive') {
            $conditions[] = "$gradExpr <= ?";
            $params[] = $archiveLimit;
        } elseif ($isGraduatesView) {
            $conditions[] = "$gradExpr > ?";
            $conditions[] = "$gradExpr < ?";
            $params[] = $archiveLimit;
            $params[] = $currentYear;
        } elseif (in_array($viewMode, ['group', 'groups'], true)) {
            $conditions[] = "($gradExpr >= ? OR $groupAlias.year_started IS NULL OR $groupAlias.course IS NULL)";
            $params[] = $currentYear;
        }
    }
}

if (!function_exists('hr_scope_sql')) {
    /**
     * Возвращает SQL-условия и параметры для ограничения видимости HR-данных.
     * teacher видит только свои кураторские группы; director — отделения; admin — всё.
     */
    function hr_scope_sql(string $groupAlias, string $role, int $userId, string $viewMode, ?int $currentYear = null, array $hrScope = []): array
    {
        $role = hr_normalize_role($role);
        $currentYear = $currentYear ?: (int)date('Y');
        $archiveLimit = hr_archive_limit_year($currentYear);
        $gradExpr = hr_group_grad_expr($groupAlias);
        $departmentExpr = "COALESCE($groupAlias.department_id, sp.department_id)";

        $conditions = [];
        $params = [];

        $isGraduatesView = in_array($viewMode, ['graduates', 'previous'], true);

        if (!empty($hrScope['practice_head']) && $role !== 'admin') {
            hr_apply_group_state_scope($conditions, $params, $groupAlias, $viewMode, $currentYear);
        } elseif (!empty($hrScope['department_head']) && !empty($hrScope['department_id']) && $role !== 'admin') {
            $conditions[] = "$departmentExpr = ?";
            $params[] = (int)$hrScope['department_id'];
            hr_apply_group_state_scope($conditions, $params, $groupAlias, $viewMode, $currentYear);
        } elseif ($role === 'teacher') {
            $conditions[] = "$groupAlias.curator_id = ?";
            $params[] = $userId;

            if ($viewMode === 'archive') {
                $conditions[] = "$gradExpr <= ?";
                $params[] = $archiveLimit;
            } elseif ($isGraduatesView) {
                $conditions[] = "$gradExpr > ?";
                $conditions[] = "$gradExpr < ?";
                $params[] = $archiveLimit;
                $params[] = $currentYear;
            } else {
                $conditions[] = "($gradExpr >= ? OR $groupAlias.year_started IS NULL OR $groupAlias.course IS NULL)";
                $params[] = $currentYear;
            }
        } elseif ($role === 'admin') {
            if ($viewMode === 'archive') {
                $conditions[] = "$gradExpr <= ?";
                $params[] = $archiveLimit;
            } elseif ($isGraduatesView) {
                $conditions[] = "$gradExpr > ?";
                $conditions[] = "$gradExpr < ?";
                $params[] = $archiveLimit;
                $params[] = $currentYear;
            } elseif ($viewMode === 'groups') {
                $conditions[] = "($gradExpr >= ? OR $groupAlias.year_started IS NULL OR $groupAlias.course IS NULL)";
                $params[] = $currentYear;
            }
        }

        return [$conditions, $params];
    }
}

if (!function_exists('hr_allowed_views_for_role')) {
    function hr_allowed_views_for_role(string $role, array $hrScope = []): array
    {
        $role = hr_normalize_role($role);
        if ($role === 'admin') {
            return ['all', 'groups', 'graduates', 'departments', 'archive'];
        }
        if (!empty($hrScope['practice_head']) || !empty($hrScope['department_head'])) {
            return ['departments'];
        }
        if ($role === 'director') {
            return ['departments'];
        }
        if ($role === 'teacher') {
            return ['group', 'graduates', 'archive'];
        }
        return [];
    }
}

if (!function_exists('hr_default_view_for_role')) {
    function hr_default_view_for_role(string $role, array $hrScope = []): string
    {
        $role = hr_normalize_role($role);
        if ($role !== 'admin' && (!empty($hrScope['practice_head']) || !empty($hrScope['department_head']))) {
            return 'departments';
        }
        return match ($role) {
            'admin' => 'all',
            'director' => 'departments',
            'teacher' => 'group',
            default => 'all',
        };
    }
}

if (!function_exists('hr_user_can_manage_student')) {
    function hr_user_can_manage_student(PDO $pdo, int $studentId, int $userId, string $role): bool
    {
        $role = hr_normalize_role($role);
        if ($role === 'admin') {
            return true;
        }
        if ($role !== 'teacher' || $studentId <= 0 || $userId <= 0) {
            return false;
        }

        $archiveLimit = hr_archive_limit_year();
        $gradExpr = hr_group_grad_expr('g');
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM edu_students s
            JOIN edu_groups g ON g.id = s.group_id
            WHERE s.id = ?
              AND g.curator_id = ?
              AND ($gradExpr > ? OR g.year_started IS NULL OR g.course IS NULL)
        ");
        $stmt->execute([$studentId, $userId, $archiveLimit]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('hr_user_can_view_student')) {
    function hr_user_can_view_student(PDO $pdo, int $studentId, int $userId, string $role, array $hrScope = []): bool
    {
        $role = hr_normalize_role($role);
        if (in_array($role, ['admin', 'director'], true)) {
            return true;
        }
        if ($studentId <= 0 || $userId <= 0) {
            return false;
        }

        // Замдиректора по практике видит студентов всех отделений (см. hr_scope_sql).
        if (!empty($hrScope['practice_head'])) {
            return true;
        }

        // Заведующий отделением — только студентов своего отделения.
        if (!empty($hrScope['department_head']) && !empty($hrScope['department_id'])) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM edu_students s
                JOIN edu_groups g ON g.id = s.group_id
                LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
                WHERE s.id = ? AND COALESCE(g.department_id, sp.department_id) = ?
            ");
            $stmt->execute([$studentId, (int)$hrScope['department_id']]);
            return (int)$stmt->fetchColumn() > 0;
        }

        if ($role !== 'teacher') {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM edu_students s
            JOIN edu_groups g ON g.id = s.group_id
            WHERE s.id = ? AND g.curator_id = ?
        ");
        $stmt->execute([$studentId, $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('hr_user_can_manage_record')) {
    function hr_user_can_manage_record(PDO $pdo, int $recordId, int $userId, string $role): bool
    {
        if ($recordId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT student_id FROM hr_employment WHERE id = ?');
        $stmt->execute([$recordId]);
        $studentId = (int)$stmt->fetchColumn();
        return $studentId > 0 && hr_user_can_manage_student($pdo, $studentId, $userId, $role);
    }
}

if (!function_exists('hr_user_can_view_record')) {
    function hr_user_can_view_record(PDO $pdo, int $recordId, int $userId, string $role, array $hrScope = []): bool
    {
        if ($recordId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT student_id FROM hr_employment WHERE id = ?');
        $stmt->execute([$recordId]);
        $studentId = (int)$stmt->fetchColumn();
        return $studentId > 0 && hr_user_can_view_student($pdo, $studentId, $userId, $role, $hrScope);
    }
}

if (!function_exists('hr_user_can_view_document')) {
    function hr_user_can_view_document(PDO $pdo, int $documentId, int $userId, string $role, array $hrScope = []): bool
    {
        if ($documentId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT employment_id FROM hr_documents WHERE id = ?');
        $stmt->execute([$documentId]);
        $employmentId = (int)$stmt->fetchColumn();
        return $employmentId > 0 && hr_user_can_view_record($pdo, $employmentId, $userId, $role, $hrScope);
    }
}
