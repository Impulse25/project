<?php
require_once 'includes/header.php';
requireRole('admin', 'teacher');

$pdo = getPDO();

// Filters
$filterGroup   = (int)($_GET['group_id'] ?? 0);
$filterSubject = trim($_GET['subject'] ?? '');
$filterPeriod  = trim($_GET['period'] ?? '');

$where  = ['1=1'];
$params = [];
if ($filterGroup)   { $where[] = 's.group_id = ?'; $params[] = $filterGroup; }
if ($filterSubject) { $where[] = 'g.subject LIKE ?'; $params[] = "%$filterSubject%"; }
if ($filterPeriod)  { $where[] = 'g.period = ?'; $params[] = $filterPeriod; }

$sql = "SELECT g.*, u.full_name, gl.name AS group_name, t.full_name AS teacher_name
    FROM grades g
    JOIN users u ON g.student_id = u.id
    LEFT JOIN students s ON s.user_id = g.student_id
    LEFT JOIN groups_list gl ON s.group_id = gl.id
    LEFT JOIN users t ON g.teacher_id = t.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY g.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$grades = $stmt->fetchAll();

// Summary stats
$total   = count($grades);
$average = $total ? round(array_sum(array_column($grades, 'grade')) / $total, 2) : 0;
$dist    = [5=>0, 4=>0, 3=>0, 2=>0];
foreach ($grades as $g) { $k = (int)$g['grade']; if (isset($dist[$k])) $dist[$k]++; }

// Helper data
$allGroups  = getAllGroups();
$allStudents = $pdo->query("SELECT u.id, u.full_name, gl.name AS group_name
    FROM students s JOIN users u ON s.user_id=u.id
    LEFT JOIN groups_list gl ON s.group_id=gl.id
    ORDER BY u.full_name")->fetchAll();
$allPeriods = $pdo->query("SELECT DISTINCT period FROM grades WHERE period IS NOT NULL AND period != '' ORDER BY period DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">📊 Оценки</div>
    <div class="page-header-sub">Журнал успеваемости · записей: <?= $total ?></div>
  </div>
  <button class="btn btn-primary" onclick="openModal('modal-grade-add')">+ Добавить оценку</button>
</div>

<!-- Summary stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:var(--space-4);margin-bottom:var(--space-5)">
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800;color:var(--color-heading)"><?= $total ?></div>
    <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Всего записей</div>
  </div>
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800;color:var(--color-heading)"><?= $average ?></div>
    <div style="font-size:var(--text-xs);color:var(--color-text-muted)">Средний балл</div>
  </div>
  <?php foreach ([5=>'green',4=>'blue',3=>'amber',2=>'red'] as $grade => $color): ?>
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800"><span class="badge badge-<?= $color ?>" style="font-size:1.5rem;padding:4px 12px"><?= $grade ?></span></div>
    <div style="font-size:var(--text-xs);color:var(--color-text-muted)"><?= $dist[$grade] ?> оценок</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card anim-fade" style="padding:var(--space-4);margin-bottom:var(--space-5)">
  <form method="GET" style="display:flex;gap:var(--space-3);flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;flex:1;min-width:160px">
      <label class="form-label">Группа</label>
      <select name="group_id" class="form-control">
        <option value="">Все группы</option>
        <?php foreach ($allGroups as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $filterGroup===$g['id']?'selected':'' ?>><?= h($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:160px">
      <label class="form-label">Предмет</label>
      <input type="text" name="subject" class="form-control" placeholder="Название предмета…" value="<?= h($filterSubject) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px">
      <label class="form-label">Период</label>
      <select name="period" class="form-control">
        <option value="">Все периоды</option>
        <?php foreach ($allPeriods as $p): ?>
          <option value="<?= h($p) ?>" <?= $filterPeriod===$p?'selected':'' ?>><?= h($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;gap:var(--space-2)">
      <button type="submit" class="btn btn-primary">Фильтр</button>
      <a href="grades.php" class="btn btn-secondary">Сбросить</a>
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
          <th>Предмет</th>
          <th>Оценка</th>
          <th>Период</th>
          <th>Преподаватель</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($grades)): ?>
        <tr><td colspan="8" style="text-align:center;padding:var(--space-10);color:var(--color-text-muted)">Оценок не найдено</td></tr>
      <?php else: ?>
      <?php foreach ($grades as $i => $g):
        $cls = $g['grade']>=5?'green':($g['grade']>=4?'blue':($g['grade']>=3?'amber':'red'));
      ?>
        <tr class="anim-fade" style="animation-delay:<?= $i*0.025 ?>s">
          <td><span class="badge badge-gray"><?= $i+1 ?></span></td>
          <td><a href="<?= SITE_URL ?>/profile.php?id=<?= $g['student_id'] ?>"><?= h($g['full_name']) ?></a></td>
          <td><?= h($g['group_name'] ?? '—') ?></td>
          <td><?= h($g['subject']) ?></td>
          <td><span class="badge badge-<?= $cls ?>" style="font-size:var(--text-base);font-weight:800"><?= $g['grade'] ?></span></td>
          <td><?= h($g['period'] ?? '—') ?></td>
          <td><?= h($g['teacher_name'] ?? '—') ?></td>
          <td>
            <a href="<?= SITE_URL ?>/actions/grade_delete.php?id=<?= $g['id'] ?>&user_id=<?= $g['student_id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Удалить оценку?')">🗑</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add grade -->
<div class="modal-overlay" id="modal-grade-add">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Добавить оценку</div>
      <button class="modal-close" onclick="closeModal('modal-grade-add')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/grade_save.php">
        <div class="form-group">
          <label class="form-label">Студент</label>
          <select name="student_id" id="grade-student-sel" class="form-control" required
                  onchange="document.querySelector('[name=user_id]').value=this.value">
            <option value="">Выберите студента</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= $st['id'] ?>"><?= h($st['full_name']) ?> — <?= h($st['group_name'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- grade_save.php expects student_id AND user_id for redirect -->
        <input type="hidden" name="user_id" value="">
        <div class="form-group">
          <label class="form-label">Предмет</label>
          <input type="text" name="subject" class="form-control" placeholder="Математика" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Оценка (2–5)</label>
            <select name="grade" class="form-control" required>
              <option value="5">5 — Отлично</option>
              <option value="4">4 — Хорошо</option>
              <option value="3">3 — Удовлетворительно</option>
              <option value="2">2 — Неудовлетворительно</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Период</label>
            <input type="text" name="period" class="form-control" placeholder="2024-2025 / 1 сем.">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Сохранить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-grade-add')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// sync hidden user_id for redirect
document.getElementById('grade-student-sel').addEventListener('change', function(){
  this.closest('form').querySelector('[name=user_id]').value = this.value;
});
</script>

<?php require_once 'includes/footer.php'; ?>
