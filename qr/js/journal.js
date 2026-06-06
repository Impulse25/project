/* ═══════════════════════════════════════════════════════
   journal.js — СВГТК QR-Посещаемость
   Зависимости (должны быть объявлены ДО этого файла):
     - attendanceData  (array)  — из PHP через json_encode
     - eduGroups       (object) — из PHP через json_encode
   ═══════════════════════════════════════════════════════ */

/* ── Защита: если данные не переданы из PHP ── */
if (typeof attendanceData === 'undefined') {
  console.error('[journal.js] attendanceData не определён. Убедись, что в <head> есть блок <script> с var attendanceData = ...');
  window.attendanceData = [];
}
if (typeof eduGroups === 'undefined') {
  console.error('[journal.js] eduGroups не определён. Убедись, что в <head> есть блок <script> с var eduGroups = ...');
  window.eduGroups = {};
}

/* ── Тема ── */
(function () {
  var saved = localStorage.getItem('svgtk-theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  updateThemeIcon(saved);
})();

function toggleTheme() {
  var html = document.documentElement;
  var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('svgtk-theme', next);
  updateThemeIcon(next);
}

function updateThemeIcon(t) {
  var ic = document.getElementById('themeIcon');
  if (!ic) return;
  ic.innerHTML = t === 'dark'
    ? '<path stroke-linecap="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'
    : '<circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>';
}

/* Кнопка темы: поддержка как onclick="toggleTheme()", так и addEventListener */
var _themeBtn = document.getElementById('themeToggle');
if (_themeBtn && !_themeBtn.getAttribute('onclick')) {
  _themeBtn.addEventListener('click', toggleTheme);
}

/* ── Sidebar ── */
var _sidebar     = document.getElementById('sidebar');
var _mainWrapper = document.getElementById('mainWrapper');

var _sidebarToggle = document.getElementById('sidebarToggle');
if (_sidebarToggle) {
  _sidebarToggle.addEventListener('click', function () {
    if (_sidebar)     _sidebar.classList.toggle('collapsed');
    if (_mainWrapper) _mainWrapper.classList.toggle('sidebar-collapsed');
  });
}

var _mobileMenuBtn = document.getElementById('mobileMenuBtn');
if (_mobileMenuBtn) {
  _mobileMenuBtn.addEventListener('click', function () {
    if (_sidebar) _sidebar.classList.toggle('mobile-open');
  });
}

/* ── Состояние таблицы ── */
var ROWS_PER_PAGE = 10;
var currentPage   = 1;
var sortCol       = 'action_time';
var sortDir       = -1; // сначала новые
var filtered      = [];

/* ── Вспомогательная: название группы по id ── */
function getGroupName(gid) {
  var g = eduGroups[String(gid)];
  return g ? g.name : ('Гр.' + gid);
}

/* ── Динамические фильтры из реальных данных ── */
function buildFilters() {
  /* Группы */
  var groupIds = attendanceData
    .map(function (r) { return r.group_id; })
    .filter(function (v, i, a) { return a.indexOf(v) === i; })
    .sort(function (a, b) { return a - b; });

  var fGroup = document.getElementById('fGroup');
  if (fGroup) {
    groupIds.forEach(function (gid) {
      var opt = document.createElement('option');
      opt.value = gid;
      opt.textContent = getGroupName(gid);
      fGroup.appendChild(opt);
    });
  }

  /* Даты */
  var dates = attendanceData
    .map(function (r) { return r.action_time ? r.action_time.slice(0, 10) : null; })
    .filter(function (v) { return !!v; })
    .filter(function (v, i, a) { return a.indexOf(v) === i; })
    .sort()
    .reverse();

  var fDate = document.getElementById('fDate');
  if (fDate) {
    dates.forEach(function (d) {
      var opt = document.createElement('option');
      opt.value = d;
      var parts = d.split('-');
      opt.textContent = parts[2] + '.' + parts[1] + '.' + parts[0];
      fDate.appendChild(opt);
    });
  }
}

/* ── KPI-метрики ── */
function updateMetrics() {
  var today = new Date().toISOString().slice(0, 10);

  var entries  = attendanceData.filter(function (r) { return r.action === 'entry'; }).length;
  var exits    = attendanceData.filter(function (r) { return r.action === 'exit'; }).length;
  var students = new Set(attendanceData.map(function (r) { return r.iin; })).size;
  var todayCnt = attendanceData.filter(function (r) { return r.action_time && r.action_time.startsWith(today); }).length;

  var el;
  if ((el = document.getElementById('m-entries')))  el.textContent = entries;
  if ((el = document.getElementById('m-exits')))    el.textContent = exits;
  if ((el = document.getElementById('m-students'))) el.textContent = students;
  if ((el = document.getElementById('m-today')))    el.textContent = todayCnt;

  if ((el = document.getElementById('totalStudentsInfo'))) {
    var groupCount = Object.keys(eduGroups).length;
    el.textContent = groupCount + ' ' + pluralGroups(groupCount) + ' · ' + students + ' уникальных студентов';
  }
}

function pluralGroups(n) {
  if (n % 10 === 1 && n % 100 !== 11) return 'группа';
  if (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20)) return 'группы';
  return 'групп';
}

/* ── Фильтрация ── */
function applyFilters() {
  var q  = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
  var fa = document.getElementById('fAction')?.value || '';
  var fg = document.getElementById('fGroup')?.value  || '';
  var fd = document.getElementById('fDate')?.value   || '';

  filtered = attendanceData.filter(function (r) {
    if (fa && r.action !== fa) return false;
    if (fg && String(r.group_id) !== String(fg)) return false;
    if (fd && r.action_time && !r.action_time.startsWith(fd)) return false;
    if (q) {
      var grpName = getGroupName(r.group_id).toLowerCase();
      var str = [r.surname, r.name, r.patronymic, r.iin, grpName]
        .join(' ').toLowerCase();
      if (!str.includes(q)) return false;
    }
    return true;
  });

  /* Сортировка */
  filtered.sort(function (a, b) {
    var av = sortCol === 'group_id' ? getGroupName(a[sortCol]) : (a[sortCol] ?? '');
    var bv = sortCol === 'group_id' ? getGroupName(b[sortCol]) : (b[sortCol] ?? '');
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * sortDir;
    return String(av).localeCompare(String(bv), 'ru') * sortDir;
  });

  currentPage = 1;
  render();
}

function resetFilters() {
  var el;
  if ((el = document.getElementById('searchInput'))) el.value = '';
  if ((el = document.getElementById('fAction')))     el.value = '';
  if ((el = document.getElementById('fGroup')))      el.value = '';
  if ((el = document.getElementById('fDate')))       el.value = '';
  applyFilters();
}

function sortBy(col) {
  document.querySelectorAll('thead th').forEach(function (th) { th.classList.remove('sorted'); });
  var thEl = document.getElementById('th-' + col);
  if (thEl) thEl.classList.add('sorted');
  if (sortCol === col) {
    sortDir *= -1;
  } else {
    sortCol = col;
    sortDir = 1;
  }
  applyFilters();
}

/* ── Рендер таблицы ── */
function render() {
  var total = filtered.length;
  var pages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
  var start = (currentPage - 1) * ROWS_PER_PAGE;
  var slice = filtered.slice(start, start + ROWS_PER_PAGE);
  var tbody = document.getElementById('tableBody');
  if (!tbody) return;

  if (!slice.length) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="9" style="text-align:center;padding:2rem;color:var(--color-text-muted)">Записей не найдено</td></tr>';
  } else {
    tbody.innerHTML = slice.map(function (r) {
      var actionBadge = r.action === 'entry'
        ? '<span class="badge badge-entry">↗ Вход</span>'
        : '<span class="badge badge-exit">↙ Выход</span>';
      return '<tr>'
        + '<td>' + (r.id ?? '—') + '</td>'
        + '<td class="cell-iin">' + (r.iin ?? '—') + '</td>'
        + '<td>' + (r.surname ?? '—') + '</td>'
        + '<td>' + (r.name ?? '—') + '</td>'
        + '<td>' + (r.patronymic || '—') + '</td>'
        + '<td><span class="badge badge-blue">' + getGroupName(r.group_id) + '</span></td>'
        + '<td>' + actionBadge + '</td>'
        + '<td>' + (r.action_time ?? '—') + '</td>'
        + '<td class="cell-ip">' + (r.device_ip ?? '—') + '</td>'
        + '</tr>';
    }).join('');
  }

  /* Информация о странице */
  var s = total ? start + 1 : 0;
  var e = Math.min(start + ROWS_PER_PAGE, total);
  var pageInfoEl = document.getElementById('pageInfo');
  if (pageInfoEl) {
    pageInfoEl.textContent = total
      ? 'Показано ' + s + '–' + e + ' из ' + total + ' записей'
      : 'Нет записей';
  }

  /* Кнопки пагинации */
  var btns = document.getElementById('pageBtns');
  if (!btns) return;
  btns.innerHTML = '';

  function addBtn(label, page, disabled) {
    var b = document.createElement('button');
    b.className = 'pg-btn' + (page === currentPage ? ' active' : '');
    b.textContent = label;
    b.disabled = !!disabled;
    b.addEventListener('click', function () { currentPage = page; render(); });
    btns.appendChild(b);
  }

  addBtn('‹', currentPage - 1, currentPage === 1);
  var from = Math.max(1, currentPage - 2);
  var to   = Math.min(pages, from + 4);
  if (to - from < 4) from = Math.max(1, to - 4);
  for (var p = from; p <= to; p++) addBtn(p, p, false);
  addBtn('›', currentPage + 1, currentPage === pages);
}

/* ── Экспорт CSV ── */
function exportCSV() {
  var cols  = ['id','iin','surname','name','patronymic','group_id','action','action_time','device_ip'];
  var heads = ['ID','ИИН','Фамилия','Имя','Отчество','Группа','Действие','Время','IP'];
  var rows  = filtered.map(function (r) {
    return cols.map(function (c) {
      var v = c === 'group_id' ? getGroupName(r[c])
            : c === 'action'   ? (r[c] === 'entry' ? 'Вход' : 'Выход')
            : (r[c] ?? '');
      return '"' + String(v).replace(/"/g, '""') + '"';
    }).join(',');
  });
  var blob = new Blob(['\uFEFF' + heads.join(',') + '\n' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'attendance_' + new Date().toISOString().slice(0, 10) + '.csv';
  a.click();
}

/* ── Экспорт Excel ── */
function exportExcel() {
  if (typeof XLSX === 'undefined') {
    alert('Библиотека XLSX не загружена. Проверь подключение к интернету или CDN.');
    return;
  }
  var cols  = ['id','iin','surname','name','patronymic','group_id','action','action_time','device_ip'];
  var heads = ['ID','ИИН','Фамилия','Имя','Отчество','Группа','Действие','Время','IP-адрес'];
  var wsData = [heads];
  filtered.forEach(function (r) {
    wsData.push(cols.map(function (c) {
      if (c === 'group_id') return getGroupName(r[c]);
      if (c === 'action')   return r[c] === 'entry' ? 'Вход' : 'Выход';
      return r[c] ?? '';
    }));
  });
  var wb = XLSX.utils.book_new();
  var ws = XLSX.utils.aoa_to_sheet(wsData);
  ws['!cols'] = [{wch:6},{wch:14},{wch:18},{wch:14},{wch:18},{wch:12},{wch:10},{wch:20},{wch:16}];
  XLSX.utils.book_append_sheet(wb, ws, 'Посещаемость');
  XLSX.writeFile(wb, 'attendance_' + new Date().toISOString().slice(0, 10) + '.xlsx');
}

/* ── Инициализация ── */
(function init() {
  var footerDate = document.getElementById('footerDate');
  if (footerDate) {
    footerDate.textContent = new Date().toLocaleString('ru-RU', { day: '2-digit', month: 'long', year: 'numeric' });
  }

  buildFilters();
  updateMetrics();
  applyFilters();
})();