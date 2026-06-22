<?php
// hr/templates/modals.php — модальные окна и контейнер уведомлений
$currentUrl = $_SERVER['REQUEST_URI'] ?? 'index.php';
?>
<!-- ═══════════════════ MODAL: Добавить/Редактировать ═════════ -->
<div class="modal-overlay" id="modalRecord">
  <div class="modal">
    <form method="POST" action="actions.php" enctype="multipart/form-data" id="recordForm">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentUrl) ?>">
      <input type="hidden" name="record_id" id="fRecordId">
      <input type="hidden" name="student_id" id="fStudentId">

      <div class="modal-header">
        <span class="modal-title" id="modalTitle">Добавить запись</span>
        <button type="button" class="modal-close" onclick="closeModal('modalRecord')">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div class="modal-body">

        <!-- Студент (режим добавления) -->
        <div id="studentAddMode">
          <div class="form-row">
            <label class="form-label">Поиск студента <span class="required-mark">*</span></label>
            <div class="search-wrapper">
              <input type="text" id="fStudentSearch" class="form-control" placeholder="Введите ФИО..." autocomplete="off">
              <div class="search-results" id="searchResults"></div>
            </div>
          </div>
          <div class="form-row" style="margin-top:var(--space-4)">
            <label class="form-label">Выбранный студент</label>
            <div id="selectedStudentDisplay" style="font-size:var(--text-sm);color:var(--color-text-muted)">Не выбран</div>
          </div>
        </div>

        <!-- Студент (режим редактирования) -->
        <div id="studentEditMode" style="display:none">
          <div class="form-row">
            <label class="form-label">Студент</label>
            <div id="studentNameDisplay" style="font-weight:600;color:var(--color-text);padding:var(--space-2) 0"></div>
          </div>
        </div>

        <!-- Статус -->
        <div class="form-row">
          <label class="form-label">Статус занятости <span style="color:var(--color-error)">*</span></label>
          <select class="form-control" id="fStatus" name="status" onchange="toggleEmployedFields()">
            <option value="employed">Трудоустроен</option>
            <option value="unemployed">Не трудоустроен</option>
            <option value="studying">Продолжает учёбу</option>
            <option value="decree">В декрете</option>
            <option value="military">Военная служба</option>
            <option value="relocation">Выезд на ПМЖ</option>
            <option value="other">Прочее</option>
            <option value="unknown">Неизвестно</option>
          </select>
        </div>

        <!-- Поля трудоустройства -->
        <div id="employedFields">
          <div class="form-grid">
            <div class="form-row">
              <label class="form-label">Организация <span class="required-mark">*</span></label>
              <input type="text" class="form-control" id="fEmployerName" name="employer_name" placeholder="ТОО 'Название'" maxlength="255" data-validate="employment-text" autocomplete="off">
            </div>
            <div class="form-row">
              <label class="form-label">Должность <span class="required-mark">*</span></label>
              <input type="text" class="form-control" id="fPosition" name="position" placeholder="Программист" maxlength="255" data-validate="employment-text" autocomplete="off">
            </div>
            <div class="form-row">
              <label class="form-label">Дата трудоустройства <span class="required-mark">*</span></label>
              <input type="date" class="form-control" id="fEmploymentDate" name="employment_date">
            </div>
            <div class="form-row">
              <label class="form-label">Тип занятости <span class="required-mark">*</span></label>
              <select class="form-control" id="fEmploymentType" name="employment_type">
                <option value="full_time">Полная занятость</option>
                <option value="part_time">Частичная занятость</option>
                <option value="contract">Договор/Контракт</option>
                <option value="self_employed">Самозанятый</option>
                <option value="other">Прочее</option>
              </select>
            </div>
            <div class="form-row full">
              <label class="checkbox-row">
                <input type="checkbox" id="fIsBySpec" name="is_by_specialty" value="1" checked>
                <span>Работает по специальности</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Примечание -->
        <div class="form-row">
          <label class="form-label">Примечание</label>
          <textarea class="form-control" id="fNotes" name="notes" placeholder="Дополнительная информация..." maxlength="2000" data-validate="notes-text"></textarea>
        </div>

        <!-- Справки -->
        <div id="docsSection" style="display:none">
          <div id="docsSectionTitle" style="font-size:var(--text-sm);font-weight:600;color:var(--color-text);margin-bottom:var(--space-3)">
            Справка о трудоустройстве
          </div>
          <div class="docs-list" id="docsList"></div>
          <div class="upload-area" id="uploadArea" style="margin-top:var(--space-3)">
            <input type="file" id="fileInput" name="documents[]" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx" multiple>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" style="margin:0 auto var(--space-2)"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <p id="uploadPrimaryText" style="font-size:var(--text-sm);color:var(--color-text-muted)">Нажмите или перетащите файл</p>
            <p id="uploadHintText" style="font-size:var(--text-xs);color:var(--color-text-faint);margin-top:4px">Справка о трудоустройстве. PDF, JPG, PNG, DOC — до 10 МБ. Файл отправится после нажатия «Сохранить».</p>
            <div id="selectedFilesInfo" style="font-size:var(--text-xs);color:var(--color-text);margin-top:var(--space-2)"></div>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalRecord')">Отмена</button>
        <button type="submit" class="btn btn-primary" id="btnSaveRecord">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Сохранить
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════ MODAL: Документы ════════════════════ -->
<div class="modal-overlay" id="modalDocs">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <span class="modal-title" id="docsModalTitle">Справки о трудоустройстве</span>
      <button type="button" class="modal-close" onclick="closeModal('modalDocs')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="docs-list" id="docsViewList">
        <div style="text-align:center;color:var(--color-text-muted);padding:var(--space-8)">Выберите запись</div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('modalDocs')">Закрыть</button>
    </div>
  </div>
</div>


<!-- ═══════════════════ MODAL: Справка ═══════════════════════ -->
<div class="modal-overlay" id="modalHelp">
  <div class="modal help-modal">
    <div class="modal-header">
      <span class="modal-title">Справка по HR-Аналитике</span>
      <button type="button" class="modal-close" onclick="closeModal('modalHelp')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body help-body">
      <div class="help-intro">
        <div class="help-intro-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 1 1 5.82 1c-.45.78-1.16 1.2-1.91 1.8-.7.56-1 1.1-1 2.2"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
          <div class="help-intro-title">Как пользоваться модулем</div>
          <div class="help-intro-text">Здесь коротко описано, что делать обычному пользователю. Полное описание можно открыть в PDF.</div>
        </div>
      </div>

      <div class="help-steps">
        <div class="help-step">
          <span class="help-step-number">1</span>
          <div>
            <strong>Найдите студента</strong>
            <p>Используйте поиск, группу, специальность, год выпуска или статус. Если записей много, переходите по страницам снизу таблицы.</p>
          </div>
        </div>
        <div class="help-step">
          <span class="help-step-number">2</span>
          <div>
            <strong>Добавьте или измените запись</strong>
            <p>Нажмите «Добавить запись» или кнопку редактирования в строке студента. Выберите статус занятости и заполните нужные поля.</p>
          </div>
        </div>
        <div class="help-step">
          <span class="help-step-number">3</span>
          <div>
            <strong>Прикрепите документ, если он нужен</strong>
            <p>Для трудоустройства, учебы, декрета и военной службы появится блок с нужной справкой. Для «Не трудоустроен» и «Неизвестно» справка не требуется.</p>
          </div>
        </div>
        <div class="help-step">
          <span class="help-step-number">4</span>
          <div>
            <strong>Сохраните и проверьте статистику</strong>
            <p>После сохранения запись появится в таблице, а карточки статистики пересчитаются по выбранной выборке.</p>
          </div>
        </div>
        <div class="help-step">
          <span class="help-step-number">5</span>
          <div>
            <strong>Выгрузите отчет при необходимости</strong>
            <p>Кнопки Excel, CSV и Word выгружают текущую отфильтрованную выборку.</p>
          </div>
        </div>
      </div>

      <div class="help-note">
        <strong>Важно:</strong> для статуса «Трудоустроен» нельзя оставлять пустыми организацию, должность, дату трудоустройства и тип занятости.
      </div>
    </div>
    <div class="modal-footer">
      <a class="btn btn-primary" href="assets/docs/hr_help.pdf" target="_blank" rel="noopener">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>
        Открыть PDF
      </a>
      <button type="button" class="btn btn-outline" onclick="closeModal('modalHelp')">Закрыть</button>
    </div>
  </div>
</div>

<!-- ═══════════════════ TOASTS ════════════════════════════════ -->
<div class="toast-container" id="toastContainer"></div>
