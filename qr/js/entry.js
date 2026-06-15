/* ============================================================
   ТЕМА
   ============================================================ */
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

/* ============================================================
   ГЕОЛОКАЦИЯ — состояние
   ============================================================ */
var geoState = {
  granted:  false,   // разрешение выдано
  denied:   false,   // пользователь отказал
  lat:      null,    // текущие координаты
  lng:      null,
  watching: null,    // ID watchPosition
};

/* Радиус допуска в метрах */
var GEO_RADIUS = 100;

var CAMPUSES = {
  main:    { lat: 49.802336515317165, lng: 72.82927229999896, name: 'Главный корпус' },
  sport:   { lat: 49.793952329641684, lng: 72.81703731562591, name: 'Корпус ФКиС' },
  school:  { lat: 49.79409883139033, lng: 72.81945789003095, name: 'Корпус школьно-русского отделения' },
  foreign: { lat: 49.79415538925407, lng: 72.81880781723116, name: 'Корпус иностранного отделения' },
};

var selectedCampus = null;   // ключ из CAMPUSES

/* ============================================================
   ВСПЛЫВАЮЩЕЕ ОКНО ГЕОЛОКАЦИИ
   ============================================================ */
window.addEventListener('DOMContentLoaded', function () {
  var popup = document.getElementById('geoPopup');
  if (popup) popup.classList.add('show');
});

function requestGeo() {
  var popup = document.getElementById('geoPopup');
  if (!navigator.geolocation) {
    if (popup) popup.classList.remove('show');
    showGeoStatus('error', 'Геолокация не поддерживается вашим браузером');
    return;
  }

  if (popup) popup.classList.remove('show');
  showGeoStatus('loading', 'Определяем местоположение…');

  var options = { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 };

  /* Сначала один запрос для быстрого получения позиции */
  navigator.geolocation.getCurrentPosition(
    function (pos) {
      geoState.granted = true;
      geoState.lat = pos.coords.latitude;
      geoState.lng = pos.coords.longitude;
      onPositionUpdate();

      /* Запускаем watchPosition для актуализации в реальном времени */
      geoState.watching = navigator.geolocation.watchPosition(
        function (p) {
          geoState.lat = p.coords.latitude;
          geoState.lng = p.coords.longitude;
          onPositionUpdate();
        },
        function (err) { /* игнорируем ошибки watch — позиция уже есть */ },
        options
      );
    },
    function (err) {
      geoState.denied = true;
      var msg = 'Доступ к геолокации отклонён. Вход невозможен.';
      if (err.code === 1) msg = 'Вы запретили доступ к геолокации. Разрешите его в настройках браузера.';
      if (err.code === 3) msg = 'Истекло время ожидания геолокации. Попробуйте ещё раз.';
      showGeoStatus('error', msg);
    },
    options
  );
}

function denyGeo() {
  var popup = document.getElementById('geoPopup');
  if (popup) popup.classList.remove('show');
  geoState.denied = true;
  showGeoStatus('error', 'Геолокация отклонена. Отметка входа невозможна.');
}

/* ============================================================
   ПЕРЕСЧЁТ СТАТУСА ПРИ ОБНОВЛЕНИИ ПОЗИЦИИ
   ============================================================ */
function onPositionUpdate() {
  if (!selectedCampus) {
    showGeoStatus('info', 'Геолокация получена. Выберите корпус.');
    return;
  }
  checkProximity();
}

/* ============================================================
   ВЫПАДАЮЩИЙ СПИСОК КОРПУСОВ
   ============================================================ */
function toggleDropdown() {
  var dd = document.getElementById('campusDropdown');
  var arrow = document.getElementById('selectArrow');
  var box = document.getElementById('campusSelectBox');
  if (!dd) return;
  var open = dd.classList.toggle('open');
  if (arrow) arrow.classList.toggle('rotated', open);
  if (box)   box.classList.toggle('active', open);
}

/* Закрываем при клике вне списка */
document.addEventListener('click', function (e) {
  var wrap = document.getElementById('campusWrap');
  if (wrap && !wrap.contains(e.target)) {
    var dd = document.getElementById('campusDropdown');
    var arrow = document.getElementById('selectArrow');
    var box = document.getElementById('campusSelectBox');
    if (dd)    dd.classList.remove('open');
    if (arrow) arrow.classList.remove('rotated');
    if (box)   box.classList.remove('active');
  }
});

function selectCampus(el) {
  selectedCampus = el.dataset.value;

  /* Обновляем текст в «кнопке» */
  var nameEl = el.querySelector('.campus-name');
  var addrEl = el.querySelector('.campus-addr');
  var txt    = document.getElementById('campusSelectedText');
  if (txt && nameEl) {
    txt.textContent = nameEl.textContent;
    txt.classList.remove('select-placeholder');
  }

  /* Подсвечиваем выбранный пункт */
  var items = document.querySelectorAll('.dropdown-item');
  items.forEach(function (i) { i.classList.remove('selected'); });
  el.classList.add('selected');

  /* Закрываем дропдаун */
  toggleDropdown();

  /* Проверяем радиус сразу */
  if (geoState.granted && geoState.lat !== null) {
    checkProximity();
  } else if (!geoState.denied) {
    showGeoStatus('info', 'Ожидаем геолокацию…');
  }
}

/* ============================================================
   РАСЧЁТ РАССТОЯНИЯ (формула Гаверсинуса)
   ============================================================ */
function haversineMeters(lat1, lng1, lat2, lng2) {
  var R = 6371000; // радиус Земли в метрах
  var dLat = (lat2 - lat1) * Math.PI / 180;
  var dLng = (lng2 - lng1) * Math.PI / 180;
  var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
          Math.sin(dLng / 2) * Math.sin(dLng / 2);
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function checkProximity() {
  if (!selectedCampus || geoState.lat === null) return;
  var campus = CAMPUSES[selectedCampus];
  if (!campus) return;

  var dist = Math.round(haversineMeters(geoState.lat, geoState.lng, campus.lat, campus.lng));

  if (dist <= GEO_RADIUS) {
    showGeoStatus('ok', 'Вы находитесь в зоне корпуса');
  } else {
    showGeoStatus('far', 'Вы находитесь вне зоны выбранного корпуса');
  }
}

/* ============================================================
   ОТОБРАЖЕНИЕ СТАТУСА ГЕОЛОКАЦИИ
   ============================================================ */
function showGeoStatus(type, msg) {
  var el = document.getElementById('geoStatus');
  if (!el) return;
  el.className = 'geo-status geo-status--' + type;
  el.textContent = msg;
}

/* ============================================================
   ИНФОРМАЦИЯ ОБ УСТРОЙСТВЕ
   ============================================================ */
function getDeviceInfo() {
  var nav = window.navigator || {};
  var scr = window.screen   || {};
  return {
    screen_w : scr.width        || '',
    screen_h : scr.height       || '',
    timezone : (typeof Intl !== 'undefined' && Intl.DateTimeFormat)
               ? Intl.DateTimeFormat().resolvedOptions().timeZone || ''
               : '',
    language : nav.language     || nav.userLanguage || '',
    platform : nav.platform     || '',
  };
}

/* ============================================================
   ОТПРАВКА ДАННЫХ
   ============================================================ */
function sendData() {
  var iin    = document.getElementById('iin').value.trim();
  var btn    = document.querySelector('.btn');
  var action = btn ? btn.dataset.action : null;

  if (!action) { showError('Ошибка конфигурации: action не задан'); return; }

  /* 1. Корпус выбран? */
  if (!selectedCampus) {
    showError('Пожалуйста, выберите корпус из списка');
    highlightCampusError();
    return;
  }

  /* 2. Геолокация разрешена? */
  if (geoState.denied) {
    showError('Вы запретили геолокацию. Вход без неё невозможен.');
    return;
  }

  if (!geoState.granted || geoState.lat === null) {
    showError('Ожидаем получения координат. Пожалуйста, подождите.');
    return;
  }

  /* 3. В радиусе 100 м? */
  var campus = CAMPUSES[selectedCampus];
  var dist   = haversineMeters(geoState.lat, geoState.lng, campus.lat, campus.lng);
  if (dist > GEO_RADIUS) {
    showError('Вы находитесь вне зоны выбранного корпуса. Отметка возможна только на территории корпуса.');
    return;
  }

  /* 4. ИИН корректен? */
  if (!/^\d{12}$/.test(iin)) {
    showError('Введите корректный ИИН — 12 цифр');
    return;
  }

  /* Всё ок — отправляем */
  var dev = getDeviceInfo();

  var body = new URLSearchParams({
    iin:      iin,
    action:   action,
    campus:   selectedCampus,
    geo_lat:  geoState.lat,
    geo_lng:  geoState.lng,
    screen_w: dev.screen_w,
    screen_h: dev.screen_h,
    timezone: dev.timezone,
    language: dev.language,
    platform: dev.platform,
  }).toString();

  fetch('process.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    body,
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.success) showSuccess(data, action);
      else showError(data.message);
    })
    .catch(function () { showError('Ошибка соединения с сервером'); });
}

/* ============================================================
   ПОДСВЕТКА ОШИБКИ СПИСКА КОРПУСОВ
   ============================================================ */
function highlightCampusError() {
  var box = document.getElementById('campusSelectBox');
  if (!box) return;
  box.classList.add('error');
  setTimeout(function () { box.classList.remove('error'); }, 2500);
}

/* ============================================================
   КАРТОЧКИ РЕЗУЛЬТАТА
   ============================================================ */
function showSuccess(data, action) {
  var label = action === 'entry' ? 'Вход зафиксирован' : 'Выход зафиксирован';
  document.getElementById('resultCard').innerHTML =
    '<div class="check-circle">' +
      '<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M7 18L15 26L29 10" stroke="white" stroke-width="2.8"' +
          ' stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>' +
    '</div>' +
    '<div class="res-tag">' + label + '</div>' +
    '<div class="res-name">' + data.student + '</div>' +
    '<div class="res-group">' + data.group_name + '</div>' +
    '<div class="res-time">' + data.time + '</div>' +
    '<div class="res-date">' + data.date + '</div>' +
    '<button class="close-btn" onclick="closeOverlay()">Закрыть</button>';
  document.getElementById('overlay').className = 'overlay show';
  document.getElementById('iin').value = '';
}

function showError(msg) {
  document.getElementById('resultCard').innerHTML =
    '<div class="check-circle err">' +
      '<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M11 11L25 25M25 11L11 25" stroke="#999" stroke-width="2.8"' +
          ' stroke-linecap="round"/>' +
      '</svg>' +
    '</div>' +
    '<div class="res-tag">Ошибка</div>' +
    '<div class="res-error">' + msg + '</div>' +
    '<button class="close-btn" onclick="closeOverlay()">Закрыть</button>';
  document.getElementById('overlay').className = 'overlay show';
}

function closeOverlay() {
  document.getElementById('overlay').className = 'overlay';
}

/* ============================================================
   ENTER в поле ИИН
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  var inp = document.getElementById('iin');
  if (inp) {
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') sendData();
    });
  }
});
