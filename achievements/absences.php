<?php
require_once 'includes/header.php';
requireRole('admin', 'teacher');

$pdo = getPDO();

// Filters
$filterGroup  = (int)($_GET['group_id'] ?? 0);
$filterReason = trim($_GET['reason'] ?? '');
$filterFrom   = trim($_GET['from'] ?? '');
$filterTo     = trim($_GET['to'] ?? '');

$where  = ['1=1'];
$params = [];
if ($filterGroup)  { $where[] = 's.group_id = ?'; $params[] = $filterGroup; }
if ($filterReason) { $where[] = 'a.reason = ?'; $params[] = $filterReason; }
if ($filterFrom)   { $where[] = 'a.absent_date >= ?'; $params[] = $filterFrom; }
if ($filterTo)     { $where[] = 'a.absent_date <= ?'; $params[] = $filterTo; }

$sql = "SELECT a.*, u.full_name, gl.name AS group_name, t.full_name AS teacher_name
    FROM absences a
    JOIN users u ON a.student_id = u.id
    LEFT JOIN students s ON s.user_id = a.student_id
    LEFT JOIN groups_list gl ON s.group_id = gl.id
    LEFT JOIN users t ON a.teacher_id = t.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.absent_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$absences = $stmt->fetchAll();

// Stats
$total      = count($absences);
$totalHours = array_sum(array_column($absences, 'hours'));
$bySick     = count(array_filter($absences, fn($r) => $r['reason'] === 'sick'));
$byNoReason = count(array_filter($absences, fn($r) => $r['reason'] === 'no_reason'));
$byExcused  = count(array_filter($absences, fn($r) => $r['reason'] === 'excused'));

$allGroups   = getAllGroups();
$allStudents = $pdo->query("SELECT u.id, u.full_name, s.id AS student_id, gl.name AS group_name
    FROM students s JOIN users u ON s.user_id=u.id
    LEFT JOIN groups_list gl ON s.group_id=gl.id
    ORDER BY u.full_name")->fetchAll();
?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">⚠️ Отсутствия</div>
    <div class="page-header-sub">Журнал пропусков · записей: <?= $total ?> · часов: <?= $totalHours ?></div>
  </div>
  <button class="btn btn-primary" onclick="openModal('modal-absence-add')">+ Отметить отсутствие</button>
</div>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:var(--space-4);margin-bottom:var(--space-5)">
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800;color:var(--color-heading)"><?= $total ?></div>
    <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Всего пропусков</div>
  </div>
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800;color:var(--color-heading)"><?= $totalHours ?></div>
    <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Всего часов</div>
  </div>
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800"><span class="badge badge-blue" style="font-size:1.3rem;padding:4px 10px"><?= $bySick ?></span></div>
    <div style="font-size:var(--text-xs);color:var(--color-text-muted)">По болезни</div>
  </div>
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800"><span class="badge badge-red" style="font-size:1.3rem;padding:4px 10px"><?= $byNoReason ?></span></div>
    <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Без причины</div>
  </div>
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800"><span class="badge badge-amber" style="font-size:1.3rem;padding:4px 10px"><?= $byExcused ?></span></div>
    <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Уважительная</div>
  </div>
</div>

<!-- Filters -->
<div class="card anim-fade" style="padding:var(--space-4);margin-bottom:var(--space-5)">
  <form method="GET" style="display:flex;gap:var(--space-3);flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;flex:1;min-width:150px">
      <label class="form-label">Группа</label>
      <select name="group_id" class="form-control">
        <option value="">Все группы</option>
        <?php foreach ($allGroups as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $filterGroup===$g['id']?'selected':'' ?>><?= h($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:150px">
      <label class="form-label">Причина</label>
      <select name="reason" class="form-control">
        <option value="">Все причины</option>
        <option value="sick"      <?= $filterReason==='sick'?'selected':'' ?>>По болезни</option>
        <option value="no_reason" <?= $filterReason==='no_reason'?'selected':'' ?>>Без причины</option>
        <option value="excused"   <?= $filterReason==='excused'?'selected':'' ?>>Уважительная</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:130px">
      <label class="form-label">С даты</label>
      <input type="date" name="from" class="form-control" value="<?= h($filterFrom) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:130px">
      <label class="form-label">По дату</label>
      <input type="date" name="to" class="form-control" value="<?= h($filterTo) ?>">
    </div>
    <div style="display:flex;gap:var(--space-2)">
      <button type="submit" class="btn btn-primary">Фильтр</button>
      <a href="absences.php" class="btn btn-secondary">Сбросить</a>
    </div>
  </form>
</div>

<!-- Table -->
<div class="card anim-fade">
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Студент</th>
          <th>Группа</th>
          <th>Дата</th>
          <th>Предмет</th>
          <th>Причина</th>
          <th>Часов</th>
          <th>Преподаватель</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($absences)): ?>
        <tr><td colspan="9" style="text-align:center;padding:var(--space-10);color:var(--color-text-muted)">Пропусков не найдено</td></tr>
      <?php else: ?>
      <?php foreach ($absences as $i => $a):
        $cls = $a['reason']==='sick'?'blue':($a['reason']==='excused'?'amber':'red');
      ?>
        <tr class="anim-fade" style="animation-delay:<?= $i*0.025 ?>s">
          <td><span class="badge badge-gray"><?= $i+1 ?></span></td>
          <td><a href="<?= SITE_URL ?>/profile.php?id=<?= $a['student_id'] ?>"><?= h($a['full_name']) ?></a></td>
          <td><?= h($a['group_name'] ?? '—') ?></td>
          <td><?= h($a['absent_date']) ?></td>
          <td><?= h($a['subject'] ?? '—') ?></td>
          <td><span class="badge badge-<?= $cls ?>"><?= absenceReasonLabel($a['reason']) ?></span></td>
          <td><?= (int)$a['hours'] ?></td>
          <td><?= h($a['teacher_name'] ?? '—') ?></td>
          <td>
            <a href="<?= SITE_URL ?>/actions/absence_delete.php?id=<?= $a['id'] ?>&user_id=<?= $a['student_id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Удалить запись?')">🗑</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add absence -->
<div class="modal-overlay" id="modal-absence-add">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Отметить отсутствие</div>
      <button class="modal-close" onclick="closeModal('modal-absence-add')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/absence_save.php">
        <div class="form-group">
          <label class="form-label">Студент</label>
          <select name="student_id" class="form-control" required
                  onchange="document.querySelector('[name=user_id]').value=this.value">
            <option value="">Выберите студента</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= $st['id'] ?>"><?= h($st['full_name']) ?> — <?= h($st['group_name'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" name="user_id" value="">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Дата</label>
            <input type="date" name="absent_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Часов пропущено</label>
            <input type="number" name="hours" class="form-control" value="2" min="1" max="16">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Предмет</label>
          <input type="text" name="subject" class="form-control" placeholder="Математика">
        </div>
        <div class="form-group">
          <label class="form-label">Причина</label>
          <select name="reason" class="form-control" required>
            <option value="no_reason">Без причины</option>
            <option value="sick">По болезни</option>
            <option value="excused">Уважительная</option>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Сохранить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-absence-add')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelector('[name=student_id]').addEventListener('change', function(){
  this.closest('form').querySelector('[name=user_id]').value = this.value;
});
</script>

<?php require_once 'includes/footer.php'; ?>
