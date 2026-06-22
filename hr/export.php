<?php
// hr/export.php — Экспорт данных HR-Аналитики в Excel, CSV и Word
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Не авторизован');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/app/access.php';

$userId = (int)$_SESSION['user_id'];
$userRole = hr_normalize_role($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? '');
$hrScope = hr_user_scope($pdo, $userId, $userRole);
$allowedViews = hr_allowed_views_for_role($userRole, $hrScope);
if (!$allowedViews) {
    http_response_code(403);
    die('Нет доступа к HR-модулю');
}

$isDirector = $userRole === 'director';
$isSystemAdmin = $userRole === 'admin';
$isHrDepartmentHead = !$isSystemAdmin && !empty($hrScope['department_head']);
$isHrPracticeHead = !$isSystemAdmin && !empty($hrScope['practice_head']);
$canExportHrCharts = true;
$currentYear = (int)date('Y');

// ── Параметры экспорта ────────────────────────────────────
$format = mb_strtolower(trim($_GET['format'] ?? 'excel')); // excel|xlsx|csv|word
$exportScope = normalizeExportScope($_GET['export_scope'] ?? 'table');
$requestedView = trim((string)($_GET['view'] ?? ''));
if ($requestedView === 'previous') {
    $requestedView = 'graduates';
}
$hrView = in_array($requestedView, $allowedViews, true) ? $requestedView : hr_default_view_for_role($userRole, $hrScope);

$fGroup      = isset($_GET['group_id'])      && $_GET['group_id']      !== '' ? (int)$_GET['group_id']      : null;
$fSpec       = isset($_GET['specialty_id'])  && $_GET['specialty_id']  !== '' ? (int)$_GET['specialty_id']  : null;
$fDepartment = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : null;
$fYear       = isset($_GET['grad_year'])     && $_GET['grad_year']     !== '' ? (int)$_GET['grad_year']     : null;
$fStatus     = isset($_GET['status'])        && $_GET['status']        !== '' ? trim($_GET['status'])       : null;
$fSearch     = isset($_GET['search'])        ? trim($_GET['search'])          : '';
$exportChartType = normalizeChartType($_GET['chart_type'] ?? 'doughnut');
$exportGraphType = normalizeGraphType($_GET['graph_type'] ?? 'line');
$exportChartPercent = (string)($_GET['chart_percent'] ?? '') === '1';
$exportVisuals = normalizeExportVisuals($_GET['export_visuals'] ?? ($exportScope === 'visuals' ? 'both' : 'none'));
$exportVisuals = $exportScope === 'visuals' ? $exportVisuals : 'none';
$exportChartIndexes = parseChartItemIndexes($_GET['chart_items'] ?? '');
$canExportHrCharts = $exportScope === 'visuals' && $exportVisuals !== 'none';

if ($isDirector || $isHrDepartmentHead || $isHrPracticeHead) {
    $fGroup = null;
}

$gradExpr = hr_group_grad_expr('g');
$groupStateExpr = hr_group_state_sql('g', $currentYear);
$departmentExpr = 'COALESCE(g.department_id, sp.department_id)';

// ── Основной запрос с теми же фильтрами, что и в index.php ─
[$scopeConds, $scopeParams] = hr_scope_sql('g', $userRole, $userId, $hrView, $currentYear, $hrScope);
$where  = $scopeConds ?: ['1=1'];
$params = $scopeParams;

if ($isHrDepartmentHead && !empty($hrScope['department_id'])) {
    $fDepartment = (int)$hrScope['department_id'];
}

if ($fGroup) {
    $where[]  = 's.group_id = ?';
    $params[] = $fGroup;
}
if ($fDepartment) {
    $where[] = "$departmentExpr = ?";
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
    // Нечёткий поиск: каждое слово ищется отдельно и не зависит от порядка ввода.
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

$sql = "
    SELECT
        s.id AS student_id,
        s.surname, s.name, s.patronymic,
        g.name AS group_name,
        g.course,
        g.year_started,
        $gradExpr AS grad_year,
        $groupStateExpr AS group_state,
        d.department_name,
        sp.name_ru AS specialty_name,
        sp.code AS specialty_code,
        e.status,
        e.employer_name,
        e.position,
        e.employment_date,
        e.employment_type,
        e.is_by_specialty,
        e.graduation_year AS employment_graduation_year,
        e.notes
    FROM edu_students s
    LEFT JOIN edu_groups g ON s.group_id = g.id
    LEFT JOIN edu_specialties sp ON sp.id = COALESCE(s.speciality_id, g.specialty_id)
    LEFT JOIN departments d ON d.id = $departmentExpr
    LEFT JOIN hr_employment e ON e.student_id = s.id
        AND e.id = (SELECT MAX(e2.id) FROM hr_employment e2 WHERE e2.student_id = s.id)
    WHERE $whereStr
    ORDER BY d.department_name, g.name, s.surname, s.name, s.patronymic
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Форматирование данных ─────────────────────────────────
function statusText(?string $s): string {
    return match($s) {
        'employed'   => 'Трудоустроен',
        'unemployed' => 'Не трудоустроен',
        'studying'   => 'Продолжает учёбу',
        'decree'     => 'В декрете',
        'military'   => 'Военная служба',
        'relocation' => 'Выезд на ПМЖ',
        'other'      => 'Прочее',
        'unknown'    => 'Неизвестно',
        default      => '—',
    };
}
function empTypeText(?string $t): string {
    return match($t) {
        'full_time'     => 'Полная занятость',
        'part_time'     => 'Частичная занятость',
        'contract'      => 'Договор/контракт',
        'self_employed' => 'Самозанятый',
        'other'         => 'Прочее',
        default         => '—',
    };
}
function fmtDate(?string $d): string {
    if (!$d) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d.m.Y') : $d;
}
function yesNo($v): string {
    return ((int)$v === 1) ? 'Да' : 'Нет';
}
function valueOrDash($v): string {
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : '—';
}
function fullName(array $row): string {
    return trim(preg_replace('/\s+/u', ' ', ($row['surname'] ?? '') . ' ' . ($row['name'] ?? '') . ' ' . ($row['patronymic'] ?? '')));
}
function exportRows(array $students): array {
    $headers = [
        '№', 'ФИО', 'Отделение', 'Группа', 'Тип группы', 'Год выпуска', 'Код специальности', 'Специальность',
        'Статус', 'Организация', 'Должность', 'Дата трудоустройства',
        'Тип занятости', 'По специальности', 'Примечания'
    ];

    $rows = [];
    foreach ($students as $i => $row) {
        $gradYear = $row['employment_graduation_year'] ?: $row['grad_year'];
        $rows[] = [
            (string)($i + 1),
            valueOrDash(fullName($row)),
            valueOrDash($row['department_name'] ?? ''),
            valueOrDash($row['group_name'] ?? ''),
            match($row['group_state'] ?? '') { 'current' => 'Группа', 'previous' => 'Выпускники', 'archive' => 'Архив', default => '—' },
            valueOrDash($gradYear),
            valueOrDash($row['specialty_code'] ?? ''),
            valueOrDash($row['specialty_name'] ?? ''),
            statusText($row['status'] ?? null),
            valueOrDash($row['employer_name'] ?? ''),
            valueOrDash($row['position'] ?? ''),
            fmtDate($row['employment_date'] ?? null),
            empTypeText($row['employment_type'] ?? null),
            yesNo($row['is_by_specialty'] ?? 0),
            valueOrDash($row['notes'] ?? ''),
        ];
    }

    return [$headers, $rows];
}
function xmlText($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
function colName(int $index): string {
    $name = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = intdiv($index - $mod, 26);
    }
    return $name;
}
function downloadFileName(string $base, string $ext): string {
    return $base . '-' . date('Y-m-d') . '.' . $ext;
}
function normalizeExportScope($value): string {
    $scope = trim((string)$value);
    return in_array($scope, ['table', 'visuals'], true) ? $scope : 'table';
}
function normalizeChartType($value): string {
    $type = trim((string)$value);
    return in_array($type, ['doughnut', 'pie', 'bar', 'line'], true) ? $type : 'doughnut';
}
function normalizeGraphType($value): string {
    $type = trim((string)$value);
    return in_array($type, ['line', 'smooth', 'area', 'step'], true) ? $type : 'line';
}
function normalizeExportVisuals($value): string {
    $mode = trim((string)$value);
    return in_array($mode, ['chart', 'graph', 'both', 'none'], true) ? $mode : 'chart';
}
function parseChartItemIndexes($value): array {
    if (is_array($value)) {
        $rawItems = $value;
    } else {
        $rawItems = explode(',', (string)$value);
    }

    $indexes = [];
    foreach ($rawItems as $item) {
        $item = trim((string)$item);
        if ($item === '' || !ctype_digit($item)) {
            continue;
        }
        $indexes[] = (int)$item;
    }
    return array_values(array_unique($indexes));
}
function filterChartExportData(array $chartData, array $indexes): array {
    if (!$indexes) {
        return $chartData;
    }

    $labels = $chartData['labels'] ?? [];
    $values = $chartData['values'] ?? [];
    $filteredLabels = [];
    $filteredValues = [];

    foreach ($indexes as $index) {
        if (!array_key_exists($index, $labels)) {
            continue;
        }
        $filteredLabels[] = $labels[$index];
        $filteredValues[] = (int)($values[$index] ?? 0);
    }

    if (!$filteredLabels) {
        return $chartData;
    }

    $chartData['labels'] = $filteredLabels;
    $chartData['values'] = $filteredValues;
    return $chartData;
}
function exportVisualParts(string $mode, string $chartType, string $graphType = 'line'): array {
    $parts = [];
    if ($mode === 'chart' || $mode === 'both') {
        if ($chartType === 'line' && $mode === 'chart') {
            $parts[] = [
                'type' => $graphType,
                'title' => 'График HR-статистики',
                'name' => 'hr_graph',
                'label' => 'HR-график',
            ];
        } else {
            $parts[] = [
                'type' => $chartType === 'line' ? 'bar' : $chartType,
                'title' => 'Диаграмма HR-статистики',
                'name' => 'hr_diagram',
                'label' => 'HR-диаграмма',
            ];
        }
    }
    if ($mode === 'graph' || $mode === 'both') {
        $parts[] = [
            'type' => $graphType,
            'title' => 'График HR-статистики',
            'name' => 'hr_graph',
            'label' => 'HR-график',
        ];
    }
    return $parts;
}
function chartFormulaMap(int $dataLastRow): array {
    $statusRange = "'HR-Аналитика'!\$I\$2:\$I\$$dataLastRow";
    $nameRange = "'HR-Аналитика'!\$B\$2:\$B\$$dataLastRow";
    $specRange = "'HR-Аналитика'!\$N\$2:\$N\$$dataLastRow";
    return [
        'Трудоустроены' => 'COUNTIF(' . $statusRange . ',"Трудоустроен")',
        'По специальности' => 'COUNTIFS(' . $statusRange . ',"Трудоустроен",' . $specRange . ',"Да")',
        'Не по специальности' => 'COUNTIFS(' . $statusRange . ',"Трудоустроен",' . $specRange . ',"Нет")',
        'Не работают' => 'COUNTIF(' . $statusRange . ',"Не трудоустроен")',
        'Продолжают учёбу' => 'COUNTIF(' . $statusRange . ',"Продолжает учёбу")',
        'В декрете' => 'COUNTIF(' . $statusRange . ',"В декрете")',
        'Военная служба' => 'COUNTIF(' . $statusRange . ',"Военная служба")',
        'Выезд на ПМЖ' => 'COUNTIF(' . $statusRange . ',"Выезд на ПМЖ")',
        'Прочее' => 'COUNTIF(' . $statusRange . ',"Прочее")',
        'Неизвестно' => 'COUNTIF(' . $statusRange . ',"Неизвестно")',
        'Нет данных' => 'COUNTIF(' . $statusRange . ',"—")+COUNTA(' . $nameRange . ')-COUNTA(' . $statusRange . ')',
    ];
}
function chartExportData(array $students): array {
    $employed = 0;
    $bySpec = 0;
    $unemployed = 0;
    $studying = 0;
    $decree = 0;
    $military = 0;
    $relocation = 0;
    $other = 0;
    $unknown = 0;
    $noData = 0;

    foreach ($students as $row) {
        $status = $row['status'] ?? null;
        if ($status === null || $status === '') {
            $noData++;
        } elseif ($status === 'employed') {
            $employed++;
            ((int)($row['is_by_specialty'] ?? 0) === 1) ? $bySpec++ : null;
        } elseif ($status === 'unemployed') {
            $unemployed++;
        } elseif ($status === 'studying') {
            $studying++;
        } elseif ($status === 'decree') {
            $decree++;
        } elseif ($status === 'military') {
            $military++;
        } elseif ($status === 'relocation') {
            $relocation++;
        } elseif ($status === 'other') {
            $other++;
        } elseif ($status === 'unknown') {
            $unknown++;
        }
    }

    $notBySpec = max(0, $employed - $bySpec);

    return [
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
            $other,
            $unknown,
            $noData,
        ],
    ];
}
function xlsxInlineCell(string $ref, $value, int $style = 0): string {
    return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t>' . xmlText($value) . '</t></is></c>';
}
function xlsxNumberCell(string $ref, $value, int $style = 0): string {
    return '<c r="' . $ref . '" s="' . $style . '"><v>' . (int)$value . '</v></c>';
}
function xlsxFormulaCell(string $ref, string $formula, $cachedValue = 0, int $style = 0): string {
    return '<c r="' . $ref . '" s="' . $style . '"><f>' . xmlText($formula) . '</f><v>' . (int)$cachedValue . '</v></c>';
}
function chartCacheXml(array $labels, array $values, string $labelRange, string $valueRange): string {
    $labelPts = '';
    foreach ($labels as $i => $label) {
        $labelPts .= '<c:pt idx="' . $i . '"><c:v>' . xmlText($label) . '</c:v></c:pt>';
    }
    $valuePts = '';
    foreach ($values as $i => $value) {
        $valuePts .= '<c:pt idx="' . $i . '"><c:v>' . (int)$value . '</c:v></c:pt>';
    }

    return '<c:cat><c:strRef><c:f>' . xmlText($labelRange) . '</c:f><c:strCache><c:ptCount val="' . count($labels) . '"/>' . $labelPts . '</c:strCache></c:strRef></c:cat>
<c:val><c:numRef><c:f>' . xmlText($valueRange) . '</c:f><c:numCache><c:formatCode>General</c:formatCode><c:ptCount val="' . count($values) . '"/>' . $valuePts . '</c:numCache></c:numRef></c:val>';
}
function chartLabelsXml(bool $showPercent, string $type): string {
    if (in_array($type, ['line', 'smooth', 'area', 'step'], true)) {
        return '';
    }
    if ($showPercent && in_array($type, ['pie', 'doughnut'], true)) {
        return '<c:dLbls><c:showVal val="0"/><c:showCatName val="0"/><c:showPercent val="1"/><c:showLeaderLines val="1"/></c:dLbls>';
    }
    return '<c:dLbls><c:showVal val="1"/><c:showCatName val="0"/><c:showPercent val="0"/></c:dLbls>';
}
function chartPlotXml(string $type, array $labels, array $values, string $labelRange, string $valueRange, bool $showPercent): string {
    $seriesXml = '<c:ser><c:idx val="0"/><c:order val="0"/><c:tx><c:v>Количество студентов</c:v></c:tx>' . chartCacheXml($labels, $values, $labelRange, $valueRange) . '</c:ser>';
    $labelsXml = chartLabelsXml($showPercent, $type);

    if (in_array($type, ['line', 'smooth', 'step'], true)) {
        $smooth = $type === 'smooth' ? '1' : '0';
        return '<c:lineChart><c:grouping val="standard"/><c:varyColors val="0"/>'
            . $seriesXml
            . $labelsXml
            . '<c:marker val="1"/><c:smooth val="' . $smooth . '"/><c:axId val="123456"/><c:axId val="654321"/></c:lineChart>
<c:catAx><c:axId val="123456"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="b"/><c:tickLblPos val="nextTo"/><c:crossAx val="654321"/><c:crosses val="autoZero"/></c:catAx>
<c:valAx><c:axId val="654321"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="l"/><c:majorGridlines/><c:numFmt formatCode="General" sourceLinked="1"/><c:tickLblPos val="nextTo"/><c:crossAx val="123456"/><c:crosses val="autoZero"/></c:valAx>';
    }

    if ($type === 'area') {
        return '<c:areaChart><c:grouping val="standard"/><c:varyColors val="0"/>'
            . $seriesXml
            . $labelsXml
            . '<c:axId val="123456"/><c:axId val="654321"/></c:areaChart>
<c:catAx><c:axId val="123456"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="b"/><c:tickLblPos val="nextTo"/><c:crossAx val="654321"/><c:crosses val="autoZero"/></c:catAx>
<c:valAx><c:axId val="654321"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="l"/><c:majorGridlines/><c:numFmt formatCode="General" sourceLinked="1"/><c:tickLblPos val="nextTo"/><c:crossAx val="123456"/><c:crosses val="autoZero"/></c:valAx>';
    }

    if ($type === 'pie' || $type === 'doughnut') {
        $tag = $type === 'doughnut' ? 'doughnutChart' : 'pieChart';
        $hole = $type === 'doughnut' ? '<c:holeSize val="58"/>' : '';
        return '<c:' . $tag . '><c:varyColors val="1"/>'
            . $seriesXml
            . $labelsXml
            . $hole
            . '</c:' . $tag . '>';
    }

    return '<c:barChart><c:barDir val="col"/><c:grouping val="clustered"/><c:varyColors val="1"/>'
        . $seriesXml
        . $labelsXml
        . '<c:axId val="123456"/><c:axId val="654321"/></c:barChart>
<c:catAx><c:axId val="123456"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="b"/><c:tickLblPos val="nextTo"/><c:crossAx val="654321"/><c:crosses val="autoZero"/></c:catAx>
<c:valAx><c:axId val="654321"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="l"/><c:majorGridlines/><c:numFmt formatCode="General" sourceLinked="1"/><c:tickLblPos val="nextTo"/><c:crossAx val="123456"/><c:crosses val="autoZero"/></c:valAx>';
}
function chartPartXml(string $title, array $labels, array $values, string $labelRange, string $valueRange, string $type = 'bar', bool $showPercent = false): string {
    $type = in_array($type, ['doughnut', 'pie', 'bar', 'line', 'smooth', 'area', 'step'], true) ? $type : 'bar';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<c:chart>
<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="ru-RU" sz="1200" b="1"/><a:t>' . xmlText($title) . '</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>
<c:plotArea><c:layout/>
' . chartPlotXml($type, $labels, $values, $labelRange, $valueRange, $showPercent) . '
</c:plotArea><c:legend><c:legendPos val="r"/><c:overlay val="0"/></c:legend><c:plotVisOnly val="1"/></c:chart>
</c:chartSpace>';
}
function wordChartDrawingXml(string $relationId, int $docPrId, string $name): string {
    return '<w:p><w:r><w:drawing>
<wp:inline distT="0" distB="0" distL="0" distR="0">
<wp:extent cx="9144000" cy="5029200"/><wp:effectExtent l="0" t="0" r="0" b="0"/>
<wp:docPr id="' . $docPrId . '" name="' . xmlText($name) . '"/><wp:cNvGraphicFramePr/>
<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart r:id="' . $relationId . '"/></a:graphicData></a:graphic>
</wp:inline>
</w:drawing></w:r></w:p>';
}
function wordChartDataTableXml(array $exportChartData): string {
    $rows = [['Показатель', 'Значение']];
    foreach ($exportChartData['labels'] as $index => $label) {
        $rows[] = [$label, $exportChartData['values'][$index] ?? 0];
    }

    $xml = '<w:tbl><w:tblPr><w:tblBorders>
<w:top w:val="single" w:sz="6" w:space="0" w:color="999999"/>
<w:left w:val="single" w:sz="6" w:space="0" w:color="999999"/>
<w:bottom w:val="single" w:sz="6" w:space="0" w:color="999999"/>
<w:right w:val="single" w:sz="6" w:space="0" w:color="999999"/>
<w:insideH w:val="single" w:sz="6" w:space="0" w:color="999999"/>
<w:insideV w:val="single" w:sz="6" w:space="0" w:color="999999"/>
</w:tblBorders></w:tblPr>';
    foreach ($rows as $rowIndex => $row) {
        $xml .= '<w:tr>';
        foreach ($row as $value) {
            $bold = $rowIndex === 0 ? '<w:rPr><w:b/></w:rPr>' : '';
            $xml .= '<w:tc><w:p><w:r>' . $bold . '<w:t>' . xmlText($value) . '</w:t></w:r></w:p></w:tc>';
        }
        $xml .= '</w:tr>';
    }
    return $xml . '</w:tbl>';
}
function addPhpSpreadsheetChart($chartSheet, string $name, string $title, string $chartType, string $topLeft, string $bottomRight, int $count, bool $showPercent, string $dataSheetName = 'Диаграммы'): void {
    $lastChartRow = $count + 1;
    $dataSheetRef = "'" . str_replace("'", "''", $dataSheetName) . "'";
    $dataSeriesLabels = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
            \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING,
            $dataSheetRef . "!\$B\$1",
            null,
            1
        ),
    ];
    $xAxisTickValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
            \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING,
            $dataSheetRef . "!\$A\$2:\$A\$$lastChartRow",
            null,
            $count
        ),
    ];
    $dataSeriesValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
            \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_NUMBER,
            $dataSheetRef . "!\$B\$2:\$B\$$lastChartRow",
            null,
            $count
        ),
    ];
    $seriesType = match ($chartType) {
        'line', 'smooth', 'step' => \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_LINECHART,
        'area' => \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_AREACHART,
        'pie' => \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_PIECHART,
        'doughnut' => \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_DOUGHNUTCHART,
        default => \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_BARCHART,
    };
    $grouping = in_array($chartType, ['line', 'smooth', 'step', 'area'], true)
        ? \PhpOffice\PhpSpreadsheet\Chart\DataSeries::GROUPING_STANDARD
        : ($chartType === 'bar' ? \PhpOffice\PhpSpreadsheet\Chart\DataSeries::GROUPING_CLUSTERED : null);
    $series = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
        $seriesType,
        $grouping,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues,
        \PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_COL,
        $chartType === 'smooth'
    );
    $layout = new \PhpOffice\PhpSpreadsheet\Chart\Layout([
        'showVal' => !$showPercent || !in_array($chartType, ['pie', 'doughnut'], true),
        'showPercent' => $showPercent && in_array($chartType, ['pie', 'doughnut'], true),
        'showCatName' => false,
    ]);
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea($layout, [$series]);
    $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_RIGHT, null, false);
    $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
        $name,
        new \PhpOffice\PhpSpreadsheet\Chart\Title($title),
        $legend,
        $plotArea,
        true,
        'gap',
        new \PhpOffice\PhpSpreadsheet\Chart\Title('Показатель'),
        new \PhpOffice\PhpSpreadsheet\Chart\Title('Количество')
    );
    $chart->setTopLeftPosition($topLeft);
    $chart->setBottomRightPosition($bottomRight);
    $chartSheet->addChart($chart);
}
function exportXlsxWithPhpSpreadsheet(array $headers, array $rows, array $exportChartData, string $filename, string $chartType, string $graphType, bool $showPercent, string $exportVisuals): void {
    $autoload = __DIR__ . '/../edu/vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException('PhpSpreadsheet не найден');
    }
    require_once $autoload;

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('HR-Аналитика');
    $sheet->fromArray($headers, null, 'A1');
    if ($rows) {
        $sheet->fromArray($rows, null, 'A2');
    }
    $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $lastRow = max(1, count($rows) + 1);
    $sheet->getStyle("A1:$lastColumn" . '1')->getFont()->setBold(true);
    $sheet->getStyle("A1:$lastColumn$lastRow")->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    $sheet->setAutoFilter("A1:$lastColumn$lastRow");
    $widths = [6, 30, 18, 14, 18, 14, 22, 34, 24, 24, 24, 18, 20, 18, 44];
    foreach ($widths as $i => $width) {
        $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1))->setWidth($width);
    }

    $visualParts = exportVisualParts($exportVisuals, $chartType, $graphType);
    if ($visualParts) {
        $chartSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Диаграммы');
        $spreadsheet->addSheet($chartSheet);
        $chartSheet->setCellValue('A1', 'Показатель');
        $chartSheet->setCellValue('B1', 'Значение');
        $chartSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $chartSheet->getColumnDimension('A')->setWidth(28);
        $chartSheet->getColumnDimension('B')->setWidth(14);

        $formulas = chartFormulaMap(max(2, count($rows) + 1));
        foreach ($exportChartData['labels'] as $i => $label) {
            $rowNum = $i + 2;
            $chartSheet->setCellValue("A$rowNum", $label);
            $chartSheet->setCellValue("B$rowNum", '=' . ($formulas[$label] ?? '0'));
        }

        $count = count($exportChartData['labels']);
        foreach ($visualParts as $index => $part) {
            $topLeft = $index === 0 ? 'D2' : 'D24';
            $bottomRight = $index === 0 ? 'N22' : 'N44';
            addPhpSpreadsheetChart($chartSheet, $part['name'], $part['title'], $part['type'], $topLeft, $bottomRight, $count, $showPercent);
        }
    }

    $spreadsheet->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setIncludeCharts((bool)$visualParts);
    $writer->setPreCalculateFormulas(true);
    $writer->save('php://output');
}
function exportVisualXlsxWithPhpSpreadsheet(array $exportChartData, string $filename, string $chartType, string $graphType, bool $showPercent, string $exportVisuals): void {
    $autoload = __DIR__ . '/../edu/vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException('PhpSpreadsheet не найден');
    }
    require_once $autoload;

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $dataSheet = $spreadsheet->getActiveSheet();
    $dataSheet->setTitle('Данные');
    $dataSheet->setCellValue('A1', 'Показатель');
    $dataSheet->setCellValue('B1', 'Значение');
    $dataSheet->getStyle('A1:B1')->getFont()->setBold(true);
    $dataSheet->getColumnDimension('A')->setWidth(30);
    $dataSheet->getColumnDimension('B')->setWidth(14);
    foreach ($exportChartData['labels'] as $i => $label) {
        $rowNum = $i + 2;
        $dataSheet->setCellValue("A$rowNum", $label);
        $dataSheet->setCellValue("B$rowNum", (int)($exportChartData['values'][$i] ?? 0));
    }

    $visualParts = exportVisualParts($exportVisuals, $chartType, $graphType);
    if ($visualParts) {
        $chartSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Диаграммы');
        $spreadsheet->addSheet($chartSheet);
        $count = count($exportChartData['labels']);
        foreach ($visualParts as $index => $part) {
            $topLeft = $index === 0 ? 'A1' : 'A24';
            $bottomRight = $index === 0 ? 'L22' : 'L45';
            addPhpSpreadsheetChart($chartSheet, $part['name'], $part['title'], $part['type'], $topLeft, $bottomRight, $count, $showPercent, 'Данные');
        }
        $spreadsheet->setActiveSheetIndex(1);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setIncludeCharts((bool)$visualParts);
    $writer->save('php://output');
}

[$headers, $rows] = exportRows($students);
$exportChartData = filterChartExportData(chartExportData($students), $exportChartIndexes);

// ════════════════════════════════════════════════════════════
// CSV
// ════════════════════════════════════════════════════════════
if ($format === 'csv') {
    $filename = downloadFileName('hr-analitika', 'csv');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel корректно открывает кириллицу
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

// ════════════════════════════════════════════════════════════
// XLSX / Excel
// ════════════════════════════════════════════════════════════
if ($format === 'excel' || $format === 'xlsx') {
    $filename = downloadFileName('hr-analitika', 'xlsx');
    if (file_exists(__DIR__ . '/../edu/vendor/autoload.php')) {
        if ($exportScope === 'visuals') {
            exportVisualXlsxWithPhpSpreadsheet($exportChartData, $filename, $exportChartType, $exportGraphType, $exportChartPercent, $exportVisuals);
        } else {
            exportXlsxWithPhpSpreadsheet($headers, $rows, $exportChartData, $filename, $exportChartType, $exportGraphType, $exportChartPercent, 'none');
        }
        exit;
    }

    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        die('Для экспорта Excel требуется расширение PHP ZipArchive. Включите php_zip в OpenServer.');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        die('Не удалось создать Excel-файл');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
'
 . ($canExportHrCharts ? '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
<Override PartName="/xl/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
' : '') .
'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
'
 . ($canExportHrCharts ? '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
' : '') .
'</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="HR-Аналитика" sheetId="1" r:id="rId1"/>'
 . ($canExportHrCharts ? '<sheet name="Диаграммы" sheetId="2" r:id="rId3"/>' : '') .
'</sheets>
</workbook>');

    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>
<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs>
</styleSheet>');

    $maxRow = max(1, count($rows) + 1);
    $lastCol = colName(count($headers) - 1);
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<dimension ref="A1:' . $lastCol . $maxRow . '"/>
<cols>';
    $widths = [6, 30, 16, 12, 18, 38, 22, 30, 26, 20, 22, 18, 45];
    foreach ($widths as $i => $width) {
        $col = $i + 1;
        $sheetXml .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
    }
    $sheetXml .= '</cols><sheetData>';

    $sheetXml .= '<row r="1">';
    foreach ($headers as $i => $header) {
        $cellRef = colName($i) . '1';
        $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr" s="1"><is><t>' . xmlText($header) . '</t></is></c>';
    }
    $sheetXml .= '</row>';

    foreach ($rows as $r => $row) {
        $rowNum = $r + 2;
        $sheetXml .= '<row r="' . $rowNum . '">';
        foreach ($row as $i => $value) {
            $cellRef = colName($i) . $rowNum;
            $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr" s="0"><is><t>' . xmlText($value) . '</t></is></c>';
        }
        $sheetXml .= '</row>';
    }

    $sheetXml .= '</sheetData><autoFilter ref="A1:' . $lastCol . $maxRow . '"/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

    if ($canExportHrCharts) {
        $fallbackParts = exportVisualParts($exportVisuals, $exportChartType, $exportGraphType);
        $fallbackPart = $fallbackParts[0] ?? ['type' => 'bar', 'title' => $exportChartData['title']];
        $fallbackLastRow = count($exportChartData['labels']) + 1;
        $chartSheetRows = [
            ['A1', 'Показатель', 1, null],
            ['B1', 'Значение', 1, null],
        ];
        foreach ($exportChartData['labels'] as $i => $label) {
            $chartSheetRows[] = ['A' . ($i + 2), $label, 0, null];
            $chartSheetRows[] = ['B' . ($i + 2), $exportChartData['values'][$i] ?? 0, 0, null];
        }
        $rowsByNumber = [];
        foreach ($chartSheetRows as [$ref, $value, $style, $formula]) {
            preg_match('/\d+/', $ref, $m);
            $rowNum = (int)($m[0] ?? 1);
            $rowsByNumber[$rowNum][] = $formula !== null
                ? xlsxFormulaCell($ref, $formula, $value, $style)
                : (is_int($value)
                ? xlsxNumberCell($ref, $value, $style)
                : xlsxInlineCell($ref, $value, $style));
        }

        $chartSheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<dimension ref="A1:B' . $fallbackLastRow . '"/><cols><col min="1" max="1" width="34" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/></cols><sheetData>';
        foreach ($rowsByNumber as $rowNum => $cells) {
            $chartSheetXml .= '<row r="' . $rowNum . '">' . implode('', $cells) . '</row>';
        }
        $chartSheetXml .= '</sheetData><drawing r:id="rId1"/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
        $zip->addFromString('xl/worksheets/sheet2.xml', $chartSheetXml);
        $zip->addFromString('xl/worksheets/_rels/sheet2.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>');
        $zip->addFromString('xl/drawings/drawing1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<xdr:twoCellAnchor><xdr:from><xdr:col>3</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>12</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>22</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to><xdr:graphicFrame macro=""><xdr:nvGraphicFramePr><xdr:cNvPr id="2" name="HR-диаграмма"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr><xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" r:id="rId1"/></a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor>
</xdr:wsDr>');
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart1.xml"/>
</Relationships>');
        $zip->addFromString('xl/charts/chart1.xml', chartPartXml($fallbackPart['title'], $exportChartData['labels'], $exportChartData['values'], "'Диаграммы'!\$A\$2:\$A\$$fallbackLastRow", "'Диаграммы'!\$B\$2:\$B\$$fallbackLastRow", $fallbackPart['type'], $exportChartPercent));
    }

    $created = gmdate('Y-m-d\TH:i:s\Z');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>СВГТК Портал</dc:creator>
<dc:title>HR-Аналитика</dc:title>
<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>
</cp:coreProperties>');

    $zip->close();
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// ════════════════════════════════════════════════════════════
// DOCX / Word — оставлен для совместимости со старой кнопкой
// ════════════════════════════════════════════════════════════
if ($format === 'word' || $format === 'docx') {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        die('Для экспорта Word требуется расширение PHP ZipArchive. Включите php_zip в OpenServer.');
    }

    $wordVisualParts = exportVisualParts($exportVisuals, $exportChartType, $exportGraphType);
    $wordChartContentTypes = '';
    foreach ($wordVisualParts as $index => $part) {
        $wordChartContentTypes .= '<Override PartName="/word/charts/chart' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
';
    }

    $filename = downloadFileName('hr-analitika', 'docx');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $tmpFile = tempnam(sys_get_temp_dir(), 'docx_');
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        die('Не удалось создать Word-файл');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
' . $wordChartContentTypes . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>');

    $wordTitle = $exportScope === 'visuals' ? 'Диаграммы и графики HR-статистики' : 'Отчёт о трудоустройстве выпускников';
    $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"><w:body>
<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t>' . xmlText($wordTitle) . '</w:t></w:r></w:p>
<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>СВГТК им. Абая Кунанбаева</w:t></w:r></w:p>
<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>' . date('d.m.Y') . '</w:t></w:r></w:p>
<w:p><w:r><w:t>Всего записей: ' . count($rows) . '</w:t></w:r></w:p>';

    if ($fSearch !== '') {
        $docXml .= '<w:p><w:r><w:t>Поиск: ' . xmlText($fSearch) . '</w:t></w:r></w:p>';
    }

    if ($wordVisualParts) {
        $docXml .= '<w:p><w:r><w:rPr><w:b/><w:sz w:val="26"/></w:rPr><w:t>Диаграммы статистики</w:t></w:r></w:p>';
        $docXml .= '<w:p><w:r><w:t>Данные для построения диаграмм:</w:t></w:r></w:p>';
        $docXml .= wordChartDataTableXml($exportChartData);
        foreach ($wordVisualParts as $index => $part) {
            $docXml .= wordChartDrawingXml('rId' . ($index + 1), 10 + $index, $part['label']);
        }
    }

    if ($exportScope !== 'visuals') {
        $docXml .= '<w:tbl><w:tblPr><w:tblBorders>
<w:top w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:left w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:bottom w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:right w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:insideH w:val="single" w:sz="8" w:space="0" w:color="000000"/>
<w:insideV w:val="single" w:sz="8" w:space="0" w:color="000000"/>
</w:tblBorders></w:tblPr>';

        $wordHeaders = ['ФИО', 'Группа', 'Статус', 'Организация', 'Должность', 'Дата', 'По спец.'];
        $docXml .= '<w:tr>';
        foreach ($wordHeaders as $h) {
            $docXml .= '<w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' . xmlText($h) . '</w:t></w:r></w:p></w:tc>';
        }
        $docXml .= '</w:tr>';

        foreach ($students as $row) {
            $data = [
                valueOrDash(fullName($row)),
                valueOrDash($row['group_name'] ?? ''),
                statusText($row['status'] ?? null),
                valueOrDash($row['employer_name'] ?? ''),
                valueOrDash($row['position'] ?? ''),
                fmtDate($row['employment_date'] ?? null),
                yesNo($row['is_by_specialty'] ?? 0),
            ];
            $docXml .= '<w:tr>';
            foreach ($data as $v) {
                $docXml .= '<w:tc><w:p><w:r><w:t>' . xmlText($v) . '</w:t></w:r></w:p></w:tc>';
            }
            $docXml .= '</w:tr>';
        }
        $docXml .= '</w:tbl>';
    }
    $docXml .= '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/></w:sectPr></w:body></w:document>';
    $zip->addFromString('word/document.xml', $docXml);

    if ($wordVisualParts) {
        $relationships = '';
        $lastWordChartRow = count($exportChartData['labels']) + 1;
        foreach ($wordVisualParts as $index => $part) {
            $chartNumber = $index + 1;
            $relationships .= '<Relationship Id="rId' . $chartNumber . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart' . $chartNumber . '.xml"/>
';
            $zip->addFromString(
                'word/charts/chart' . $chartNumber . '.xml',
                chartPartXml($part['title'], $exportChartData['labels'], $exportChartData['values'], 'Диаграммы!$A$2:$A$' . $lastWordChartRow, 'Диаграммы!$B$2:$B$' . $lastWordChartRow, $part['type'], $exportChartPercent)
            );
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
' . $relationships . '</Relationships>');
    }

    $created = gmdate('Y-m-d\TH:i:s\Z');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>СВГТК Портал</dc:creator>
<dc:title>HR-Аналитика</dc:title>
<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>
</cp:coreProperties>');

    $zip->close();
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

http_response_code(400);
echo 'Неверный формат экспорта';
