// Текущие данные модали назначения
let _assignCtx = null;

function openAssignModal(cellKey, moduleId, groupId, semesterNum) {
  _assignCtx = { cellKey, moduleId, groupId, semesterNum };

  const modal = document.getElementById('modalAssign');
  const body  = document.getElementById('modalAssignBody');

  // Сборка содержимого
  let html = '';

  if (isAdmin) {
    html += `<div class="modal-field">
      <label class="modal-label">Председатель ПЦК <span style="color:var(--color-error)">*</span></label>
      <select id="maSelectPcc" class="modal-select" required>
        <option value="">— выберите председателя —</option>
        ${pccHeads.map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('')}
      </select>
    </div>`;
  }

  html += `<div class="modal-field">
    <label class="modal-label">Преподаватель <span style="color:var(--color-error)">*</span></label>
    <select id="maSelectTeacher" class="modal-select" required>
      <option value="">— выберите преподавателя —</option>
      ${teachers.map(t => `<option value="${t.id}">${escHtml(t.name)}</option>`).join('')}
    </select>
  </div>`;

  body.innerHTML = html;
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';

  setTimeout(() => body.querySelector('select')?.focus(), 50);
}

function closeAssignModal() {
  document.getElementById('modalAssign').classList.remove('open');
  document.body.style.overflow = '';
  _assignCtx = null;
}

async function submitAssignModal() {
  if (!_assignCtx) return;

  const teacherSel = document.getElementById('maSelectTeacher');
  const pccSel     = document.getElementById('maSelectPcc');
  const teacherId  = teacherSel?.value;
  const pccHeadId  = pccSel?.value ?? '';

  if (!teacherId) { teacherSel.focus(); return; }
  if (isAdmin && pccSel && !pccHeadId) { pccSel.focus(); return; }

  const btn = document.getElementById('modalAssignSubmit');
  btn.disabled = true;
  btn.textContent = 'Назначаю...';

  const params = {
    module_id:    _assignCtx.moduleId,
    group_id:     _assignCtx.groupId,
    semester_num: _assignCtx.semesterNum,
    teacher_id:   teacherId,
  };
  if (isAdmin && pccHeadId) params.pcc_head_id = pccHeadId;

  const res  = await fetch('actions/assign.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams(params),
  });
  const data = await res.json();

  btn.disabled = false;
  btn.textContent = 'Назначить';

  if (data.ok) {
    const { cellKey } = _assignCtx;
    const list   = document.getElementById('tlist-' + cellKey);
    const btnAdd = list?.querySelector('.btn-add-teacher');
    
    if (list && btnAdd) {
      const chip = document.createElement('span');
      chip.className = 'teacher-chip';
      chip.innerHTML = `${escHtml(data.teacher_name)}
        <button class="chip-remove" title="Снять"
          onclick="removeAssignment(${data.assignment_id},'${cellKey}')">×</button>`;
      list.insertBefore(chip, btnAdd);
    }

    const pccCell = list?.closest('tr')?.querySelector('.pcc-cell');
    if (pccCell && data.pcc_name) {
      const alreadyShown = [...pccCell.querySelectorAll('.pcc-label')]
        .some(el => el.textContent.trim() === data.pcc_name.trim());
      if (!alreadyShown) {
        const label = document.createElement('div');
        label.className = 'pcc-label';
        label.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ${escHtml(data.pcc_name)}`;
        pccCell.appendChild(label);
      }
    }

    closeAssignModal();
  } else {
    // Ошибка внутри модали
    let errEl = document.getElementById('maError');
    if (!errEl) {
      errEl = document.createElement('p');
      errEl.id = 'maError';
      errEl.style.cssText = 'color:var(--color-error);font-size:.83rem;margin-top:.5rem;margin-bottom:0';
      document.getElementById('modalAssignBody').appendChild(errEl);
    }
    errEl.textContent = data.error || 'Ошибка при назначении';
  }
}

//  Модаль экспорта тарификации  для admin
function openExportModal() {
  document.getElementById('modalExport').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeExportModal() {
  document.getElementById('modalExport').classList.remove('open');
  document.body.style.overflow = '';
}

function submitExportModal() {
  const sel = document.getElementById('meSelectPcc');
  if (!sel.value) { sel.focus(); return; }
  const year = filterYear;
  closeExportModal();
  window.location.href = `actions/export_tarification.php?academic_year=${year}&pcc_head_id=${sel.value}`;
}

function submitExportTestsModal() {
  window.location.href = `actions/export_tests.php?academic_year=${filterYear}`;
}

// ── Снятие преподавателя ──────────────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Снятие преподавателя ──────────────────────────────────────────────────────
async function removeAssignment(assignmentId, key) {
  if (!confirm('Снять преподавателя с этой дисциплины?')) return;

  const res  = await fetch('actions/unassign.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ assignment_id: assignmentId })
  });
  const data = await res.json();

  if (!data.ok) {
    alert(data.error || 'Ошибка при удалении');
    return;
  }

  const list = document.getElementById('tlist-' + key);

  list?.querySelectorAll('.teacher-chip').forEach(chip => {
    if (chip.querySelector('.chip-remove')
          ?.getAttribute('onclick')?.includes(String(assignmentId))) {
      chip.remove();
    }
  });

  const pccCell = list?.closest('tr')?.querySelector('.pcc-cell');
  if (pccCell) {
    pccCell.innerHTML = '';
  }
}