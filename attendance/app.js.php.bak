<?php header("Content-Type: application/javascript"); ?>
/**
 * app.js.php — JavaScript модуля «Учёт посещаемости»
 */

// ── Тема: применяется сразу (дублирует <head>-скрипт для надёжности) ─────
(function(){
  var t = localStorage.getItem('theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
})();

// ══════════════════════════════════════════════════════════════════
// Весь код запускается после полной загрузки DOM
// ══════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {

<?php if (!empty($_GET['saved'])): ?>
  showToast('✓ Журнал сохранён');
<?php endif ?>

  // ── Sidebar ────────────────────────────────────────────────────
  const sidebar     = document.getElementById('sidebar');
  const mainWrapper = document.getElementById('mainWrapper');

  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    sidebar?.classList.toggle('collapsed');
    mainWrapper?.classList.toggle('sidebar-collapsed');
  });
  document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
    sidebar?.classList.toggle('mobile-open');
  });

  // ── Переключение темы ──────────────────────────────────────────
  document.getElementById('themeToggle')?.addEventListener('click', () => {
    const html = document.documentElement;
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
  });

}); // end DOMContentLoaded

// ══════════════════════════════════════════════════════════════════
// ВКЛАДКИ — все переключаются через URL (серверный рендер)
// ══════════════════════════════════════════════════════════════════
function switchTab(name) {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', name);
  // Сохраняем текущие параметры если их нет
  if (!url.searchParams.get('group')) url.searchParams.set('group', '<?= (int)$selectedGrp ?>');
  if (!url.searchParams.get('date'))  url.searchParams.set('date',  '<?= htmlspecialchars($selectedDate) ?>');
  if (name === 'report' && !url.searchParams.get('month')) {
    url.searchParams.set('month', '<?= htmlspecialchars($reportMonth) ?>');
  }
  window.location.href = url.toString();
}

// ══════════════════════════════════════════════════════════════════
// ЖУРНАЛ — изменение статуса
// ══════════════════════════════════════════════════════════════════
const statusLabels = { present:'Присутствует', absent:'Отсутствует', excused:'Уваж. причина', late:'Опоздал' };
const statusCls    = { present:'badge-present', absent:'badge-absent', excused:'badge-excused', late:'badge-late' };

function updateStatus(sel, id) {
  const val   = sel.value;
  const badge = document.querySelector(`.status-badge-${id}`);
  if (badge) {
    badge.className   = `badge ${statusCls[val]} status-badge-${id}`;
    badge.textContent = statusLabels[val];
  }
  const hoursInput = document.getElementById('hours-' + id);
  if (hoursInput) hoursInput.value = (val === 'absent' || val === 'excused') ? 2 : 0;
  markUnsaved();
}

function markAll(status) {
  document.querySelectorAll('#journalTable tbody tr').forEach(row => {
    const id  = row.dataset.id;
    const sel = document.getElementById('status-' + id);
    if (!sel) return;
    sel.value = status;
    updateStatus(sel, id);
  });
}

let unsaved = false;
function markUnsaved() {
  unsaved = true;
  const btn = document.getElementById('saveBtn');
  if (btn) {
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> ● Сохранить журнал`;
  }
}

function recalcStats() {
  const total = <?= (int)$total ?>;
  let present = 0, absent = 0, excused = 0, late = 0;
  document.querySelectorAll('#journalTable tbody tr').forEach(row => {
    const sel = document.getElementById('status-' + row.dataset.id);
    const val = sel ? sel.value : 'present';
    if      (val === 'present') present++;
    else if (val === 'absent')  absent++;
    else if (val === 'excused') excused++;
    else if (val === 'late')    late++;
  });
  const pct    = total > 0 ? Math.round(present / total * 100) : 0;
  const absPct = total > 0 ? Math.round(absent  / total * 100) : 0;
  const g = id => document.getElementById(id);
  if (g('kpi-present'))     g('kpi-present').textContent     = present;
  if (g('kpi-present-bar')) g('kpi-present-bar').style.width = pct + '%';
  if (g('kpi-present-pct')) g('kpi-present-pct').textContent = pct + '% от списка';
  if (g('kpi-absent'))      g('kpi-absent').textContent      = absent;
  if (g('kpi-absent-bar'))  g('kpi-absent-bar').style.width  = absPct + '%';
  if (g('kpi-absent-pct'))  g('kpi-absent-pct').textContent  = absPct + '% без причины';
  if (g('kpi-excused'))     g('kpi-excused').textContent     = excused;
  if (g('kpi-late'))        g('kpi-late').textContent        = late;
}

// ── Сохранение журнала ─────────────────────────────────────────
function saveAttendance() {
  const rows = [];
  document.querySelectorAll('#journalTable tbody tr').forEach(row => {
    const id = row.dataset.id;
    rows.push({
      student_id:   id,
      status:       document.getElementById('status-' + id)?.value || 'present',
      hours_missed: parseInt(document.getElementById('hours-' + id)?.value || 0),
      reason_id:    document.getElementById('reason-' + id)?.value || ''
    });
  });

  if (rows.length === 0) { showToast('Нет студентов для сохранения', 'error'); return; }

  const currentUrl   = new URL(window.location.href);
  const currentDate  = currentUrl.searchParams.get('date')  || '<?= htmlspecialchars($selectedDate) ?>';
  const currentGroup = currentUrl.searchParams.get('group') || '<?= (int)$selectedGrp ?>';

  const btn = document.getElementById('saveBtn');
  if (btn) btn.innerHTML = '⏳ Сохранение...';

  fetch('save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ date: currentDate, group_id: currentGroup, rows })
  })
  .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
  .then(d => {
    if (d.success) {
      unsaved = false;
      const url = new URL(window.location.href);
      url.searchParams.set('tab',   'journal');
      url.searchParams.set('group', currentGroup);
      url.searchParams.set('date',  currentDate);
      url.searchParams.set('saved', '1');
      window.location.replace(url.toString());
    } else {
      showToast('Ошибка: ' + (d.error || 'неизвестная'), 'error');
      if (btn) btn.innerHTML = '💾 Сохранить журнал';
    }
  })
  .catch(err => {
    showToast('Ошибка соединения: ' + err.message, 'error');
    if (btn) btn.innerHTML = '💾 Сохранить журнал';
  });
}

// ── Экспорт CSV (журнал) ───────────────────────────────────────
function exportCSV() {
  const statusLabelsRu = { present:'Присутствует', absent:'Отсутствует', excused:'Уваж. причина', late:'Опоздал' };
  const rows = [['#', 'Фамилия', 'Имя', 'Отчество', 'Группа', 'ИИН', 'Статус', 'Часов пропущено']];
  document.querySelectorAll('#journalTable tbody tr').forEach((row, i) => {
    const cells  = row.querySelectorAll('td');
    const status = document.getElementById('status-' + row.dataset.id)?.value || 'present';
    const hours  = document.getElementById('hours-'  + row.dataset.id)?.value || '0';
    const surname    = cells[1]?.textContent.trim() || '';
    const firstName  = cells[2]?.textContent.trim() || '';
    const patronymic = cells[3]?.textContent.trim() || '';
    const group      = cells[4]?.textContent.trim() || '';
    const iin        = cells[5]?.textContent.trim() || '';
    const statusRu   = statusLabelsRu[status] || status;
    const esc = v => '"' + String(v).replace(/"/g, '""') + '"';
    rows.push([i + 1, esc(surname), esc(firstName), esc(patronymic), esc(group), esc(iin), esc(statusRu), hours]);
  });
  const csv  = rows.map(r => r.join(';')).join('
');
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = `attendance_<?= htmlspecialchars($groupInfo['name']) ?>_<?= htmlspecialchars($selectedDate) ?>.csv`;
  a.click();
}

// ── Экспорт рапортички в CSV ───────────────────────────────────
function exportRapCSV() {
  const table = document.getElementById('rapTable');
  if (!table) { showToast('Рапортичка не найдена', 'error'); return; }

  const esc = v => '"' + String(v).replace(/"/g, '""') + '"';
  const rows = [];

  table.querySelectorAll('tr').forEach(tr => {
    const cells = [];
    tr.querySelectorAll('th, td').forEach(td => {
      const clone = td.cloneNode(true);
      // Достаём часы из sup и убираем тег
      const sup = clone.querySelector('sup');
      const hours = sup ? sup.textContent.trim() : '';
      clone.querySelectorAll('sup').forEach(s => s.remove());
      let txt = clone.textContent.trim().replace(/\s+/g, ' ');
      // Часы склеиваем с символом: н2 → н(2ч)
      if (hours) txt = txt + '(' + hours + 'ч)';
      // Присутствие · → пусто, выходной — → вых
      if (txt === '·') txt = '';
      if (txt === '—') txt = 'вых';
      cells.push(esc(txt));
    });
    if (cells.length) rows.push(cells.join(';'));
  });

  const csv  = rows.join('\r\n');
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = `rapportichka_<?= htmlspecialchars($groupInfo['name']) ?>_<?= htmlspecialchars($reportMonth) ?>_<?= htmlspecialchars($reportPeriod) ?>.csv`;
  a.click();
  showToast('✓ CSV скачан');
}

// ── Печать сводной ведомости ───────────────────────────────────
function printSummary() {
  const table = document.getElementById('rapTable');
  if (!table) { showToast('Рапортичка не найдена', 'error'); return; }
  const win = window.open('', '_blank', 'width=900,height=700');
  win.document.write(`<!DOCTYPE html><html lang="ru"><head>
    <meta charset="UTF-8">
    <title>Рапортичка — <?= htmlspecialchars($groupInfo['name']) ?></title>
    <style>
      body{font-family:Arial,sans-serif;font-size:11px;margin:16px}
      table{border-collapse:collapse;width:100%}
      th,td{border:1px solid #999;padding:3px 5px;text-align:center}
      th{background:#f0f0f0;font-weight:600}
      .col-name{text-align:left}
      @media print{body{margin:8px}}
    </style>
  </head><body>
    <h3 style="margin:0 0 8px">Рапортичка — <?= htmlspecialchars($groupInfo['name']) ?></h3>
    ${table.outerHTML}
  </body></html>`);
  win.document.close();
  win.focus();
  win.onload = () => win.print();
}

// ══════════════════════════════════════════════════════════════════
// СПРАВКИ
// ══════════════════════════════════════════════════════════════════
let selectedDocFile = null;

function handleFileSelect(input) {
  if (input.files && input.files[0]) setDocFile(input.files[0]);
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('uploadZone')?.classList.remove('drag-over');
  if (e.dataTransfer.files[0]) setDocFile(e.dataTransfer.files[0]);
}
function setDocFile(file) {
  const allowed = ['application/pdf','image/jpeg','image/png'];
  if (!allowed.includes(file.type) && !file.name.match(/\.(pdf|jpg|jpeg|png)$/i)) {
    showToast('Разрешены только PDF, JPG, PNG', 'error'); return;
  }
  if (file.size > 5 * 1024 * 1024) { showToast('Файл превышает 5 МБ', 'error'); return; }
  selectedDocFile = file;
  const uz = document.getElementById('uploadZone');
  const pv = document.getElementById('filePreview');
  if (uz) uz.style.display = 'none';
  if (pv) {
    pv.style.display = 'flex';
    document.getElementById('filePreviewName').textContent = file.name;
    document.getElementById('filePreviewSize').textContent = (file.size/1024).toFixed(1) + ' КБ';
  }
}
function clearFile() {
  selectedDocFile = null;
  const fi = document.getElementById('docFile');    if (fi) fi.value = '';
  const uz = document.getElementById('uploadZone'); if (uz) uz.style.display = '';
  const fp = document.getElementById('filePreview');if (fp) fp.style.display = 'none';
}

async function submitDocument() {
  const student  = document.getElementById('docStudent')?.value;
  const reason   = document.getElementById('docReason')?.value;
  const dateFrom = document.getElementById('docDateFrom')?.value;
  const dateTo   = document.getElementById('docDateTo')?.value;
  const note     = document.getElementById('docNote')?.value || '';

  if (!student)              { showToast('Выберите студента', 'error'); return; }
  if (!dateFrom)             { showToast('Укажите дату начала', 'error'); return; }
  if (!dateTo)               { showToast('Укажите дату окончания', 'error'); return; }
  if (dateFrom > dateTo)     { showToast('Дата «с» не может быть позже даты «по»', 'error'); return; }
  if (!selectedDocFile)      { showToast('Выберите файл справки', 'error'); return; }

  const btn = document.getElementById('docSubmitBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Загрузка...'; }

  const fd = new FormData();
  fd.append('action',     'upload');
  fd.append('student_id', student);
  fd.append('group_id',   '<?= (int)$selectedGrp ?>');
  fd.append('reason_id',  reason);
  fd.append('date_from',  dateFrom);
  fd.append('date_to',    dateTo);
  fd.append('note',       note);
  fd.append('file',       selectedDocFile);

  try {
    const res  = await fetch('doc_save.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast('✓ Справка сохранена');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(data.error || 'Ошибка сохранения', 'error');
      if (btn) { btn.disabled = false; btn.innerHTML = 'Сохранить справку'; }
    }
  } catch(e) {
    showToast('Ошибка сети: ' + e.message, 'error');
    if (btn) { btn.disabled = false; btn.innerHTML = 'Сохранить справку'; }
  }
}

let _docModalAction = null, _docModalId = null;

function docAction(action, id) {
  _docModalAction = action; _docModalId = id;
  const modal = document.getElementById('docModal');
  const title = document.getElementById('docModalTitle');
  const desc  = document.getElementById('docModalDesc');
  const btn   = document.getElementById('docModalConfirmBtn');
  const noteF = document.getElementById('docModalNote');
  if (noteF) { noteF.value = ''; noteF.closest('.form-group').style.display = ''; }

  if (action === 'verify') {
    if (title) title.textContent = 'Принять справку';
    if (desc)  desc.textContent  = 'Справка будет принята. Журнал посещаемости за период обновится автоматически.';
    if (btn) { btn.textContent = 'Принять'; btn.className = 'btn btn-success'; btn.style.color = ''; btn.style.borderColor = ''; }
  } else if (action === 'reject') {
    if (title) title.textContent = 'Отклонить справку';
    if (desc)  desc.textContent  = 'Справка будет отклонена. Укажите причину (необязательно).';
    if (btn) { btn.textContent = 'Отклонить'; btn.className = 'btn btn-outline'; btn.style.color = 'var(--color-error)'; btn.style.borderColor = 'var(--color-error)'; }
  } else if (action === 'delete') {
    if (title) title.textContent = 'Удалить справку';
    if (desc)  desc.textContent  = 'Файл будет удалён безвозвратно. Записи в журнале посещаемости останутся.';
    if (btn) { btn.textContent = 'Удалить'; btn.className = 'btn btn-outline'; btn.style.color = 'var(--color-error)'; btn.style.borderColor = 'var(--color-error)'; }
    if (noteF) noteF.closest('.form-group').style.display = 'none';
  }
  if (modal) modal.style.display = 'flex';
}
function closeDocModal() {
  const modal = document.getElementById('docModal');
  if (modal) modal.style.display = 'none';
  _docModalAction = null; _docModalId = null;
}
async function confirmDocAction() {
  if (!_docModalAction || !_docModalId) return;
  const note = document.getElementById('docModalNote')?.value || '';
  const btn  = document.getElementById('docModalConfirmBtn');
  if (btn) { btn.disabled = true; btn.textContent = '⏳'; }
  try {
    const res  = await fetch('doc_save.php?action=' + _docModalAction, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: _docModalId, note })
    });
    const data = await res.json();
    if (data.success) {
      showToast('✓ Готово');
      closeDocModal();
      setTimeout(() => location.reload(), 600);
    } else {
      showToast(data.error || 'Ошибка', 'error');
      if (btn) { btn.disabled = false; btn.textContent = 'Подтвердить'; }
    }
  } catch(e) {
    showToast('Ошибка сети', 'error');
    if (btn) { btn.disabled = false; }
  }
}

// ── Устаревший алиас ───────────────────────────────────────────
function handleUpload(input) { if (input.files[0]) setDocFile(input.files[0]); }

// ══════════════════════════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════════════════════════
function showToast(msg, type = 'success') {
  let toast = document.getElementById('toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toast';
    toast.style.cssText = [
      'position:fixed','bottom:24px','right:24px','z-index:9999',
      'background:var(--color-text)','color:var(--color-text-inverse)',
      'padding:12px 20px','border-radius:10px','font-size:.875rem','font-weight:500',
      'box-shadow:0 8px 24px rgba(0,0,0,.2)',
      'transform:translateY(80px)','opacity:0',
      'transition:transform .3s cubic-bezier(.16,1,.3,1),opacity .3s',
      'max-width:320px'
    ].join(';');
    document.body.appendChild(toast);
  }
  if (type === 'error') {
    toast.style.background = 'var(--color-error)';
    toast.style.color = '#fff';
  } else {
    toast.style.background = 'var(--color-text)';
    toast.style.color = 'var(--color-text-inverse)';
  }
  toast.textContent = msg;
  toast.style.transform = 'translateY(0)';
  toast.style.opacity   = '1';
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => {
    toast.style.transform = 'translateY(80px)';
    toast.style.opacity   = '0';
  }, 3000);
}
