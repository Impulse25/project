<?php
session_start();

$userRole   = $_SESSION['role'] ?? '';
$userName   = $_SESSION['full_name'] ?? '';
$isAdmin    = in_array($userRole, ['admin', 'director']);
$isLoggedIn = isset($_SESSION['user_id']);
$nameParts  = explode(' ', trim($userName));
$initials   = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p,0,1)), array_slice($nameParts,0,2)));

// ── БД и данные (подключение точно как в твоём process.php) ─────────────────
require_once __DIR__ . '/../config/db.php';

// Журнал посещаемости — таблица qr_attendance
$stmt = $pdo->query("
    SELECT a.id, a.iin, a.surname, a.name, a.patronymic,
           a.group_id, a.action, a.action_time,
           a.device_ip, a.browser_name, a.browser_version,
           a.os_name, a.device_type,
           a.screen_width, a.screen_height,
           a.timezone, a.language, a.platform
    FROM qr_attendance a
    ORDER BY a.action_time DESC
");
$attendanceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Группы — таблица edu_groups (как в process.php)
$stmtG     = $pdo->query("SELECT id, name FROM edu_groups ORDER BY name");
$groupsRaw = $stmtG->fetchAll(PDO::FETCH_ASSOC);
$eduGroups = [];
foreach ($groupsRaw as $g) {
    $eduGroups[$g['id']] = ['name' => $g['name']];
}
// ─────────────────────────────────────────────────────────────────────────────

// Страница для хедера
$breadcrumbCurrent = 'QR-Посещаемость';
$breadcrumbLink    = '/';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QR-Посещаемость — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/journal.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

  <!-- Данные PHP → JS. Должны быть ДО подключения journal.js -->
  <script>
    var attendanceData = <?= json_encode(array_values($attendanceRows), JSON_UNESCAPED_UNICODE) ?>;
    var eduGroups      = <?= json_encode($eduGroups, JSON_UNESCAPED_UNICODE) ?>;
  </script>
</head>
<body>

<?php include __DIR__ . '/../qr/sidebar.php'; ?>

<div class="main-wrapper" id="mainWrapper">

  <?php include __DIR__ . '/../qr/header.php'; ?>

  <main class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Журнал QR-посещаемости</h1>
        <p class="page-subtitle">Данные из таблицы посещаемости</p>
      </div>
      <div class="page-actions">
        <a href="/qr/entry.html" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 3h7v7M21 3L11 13M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/></svg>
          Вход/Выход
        </a>
        <button class="btn btn-outline" onclick="document.getElementById('helpModal').classList.add('open')" title="Открыть справку">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path stroke-linecap="round" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
            <line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/>
          </svg>
          Справка
        </button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-card-top"><span class="kpi-label">Входов</span><div class="kpi-icon green">🚪</div></div>
        <div class="kpi-value" id="m-entries">—</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-card-top"><span class="kpi-label">Выходов</span><div class="kpi-icon red">🏃</div></div>
        <div class="kpi-value" id="m-exits">—</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-card-top"><span class="kpi-label">Студентов</span><div class="kpi-icon blue">👥</div></div>
        <div class="kpi-value" id="m-students">—</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-card-top"><span class="kpi-label">Сегодня</span><div class="kpi-icon amber">📅</div></div>
        <div class="kpi-value" id="m-today">—</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Журнал посещаемости</span>
        <div class="card-actions">
          <button class="btn btn-outline btn-sm" onclick="exportCSV()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v12m-4-4 4 4 4-4M5 20h14"/></svg>
            CSV
          </button>
          <button class="btn btn-success btn-sm" onclick="exportExcel()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="18" rx="2"/><path d="M8 3v18M16 3v18M2 9h20M2 15h20"/></svg>
            Excel
          </button>
          <button class="btn btn-outline btn-sm" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
            Печать
          </button>
        </div>
      </div>

      <div class="filters-bar">
        <div class="form-group search-wrap">
          <label class="form-label">Поиск</label>
          <div class="search-input-wrap">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" class="form-control" id="searchInput" placeholder="ФИО, ИИН, группа..." oninput="applyFilters()">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Действие</label>
          <select class="form-control" id="fAction" onchange="applyFilters()">
            <option value="">Все</option>
            <option value="entry">Вход</option>
            <option value="exit">Выход</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Группа</label>
          <select class="form-control" id="fGroup" onchange="applyFilters()">
            <option value="">Все группы</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Дата</label>
          <select class="form-control" id="fDate" onchange="applyFilters()">
            <option value="">Все даты</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" style="opacity:0">.</label>
          <button class="btn btn-outline btn-sm" onclick="resetFilters()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Сбросить
          </button>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th onclick="sortBy('id')"            id="th-id">ID <span>↕</span></th>
              <th onclick="sortBy('iin')"           id="th-iin">ИИН <span>↕</span></th>
              <th onclick="sortBy('surname')"       id="th-surname">Фамилия <span>↕</span></th>
              <th onclick="sortBy('name')"          id="th-name">Имя <span>↕</span></th>
              <th onclick="sortBy('patronymic')"    id="th-patronymic">Отчество <span>↕</span></th>
              <th onclick="sortBy('group_id')"      id="th-group_id">Группа <span>↕</span></th>
              <th onclick="sortBy('action')"        id="th-action">Действие <span>↕</span></th>
              <th onclick="sortBy('action_time')"   id="th-action_time">Время <span>↕</span></th>
              <th onclick="sortBy('device_ip')"     id="th-device_ip">IP-адрес <span>↕</span></th>
              <th onclick="sortBy('browser_name')"  id="th-browser_name">Браузер <span>↕</span></th>
              <th onclick="sortBy('os_name')"       id="th-os_name">ОС <span>↕</span></th>
              <th onclick="sortBy('device_type')"   id="th-device_type">Тип <span>↕</span></th>
            </tr>
          </thead>
          <tbody id="tableBody"></tbody>
        </table>
      </div>

      <div class="pagination">
        <div class="page-info" id="pageInfo">Загрузка...</div>
        <div class="page-btns" id="pageBtns"></div>
      </div>
    </div>
  </main>

  <footer class="page-footer">
    <span>СВГТК им. Абая Кунанбаева — г. Сарань</span>
    <span id="footerDate"></span>
  </footer>
</div>

<script src="js/journal.js"></script>

<div class="help-modal-bg" id="helpModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="help-modal">
    <button class="hm-close" onclick="document.getElementById('helpModal').classList.remove('open')" title="Закрыть">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>

    <h2>Журнал посещаемости</h2>
    <p class="hm-sub">Руководство по работе с index.php</p>

    <div class="hm-section">
      <h3>KPI-карточки</h3>
      <div class="hm-grid">
        <div class="hm-card"><div class="hm-card-title">🚪 Входов</div><div class="hm-card-text">Число зафиксированных входов в выборке</div></div>
        <div class="hm-card"><div class="hm-card-title">🏃 Выходов</div><div class="hm-card-text">Число зафиксированных выходов в выборке</div></div>
        <div class="hm-card"><div class="hm-card-title">👥 Студентов</div><div class="hm-card-text">Уникальных студентов (по ИИН) в выборке</div></div>
        <div class="hm-card"><div class="hm-card-title">📅 Сегодня</div><div class="hm-card-text">События за текущие календарные сутки</div></div>
      </div>
    </div>

    <div class="hm-section">
      <h3>Фильтры и сортировка</h3>
      <ul class="hm-steps">
        <li><span class="hm-dot"></span><span><b>Поиск</b> — по ФИО, ИИН, группе. Работает мгновенно при вводе.</span></li>
        <li><span class="hm-dot"></span><span><b>Действие</b> — показать только входы или только выходы.</span></li>
        <li><span class="hm-dot"></span><span><b>Группа</b> — фильтрация по учебной группе.</span></li>
        <li><span class="hm-dot"></span><span><b>Дата</b> — выборка за конкретный день.</span></li>
        <li><span class="hm-dot"></span><span>Кликните на <b>заголовок столбца</b> для сортировки. Повторный клик — обратный порядок.</span></li>
        <li><span class="hm-dot"></span><span>Кнопка <b>«Сбросить»</b> снимает все фильтры разом.</span></li>
      </ul>
    </div>

    <div class="hm-section">
      <h3>Экспорт данных</h3>
      <ul class="hm-steps">
        <li><span class="hm-dot"></span><span><b>CSV</b> — выгрузка текущей выборки в CSV-файл (UTF-8)</span></li>
        <li><span class="hm-dot"></span><span><b>Excel</b> — выгрузка в формат .xlsx (через SheetJS)</span></li>
        <li><span class="hm-dot"></span><span><b>Печать</b> — диалог печати браузера, сайдбар скрывается автоматически</span></li>
      </ul>
      <div class="hm-note">💡 Сначала установите фильтры, затем экспортируйте — выгружается именно текущая выборка.</div>
    </div>
  </div>
</div>

</body>
</html>