<?php
require_once 'includes/header.php';

if (!in_array($role, ['admin','director'])) {
    header('Location: ' . SITE_URL . '/dashboard.php'); exit;
}

$pdo = getPDO();

$filterGroup    = (int)($_GET['group_id']  ?? 0);
$filterLevel    = trim($_GET['level']      ?? '');
$filterCategory = trim($_GET['category']   ?? '');
$filterType     = trim($_GET['type']       ?? 'all');
$filterFrom     = trim($_GET['date_from']  ?? '');
$filterTo       = trim($_GET['date_to']    ?? '');
$doExport       = isset($_GET['export']);

$allGroups = getAllGroups();
$results   = [];

try {
    $where  = ['1=1'];
    $params = [];

    if ($filterGroup)    { $where[] = 'es.group_id = ?';   $params[] = $filterGroup; }
    if ($filterLevel)    { $where[] = 'a.level = ?';       $params[] = $filterLevel; }
    if ($filterCategory) { $where[] = 'a.category = ?';    $params[] = $filterCategory; }
    if ($filterFrom)     { $where[] = 'a.date_event >= ?'; $params[] = $filterFrom; }
    if ($filterTo)       { $where[] = 'a.date_event <= ?'; $params[] = $filterTo; }

    if ($filterType === 'students' || $filterType === 'all') {
        $hasEduCol = (bool)$pdo->query("SHOW COLUMNS FROM achievements LIKE 'edu_student_id'")->fetch();
        if ($hasEduCol) {
            $sw   = array_merge(['a.edu_student_id IS NOT NULL'], $where);
            $stmt = $pdo->prepare("SELECT
                CONCAT(es.surname,' ',es.name,
                    IF(es.patronymic!='' AND es.patronymic IS NOT NULL,CONCAT(' ',es.patronymic),'')) AS full_name,
                g.name AS group_name, 'Студент' AS person_type,
                a.title, a.category, a.level, a.place, a.date_event, a.file_path
                FROM achievements a
                JOIN edu_students es ON a.edu_student_id = es.id
                LEFT JOIN edu_groups g ON es.group_id = g.id
                WHERE " . implode(' AND ', $sw) . "
                ORDER BY g.name, es.surname");
            $stmt->execute($params);
            $results = array_merge($results, $stmt->fetchAll());
        }
    }

    if ($filterType === 'teachers' || $filterType === 'all') {
        $tw   = array_merge(['a.user_id > 0', 'a.edu_student_id IS NULL'], $where);
        $stmt = $pdo->prepare("SELECT
            u.full_name, '' AS group_name, 'Преподаватель' AS person_type,
            a.title, a.category, a.level, a.place, a.date_event, a.file_path
            FROM achievements a
            JOIN users u ON a.user_id = u.id
            WHERE " . implode(' AND ', $tw) . "
            ORDER BY u.full_name");
        $stmt->execute($params);
        $results = array_merge($results, $stmt->fetchAll());
    }
} catch (Exception $e) {
    $results = [];
}

if ($doExport && !empty($results)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ФИО','Группа','Тип','Достижение','Категория','Уровень','Место','Дата'], ';');
    foreach ($results as $r) {
        fputcsv($out, [
            $r['full_name'],
            $r['group_name'] ?? '—',
            $r['person_type'],
            $r['title'],
            categoryLabel($r['category'] ?? ''),
            levelLabel($r['level'] ?? ''),
            $r['place'] ?? '—',
            $r['date_event'] ?? '—',
        ], ';');
    }
    fclose($out);
    exit;
}
?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">📊 Выгрузка по критериям</div>
    <div class="page-header-sub">Фильтрация и экспорт достижений в CSV</div>
  </div>
</div>

<div class="card anim-fade" style="padding:var(--space-5);margin-bottom:var(--space-5)">
  <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem">
    <div class="form-group" style="margin:0">
      <label class="form-label">Группа</label>
      <select name="group_id" class="form-control">
        <option value="">Все группы</option>
        <?php foreach ($allGroups as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $filterGroup===$g['id']?'selected':'' ?>><?= h($g['name']) ?></option>
        <?php endforeach; ?>
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
      <label class="form-label">Категория</label>
      <select name="category" class="form-control">
        <option value="">Все</option>
        <option value="olympiad"   <?= $filterCategory==='olympiad'?'selected':'' ?>>Олимпиада</option>
        <option value="conference" <?= $filterCategory==='conference'?'selected':'' ?>>Конференция</option>
        <option value="sport"      <?= $filterCategory==='sport'?'selected':'' ?>>Спорт</option>
        <option value="art"        <?= $filterCategory==='art'?'selected':'' ?>>Творчество</option>
        <option value="science"    <?= $filterCategory==='science'?'selected':'' ?>>Наука</option>
        <option value="other"      <?= $filterCategory==='other'?'selected':'' ?>>Другое</option>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Уровень</label>
      <select name="level" class="form-control">
        <option value="">Все уровни</option>
        <option value="college"       <?= $filterLevel==='college'?'selected':'' ?>>Колледж</option>
        <option value="city"          <?= $filterLevel==='city'?'selected':'' ?>>Город</option>
        <option value="regional"      <?= $filterLevel==='regional'?'selected':'' ?>>Регион</option>
        <option value="national"      <?= $filterLevel==='national'?'selected':'' ?>>Республика</option>
        <option value="international" <?= $filterLevel==='international'?'selected':'' ?>>Международный</option>
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
    <div class="form-group" style="margin:0;display:flex;align-items:flex-end;gap:.5rem">
      <button type="submit" class="btn btn-primary" style="flex:1">🔍 Найти</button>
      <a href="export.php" class="btn btn-secondary">Сброс</a>
    </div>
  </form>
</div>

<div class="card anim-fade">
  <div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-4) var(--space-5);border-bottom:1px solid var(--border)">
    <div style="font-weight:600">Найдено: <?= count($results) ?> записей</div>
    <?php if (!empty($results)): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'1'])) ?>"
       class="btn btn-primary">⬇ Экспорт CSV (Excel)</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th><th>ФИО</th><th>Группа</th><th>Тип</th>
          <th>Достижение</th><th>Категория</th><th>Уровень</th><th>Место</th><th>Дата</th><th>Файл</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($results)): ?>
        <tr><td colspan="10" style="text-align:center;padding:var(--space-10);color:var(--text-m)">
          Выберите критерии и нажмите «Найти»
        </td></tr>
      <?php else: foreach ($results as $i => $r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td style="font-weight:600"><?= h($r['full_name']) ?></td>
          <td><?= h($r['group_name']??'—') ?></td>
          <td><span class="badge badge-<?= $r['person_type']==='Студент'?'green':'blue' ?>"><?= $r['person_type'] ?></span></td>
          <td><?= h($r['title']) ?></td>
          <td><span class="badge badge-blue"><?= categoryLabel($r['category']??'') ?></span></td>
          <td><span class="badge badge-gray"><?= levelLabel($r['level']??'') ?></span></td>
          <td><?= $r['place'] ? '<span class="badge badge-amber">'.h($r['place']).'</span>' : '—' ?></td>
          <td><?= h($r['date_event']??'—') ?></td>
          <td>
            <?php if (!empty($r['file_path'])): ?>
              <a href="<?= SITE_URL ?>/uploads/<?= h($r['file_path']) ?>" target="_blank" class="btn btn-secondary btn-sm" download>⬇</a>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>