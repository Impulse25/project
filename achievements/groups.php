<?php
require_once 'includes/header.php';

$pdo     = getPDO();
$flash   = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$isAdmin = ($role === 'admin');

// Load groups with student count
$groups = getAllGroups();
foreach ($groups as &$g) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM students WHERE group_id=?");
    $s->execute([$g['id']]);
    $g['student_count'] = (int)$s->fetchColumn();
}
unset($g);

// All teachers for dropdown
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name")->fetchAll();
?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">Группы</div>
    <div class="page-header-sub">Все учебные группы колледжа · всего: <?= count($groups) ?></div>
  </div>
  <?php if ($isAdmin): ?>
  <button class="btn btn-primary" onclick="openModal('modal-group-add')">+ Добавить группу</button>
  <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="alert alert-success anim-fade">✅ <?= h($flash) ?></div>
<?php endif; ?>

<!-- Groups grid -->
<div class="groups-grid">
<?php foreach ($groups as $i => $g): ?>
  <div class="group-card anim-fade" style="animation-delay:<?= $i*0.07 ?>s">

    <!-- Click header → group detail -->
    <a href="<?= SITE_URL ?>/group_detail.php?id=<?= $g['id'] ?>" style="text-decoration:none;display:block">
      <div class="group-card-header">
        <div class="group-card-name"><?= h($g['name']) ?></div>
        <div class="group-card-specialty"><?= h($g['specialty'] ?? '') ?></div>
      </div>
    </a>

    <div class="group-card-body">
      <div>
        <div class="group-card-count">Студентов: <strong><?= $g['student_count'] ?></strong></div>
        <div style="font-size:var(--text-xs);color:var(--color-text-muted);margin-top:2px">
          <?= h($g['teacher_name'] ?? 'Куратор не назначен') ?>
        </div>
      </div>

      <?php if ($isAdmin): ?>
      <div style="display:flex;gap:var(--space-2)">
        <!-- Edit button — pass data via data-attributes, no quoting issues -->
        <button class="btn btn-secondary btn-sm edit-group-btn"
                data-id="<?= (int)$g['id'] ?>"
                data-name="<?= h($g['name']) ?>"
                data-specialty="<?= h($g['specialty'] ?? '') ?>"
                data-year="<?= (int)($g['year_start'] ?? date('Y')) ?>"
                data-teacher="<?= (int)($g['teacher_id'] ?? 0) ?>"
                title="Редактировать">✏️</button>

        <!-- Delete button -->
        <a href="<?= SITE_URL ?>/actions/group_delete.php?id=<?= $g['id'] ?>"
           class="btn btn-danger btn-sm"
           onclick="return confirm('Удалить группу «<?= h(addslashes($g['name'])) ?>»?\nСтуденты будут откреплены от группы.')"
           title="Удалить">🗑</a>
      </div>
      <?php endif; ?>
    </div>

  </div>
<?php endforeach; ?>
</div>

<?php if ($isAdmin): ?>

<!-- ── Modal: Add group ────────────────────────────────── -->
<div class="modal-overlay" id="modal-group-add">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">➕ Добавить группу</div>
      <button class="modal-close" onclick="closeModal('modal-group-add')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/group_save.php">
        <div class="form-group">
          <label class="form-label">Название группы</label>
          <input type="text" name="name" class="form-control" placeholder="ПВТ-9-24" required>
        </div>
        <div class="form-group">
          <label class="form-label">Специальность</label>
          <input type="text" name="specialty" class="form-control" placeholder="Программное обеспечение">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Год набора</label>
            <input type="number" name="year_start" class="form-control" value="<?= date('Y') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Куратор</label>
            <select name="teacher_id" class="form-control">
              <option value="">Не назначен</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= h($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Сохранить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-group-add')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal: Edit group ───────────────────────────────── -->
<div class="modal-overlay" id="modal-group-edit">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏️ Редактировать группу</div>
      <button class="modal-close" onclick="closeModal('modal-group-edit')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/group_update.php">
        <input type="hidden" name="id" id="edit-group-id">

        <div class="form-group">
          <label class="form-label">Название группы</label>
          <input type="text" name="name" id="edit-group-name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Специальность</label>
          <input type="text" name="specialty" id="edit-group-specialty" class="form-control">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Год набора</label>
            <input type="number" name="year_start" id="edit-group-year" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Куратор</label>
            <select name="teacher_id" id="edit-group-teacher" class="form-control">
              <option value="">Не назначен</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= h($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">💾 Сохранить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-group-edit')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Open edit modal — read from data-attributes (no escaping issues)
document.querySelectorAll('.edit-group-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    document.getElementById('edit-group-id').value        = this.dataset.id;
    document.getElementById('edit-group-name').value      = this.dataset.name;
    document.getElementById('edit-group-specialty').value = this.dataset.specialty;
    document.getElementById('edit-group-year').value      = this.dataset.year;

    // Set teacher dropdown — find matching option
    const sel = document.getElementById('edit-group-teacher');
    sel.value = this.dataset.teacher;
    // If teacher not in list (e.g. 0), default to first option
    if (!sel.value) sel.selectedIndex = 0;

    openModal('modal-group-edit');
  });
});
</script>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>