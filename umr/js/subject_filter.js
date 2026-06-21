let sfOpen = true;

function toggleSfBody() {
  sfOpen = !sfOpen;
  document.getElementById('sfBody').classList.toggle('collapsed', !sfOpen);
  document.getElementById('sfChevron').style.transform = sfOpen ? '' : 'rotate(-90deg)';
}

function sfUpdateCount() {
  const total   = document.querySelectorAll('.sf-chip').length;
  const checked = document.querySelectorAll('.sf-chip.checked').length;
  const el = document.getElementById('sfCount');
  if (el) el.textContent = checked < total ? `(${checked} из ${total})` : '';
}

function sfToggle(cb, key) {
  cb.closest('.sf-chip').classList.toggle('checked', cb.checked);
  document.querySelectorAll(`tr.subject-row[data-sf-key="${CSS.escape(key)}"]`).forEach(tr => {
    tr.classList.toggle('sf-hidden', !cb.checked);
  });
  sfUpdateCount();
}

function sfSelectAll() {
  document.querySelectorAll('.sf-chip input[type=checkbox]').forEach(cb => {
    if (!cb.checked) { cb.checked = true; cb.dispatchEvent(new Event('change')); }
  });
}

function sfSelectNone() {
  document.querySelectorAll('.sf-chip input[type=checkbox]').forEach(cb => {
    if (cb.checked) { cb.checked = false; cb.dispatchEvent(new Event('change')); }
  });
}

sfUpdateCount();