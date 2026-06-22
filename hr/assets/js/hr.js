// ── Данные для поиска и просмотра документов ─────────────────
let allNewStudents = [];
let docsByEmployment = {};
let hrChartData = {};
let hrChartVisible = {};
let selectedStudentId = null;
let hrPageAbortController = null;
let hrNavigationController = null;

const STATUS_DOCUMENT_LABELS = {
  employed: {
    single: 'Справка о трудоустройстве',
    plural: 'Справки о трудоустройстве',
    hint: 'Справка о трудоустройстве',
  },
  studying: {
    single: 'Справка о продолжении обучения',
    plural: 'Справки о продолжении обучения',
    hint: 'Справка из учебного заведения',
  },
  decree: {
    single: 'Справка о декрете',
    plural: 'Справки о декрете',
    hint: 'Справка о декрете',
  },
  military: {
    single: 'Справка о прохождении военной службы',
    plural: 'Справки о прохождении военной службы',
    hint: 'Справка о прохождении военной службы',
  },
};

const GENERIC_DOCUMENT_LABELS = {
  single: 'Документ',
  plural: 'Документы',
  hint: 'Документ',
};

const EMPLOYMENT_TEXT_RE = /^[\p{L}\p{N}\s.,\-–—'"«»„“”№\/()]+$/u;
const NOTES_TEXT_RE = /^[\p{L}\p{N}\s.,\-–—'"«»„“”№\/()!?;:]+$/u;

function parseJsonScript(id, fallback) {
  const node = document.getElementById(id);
  if (!node) return fallback;

  try {
    return JSON.parse(node.textContent || '');
  } catch (err) {
    return fallback;
  }
}

function loadHrEmbeddedData() {
  allNewStudents = parseJsonScript('hrNewStudentsData', []);
  docsByEmployment = parseJsonScript('hrDocsData', {});
  hrChartData = parseJsonScript('hrChartData', {});
  hrChartVisible = {};
}

function documentLabelsForStatus(status) {
  return STATUS_DOCUMENT_LABELS[status] || null;
}

function statusRequiresDocument(status) {
  return Object.prototype.hasOwnProperty.call(STATUS_DOCUMENT_LABELS, status);
}

function documentLabelsForTitle(status) {
  return documentLabelsForStatus(status) || GENERIC_DOCUMENT_LABELS;
}

function updateDocumentLabels(status, fallbackToGeneric = false) {
  const labels = fallbackToGeneric
    ? documentLabelsForTitle(status || document.getElementById('fStatus')?.value || 'unknown')
    : documentLabelsForStatus(status || document.getElementById('fStatus')?.value || 'unknown');

  if (!labels) return null;

  const docsSectionTitle = document.getElementById('docsSectionTitle');
  const uploadPrimaryText = document.getElementById('uploadPrimaryText');
  const uploadHintText = document.getElementById('uploadHintText');
  const docsModalTitle = document.getElementById('docsModalTitle');

  if (docsSectionTitle) docsSectionTitle.textContent = labels.single;
  if (uploadPrimaryText) uploadPrimaryText.textContent = 'Нажмите или перетащите файл: ' + labels.single.toLowerCase();
  if (uploadHintText) uploadHintText.textContent = labels.hint + '. PDF, JPG, PNG, DOC — до 10 МБ. Файл отправится после нажатия «Сохранить».';
  if (docsModalTitle) docsModalTitle.textContent = labels.plural;

  return labels;
}

// ── Утилиты ────────────────────────────────────────────────
function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function showToast(msg, type = 'success') {
  const c = document.getElementById('toastContainer');
  if (!c || !msg) return;

  const t = document.createElement('div');
  t.className = `toast ${type === 'error' ? 'error' : 'success'}`;
  t.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${type === 'success' ? '<polyline points="20 6 9 17 4 12"/>' : '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'}</svg>${escapeHtml(msg)}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

function openModal(id) {
  document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
}

function closeAllModals() {
  document.querySelectorAll('.modal-overlay.open').forEach(modal => modal.classList.remove('open'));
}

function fmtBytes(b) {
  b = Number(b) || 0;
  if (b < 1024) return b + ' Б';
  if (b < 1048576) return (b / 1024).toFixed(1) + ' КБ';
  return (b / 1048576).toFixed(1) + ' МБ';
}

function normalizeFormValue(value) {
  return String(value || '').replace(/\s+/gu, ' ').trim();
}

function fieldValue(id) {
  return normalizeFormValue(document.getElementById(id)?.value || '');
}

function failFormValidation(e, message, fieldId) {
  e.preventDefault();
  showToast(message, 'error');
  const field = document.getElementById(fieldId);
  field?.focus();
  field?.classList.add('is-invalid');
  setTimeout(() => field?.classList.remove('is-invalid'), 1800);
}

function hasForbiddenChars(value, isNotes = false) {
  if (!value) return false;
  return !(isNotes ? NOTES_TEXT_RE : EMPLOYMENT_TEXT_RE).test(value);
}

function currentDocsForEmployment(empId) {
  if (!empId) return [];
  return docsByEmployment[String(empId)] || docsByEmployment[Number(empId)] || [];
}

function clearSelectedFiles() {
  const fileInputNode = document.getElementById('fileInput');
  const selectedFilesInfoNode = document.getElementById('selectedFilesInfo');
  if (fileInputNode) fileInputNode.value = '';
  if (selectedFilesInfoNode) selectedFilesInfoNode.textContent = '';
}

function setUploadAreaVisible(isVisible) {
  const uploadAreaNode = document.getElementById('uploadArea');
  if (uploadAreaNode) uploadAreaNode.style.display = isVisible ? '' : 'none';
  if (!isVisible) clearSelectedFiles();
}

function refreshDocumentSection() {
  const status = document.getElementById('fStatus')?.value || 'unknown';
  const recordId = document.getElementById('fRecordId')?.value || '';
  const docsSection = document.getElementById('docsSection');
  const docsList = document.getElementById('docsList');
  if (!docsSection || !docsList) return;

  const docs = currentDocsForEmployment(recordId);

  if (!statusRequiresDocument(status)) {
    if (recordId && docs.length > 0) {
      docsSection.style.display = '';
      updateDocumentLabels(status, true);
      setUploadAreaVisible(false);
      renderDocs(recordId, 'docsList', true);
    } else {
      docsSection.style.display = 'none';
      docsList.innerHTML = '';
      setUploadAreaVisible(false);
    }
    return;
  }

  const labels = updateDocumentLabels(status);
  if (!labels) return;

  docsSection.style.display = '';
  setUploadAreaVisible(true);

  if (recordId) {
    renderDocs(recordId, 'docsList', true);
    const uploadAreaNode = document.getElementById('uploadArea');
    if (uploadAreaNode) uploadAreaNode.dataset.empId = recordId;
  } else {
    docsList.innerHTML = `<p style="font-size:var(--text-xs);color:var(--color-text-faint)">Можно прикрепить файл: ${escapeHtml(labels.single.toLowerCase())}. Файл загрузится после сохранения записи.</p>`;
  }
}

function docItemHtml(doc, canDelete) {
  const docId = Number(doc.id) || 0;
  const deleteButton = canDelete
    ? `<button type="submit" name="delete_doc_id" value="${docId}" formnovalidate class="btn btn-ghost btn-sm" style="color:var(--color-error)" title="Удалить">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
      </button>`
    : '';

  return `<div class="doc-item" id="doc-${docId}">
    <div class="doc-icon">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    </div>
    <div class="doc-info">
      <div class="doc-name">${escapeHtml(doc.original_name)}</div>
      <div class="doc-meta">${fmtBytes(doc.file_size)} · ${escapeHtml(doc.uploaded_at || '')}</div>
    </div>
    <a href="download.php?id=${docId}" class="btn btn-ghost btn-sm" title="Скачать" data-no-ajax="1">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    </a>
    ${deleteButton}
  </div>`;
}

// ── AJAX-навигация без перезагрузки страницы ──────────────
function normalizeHrPath(pathname) {
  return pathname.replace(/\/index\.php$/i, '/').replace(/\/+$/g, '/');
}

function canHandleAjaxLink(link) {
  if (!link || link.closest('[data-no-ajax]')) return false;
  if (link.target && link.target !== '_self') return false;

  const rawHref = link.getAttribute('href') || '';
  if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:')) return false;

  const url = new URL(rawHref, window.location.href);
  if (url.origin !== window.location.origin) return false;
  if (normalizeHrPath(url.pathname) !== normalizeHrPath(window.location.pathname)) return false;

  return Boolean(link.closest('#mainWrapper'));
}

function getBrowserUrl(url) {
  const u = new URL(url, window.location.href);
  return u.pathname + u.search + u.hash;
}

function replaceElementById(doc, id) {
  const current = document.getElementById(id);
  const next = doc.getElementById(id);
  if (current && next) current.replaceWith(next);
}

function applyAjaxHtml(html, targetUrl, pushHistory = true) {
  const parser = new DOMParser();
  const nextDoc = parser.parseFromString(html, 'text/html');
  const nextMain = nextDoc.getElementById('mainWrapper');

  if (!nextMain) {
    window.location.href = targetUrl;
    return;
  }

  replaceElementById(nextDoc, 'mainWrapper');
  replaceElementById(nextDoc, 'modalRecord');
  replaceElementById(nextDoc, 'modalDocs');
  replaceElementById(nextDoc, 'modalHelp');
  replaceElementById(nextDoc, 'toastContainer');
  replaceElementById(nextDoc, 'hrNewStudentsData');
  replaceElementById(nextDoc, 'hrDocsData');
  replaceElementById(nextDoc, 'hrChartData');

  if (pushHistory) {
    window.history.pushState({}, '', getBrowserUrl(targetUrl));
  }

  initHrPage();
  const flash = document.querySelector('.flash-message');
  if (flash) {
    showToast(flash.textContent.trim(), flash.classList.contains('error') ? 'error' : 'success');
    flash.remove();
  }
}

async function ajaxNavigate(url, pushHistory = true) {
  const targetUrl = new URL(url, window.location.href).toString();

  if (hrNavigationController) hrNavigationController.abort();
  hrNavigationController = new AbortController();

  const wrapper = document.getElementById('mainWrapper');
  wrapper?.classList.add('ajax-loading');

  try {
    const response = await fetch(targetUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
      signal: hrNavigationController.signal,
    });

    if (!response.ok) throw new Error('Ошибка загрузки страницы');
    const html = await response.text();
    applyAjaxHtml(html, response.url || targetUrl, pushHistory);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (err) {
    if (err.name === 'AbortError') return;
    showToast(err.message || 'Не удалось обновить страницу', 'error');
  } finally {
    document.getElementById('mainWrapper')?.classList.remove('ajax-loading');
  }
}

function buildFilterUrl(form) {
  const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
  const formData = new FormData(form);

  url.search = '';
  formData.delete('page');

  for (const [key, value] of formData.entries()) {
    const normalizedValue = String(value || '').trim();
    if (normalizedValue !== '') url.searchParams.append(key, normalizedValue);
  }

  return url.toString();
}

function validateRecordForm(e, submitter) {
  if (submitter?.name === 'delete_doc_id') {
    if (!confirm('Удалить документ?')) {
      e.preventDefault();
      return false;
    }
    return true;
  }

  const studentId = document.getElementById('fStudentId')?.value;
  if (!studentId) {
    failFormValidation(e, 'Выберите студента', 'fStudentSearch');
    return false;
  }

  const status = document.getElementById('fStatus')?.value || 'unknown';
  const isEmployed = status === 'employed';

  if (isEmployed) {
    const requiredFields = [
      ['fEmployerName', 'Заполните поле: Организация'],
      ['fPosition', 'Заполните поле: Должность'],
      ['fEmploymentDate', 'Заполните поле: Дата трудоустройства'],
      ['fEmploymentType', 'Заполните поле: Тип занятости'],
    ];

    for (const [fieldId, message] of requiredFields) {
      if (!fieldValue(fieldId)) {
        failFormValidation(e, message, fieldId);
        return false;
      }
    }
  }

  const employerName = fieldValue('fEmployerName');
  const position = fieldValue('fPosition');
  const notes = fieldValue('fNotes');

  if (isEmployed && hasForbiddenChars(employerName)) {
    failFormValidation(e, 'Поле «Организация» содержит запрещённые символы', 'fEmployerName');
    return false;
  }

  if (isEmployed && hasForbiddenChars(position)) {
    failFormValidation(e, 'Поле «Должность» содержит запрещённые символы', 'fPosition');
    return false;
  }

  if (hasForbiddenChars(notes, true)) {
    failFormValidation(e, 'Поле «Примечание» содержит запрещённые символы', 'fNotes');
    return false;
  }

  return true;
}

async function submitActionForm(form, submitter) {
  const formData = new FormData(form);
  if (submitter?.name && !formData.has(submitter.name)) {
    formData.append(submitter.name, submitter.value || '');
  }
  formData.append('ajax', '1');

  const btn = submitter || form.querySelector('[type="submit"]');
  const oldHtml = btn?.innerHTML;
  if (btn) {
    btn.disabled = true;
    if (btn.id === 'btnSaveRecord') btn.textContent = 'Сохранение...';
  }

  try {
    const response = await fetch(form.getAttribute('action') || 'actions.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });

    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      throw new Error('Сервер вернул некорректный ответ');
    }

    const result = await response.json();
    if (!result.success) {
      showToast(result.message || 'Операция не выполнена', 'error');
      return;
    }

    closeAllModals();
    await ajaxNavigate(window.location.href, false);
    showToast(result.message || 'Готово', 'success');
  } catch (err) {
    showToast(err.message || 'Ошибка выполнения действия', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      if (oldHtml !== undefined) btn.innerHTML = oldHtml;
    }
  }
}

// ── Поиск студента ─────────────────────────────────────────
function normalizeSearchText(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/ё/g, 'е')
    .replace(/[^\p{L}\p{N}\s-]+/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function studentMatchesSearch(student, query) {
  const terms = normalizeSearchText(query).split(' ').filter(Boolean);
  const haystack = normalizeSearchText([student.name, student.group].join(' '));
  return terms.every(term => haystack.includes(term));
}

function selectStudent(id, name) {
  selectedStudentId = id;
  const studentIdField = document.getElementById('fStudentId');
  const searchInput = document.getElementById('fStudentSearch');
  const searchResults = document.getElementById('searchResults');
  const selectedDisplay = document.getElementById('selectedStudentDisplay');

  if (studentIdField) studentIdField.value = id;
  if (searchInput) searchInput.value = name;
  if (selectedDisplay) selectedDisplay.textContent = name;
  searchResults?.classList.remove('open');
}

// ── Переключение полей трудоустройства ────────────────────
function toggleEmployedFields() {
  const statusField = document.getElementById('fStatus');
  const employedFields = document.getElementById('employedFields');
  if (!statusField || !employedFields) return;

  const isEmployed = statusField.value === 'employed';
  employedFields.style.display = isEmployed ? '' : 'none';

  ['fEmployerName', 'fPosition', 'fEmploymentDate', 'fEmploymentType'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.required = isEmployed;
    el.disabled = !isEmployed;
  });

  refreshDocumentSection();
}

// ── Открыть модал редактирования ─────────────────────────
function openEdit(data) {
  clearForm();
  document.getElementById('modalTitle').textContent = 'Редактировать запись';
  document.getElementById('fStudentId').value = data.studentId;
  document.getElementById('fRecordId').value = data.recordId || '';
  document.getElementById('fStatus').value = data.status || 'unknown';
  document.getElementById('fEmployerName').value = data.employerName || '';
  document.getElementById('fPosition').value = data.position || '';
  document.getElementById('fEmploymentDate').value = data.employmentDate ? String(data.employmentDate).substr(0, 10) : '';
  document.getElementById('fEmploymentType').value = data.employmentType || 'full_time';
  document.getElementById('fIsBySpec').checked = !!data.isBySpec;
  document.getElementById('fNotes').value = data.notes || '';

  document.getElementById('studentAddMode').style.display = 'none';
  document.getElementById('studentEditMode').style.display = '';
  document.getElementById('studentNameDisplay').textContent = data.studentName;
  toggleEmployedFields();

  openModal('modalRecord');
}

function clearForm() {
  ['fRecordId', 'fStudentId', 'fEmployerName', 'fPosition', 'fEmploymentDate', 'fNotes']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

  const statusField = document.getElementById('fStatus');
  const typeField = document.getElementById('fEmploymentType');
  const specField = document.getElementById('fIsBySpec');
  const searchInput = document.getElementById('fStudentSearch');
  const selectedDisplay = document.getElementById('selectedStudentDisplay');
  const docsList = document.getElementById('docsList');
  const uploadArea = document.getElementById('uploadArea');
  const fileInput = document.getElementById('fileInput');
  const selectedFilesInfo = document.getElementById('selectedFilesInfo');

  if (statusField) statusField.value = 'employed';
  if (typeField) typeField.value = 'full_time';
  if (specField) specField.checked = true;
  if (searchInput) searchInput.value = '';
  if (selectedDisplay) selectedDisplay.textContent = 'Не выбран';
  if (docsList) docsList.innerHTML = '';
  if (uploadArea) uploadArea.dataset.empId = '';
  if (fileInput) fileInput.value = '';
  if (selectedFilesInfo) selectedFilesInfo.textContent = '';
  toggleEmployedFields();
}

// ── Документы: список берётся из JSON, встроенного в страницу ──
function renderDocs(empId, containerId, canDelete) {
  const c = document.getElementById(containerId);
  if (!c) return;

  const docs = currentDocsForEmployment(empId);
  c.innerHTML = docs.length
    ? docs.map(d => docItemHtml(d, canDelete)).join('')
    : '<p style="font-size:var(--text-xs);color:var(--color-text-faint)">Документов нет</p>';
}

function openDocs(empId, status = 'unknown') {
  updateDocumentLabels(status, true);
  renderDocs(empId, 'docsViewList', false);
  openModal('modalDocs');
}

function updateSelectedFilesInfo() {
  const selectedFilesInfo = document.getElementById('selectedFilesInfo');
  const fileInput = document.getElementById('fileInput');
  if (!selectedFilesInfo || !fileInput) return;
  const files = Array.from(fileInput.files || []);
  selectedFilesInfo.textContent = files.length
    ? 'Выбрано файлов: ' + files.map(file => file.name).join(', ')
    : '';
}

function drawHrPieChart(ctx, chart, type, width, height, showPercent) {
  const values = (chart.values || []).map(v => Math.max(0, Number(v) || 0));
  const labels = chart.labels || [];
  const colors = chart.colors || [];
  const total = values.reduce((sum, value) => sum + value, 0);
  const cx = width / 2;
  const cy = height / 2;
  const radius = Math.min(width, height) * 0.36;
  let start = -Math.PI / 2;

  if (total <= 0) {
    ctx.fillStyle = '#94a3b8';
    ctx.font = '16px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('Нет данных для диаграммы', cx, cy);
    return;
  }

  const segments = [];
  values.forEach((value, index) => {
    const angle = (value / total) * Math.PI * 2;
    const end = start + angle;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, radius, start, end);
    ctx.closePath();
    ctx.fillStyle = colors[index] || '#1a56db';
    ctx.fill();
    segments.push({ start, end, value, color: colors[index] || '#1a56db' });
    start = end;
  });

  if (type === 'doughnut') {
    ctx.beginPath();
    ctx.arc(cx, cy, radius * 0.58, 0, Math.PI * 2);
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-surface').trim() || '#fff';
    ctx.fill();
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-text').trim() || '#1e293b';
    ctx.font = '700 26px Montserrat, Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(String(total), cx, cy + 8);
  }

  if (showPercent) {
    ctx.font = '700 12px Inter, sans-serif';
    ctx.textAlign = 'center';
    segments.forEach(segment => {
      if (segment.value <= 0) return;
      const mid = (segment.start + segment.end) / 2;
      const labelRadius = type === 'doughnut' ? radius * 0.8 : radius * 0.68;
      const x = cx + Math.cos(mid) * labelRadius;
      const y = cy + Math.sin(mid) * labelRadius;
      const percent = Math.round(segment.value / total * 1000) / 10;
      ctx.fillStyle = '#ffffff';
      ctx.strokeStyle = 'rgba(15,23,42,.35)';
      ctx.lineWidth = 3;
      ctx.strokeText(percent + '%', x, y + 4);
      ctx.fillText(percent + '%', x, y + 4);
    });
  }

  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-text-muted').trim() || '#64748b';
  ctx.font = '13px Inter, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(chart.title || '', cx, 24);
}

function drawHrBarChart(ctx, chart, width, height, showPercent) {
  const values = (chart.values || []).map(v => Math.max(0, Number(v) || 0));
  const labels = chart.labels || [];
  const colors = chart.colors || [];
  const total = values.reduce((sum, value) => sum + value, 0);
  const max = Math.max(1, ...values);
  const pad = { top: 42, right: 24, bottom: 72, left: 44 };
  const chartWidth = width - pad.left - pad.right;
  const chartHeight = height - pad.top - pad.bottom;
  const gap = 20;
  const barWidth = Math.max(28, (chartWidth - gap * Math.max(0, values.length - 1)) / Math.max(1, values.length));

  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-text-muted').trim() || '#64748b';
  ctx.font = '13px Inter, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(chart.title || '', width / 2, 24);

  ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-border').trim() || '#cbd5e1';
  ctx.beginPath();
  ctx.moveTo(pad.left, pad.top);
  ctx.lineTo(pad.left, pad.top + chartHeight);
  ctx.lineTo(width - pad.right, pad.top + chartHeight);
  ctx.stroke();

  values.forEach((value, index) => {
    const x = pad.left + index * (barWidth + gap) + 12;
    const h = (value / max) * chartHeight;
    const y = pad.top + chartHeight - h;
    ctx.fillStyle = colors[index] || '#1a56db';
    ctx.fillRect(x, y, barWidth - 24, h);
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-text').trim() || '#1e293b';
    ctx.font = '700 14px Inter, sans-serif';
    ctx.textAlign = 'center';
    const valueLabel = showPercent && total > 0
      ? `${value} · ${Math.round(value / total * 1000) / 10}%`
      : String(value);
    ctx.fillText(valueLabel, x + (barWidth - 24) / 2, y - 8);
    ctx.font = '12px Inter, sans-serif';
    wrapCanvasLabel(ctx, labels[index] || '', x + (barWidth - 24) / 2, pad.top + chartHeight + 20, Math.min(120, barWidth));
  });
}

function drawHrLineChart(ctx, chart, width, height, showPercent, graphType = 'line') {
  const values = (chart.values || []).map(v => Math.max(0, Number(v) || 0));
  const labels = chart.labels || [];
  const colors = chart.colors || [];
  const total = values.reduce((sum, value) => sum + value, 0);
  const max = Math.max(1, ...values);
  const pad = { top: 42, right: 28, bottom: 76, left: 48 };
  const chartWidth = width - pad.left - pad.right;
  const chartHeight = height - pad.top - pad.bottom;
  const step = values.length > 1 ? chartWidth / (values.length - 1) : 0;
  const points = values.map((value, index) => ({
    x: values.length > 1 ? pad.left + index * step : pad.left + chartWidth / 2,
    y: pad.top + chartHeight - (value / max) * chartHeight,
    value,
    label: labels[index] || '',
    color: colors[index] || '#1a56db',
  }));
  const safeGraphType = ['line', 'smooth', 'area', 'step'].includes(graphType) ? graphType : 'line';

  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-text-muted').trim() || '#64748b';
  ctx.font = '13px Inter, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(chart.title || '', width / 2, 24);

  ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-border').trim() || '#cbd5e1';
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(pad.left, pad.top);
  ctx.lineTo(pad.left, pad.top + chartHeight);
  ctx.lineTo(width - pad.right, pad.top + chartHeight);
  ctx.stroke();

  if (safeGraphType === 'area' && points.length > 0) {
    ctx.beginPath();
    ctx.moveTo(points[0].x, pad.top + chartHeight);
    points.forEach((point, index) => {
      if (index === 0) {
        ctx.lineTo(point.x, point.y);
      } else {
        ctx.lineTo(point.x, point.y);
      }
    });
    ctx.lineTo(points[points.length - 1].x, pad.top + chartHeight);
    ctx.closePath();
    ctx.fillStyle = 'rgba(26,86,219,.16)';
    ctx.fill();
  }

  ctx.strokeStyle = '#1a56db';
  ctx.lineWidth = 3;
  ctx.beginPath();
  if (safeGraphType === 'smooth' && points.length > 1) {
    ctx.moveTo(points[0].x, points[0].y);
    for (let i = 1; i < points.length; i++) {
      const previous = points[i - 1];
      const current = points[i];
      const midX = (previous.x + current.x) / 2;
      ctx.bezierCurveTo(midX, previous.y, midX, current.y, current.x, current.y);
    }
  } else if (safeGraphType === 'step' && points.length > 1) {
    ctx.moveTo(points[0].x, points[0].y);
    for (let i = 1; i < points.length; i++) {
      const previous = points[i - 1];
      const current = points[i];
      const midX = (previous.x + current.x) / 2;
      ctx.lineTo(midX, previous.y);
      ctx.lineTo(midX, current.y);
      ctx.lineTo(current.x, current.y);
    }
  } else {
    points.forEach((point, index) => {
      if (index === 0) {
        ctx.moveTo(point.x, point.y);
      } else {
        ctx.lineTo(point.x, point.y);
      }
    });
  }
  ctx.stroke();

  points.forEach((point, index) => {
    ctx.fillStyle = point.color;
    ctx.beginPath();
    ctx.arc(point.x, point.y, 5, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-text').trim() || '#1e293b';
    ctx.font = '700 13px Inter, sans-serif';
    ctx.textAlign = 'center';
    const valueLabel = showPercent && total > 0
      ? `${point.value} · ${Math.round(point.value / total * 1000) / 10}%`
      : String(point.value);
    ctx.fillText(valueLabel, point.x, point.y - 10);
    ctx.font = '12px Inter, sans-serif';
    wrapCanvasLabel(ctx, point.label, point.x, pad.top + chartHeight + 20, Math.min(120, values.length > 1 ? step : 140));
  });
}

function wrapCanvasLabel(ctx, text, x, y, maxWidth) {
  const words = String(text).split(' ');
  let line = '';
  let offset = 0;

  words.forEach((word, index) => {
    const test = line ? line + ' ' + word : word;
    if (ctx.measureText(test).width > maxWidth && line) {
      ctx.fillText(line, x, y + offset);
      line = word;
      offset += 15;
    } else {
      line = test;
    }
    if (index === words.length - 1 && line) {
      ctx.fillText(line, x, y + offset);
    }
  });
}

function ensureHrChartVisibility(metric, chart) {
  const count = (chart.labels || []).length;
  if (!hrChartVisible[metric] || hrChartVisible[metric].length !== count) {
    hrChartVisible[metric] = Array.from({ length: count }, () => true);
  }
  if (!hrChartVisible[metric].some(Boolean) && count > 0) {
    hrChartVisible[metric][0] = true;
  }
}

function selectedHrChart(chart, metric) {
  ensureHrChartVisibility(metric, chart);
  const visible = hrChartVisible[metric] || [];
  const selected = {
    title: chart.title || '',
    labels: [],
    values: [],
    colors: [],
  };

  (chart.labels || []).forEach((label, index) => {
    if (!visible[index]) return;
    selected.labels.push(label);
    selected.values.push((chart.values || [])[index] || 0);
    selected.colors.push((chart.colors || [])[index] || '#1a56db');
  });

  return selected;
}

function renderHrChartChecks(metric, chart) {
  const checks = document.getElementById('hrChartChecks');
  if (!checks) return;

  ensureHrChartVisibility(metric, chart);
  checks.innerHTML = (chart.labels || []).map((label, index) => {
    const checked = hrChartVisible[metric][index] ? 'checked' : '';
    const color = (chart.colors || [])[index] || '#1a56db';
    return `<label class="hr-chart-check">
      <input type="checkbox" data-chart-index="${index}" ${checked}>
      <span class="hr-chart-check-color" style="background:${escapeHtml(color)}"></span>
      <span>${escapeHtml(label)}</span>
    </label>`;
  }).join('');
}

function selectedHrChartIndexes(metric, chart) {
  ensureHrChartVisibility(metric, chart);
  return (hrChartVisible[metric] || [])
    .map((isVisible, index) => isVisible ? index : null)
    .filter(index => index !== null);
}

function hrExportVisualMode() {
  const chart = !!document.getElementById('hrExportChart')?.checked;
  const graph = !!document.getElementById('hrExportGraph')?.checked;
  if (chart && graph) return 'both';
  if (chart) return 'chart';
  if (graph) return 'graph';
  return 'none';
}

function syncHrGraphTypeControl() {
  const exportGraph = !!document.getElementById('hrExportGraph')?.checked;
  const graphTypeControl = document.getElementById('hrGraphTypeControl');
  graphTypeControl?.classList.toggle('is-visible', exportGraph);
}

function updateHrExportLinks() {
  const typeSelect = document.getElementById('hrChartType');
  const graphTypeSelect = document.getElementById('hrGraphType');
  const percentToggle = document.getElementById('hrChartPercent');
  const metric = 'summary';
  const chart = hrChartData[metric] || {};
  const indexes = selectedHrChartIndexes(metric, chart);
  const params = {
    chart_type: typeSelect?.value || 'doughnut',
    graph_type: graphTypeSelect?.value || 'line',
    chart_percent: percentToggle?.checked ? '1' : '0',
    chart_items: indexes.join(','),
    export_visuals: hrExportVisualMode(),
  };

  document.querySelectorAll('a[data-hr-visual-export-link]').forEach(link => {
    if (!link.dataset.exportBase) {
      link.dataset.exportBase = link.getAttribute('href') || '';
    }
    const url = new URL(link.dataset.exportBase, window.location.href);
    Object.entries(params).forEach(([key, value]) => {
      url.searchParams.set(key, value);
    });
    link.setAttribute('href', url.pathname + url.search + url.hash);
  });
}

function downloadCanvasImage(canvas, filename) {
  const link = document.createElement('a');
  link.download = filename;
  link.href = canvas.toDataURL('image/png');
  document.body.appendChild(link);
  link.click();
  link.remove();
}

function copyChartCanvasToPng(source, targetCtx, x, y, width, height) {
  targetCtx.drawImage(source, x, y, width, height);
}

function exportHrVisualsPng() {
  renderHrChart();

  const chartToggle = document.getElementById('hrExportChart');
  const graphToggle = document.getElementById('hrExportGraph');
  const chartCanvas = document.getElementById('hrStatsChart');
  const graphCanvas = document.getElementById('hrStatsGraph');
  const includeChart = chartToggle?.checked !== false && chartCanvas;
  const includeGraph = !!graphToggle?.checked && graphCanvas;
  const items = [];

  if (includeChart) {
    items.push({ title: 'Диаграмма HR-статистики', canvas: chartCanvas });
  }
  if (includeGraph) {
    items.push({ title: 'График HR-статистики', canvas: graphCanvas });
  }

  if (!items.length) {
    showToast('Выберите диаграмму или график для экспорта PNG', 'error');
    return;
  }

  const cssWidth = Math.max(...items.map(item => Math.round(item.canvas.getBoundingClientRect().width || item.canvas.width)));
  const itemHeights = items.map(item => Math.round(item.canvas.getBoundingClientRect().height || item.canvas.height));
  const padding = 32;
  const gap = 34;
  const titleHeight = 28;
  const width = Math.max(720, cssWidth) + padding * 2;
  const height = padding + items.reduce((sum, item, index) => sum + titleHeight + itemHeights[index] + (index > 0 ? gap : 0), 0) + padding;
  const ratio = window.devicePixelRatio || 1;
  const output = document.createElement('canvas');
  const ctx = output.getContext('2d');
  output.width = width * ratio;
  output.height = height * ratio;
  output.style.width = width + 'px';
  output.style.height = height + 'px';
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-surface').trim() || '#ffffff';
  ctx.fillRect(0, 0, width, height);
  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-text').trim() || '#1e293b';
  ctx.font = '700 18px Inter, sans-serif';

  let y = padding;
  items.forEach((item, index) => {
    if (index > 0) y += gap;
    const itemWidth = Math.round(item.canvas.getBoundingClientRect().width || item.canvas.width);
    const itemHeight = itemHeights[index];
    ctx.fillText(item.title, padding, y + 18);
    y += titleHeight;
    copyChartCanvasToPng(item.canvas, ctx, padding, y, itemWidth, itemHeight);
    y += itemHeight;
  });

  downloadCanvasImage(output, 'hr-visuals-' + new Date().toISOString().slice(0, 10) + '.png');
}

function renderHrLegend(legend, chart, showPercent) {
  if (!legend) return;

  const values = chart.values || [];
  const labels = chart.labels || [];
  const colors = chart.colors || [];
  const total = values.reduce((sum, value) => sum + (Number(value) || 0), 0);
  legend.innerHTML = labels.map((label, index) => {
    const value = Number(values[index]) || 0;
    const percent = total > 0 ? Math.round(value / total * 1000) / 10 : 0;
    return `<div class="hr-chart-legend-item">
      <span class="hr-chart-dot" style="background:${escapeHtml(colors[index] || '#1a56db')}"></span>
      <span>${escapeHtml(label)}</span>
      <b>${showPercent ? `${value} · ${percent}%` : value}</b>
    </div>`;
  }).join('');
}

function renderHrChart() {
  const canvas = document.getElementById('hrStatsChart');
  const graphCanvas = document.getElementById('hrStatsGraph');
  const typeSelect = document.getElementById('hrChartType');
  const graphTypeSelect = document.getElementById('hrGraphType');
  const percentToggle = document.getElementById('hrChartPercent');
  const chartPreview = document.getElementById('hrChartPreview');
  const graphPreview = document.getElementById('hrGraphPreview');
  const legend = document.getElementById('hrChartLegend');
  const graphLegend = document.getElementById('hrGraphLegend');
  const chartToggle = document.getElementById('hrExportChart');
  const graphToggle = document.getElementById('hrExportGraph');
  if (!canvas || !typeSelect || !legend) return;

  const metric = 'summary';
  const type = typeSelect.value || 'doughnut';
  const graphType = graphTypeSelect?.value || 'line';
  const showPercent = !!percentToggle?.checked;
  const showChart = chartToggle?.checked !== false;
  const showGraph = !!graphToggle?.checked;
  const chart = hrChartData[metric] || {};
  syncHrGraphTypeControl();
  renderHrChartChecks(metric, chart);
  const selectedChart = selectedHrChart(chart, metric);
  const ctx = canvas.getContext('2d');
  const ratio = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();
  const width = Math.max(320, Math.round(rect.width || canvas.width));
  const height = Math.max(260, Math.round(rect.height || canvas.height));

  chartPreview?.classList.toggle('is-hidden', !showChart);
  if (showChart) {
    canvas.width = width * ratio;
    canvas.height = height * ratio;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);

    if (type === 'bar') {
      drawHrBarChart(ctx, selectedChart, width, height, showPercent);
    } else {
      drawHrPieChart(ctx, selectedChart, type, width, height, showPercent);
    }
    renderHrLegend(legend, selectedChart, showPercent);
  } else {
    legend.innerHTML = '';
  }

  graphPreview?.classList.toggle('is-hidden', !showGraph);
  if (showGraph && graphCanvas) {
    const graphCtx = graphCanvas.getContext('2d');
    const graphRect = graphCanvas.getBoundingClientRect();
    const graphWidth = Math.max(320, Math.round(graphRect.width || graphCanvas.width));
    const graphHeight = Math.max(260, Math.round(graphRect.height || graphCanvas.height));
    const graphChart = { ...selectedChart, title: 'График HR-статистики' };
    graphCanvas.width = graphWidth * ratio;
    graphCanvas.height = graphHeight * ratio;
    graphCtx.setTransform(ratio, 0, 0, ratio, 0, 0);
    graphCtx.clearRect(0, 0, graphWidth, graphHeight);
    drawHrLineChart(graphCtx, graphChart, graphWidth, graphHeight, showPercent, graphType);
    renderHrLegend(graphLegend, selectedChart, showPercent);
  } else if (graphLegend) {
    graphLegend.innerHTML = '';
  }

  updateHrExportLinks();
}

function handleHrChartCheckChange(e) {
  const checkbox = e.target.closest('#hrChartChecks input[type="checkbox"]');
  if (!checkbox) return;

  const metric = 'summary';
  const index = Number(checkbox.dataset.chartIndex);
  if (!Number.isInteger(index)) return;

  const visible = hrChartVisible[metric] || [];
  visible[index] = checkbox.checked;

  if (!visible.some(Boolean)) {
    visible[index] = true;
    checkbox.checked = true;
    showToast('В диаграмме должен остаться хотя бы один пункт', 'error');
  }

  renderHrChart();
}

function initHrPage() {
  if (hrPageAbortController) hrPageAbortController.abort();
  hrPageAbortController = new AbortController();
  const signal = hrPageAbortController.signal;

  loadHrEmbeddedData();
  selectedStudentId = null;

  const html = document.documentElement;
  html.setAttribute('data-theme', localStorage.getItem('theme') || 'light');

  const searchInput = document.getElementById('fStudentSearch');
  const searchResults = document.getElementById('searchResults');
  const chartType = document.getElementById('hrChartType');
  const graphType = document.getElementById('hrGraphType');
  const chartPercent = document.getElementById('hrChartPercent');
  const chartChecks = document.getElementById('hrChartChecks');
  const exportChart = document.getElementById('hrExportChart');
  const exportGraph = document.getElementById('hrExportGraph');
  const exportPng = document.getElementById('hrExportPng');

  renderHrChart();
  chartType?.addEventListener('change', renderHrChart, { signal });
  graphType?.addEventListener('change', renderHrChart, { signal });
  chartPercent?.addEventListener('change', renderHrChart, { signal });
  chartChecks?.addEventListener('change', handleHrChartCheckChange, { signal });
  exportChart?.addEventListener('change', renderHrChart, { signal });
  exportGraph?.addEventListener('change', () => {
    syncHrGraphTypeControl();
    renderHrChart();
  }, { signal });
  exportPng?.addEventListener('click', exportHrVisualsPng, { signal });
  window.addEventListener('resize', renderHrChart, { signal });

  searchInput?.addEventListener('input', () => {
    const q = searchInput.value.trim();
    if (!q) {
      searchResults?.classList.remove('open');
      return;
    }

    const results = allNewStudents
      .filter(s => studentMatchesSearch(s, q))
      .slice(0, 15);

    if (!searchResults) return;

    if (results.length === 0) {
      searchResults.innerHTML = '<div class="search-result-empty">Студенты не найдены</div>';
    } else {
      searchResults.innerHTML = results.map(s => `
        <button type="button" class="search-result-item" data-student-id="${Number(s.id)}" data-student-name="${escapeHtml(s.name)}">
          <div style="font-weight:500">${escapeHtml(s.name)}</div>
          <div style="font-size:var(--text-xs);color:var(--color-text-muted)">${escapeHtml(s.group || '—')}</div>
        </button>
      `).join('');
    }

    searchResults.classList.add('open');
  }, { signal });

  searchResults?.addEventListener('click', (e) => {
    const item = e.target.closest('.search-result-item');
    if (!item) return;
    selectStudent(item.dataset.studentId, item.dataset.studentName);
  }, { signal });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-wrapper')) {
      document.getElementById('searchResults')?.classList.remove('open');
    }
  }, { signal });

  document.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (!link || !canHandleAjaxLink(link)) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    e.preventDefault();
    ajaxNavigate(link.href, true);
  }, { signal });

  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('collapsed');
    document.getElementById('mainWrapper')?.classList.toggle('sidebar-collapsed');
  }, { signal });

  document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('mobile-open');
  }, { signal });

  document.getElementById('themeToggle')?.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
  }, { signal });

  document.getElementById('fStatus')?.addEventListener('change', toggleEmployedFields, { signal });
  toggleEmployedFields();

  document.getElementById('btnHelp')?.addEventListener('click', () => {
    openModal('modalHelp');
  }, { signal });

  document.getElementById('btnAddRecord')?.addEventListener('click', () => {
    clearForm();
    document.getElementById('modalTitle').textContent = 'Добавить запись';
    document.getElementById('studentAddMode').style.display = '';
    document.getElementById('studentEditMode').style.display = 'none';
    refreshDocumentSection();
    selectedStudentId = null;
    openModal('modalRecord');
  }, { signal });

  const uploadArea = document.getElementById('uploadArea');
  const fileInput = document.getElementById('fileInput');

  uploadArea?.addEventListener('click', () => fileInput?.click(), { signal });
  uploadArea?.addEventListener('dragover', e => {
    e.preventDefault();
    uploadArea.classList.add('drag-over');
  }, { signal });
  uploadArea?.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'), { signal });
  uploadArea?.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');
    if (!fileInput) return;

    try {
      fileInput.files = e.dataTransfer.files;
      updateSelectedFilesInfo();
    } catch (err) {
      showToast('Перетащите файл через стандартный выбор файла', 'error');
    }
  }, { signal });
  fileInput?.addEventListener('change', updateSelectedFilesInfo, { signal });

  document.getElementById('filterForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    ajaxNavigate(buildFilterUrl(e.currentTarget), true);
  }, { signal });

  document.querySelectorAll('form[action="actions.php"], form[action$="/actions.php"]').forEach(form => {
    form.addEventListener('submit', (e) => {
      if (e.defaultPrevented) return;
      const submitter = e.submitter;

      if (form.id === 'recordForm' && !validateRecordForm(e, submitter)) return;

      e.preventDefault();
      submitActionForm(form, submitter);
    }, { signal });
  });

  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.remove('open');
    }, { signal });
  });

  const flash = document.querySelector('.flash-message');
  if (flash) {
    showToast(flash.textContent.trim(), flash.classList.contains('error') ? 'error' : 'success');
    flash.remove();
  }
}

window.addEventListener('popstate', () => ajaxNavigate(window.location.href, false));
document.addEventListener('DOMContentLoaded', initHrPage);
