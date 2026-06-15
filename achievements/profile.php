<?php
require_once 'includes/header.php';

$viewId = isset($_GET['id']) ? (int)$_GET['id'] : $user['id'];
$pdo    = getPDO();
$stmt   = $pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$viewId]);
$viewUser = $stmt->fetch();
if (!$viewUser) { echo '<p>Пользователь не найден.</p>'; require_once 'includes/footer.php'; exit; }

$studentInfo  = getStudentByUserId($viewId);
$achievements = getAchievements($viewId);
$certs        = getCertificates($viewId);
$events       = getEvents($viewId);
$grades       = $studentInfo ? getGrades($studentInfo['id']) : [];
$absences     = $studentInfo ? getAbsences($studentInfo['id']) : [];

$initials = implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w,0,1)), array_slice(explode(' ',$viewUser['full_name']),0,2)));
$roleMap  = ['admin'=>'Администратор','teacher'=>'Преподаватель','student'=>'Студент','director'=>'Директор'];
?>

<div class="profile-hero anim-fade">
  <div class="profile-avatar"><?= $initials ?></div>
  <div>
    <div class="profile-name"><?= h($viewUser['full_name']) ?></div>
    <div class="profile-meta">
      <?= $roleMap[$viewUser['role']] ?? $viewUser['role'] ?>
      <?php if ($viewUser['email']): ?> · <?= h($viewUser['email']) ?><?php endif; ?>
      <?php if ($studentInfo): ?> · Группа: <strong><?= h($studentInfo['group_name']) ?></strong><?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($grades)):
  $avg = round(array_sum(array_column($grades,'grade')) / count($grades), 1);
?>
<div class="stats-grid anim-fade" style="margin-bottom:var(--space-6)">
  <div class="stat-card">
    <div class="stat-icon purple">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
      </svg>
    </div>
    <div>
      <div class="stat-number"><?= $avg ?></div>
      <div class="stat-label">Средний балл</div>
    </div>
  </div>
</div>
<?php endif; ?>

<div data-tabs>
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab-ach">🏆 Достижения</button>
    <button class="tab-btn" data-tab="tab-cert">📜 Сертификаты</button>
    <button class="tab-btn" data-tab="tab-events">📅 Мероприятия</button>
    <?php if ($studentInfo): ?>
    <button class="tab-btn" data-tab="tab-grades">📊 Оценки</button>
    <button class="tab-btn" data-tab="tab-absences">⚠️ Отсутствия</button>
    <?php endif; ?>
  </div>

  <!-- Достижения -->
  <div class="tab-content active" id="tab-ach">
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Название</th>
              <th>Категория</th>
              <th>Уровень</th>
              <th>Место</th>
              <th>Дата</th>
              <th>Файл</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($achievements)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:var(--space-10)">Достижений пока нет.</td></tr>
          <?php else: ?>
          <?php foreach ($achievements as $a): ?>
            <tr>
              <td>
                <div style="font-weight:600"><?= h($a['title']) ?></div>
                <?php if ($a['description']): ?>
                  <div style="font-size:var(--text-xs);color:var(--text-m)"><?= h(mb_strimwidth($a['description'],0,60,'…')) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-blue"><?= categoryLabel($a['category']) ?></span></td>
              <td><span class="badge badge-purple"><?= levelLabel($a['level']) ?></span></td>
              <td><?= $a['place'] ? '<span class="badge badge-amber">🥇 '.h($a['place']).'</span>' : '—' ?></td>
              <td><?= h($a['date_event'] ?? '—') ?></td>
              <td>
                <?php if (!empty($a['file_path'])): ?>
                  <a href="<?= SITE_URL ?>/uploads/<?= h($a['file_path']) ?>" class="btn btn-secondary btn-sm" target="_blank" download>⬇ PDF</a>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Сертификаты -->
  <div class="tab-content" id="tab-cert">
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Название</th>
              <th>Организация</th>
              <th>Место / Результат</th>
              <th>Дата</th>
              <th>Файл</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($certs)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--color-text-muted);padding:var(--space-10)">Нет сертификатов.</td></tr>
          <?php else: ?>
          <?php foreach ($certs as $c): ?>
            <tr>
              <td style="font-weight:600"><?= h($c['title']) ?></td>
              <td><?= h($c['issuer'] ?? '—') ?></td>
              <td><?= !empty($c['place']) ? '<span class="badge badge-amber">🏆 '.h($c['place']).'</span>' : '—' ?></td>
              <td><?= h($c['issue_date'] ?? '—') ?></td>
              <td>
                <?php if (!empty($c['file_path'])): ?>
                  <a href="<?= SITE_URL ?>/uploads/<?= h($c['file_path']) ?>" class="btn btn-secondary btn-sm" target="_blank" download>⬇ PDF</a>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Мероприятия -->
  <div class="tab-content" id="tab-events">
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Мероприятие</th><th>Дата</th><th>Роль</th><th>Результат</th></tr>
          </thead>
          <tbody>
          <?php foreach ($events as $e): ?>
            <tr>
              <td><?= h($e['title']) ?></td>
              <td><?= h($e['event_date'] ?? '—') ?></td>
              <td><?= h($e['role_event']) ?></td>
              <td><?= h($e['result'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($events)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted)">Участий нет</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($studentInfo): ?>
  <!-- Оценки -->
  <div class="tab-content" id="tab-grades">
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Предмет</th><th>Оценка</th><th>Период</th><th>Преподаватель</th></tr>
          </thead>
          <tbody>
          <?php foreach ($grades as $g):
            $cls = $g['grade']>=5?'green':($g['grade']>=4?'blue':($g['grade']>=3?'amber':'red'));
          ?>
            <tr>
              <td><?= h($g['subject']) ?></td>
              <td><span class="badge badge-<?= $cls ?>"><?= $g['grade'] ?></span></td>
              <td><?= h($g['period'] ?? '—') ?></td>
              <td><?= h($g['teacher_name'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($grades)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted)">Нет оценок</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Отсутствия -->
  <div class="tab-content" id="tab-absences">
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Дата</th><th>Предмет</th><th>Причина</th><th>Часов</th></tr>
          </thead>
          <tbody>
          <?php foreach ($absences as $ab):
            $cls = $ab['reason']==='sick'?'blue':($ab['reason']==='excused'?'amber':'red');
          ?>
            <tr>
              <td><?= h($ab['absent_date']) ?></td>
              <td><?= h($ab['subject'] ?? '—') ?></td>
              <td><span class="badge badge-<?= $cls ?>"><?= absenceReasonLabel($ab['reason']) ?></span></td>
              <td><?= $ab['hours'] ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($absences)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted)">Пропусков нет</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>