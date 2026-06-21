// js/register_journal.js

// Блок "Утверждённые РП — ожидают регистрации" (сворачивание)
let pendingOpen = true;
function togglePending() {
  pendingOpen = !pendingOpen;
  document.getElementById('pendingBody').classList.toggle('collapsed', !pendingOpen);
  document.getElementById('pendingChevron').style.transform = pendingOpen ? '' : 'rotate(-90deg)';
}

//  Модалка регистрации РП
let regState = {};
let pendingRegData = null; // data, ожидающие подтверждения (чужого ПЦК)

// Если нажал чужой ПЦК выведется сперва окно с подтверждение уведомления
function maybeOpenRegModal(data) {
  if (data.needs_pcc_confirm) {
    pendingRegData = data;
    document.getElementById('pccConfirmName').textContent = data.pcc_name || '—';
    document.getElementById('pccConfirmOverlay').classList.add('open');
    return;
  }
  openRegModal(data);
}

function closePccConfirmModal() {
  document.getElementById('pccConfirmOverlay').classList.remove('open');
  pendingRegData = null;
}

function confirmPccAndOpenRegModal() {
  if (pendingRegData) {
    openRegModal(pendingRegData);
    closePccConfirmModal();
  }
}

function openRegModal(data) {
  regState = data;
  document.getElementById('regTitle').textContent    = data.title        || '—';
  document.getElementById('regGroup').textContent    = data.group_name   || '—';
  document.getElementById('regTeacher').textContent  = data.teacher_name || '—';
  document.getElementById('regDate').value           = new Date().toISOString().slice(0, 10);
  document.getElementById('regModalError').style.display = 'none';
  document.getElementById('regModalOverlay').classList.add('open');
}

function closeRegModal() {
  document.getElementById('regModalOverlay').classList.remove('open');
}

async function submitReg() {
  const errBox = document.getElementById('regModalError');
  errBox.style.display = 'none';

  const date = document.getElementById('regDate').value;
  if (!date) {
    errBox.textContent = 'Укажите дату регистрации';
    errBox.style.display = 'block';
    return;
  }

  const res  = await fetch('actions/register.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ wp_id: regState.wp_id, registered_at: date })
  });
  const data = await res.json();

  if (data.ok) {
    closeRegModal();
    location.reload();
  } else {
    errBox.textContent = data.error || 'Ошибка при регистрации';
    errBox.style.display = 'block';
  }
}

//  Удаление записи из журнала
async function deleteJournal(journalId) {
  if (!confirm('Удалить запись из журнала регистрации?')) return;

  const res  = await fetch('actions/delete_journal.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ journal_id: journalId })
  });
  const data = await res.json();

  if (data.ok) {
    location.reload();
  } else {
    alert(data.error || 'Ошибка при удалении');
  }
}