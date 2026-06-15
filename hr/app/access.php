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

if (!function_exists('hr_scope_sql')) {
    /**
     * Возвращает SQL-условия и параметры для ограничения видимости HR-данных.
     * teacher видит только свои кураторские группы; director — отделения; admin — всё.
     */
    function hr_scope_sql(string $groupAlias, string $role, int $userId, string $viewMode, ?int $currentYear = null): array
    {
        $role = hr_normalize_role($role);
        $currentYear = $currentYear ?: (int)date('Y');
        $archiveLimit = hr_archive_limit_year($currentYear);
        $gradExpr = hr_group_grad_expr($groupAlias);

        $conditions = [];
        $params = [];

        $isGraduatesView = in_array($viewMode, ['graduates', 'previous'], true);

        if ($role === 'teacher') {
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
    function hr_allowed_views_for_role(string $role): array
    {
        $role = hr_normalize_role($role);
        if ($role === 'admin') {
            return ['all', 'groups', 'graduates', 'departments', 'archive'];
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
    function hr_default_view_for_role(string $role): string
    {
        $role = hr_normalize_role($role);
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
    function hr_user_can_view_student(PDO $pdo, int $studentId, int $userId, string $role): bool
    {
        $role = hr_normalize_role($role);
        if (in_array($role, ['admin', 'director'], true)) {
            return true;
        }
        if ($role !== 'teacher' || $studentId <= 0 || $userId <= 0) {
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
    function hr_user_can_view_record(PDO $pdo, int $recordId, int $userId, string $role): bool
    {
        if ($recordId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT student_id FROM hr_employment WHERE id = ?');
        $stmt->execute([$recordId]);
        $studentId = (int)$stmt->fetchColumn();
        return $studentId > 0 && hr_user_can_view_student($pdo, $studentId, $userId, $role);
    }
}

if (!function_exists('hr_user_can_view_document')) {
    function hr_user_can_view_document(PDO $pdo, int $documentId, int $userId, string $role): bool
    {
        if ($documentId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT employment_id FROM hr_documents WHERE id = ?');
        $stmt->execute([$documentId]);
        $employmentId = (int)$stmt->fetchColumn();
        return $employmentId > 0 && hr_user_can_view_record($pdo, $employmentId, $userId, $role);
    }
}
