<?php
require_once 'includes/header.php';

if ($role === 'student') {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$pdo       = getPDO();
$isAdmin   = ($role === 'admin' || $role === 'director');
$isTeacher = ($role === 'teacher');

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$filterRole   = trim($_GET['role'] ?? '');
$filterGroup  = (int)($_GET['group_id'] ?? 0);
$filterSearch = trim($_GET['q'] ?? '');

if ($isTeacher) { $filterRole = 'student'; }

$where  = ['1=1'];
$params = [];
if ($filterRole)   { $where[] = 'u.role = ?';                             $params[] = $filterRole; }
if ($filterGroup)  { $where[] = 's.group_id = ?';                         $params[] = $filterGroup; }
if ($filterSearch) { $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)'; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }

$sql = "SELECT u.*,
    s.id AS student_id, s.student_num, s.phone,
    gl.id AS group_id, gl.name AS group_name,
    (SELECT COUNT(*) FROM achievements a WHERE a.user_id=u.id) AS ach_count,
    (SELECT COUNT(*) FROM certificates c WHERE c.user_id=u.id) AS cert_count,
    (SELECT COUNT(*) FROM grades g WHERE g.student_id=s.id) AS grade_count,
    (SELECT IFNULL(ROUND(AVG(g2.grade),1),0) FROM grades g2 WHERE g2.student_id=s.id) AS avg_grade,
    0 AS absence_count
    FROM users u
    LEFT JOIN students s ON s.user_id = u.id
    LEFT JOIN edu_groups gl ON s.group_id = gl.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$counts   = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();
$statMap  = ['admin'=>0,'teacher'=>0,'student'=>0,'director'=>0];
foreach ($counts as $c) $statMap[$c['role']] = (int)$c['cnt'];
$totalUsers = array_sum($statMap);

$allGroups  = getAllGroups();
$roleMap    = ['admin'=>'Администратор','teacher'=>'Преподаватель','student'=>'Студент','director'=>'Директор'];
$roleColors = ['admin'=>'purple','teacher'=>'blue','student'=>'green','director'=>'amber'];
?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">
      <?= $isAdmin ? '👤 Пользователи' : '🎓 Студенты' ?>
    </div>
    <div class="page-header-sub">
      <?= $isAdmin
        ? 'Управление аккаунтами · всего: '.$totalUsers
        : 'Список студентов · найдено: '.count($users) ?>
    </div>
  </div>
  <?php if ($isAdmin): ?>
  <button class="btn btn-primary" onclick="openModal('modal-user-add')">+ Добавить пользователя</button>
  <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="alert alert-success anim-fade">✅ <?= h($flash) ?></div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:var(--space-4);margin-bottom:var(--space-5)">
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:2rem;font-weight:800;color:var(--text)"><?= $totalUsers ?></div>
    <div style="font-size:var(--text-xs);color:var(--text-m)">Всего</div>
  </div>
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:1.3rem;margin-bottom:4px">📚</div>
    <div style="font-size:1.8rem;font-weight:800"><span class="badge badge-blue" style="font-size:1rem;padding:3px 10px"><?= $statMap['teacher'] ?></span></div>
    <div style="font-size:var(--text-xs);color:var(--text-m)">Преподавателей</div>
  </div>
  <div class="card anim-fade" style="padding:var(--space-4);text-align:center">
    <div style="font-size:1.3rem;margin-bottom:4px">👑</div>
    <div style="font-size:1.8rem;font-weight:800"><span class="badge badge-purple" style="font-size:1rem;padding:3px 10px"><?= $statMap['admin'] ?></span></div>
    <div style="font-size:var(--text-xs);color:var(--text-m)">Администраторов</div>
  </div>
</div>
<?php endif; ?>

<?php if ($isTeacher): ?>
<div class="alert alert-info anim-fade">
  📌 Вы видите только студентов. Нажмите на имя для просмотра профиля.
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card anim-fade" style="padding:var(--space-4);margin-bottom:var(--space-5)">
  <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;flex:2;min-width:200px">
      <label class="form-label">Поиск</label>
      <input type="text" name="q" class="form-control" placeholder="Имя или email…" value="<?= h($filterSearch) ?>">
    </div>
    <?php if ($isAdmin): ?>
    <div class="form-group" style="margin:0;flex:1;min-width:150px">
      <label class="form-label">Роль</label>
      <select name="role" class="form-control">
        <option value="">Все роли</option>
        <option value="admin"    <?= $filterRole==='admin'?'selected':'' ?>>👑 Администратор</option>
        <option value="director" <?= $filterRole==='director'?'selected':'' ?>>🏫 Директор</option>
        <option value="teacher"  <?= $filterRole==='teacher'?'selected':'' ?>>📚 Преподаватель</option>
        <option value="student"  <?= $filterRole==='student'?'selected':'' ?>>🎓 Студент</option>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group" style="margin:0;flex:1;min-width:150px">
      <label class="form-label">Группа</label>
      <select name="group_id" class="form-control">
        <option value="">Все группы</option>
        <?php foreach ($allGroups as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $filterGroup===$g['id']?'selected':'' ?>><?= h($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;gap:.5rem">
      <button type="submit" class="btn btn-primary">Найти</button>
      <a href="users.php" class="btn btn-secondary">Сбросить</a>
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
          <th>ФИО</th>
          <th>Email</th>
          <?php if ($isAdmin): ?><th>Роль</th><?php endif; ?>
          <th>Группа</th>
          <th>🏆</th>
          <th>📜</th>
          <th>📊 Ср. балл</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($users)): ?>
        <tr>
          <td colspan="9" style="text-align:center;padding:var(--space-10);color:var(--text-m)">
            <?= $isTeacher ? 'Студентов не найдено' : 'Пользователей не найдено' ?>
          </td>
        </tr>
      <?php else: ?>
      <?php foreach ($users as $i => $u):
        $rc       = $roleColors[$u['role']] ?? 'gray';
        $roleIcon = ['admin'=>'👑','teacher'=>'📚','student'=>'🎓','director'=>'🏫'][$u['role']] ?? '';
      ?>
        <tr class="anim-fade" style="animation-delay:<?= $i*0.02 ?>s">
          <td><span class="badge badge-gray"><?= $i+1 ?></span></td>
          <td>
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $u['id'] ?>" style="font-weight:600">
              <?= h($u['full_name']) ?>
            </a>
            <?php if ($u['student_num']): ?>
              <div style="font-size:var(--text-xs);color:var(--text-m)">№ <?= h($u['student_num']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:var(--text-sm)"><?= h($u['email'] ?? '—') ?></td>
          <?php if ($isAdmin): ?>
          <td>
            <span class="badge badge-<?= $rc ?>"><?= $roleIcon ?> <?= $roleMap[$u['role']] ?? $u['role'] ?></span>
          </td>
          <?php endif; ?>
          <td>
            <?= $u['group_name']
              ? '<a href="'.SITE_URL.'/group_detail.php?id='.$u['group_id'].'">'.h($u['group_name']).'</a>'
              : '<span style="color:var(--text-m)">—</span>' ?>
          </td>
          <td><span class="badge badge-<?= $u['ach_count']>0?'green':'gray' ?>"><?= $u['ach_count'] ?></span></td>
          <td><span class="badge badge-<?= $u['cert_count']>0?'amber':'gray' ?>"><?= $u['cert_count'] ?></span></td>
          <td>
            <?php if ($u['avg_grade'] > 0):
              $gc = $u['avg_grade']>=4.5?'green':($u['avg_grade']>=3.5?'blue':($u['avg_grade']>=2.5?'amber':'red'));
            ?>
              <span class="badge badge-<?= $gc ?>"><?= $u['avg_grade'] ?></span>
            <?php else: ?>
              <span style="color:var(--text-m)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:.375rem;flex-wrap:wrap">
              <a href="<?= SITE_URL ?>/profile.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">👁</a>
              <?php if ($isAdmin && $u['id'] !== $user['id']): ?>
              <button class="btn btn-secondary btn-sm"
                      onclick="openEditModal(<?= $u['id'] ?>,'<?= h(addslashes($u['full_name'])) ?>','<?= h(addslashes($u['email'] ?? '')) ?>','<?= $u['role'] ?>')">✏️</button>
              <a href="<?= SITE_URL ?>/actions/user_delete.php?id=<?= $u['id'] ?>"
                 class="btn btn-danger btn-sm"
                 onclick="return confirm('Удалить «<?= h(addslashes($u['full_name'])) ?>»?')">🗑</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($isAdmin): ?>

<!-- Modal: Add user -->
<div class="modal-overlay" id="modal-user-add">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Добавить пользователя</div>
      <button class="modal-close" onclick="closeModal('modal-user-add')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/user_save.php">
        <div class="form-group">
          <label class="form-label">Роль</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:.5rem">
            <?php foreach ([
              ['teacher','📚','Преподаватель','blue'],
              ['admin',  '👑','Администратор','purple'],
              ['director','🏫','Директор',   'amber'],
            ] as [$val,$icon,$label,$col]): ?>
            <label style="cursor:pointer">
              <input type="radio" name="role" value="<?= $val ?>"
                     <?= $val==='teacher'?'checked':'' ?>
                     style="display:none" class="role-radio">
              <div class="role-card" style="
                border:2px solid <?= $val==='teacher'?'#3b82f6':($val==='admin'?'#a855f7':'#f59e0b') ?>;
                border-radius:10px;padding:10px 8px;text-align:center;
                background:<?= $val==='teacher'?'#eff6ff':($val==='admin'?'#faf5ff':'#fffbeb') ?>;
                transition:all 0.15s;opacity:<?= $val==='teacher'?'1':'0.45' ?>">
                <div style="font-size:1.2rem"><?= $icon ?></div>
                <div style="font-size:.72rem;font-weight:700;margin-top:4px"><?= $label ?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">ФИО</label>
          <input type="text" name="full_name" class="form-control" placeholder="Иванова Марина Сергеевна" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email <span style="color:var(--text-m);font-weight:400">(необязательно)</span></label>
            <input type="email" name="email" class="form-control" placeholder="user@svgtk.kz">
          </div>
          <div class="form-group">
            <label class="form-label">Пароль</label>
            <input type="password" name="password" class="form-control" minlength="8" required placeholder="Минимум 8 символов">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">✅ Создать</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-user-add')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit user -->
<div class="modal-overlay" id="modal-user-edit">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Редактировать пользователя</div>
      <button class="modal-close" onclick="closeModal('modal-user-edit')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/user_update.php">
        <input type="hidden" name="id" id="edit-user-id">
        <div class="form-group">
          <label class="form-label">ФИО</label>
          <input type="text" name="full_name" id="edit-user-name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="edit-user-email" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Роль</label>
          <select name="role" id="edit-user-role" class="form-control">
            <option value="teacher">📚 Преподаватель</option>
            <option value="admin">👑 Администратор</option>
            <option value="director">🏫 Директор</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Новый пароль <span style="color:var(--text-m);font-weight:400">(оставьте пустым)</span></label>
          <input type="password" name="password" class="form-control" minlength="8" placeholder="Минимум 8 символов">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">💾 Сохранить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-user-edit')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.role-radio').forEach(radio => {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.role-card').forEach(c => c.style.opacity = '0.45');
    this.closest('label').querySelector('.role-card').style.opacity = '1';
  });
});
function openEditModal(id, name, email, role) {
  document.getElementById('edit-user-id').value    = id;
  document.getElementById('edit-user-name').value  = name;
  document.getElementById('edit-user-email').value = email;
  document.getElementById('edit-user-role').value  = role;
  openModal('modal-user-edit');
}
</script>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>