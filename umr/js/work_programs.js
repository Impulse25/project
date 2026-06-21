//  Утверждение РП (ПЦК / админ) 
async function approveWp(wpId) {
  if (!confirm('Утвердить рабочую программу?')) return;
  const res  = await fetch('actions/approve_wp.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams({ wp_id: wpId }),
  });
  const data = await res.json();
  if (data.ok) {
    location.reload();
  } else {
    alert(data.error || 'Ошибка при утверждении');
  }
}

//  Отклонение РП (ПЦК / админ) 
async function rejectWp(wpId) {
  const reason = prompt('Причина отклонения:');
  if (reason === null) return;
  if (!reason.trim()) { alert('Укажите причину отклонения'); return; }

  const res  = await fetch('actions/reject_wp.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams({ wp_id: wpId, reason }),
  });
  const data = await res.json();
  if (data.ok) {
    location.reload();
  } else {
    alert(data.error || 'Ошибка при отклонении');
  }
}

//  Удаление РП 
async function deleteWp(wpId) {
  if (!confirm('Удалить рабочую программу? Запись и файл будут удалены без возможности восстановления.')) return;
  const res  = await fetch('actions/delete_wp.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams({ wp_id: wpId }),
  });
  const data = await res.json();
  if (data.ok) {
    location.reload();
  } else {
    alert(data.error || 'Ошибка при удалении');
  }
}

// Модалка уведомления "преподаватель назначен другим ПЦК" 

let pendingWpData = null;
let confirmedPccId = null;
let pendingApproveWpId = null;
let pendingRejectWpId = null;

function openPccConfirmModal(data) {
  pendingWpData = data;
  confirmedPccId = data.current_pcc_id;

  document.getElementById('pccConfirmName').textContent = data.current_pcc_name || '—';

  document.getElementById('pccConfirmOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closePccConfirmModal() {
  document.getElementById('pccConfirmOverlay').classList.remove('open');
  document.body.style.overflow = '';
  pendingWpData  = null;
  confirmedPccId = null;
}

function confirmPccAndOpenWpModal() {
  const data = pendingWpData;
  document.getElementById('pccConfirmOverlay').classList.remove('open');
  pendingWpData = null;

  openWpModalInternal(data);
}

// Закрытие pccConfirmOverlay по клику на фон
document.getElementById('pccConfirmOverlay')
  ?.addEventListener('click', e => { if (e.target === e.currentTarget) closePccConfirmModal(); });

//  Модалка загрузки / замены РП
let wpState = {};

function openWpModal(data) {
  confirmedPccId = null;

  if (data.needs_pcc_confirm) {
    openPccConfirmModal(data);
    return;
  }

  openWpModalInternal(data);
}

function openWpModalInternal(data) {
  wpState = data;

  document.getElementById('wpModalTitle').textContent =
    (data.mode === 'update' ? 'Замена РП — ' : 'Загрузка РП — ') + data.title;
  document.getElementById('wpTeacherName').textContent = data.teacher_name || '—';
  document.getElementById('wpFile').value              = '';
  document.getElementById('wpSubmitBtn').textContent   = data.mode === 'update' ? 'Заменить' : 'Загрузить';

  const errBox   = document.getElementById('wpModalError');
  errBox.textContent  = '';
  errBox.style.display = 'none';

  const reasonBox = document.getElementById('wpRejectReason');
  if (data.mode === 'update' && data.reject_reason) {
    reasonBox.textContent    = 'Причина отклонения: ' + data.reject_reason;
    reasonBox.style.display  = 'block';
  } else {
    reasonBox.style.display  = 'none';
  }

  document.getElementById('wpModalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeWpModal() {
  document.getElementById('wpModalOverlay').classList.remove('open');
  document.body.style.overflow = '';
  confirmedPccId = null;
}

async function submitWpModal() {
  const fileInput = document.getElementById('wpFile');
  const errBox    = document.getElementById('wpModalError');
  errBox.style.display = 'none';

  if (!fileInput.files.length) {
    errBox.textContent   = 'Выберите файл';
    errBox.style.display = 'block';
    return;
  }

  const btn = document.getElementById('wpSubmitBtn');
  btn.disabled    = true;
  btn.textContent = 'Загружаю...';

  const fd = new FormData();
  fd.append('file', fileInput.files[0]);

  if (confirmedPccId) {
    fd.append('confirmed_pcc_id', confirmedPccId);
  }

  let url;
  if (wpState.mode === 'update') {
    fd.append('wp_id',         wpState.wp_id);
    fd.append('assignment_id', wpState.assignment_id);
    url = 'actions/update_wp.php';
  } else {
    fd.append('assignment_id', wpState.assignment_id);
    fd.append('module_id',     wpState.module_id);
    fd.append('group_id',      wpState.group_id);
    url = 'actions/upload_wp.php';
  }

  const res  = await fetch(url, { method: 'POST', body: fd });
  const data = await res.json();

  btn.disabled    = false;
  btn.textContent = wpState.mode === 'update' ? 'Заменить' : 'Загрузить';

  if (data.ok) {
    closeWpModal();
    location.reload();
  } else {
    errBox.textContent   = data.error || 'Ошибка при загрузке';
    errBox.style.display = 'block';
  }
}

// Закрытие по клику на оверлей
document.getElementById('wpModalOverlay')
  ?.addEventListener('click', e => { if (e.target === e.currentTarget) closeWpModal(); });


// Открыть подтверждение
function confirmApproveWp(wpId, pccName) {
    pendingApproveWpId = wpId;
    document.getElementById('approvePccName').textContent = pccName || '—';
    document.getElementById('approveConfirmOverlay').classList.add('open');
}

// Закрыть
function closeApproveConfirmModal() {
    document.getElementById('approveConfirmOverlay').classList.remove('open');
    pendingApproveWpId = null;
}

// Подтвердить и выполнить
document.getElementById('confirmApproveBtn').addEventListener('click', function() {
    if (pendingApproveWpId) {
        approveWp(pendingApproveWpId);
        closeApproveConfirmModal();
    }
});

// Подтверждение отклонения
function confirmRejectWp(wpId, pccName) {
    pendingRejectWpId = wpId;
    document.getElementById('rejectPccName').textContent = pccName || '—';
    document.getElementById('rejectConfirmOverlay').classList.add('open');
}

function closeRejectConfirmModal() {
    document.getElementById('rejectConfirmOverlay').classList.remove('open');
    pendingRejectWpId = null;
}

// Подтверждение действия
document.getElementById('confirmRejectBtn').addEventListener('click', function() {
    if (pendingRejectWpId) {
        rejectWp(pendingRejectWpId);
        closeRejectConfirmModal();
    }
});