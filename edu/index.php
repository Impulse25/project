<?php
require 'vendor/autoload.php';
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ── Роль текущего пользователя ─────────────────────────────────────────────
$role      = $_SESSION['role'] ?? 'guest';   // admin | director | teacher | guest
$userId    = $_SESSION['user_id'] ?? 0;
$isAdmin   = $role === 'admin';
$isDir     = $role === 'director';
$isTeacher = $role === 'teacher';

$message     = '';
$messageType = '';

// ── Загрузка Excel (только admin) ─────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
            try {
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $sheet       = $spreadsheet->getActiveSheet();
                $rows        = $sheet->toArray(null, true, true, false);

                // ── Кешируем группы и специальности по имени для быстрого поиска ──
                $groupMap    = [];  // name => id
                $specMap     = [];  // name_ru => id
                foreach ($pdo->query("SELECT id, name FROM edu_groups")->fetchAll(PDO::FETCH_ASSOC) as $g) {
                    $groupMap[mb_strtolower(trim($g['name']))] = $g['id'];
                }
                foreach ($pdo->query("SELECT id, name_ru FROM edu_specialties")->fetchAll(PDO::FETCH_ASSOC) as $sp) {
                    $specMap[mb_strtolower(trim($sp['name_ru']))] = $sp['id'];
                }

                // ── Вспомогательные функции ────────────────────────────
                // Определяем, является ли строка заголовком (содержит нечисловые данные в нужных полях)
                $isHeaderRow = function(array $row): bool {
                    $cell = trim((string)($row[3] ?? '')); // колонка "Дата рождения"
                    // Если в колонке даты не дата-подобная строка — это заголовок
                    return !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cell)
                        && !preg_match('/^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}$/', $cell)
                        && !is_numeric($cell);
                };

                $parseDate = function($val): ?string {
                    if ($val === null || trim((string)$val) === '') return null;
                    // PhpSpreadsheet может вернуть float (Excel serial date)
                    if (is_float($val) || (is_numeric($val) && strpos((string)$val, '-') === false)) {
                        $unix = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float)$val);
                        return date('Y-m-d', $unix);
                    }
                    $str = trim((string)$val);
                    // Уже ISO
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) return $str;
                    // dd.mm.yyyy или dd/mm/yyyy
                    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $str, $m)) {
                        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                    }
                    return null;
                };

                $lookupGroup = function(?string $val) use ($groupMap): ?int {
                    if ($val === null || trim($val) === '') return null;
                    $key = mb_strtolower(trim($val));
                    return $groupMap[$key] ?? null;
                };

                $lookupSpec = function(?string $val) use ($specMap): ?int {
                    if ($val === null || trim($val) === '') return null;
                    $key = mb_strtolower(trim($val));
                    return $specMap[$key] ?? null;
                };

                // ── Формат файла: Фамилия|Имя|Отчество|Дата рождения|Группа|Специальность|ИИН|Гражданство|Национальность
                // Колонки: 0=Фамилия, 1=Имя, 2=Отчество, 3=Дата, 4=Группа(имя), 5=Спец(имя), 6=ИИН (опц), 7=Гражд, 8=Нац
                // Пропускаем любые строки-заголовки автоматически

                // Полная замена — очищаем таблицы перед вставкой
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $pdo->exec("TRUNCATE TABLE edu_student_cards");
                $pdo->exec("TRUNCATE TABLE edu_students");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                $stmt = $pdo->prepare("
                    INSERT INTO edu_students
                        (surname, name, patronymic, birth_date, group_id, speciality_id, iin, citizenship, nationality)
                    VALUES
                        (:surname, :name, :patronymic, :birth_date, :group_id, :speciality_id, :iin, :citizenship, :nationality)
                ");

                $imported = 0;
                $skipped  = 0;
                foreach ($rows as $row) {
                    $surname = trim((string)($row[0] ?? ''));
                    $name    = trim((string)($row[1] ?? ''));

                    // Пропускаем пустые строки
                    if ($surname === '' && $name === '') { $skipped++; continue; }

                    // Пропускаем заголовки — строки где дата не дата
                    if ($isHeaderRow($row)) { $skipped++; continue; }

                    $birthDate = $parseDate($row[3] ?? null);
                    if (!$birthDate) { $skipped++; continue; } // без даты — не студент

                    $groupVal = trim((string)($row[4] ?? ''));
                    $specVal  = trim((string)($row[5] ?? ''));
                    $iin = trim((string)($row[6] ?? ''));

                    // Если ИИН пуст — ищем по ФИО+дата, иначе генерируем временный
                    if ($iin === '') {
                        $chk = $pdo->prepare("SELECT iin FROM edu_students WHERE surname=? AND name=? AND birth_date=? LIMIT 1");
                        $chk->execute([$surname, $name, $birthDate]);
                        $existing = $chk->fetchColumn();
                        if ($existing) {
                            $iin = $existing; // обновляем существующего
                        } else {
                            // Генерируем временный ИИН: T + 11 символов hex (итого 12)
                            $iin = 'T' . strtoupper(substr(md5($surname.$name.$birthDate), 0, 11));
                        }
                    }

                    $stmt->execute([
                        ':surname'       => $surname,
                        ':name'          => $name,
                        ':patronymic'    => trim((string)($row[2] ?? '')),
                        ':birth_date'    => $birthDate,
                        ':group_id'      => $lookupGroup($groupVal),
                        ':speciality_id' => $lookupSpec($specVal),
                        ':iin'           => $iin,
                        ':citizenship'   => trim((string)($row[7] ?? '')),
                        ':nationality'   => trim((string)($row[8] ?? '')),
                    ]);
                    $imported++;
                }

                // ── Сохраняем файл и пишем лог ────────────────────────
                $importDir = __DIR__ . '/uploads/imports/';
                if (!is_dir($importDir)) mkdir($importDir, 0755, true);
                $savedName = date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                $savedPath = 'uploads/imports/' . $savedName;
                move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $savedPath);

                $pdo->prepare("
                    INSERT INTO edu_import_logs (file_name, imported_at, user_id, file_path)
                    VALUES (?, NOW(), ?, ?)
                ")->execute([$file['name'], $userId, $savedPath]);

                $skipNote = $skipped > 0 ? " (пропущено строк-заголовков/пустых: $skipped)" : '';
                $message     = "Успешно импортировано $imported студентов из файла «{$file['name']}»$skipNote";
                $messageType = 'success';
            } catch (Exception $e) {
                $message     = 'Ошибка при чтении файла: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message     = 'Допустимые форматы: .xlsx, .xls, .csv';
            $messageType = 'error';
        }
    } else {
        $message     = 'Ошибка загрузки файла.';
        $messageType = 'error';
    }
}

// ── Экспорт Excel (только admin) ──────────────────────────────────────────
if ($isAdmin && isset($_GET['export']) && $_GET['export'] === '1') {

    // Очищаем буфер, чтобы Excel-файл не ломался из-за лишнего вывода
    if (ob_get_length()) {
        ob_end_clean();
    }

    $rows = $pdo->query("
        SELECT
               s.surname,
               s.name,
               s.patronymic,
               s.birth_date,
               g.name AS group_name,
               sp.name_ru AS specialty,
               s.iin,
               s.citizenship,
               s.nationality
        FROM edu_students s
        LEFT JOIN edu_groups g ON g.id = s.group_id
        LEFT JOIN edu_specialties sp ON sp.id = s.speciality_id
        ORDER BY s.surname, s.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    // Заголовки — строго в том порядке, который ждёт импорт
    $headers = [
        'Фамилия',
        'Имя',
        'Отчество',
        'Дата рождения',
        'Группа',
        'Специальность',
        'ИИН',
        'Гражданство',
        'Национальность'
    ];

    $sheet->fromArray($headers, null, 'A1');

    $rowNum = 2;

    foreach ($rows as $r) {

        // Форматируем дату
        $birthDate = '';

        if (!empty($r['birth_date'])) {
            $birthDate = date('d.m.Y', strtotime($r['birth_date']));
        }

        $sheet->fromArray([
            $r['surname'],
            $r['name'],
            $r['patronymic'],
            $birthDate,
            $r['group_name'],
            $r['specialty'],
            $r['iin'],
            $r['citizenship'],
            $r['nationality']
        ], null, "A$rowNum");

        $rowNum++;
    }

    // Жирные заголовки
    $sheet->getStyle('A1:I1')->getFont()->setBold(true);

    // Автоширина колонок
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Заголовки ответа
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="students_export.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    exit;
}

// ── Список групп для фильтра (admin + director) ───────────────────────────
$allGroups = [];
if ($isAdmin || $isDir) {
    $allGroups = $pdo->query("SELECT id, name FROM edu_groups ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}
$filterGroup = (isset($_GET['group_id']) && $_GET['group_id'] !== '') ? (int)$_GET['group_id'] : null;

// ── Выборка студентов из БД ───────────────────────────────────────────────
$sql    = "
    SELECT s.id, s.surname, s.name, s.patronymic,
           s.birth_date,
           g.name AS group_name, g.id AS group_id,
           sp.name_ru AS specialty,
           s.iin, s.citizenship, s.nationality
    FROM edu_students s
    LEFT JOIN edu_groups g  ON g.id  = s.group_id
    LEFT JOIN edu_specialties sp ON sp.id = s.speciality_id
";
$params = [];

if ($isTeacher) {
    // Только студенты групп, где куратором является текущий пользователь
    $sql   .= " WHERE g.curator_id = :uid";
    $params[':uid'] = $userId;
} elseif ($filterGroup) {
    $sql   .= " WHERE s.group_id = :gid";
    $params[':gid'] = $filterGroup;
}
$sql .= " ORDER BY s.surname, s.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total    = count($students);

// Статистика
$groups   = count(array_unique(array_column($students, 'group_id')));
$citizens = count(array_unique(array_filter(array_column($students, 'citizenship'))));

// ── Настройки страницы ─────────────────────────────────────────────────────
$pageTitle       = 'Учебный процесс — СВГТК Портал';
$activeNav       = 'edu';
$sidebarSubtitle = 'Учебный процесс';
$breadcrumbs     = [
    ['label' => 'СВГТК', 'href' => '../'],
    ['label' => 'Учебный процесс'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <?php require 'includes/head.php' ?>
  <style>
    .upload-zone { border: 2px dashed var(--color-border); border-radius: var(--radius-lg); padding: 2.5rem; text-align: center; transition: border-color var(--transition), background var(--transition); cursor: pointer; position: relative; }
    .upload-zone:hover, .upload-zone.dragover { border-color: var(--color-primary); background: var(--color-primary-highlight); }
    .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .upload-icon { width: 52px; height: 52px; border-radius: var(--radius-lg); background: var(--color-primary-highlight); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }
    .upload-title { font-weight: 600; font-size: 1rem; color: var(--color-text); margin-bottom: 0.25rem; }
    .upload-sub   { font-size: 0.875rem; color: var(--color-text-muted); }
    .upload-hint  { font-size: 0.8125rem; color: var(--color-text-faint); margin-top: 0.5rem; }
    .template-hint { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; background: var(--color-primary-highlight); border-radius: var(--radius-md); font-size: 0.875rem; color: var(--color-primary); margin-top: 1rem; border: 1px solid color-mix(in srgb, var(--color-primary) 20%, transparent); }
    .stats-strip  { display: flex; gap: 2rem; flex-wrap: wrap; padding: 1rem 1.5rem; background: var(--color-surface-2); border-bottom: 1px solid var(--color-divider); }
    .stat-item    { display: flex; flex-direction: column; gap: 2px; }
    .stat-value   { font-weight: 700; font-size: 1.125rem; font-variant-numeric: tabular-nums; }
    .stat-label   { font-size: 0.75rem; color: var(--color-text-muted); }
    .table-wrapper { overflow-x: auto; }
    .data-table { width: 100%; min-width: 700px; }
    .data-table th { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); padding: 0.75rem 1rem; background: var(--color-surface-2); border-bottom: 1px solid var(--color-divider); text-align: left; white-space: nowrap; }
    .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--color-divider); font-size: 0.9375rem; vertical-align: middle; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tbody tr { transition: background var(--transition); cursor: pointer; }
    .data-table tbody tr:hover { background: var(--color-primary-highlight); }
    .show-more-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--color-divider); color: var(--color-primary); font-weight: 500; font-size: 0.9375rem; transition: background var(--transition); cursor: pointer; width: 100%; }
    .show-more-btn:hover { background: var(--color-primary-highlight); }
    .empty-state      { text-align: center; padding: 3rem 1.5rem; }
    .empty-state-icon { width: 72px; height: 72px; border-radius: 18px; background: var(--color-surface-offset); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; }
    .empty-state-title { font-weight: 600; font-size: 1.125rem; color: var(--color-text); margin-bottom: 0.5rem; }
    .empty-state-sub  { font-size: 0.9375rem; color: var(--color-text-muted); }
    .filter-bar { display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1.5rem; background: var(--color-surface-2); border-bottom: 1px solid var(--color-divider); flex-wrap: wrap; }
    .filter-bar label { font-size: 0.875rem; font-weight: 500; color: var(--color-text-muted); white-space: nowrap; }
    .filter-bar select { font-size: 0.875rem; padding: 0.375rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text); }
    .refs-nav { display: flex; gap: 0.625rem; flex-wrap: wrap; }
  </style>
</head>
<body>

<?php require 'includes/sidebar.php' ?>

<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>

  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Учебный процесс</h1>
        <p class="page-subtitle">Управление данными студентов</p>
      </div>
      <div class="page-actions">
        <?php if ($isAdmin): ?>
        <div class="refs-nav">
          <a href="specialties.php"   class="btn btn-outline">Специальности</a>
          <a href="groups.php"        class="btn btn-outline">Группы</a>
          <a href="subjects.php"      class="btn btn-outline">Дисциплины</a>
          <a href="semesters.php"     class="btn btn-outline">Семестры</a>
          <a href="import_logs.php"   class="btn btn-outline">История импортов</a>
          <a href="grade_sheets.php"   class="btn btn-outline">Ведомости</a>
        </div>
        <?php if ($total > 0): ?>
        <a href="index.php?export=1<?= $filterGroup ? '&group_id='.$filterGroup : '' ?>" class="btn btn-success">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Экспорт Excel
        </a>
        <?php endif ?>
        <?php endif ?>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
      <?php if ($messageType === 'success'): ?>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php else: ?>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?php endif ?>
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif ?>

    <?php if ($isAdmin): ?>
    <!-- Загрузка файла -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Импорт из Excel
        </span>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <div class="upload-zone" id="uploadZone">
            <input type="file" name="excel_file" id="fileInput" accept=".xlsx,.xls,.csv">
            <div class="upload-icon">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1a56db" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            <div class="upload-title" id="uploadTitle">Перетащите файл или нажмите для выбора</div>
            <div class="upload-sub">Поддерживаемые форматы: .xlsx, .xls, .csv</div>
            <div class="upload-hint">Максимальный размер: 10 МБ</div>
          </div>
          <div class="template-hint">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Колонки файла: <strong style="margin-left:4px">Фамилия · Имя · Отчество · Дата рождения · Группа (название) · Специальность (название) · ИИН · Гражданство · Национальность</strong>
          </div>
          <div style="margin-top:1rem">
            <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M3 17v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/></svg>
              Загрузить файл
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endif ?>

    <!-- Таблица студентов -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">
          Список студентов
        </span>
        <?php if ($total > 0): ?>
        <span style="font-size:0.875rem;color:var(--color-text-muted)"><?= $total ?> записей</span>
        <?php endif ?>
      </div>

      <?php if ($isAdmin || $isDir): ?>
      <!-- Фильтр по группе -->
      <form method="GET" action="index.php" class="filter-bar">
        <label for="group_filter">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:-2px"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Фильтр по группе:
        </label>
        <select name="group_id" id="group_filter" onchange="this.form.submit()">
          <option value="">— Все группы —</option>
          <?php foreach ($allGroups as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $filterGroup == $g['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($g['name']) ?>
          </option>
          <?php endforeach ?>
        </select>
        <?php if ($filterGroup): ?>
        <a href="index.php" class="btn btn-outline" style="padding:0.3rem 0.75rem;font-size:0.8125rem">Сбросить</a>
        <?php endif ?>
      </form>
      <?php endif ?>

      <?php if ($total > 0): ?>
      <div class="stats-strip">
        <div class="stat-item"><span class="stat-value"><?= $total ?></span><span class="stat-label">Всего студентов</span></div>
        <div class="stat-item"><span class="stat-value"><?= $groups ?></span><span class="stat-label">Групп</span></div>
        <div class="stat-item"><span class="stat-value"><?= $citizens ?></span><span class="stat-label">Гражданств</span></div>
      </div>

      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>ID</th><th>Фамилия</th><th>Имя</th><th>Отчество</th>
              <th>Дата рождения</th><th>Группа</th><th>Специальность</th>
              <th>ИИН</th><th>Гражданство</th><th>Национальность</th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <?php foreach (array_slice($students, 0, 25) as $i => $s): ?>
            <tr class="student-row" data-index="<?= $i ?>" onclick="openStudent(<?= $i ?>)">
              <td style="color:var(--color-text-muted)"><?= $i + 1 ?></td>
              <td style="font-variant-numeric:tabular-nums;color:var(--color-text-muted)"><?= htmlspecialchars($s['id']) ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($s['surname']) ?></td>
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td style="color:var(--color-text-muted)"><?= htmlspecialchars($s['patronymic']) ?></td>
              <td><?= htmlspecialchars($s['birth_date']) ?></td>
              <td><span class="badge badge-gray"><?= htmlspecialchars($s['group_name'] ?? '—') ?></span></td>
              <td style="font-size:0.875rem;color:var(--color-text-muted)"><?= htmlspecialchars($s['specialty'] ?? '—') ?></td>
              <td style="font-family:monospace;font-size:0.875rem"><?= htmlspecialchars($s['iin']) ?></td>
              <td><?= htmlspecialchars($s['citizenship'] ?? '—') ?></td>
              <td><?= htmlspecialchars($s['nationality'] ?? '—') ?></td>
            </tr>
            <?php endforeach ?>
          </tbody>
          <?php if ($total > 25): ?>
          <tbody id="hiddenRows" style="display:none">
            <?php foreach (array_slice($students, 25) as $i => $s): $real = $i + 25; ?>
            <tr class="student-row" onclick="openStudent(<?= $real ?>)">
              <td style="color:var(--color-text-muted)"><?= $real + 1 ?></td>
              <td style="font-variant-numeric:tabular-nums;color:var(--color-text-muted)"><?= htmlspecialchars($s['id']) ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($s['surname']) ?></td>
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td style="color:var(--color-text-muted)"><?= htmlspecialchars($s['patronymic']) ?></td>
              <td><?= htmlspecialchars($s['birth_date']) ?></td>
              <td><span class="badge badge-gray"><?= htmlspecialchars($s['group_name'] ?? '—') ?></span></td>
              <td style="font-size:0.875rem;color:var(--color-text-muted)"><?= htmlspecialchars($s['specialty'] ?? '—') ?></td>
              <td style="font-family:monospace;font-size:0.875rem"><?= htmlspecialchars($s['iin']) ?></td>
              <td><?= htmlspecialchars($s['citizenship'] ?? '—') ?></td>
              <td><?= htmlspecialchars($s['nationality'] ?? '—') ?></td>
            </tr>
            <?php endforeach ?>
          </tbody>
          <?php endif ?>
        </table>
      </div>

      <?php if ($total > 25): ?>
      <button class="show-more-btn" id="showMoreBtn" onclick="toggleRows()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="showMoreIcon"><polyline points="6 9 12 15 18 9"/></svg>
        Подробнее — ещё <?= $total - 25 ?> записей
      </button>
      <?php endif ?>

      <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-faint)" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="empty-state-title">Нет данных</div>
        <div class="empty-state-sub">
          <?php if ($isTeacher): ?>
          Нет студентов в ваших группах
          <?php elseif ($filterGroup): ?>
          В выбранной группе нет студентов
          <?php else: ?>
          В базе данных пока нет студентов<?= $isAdmin ? '. Используйте импорт выше.' : '' ?>
          <?php endif ?>
        </div>
      </div>
      <?php endif ?>
    </div>

  </main>
</div>

<!-- Форма перехода на карточку студента -->
<form method="POST" action="about_student.php" id="studentForm" style="display:none">
  <input type="hidden" name="id"          id="f_id">
  <input type="hidden" name="surname"     id="f_surname">
  <input type="hidden" name="name"        id="f_name">
  <input type="hidden" name="patronymic"  id="f_patronymic">
  <input type="hidden" name="birth_date"  id="f_birth_date">
  <input type="hidden" name="group_id"    id="f_group_id">
  <input type="hidden" name="specialty"   id="f_specialty">
  <input type="hidden" name="iin"         id="f_iin">
  <input type="hidden" name="citizenship" id="f_citizenship">
  <input type="hidden" name="nationality" id="f_nationality">
</form>

<script src="assets/app.js"></script>
<script>
const STUDENTS = <?= json_encode($students, JSON_UNESCAPED_UNICODE) ?>;

function openStudent(index) {
  const s = STUDENTS[index];
  if (!s) return;
  document.getElementById('f_id').value          = s.id;
  document.getElementById('f_surname').value     = s.surname;
  document.getElementById('f_name').value        = s.name;
  document.getElementById('f_patronymic').value  = s.patronymic;
  document.getElementById('f_birth_date').value  = s.birth_date;
  document.getElementById('f_group_id').value    = s.group_id;
  document.getElementById('f_specialty').value   = s.specialty ?? '';
  document.getElementById('f_iin').value         = s.iin;
  document.getElementById('f_citizenship').value = s.citizenship ?? '';
  document.getElementById('f_nationality').value = s.nationality ?? '';
  document.getElementById('studentForm').submit();
}

let expanded = false;
function toggleRows() {
  const hidden = document.getElementById('hiddenRows');
  const btn    = document.getElementById('showMoreBtn');
  if (!hidden) return;
  expanded = !expanded;
  hidden.style.display = expanded ? '' : 'none';
  const remaining = <?= max(0, $total - 25) ?>;
  btn.innerHTML = expanded
    ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg> Свернуть'
    : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg> Подробнее — ещё ${remaining} записей`;
}

<?php if ($isAdmin): ?>
const zone      = document.getElementById('uploadZone');
const fileInput = document.getElementById('fileInput');
const submitBtn = document.getElementById('submitBtn');
const titleEl   = document.getElementById('uploadTitle');

fileInput.addEventListener('change', () => {
  if (fileInput.files.length > 0) {
    titleEl.textContent = '📄 ' + fileInput.files[0].name;
    submitBtn.style.display = 'flex';
  }
});
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault();
  zone.classList.remove('dragover');
  fileInput.files = e.dataTransfer.files;
  if (fileInput.files.length > 0) {
    titleEl.textContent = '📄 ' + fileInput.files[0].name;
    submitBtn.style.display = 'flex';
  }
});
<?php endif ?>
</script>
</body>
</html>
