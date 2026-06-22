/* ═══════════════════════════════════════════════════════
   journal.js — СВГТК QR-Посещаемость
   Зависимости (должны быть объявлены ДО этого файла):
     - attendanceData  (array)  — из PHP через json_encode
     - eduGroups       (object) — из PHP через json_encode
     - qrcode.min.js   — CDN, ДОЛЖЕН подключаться ДО journal.js
   ═══════════════════════════════════════════════════════ */

/* ── Защита: если данные не переданы из PHP ── */
if (typeof attendanceData === 'undefined') {
  console.error('[journal.js] attendanceData не определён.');
  window.attendanceData = [];
}
if (typeof eduGroups === 'undefined') {
  console.error('[journal.js] eduGroups не определён.');
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
var sortDir       = -1;
var filtered      = [];

/* ── Название группы по id ── */
function getGroupName(gid) {
  var g = eduGroups[String(gid)];
  return g ? g.name : ('Гр.' + gid);
}

/* ── Динамические фильтры ── */
function buildFilters() {
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

  var dates = attendanceData
    .map(function (r) { return r.action_time ? r.action_time.slice(0, 10) : null; })
    .filter(function (v) { return !!v; })
    .filter(function (v, i, a) { return a.indexOf(v) === i; })
    .sort().reverse();

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

/* ── KPI ── */
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
  var q  = (document.getElementById('searchInput') ? document.getElementById('searchInput').value : '').toLowerCase().trim();
  var fa = document.getElementById('fAction') ? document.getElementById('fAction').value : '';
  var fg = document.getElementById('fGroup')  ? document.getElementById('fGroup').value  : '';
  var fd = document.getElementById('fDate')   ? document.getElementById('fDate').value   : '';

  filtered = attendanceData.filter(function (r) {
    if (fa && r.action !== fa) return false;
    if (fg && String(r.group_id) !== String(fg)) return false;
    if (fd && r.action_time && !r.action_time.startsWith(fd)) return false;
    if (q) {
      var grpName = getGroupName(r.group_id).toLowerCase();
      var str = [r.surname, r.name, r.patronymic, r.iin, grpName].join(' ').toLowerCase();
      if (!str.includes(q)) return false;
    }
    return true;
  });

  filtered.sort(function (a, b) {
    var av = sortCol === 'group_id' ? getGroupName(a[sortCol]) : (a[sortCol] !== undefined ? a[sortCol] : '');
    var bv = sortCol === 'group_id' ? getGroupName(b[sortCol]) : (b[sortCol] !== undefined ? b[sortCol] : '');
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
  if (sortCol === col) { sortDir *= -1; } else { sortCol = col; sortDir = 1; }
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
    tbody.innerHTML = '<tr class="empty-row"><td colspan="12" style="text-align:center;padding:2rem;color:var(--color-text-muted)">Записей не найдено</td></tr>';
  } else {
    tbody.innerHTML = slice.map(function (r) {
      var actionBadge = r.action === 'entry'
        ? '<span class="badge badge-entry">↗ Вход</span>'
        : '<span class="badge badge-exit">↙ Выход</span>';
      var typeEmoji = { desktop: '🖥️', mobile: '📱', tablet: '📟', unknown: '❓' };
      var typeLabel = typeEmoji[r.device_type] || '❓';
      var browserStr = r.browser_name ? r.browser_name + (r.browser_version ? ' ' + r.browser_version.split('.')[0] : '') : '—';
      return '<tr>'
        + '<td>' + (r.id !== undefined ? r.id : '—') + '</td>'
        + '<td class="cell-iin">' + (r.iin || '—') + '</td>'
        + '<td>' + (r.surname || '—') + '</td>'
        + '<td>' + (r.name || '—') + '</td>'
        + '<td>' + (r.patronymic || '—') + '</td>'
        + '<td><span class="badge badge-blue">' + getGroupName(r.group_id) + '</span></td>'
        + '<td>' + actionBadge + '</td>'
        + '<td>' + (r.action_time || '—') + '</td>'
        + '<td class="cell-ip">' + (r.device_ip || '—') + '</td>'
        + '<td>' + browserStr + '</td>'
        + '<td>' + (r.os_name || '—') + '</td>'
        + '<td title="' + (r.device_type || '') + '">' + typeLabel + '</td>'
        + '</tr>';
    }).join('');
  }

  var s = total ? start + 1 : 0;
  var e = Math.min(start + ROWS_PER_PAGE, total);
  var pageInfoEl = document.getElementById('pageInfo');
  if (pageInfoEl) {
    pageInfoEl.textContent = total ? 'Показано ' + s + '–' + e + ' из ' + total + ' записей' : 'Нет записей';
  }

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
  var cols  = ['id','iin','surname','name','patronymic','group_id','action','action_time','device_ip','browser_name','browser_version','os_name','device_type'];
  var heads = ['ID','ИИН','Фамилия','Имя','Отчество','Группа','Действие','Время','IP','Браузер','Версия браузера','ОС','Тип устройства'];
  var rows  = filtered.map(function (r) {
    return cols.map(function (c) {
      var v = c === 'group_id' ? getGroupName(r[c])
            : c === 'action'   ? (r[c] === 'entry' ? 'Вход' : 'Выход')
            : (r[c] !== undefined ? r[c] : '');
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
    alert('Библиотека XLSX не загружена.');
    return;
  }
  var cols  = ['id','iin','surname','name','patronymic','group_id','action','action_time','device_ip','browser_name','browser_version','os_name','device_type'];
  var heads = ['ID','ИИН','Фамилия','Имя','Отчество','Группа','Действие','Время','IP-адрес','Браузер','Версия браузера','ОС','Тип устройства'];
  var wsData = [heads];
  filtered.forEach(function (r) {
    wsData.push(cols.map(function (c) {
      if (c === 'group_id') return getGroupName(r[c]);
      if (c === 'action')   return r[c] === 'entry' ? 'Вход' : 'Выход';
      return r[c] !== undefined ? r[c] : '';
    }));
  });
  var wb = XLSX.utils.book_new();
  var ws = XLSX.utils.aoa_to_sheet(wsData);
  ws['!cols'] = [{wch:6},{wch:14},{wch:18},{wch:14},{wch:18},{wch:12},{wch:10},{wch:20},{wch:16},{wch:16},{wch:14},{wch:18},{wch:12}];
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

/* ═══════════════════════════════════════════════════
   QR-ФУНКЦИОНАЛ — брендированный QR внутри старого модального окна
   ═══════════════════════════════════════════════════ */

/* Текущий тип QR: 'entry' или 'exit' */
var _qrCurrentType = null;
var _qrLogoSrc = 'assets/svgtk-logo.png';

function getQRConfig(type) {
  var isEntry = type === 'entry';
  return {
    type: isEntry ? 'entry' : 'exit',
    label: isEntry ? 'ВХОД' : 'ВЫХОД',
    title: isEntry ? 'QR-код — Вход' : 'QR-код — Выход',
    subtitle: isEntry ? 'Студент сканирует для отметки входа' : 'Студент сканирует для отметки выхода',
    cardTitle: isEntry ? 'Отметка входа' : 'Отметка выхода',
    cardSubtitle: isEntry ? 'Сканируйте код для регистрации прихода' : 'Сканируйте код для регистрации ухода',
    filename: isEntry ? 'svgtk-qr-entry.png' : 'svgtk-qr-exit.png',
    url: isEntry ? 'https://portal-svgtk.ru/qr/entry.html' : 'https://portal-svgtk.ru/qr/exit.html',
    // Вход и выход выполнены в одном синем стиле
    color: '#1a56db',
    color2: '#2563eb'
  };
}

/**
 * Открыть старое модальное окно, но сформировать внутри него новый дизайн QR.
 * @param {'entry'|'exit'} type
 */
function showQRModal(type) {
  var cfg = getQRConfig(type);
  _qrCurrentType = cfg.type;

  var titleEl = document.getElementById('qrModalTitle');
  var subEl = document.getElementById('qrModalSub');
  var urlEl = document.getElementById('qrModalUrl');
  var headEl = document.getElementById('qrModalHead');
  var container = document.getElementById('qrContainer');
  var dlBtn = document.getElementById('qrModalDownload');

  if (titleEl) titleEl.textContent = cfg.title;
  if (subEl) subEl.textContent = cfg.subtitle;
  if (urlEl) urlEl.textContent = cfg.url;

  if (headEl) {
    headEl.style.background = 'linear-gradient(135deg,' + cfg.color + ',' + cfg.color2 + ')';
  }

  if (dlBtn) {
    dlBtn.href = '#';
    dlBtn.download = cfg.filename;
    dlBtn.style.background = cfg.color;
    dlBtn.style.borderColor = cfg.color;
  }

  if (!container) return;
  container.innerHTML = '<div style="width:250px; min-height:292px; display:flex; align-items:center; justify-content:center; text-align:center; color:var(--color-text-muted); font-size:13px; line-height:1.4;">Формируем QR-код…</div>';

  /* Открыть старый модал. Внешнее окно и его оформление остаются прежними. */
  var bg = document.getElementById('qrModalBg');
  if (bg) {
    bg.style.display        = 'flex';
    bg.style.position       = 'fixed';
    bg.style.inset          = '0';
    bg.style.background     = 'rgba(0,0,0,.6)';
    bg.style.zIndex         = '99999';
    bg.style.alignItems     = 'center';
    bg.style.justifyContent = 'center';
    bg.style.padding        = '16px';
    bg.classList.add('open');
  }

  if (typeof QRCode === 'undefined') {
    container.innerHTML = '<p style="color:#dc2626;font-size:13px;text-align:center;padding:8px">'
      + 'QRCode.js не загружен.<br>Убедись, что CDN-скрипт идёт ДО journal.js</p>';
    return;
  }

  renderBrandedQR(cfg, function (canvas) {
    if (_qrCurrentType !== cfg.type) return;
    canvas.style.width = '250px';
    canvas.style.maxWidth = '100%';
    canvas.style.height = 'auto';
    canvas.style.display = 'block';
    canvas.style.borderRadius = '18px';

    container.innerHTML = '';
    container.appendChild(canvas);

    if (dlBtn) {
      dlBtn.href = canvas.toDataURL('image/png');
      dlBtn.download = cfg.filename;
    }
  });
}

function renderBrandedQR(cfg, done) {
  var tmp = document.createElement('div');
  tmp.style.position = 'fixed';
  tmp.style.left = '-10000px';
  tmp.style.top = '-10000px';
  tmp.style.width = '420px';
  tmp.style.height = '420px';
  tmp.style.overflow = 'hidden';
  document.body.appendChild(tmp);

  new QRCode(tmp, {
    text: cfg.url,
    width: 420,
    height: 420,
    colorDark: cfg.color,
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
  });

  setTimeout(function () {
    var qrCanvas = tmp.querySelector('canvas');
    var qrImg = tmp.querySelector('img');

    loadQRLogo(function (logo) {
      var canvas = drawQRCard(qrCanvas || qrImg, logo, cfg);
      if (tmp.parentNode) tmp.parentNode.removeChild(tmp);
      done(canvas);
    });
  }, 100);
}

function loadQRLogo(callback) {
  var img = new Image();
  img.onload = function () { callback(img); };
  img.onerror = function () { callback(null); };
  img.src = _qrLogoSrc;
}

function drawQRCard(qrSource, logo, cfg) {
  var canvas = document.createElement('canvas');
  var w = 600;
  var h = 740;
  canvas.width = w;
  canvas.height = h;

  var ctx = canvas.getContext('2d');
  ctx.clearRect(0, 0, w, h);

  /* Основная карточка без лишних плашек, точек и синей декоративной рамки */
  ctx.save();
  ctx.shadowColor = 'rgba(15, 23, 42, .14)';
  ctx.shadowBlur = 24;
  ctx.shadowOffsetY = 12;
  fillRoundRect(ctx, 22, 22, w - 44, h - 44, 28, '#eef3f9');
  ctx.restore();

  var bgGrad = ctx.createLinearGradient(22, 22, w - 22, h - 22);
  bgGrad.addColorStop(0, '#f8fafc');
  bgGrad.addColorStop(.58, '#eef3f9');
  bgGrad.addColorStop(1, '#e4ebf5');
  fillRoundRect(ctx, 22, 22, w - 44, h - 44, 28, bgGrad);

  /* Логотип */
  ctx.save();
  ctx.shadowColor = 'rgba(15, 23, 42, .10)';
  ctx.shadowBlur = 13;
  ctx.shadowOffsetY = 6;
  fillRoundRect(ctx, 58, 50, 98, 98, 18, '#ffffff');
  ctx.restore();
  strokeRoundRect(ctx, 58, 50, 98, 98, 18, 'rgba(203,213,225,.85)', 1.3);

  if (logo) {
    drawImageContain(ctx, logo, 70, 60, 74, 78);
  } else {
    ctx.fillStyle = cfg.color;
    ctx.font = '700 23px Montserrat, Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('СВГТК', 107, 109);
  }

  /* Заголовок без подписи QR-посещаемости и без плашки ВХОД/ВЫХОД */
  ctx.textAlign = 'left';
  ctx.fillStyle = '#0f172a';
  ctx.font = '700 29px Montserrat, Inter, sans-serif';
  ctx.fillText('СВГТК', 178, 103);

  /* Панель QR */
  ctx.save();
  ctx.shadowColor = 'rgba(15, 23, 42, .13)';
  ctx.shadowBlur = 22;
  ctx.shadowOffsetY = 10;
  fillRoundRect(ctx, 64, 168, 472, 472, 24, '#ffffff');
  ctx.restore();
  strokeRoundRect(ctx, 64, 168, 472, 472, 24, 'rgba(203,213,225,.9)', 1.2);

  if (qrSource) {
    ctx.drawImage(qrSource, 94, 198, 412, 412);
  } else {
    ctx.fillStyle = '#dc2626';
    ctx.font = '600 19px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('QR не сформирован', w / 2, 414);
  }

  /* Нижняя подпись — без длинной URL-плашки, чтобы карточка не выглядела обрезанной */
  ctx.textAlign = 'center';
  ctx.fillStyle = '#0f172a';
  ctx.font = '700 23px Montserrat, Inter, sans-serif';
  ctx.fillText(cfg.cardTitle, w / 2, 665);

  ctx.fillStyle = '#64748b';
  ctx.font = '500 14px Inter, sans-serif';
  ctx.fillText(cfg.cardSubtitle, w / 2, 688);

  return canvas;
}

function fillRoundRect(ctx, x, y, w, h, r, fillStyle) {
  roundedPath(ctx, x, y, w, h, r);
  ctx.fillStyle = fillStyle;
  ctx.fill();
}

function strokeRoundRect(ctx, x, y, w, h, r, strokeStyle, lineWidth) {
  roundedPath(ctx, x, y, w, h, r);
  ctx.strokeStyle = strokeStyle;
  ctx.lineWidth = lineWidth || 1;
  ctx.stroke();
}

function roundedPath(ctx, x, y, w, h, r) {
  r = Math.min(r, w / 2, h / 2);
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

function drawImageContain(ctx, img, x, y, w, h) {
  var iw = img.naturalWidth || img.width;
  var ih = img.naturalHeight || img.height;
  var scale = Math.min(w / iw, h / ih);
  var dw = iw * scale;
  var dh = ih * scale;
  ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
}

function hexToRgba(hex, alpha) {
  var c = hex.replace('#', '');
  var bigint = parseInt(c, 16);
  var r = (bigint >> 16) & 255;
  var g = (bigint >> 8) & 255;
  var b = bigint & 255;
  return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
}

function closeQRModal() {
  var bg = document.getElementById('qrModalBg');
  if (bg) {
    bg.style.display = 'none';
    bg.classList.remove('open');
  }
  _qrCurrentType = null;
  var container = document.getElementById('qrContainer');
  if (container) container.innerHTML = '';
  var dlBtn = document.getElementById('qrModalDownload');
  if (dlBtn) dlBtn.href = '#';
}

/* Закрытие по клику на тёмный фон и по Escape */
(function () {
  function attachQREvents() {
    var bg = document.getElementById('qrModalBg');
    if (bg) {
      bg.addEventListener('click', function (e) {
        if (e.target === bg) closeQRModal();
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachQREvents);
  } else {
    attachQREvents();
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeQRModal();
  });
})();

/* ══════════════════════════════════════════════════
   Динамические стили: тёмная тема для QR-модала
   ══════════════════════════════════════════════════ */
(function injectQRStyles() {
  var style = document.createElement('style');
  style.textContent = [
    '#qrModalBg > div {',
    '  border-color: transparent !important;',
    '}',
    '#qrModalBg > div > div:nth-child(2) > div {',
    '  border-color: transparent !important;',
    '}',
    '[data-theme="dark"] #qrModalBg > div {',
    '  background: var(--color-surface) !important;',
    '}',
    '[data-theme="dark"] #qrModalBg > div > div:nth-child(2) {',
    '  background: var(--color-surface) !important;',
    '}',
    '[data-theme="dark"] #qrModalBg > div > div:nth-child(3) {',
    '  background: var(--color-surface) !important;',
    '}',
    '.page-actions .btn { transition: opacity .18s, transform .18s, box-shadow .18s; }',
    '.page-actions .btn:hover { opacity: .87; box-shadow: 0 4px 12px rgba(30,41,59,.18); transform: translateY(-1px); }',
    '.page-actions .btn:active { transform: translateY(0); opacity: 1; }'
  ].join('\n');
  document.head.appendChild(style);
})();
