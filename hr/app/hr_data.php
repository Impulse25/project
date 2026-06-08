<?php
// hr/app/hr_data.php — фильтры, выборки, пагинация и статистика HR-модуля

// ── Фильтры из GET ────────────────────────────────────────────
$fGroup    = isset($_GET['group_id'])     && $_GET['group_id']     !== '' ? (int)$_GET['group_id']     : null;
$fSpec     = isset($_GET['specialty_id']) && $_GET['specialty_id'] !== '' ? (int)$_GET['specialty_id'] : null;
$fYear     = isset($_GET['grad_year'])    && $_GET['grad_year']    !== '' ? (int)$_GET['grad_year']    : null;
$fStatus   = isset($_GET['status'])       && $_GET['status']       !== '' ? trim($_GET['status'])      : null;
$fSearch   = isset($_GET['search'])       ? trim($_GET['search'])          : '';

// ── Пагинация ─────────────────────────────────────────────────
$perPage = 20;
$currentPage = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// ── Справочники для фильтров ──────────────────────────────────
$groups      = $pdo->query("SELECT id, name, course FROM edu_groups ORDER BY name")->fetchAll();
$specialties = $pdo->query("SELECT id, code, name_ru FROM edu_specialties ORDER BY name_ru")->fetchAll();

// Годы выпуска из существующих групп
$years = $pdo->query("
    SELECT DISTINCT (year_started + course) AS grad_year
    FROM edu_groups
    WHERE year_started IS NOT NULL
    ORDER BY grad_year DESC
")->fetchAll(PDO::FETCH_COLUMN);

// ── Общие условия фильтрации ──────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($fGroup) {
    $where[]  = 's.group_id = ?';
    $params[] = $fGroup;
}
if ($fSpec) {
    $where[]  = 's.speciality_id = ?';
    $params[] = $fSpec;
}
if ($fYear) {
    // Год выпуска = year_started + course
    $where[]  = '(g.year_started + g.course) = ?';
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
            LOWER(REPLACE(COALESCE(e.employer_name,''), 'ё', 'е')) LIKE ? OR
            LOWER(REPLACE(COALESCE(e.position,''), 'ё', 'е')) LIKE ?
        )";
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }
}

$whereStr = implode(' AND ', $where);

$baseFromSql = "
    FROM edu_students s
    LEFT JOIN edu_groups       g  ON s.group_id      = g.id
    LEFT JOIN edu_specialties  sp ON s.speciality_id = sp.id
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
$unknown    = (int)($stats['unknown_status'] ?? 0);
$noData     = (int)($stats['no_data'] ?? 0);
$bySpec     = (int)($stats['by_spec'] ?? 0);

$empRate        = $total > 0 ? round($employed / $total * 100, 1) : 0;
$unemployedRate = $total > 0 ? round($unemployed / $total * 100, 1) : 0;
$studyingRate   = $total > 0 ? round($studying / $total * 100, 1) : 0;
$decreeRate     = $total > 0 ? round($decree / $total * 100, 1) : 0;
$militaryRate   = $total > 0 ? round($military / $total * 100, 1) : 0;
$unknownRate    = $total > 0 ? round($unknown / $total * 100, 1) : 0;
$noDataRate     = $total > 0 ? round($noData / $total * 100, 1) : 0;
$bySpecRate     = $employed > 0 ? round($bySpec / $employed * 100, 1) : 0;

$statusSummaryCards = [
    [
        'label' => 'Трудоустроены',
        'value' => $employed,
        'rate' => $empRate,
        'hint' => 'от выборки',
        'icon' => 'success',
    ],
    [
        'label' => 'По специальности',
        'value' => $bySpec,
        'rate' => $bySpecRate,
        'hint' => 'из трудоустроенных',
        'icon' => 'gold',
    ],
    [
        'label' => 'Не работают',
        'value' => $unemployed,
        'rate' => $unemployedRate,
        'hint' => 'от выборки',
        'icon' => 'error',
    ],
    [
        'label' => 'Продолжают учёбу',
        'value' => $studying,
        'rate' => $studyingRate,
        'hint' => 'от выборки',
        'icon' => 'primary',
    ],
    [
        'label' => 'В декрете',
        'value' => $decree,
        'rate' => $decreeRate,
        'hint' => 'от выборки',
        'icon' => 'warning',
    ],
    [
        'label' => 'Военная служба',
        'value' => $military,
        'rate' => $militaryRate,
        'hint' => 'от выборки',
        'icon' => 'muted',
    ],
    [
        'label' => 'Неизвестно',
        'value' => $unknown,
        'rate' => $unknownRate,
        'hint' => 'от выборки',
        'icon' => 'muted',
    ],
    [
        'label' => 'Нет данных',
        'value' => $noData,
        'rate' => $noDataRate,
        'hint' => 'требуют заполнения',
        'icon' => 'muted',
    ],
];

$totalPages = max(1, (int)ceil($total / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;
$pageStart = $total > 0 ? $offset + 1 : 0;
$pageEnd = $total > 0 ? min($offset + $perPage, $total) : 0;

// ── Основной запрос: только записи текущей страницы ───────────
$sql = "
    SELECT
        s.id          AS student_id,
        s.surname, s.name, s.patronymic,
        g.id          AS group_id,
        g.name        AS group_name,
        g.course,
        g.year_started,
        (g.year_started + g.course) AS grad_year,
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
        (SELECT COUNT(*) FROM hr_documents d WHERE d.employment_id = e.id) AS doc_count
    $baseFromSql
    ORDER BY s.surname, s.name, s.patronymic
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

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
$newStudents = $pdo->query("
    SELECT s.id, s.surname, s.name, s.patronymic, g.name as group_name
    FROM edu_students s
    LEFT JOIN edu_groups g ON s.group_id = g.id
    WHERE s.id NOT IN (SELECT DISTINCT student_id FROM hr_employment)
    ORDER BY s.surname, s.name
")->fetchAll();
