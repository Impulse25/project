<?php
require_once 'includes/header.php';

if ($role === 'student') {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$groupId = (int)($_GET['id'] ?? 0);
$group   = getGroupById($groupId);
if (!$group) { echo '<p>Группа не найдена.</p>'; require_once 'includes/footer.php'; exit; }

$students = getStudentsByGroup($groupId);

// Handle flash messages
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title"><?= h($group['name']) ?></div>
    <div class="page-header-sub"><?= h($group['specialty'] ?? '') ?> · Куратор: <?= h($group['teacher_name'] ?? '—') ?></div>
  </div>
  <?php if (in_array($role, ['admin','teacher'])): ?>
  <div style="display:flex;gap:var(--space-3)">
    <button class="btn btn-primary" onclick="openModal('modal-student')">+ Добавить студента</button>
    <a href="<?= SITE_URL ?>/export.php?type=group&id=<?= $groupId ?>" class="btn btn-secondary">⬇ Экспорт CSV</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="alert alert-success anim-fade"><?= h($flash) ?></div>
<?php endif; ?>

<div class="card anim-fade">
  <div class="card-header">
    <div class="card-title">Список студентов (<?= count($students) ?>)</div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>ФИО</th>
          <th>Номер</th>
          <th>Email</th>
          <th>Достижения</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($students as $i => $st):
        $achCount  = getPDO()->prepare("SELECT COUNT(*) FROM achievements WHERE user_id=?");
        $achCount->execute([$st['id']]); $ac = $achCount->fetchColumn();
      ?>
        <tr class="anim-fade" style="animation-delay:<?= $i*0.05 ?>s">
          <td><span class="badge badge-gray"><?= $i+1 ?></span></td>
          <td><a href="<?= SITE_URL ?>/profile.php?id=<?= $st['id'] ?>"><?= h($st['full_name']) ?></a></td>
          <td><?= h($st['student_num'] ?? '—') ?></td>
          <td><?= h($st['email']) ?></td>
          <td><?= $ac > 0 ? "<span class='badge badge-green'>$ac</span>" : '<span class="badge badge-gray">0</span>' ?></td>
          <td>
            <div style="display:flex;gap:var(--space-2)">
              <a href="<?= SITE_URL ?>/profile.php?id=<?= $st['id'] ?>" class="btn btn-secondary btn-sm">Профиль</a>
              <?php if (in_array($role, ['admin','teacher'])): ?>
              <a href="<?= SITE_URL ?>/actions/student_edit.php?id=<?= $st['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
              <?php endif; ?>
              <?php if ($role === 'admin'): ?>
              <a href="<?= SITE_URL ?>/actions/student_delete.php?id=<?= $st['id'] ?>&group_id=<?= $groupId ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить студента?')">🗑</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($students)): ?>
        <tr><td colspan="6" style="text-align:center;padding:var(--space-10);color:var(--color-text-muted)">Студентов пока нет. Добавьте первого!</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (in_array($role, ['admin','teacher'])): ?>
<!-- Modal: Add student -->
<div class="modal-overlay" id="modal-student">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Добавить студента в <?= h($group['name']) ?></div>
      <button class="modal-close" onclick="closeModal('modal-student')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/student_save.php">
        <input type="hidden" name="group_id" value="<?= $groupId ?>">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">ФИО</label>
            <input type="text" name="full_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Номер студента</label>
            <input type="text" name="student_num" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Пароль</label>
            <input type="password" name="password" class="form-control" required minlength="8">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Телефон</label>
          <input type="text" name="phone" class="form-control">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Добавить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-student')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>