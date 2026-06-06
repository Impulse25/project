/* ── Theme ── */
(function () {
  var saved = localStorage.getItem('svgtk-theme') || 'light';
  document.documentElement.dataset.theme = saved;
  updateIcon(saved);
})();

function toggleTheme() {
  var html = document.documentElement;
  var next = html.dataset.theme === 'dark' ? 'light' : 'dark';
  html.dataset.theme = next;
  localStorage.setItem('svgtk-theme', next);
  updateIcon(next);
}

function updateIcon(theme) {
  var icon = document.getElementById('themeIcon');
  if (!icon) return;
  if (theme === 'dark') {
    icon.innerHTML = '<path stroke-linecap="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
  } else {
    icon.innerHTML = '<circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>';
  }
}

/* ── Core logic ── */
function sendData() {
  const iin    = document.getElementById('iin').value.trim();
  const btn    = document.querySelector('.btn');
  const action = btn ? btn.dataset.action : null;

  if (!action) {
    showError('Ошибка конфигурации: action не задан');
    return;
  }

  if (!/^\d{12}$/.test(iin)) {
    showError('Введите корректный ИИН — 12 цифр');
    return;
  }

  fetch('process.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'iin=' + encodeURIComponent(iin) + '&action=' + action
  })
    .then(res => res.json())
    .then(data => data.success ? showSuccess(data, action) : showError(data.message))
    .catch(() => showError('Ошибка соединения с сервером'));
}

function showSuccess(data, action) {
  const label = action === 'entry' ? 'Вход зафиксирован' : 'Выход зафиксирован';
  document.getElementById('resultCard').innerHTML = `
    <div class="check-circle">
      <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M7 18L15 26L29 10" stroke="white" stroke-width="2.8"
          stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <div class="res-tag">${label}</div>
    <div class="res-name">${data.student}</div>
    <div class="res-group">${data.group_name}</div>
    <div class="res-time">${data.time}</div>
    <div class="res-date">${data.date}</div>
    <button class="close-btn" onclick="closeOverlay()">Закрыть</button>
  `;
  document.getElementById('overlay').className = 'overlay show';
  document.getElementById('iin').value = '';
}

function showError(msg) {
  document.getElementById('resultCard').innerHTML = `
    <div class="check-circle err">
      <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M11 11L25 25M25 11L11 25" stroke="#999" stroke-width="2.8"
          stroke-linecap="round"/>
      </svg>
    </div>
    <div class="res-tag">Ошибка</div>
    <div class="res-error">${msg}</div>
    <button class="close-btn" onclick="closeOverlay()">Закрыть</button>
  `;
  document.getElementById('overlay').className = 'overlay show';
  setTimeout(closeOverlay, 5000);
}

function closeOverlay() {
  document.getElementById('overlay').className = 'overlay';
}

document.getElementById('iin').addEventListener('keydown', e => {
  if (e.key === 'Enter') sendData();
});
