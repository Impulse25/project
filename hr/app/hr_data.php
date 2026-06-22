<?php
// hr/app/hr_data.php — фильтры, область доступа, выборки, пагинация и статистика HR-модуля

$currentYear = (int)date('Y');
$archiveLimitYear = hr_archive_limit_year($currentYear);

$allowedViews = hr_allowed_views_for_role($userRole, $hrScope ?? []);
if (!$allowedViews) {
    http_response_code(403);
    die('Нет доступа к HR-модулю');
}

$requestedView = trim((string)($_GET['view'] ?? ''));
if ($requestedView === 'previous') {
    $requestedView = 'graduates';
}
$hrView = in_array($requestedView, $allowedViews, true)
    ? $requestedView
    : hr_default_view_for_role($userRole, $hrScope ?? []);

// ── Фильтры из GET ────────────────────────────────────────────
$fGroup      = isset($_GET['group_id'])      && $_GET['group_id']      !== '' ? (int)$_GET['group_id']      : null;
$fSpec       = isset($_GET['specialty_id'])  && $_GET['specialty_id']  !== '' ? (int)$_GET['specialty_id']  : null;
$fDepartment = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : null;
$fYear       = isset($_GET['grad_year'])     && $_GET['grad_year']     !== '' ? (int)$_GET['grad_year']     : null;
$fStatus     = isset($_GET['status'])        && $_GET['status']        !== '' ? trim($_GET['status'])       : null;
$fSearch     = isset($_GET['search'])        ? trim($_GET['search'])          : '';

// Директор работает только с отделениями. Чтобы интерфейс не смешивал уровни,
// прямой group_id для него игнорируется.
if ($isDirector || $isHrDepartmentHead || $isHrPracticeHead) {
    $fGroup = null;
}

// ── Пагинация ─────────────────────────────────────────────────
$perPage = 20;
$currentPage = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

$gradExpr = hr_group_grad_expr('g');
$groupStateExpr = hr_group_state_sql('g', $currentYear);
$departmentExpr = 'COALESCE(g.department_id, sp.department_id)';
$isRegularTeacher = $isTeacher && !$isHrDepartmentHead && !$isHrPracticeHead;

// ── Вкладки по роли ───────────────────────────────────────────
$viewTabs = [];
if ($isRegularTeacher) {
    $viewTabs = [
        ['key' => 'group',    'label' => 'Группа',                 'hint' => 'Группы преподавателя'],
        ['key' => 'graduates', 'label' => 'Выпускники',         'hint' => 'Выпуски за последние 5 лет'],
        ['key' => 'archive',  'label' => 'Архив выпускников',    'hint' => 'Выпускники 5 и более лет назад'],
    ];
} elseif ($isDirector || $isHrDepartmentHead || $isHrPracticeHead) {
    $viewTabs = [
        ['key' => 'departments', 'label' => 'Отделения', 'hint' => 'Сводная статистика по отделениям'],
    ];
} elseif ($isSystemAdmin) {
    $viewTabs = [
        ['key' => 'all',         'label' => 'Все данные',        'hint' => 'Все группы, отделения и архив выпускников'],
        ['key' => 'groups',      'label' => 'Группы',   'hint' => 'Все группы'],
        ['key' => 'graduates',    'label' => 'Выпускники',     'hint' => 'Выпуски за последние 5 лет'],
        ['key' => 'departments', 'label' => 'Отделения',         'hint' => 'Сводка по отделениям'],
        ['key' => 'archive',     'label' => 'Архив выпускников',       'hint' => 'Выпускники старше 5 лет после выпуска'],
    ];
}

$pageContextTitle = match ($hrView) {
    'group' => 'мои группы',
    'groups' => 'группы',
    'departments' => 'отделения',
    'graduates' => 'выпускники',
    'archive' => 'архив выпускников',
    default => 'все данные',
};

// ── Справочники для фильтров ──────────────────────────────────
[$scopeGroupConds, $scopeGroupParams] = hr_scope_sql('g', $userRole, $userId, $hrView, $currentYear, $hrScope ?? []);
$scopeGroupWhere = $scopeGroupConds ? implode(' AND ', $scopeGroupConds) : '1=1';

$departments = $pdo->query("
    SELECT id, department_name
    FROM departments
    ORDER BY department_name
")->fetchAll(PDO::FETCH_ASSOC);

if ($isHrDepartmentHead && !empty($hrScope['department_id'])) {
    $headDepartmentId = (int)$hrScope['department_id'];
    $departments = array_values(array_filter(
        $departments,
        static fn(array $dept): bool => (int)$dept['id'] === $headDepartmentId
    ));
    $fDepartment = $headDepartmentId;
}

$selectedDepartmentName = null;
foreach ($departments as $deptRow) {
    if ($fDepartment !== null && (int)$deptRow['id'] === $fDepartment) {
        $selectedDepartmentName = $deptRow['department_name'];
        break;
    }
}

$groupsSql = "
    SELECT
        g.id,
        g.name,
        g.course,
        g.year_started,
        $gradExpr AS grad_year,
        $groupStateExpr AS group_state,
        COALESCE(d.department_name, 'Без отделения') AS department_name
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    LEFT JOIN departments d ON d.id = $departmentExpr
    WHERE $scopeGroupWhere
";
$groupsParams = $scopeGroupParams;
if ($fDepartment) {
    $groupsSql .= " AND $departmentExpr = ?";
    $groupsParams[] = $fDepartment;
}
$groupsSql .= " ORDER BY group_state, g.name";
$groupsStmt = $pdo->prepare($groupsSql);
$groupsStmt->execute($groupsParams);
$groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

$specialtiesSql = "
    SELECT DISTINCT sp.id, sp.code, sp.name_ru
    FROM edu_specialties sp
    JOIN edu_groups g ON g.specialty_id = sp.id
    WHERE $scopeGroupWhere
";
$specialtiesParams = $scopeGroupParams;
if ($fDepartment) {
    $specialtiesSql .= " AND COALESCE(g.department_id, sp.department_id) = ?";
    $specialtiesParams[] = $fDepartment;
}
$specialtiesSql .= " ORDER BY sp.name_ru";
$specialtiesStmt = $pdo->prepare($specialtiesSql);
$specialtiesStmt->execute($specialtiesParams);
$specialties = $specialtiesStmt->fetchAll(PDO::FETCH_ASSOC);

$yearsSql = "
    SELECT DISTINCT $gradExpr AS grad_year
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    WHERE $scopeGroupWhere
      AND g.year_started IS NOT NULL
";
$yearsParams = $scopeGroupParams;
if ($fDepartment) {
    $yearsSql .= " AND $departmentExpr = ?";
    $yearsParams[] = $fDepartment;
}
$yearsSql .= " ORDER BY grad_year DESC";
$yearsStmt = $pdo->prepare($yearsSql);
$yearsStmt->execute($yearsParams);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

// ── Общие условия фильтрации реестра ──────────────────────────
$where  = $scopeGroupConds ?: ['1=1'];
$params = $scopeGroupParams;

if ($fGroup) {
    $where[]  = 's.group_id = ?';
    $params[] = $fGroup;
}
if ($fDepartment) {
    $where[]  = "$departmentExpr = ?";
    $params[] = $fDepartment;
}
if ($fSpec) {
    $where[]  = 'COALESCE(s.speciality_id, g.specialty_id) = ?';
    $params[] = $fSpec;
}
if ($fYear) {
    $where[]  = "$gradExpr = ?";
    $params[] = $fYear;
}
if ($fStatus) {
    $where[]  = 'e.status = ?';
    $params[] = $fStatus;
}
if ($fSearch !== '') {
    // Нечёткий поиск: ищем каждое введённое слово отдельно.
    // Так "Александр Ломакин", "Ломакин Александр" и "Алекс" дадут результат.
    $terms = preg_split('/\s+/u', mb_strtolower(str_replace('ё', 'е', $fSearch)), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $where[] = "(
            LOWER(REPLACE(COALESCE(s.surname,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(s.name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(s.patronymic,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(CONCAT_WS(' ', COALESCE(s.surname,''), COALESCE(s.name,''), COALESCE(s.patronymic,'')), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(g.name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(sp.name_ru,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(sp.code,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(d.department_name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(e.employer_name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(e.position,''), 'ё', 'е')) LIKE ?
        )";
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }
}

$whereStr = implode(' AND ', $where);

$baseFromSql = "
    FROM edu_students s
    LEFT JOIN edu_groups       g  ON s.group_id      = g.id
    LEFT JOIN edu_specialties  sp ON sp.id           = COALESCE(s.speciality_id, g.specialty_id)
    LEFT JOIN departments      d  ON d.id            = $departmentExpr
    LEFT JOIN hr_employment    e  ON e.student_id    = s.id
        AND e.id = (
            SELECT MAX(e2.id) FROM hr_employment e2 WHERE e2.student_id = s.id
        )
    WHERE $whereStr
";

// ── Статистика по всей отфильтрованной выборке, не только по текущей странице ──
$statsSql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN e.status = 'employed' THEN 1 ELSE 0 END) AS employed,
        SUM(CASE WHEN e.status = 'unemployed' THEN 1 ELSE 0 END) AS unemployed,
        SUM(CASE WHEN e.status = 'studying' THEN 1 ELSE 0 END) AS studying,
        SUM(CASE WHEN e.status = 'decree' THEN 1 ELSE 0 END) AS decree,
        SUM(CASE WHEN e.status = 'military' THEN 1 ELSE 0 END) AS military,
        SUM(CASE WHEN e.status = 'relocation' THEN 1 ELSE 0 END) AS relocation,
        SUM(CASE WHEN e.status = 'other' THEN 1 ELSE 0 END) AS other_status,
        SUM(CASE WHEN e.status = 'unknown' THEN 1 ELSE 0 END) AS unknown_status,
        SUM(CASE WHEN e.id IS NULL THEN 1 ELSE 0 END) AS no_data,
        SUM(CASE WHEN e.status = 'employed' AND COALESCE(e.is_by_specialty, 0) = 1 THEN 1 ELSE 0 END) AS by_spec
    $baseFromSql
";
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$total      = (int)($stats['total'] ?? 0);
$employed   = (int)($stats['employed'] ?? 0);
$unemployed = (int)($stats['unemployed'] ?? 0);
$studying   = (int)($stats['studying'] ?? 0);
$decree     = (int)($stats['decree'] ?? 0);
$military   = (int)($stats['military'] ?? 0);
$relocation = (int)($stats['relocation'] ?? 0);
$otherStatus = (int)($stats['other_status'] ?? 0);
$unknown    = (int)($stats['unknown_status'] ?? 0);
$noData     = (int)($stats['no_data'] ?? 0);
$bySpec     = (int)($stats['by_spec'] ?? 0);

$empRate        = $total > 0 ? round($employed / $total * 100, 1) : 0;
$unemployedRate = $total > 0 ? round($unemployed / $total * 100, 1) : 0;
$studyingRate   = $total > 0 ? round($studying / $total * 100, 1) : 0;
$decreeRate     = $total > 0 ? round($decree / $total * 100, 1) : 0;
$militaryRate   = $total > 0 ? round($military / $total * 100, 1) : 0;
$relocationRate = $total > 0 ? round($relocation / $total * 100, 1) : 0;
$otherStatusRate = $total > 0 ? round($otherStatus / $total * 100, 1) : 0;
$unknownRate    = $total > 0 ? round($unknown / $total * 100, 1) : 0;
$noDataRate     = $total > 0 ? round($noData / $total * 100, 1) : 0;
$bySpecRate     = $employed > 0 ? round($bySpec / $employed * 100, 1) : 0;

$statusSummaryCards = [
    ['label' => 'Трудоустроены',     'value' => $employed,   'rate' => $empRate,        'hint' => 'от выборки',        'icon' => 'success'],
    ['label' => 'По специальности',  'value' => $bySpec,     'rate' => $bySpecRate,     'hint' => 'из трудоустроенных','icon' => 'gold'],
    ['label' => 'Не работают',       'value' => $unemployed, 'rate' => $unemployedRate, 'hint' => 'от выборки',        'icon' => 'error'],
    ['label' => 'Продолжают учёбу',  'value' => $studying,   'rate' => $studyingRate,   'hint' => 'от выборки',        'icon' => 'primary'],
    ['label' => 'В декрете',         'value' => $decree,     'rate' => $decreeRate,     'hint' => 'от выборки',        'icon' => 'warning'],
    ['label' => 'Военная служба',    'value' => $military,   'rate' => $militaryRate,   'hint' => 'от выборки',        'icon' => 'muted'],
    ['label' => 'Выезд на ПМЖ',      'value' => $relocation, 'rate' => $relocationRate, 'hint' => 'от выборки',        'icon' => 'muted'],
    ['label' => 'Прочее',            'value' => $otherStatus,'rate' => $otherStatusRate,'hint' => 'от выборки',        'icon' => 'muted'],
    ['label' => 'Неизвестно',        'value' => $unknown,    'rate' => $unknownRate,    'hint' => 'от выборки',        'icon' => 'muted'],
    ['label' => 'Нет данных',        'value' => $noData,     'rate' => $noDataRate,     'hint' => 'требуют заполнения','icon' => 'muted'],
];

$notBySpec = max(0, $employed - $bySpec);
$notEmployedOrOther = max(0, $total - $employed);
$hrChartData = [
    'summary' => [
        'title' => 'HR-статистика по выбранной выборке',
        'labels' => [
            'Трудоустроены',
            'По специальности',
            'Не по специальности',
            'Не работают',
            'Продолжают учёбу',
            'В декрете',
            'Военная служба',
            'Выезд на ПМЖ',
            'Прочее',
            'Неизвестно',
            'Нет данных',
        ],
        'values' => [
            $employed,
            $bySpec,
            $notBySpec,
            $unemployed,
            $studying,
            $decree,
            $military,
            $relocation,
            $otherStatus,
            $unknown,
            $noData,
        ],
        'colors' => [
            '#16a34a',
            '#ca8a04',
            '#1a56db',
            '#dc2626',
            '#3b82f6',
            '#d97706',
            '#64748b',
            '#0f766e',
            '#7c3aed',
            '#94a3b8',
            '#cbd5e1',
        ],
    ],
];

// ── Сводка по группам ────────────────────────────────────────
[$roleGroupConds, $roleGroupParams] = hr_scope_sql('g', $userRole, $userId, 'all', $currentYear, $hrScope ?? []);
// Для преподавателя hr_scope_sql('all') вернёт неархив. Нужна полная история по его кураторским группам.
if ($isRegularTeacher) {
    $roleGroupConds = ['g.curator_id = ?'];
    $roleGroupParams = [$userId];
}
$roleGroupWhere = $roleGroupConds ? implode(' AND ', $roleGroupConds) : '1=1';

$groupStatsSql = "
    SELECT
        g.id AS group_id,
        g.name AS group_name,
        g.course,
        g.year_started,
        $gradExpr AS grad_year,
        $groupStateExpr AS group_state,
        COALESCE(d.department_name, 'Без отделения') AS department_name,
        COUNT(s.id) AS total,
        SUM(CASE WHEN e.status = 'employed' THEN 1 ELSE 0 END) AS employed,
        SUM(CASE WHEN e.status = 'employed' AND COALESCE(e.is_by_specialty, 0) = 1 THEN 1 ELSE 0 END) AS by_spec,
        SUM(CASE WHEN e.id IS NULL THEN 1 ELSE 0 END) AS no_data
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    LEFT JOIN departments d ON d.id = $departmentExpr
    LEFT JOIN edu_students s ON s.group_id = g.id
    LEFT JOIN hr_employment e ON e.student_id = s.id
        AND e.id = (SELECT MAX(e2.id) FROM hr_employment e2 WHERE e2.student_id = s.id)
    WHERE $roleGroupWhere
";
$groupStatsParams = $roleGroupParams;
if ($fDepartment) {
    $groupStatsSql .= " AND $departmentExpr = ?";
    $groupStatsParams[] = $fDepartment;
}
if ($fSpec) {
    $groupStatsSql .= ' AND g.specialty_id = ?';
    $groupStatsParams[] = $fSpec;
}
if ($fYear) {
    $groupStatsSql .= " AND $gradExpr = ?";
    $groupStatsParams[] = $fYear;
}
$groupStatsSql .= "
    GROUP BY g.id, g.name, g.course, g.year_started, d.department_name
    ORDER BY group_state, g.name
";
$groupStatsStmt = $pdo->prepare($groupStatsSql);
$groupStatsStmt->execute($groupStatsParams);
$groupStatsAll = $groupStatsStmt->fetchAll(PDO::FETCH_ASSOC);

$teacherCurrentGroupStats = [];
$teacherPreviousGroupStats = [];
$teacherArchiveGroupStats = [];
foreach ($groupStatsAll as $gs) {
    if (($gs['group_state'] ?? '') === 'archive') {
        $teacherArchiveGroupStats[] = $gs;
    } elseif (($gs['group_state'] ?? '') === 'previous') {
        $teacherPreviousGroupStats[] = $gs;
    } else {
        $teacherCurrentGroupStats[] = $gs;
    }
}

$visibleGroupStats = array_values(array_filter($groupStatsAll, static function(array $row) use ($hrView): bool {
    $state = $row['group_state'] ?? '';
    if ($hrView === 'archive') return $state === 'archive';
    if ($hrView === 'graduates') return $state === 'previous';
    if ($hrView === 'group' || $hrView === 'groups') return $state === 'current';
    return true;
}));

// ── Сводка по отделениям ─────────────────────────────────────
$deptScopeConds = [];
$deptScopeParams = [];
if ($isRegularTeacher) {
    $deptScopeConds[] = 'g.curator_id = ?';
    $deptScopeParams[] = $userId;
} elseif ($isHrDepartmentHead || $isHrPracticeHead) {
    [$deptScopeConds, $deptScopeParams] = hr_scope_sql('g', $userRole, $userId, $hrView, $currentYear, $hrScope ?? []);
}
if (!$isHrDepartmentHead && !$isHrPracticeHead && $hrView === 'archive') {
    $deptScopeConds[] = "$gradExpr <= ?";
    $deptScopeParams[] = $archiveLimitYear;
} elseif (!$isHrDepartmentHead && !$isHrPracticeHead && $hrView === 'graduates') {
    $deptScopeConds[] = "$gradExpr > ?";
    $deptScopeConds[] = "$gradExpr < ?";
    $deptScopeParams[] = $archiveLimitYear;
    $deptScopeParams[] = $currentYear;
} elseif (!$isHrDepartmentHead && !$isHrPracticeHead && in_array($hrView, ['group', 'groups'], true)) {
    $deptScopeConds[] = "($gradExpr >= ? OR g.year_started IS NULL OR g.course IS NULL)";
    $deptScopeParams[] = $currentYear;
}
// Список отделений должен оставаться полным даже после выбора одного отделения.
// Иначе директор кликает по отделению, department_id попадает в URL,
// и сама панель выбора отделений сжимается до одной строки без явного возврата.
// Фильтр department_id применяется ниже к реестру и KPI, но не к панели выбора.
if ($fSpec) {
    $deptScopeConds[] = 'g.specialty_id = ?';
    $deptScopeParams[] = $fSpec;
}
$deptScopeWhere = $deptScopeConds ? implode(' AND ', $deptScopeConds) : '1=1';

$departmentStatsSql = "
    SELECT
        COALESCE(d.id, 0) AS department_id,
        COALESCE(d.department_name, 'Без отделения') AS department_name,
        COUNT(DISTINCT g.id) AS groups_count,
        COUNT(s.id) AS total,
        SUM(CASE WHEN e.status = 'employed' THEN 1 ELSE 0 END) AS employed,
        SUM(CASE WHEN e.status = 'employed' AND COALESCE(e.is_by_specialty, 0) = 1 THEN 1 ELSE 0 END) AS by_spec,
        SUM(CASE WHEN e.id IS NULL THEN 1 ELSE 0 END) AS no_data
    FROM edu_groups g
    LEFT JOIN edu_specialties sp ON sp.id = g.specialty_id
    LEFT JOIN departments d ON d.id = $departmentExpr
    LEFT JOIN edu_students s ON s.group_id = g.id
    LEFT JOIN hr_employment e ON e.student_id = s.id
        AND e.id = (SELECT MAX(e2.id) FROM hr_employment e2 WHERE e2.student_id = s.id)
    WHERE $deptScopeWhere
    GROUP BY COALESCE(d.id, 0), COALESCE(d.department_name, 'Без отделения')
    ORDER BY department_name
";
$departmentStatsStmt = $pdo->prepare($departmentStatsSql);
$departmentStatsStmt->execute($deptScopeParams);
$departmentStats = $departmentStatsStmt->fetchAll(PDO::FETCH_ASSOC);

$scopeCounts = [
    'current_groups' => 0,
    'previous_groups' => 0,
    'archive_groups' => 0,
    'departments' => 0,
];
foreach ($groupStatsAll as $gs) {
    if (($gs['group_state'] ?? '') === 'archive') $scopeCounts['archive_groups']++;
    elseif (($gs['group_state'] ?? '') === 'previous') $scopeCounts['previous_groups']++;
    else $scopeCounts['current_groups']++;
}
$scopeCounts['departments'] = count(array_filter($departmentStats, static fn($d) => (int)($d['department_id'] ?? 0) > 0));

// ── Основной запрос: только записи текущей страницы ───────────
$totalPages = max(1, (int)ceil($total / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;
$pageStart = $total > 0 ? $offset + 1 : 0;
$pageEnd = $total > 0 ? min($offset + $perPage, $total) : 0;

$sql = "
    SELECT
        s.id          AS student_id,
        s.surname, s.name, s.patronymic,
        g.id          AS group_id,
        g.name        AS group_name,
        g.course,
        g.year_started,
        $gradExpr AS grad_year,
        $groupStateExpr AS group_state,
        d.id          AS department_id,
        d.department_name,
        sp.id         AS specialty_id,
        sp.name_ru    AS specialty_name,
        sp.code       AS specialty_code,
        e.id          AS employment_id,
        e.status,
        e.employer_name,
        e.position,
        e.employment_date,
        e.employment_type,
        e.is_by_specialty,
        e.graduation_year,
        e.notes,
        e.updated_at,
        (SELECT COUNT(*) FROM hr_documents doc WHERE doc.employment_id = e.id) AS doc_count
    $baseFromSql
    ORDER BY d.department_name, g.name, s.surname, s.name, s.patronymic
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Документы текущей страницы: сразу отдаются в HTML/JS, без фоновых запросов ──
$docsByEmployment = [];
$employmentIds = array_values(array_unique(array_filter(array_map(
    fn($row) => (int)($row['employment_id'] ?? 0),
    $students
))));

if ($employmentIds) {
    $placeholders = implode(',', array_fill(0, count($employmentIds), '?'));
    $docsStmt = $pdo->prepare("
        SELECT id, employment_id, original_name, file_size, uploaded_at
        FROM hr_documents
        WHERE employment_id IN ($placeholders)
        ORDER BY uploaded_at DESC, id DESC
    ");
    $docsStmt->execute($employmentIds);
    foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
        $empId = (int)$doc['employment_id'];
        $docsByEmployment[$empId][] = $doc;
    }
}

// ── Студенты без записи (для добавления новых) ────────────────
$newStudents = [];
if ($canManageHrRecords) {
    $manageWhere = [];
    $manageParams = [];
    if ($isRegularTeacher) {
        $manageWhere[] = 'g.curator_id = ?';
        $manageParams[] = $userId;
        $manageWhere[] = "($gradExpr > ? OR g.year_started IS NULL OR g.course IS NULL)";
        $manageParams[] = $archiveLimitYear;
    }
    if ($fDepartment) {
        $manageWhere[] = "$departmentExpr = ?";
        $manageParams[] = $fDepartment;
    }
    $manageWhereStr = $manageWhere ? implode(' AND ', $manageWhere) : '1=1';

    $newStudentsStmt = $pdo->prepare("
        SELECT s.id, s.surname, s.name, s.patronymic, g.name AS group_name
        FROM edu_students s
        LEFT JOIN edu_groups g ON s.group_id = g.id
        LEFT JOIN edu_specialties sp ON sp.id = COALESCE(s.speciality_id, g.specialty_id)
        WHERE s.id NOT IN (SELECT DISTINCT student_id FROM hr_employment)
          AND $manageWhereStr
        ORDER BY s.surname, s.name
    ");
    $newStudentsStmt->execute($manageParams);
    $newStudents = $newStudentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$canShowGroupFilter = !$isDirector && !$isHrDepartmentHead && !$isHrPracticeHead;
$canShowDepartmentFilter = $isDirector || $isSystemAdmin || $isHrDepartmentHead || $isHrPracticeHead;
$canShowRecordActions = $canManageHrRecords;
