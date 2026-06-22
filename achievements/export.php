<?php
require_once 'includes/header.php';

if (!in_array($role, ['admin','teacher','director'])) {
    header('Location: ' . SITE_URL . '/dashboard.php'); exit;
}
$isTeacher = ($role === 'teacher');

$pdo = getPDO();

$filterGroup    = (int)($_GET['group_id']  ?? 0);
$filterLevel    = trim($_GET['level']      ?? '');
$filterCategory = trim($_GET['category']   ?? '');
$filterType     = trim($_GET['type']       ?? 'all');   // all / students / teachers
$filterKind     = trim($_GET['kind']       ?? 'cert');  // all / ach / cert  (по умолчанию сертификаты)
$filterFrom     = trim($_GET['date_from']  ?? '');
$filterTo       = trim($_GET['date_to']    ?? '');
$doExport       = isset($_GET['export']);
$exportFmt      = trim($_GET['fmt']        ?? 'csv');    // csv / xlsx

$allGroups = getAllGroups();
$results   = [];

/* ============ ДОСТИЖЕНИЯ ============ */
if ($filterKind === 'all' || $filterKind === 'ach') {
    try {
        $where  = ['1=1'];
        $params = [];
        if ($filterLevel)    { $where[] = 'a.level = ?';       $params[] = $filterLevel; }
        if ($filterCategory) { $where[] = 'a.category = ?';    $params[] = $filterCategory; }
        if ($filterFrom)     { $where[] = 'a.date_event >= ?'; $params[] = $filterFrom; }
        if ($filterTo)       { $where[] = 'a.date_event <= ?'; $params[] = $filterTo; }

        if ($filterType === 'students' || $filterType === 'all') {
            $hasEduCol = (bool)$pdo->query("SHOW COLUMNS FROM achievements LIKE 'edu_student_id'")->fetch();
            if ($hasEduCol) {
                $sw = array_merge(['a.edu_student_id IS NOT NULL'], $where);
                $sp = $params;
                if ($filterGroup) { $sw[] = 'es.group_id = ?'; $sp[] = $filterGroup; }
                $stmt = $pdo->prepare("SELECT
                    CONCAT(es.surname,' ',es.name,
                        IF(es.patronymic!='' AND es.patronymic IS NOT NULL,CONCAT(' ',es.patronymic),'')) AS full_name,
                    g.name AS group_name, 'Студент' AS person_type, 'Достижение' AS record_kind,
                    a.title, a.category AS extra, a.level, a.place, a.date_event AS rec_date, a.file_path
                    FROM achievements a
                    JOIN edu_students es ON a.edu_student_id = es.id
                    LEFT JOIN edu_groups g ON es.group_id = g.id
                    WHERE " . implode(' AND ', $sw) . "
                    ORDER BY g.name, es.surname");
                $stmt->execute($sp);
                $results = array_merge($results, $stmt->fetchAll());
            }
        }

        // Достижения преподавателей (в группах их нет, поэтому при фильтре по группе — пропускаем)
        if (($filterType === 'teachers' || $filterType === 'all') && !$filterGroup) {
            $tw = array_merge(['a.user_id > 0', 'a.edu_student_id IS NULL'], $where);
            $tp = $params;
            if ($isTeacher) {                       // преподаватель видит только свои
                $tw[] = '(a.user_id = ? OR a.added_by = ?)';
                $tp[] = $user['id']; $tp[] = $user['id'];
            }
            $stmt = $pdo->prepare("SELECT
                u.full_name, '' AS group_name, 'Преподаватель' AS person_type, 'Достижение' AS record_kind,
                a.title, a.category AS extra, a.level, a.place, a.date_event AS rec_date, a.file_path
                FROM achievements a
                JOIN users u ON a.user_id = u.id
                WHERE " . implode(' AND ', $tw) . "
                ORDER BY u.full_name");
            $stmt->execute($tp);
            $results = array_merge($results, $stmt->fetchAll());
        }
    } catch (Exception $e) {}
}

/* ============ СЕРТИФИКАТЫ ============ */
/* Период считается по дате выдачи (issue_date), уровень — по c.level */
if ($filterKind === 'all' || $filterKind === 'cert') {
    try {
        $cBase   = [];
        $cParams = [];
        if ($filterLevel) { $cBase[] = 'c.level = ?';       $cParams[] = $filterLevel; }
        if ($filterFrom)  { $cBase[] = 'c.issue_date >= ?'; $cParams[] = $filterFrom; }
        if ($filterTo)    { $cBase[] = 'c.issue_date <= ?'; $cParams[] = $filterTo; }

        $hasEduColC = (bool)$pdo->query("SHOW COLUMNS FROM certificates LIKE 'edu_student_id'")->fetch();

        if (($filterType === 'students' || $filterType === 'all') && $hasEduColC) {
            $sw = array_merge(['c.edu_student_id IS NOT NULL'], $cBase);
            $sp = $cParams;
            if ($filterGroup) { $sw[] = 'es.group_id = ?'; $sp[] = $filterGroup; }
            $stmt = $pdo->prepare("SELECT
                CONCAT(es.surname,' ',es.name,
                    IF(es.patronymic!='' AND es.patronymic IS NOT NULL,CONCAT(' ',es.patronymic),'')) AS full_name,
                g.name AS group_name, 'Студент' AS person_type, 'Сертификат' AS record_kind,
                c.title, c.issuer AS extra, c.level, c.place, c.issue_date AS rec_date, c.file_path
                FROM certificates c
                JOIN edu_students es ON c.edu_student_id = es.id
                LEFT JOIN edu_groups g ON es.group_id = g.id
                WHERE " . implode(' AND ', $sw) . "
                ORDER BY g.name, es.surname");
            $stmt->execute($sp);
            $results = array_merge($results, $stmt->fetchAll());
        }

        // Сертификаты преподавателей (без привязки к группе)
        if (($filterType === 'teachers' || $filterType === 'all') && !$filterGroup) {
            $nullc = $hasEduColC ? '(c.edu_student_id IS NULL OR c.edu_student_id = 0)' : '1=1';
            $tw = array_merge(['c.user_id > 0', $nullc], $cBase);
            $tp = $cParams;
            if ($isTeacher) {                       // преподаватель видит только свои
                $tw[] = '(c.user_id = ? OR (c.user_id = 0 AND c.added_by = ?) OR c.recipient_name = ?)';
                $tp[] = $user['id']; $tp[] = $user['id']; $tp[] = $user['full_name'];
            }
            $stmt = $pdo->prepare("SELECT
                u.full_name, '' AS group_name, 'Преподаватель' AS person_type, 'Сертификат' AS record_kind,
                c.title, c.issuer AS extra, c.level, c.place, c.issue_date AS rec_date, c.file_path
                FROM certificates c
                JOIN users u ON c.user_id = u.id
                WHERE " . implode(' AND ', $tw) . "
                ORDER BY u.full_name");
            $stmt->execute($tp);
            $results = array_merge($results, $stmt->fetchAll());
        }
    } catch (Exception $e) {}
}

/* Текст в колонке «Категория / Орг.»: для достижений — категория, для сертификатов — организация */
function extraLabel(array $r): string {
    if (($r['record_kind'] ?? '') === 'Сертификат') {
        return $r['extra'] ?? '';
    }
    return categoryLabel($r['extra'] ?? '');
}

/* Генерация настоящего .xlsx без сторонних библиотек (xlsx = zip из xml) */
function exportXlsx(string $filename, array $header, array $rows): void {
    $colLetter = function (int $n): string {
        $s = '';
        for ($n++; $n > 0; $n = intdiv($n - 1, 26)) { $s = chr(65 + ($n - 1) % 26) . $s; }
        return $s;
    };
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');

    $sheetRows = '';
    foreach (array_merge([$header], $rows) as $ri => $cells) {
        $rowNum   = $ri + 1;
        $isHeader = ($ri === 0);
        $cellsXml = '';
        foreach (array_values($cells) as $ci => $val) {
            $ref   = $colLetter($ci) . $rowNum;
            $style = $isHeader ? ' s="1"' : '';
            $cellsXml .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$esc($val).'</t></is></c>';
        }
        $sheetRows .= '<row r="'.$rowNum.'">'.$cellsXml.'</row>';
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetData>'.$sheetRows.'</sheetData></worksheet>';
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        .'</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Выгрузка" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        .'</Relationships>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        .'<borders count="1"><border/></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('Не удалось создать XLSX'); }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
}

/* ============ ВЫГРУЗКА (CSV или Excel) ============ */
if ($doExport && !empty($results)) {
    $headerRow = ['ФИО','Группа','Тип','Вид','Название','Категория / Орг.','Уровень','Место','Дата'];
    $dataRows  = [];
    foreach ($results as $r) {
        $dataRows[] = [
            $r['full_name'],
            $r['group_name'] ?: '—',
            $r['person_type'],
            $r['record_kind'],
            $r['title'],
            extraLabel($r) ?: '—',
            levelLabel($r['level'] ?? ''),
            $r['place'] ?: '—',
            $r['rec_date'] ?? '—',
        ];
    }
    if ($exportFmt === 'xlsx') {
        exportXlsx('export_'.date('Y-m-d').'.xlsx', $headerRow, $dataRows);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headerRow, ';');
    foreach ($dataRows as $row) { fputcsv($out, $row, ';'); }
    fclose($out);
    exit;
}
$emptyExport = ($doExport && empty($results));   // нажали «Скачать», а под фильтр ничего не попало
?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">📊 Выгрузка по критериям</div>
    <div class="page-header-sub">Выберите период и уровень, затем скачайте файл в Excel или CSV</div>
  </div>
</div>

<?php if ($emptyExport): ?>
<div class="alert alert-error anim-fade" style="margin-bottom:var(--space-4)">По заданным критериям ничего не найдено — измените фильтры.</div>
<?php endif; ?>

<div class="card anim-fade" style="padding:var(--space-5)">
  <form method="GET" action="<?= SITE_URL ?>/export.php">
    <input type="hidden" name="export" value="1">

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem">
      <div class="form-group" style="margin:0">
        <label class="form-label">Что выгружать</label>
        <select name="kind" class="form-control">
          <option value="cert" <?= $filterKind==='cert'?'selected':'' ?>>Сертификаты</option>
          <option value="ach"  <?= $filterKind==='ach'?'selected':'' ?>>Достижения</option>
          <option value="all"  <?= $filterKind==='all'?'selected':'' ?>>Всё</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Дата с</label>
        <input type="date" name="date_from" class="form-control" value="<?= h($filterFrom) ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Дата по</label>
        <input type="date" name="date_to" class="form-control" value="<?= h($filterTo) ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Уровень</label>
        <select name="level" class="form-control">
          <option value="">Все уровни</option>
          <option value="college"       <?= $filterLevel==='college'?'selected':'' ?>>Колледж</option>
          <option value="city"          <?= $filterLevel==='city'?'selected':'' ?>>Город</option>
          <option value="regional"      <?= $filterLevel==='regional'?'selected':'' ?>>Область</option>
          <option value="national"      <?= $filterLevel==='national'?'selected':'' ?>>Республика</option>
          <option value="international" <?= $filterLevel==='international'?'selected':'' ?>>Международный</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Тип людей</label>
        <select name="type" class="form-control">
          <option value="all"      <?= $filterType==='all'?'selected':'' ?>>Все</option>
          <option value="students" <?= $filterType==='students'?'selected':'' ?>>Студенты</option>
          <option value="teachers" <?= $filterType==='teachers'?'selected':'' ?>>Преподаватели</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Группа <span style="font-size:.7rem;color:var(--text-m)">(студенты)</span></label>
        <select name="group_id" class="form-control">
          <option value="">Все группы</option>
          <?php foreach ($allGroups as $g): ?>
            <option value="<?= $g['id'] ?>" <?= $filterGroup===$g['id']?'selected':'' ?>><?= h($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="font-size:.75rem;color:var(--text-m);margin:.85rem 0 1.1rem">
      Период для достижений считается по дате мероприятия, для сертификатов — по дате выдачи. Пустые даты — без ограничения по периоду.
    </div>

    <div style="border-top:1px solid var(--border);padding-top:1.1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <span style="font-weight:700">Скачать:</span>
      <button type="submit" name="fmt" value="xlsx" class="btn btn-primary">⬇ Excel (.xlsx)</button>
      <button type="submit" name="fmt" value="csv"  class="btn btn-secondary">⬇ CSV</button>
      <a href="<?= SITE_URL ?>/export.php?kind=<?= h($filterKind) ?>" class="btn btn-secondary">Сброс</a>
    </div>
  </form>
</div>

<?php require_once 'includes/footer.php'; ?>
