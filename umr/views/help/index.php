<?php
// views/help/index.php
define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/partials/init.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Справка — УМР — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../css/style.css">
  <link rel="stylesheet" href="../../css/style_help.css">

</head>
<body>
  
<?php $_nav_active_key = '';
      require __DIR__ . '/../../partials/sidebar.php'; ?>

<div class="main-wrapper" id="mainWrapper">
  <?php
    $_breadcrumbs = ['УМР' => null, 'Справка' => null];
    require_once BASE_PATH . '/partials/topbar.php';
  ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Справка</h1>
        <p class="page-subtitle">Ответы на часто задаваемые вопросы по работе с модулем УМР</p>
        
      </div>
    </div>

    <div class="card">
      <div class="card-body">

        <!--  Рабочие программы  -->
        <div class="faq-section">
          <div class="faq-section-label">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
              <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
            Рабочие программы
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Как загрузить рабочую программу?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Перейдите в раздел <b>РУП</b>, найдите нужную дисциплину и нажмите кнопку
              <b>«Загрузить»</b>. Допустимые форматы файла: <b>.doc, .docx, .pdf, .xls, .xlsx.</b>.
              Максимальный размер — <b>20 МБ</b>. После загрузки программа получает статус
              <span class="badge-status pending">На проверке</span> и ожидает решения председателя ПЦК.
              <div class="help-tip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Загрузить программу может сам преподаватель, его председатель ПЦК или администратор.
              </div>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Что означают статусы рабочей программы?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              В системе используются три статуса:
              <ul>
                <li>
                  <span class="badge-status pending">На проверке</span> —
                  программа загружена и ожидает решения председателя ПЦК.
                </li>
                <li>
                  <span class="badge-status approved">Утверждена</span> —
                  председатель ПЦК принял программу. Её можно регистрировать в журнале.
                </li>
                <li>
                  <span class="badge-status rejected">Отклонена</span> —
                  программа требует доработки. Причина отклонения указана в карточке.
                  Загрузите исправленный вариант через кнопку <b>«Редактировать»</b>.
                </li>
              </ul>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Можно ли заменить уже загруженную программу?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Да. Нажмите кнопку <b>«Редактировать»</b> рядом с нужной программой и загрузите
              новый файл. Версия программы будет увеличена автоматически. Заменить можно
              программу со статусом <span class="badge-status rejected">Отклонена</span>
              или <span class="badge-status pending">На проверке</span>.
              <div class="help-tip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Программу со статусом <span class="badge-status approved">Утверждена</span>
                заменить нельзя — сначала обратитесь к председателю ПЦК для отклонения.
              </div>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Какие форматы файлов принимает система?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Система принимает файлы в форматах <b>.doc</b>, <b>.docx</b> и <b>.pdf</b>, <b>.xls</b> и <b>.xlsx</b>.
              Максимальный допустимый размер файла — <b>20 МБ</b>.
              Файлы других форматов (например, .odt, .pages, .pptx) не принимаются.
            </div>
          </div>
        </div>

        <!--  Назначения преподавателей  -->
        <div class="faq-section">
          <div class="faq-section-label">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Назначения преподавателей
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Кто может назначать преподавателей на дисциплины?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Назначать преподавателей могут только <b>председатель ПЦК</b> и
              <b>администратор</b>. Раздел «Назначения» недоступен для обычных преподавателей —
              они видят только свои дисциплины в разделе <b>РУП</b>.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Как снять назначение преподавателя?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              В разделе <b>«Назначения»</b> найдите нужную строку, нажмите кнопку
              <b>«×»</b> рядом с именем преподавателя и подтвердите действие.
              <div class="help-tip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Если для назначения уже загружена рабочая программа — сначала удалите её,
                затем снимайте назначение.
              </div>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Как выгрузить тарификационную ведомость?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              В разделе <b>«Назначения»</b> нажмите кнопку <b>«Экспорт тарификации»</b>
              в правом верхнем углу. Выберите учебный год и нажмите <b>«Скачать Excel»</b>.
              Файл формата <b>.xlsx</b> загрузится автоматически.
            </div>
          </div>
        </div>

        <!--  Нагрузка  -->
        <div class="faq-section">
          <div class="faq-section-label">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="20" x2="18" y2="10"/>
              <line x1="12" y1="20" x2="12" y2="4"/>
              <line x1="6" y1="20" x2="6" y2="14"/>
            </svg>
            Нагрузка
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Как выгрузить нагрузку в Excel?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Перейдите в раздел <b>«Нагрузка»</b>, выберите нужный учебный год и нажмите
              кнопку <b>«Экспорт в Excel»</b>. Файл формата <b>.xlsx</b> загрузится
              автоматически. Имя файла содержит ФИО преподавателя и учебный год.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Почему в нагрузке не отображаются некоторые дисциплины?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Нагрузка формируется только по дисциплинам, на которые преподаватель
              назначен председателем ПЦК. Если дисциплина отсутствует — обратитесь
              к своему председателю ПЦК для проверки назначений в разделе
              <b>«Назначения»</b>.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Могу ли я просматривать нагрузку другого преподавателя?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Да, если вы являетесь <b>председателем ПЦК</b> или <b>администратором</b>.
              В разделе «Нагрузка» выберите нужного преподавателя из выпадающего списка.
              Обычные преподаватели видят только свою нагрузку.
            </div>
          </div>
        </div>

        <!--  Журнал регистрации  -->
        <div class="faq-section">
          <div class="faq-section-label">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Журнал регистрации
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Когда можно зарегистрировать программу в журнале?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Только после того, как рабочая программа получила статус
              <span class="badge-status approved">Утверждена</span>. Программы со статусом
              <span class="badge-status pending">На проверке</span> или
              <span class="badge-status rejected">Отклонена</span> зарегистрировать невозможно.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Можно ли удалить запись из журнала?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              Да. Удалить запись может председатель ПЦК, создавший её, или администратор.
              Нажмите кнопку удаления в строке журнала и подтвердите действие.
              <div class="help-tip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Удаление записи из журнала не удаляет саму рабочую программу.
              </div>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              Как экспортировать журнал регистрации?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </button>
            <div class="faq-answer">
              В разделе <b>«Журнал регистрации»</b> нажмите кнопку <b>«Экспорт»</b>.
              Будет сформирован файл <b>.xlsx</b> с текущим содержимым журнала с учётом
              выбранных фильтров (год, семестр, группа).
            </div>
          </div>
        </div>

      </div>
    </div>

  </main>
</div>

<script>

// Аккордеон
document.querySelectorAll('.faq-question').forEach(btn => {
  btn.addEventListener('click', () => {
    const item   = btn.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
  });
});
</script>

<script src="../../js/umr.js"></script>
</body>
</html>
