<?php
/**
 * tabs.php — Контент вкладок: Журнал, Рапортичка, Справки, Аналитика
 * Подключается из layout.php — все переменные уже определены в index.php
 */
?>

<?php if ($noGroupsWarning): ?>
<!-- Нет привязанных групп -->
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;gap:var(--space-4);text-align:center">
  <div style="width:72px;height:72px;border-radius:var(--radius-xl);background:var(--color-warning-highlight);display:flex;align-items:center;justify-content:center;color:var(--color-warning)">
    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
  </div>
  <div>
    <div style="font-size:var(--text-xl);font-weight:700;color:var(--color-text);margin-bottom:var(--space-2)">Группы не назначены</div>
    <div style="color:var(--color-text-muted);max-width:420px;line-height:1.6">
      Вы ещё не назначены куратором ни одной группы.<br>
      Обратитесь к администратору для привязки вашего аккаунта к группе<br>
      через поле <code>curator_id</code> в таблице <code>edu_groups</code>.
    </div>
  </div>
</div>
<?php else: ?>

<!-- Заголовок страницы -->
<div class="page-header">
  <div>
    <p class="page-subtitle">
      Группа <strong><?= htmlspecialchars($groupInfo['name']) ?></strong>
      · <?= htmlspecialchars($groupInfo['specialty'] ?? '') ?>
      · <?= date('d.m.Y', strtotime($selectedDate)) ?>
      <?php if ($isAdmin && !empty($groupInfo['curator_name'])): ?>
      · <span style="color:var(--color-primary)">Куратор: <?= htmlspecialchars($groupInfo['curator_name']) ?></span>
      <?php endif ?>
    </p>
  </div>
</div>

<!-- Фильтры -->
<div class="filters-bar">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:var(--space-4);align-items:flex-end;width:100%">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
    <div class="form-group">
      <label class="form-label">
        Группа
        <?php if ($isTeacher): ?>
        <span style="font-size:10px;color:var(--color-text-faint);font-weight:400">(ваши группы)</span>
        <?php endif ?>
      </label>
      <select name="group" class="form-control" onchange="this.form.submit()">
        <?php foreach ($groups as $gid => $g): ?>
        <option value="<?= $gid ?>" <?= $selectedGrp == $gid ? 'selected' : '' ?>>
          <?= htmlspecialchars($g['name']) ?>
          <?php if ($isAdmin && !empty($g['curator_name'])): ?> — <?= htmlspecialchars($g['curator_name']) ?><?php endif ?>
        </option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Дата</label>
      <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
    </div>
    <div class="form-group">
      <label class="form-label">Учебный год</label>
      <select name="year" class="form-control">
        <option selected>2025–2026</option>
        <option>2024–2025</option>
      </select>
    </div>
  </form>
</div>

<!-- KPI-карточки -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon blue">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <div class="kpi-value"><?= $total ?></div>
    <div class="kpi-label">Всего студентов</div>
    <div class="kpi-pct">Группа <?= htmlspecialchars($groupInfo['name']) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon green">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    </div>
    <div class="kpi-value" id="kpi-present"><?= $present ?></div>
    <div class="kpi-label">Присутствуют</div>
    <div class="prog-bar"><div class="prog-fill green" id="kpi-present-bar" style="width:<?= $pct ?>%"></div></div>
    <div class="kpi-pct" id="kpi-present-pct"><?= $pct ?>% от списка</div>
  </div>
  <?php $absPct = $total > 0 ? round($absent/$total*100) : 0 ?>
  <div class="kpi-card">
    <div class="kpi-icon red">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    </div>
    <div class="kpi-value" id="kpi-absent"><?= $absent ?></div>
    <div class="kpi-label">Отсутствуют</div>
    <div class="prog-bar"><div class="prog-fill red" id="kpi-absent-bar" style="width:<?= $absPct ?>%"></div></div>
    <div class="kpi-pct" id="kpi-absent-pct"><?= $absPct ?>% без причины</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon gold">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
    </div>
    <div class="kpi-value" id="kpi-excused"><?= $excused ?></div>
    <div class="kpi-label">Уваж. причина</div>
    <div class="kpi-pct">Справки / заявления</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon yellow">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div class="kpi-value" id="kpi-late"><?= $late ?></div>
    <div class="kpi-label">Опоздали</div>
    <div class="kpi-pct">Зафиксировано сегодня</div>
  </div>
</div>

<?php if ($absPct >= 25): ?>
<div class="alert alert-warn">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <div>
    <div class="alert-title">Высокий уровень пропусков — <?= $absPct ?>%</div>
    <div class="alert-desc">Посещаемость группы ниже нормы (75%). Рекомендуется уведомить куратора группы и сформировать рапортичку.</div>
  </div>
</div>
<?php endif ?>

<!-- Вкладки -->
<div class="tabs">
  <button class="tab-btn <?= $activeTab==='journal'   ? 'active' : '' ?>" onclick="switchTab('journal')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
    Журнал посещаемости
  </button>
  <button class="tab-btn <?= $activeTab==='report'    ? 'active' : '' ?>" onclick="switchTab('report')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    Рапортичка
    <span class="tab-badge"><?= ['week'=>'Неделя','month'=>'Месяц','semester'=>'Семестр','year'=>'Год'][$reportPeriod] ?? 'Месяц' ?></span>
  </button>
  <button class="tab-btn <?= $activeTab==='documents' ? 'active' : '' ?>" onclick="switchTab('documents')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    Справки
    <?php if ($docsPending > 0): ?>
    <span class="tab-badge" style="background:var(--color-warning-highlight);color:var(--color-warning)"><?= $docsPending ?></span>
    <?php elseif (!empty($documents)): ?>
    <span class="tab-badge"><?= count($documents) ?></span>
    <?php endif ?>
  </button>
  <button class="tab-btn <?= $activeTab==='analytics' ? 'active' : '' ?>" onclick="switchTab('analytics')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    Аналитика
  </button>
  <button class="tab-btn <?= $activeTab==='criteria' ? 'active' : '' ?>" onclick="switchTab('criteria')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    Отчёт по критериям
  </button>
</div>

    <!-- ══════════════════ ТАБ 1: ЖУРНАЛ ══════════════════════ -->
    <div id="tab-journal" class="tab-panel <?= $activeTab==='journal' ? 'active' : '' ?>">
      <div>
        <div class="card">
          <div class="card-header">
            <span class="card-title">
              <?php
                $ruDays = ['Monday'=>'Понедельник','Tuesday'=>'Вторник','Wednesday'=>'Среда','Thursday'=>'Четверг','Friday'=>'Пятница','Saturday'=>'Суббота','Sunday'=>'Воскресенье'];
                $ruDay = $ruDays[date('l', strtotime($selectedDate))] ?? date('l', strtotime($selectedDate));
              ?>
              Журнал — <?= date('d.m.Y', strtotime($selectedDate)) ?> (<?= $ruDay ?>)
            </span>
            <div style="display:flex;gap:.5rem">
              <button class="btn btn-outline btn-sm" onclick="markAll('present')">✓ Все присутствуют</button>
              <button class="btn btn-outline btn-sm" onclick="markAll('absent')">✗ Все отсутствуют</button>
              <button class="btn btn-primary btn-sm" id="saveBtn" onclick="saveAttendance()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Сохранить журнал
              </button>
            </div>
          </div>
          <div class="table-wrap">
            <table class="data-table" id="journalTable">
              <thead>
                <tr>
                  <th style="width:36px">#</th>
                  <th>Фамилия</th>
                  <th>Имя</th>
                  <th>Отчество</th>
                  <th>Группа</th>
                  <th>ИИН</th>
                  <th>Статус</th>
                  <th>Вход/Выход в колледж</th>
                  <th>Часов пропущено</th>
                  <th>Причина</th>
                  <th style="width:140px">Действие</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $statusLabels = ['present'=>'Присутствует','absent'=>'Отсутствует','excused'=>'Уваж. причина','late'=>'Опоздал'];
                $statusCls    = ['present'=>'badge-present','absent'=>'badge-absent','excused'=>'badge-excused','late'=>'badge-late'];
                foreach ($students as $i => $st):
                ?>
                <tr data-id="<?= $st['id'] ?>" data-status="<?= $st['status'] ?>">
                  <td style="color:var(--color-text-faint)"><?= $i+1 ?></td>
                  <td style="font-weight:600"><?= htmlspecialchars($st['surname']) ?></td>
                  <td><?= htmlspecialchars($st['first_name']) ?></td>
                  <td style="color:var(--color-text-muted)"><?= htmlspecialchars($st['patronymic']) ?></td>
                  <td><span style="font-size:var(--text-xs);background:var(--color-primary-highlight);color:var(--color-primary);padding:2px 8px;border-radius:var(--radius-full);white-space:nowrap"><?= htmlspecialchars($st['group_name'] ?? '') ?></span></td>
                  <td style="font-family:monospace;color:var(--color-text-muted)"><?= $st['iin'] ?></td>
                  <td>
                    <span class="badge <?= $statusCls[$st['status']] ?> status-badge-<?= $st['id'] ?>">
                      <?= $statusLabels[$st['status']] ?>
                    </span>
                  </td>
                  <?php
                    $qr = $qrActions[$st['iin']] ?? null;
                    $entryTime = isset($qr['entry_time']) ? date('H:i', strtotime($qr['entry_time'])) : null;
                    $exitTime  = isset($qr['exit_time'])  ? date('H:i', strtotime($qr['exit_time']))  : null;
                  ?>
                  <td style="white-space:nowrap">
                    <?php if ($entryTime || $exitTime): ?>
                      <div style="display:flex;flex-direction:row;gap:6px;flex-wrap:wrap">
                        <?php if ($entryTime): ?>
                          <span style="display:inline-flex;align-items:center;gap:5px;background:#14532d;color:#86efac;font-size:11px;font-weight:600;padding:3px 8px;border-radius:6px;width:fit-content">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="7 17 17 7"/><polyline points="7 7 17 7 17 17"/></svg>
                            Вход <span style="font-weight:400;opacity:.85"><?= $entryTime ?></span>
                          </span>
                        <?php else: ?>
                          <span style="display:inline-flex;align-items:center;gap:5px;background:var(--color-bg-subtle,#1e293b);color:var(--color-text-faint);font-size:11px;padding:3px 8px;border-radius:6px;width:fit-content;opacity:.5">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="7 17 17 7"/><polyline points="7 7 17 7 17 17"/></svg>
                            Вход —
                          </span>
                        <?php endif ?>
                        <?php if ($exitTime): ?>
                          <span style="display:inline-flex;align-items:center;gap:5px;background:#4c0519;color:#fca5a5;font-size:11px;font-weight:600;padding:3px 8px;border-radius:6px;width:fit-content">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="17 7 7 17"/><polyline points="17 17 7 17 7 7"/></svg>
                            Выход <span style="font-weight:400;opacity:.85"><?= $exitTime ?></span>
                          </span>
                        <?php else: ?>
                          <span style="display:inline-flex;align-items:center;gap:5px;background:var(--color-bg-subtle,#1e293b);color:var(--color-text-faint);font-size:11px;padding:3px 8px;border-radius:6px;width:fit-content;opacity:.5">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="17 7 7 17"/><polyline points="17 17 7 17 7 7"/></svg>
                            Выход —
                          </span>
                        <?php endif ?>
                      </div>
                    <?php else: ?>
                      <span style="color:var(--color-text-faint);font-size:var(--text-xs)">Нет данных</span>
                    <?php endif ?>
                  </td>
                  <td>
                    <input type="number" min="0" max="8" value="<?= $st['hours_missed'] ?>"
                           class="form-control" style="width:64px;padding:4px 8px;font-size:var(--text-xs)"
                           id="hours-<?= $st['id'] ?>"
                           oninput="markUnsaved()">
                  </td>
                  <td>
                    <select class="form-control" style="min-width:unset;padding:4px 8px;font-size:var(--text-xs)" id="reason-<?= $st['id'] ?>" onchange="markUnsaved()">
                      <option value="">— не указано —</option>
                      <?php foreach ($reasons as $r): ?>
                      <option value="<?= $r['id'] ?>" <?= $st['reason_id'] == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name_ru']) ?></option>
                      <?php endforeach ?>
                    </select>
                  </td>
                  <td>
                    <select class="form-control" id="status-<?= $st['id'] ?>" style="padding:4px 8px;font-size:var(--text-xs);min-width:unset"
                            onchange="updateStatus(this, <?= $st['id'] ?>)">
                      <option value="present" <?= $st['status']==='present' ? 'selected':'' ?>>Присутствует</option>
                      <option value="absent"  <?= $st['status']==='absent'  ? 'selected':'' ?>>Отсутствует</option>
                      <option value="excused" <?= $st['status']==='excused' ? 'selected':'' ?>>Уваж. причина</option>
                      <option value="late"    <?= $st['status']==='late'    ? 'selected':'' ?>>Опоздал</option>
                    </select>
                  </td>
                </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div><!-- /tab-journal -->

    <!-- ══════════════════ ТАБ 2: РАПОРТИЧКА ══════════════════ -->
    <div id="tab-report" class="tab-panel <?= $activeTab==='report' ? 'active' : '' ?>">

      <!-- Фильтры рапортички -->
      <form method="get" class="rap-filters">
          <input type="hidden" name="tab"   value="report">
          <input type="hidden" name="group" value="<?= $selectedGrp ?>">
          <input type="hidden" name="date"  value="<?= htmlspecialchars($selectedDate) ?>">

          <div class="form-group">
            <label class="form-label">Период</label>
            <select name="period" class="form-control" style="min-width:130px" onchange="this.form.submit()">
              <option value="week"     <?= $reportPeriod==='week'     ?'selected':'' ?>>Текущая неделя</option>
              <option value="month"    <?= $reportPeriod==='month'    ?'selected':'' ?>>Месяц</option>
              <option value="semester" <?= $reportPeriod==='semester' ?'selected':'' ?>>Семестр</option>
              <option value="year"     <?= $reportPeriod==='year'     ?'selected':'' ?>>Учебный год</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Месяц</label>
            <input type="month" name="month" class="form-control"
                   value="<?= htmlspecialchars($reportMonth) ?>" onchange="this.form.submit()">
          </div>

          <div style="display:flex;gap:var(--space-2);align-items:flex-end;margin-left:auto;flex-wrap:wrap">
            <button type="button" class="btn btn-outline btn-sm" onclick="exportRapCSV()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              CSV
            </button>
            <a class="btn btn-primary btn-sm"
               href="export_vedomost.php?group_id=<?= $selectedGrp ?>&date_from=<?= urlencode($rapDateFrom) ?>&date_to=<?= urlencode($rapDateTo) ?>&course=<?= urlencode((string)($groupInfo['course'] ?? '')) ?>&period_label=<?= urlencode(date('m.Y', strtotime($rapDateFrom))) ?>&semester=<?= urlencode($reportPeriod === 'semester' ? '?' : '') ?>"
               download>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Сводная ведомость (.docx)
            </a>
          </div>
        </form>
      <!-- /rap-filters -->

      <!-- Шапка периода -->
      <div class="card" style="margin-bottom:var(--space-6)">
        <div class="card-header" style="flex-wrap:wrap;gap:var(--space-3)">
          <span class="card-title">
            Рапортичка — <?= htmlspecialchars($groupInfo['name']) ?>
            &nbsp;·&nbsp;
            <?php
              $periodLabels = ['week'=>'Неделя','month'=>'Месяц','semester'=>'Семестр','year'=>'Учебный год'];
              echo $periodLabels[$reportPeriod] ?? 'Месяц';
            ?>
            &nbsp;·&nbsp;
            <?= date('d.m.Y', strtotime($rapDateFrom)) ?>
            <?php if ($rapDateFrom !== $rapDateTo): ?>
              — <?= date('d.m.Y', strtotime($rapDateTo)) ?>
            <?php endif ?>
          </span>
          <!-- Сводные бейджи -->
          <div style="display:flex;gap:var(--space-2);flex-wrap:wrap">
            <span class="badge badge-absent">Пропусков (н/уч): <?= $rapGroupAbsH ?> ч.</span>
            <span class="badge badge-excused">Уваж.: <?= $rapGroupExcH ?> ч.</span>
            <span class="badge badge-late">Опозданий: <?= $rapGroupLateH ?> ч.</span>
            <span class="badge" style="background:var(--color-success-highlight);color:var(--color-success)">
              Ср. посещ.: <?= $rapGroupAvgPct ?>%
            </span>
          </div>
        </div>

        <div class="table-wrap" style="padding:var(--space-3) var(--space-4) var(--space-4)">
          <table class="rap-table" id="rapTable">
            <thead>
              <!-- Строка с месяцами (для группировки при длинных периодах) -->
              <?php
                $monthGroups = [];
                foreach ($rapDates as $dt) {
                    $mk = date('Y-m', strtotime($dt));
                    if (!isset($monthGroups[$mk])) $monthGroups[$mk] = 0;
                    $monthGroups[$mk]++;
                }
                if (count($monthGroups) > 1):
              ?>
              <tr>
                <th class="col-name" rowspan="1"></th>
                <?php foreach ($monthGroups as $mk => $cnt): ?>
                <th colspan="<?= $cnt ?>" style="text-align:center;background:var(--color-primary-highlight);color:var(--color-primary)">
                  <?= date('M Y', strtotime($mk.'-01')) ?>
                </th>
                <?php endforeach ?>
                <th colspan="4">Итого</th>
              </tr>
              <?php endif ?>

              <!-- Строка с числами -->
              <tr>
                <th class="col-name">ФИО студента</th>
                <?php foreach ($rapDates as $dt):
                  $dow  = (int)date('N', strtotime($dt));
                  $isWe = $dow >= 6;
                  $dayN = (int)date('d', strtotime($dt));
                  $short = ['','Пн','Вт','Ср','Чт','Пт','Сб','Вс'][$dow];
                ?>
                <th class="<?= $isWe ? 'rap-th-weekend' : '' ?>" title="<?= date('d.m.Y', strtotime($dt)) ?> (<?= $short ?>)">
                  <?= $dayN ?>
                  <?php if (count($rapDates) <= 14): ?>
                  <div style="font-size:9px;font-weight:400;color:var(--color-text-faint)"><?= $short ?></div>
                  <?php endif ?>
                </th>
                <?php endforeach ?>
                <th class="rap-col-total" style="min-width:40px">н/уч</th>
                <th class="rap-col-total" style="min-width:40px">уваж</th>
                <th class="rap-col-total" style="min-width:40px">оп</th>
                <th class="rap-col-total" style="min-width:46px">%</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $cellSymbols = ['present'=>'', 'absent'=>'н', 'excused'=>'у', 'late'=>'оп'];
              $cellCls     = [
                  'present' => 'rap-cell-present',
                  'absent'  => 'rap-cell-absent',
                  'excused' => 'rap-cell-excused',
                  'late'    => 'rap-cell-late',
              ];

              // Счётчики для итоговой строки группы
              $colAbsent  = array_fill_keys($rapDates, 0);
              $colExcused = array_fill_keys($rapDates, 0);
              $colLate    = array_fill_keys($rapDates, 0);

              foreach ($students as $i => $st):
                $sid   = $st['id'];
                $tots  = $rapTotals[$sid];
                $pctCls = $tots['pct'] >= 85 ? 'rap-pct-good' : ($tots['pct'] >= 75 ? 'rap-pct-warn' : 'rap-pct-bad');
              ?>
              <tr>
                <td class="col-name">
                  <span style="font-weight:600;font-size:11px">
                    <?= htmlspecialchars($st['surname']) ?>
                    <?= mb_strtoupper(mb_substr($st['first_name'],0,1)) ?>.<?= $st['patronymic'] ? mb_strtoupper(mb_substr($st['patronymic'],0,1)).'.' : '' ?>
                  </span>
                </td>
                <?php foreach ($rapDates as $dt):
                  $dow  = (int)date('N', strtotime($dt));
                  $isWe = $dow >= 6;

                  if ($isWe) {
                    // Выходной — серая ячейка
                    echo '<td class="rap-cell-weekend">—</td>';
                    continue;
                  }
                  if ($dt > $todayStr) {
                    echo '<td class="rap-cell-future"></td>';
                    continue;
                  }

                  if (!isset($rapData[$sid][$dt])) {
                    // Нет записи в БД = присутствовал (по умолчанию)
                    echo '<td class="rap-cell-present" title="' . $dt . '">·</td>';
                    continue;
                  }

                  $rec = $rapData[$sid][$dt];
                  $st2 = $rec['status'];
                  $sym = $cellSymbols[$st2] ?? '';
                  $cls = $cellCls[$st2]     ?? '';
                  $hrs = $rec['hours'];

                  // Считаем колонку
                  if ($st2 === 'absent')  $colAbsent[$dt]++;
                  if ($st2 === 'excused') $colExcused[$dt]++;
                  if ($st2 === 'late')    $colLate[$dt]++;

                  $title = htmlspecialchars(date('d.m.Y', strtotime($dt)) . ' — ' . ['present'=>'Присутствует','absent'=>'Отсутствует','excused'=>'Уваж. причина','late'=>'Опоздал'][$st2] . ($hrs ? " ($hrs ч.)" : ''));
                  echo "<td class=\"$cls\" title=\"$title\">$sym" . ($hrs && $st2 !== 'present' ? "<sup style='font-size:8px'>$hrs</sup>" : "") . "</td>";
                endforeach ?>
                <td class="rap-col-total" style="color:var(--color-error)"><?= $tots['absent_h']  ?: '—' ?></td>
                <td class="rap-col-total" style="color:var(--color-primary)"><?= $tots['excused_h'] ?: '—' ?></td>
                <td class="rap-col-total" style="color:var(--color-warning)"><?= $tots['late_h']    ?: '—' ?></td>
                <td class="rap-col-total <?= $pctCls ?>"><?= $tots['pct'] ?>%</td>
              </tr>
              <?php endforeach ?>

              <!-- Итоговая строка: сколько пропускающих в каждый день -->
              <?php if (count($students) > 0): ?>
              <tr class="rap-row-total">
                <td class="col-name" style="font-weight:700;font-size:11px;text-align:left">Итого по группе</td>
                <?php foreach ($rapDates as $dt):
                  $dow  = (int)date('N', strtotime($dt));
                  $isWe = $dow >= 6;
                  if ($isWe) { echo '<td class="rap-cell-weekend">—</td>'; continue; }
                  if ($dt > $todayStr) { echo '<td></td>'; continue; }
                  $ab = $colAbsent[$dt];
                  $ex = $colExcused[$dt];
                  $lt = $colLate[$dt];
                  $parts = [];
                  if ($ab) $parts[] = "<span style='color:var(--color-error)'>$ab</span>";
                  if ($ex) $parts[] = "<span style='color:var(--color-primary)'>$ex</span>";
                  if ($lt) $parts[] = "<span style='color:var(--color-warning)'>$lt</span>";
                  echo '<td>' . (implode('/', $parts) ?: '<span style="color:var(--color-text-faint)">✓</span>') . '</td>';
                endforeach ?>
                <td class="rap-col-total" style="color:var(--color-error)"><?= $rapGroupAbsH  ?: '—' ?></td>
                <td class="rap-col-total" style="color:var(--color-primary)"><?= $rapGroupExcH  ?: '—' ?></td>
                <td class="rap-col-total" style="color:var(--color-warning)"><?= $rapGroupLateH ?: '—' ?></td>
                <td class="rap-col-total <?= $rapGroupAvgPct>=85?'rap-pct-good':($rapGroupAvgPct>=75?'rap-pct-warn':'rap-pct-bad') ?>"><?= $rapGroupAvgPct ?>%</td>
              </tr>
              <?php endif ?>

              <?php if (empty($students)): ?>
              <tr><td colspan="<?= count($rapDates)+5 ?>" style="text-align:center;padding:var(--space-8);color:var(--color-text-faint)">
                Нет студентов в группе
              </td></tr>
              <?php endif ?>
            </tbody>
          </table>

          <!-- Легенда -->
          <div style="margin-top:var(--space-4);display:flex;flex-wrap:wrap;gap:var(--space-5);font-size:var(--text-xs);color:var(--color-text-muted)">
            <span><span class="badge badge-absent" style="font-size:10px">н</span> — отсутствует (н/уч)</span>
            <span><span class="badge badge-excused" style="font-size:10px">у</span> — уважительная причина</span>
            <span><span class="badge badge-late" style="font-size:10px">оп</span> — опоздание</span>
            <span style="color:var(--color-text-faint)">· — присутствовал</span>
            <span style="color:var(--color-text-faint)">— — выходной</span>
            <span style="color:var(--color-text-faint)">Надстрочная цифра = часов пропущено</span>
          </div>
        </div>
      </div>

      <!-- Топ студентов по пропускам (из рапортички) -->
      <?php
        $riskRap = array_filter($rapTotals, fn($t) => $t['pct'] < 75);
        uasort($riskRap, fn($a,$b) => $a['pct'] - $b['pct']);
        $studentIndex = array_column($students, null, 'id');
      ?>
      <?php if (!empty($riskRap)): ?>
      <div class="card">
        <div class="card-header">
          <span class="card-title">⚠ Студенты ниже порога посещаемости (75%)</span>
          <span class="badge badge-absent"><?= count($riskRap) ?> чел.</span>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th><th>ФИО</th><th>Группа</th>
                <th>Пропусков (н/уч, ч.)</th>
                <th>Уваж. (ч.)</th>
                <th>Опозданий (ч.)</th>
                <th>% посещ.</th>
              </tr>
            </thead>
            <tbody>
              <?php $ri = 1; foreach ($riskRap as $sid => $t):
                $stRow = $studentIndex[$sid] ?? null; if (!$stRow) continue; ?>
              <tr>
                <td style="color:var(--color-text-faint)"><?= $ri++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($stRow['surname'].' '.$stRow['first_name'].' '.$stRow['patronymic']) ?></td>
                <td><?= htmlspecialchars($stRow['group_name'] ?? '') ?></td>
                <td style="color:var(--color-error);font-weight:600"><?= $t['absent_h'] ?></td>
                <td style="color:var(--color-primary)"><?= $t['excused_h'] ?></td>
                <td style="color:var(--color-warning)"><?= $t['late_h'] ?></td>
                <td>
                  <span class="<?= $t['pct']>=75?'rap-pct-good':($t['pct']>=60?'rap-pct-warn':'rap-pct-bad') ?>"><?= $t['pct'] ?>%</span>
                </td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif ?>

    </div><!-- /tab-report -->

    <!-- ══════════════════ ТАБ 3: СПРАВКИ ═════════════════════ -->
    <div id="tab-documents" class="tab-panel <?= $activeTab==='documents' ? 'active' : '' ?>">

      <?php if (!$docsTableExists): ?>
      <!-- Таблица не создана — показываем инструкцию -->
      <div class="card">
        <div class="doc-no-table">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-text-faint)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
          <div style="font-weight:600;font-size:var(--text-base);color:var(--color-text)">Таблица справок не создана</div>
          <div style="max-width:400px">Выполните файл <code>migration_documents.sql</code> в phpMyAdmin, затем обновите страницу.</div>
        </div>
      </div>
      <?php else: ?>

      <div style="display:grid;grid-template-columns:1fr 380px;gap:var(--space-6);align-items:start">

        <!-- ── Левая колонка: список справок ── -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Справки группы <?= htmlspecialchars($groupInfo['name']) ?></span>
            <div style="display:flex;gap:var(--space-2);align-items:center">
              <?php if ($docsPending > 0): ?>
              <span class="badge" style="background:var(--color-warning-highlight);color:var(--color-warning)">⏳ На проверке: <?= $docsPending ?></span>
              <?php endif ?>
              <span class="badge badge-excused">Всего: <?= count($documents) ?></span>
            </div>
          </div>

          <?php if (empty($documents)): ?>
          <div class="doc-no-table">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-text-faint)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
            <div>Справок пока нет. Загрузите первую справку →</div>
          </div>
          <?php else: ?>
          <div class="table-wrap">
            <table class="data-table" id="docsTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Студент</th>
                  <th>Причина</th>
                  <th>Период</th>
                  <th>Файл</th>
                  <th>Статус</th>
                  <th>Добавлена</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($documents as $i => $doc):
                  $rowCls = $doc['status'] === 'pending' ? 'doc-row-pending' : ($doc['status'] === 'rejected' ? 'doc-row-rejected' : '');
                  $stName = htmlspecialchars($doc['surname'].' '.$doc['first_name'].' '.($doc['patronymic'] ?? ''));
                  $isPdf  = $doc['file_type'] === 'application/pdf';
                ?>
                <tr class="<?= $rowCls ?>" data-doc-id="<?= $doc['id'] ?>">
                  <td style="color:var(--color-text-faint)"><?= $i+1 ?></td>
                  <td style="font-weight:600;white-space:nowrap"><?= $stName ?></td>
                  <td style="max-width:160px;font-size:var(--text-xs)"><?= htmlspecialchars($doc['reason_name'] ?? '—') ?></td>
                  <td style="font-size:var(--text-xs);white-space:nowrap">
                    <?= date('d.m.Y', strtotime($doc['date_from'])) ?><br>
                    <span style="color:var(--color-text-muted)">— <?= date('d.m.Y', strtotime($doc['date_to'])) ?></span>
                  </td>
                  <td>
                    <a href="doc_save.php?action=view&id=<?= $doc['id'] ?>" target="_blank"
                       style="color:var(--color-primary);font-size:var(--text-xs);display:flex;align-items:center;gap:4px;white-space:nowrap">
                      <?php if ($isPdf): ?>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                      <?php else: ?>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                      <?php endif ?>
                      <?= htmlspecialchars(mb_strimwidth($doc['file_orig'], 0, 20, '…')) ?>
                    </a>
                  </td>
                  <td>
                    <?php if ($doc['status'] === 'verified'): ?>
                    <span class="badge doc-status-verified">✓ Принята</span>
                    <?php elseif ($doc['status'] === 'rejected'): ?>
                    <span class="badge doc-status-rejected">✗ Отклонена</span>
                    <?php else: ?>
                    <span class="badge doc-status-pending">⏳ На проверке</span>
                    <?php endif ?>
                  </td>
                  <td style="font-size:var(--text-xs);color:var(--color-text-muted);white-space:nowrap">
                    <?= date('d.m.Y', strtotime($doc['created_at'])) ?><br>
                    <?= date('H:i', strtotime($doc['created_at'])) ?>
                  </td>
                  <td>
                    <div style="display:flex;gap:4px;flex-wrap:nowrap">
                      <?php if ($doc['status'] === 'pending'): ?>
                      <button class="btn btn-success btn-sm" style="padding:2px 8px;font-size:11px"
                              onclick="docAction('verify', <?= $doc['id'] ?>)">✓ Принять</button>
                      <button class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11px;color:var(--color-error);border-color:var(--color-error)"
                              onclick="docAction('reject', <?= $doc['id'] ?>)">✗</button>
                      <?php elseif ($doc['status'] === 'verified'): ?>
                      <button class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11px;color:var(--color-error);border-color:var(--color-error)"
                              onclick="docAction('reject', <?= $doc['id'] ?>)">Отклонить</button>
                      <?php else: ?>
                      <button class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11px"
                              onclick="docAction('verify', <?= $doc['id'] ?>)">Принять</button>
                      <?php endif ?>
                      <button class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11px"
                              onclick="docAction('delete', <?= $doc['id'] ?>)">🗑</button>
                    </div>
                    <?php if ($doc['note']): ?>
                    <div style="font-size:10px;color:var(--color-text-muted);margin-top:2px;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($doc['note']) ?>">
                      💬 <?= htmlspecialchars($doc['note']) ?>
                    </div>
                    <?php endif ?>
                  </td>
                </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
          <?php endif ?>
        </div>

        <!-- ── Правая колонка: форма загрузки ── -->
        <div class="card" style="position:sticky;top:var(--space-4)">
          <div class="card-header"><span class="card-title">Загрузить справку</span></div>
          <div style="padding:var(--space-5);display:flex;flex-direction:column;gap:var(--space-4)">

            <div class="form-group">
              <label class="form-label">Студент *</label>
              <select class="form-control" id="docStudent">
                <option value="">— выберите студента —</option>
                <?php foreach ($students as $st): ?>
                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['surname'].' '.$st['first_name'].' '.$st['patronymic']) ?></option>
                <?php endforeach ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Причина отсутствия</label>
              <select class="form-control" id="docReason">
                <option value="">— не указано —</option>
                <?php foreach ($reasons as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name_ru']) ?></option>
                <?php endforeach ?>
              </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3)">
              <div class="form-group">
                <label class="form-label">Дата с *</label>
                <input type="date" class="form-control" id="docDateFrom" value="<?= $selectedDate ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Дата по *</label>
                <input type="date" class="form-control" id="docDateTo" value="<?= $selectedDate ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Комментарий</label>
              <input type="text" class="form-control" id="docNote" placeholder="Необязательно" style="font-size:var(--text-sm)">
            </div>

            <!-- Зона загрузки -->
            <div id="uploadZone" class="upload-zone" onclick="document.getElementById('docFile').click()"
                 ondragover="event.preventDefault();this.classList.add('drag-over')"
                 ondragleave="this.classList.remove('drag-over')"
                 ondrop="handleDrop(event)">
              <input type="file" id="docFile" accept=".pdf,.jpg,.jpeg,.png" onchange="handleFileSelect(this)">
              <div class="upload-zone-icon" id="uploadIcon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              </div>
              <span class="upload-zone-text" id="uploadText">Выберите файл или перетащите</span>
              <span class="upload-zone-hint">PDF, JPG, PNG · до 5 МБ</span>
            </div>

            <!-- Превью выбранного файла -->
            <div id="filePreview" style="display:none" class="upload-preview">
              <div class="upload-preview-icon" id="filePreviewIcon" style="background:var(--color-primary-highlight);color:var(--color-primary)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
              </div>
              <div style="flex:1;min-width:0">
                <div id="filePreviewName" style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
                <div id="filePreviewSize" style="font-size:var(--text-xs);color:var(--color-text-muted)"></div>
              </div>
              <button onclick="clearFile()" style="background:none;border:none;cursor:pointer;color:var(--color-text-muted);padding:4px">✕</button>
            </div>

            <button class="btn btn-primary" id="docSubmitBtn" onclick="submitDocument()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Сохранить справку
            </button>
          </div>
        </div>

      </div>
      <?php endif ?>

    </div><!-- /tab-documents -->

    <!-- ── Модальное окно: подтвердить / отклонить ── -->
    <div id="docModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeDocModal()">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title" id="docModalTitle">Действие</span>
          <button onclick="closeDocModal()" style="background:none;border:none;cursor:pointer;color:var(--color-text-muted);font-size:20px;line-height:1">✕</button>
        </div>
        <div class="modal-body">
          <div id="docModalDesc" style="color:var(--color-text-muted);font-size:var(--text-sm)"></div>
          <div class="form-group">
            <label class="form-label">Комментарий (необязательно)</label>
            <input type="text" class="form-control" id="docModalNote" placeholder="Причина решения...">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" onclick="closeDocModal()">Отмена</button>
          <button class="btn btn-primary" id="docModalConfirmBtn" onclick="confirmDocAction()">Подтвердить</button>
        </div>
      </div>
    </div>

    <!-- ══════════════════ ТАБ 4: АНАЛИТИКА ═══════════════════ -->
    <div id="tab-analytics" class="tab-panel <?= $activeTab==='analytics' ? 'active' : '' ?>">

      <!-- Фильтры периода -->
      <form method="get" class="an-filters">
        <input type="hidden" name="tab"   value="analytics">
        <input type="hidden" name="group" value="<?= $selectedGrp ?>">
        <input type="hidden" name="date"  value="<?= htmlspecialchars($selectedDate) ?>">
        <div class="form-group">
          <label class="form-label">Период</label>
          <select name="an_period" class="form-control" onchange="this.form.submit()">
            <option value="month" <?= $anPeriod==='month'?'selected':'' ?>>Месяц</option>
            <option value="year"  <?= $anPeriod==='year' ?'selected':'' ?>>Учебный год</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Месяц / начало года</label>
          <input type="month" name="an_month" class="form-control"
                 value="<?= htmlspecialchars($anMonth) ?>" onchange="this.form.submit()">
        </div>
        <div style="margin-left:auto;display:flex;align-items:flex-end;gap:var(--space-2)">
          <span style="font-size:var(--text-xs);color:var(--color-text-faint)">
            <?= date('d.m.Y', strtotime($anDateFrom)) ?> — <?= date('d.m.Y', strtotime($anDateTo)) ?>
          </span>
        </div>
      </form>

      <!-- KPI-плашки -->
      <div class="an-kpi-grid">
        <div class="an-kpi">
          <div class="an-kpi-val" style="color:var(--color-primary)"><?= $anAvgPct ?>%</div>
          <div class="an-kpi-lbl">Средняя посещаемость</div>
        </div>
        <div class="an-kpi">
          <div class="an-kpi-val"><?= $anTotalStudents ?></div>
          <div class="an-kpi-lbl">Студентов всего</div>
        </div>
        <div class="an-kpi">
          <div class="an-kpi-val" style="color:var(--color-error)"><?= $anTotalAbsH ?></div>
          <div class="an-kpi-lbl">Пропущено часов (н/уч)</div>
        </div>
        <div class="an-kpi">
          <div class="an-kpi-val" style="color:var(--color-primary)"><?= $anTotalExcH ?></div>
          <div class="an-kpi-lbl">Уважит. причин (ч.)</div>
        </div>
        <div class="an-kpi">
          <div class="an-kpi-val" style="color:<?= $anRiskCount>0?'var(--color-error)':'var(--color-success)' ?>"><?= $anRiskCount ?></div>
          <div class="an-kpi-lbl">Групп ниже 75%</div>
        </div>
        <div class="an-kpi">
          <div class="an-kpi-val" style="color:var(--color-warning)"><?= count($anRiskStudents) ?></div>
          <div class="an-kpi-lbl">Студентов в зоне риска</div>
        </div>
      </div>

      <!-- Блок 1: Посещаемость по группам + причины пропусков -->
      <div class="an-grid-2" style="margin-bottom:var(--space-6)">

        <!-- Таблица групп -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Посещаемость по группам</span>
            <span class="badge badge-excused"><?= count($anGroups) ?> групп</span>
          </div>
          <?php if (empty($anGroups)): ?>
          <div style="padding:var(--space-8);text-align:center;color:var(--color-text-faint)">Нет данных за период</div>
          <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Группа</th>
                  <th>Куратор</th>
                  <th>Студентов</th>
                  <th>Пропуски (ч.)</th>
                  <th>% посещ.</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($anGroups as $ag):
                  $pctCls = $ag['pct']>=85?'var(--color-success)':($ag['pct']>=75?'var(--color-warning)':'var(--color-error)');
                ?>
                <tr>
                  <td>
                    <a href="?tab=journal&group=<?= $ag['id'] ?>&date=<?= $selectedDate ?>"
                       style="font-weight:600;color:var(--color-primary)"><?= htmlspecialchars($ag['group_name']) ?></a>
                  </td>
                  <td style="font-size:var(--text-xs);color:var(--color-text-muted)"><?= htmlspecialchars($ag['curator_name'] ?: '—') ?></td>
                  <td><?= $ag['total_students'] ?></td>
                  <td>
                    <span style="color:var(--color-error)"><?= $ag['absent_hours'] ?></span>
                    <?php if ($ag['excused_hours']): ?>
                    <span style="color:var(--color-text-faint);font-size:var(--text-xs)"> + <?= $ag['excused_hours'] ?> уваж.</span>
                    <?php endif ?>
                  </td>
                  <td>
                    <div style="display:flex;align-items:center;gap:var(--space-2)">
                      <div style="flex:1;max-width:60px">
                        <div class="prog-bar">
                          <div class="prog-fill <?= $ag['pct']>=85?'green':($ag['pct']>=75?'yellow':'red') ?>"
                               style="width:<?= $ag['pct'] ?>%"></div>
                        </div>
                      </div>
                      <span style="font-weight:700;color:<?= $pctCls ?>;min-width:36px"><?= $ag['pct'] ?>%</span>
                    </div>
                  </td>
                </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
          <?php endif ?>
        </div>

        <!-- Причины пропусков -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Причины пропусков</span>
            <span class="badge"><?= $anReasonsTotal ?> ч. всего</span>
          </div>
          <div style="padding:var(--space-5)">
            <?php if (empty($anReasons)): ?>
            <div style="text-align:center;color:var(--color-text-faint);padding:var(--space-8)">Пропусков за период нет</div>
            <?php else: ?>
            <?php
            $reasonColors = ['var(--color-error)','var(--color-primary)','var(--color-warning)','var(--color-success)','#8b5cf6','#ec4899'];
            foreach ($anReasons as $ri => $ar):
              $pct = round($ar['hours'] / $anReasonsTotal * 100);
              $col = $reasonColors[$ri % count($reasonColors)];
            ?>
            <div style="margin-bottom:var(--space-4)">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:var(--text-sm)">
                <span style="color:var(--color-text)"><?= htmlspecialchars($ar['reason_name']) ?></span>
                <span style="font-weight:600;color:<?= $col ?>"><?= $ar['hours'] ?> ч. (<?= $pct ?>%)</span>
              </div>
              <div style="height:8px;background:var(--color-surface-2);border-radius:var(--radius-full);overflow:hidden">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:var(--radius-full);transition:width .4s ease"></div>
              </div>
              <div style="font-size:10px;color:var(--color-text-faint);margin-top:2px"><?= $ar['cnt'] ?> записей</div>
            </div>
            <?php endforeach ?>
            <?php endif ?>
          </div>
        </div>
      </div>

      <!-- Блок 2: Динамика посещаемости (график) -->
      <div class="card" style="margin-bottom:var(--space-6)">
        <div class="card-header">
          <span class="card-title">Динамика — группа <?= htmlspecialchars($groupInfo['name']) ?></span>
          <div style="display:flex;gap:var(--space-3);font-size:var(--text-xs);align-items:center">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--color-error);margin-right:4px"></span>Прогул</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--color-primary);margin-right:4px"></span>Уваж.</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--color-warning);margin-right:4px"></span>Опоздание</span>
          </div>
        </div>
        <div style="padding:var(--space-4) var(--space-5)">
          <?php if (empty($anTrend)): ?>
          <div style="text-align:center;color:var(--color-text-faint);padding:var(--space-8)">
            Нет данных о посещаемости за выбранный период для группы <?= htmlspecialchars($groupInfo['name']) ?>
          </div>
          <?php else: ?>
          <?php
            $maxStudents = max(1, max(array_column($anTrend,'total')));
          ?>
          <div style="overflow-x:auto">
            <div style="min-width:<?= max(400, count($anTrend)*18) ?>px">
              <!-- Бары -->
              <div class="trend-bar-wrap" id="trendBars">
                <?php foreach ($anTrend as $td):
                  $total   = max(1, $td['total']);
                  $absPct  = round($td['absent']  / $total * 100);
                  $excPct  = round($td['excused'] / $total * 100);
                  $latePct = round($td['late']    / $total * 100);
                  $heightPct = round(($td['absent'] + $td['excused'] + $td['late']) / $total * 100);
                  $title = date('d.m', strtotime($td['date'])) . ': пр=' . $td['absent'] . ' ув=' . $td['excused'] . ' оп=' . $td['late'];
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0;min-width:6px"
                     title="<?= htmlspecialchars($title) ?>">
                  <div style="flex:1;width:100%;display:flex;flex-direction:column;justify-content:flex-end">
                    <?php if ($td['late'] > 0): ?>
                    <div style="width:100%;height:<?= round($td['late']/$total*80) ?>px;background:var(--color-warning);border-radius:2px 2px 0 0"></div>
                    <?php endif ?>
                    <?php if ($td['excused'] > 0): ?>
                    <div style="width:100%;height:<?= round($td['excused']/$total*80) ?>px;background:var(--color-primary)"></div>
                    <?php endif ?>
                    <?php if ($td['absent'] > 0): ?>
                    <div style="width:100%;height:<?= round($td['absent']/$total*80) ?>px;background:var(--color-error)"></div>
                    <?php endif ?>
                    <?php if ($td['absent']+$td['excused']+$td['late'] === 0): ?>
                    <div style="width:100%;height:4px;background:var(--color-success);border-radius:2px"></div>
                    <?php endif ?>
                  </div>
                </div>
                <?php endforeach ?>
              </div>
              <!-- Подписи дат -->
              <div style="display:flex;gap:2px;margin-top:4px">
                <?php foreach ($anTrend as $td): ?>
                <div style="flex:1;font-size:9px;color:var(--color-text-faint);text-align:center;min-width:6px;overflow:hidden">
                  <?= (int)date('d', strtotime($td['date'])) ?>
                </div>
                <?php endforeach ?>
              </div>
            </div>
          </div>
          <?php endif ?>
        </div>
      </div>

      <!-- Блок 3: Студенты в зоне риска -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Студенты в зоне риска</span>
          <?php if (!empty($anRiskStudents)): ?>
          <span class="badge badge-absent"><?= count($anRiskStudents) ?> чел.</span>
          <?php endif ?>
        </div>
        <?php if (empty($anRiskStudents)): ?>
        <div style="padding:var(--space-8);text-align:center;color:var(--color-text-faint)">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:8px;display:block;margin-left:auto;margin-right:auto"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Студентов с критическими пропусками нет — отличный результат!
        </div>
        <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>ФИО студента</th>
                <th>Группа</th>
                <th>Пропущено (ч.)</th>
                <th>Уваж. (ч.)</th>
                <th>Опоздания (ч.)</th>
                <th>Дней пропущено</th>
                <th>% посещ.</th>
                <th>Статус</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($anRiskStudents as $i => $rs):
                $pctCls  = $rs['pct']>=85?'var(--color-success)':($rs['pct']>=75?'var(--color-warning)':'var(--color-error)');
                $badge   = $rs['pct'] < 60  ? ['Критично',  'badge-absent']
                         : ($rs['pct'] < 75 ? ['Внимание',  'badge-late']
                         :                    ['Допустимо', 'badge-excused']);
              ?>
              <tr>
                <td style="color:var(--color-text-faint)"><?= $i+1 ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($rs['surname'].' '.$rs['first_name'].' '.$rs['patronymic']) ?></td>
                <td>
                  <a href="?tab=journal&group=<?= array_search($rs['group_name'], array_column($groups,'name',null)) ?: $selectedGrp ?>&date=<?= $selectedDate ?>"
                     style="color:var(--color-primary)"><?= htmlspecialchars($rs['group_name']) ?></a>
                </td>
                <td style="color:var(--color-error);font-weight:600"><?= $rs['absent_h'] ?></td>
                <td style="color:var(--color-primary)"><?= $rs['excused_h'] ?: '—' ?></td>
                <td style="color:var(--color-warning)"><?= $rs['late_h'] ?: '—' ?></td>
                <td><?= $rs['missed_days'] ?></td>
                <td><span style="font-weight:700;color:<?= $pctCls ?>"><?= $rs['pct'] ?>%</span></td>
                <td><span class="badge <?= $badge[1] ?>"><?= $badge[0] ?></span></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <?php endif ?>
      </div>

    </div><!-- /tab-analytics -->

    <!-- ══════════════════ ТАБ 5: КРИТЕРИИ ══════════════════════ -->
    <div id="tab-criteria" class="tab-panel <?= $activeTab==='criteria' ? 'active' : '' ?>">

      <!-- Фильтры -->
      <form method="get" class="rap-filters" id="criteriaForm">
        <input type="hidden" name="tab"   value="criteria">
        <input type="hidden" name="group" value="<?= $selectedGrp ?>">

        <div class="form-group">
          <label class="form-label">Группа</label>
          <select name="cr_group" class="form-control" style="min-width:150px">
            <option value="0">Все группы</option>
            <?php foreach ($groups as $gid => $g): ?>
            <option value="<?= $gid ?>" <?= ($crGroupId == $gid) ? 'selected' : '' ?>>
              <?= htmlspecialchars($g['name']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Период с</label>
          <input type="date" name="cr_from" class="form-control"
                 value="<?= htmlspecialchars($crDateFrom) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">по</label>
          <input type="date" name="cr_to" class="form-control"
                 value="<?= htmlspecialchars($crDateTo) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Порог посещ.&nbsp;%</label>
          <input type="number" name="cr_threshold" class="form-control"
                 value="<?= $crThreshold ?>" min="1" max="100" style="width:72px">
        </div>

        <div style="display:flex;gap:var(--space-2);align-items:flex-end;margin-left:auto;flex-wrap:wrap">
          <button type="submit" class="btn btn-outline btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Применить
          </button>
          <a class="btn btn-primary btn-sm" id="excelDownloadBtn"
             href="export_criteria.php?group_id=<?= $crGroupId ?>&date_from=<?= urlencode($crDateFrom) ?>&date_to=<?= urlencode($crDateTo) ?>&threshold=<?= $crThreshold ?>"
             download>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Выгрузить Excel
          </a>
        </div>
      </form>

      <!-- KPI сводка -->
      <div class="kpi-grid" style="margin-bottom:var(--space-4)">
        <div class="kpi-card">
          <div class="kpi-icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          <div class="kpi-value"><?= $crTotalStudents ?></div>
          <div class="kpi-label">Студентов всего</div>
          <div class="kpi-pct"><?= $crGroupCount ?> групп(ы)</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon red"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
          <div class="kpi-value"><?= $crRiskCount ?></div>
          <div class="kpi-label">В группе риска</div>
          <div class="kpi-pct">посещ. ниже <?= $crThreshold ?>%</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
          <div class="kpi-value"><?= $crAvgPct ?>%</div>
          <div class="kpi-label">Средняя посещаемость</div>
          <div class="prog-bar"><div class="prog-fill green" style="width:<?= $crAvgPct ?>%"></div></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon orange" style="background:var(--color-warning-highlight);color:var(--color-warning)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div class="kpi-value"><?= $crTotalAbsH ?> ч.</div>
          <div class="kpi-label">Н/уч часов (итого)</div>
          <div class="kpi-pct">Уваж.: <?= $crTotalExcH ?> ч.</div>
        </div>
      </div>

      <!-- Таблица студентов -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Детализация по студентам</span>
          <span style="font-size:12px;color:var(--color-text-muted)">
            Период: <?= date('d.m.Y', strtotime($crDateFrom)) ?> — <?= date('d.m.Y', strtotime($crDateTo)) ?>
            &nbsp;·&nbsp; Рабочих дней: <?= $crWorkDays ?>
          </span>
        </div>
        <div class="table-wrap">
          <table class="data-table" id="criteriaTable">
            <thead>
              <tr>
                <th style="width:36px">#</th>
                <th>ФИО</th>
                <th>Группа</th>
                <th style="text-align:center">Н/уч (ч)</th>
                <th style="text-align:center">Уваж. (ч)</th>
                <th style="text-align:center">Опозд. (ч)</th>
                <th style="text-align:center">Пропущ. дней</th>
                <th style="text-align:center">% посещ.</th>
                <th style="text-align:center">Статус</th>
              </tr>
            </thead>
            <tbody>
              <?php $crNum = 0; foreach ($crStudents as $cs): $crNum++ ?>
              <tr>
                <td style="color:var(--color-text-faint);text-align:center"><?= $crNum ?></td>
                <td><?= htmlspecialchars(trim($cs['full_name'])) ?></td>
                <td style="color:var(--color-text-muted)"><?= htmlspecialchars($cs['group_name']) ?></td>
                <td style="text-align:center<?= $cs['absent_h'] > 0 ? ';color:var(--color-danger);font-weight:600' : '' ?>"><?= (int)$cs['absent_h'] ?></td>
                <td style="text-align:center;color:var(--color-text-muted)"><?= (int)$cs['excused_h'] ?></td>
                <td style="text-align:center;color:var(--color-text-muted)"><?= (int)$cs['late_h'] ?></td>
                <td style="text-align:center"><?= (int)$cs['missed_days'] ?></td>
                <td style="text-align:center">
                  <div style="display:flex;align-items:center;gap:6px;justify-content:center">
                    <div style="width:48px;height:6px;border-radius:3px;background:var(--color-border);overflow:hidden">
                      <div style="height:100%;width:<?= $cs['pct'] ?>%;background:<?= $cs['pct'] >= $crThreshold ? 'var(--color-success)' : 'var(--color-danger)' ?>"></div>
                    </div>
                    <span style="font-weight:600;color:<?= $cs['pct'] >= $crThreshold ? 'var(--color-success)' : 'var(--color-danger)' ?>"><?= $cs['pct'] ?>%</span>
                  </div>
                </td>
                <td style="text-align:center">
                  <?php if ($cs['status'] === 'риск'): ?>
                  <span class="badge badge-absent">Риск</span>
                  <?php else: ?>
                  <span class="badge" style="background:var(--color-success-highlight);color:var(--color-success)">Норма</span>
                  <?php endif ?>
                </td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($crStudents)): ?>
              <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--color-text-muted)">Нет данных за выбранный период</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /tab-criteria -->

<?php endif; // $noGroupsWarning ?>

