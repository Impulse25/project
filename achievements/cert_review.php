<?php
require_once 'includes/header.php';

if ($role === 'student') {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Если сессия пустая — открываем пустую форму для ручного заполнения
if (empty($_SESSION['cert_parse'])) {
    $_SESSION['cert_parse'] = [
        'edu_student_id' => 0,
        'user_id'        => 0,
        'owner_name'     => '',
        'ptype'          => 'teacher',
        'filename'       => '',
        'title'          => '', 'recipient_name' => '', 'position'      => '',
        'recipient_org'  => '', 'curator_name'   => '', 'issuer'        => '',
        'event_name'     => '', 'level'          => '', 'place'         => '',
        'nomination'     => '', 'doc_number'     => '', 'doc_lang'      => 'Казахский',
        'date'           => '', 'notes'          => '', 'error'         => '',
    ];
}

$data  = $_SESSION['cert_parse'];
$pdo   = getPDO();
$ptype = $data['ptype'] ?? 'student';

$ext2  = strtolower(pathinfo($data['filename'], PATHINFO_EXTENSION));
$isImg = in_array($ext2, ['jpg','jpeg','png','webp']);
$fileUrl = SITE_URL . '/uploads/' . h($data['filename']);

$openrouterKey = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';

$docTypes = [
    '' => '— выберите тип —',
    'Алғыс хат / Благодарственное письмо' => 'Алғыс хат / Благодарственное письмо',
    'Диплом'                    => 'Диплом',
    'Грамота'                   => 'Грамота',
    'Сертификат'                => 'Сертификат',
    'Марапаттама'               => 'Марапаттама',
    'Свидетельство'             => 'Свидетельство',
    'Медаль'                    => 'Медаль',
    'Кубок'                     => 'Кубок',
    'Грант'                     => 'Грант',
    'Другое'                    => 'Другое',
];

$levels = [
    ''              => '— не указан —',
    'college'       => 'Колледж',
    'city'          => 'Городской',
    'regional'      => 'Областной / Региональный',
    'national'      => 'Республиканский',
    'international' => 'Международный',
];

$results = [
    ''                          => '— не указан —',
    '1 место'                   => '1 место',
    '2 место'                   => '2 место',
    '3 место'                   => '3 место',
    'Победитель'                => 'Победитель',
    'Призёр'                    => 'Призёр',
    'Лауреат'                   => 'Лауреат',
    'Участник'                  => 'Участник',
    'Организатор / Содействие'  => 'Организатор / Содействие',
    'Другое'                    => 'Другое',
];

$langs = [
    'Казахский'  => 'Казахский',
    'Русский'    => 'Русский',
    'Английский' => 'Английский',
    'Другой'     => 'Другой',
];

// Текущие значения из OCR
$cv = [
    'title'          => $data['title']          ?? '',
    'date'           => $data['date']            ?? '',
    'recipient_name' => $data['recipient_name']  ?? '',
    'position'       => $data['position']        ?? '',
    'recipient_org'  => $data['recipient_org']   ?? '',
    'issuer'         => $data['issuer']          ?? '',
    'event_name'     => $data['event_name']      ?? '',
    'level'          => $data['level']           ?? '',
    'place'          => $data['place']           ?? '',
    'nomination'     => $data['nomination']      ?? '',
    'doc_number'     => $data['doc_number']      ?? '',
    'doc_lang'       => $data['doc_lang']        ?? 'Казахский',
    'curator_name'   => $data['curator_name']    ?? '',
    'notes'          => $data['notes']           ?? '',
];

function selOpt(array $opts, string $cur): string {
    $out = '';
    foreach ($opts as $val => $lbl) {
        $sel = ($cur === (string)$val) ? ' selected' : '';
        $out .= '<option value="' . h($val) . '"' . $sel . '>' . h($lbl) . '</option>';
    }
    // если текущее значение не в списке — добавляем
    if ($cur && !array_key_exists($cur, $opts)) {
        $out .= '<option value="' . h($cur) . '" selected>' . h($cur) . '</option>';
    }
    return $out;
}
?>
<style>
.cr-wrap{display:grid;grid-template-columns:320px 1fr;gap:1.25rem;max-width:1280px;align-items:start}
.cr-left{display:flex;flex-direction:column;gap:1rem}

/* Загрузчик */
.cr-upload-zone{border:2px dashed var(--border-2);border-radius:var(--r-lg);padding:1.5rem 1rem;text-align:center;color:var(--text-m);font-size:.8125rem;background:var(--surface-2);cursor:pointer;transition:border-color var(--dur)}
.cr-upload-zone:hover{border-color:var(--blue)}
.cr-upload-zone svg{display:block;margin:0 auto .5rem;opacity:.45}
.cr-upload-zone a{color:var(--blue);font-weight:500}
.cr-upload-sub{font-size:.72rem;color:var(--text-f);margin-top:.25rem}
.cr-preview{margin-top:.75rem}
.cr-preview img{width:100%;border-radius:var(--r-md);border:1px solid var(--border)}
.cr-preview iframe{width:100%;height:320px;border:1px solid var(--border);border-radius:var(--r-md)}
.cr-file-badge{display:flex;align-items:center;gap:.5rem;background:var(--green-bg);border:1px solid var(--green-bd);border-radius:var(--r-md);padding:.4rem .75rem;margin-top:.5rem;font-size:.78rem;color:var(--green);font-weight:500}

/* Автозаполнение */
.cr-auto-card{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);overflow:hidden}
.cr-auto-head{padding:.75rem 1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.5rem}
.cr-auto-head svg{color:#6366f1}
.cr-auto-title{font-weight:600;font-size:.875rem;color:#4f46e5}
.cr-auto-body{padding:.875rem 1rem;display:flex;flex-direction:column;gap:.75rem}
.cr-section-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-m);margin-bottom:.3rem}
.cr-btn-manual{width:100%;height:36px;background:#4f46e5;color:#fff;border:none;border-radius:var(--r-md);font-size:.8125rem;font-weight:500;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;transition:background var(--dur)}
.cr-btn-manual:hover{background:#4338ca}
.cr-btn-openrouter{width:100%;height:36px;background:#fffbeb;color:#92400e;border:1px solid #fde68a;border-radius:var(--r-md);font-size:.8125rem;font-weight:500;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;transition:background var(--dur)}
.cr-btn-openrouter:hover{background:#fef3c7}
.cr-divider{border:none;border-top:1px solid var(--border);margin:0}
.cr-step{display:flex;gap:.6rem;align-items:flex-start}
.cr-step-num{width:20px;height:20px;border-radius:50%;background:#6366f1;color:#fff;font-size:.68rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.cr-step-text{font-size:.78rem;color:var(--text-m);line-height:1.45;flex:1}
.cr-btn-row{display:flex;gap:.5rem;margin-top:.4rem}
.cr-btn-ai{flex:1;height:30px;background:#fff;border:1px solid var(--border-2);border-radius:var(--r-md);font-size:.75rem;font-weight:500;color:var(--text-2);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.3rem;text-decoration:none;transition:background var(--dur)}
.cr-btn-ai:hover{background:var(--bg);text-decoration:none;color:var(--text)}
.cr-textarea{width:100%;box-sizing:border-box;border:1px solid var(--border-2);border-radius:var(--r-md);padding:.5rem .6rem;font-size:.72rem;font-family:monospace;background:var(--surface-2);color:var(--text);resize:vertical;margin-top:.4rem}
.cr-btn-apply{width:100%;height:32px;background:#4f46e5;color:#fff;border:none;border-radius:var(--r-md);font-size:.78rem;font-weight:500;cursor:pointer;margin-top:.35rem;transition:background var(--dur)}
.cr-btn-apply:hover{background:#4338ca}
.cr-status{display:none;font-size:.78rem;padding:.45rem .75rem;border-radius:var(--r-md);text-align:center;margin-top:.25rem}

/* Правая панель — форма */
.cr-form-card{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);overflow:hidden}
.cr-form-head{padding:.875rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.5rem}
.cr-form-title{font-weight:600;font-size:.9375rem;color:var(--text)}
.cr-form-body{padding:1.25rem}
.cr-field-label{display:block;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-m);margin-bottom:.35rem}
.cr-input{width:100%;height:38px;padding:0 .75rem;border:1px solid var(--border-2);border-radius:var(--r-md);font-size:.8125rem;color:var(--text);background:#fff;box-sizing:border-box;transition:border-color var(--dur),box-shadow var(--dur)}
.cr-input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.cr-select{width:100%;height:38px;padding:0 .75rem;border:1px solid var(--border-2);border-radius:var(--r-md);font-size:.8125rem;color:var(--text);background:#fff;box-sizing:border-box;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .6rem center;padding-right:2rem;transition:border-color var(--dur)}
.cr-select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.cr-textarea-field{width:100%;padding:.6rem .75rem;border:1px solid var(--border-2);border-radius:var(--r-md);font-size:.8125rem;color:var(--text);background:#fff;box-sizing:border-box;resize:vertical;font-family:inherit;transition:border-color var(--dur)}
.cr-textarea-field:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.cr-row{display:grid;gap:.75rem;margin-bottom:.875rem}
.cr-row-2{grid-template-columns:1fr 1fr}
.cr-row-31{grid-template-columns:1fr 160px}
.cr-row-13{grid-template-columns:170px 1fr}
.cr-fg{margin-bottom:.875rem}
.cr-actions{display:flex;gap:.75rem;padding-top:1rem;border-top:1px solid var(--border);margin-top:.25rem;flex-wrap:wrap}
.cr-btn-save{height:38px;padding:0 1.25rem;background:#16a34a;color:#fff;border:none;border-radius:var(--r-md);font-size:.8125rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.4rem;transition:background var(--dur)}
.cr-btn-save:hover{background:#15803d}
.cr-btn-clear{height:38px;padding:0 1rem;background:#fff;color:var(--text-2);border:1px solid var(--border-2);border-radius:var(--r-md);font-size:.8125rem;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:.4rem;transition:background var(--dur)}
.cr-btn-clear:hover{background:var(--bg)}
</style>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">📋 Проверка документа</div>
    <div class="page-header-sub">Проверьте данные перед сохранением</div>
  </div>
</div>

<?php if (!empty($data['owner_name'])): ?>
<div class="alert alert-info anim-fade" style="max-width:1280px">
  📌 Документ загружен для: <strong><?= h($data['owner_name']) ?></strong>
</div>
<?php endif; ?>

<div class="cr-wrap anim-fade">

  <!-- ═══════════════════════════════ ЛЕВАЯ КОЛОНКА ═══════════════════════════════ -->
  <div class="cr-left">

    <!-- Документ / превью -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">📎 Документ</div>
        <?php if ($data['filename']): ?>
        <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-secondary btn-sm">
          <?= $isImg ? '🔍 Просмотр' : '⬇ Открыть' ?>
        </a>
        <?php endif; ?>
      </div>
      <div style="padding:.75rem">

        <!-- Форма загрузки файла + drag&drop -->
        <form method="POST" action="<?= SITE_URL ?>/actions/cert_review_upload.php"
              enctype="multipart/form-data" id="cr-upload-form">
          <input type="hidden" name="ptype"          value="<?= h($ptype) ?>">
          <input type="hidden" name="edu_student_id" value="<?= (int)($data['edu_student_id'] ?? 0) ?>">
          <input type="hidden" name="pdf_user_id"    value="<?= (int)($data['user_id'] ?? 0) ?>">
          <input type="file"   name="pdf_file" id="cr-file-input"
                 accept=".pdf,.jpg,.jpeg,.png,.webp" style="display:none"
                 onchange="crFileChosen(this)">
        </form>

        <!-- Drag&drop зона -->
        <div class="cr-upload-zone" id="cr-drop-zone"
             onclick="document.getElementById('cr-file-input').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--blue)'"
             ondragleave="this.style.borderColor=''"
             ondrop="crHandleDrop(event)">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
          </svg>
          <div>Нажмите или перетащите файл</div>
          <div class="cr-upload-sub">PDF, JPG, PNG, WEBP</div>
        </div>

        <!-- Бейдж файла -->
        <?php if ($data['filename']): ?>
        <div class="cr-file-badge" id="cr-file-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          <?= h(basename($data['filename'])) ?>
        </div>
        <?php else: ?>
        <div class="cr-file-badge" id="cr-file-badge" style="display:none"></div>
        <?php endif; ?>

        <!-- Превью -->
        <div class="cr-preview" id="cr-preview" style="margin-top:.75rem;<?= $data['filename'] ? '' : 'display:none' ?>">
          <?php if ($data['filename']): ?>
            <?php if ($isImg): ?>
              <img src="<?= $fileUrl ?>" alt="Документ" id="cr-preview-img">
            <?php else: ?>
              <iframe src="<?= $fileUrl ?>#toolbar=0&navpanes=0&scrollbar=0"
                      id="cr-preview-iframe" title="Просмотр PDF"></iframe>
              <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-secondary btn-sm"
                 style="margin-top:.5rem;width:100%;justify-content:center">⬇ Открыть в новой вкладке</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Статус загрузки -->
        <div id="cr-upload-status" style="display:none;margin-top:.5rem;font-size:.78rem;padding:.4rem .75rem;border-radius:var(--r-md);text-align:center"></div>
      </div>
    </div>

    <!-- Автозаполнение -->
    <div class="cr-auto-card">
      <div class="cr-auto-head">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>
        </svg>
        <span class="cr-auto-title">Автозаполнение</span>
      </div>
      <div class="cr-auto-body">

        <!-- Кнопка Вручную -->
        <div>
          <div class="cr-section-label">Вручную (бесплатно)</div>
          <button type="button" class="cr-btn-manual" onclick="openManualFill()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            Вручную (бесплатно)
          </button>
        </div>

        <?php if ($openrouterKey): ?>
        <!-- Кнопка OpenRouter -->
        <button type="button" class="cr-btn-openrouter" id="btn-openrouter" onclick="autoFillOpenRouter()">
          ⚡ OpenRouter API
        </button>
        <?php endif; ?>

        <hr class="cr-divider">

        <!-- Шаг 1 -->
        <div>
          <div class="cr-step">
            <span class="cr-step-num">1</span>
            <span class="cr-step-text">
              Загрузите документ выше, затем нажмите кнопку — откроется сайт AI в новой вкладке
            </span>
          </div>
          <div class="cr-btn-row">
            <a href="https://gemini.google.com/app?hl=ru" target="_blank" class="cr-btn-ai">
              ✦ Открыть Gemini
            </a>
            <a href="https://claude.ai/" target="_blank" class="cr-btn-ai">
              ✦ Открыть Claude
            </a>
          </div>
        </div>

        <!-- Шаг 2 -->
        <div>
          <div class="cr-step">
            <span class="cr-step-num">2</span>
            <span class="cr-step-text">
              Прикрепите изображение документа и вставьте этот запрос:
            </span>
          </div>
          <button type="button" class="cr-btn-ai" style="width:100%;height:30px;margin-top:.4rem" onclick="copyPrompt()">
            📋 Скопировать запрос
          </button>
        </div>

        <!-- Шаг 3 — вставка ответа -->
        <div>
          <div class="cr-step">
            <span class="cr-step-num">3</span>
            <span class="cr-step-text">
              Скопируйте ответ AI и вставьте сюда:
            </span>
          </div>
          <textarea id="ai-paste-area" class="cr-textarea" rows="4"
                    placeholder='{"title":"...","date":"...","recipient_name":"..."}'></textarea>
          <button type="button" class="cr-btn-apply" onclick="applyPastedJson()">
            ✅ Применить ответ
          </button>
        </div>

        <div id="autofill-status" class="cr-status"></div>
        <div id="prompt-fallback" style="display:none;margin-top:.4rem">
          <div style="font-size:.7rem;color:var(--text-m);margin-bottom:.25rem">Скопируйте вручную (Ctrl+A, Ctrl+C):</div>
          <textarea id="prompt-fallback-text" class="cr-textarea" rows="3"
                    style="font-size:.68rem;cursor:text" readonly></textarea>
        </div>
      </div>
    </div>

  </div><!-- /cr-left -->

  <!-- ═══════════════════════════════ ПРАВАЯ КОЛОНКА ═══════════════════════════════ -->
  <div class="cr-form-card">
    <div class="cr-form-head">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
      <span class="cr-form-title">📄 Данные документа</span>
    </div>
    <div class="cr-form-body">

      <?php
      $hasData = !empty($cv['recipient_name']) || !empty($cv['title']) || !empty($cv['issuer'])
                 || !empty($cv['position']) || !empty($cv['recipient_org']) || !empty($cv['event_name']);
      ?>
      <?php if ($hasData): ?>
      <div class="alert alert-success">✅ Система распознала данные. Проверьте и исправьте при необходимости.</div>
      <?php else: ?>
      <?php endif; ?>

      <form method="POST" action="<?= SITE_URL ?>/actions/cert_save_parsed.php" id="cert-form">
        <input type="hidden" name="filename" value="<?= h($data['filename']) ?>">
        <input type="hidden" name="ptype"    value="<?= h($ptype) ?>">
        <?php if ($ptype === 'teacher'): ?>
          <input type="hidden" name="pdf_user_id"    value="<?= (int)($data['user_id'] ?? 0) ?>">
          <input type="hidden" name="edu_student_id" value="0">
        <?php else: ?>
          <input type="hidden" name="edu_student_id" value="<?= (int)($data['edu_student_id'] ?? 0) ?>">
          <input type="hidden" name="pdf_user_id"    value="0">
        <?php endif; ?>

        <!-- ТИП ДОКУМЕНТА + ДАТА ВЫДАЧИ -->
        <div class="cr-row cr-row-31">
          <div>
            <label class="cr-field-label">Тип документа</label>
            <select name="title" id="f-title" class="cr-select">
              <?= selOpt($docTypes, $cv['title']) ?>
            </select>
          </div>
          <div>
            <label class="cr-field-label">Дата выдачи</label>
            <input type="date" name="issue_date" id="f-date" class="cr-input"
                   value="<?= h($cv['date']) ?>">
          </div>
        </div>

        <!-- ФИО ПОЛУЧАТЕЛЯ -->
        <div class="cr-fg">
          <label class="cr-field-label">ФИО получателя</label>
          <input type="text" name="recipient_name" id="f-recipient" class="cr-input"
                 value="<?= h($cv['recipient_name']) ?>"
                 placeholder="Бубнов Андрей Валерьевич">
        </div>

        <!-- ДОЛЖНОСТЬ -->
        <div class="cr-fg">
          <label class="cr-field-label">Должность</label>
          <input type="text" name="position" id="f-position" class="cr-input"
                 value="<?= h($cv['position']) ?>"
                 placeholder="Преподаватель информатических дисциплин">
        </div>

        <!-- ОРГАНИЗАЦИЯ ПОЛУЧАТЕЛЯ -->
        <div class="cr-fg">
          <label class="cr-field-label">Организация получателя</label>
          <input type="text" name="recipient_org" id="f-recipient-org" class="cr-input"
                 value="<?= h($cv['recipient_org']) ?>"
                 placeholder="КМҚК «Абай Құнанбаев атындағы Сарань жоғары гуманитарлық-техникалық колледжі»">
        </div>

        <!-- ВЫДАВШАЯ ОРГАНИЗАЦИЯ -->
        <div class="cr-fg">
          <label class="cr-field-label">Выдавшая организация</label>
          <input type="text" name="issuer" id="f-issuer" class="cr-input"
                 value="<?= h($cv['issuer']) ?>"
                 placeholder="Қарағанды облысында білім беруді дамытудың оқу-әдістемелік орталығы">
        </div>

        <!-- НАЗВАНИЕ МЕРОПРИЯТИЯ / КОНКУРСА -->
        <div class="cr-fg">
          <label class="cr-field-label">Название мероприятия / конкурса</label>
          <input type="text" name="event_name" id="f-event" class="cr-input"
                 value="<?= h($cv['event_name']) ?>"
                 placeholder="Техникалық және кәсіптік, орта білімнен кейінгі...">
        </div>

        <!-- УРОВЕНЬ + РЕЗУЛЬТАТ -->
        <div class="cr-row cr-row-2">
          <div>
            <label class="cr-field-label">Уровень</label>
            <select name="level" id="f-level" class="cr-select">
              <?= selOpt($levels, $cv['level']) ?>
            </select>
          </div>
          <div>
            <label class="cr-field-label">Результат</label>
            <select name="place" id="f-place" class="cr-select">
              <?= selOpt($results, $cv['place']) ?>
            </select>
          </div>
        </div>

        <!-- НАПРАВЛЕНИЕ / КОМПЕТЕНЦИЯ / НОМИНАЦИЯ -->
        <div class="cr-fg">
          <label class="cr-field-label">Направление / Компетенция / Номинация</label>
          <input type="text" name="nomination" id="f-nomination" class="cr-input"
                 value="<?= h($cv['nomination']) ?>"
                 placeholder="Білім беру">
        </div>

        <!-- НОМЕР ДОКУМЕНТА + ЯЗЫК ДОКУМЕНТА -->
        <div class="cr-row cr-row-2">
          <div>
            <label class="cr-field-label">Номер документа</label>
            <input type="text" name="doc_number" id="f-docnum" class="cr-input"
                   value="<?= h($cv['doc_number']) ?>"
                   placeholder="ОЭБ 00116">
          </div>
          <div>
            <label class="cr-field-label">Язык документа</label>
            <select name="doc_lang" id="f-lang" class="cr-select">
              <?= selOpt($langs, $cv['doc_lang']) ?>
            </select>
          </div>
        </div>

        <!-- ПРИМЕЧАНИЯ -->
        <div class="cr-fg">
          <label class="cr-field-label">Примечания</label>
          <textarea name="notes" id="f-notes" class="cr-textarea-field" rows="2"
                    placeholder="На документе имеется печать и рукописная отметка «Копия верна. Директор В. Темирбулатов»"><?= h($cv['notes']) ?></textarea>
        </div>

        <div class="cr-actions">
          <button type="submit" class="cr-btn-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            Добавить в реестр
          </button>
          <button type="button" class="cr-btn-clear" onclick="clearForm()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6"/><path d="M14 11v6"/>
            </svg>
            Очистить форму
          </button>
          <a href="<?= SITE_URL ?>/achievements.php?tab=certs"
             class="btn btn-secondary" style="margin-left:auto">Отмена</a>
        </div>
      </form>
    </div>
  </div>

</div><!-- /cr-wrap -->

<!-- Модалка ручного заполнения -->
<div id="modal-manual"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:1.5rem;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2)">
    <div style="font-weight:600;font-size:.9375rem;margin-bottom:.5rem">📝 Ручное заполнение</div>
    <p style="font-size:.8125rem;color:var(--text-m);margin:0 0 .75rem">
      Вставьте любой текст из документа — система попытается найти ФИО, дату, организацию:
    </p>
    <textarea id="manual-text" rows="6"
              style="width:100%;box-sizing:border-box;border:1px solid var(--border-2);border-radius:8px;padding:.6rem;font-size:.8125rem;resize:vertical"
              placeholder="Вставьте текст документа здесь..."></textarea>
    <div style="display:flex;gap:.5rem;margin-top:.75rem">
      <button class="btn btn-primary btn-sm" style="flex:1" onclick="applyManualText()">Применить</button>
      <button class="btn btn-secondary btn-sm" onclick="closeManual()">Отмена</button>
    </div>
  </div>
</div>

<script>
// ─── Drag&drop загрузка файла прямо на странице ─────────────────────────────
function crHandleDrop(e) {
    e.preventDefault();
    document.getElementById('cr-drop-zone').style.borderColor = '';
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const inp = document.getElementById('cr-file-input');
    const dt  = new DataTransfer();
    dt.items.add(file);
    inp.files = dt.files;
    crFileChosen(inp);
}

function crFileChosen(input) {
    if (!input.files.length) return;
    const file = input.files[0];
    const maxMb = 10;
    if (file.size > maxMb * 1024 * 1024) {
        crShowUploadStatus('❌ Файл слишком большой (макс. ' + maxMb + ' МБ)', 'error');
        return;
    }
    const allowed = ['pdf','jpg','jpeg','png','webp'];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowed.includes(ext)) {
        crShowUploadStatus('❌ Неверный формат. Разрешены: PDF, JPG, PNG, WEBP', 'error');
        return;
    }
    // Показываем превью локально
    const badge = document.getElementById('cr-file-badge');
    badge.style.display = 'flex';
    badge.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> ${file.name}`;

    if (ext !== 'pdf') {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('cr-preview');
            preview.style.display = '';
            preview.innerHTML = `<img src="${e.target.result}" style="width:100%;border-radius:var(--r-md);border:1px solid var(--border)" alt="Документ">`;
        };
        reader.readAsDataURL(file);
    }

    crShowUploadStatus('⏳ Загружаю и распознаю...', 'info');

    // Сабмитим форму напрямую (работает на http://)
    const form = document.getElementById('cr-upload-form');
    // Небольшая задержка чтобы показать превью перед отправкой
    setTimeout(() => form.submit(), 300);
}

function crShowUploadStatus(msg, type) {
    const el = document.getElementById('cr-upload-status');
    el.style.display = 'block';
    const colors = {
        success: {bg:'#f0fdf4',color:'#166534',border:'#bbf7d0'},
        error:   {bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
        info:    {bg:'#eff6ff',color:'#1e40af',border:'#bfdbfe'},
    };
    const c = colors[type] || colors.info;
    el.style.background = c.bg;
    el.style.color = c.color;
    el.style.border = '1px solid ' + c.border;
    el.textContent = msg;
}

// ─── Промпт для копирования ───────────────────────────────────────────────────
const AI_PROMPT = `Ты специалист по анализу официальных наградных документов системы образования Казахстана.Внимательно прочитай изображение документа (сертификат, диплом, алғыс хат, грамота и т.п.) и извлеки все данные.Верни ТОЛЬКО валидный JSON — без markdown-блоков, без пояснений, без лишних символов:{  "document_type": "Сертификат|Диплом|Алғыс хат|Грамота|Свидетельство|Другое",  "full_name": "ФИО получателя кириллицей — Фамилия Имя Отчество",  "position": "Должность получателя на русском",  "organization": "Организация получателя (полное название)",  "issuer": "Выдавшая организация",  "event_name": "Полное название мероприятия или конкурса",  "level": "Международный|Республиканский|Областной|Городской|Районный|Другое",  "competency": "Направление или компетенция или номинация",  "result": "Участие|I место|II место|III место|Призёр|Организатор / Содействие|Наставник|Другое",  "date": "дата в формате DD.MM.YYYY",  "doc_number": "номер документа если указан, иначе пустая строка",  "language": "Казахский|Русский|Смешанный",  "notes": "важные детали не вошедшие в другие поля, иначе пустая строка"}`;

// ─── Конвертация даты DD.MM.YYYY → YYYY-MM-DD ────────────────────────────────
function toInputDate(raw) {
    if (!raw) return '';
    // уже YYYY-MM-DD
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    // DD.MM.YYYY или DD/MM/YYYY
    const m = raw.match(/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/);
    if (m) return `${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
    return '';
}

// ─── Маппинг уровней из нового промпта → значения <select> ───────────────────
const LEVEL_MAP = {
    'международный':    'international',
    'республиканский':  'national',
    'областной':        'regional',
    'городской':        'city',
    'районный':         'city',
    'колледж':          'college',
    'другое':           '',
};

function normalizeLevel(val) {
    if (!val) return '';
    const lo = val.toLowerCase().trim();
    return LEVEL_MAP[lo] ?? val;
}

// ─── Кнопка «Скопировать запрос» ─────────────────────────────────────────────
function showFallback() {
    const box = document.getElementById('prompt-fallback');
    const ta  = document.getElementById('prompt-fallback-text');
    box.style.display = 'block';
    ta.value = AI_PROMPT;
    ta.focus();
    ta.select();
    showStatus('⚠️ Автокопирование недоступно на HTTP. Скопируйте запрос из поля ниже вручную (Ctrl+A → Ctrl+C).', 'error');
}

function copyPrompt() {
    // execCommand работает на http://, clipboard API — только на https://
    function fallbackCopy() {
        const ta = document.createElement('textarea');
        ta.value = AI_PROMPT;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        return ok;
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(AI_PROMPT)
            .then(() => showStatus('✅ Запрос скопирован! Вставьте в Gemini или Claude вместе с изображением документа.', 'success'))
            .catch(() => {
                fallbackCopy()
                    ? showStatus('✅ Запрос скопирован!', 'success')
                    : showFallback();
            });
    } else {
        fallbackCopy()
            ? showStatus('✅ Запрос скопирован! Вставьте в Gemini или Claude вместе с изображением документа.', 'success')
            : showFallback();
    }
}

// ─── Кнопка «Применить ответ» ─────────────────────────────────────────────────
function applyPastedJson() {
    const raw = document.getElementById('ai-paste-area').value.trim();
    if (!raw) {
        showStatus('⚠️ Сначала вставьте ответ от AI в поле выше', 'error');
        return;
    }
    try {
        // убираем ```json ... ``` если AI добавил
        const clean = raw.replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/```\s*$/i, '').trim();
        const d = JSON.parse(clean);
        fillForm(d);
        document.getElementById('ai-paste-area').value = '';
        showStatus('✅ Данные применены! Проверьте поля и сохраните.', 'success');
    } catch(e) {
        showStatus('❌ Не удалось разобрать JSON. Убедитесь что скопировали полный ответ от AI.', 'error');
    }
}

// ─── Заполнение формы ─────────────────────────────────────────────────────────
function fillForm(d) {
    const map = {
        'f-title':         d.document_type  || d.title       || '',
        'f-date':          toInputDate(d.date),
        'f-recipient':     d.full_name       || d.recipient_name || '',
        'f-position':      d.position        || '',
        'f-recipient-org': d.organization    || d.recipient_org  || '',
        'f-issuer':        d.issuer          || '',
        'f-event':         d.event_name      || '',
        'f-level':         normalizeLevel(d.level),
        'f-place':         d.result          || d.place       || '',
        'f-nomination':    d.competency      || d.nomination  || '',
        'f-docnum':        d.doc_number      || '',
        'f-lang':          d.language        || d.doc_lang    || '',
        'f-notes':         d.notes           || '',
    };

    for (const [id, val] of Object.entries(map)) {
        if (!val) continue;
        const el = document.getElementById(id);
        if (!el) continue;

        if (el.tagName === 'SELECT') {
            let found = false;
            for (const opt of el.options) {
                if (opt.value === val || opt.text.trim() === val.trim()) {
                    el.value = opt.value;
                    found = true;
                    break;
                }
            }
            // если значение не в списке — добавляем новый option
            if (!found && val) {
                const opt = new Option(val, val, true, true);
                el.add(opt);
            }
        } else {
            el.value = val;
        }
    }
}

// ─── Статус-бар ───────────────────────────────────────────────────────────────
function showStatus(msg, type) {
    const el = document.getElementById('autofill-status');
    el.style.display = 'block';
    const colors = {
        success: {bg:'#f0fdf4', color:'#166534', border:'#bbf7d0'},
        error:   {bg:'#fef2f2', color:'#991b1b', border:'#fecaca'},
        info:    {bg:'#eff6ff', color:'#1e40af', border:'#bfdbfe'},
    };
    const c = colors[type] || colors.info;
    el.style.background = c.bg;
    el.style.color      = c.color;
    el.style.border     = '1px solid ' + c.border;
    el.textContent      = msg;
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.display = 'none'; }, 7000);
}

// ─── Кнопка «Очистить форму» ─────────────────────────────────────────────────
function clearForm() {
    if (!confirm('Очистить все поля формы?')) return;
    document.querySelectorAll('#cert-form input[type=text], #cert-form input[type=date], #cert-form textarea')
        .forEach(el => el.value = '');
    document.querySelectorAll('#cert-form select')
        .forEach(el => el.selectedIndex = 0);
    showStatus('Форма очищена.', 'info');
}

// ─── Модалка «Вручную (бесплатно)» ───────────────────────────────────────────
function openManualFill() {
    document.getElementById('modal-manual').style.display = 'flex';
    setTimeout(() => document.getElementById('manual-text').focus(), 50);
}
function closeManual() {
    document.getElementById('modal-manual').style.display = 'none';
}
function applyManualText() {
    const text = document.getElementById('manual-text').value.trim();
    if (!text) { closeManual(); return; }

    // Пытаемся распарсить как JSON (если вдруг вставили ответ AI сюда)
    try {
        const clean = text.replace(/^```json\s*/i,'').replace(/```\s*$/i,'').trim();
        const d = JSON.parse(clean);
        fillForm(d);
        closeManual();
        showStatus('✅ Данные из JSON применены!', 'success');
        return;
    } catch(e) {}

    // Иначе — эвристика по тексту
    const dm = text.match(/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/);
    if (dm) {
        document.getElementById('f-date').value =
            `${dm[3]}-${dm[2].padStart(2,'0')}-${dm[1].padStart(2,'0')}`;
    }
    closeManual();
    showStatus('Текст обработан. Проверьте и дополните поля вручную.', 'info');
}
document.getElementById('modal-manual').addEventListener('click', function(e) {
    if (e.target === this) closeManual();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeManual();
});

<?php if ($openrouterKey): ?>
async function autoFillOpenRouter() {
    const btn = document.getElementById('btn-openrouter');
    btn.disabled = true;
    btn.textContent = '⏳ Обрабатываю...';
    showStatus('⏳ Отправляю запрос в OpenRouter...', 'info');
    try {
        const resp = await fetch('<?= SITE_URL ?>/actions/cert_autofill_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({filename: '<?= h($data['filename']) ?>'})
        });
        const json = await resp.json();
        if (json.error) throw new Error(json.error);
        fillForm(json);
        showStatus('✅ Данные получены от OpenRouter!', 'success');
    } catch(e) {
        showStatus('❌ Ошибка OpenRouter: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '⚡ OpenRouter API';
    }
}
<?php endif; ?>

// ─── Предзаполнение из данных OCR сессии ─────────────────────────────────────
(function() {
    const d = <?= json_encode($cv) ?>;
    if (d.title || d.recipient_name || d.issuer) fillForm(d);
})();
</script>

<?php require_once 'includes/footer.php'; ?>