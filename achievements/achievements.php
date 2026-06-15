<?php
require_once 'includes/header.php';

$pdo       = getPDO();
$activeTab = $_GET['tab'] ?? 'achievements';
$flash     = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$filterGroup    = (int)($_GET['group_id'] ?? 0);
$filterCategory = trim($_GET['category'] ?? '');
$filterLevel    = trim($_GET['level'] ?? '');
$filterSearch   = trim($_GET['q'] ?? '');

try {
    $hasEduCol = (bool)$pdo->query("SHOW COLUMNS FROM achievements LIKE 'edu_student_id'")->fetch();
} catch(Exception $e) { $hasEduCol = false; }
try {
    $hasEduColC = (bool)$pdo->query("SHOW COLUMNS FROM certificates LIKE 'edu_student_id'")->fetch();
} catch(Exception $e) { $hasEduColC = false; }

$studentAchs = [];
$teacherAchs = [];

if ($hasEduCol) {
    $sw = ['a.edu_student_id IS NOT NULL'];
    $sp = [];
    if ($filterGroup)    { $sw[] = 'es.group_id = ?'; $sp[] = $filterGroup; }
    if ($filterCategory) { $sw[] = 'a.category = ?';  $sp[] = $filterCategory; }
    if ($filterSearch)   {
        $sw[] = "(a.title LIKE ? OR CONCAT(es.surname,' ',es.name) LIKE ?)";
        $sp[] = "%$filterSearch%"; $sp[] = "%$filterSearch%";
    }
    $stmt = $pdo->prepare("SELECT a.*,
        CONCAT(es.surname,' ',es.name,
            IF(es.patronymic!='' AND es.patronymic IS NOT NULL, CONCAT(' ',es.patronymic),'')) AS full_name,
        g.name AS group_name, added.full_name AS added_by_name
        FROM achievements a
        JOIN edu_students es ON a.edu_student_id = es.id
        LEFT JOIN edu_groups g ON es.group_id = g.id
        LEFT JOIN users added ON a.added_by = added.id
        WHERE " . implode(' AND ', $sw) . "
        ORDER BY a.date_event DESC, a.created_at DESC");
    $stmt->execute($sp);
    $studentAchs = $stmt->fetchAll();
}

$tw = ['1=1'];
$tp = [];
if ($filterCategory) { $tw[] = 'a.category = ?'; $tp[] = $filterCategory; }
if ($filterLevel)    { $tw[] = 'a.level = ?';    $tp[] = $filterLevel; }
if ($filterSearch)   {
    $tw[] = '(a.title LIKE ? OR COALESCE(u.full_name, added.full_name) LIKE ?)';
    $tp[] = "%$filterSearch%"; $tp[] = "%$filterSearch%";
}
try {
    $nullCond = $hasEduCol ? '(a.edu_student_id IS NULL OR a.edu_student_id = 0)' : '1=1';

    if ($role === 'teacher') {
        // Teacher видит только свои: по user_id, added_by или recipient_name
        $tw[] = '(a.user_id = ? OR (a.user_id = 0 AND a.added_by = ?))';
        $tp[] = $user['id'];
        $tp[] = $user['id'];
    } else {
        $tw[] = '(a.user_id > 0 OR a.added_by > 0)';
    }
    $tw[] = $nullCond;

    $ts = $pdo->prepare("SELECT a.*,
        COALESCE(u.full_name, added.full_name, 'Неизвестно') AS full_name,
        added.full_name AS added_by_name
        FROM achievements a
        LEFT JOIN users u ON a.user_id = u.id AND a.user_id > 0
        LEFT JOIN users added ON a.added_by = added.id
        WHERE " . implode(' AND ', $tw) . "
        ORDER BY a.date_event DESC, a.created_at DESC");
    $ts->execute($tp);
    $teacherAchs = $ts->fetchAll();
} catch (Exception $e) {}

$studentCerts = [];
$teacherCerts = [];

if ($hasEduColC) {
    $cw = ['c.edu_student_id IS NOT NULL'];
    $cp = [];
    if ($filterGroup)  { $cw[] = 'es.group_id = ?'; $cp[] = $filterGroup; }
    if ($filterSearch) {
        $cw[] = "(c.title LIKE ? OR CONCAT(es.surname,' ',es.name) LIKE ?)";
        $cp[] = "%$filterSearch%"; $cp[] = "%$filterSearch%";
    }
    $cs = $pdo->prepare("SELECT c.*,
        CONCAT(es.surname,' ',es.name,
            IF(es.patronymic!='' AND es.patronymic IS NOT NULL, CONCAT(' ',es.patronymic),'')) AS full_name,
        g.name AS group_name, curator.full_name AS curator_name,
        added.full_name AS added_by_name, 1 AS is_edu_student
        FROM certificates c
        JOIN edu_students es ON c.edu_student_id = es.id
        LEFT JOIN edu_groups g ON es.group_id = g.id
        LEFT JOIN users curator ON g.curator_id = curator.id
        LEFT JOIN users added ON c.added_by = added.id
        WHERE " . implode(' AND ', $cw) . "
        ORDER BY c.created_at DESC");
    $cs->execute($cp);
    $studentCerts = $cs->fetchAll();
}

try {
    // Показываем сертификаты преподавателей + записи без владельца (user_id=0)
    $cNullCond = $hasEduColC ? '(c.edu_student_id IS NULL OR c.edu_student_id = 0)' : '1=1';
    $ctParams = [];

    if ($role === 'teacher') {
        // Teacher видит: свои записи ИЛИ где его имя в recipient_name
        $ownerCond = '(c.user_id = ? OR (c.user_id = 0 AND c.added_by = ?) OR c.recipient_name = ?)';
        $ctParams[] = $user['id'];
        $ctParams[] = $user['id'];
        $ctParams[] = $user['full_name'];
    } else {
        // Админ/директор видят всех
        $ownerCond = '(c.user_id > 0 OR c.added_by > 0)';
    }

    $ctWhere = [$cNullCond, $ownerCond];

    if ($filterSearch) {
        $ctWhere[] = "(COALESCE(u.full_name, added.full_name) LIKE ? OR c.title LIKE ?)";
        $ctParams[] = "%$filterSearch%";
        $ctParams[] = "%$filterSearch%";
    }
    $ctQuery = $pdo->prepare("SELECT c.*,
        COALESCE(u.full_name, c.recipient_name, added.full_name, 'Неизвестно') AS full_name,
        COALESCE(u.role, added.role, 'teacher') AS user_role,
        NULL AS group_name, NULL AS curator_name,
        added.full_name AS added_by_name, 0 AS is_edu_student
        FROM certificates c
        LEFT JOIN users u ON c.user_id = u.id AND c.user_id > 0
        LEFT JOIN users added ON c.added_by = added.id
        WHERE " . implode(' AND ', $ctWhere) . "
        ORDER BY c.created_at DESC");
    $ctQuery->execute($ctParams);
    $teacherCerts = $ctQuery->fetchAll();
} catch (Exception $e) {}

$allGroups = getAllGroups();
try {
    $allStudents = $pdo->query("SELECT es.id,
        CONCAT(es.surname,' ',es.name,
            IF(es.patronymic!='' AND es.patronymic IS NOT NULL, CONCAT(' ',es.patronymic),'')) AS full_name,
        g.name AS group_name, g.id AS group_id
        FROM edu_students es
        LEFT JOIN edu_groups g ON es.group_id = g.id
        ORDER BY g.name, es.surname")->fetchAll();
} catch (Exception $e) { $allStudents = []; }

$allTeachers = $pdo->query("SELECT id, full_name FROM users
    WHERE role IN ('teacher','admin','director') ORDER BY full_name")->fetchAll();

$lvlColors = ['international'=>'purple','national'=>'blue','regional'=>'green','city'=>'amber','college'=>'gray'];
$totalAch  = count($studentAchs) + count($teacherAchs);
$totalCert = count($studentCerts) + count($teacherCerts);
?>

<?php if ($flash): ?>
<div class="alert alert-success anim-fade"> <?= h($flash) ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert alert-error anim-fade"> <?= h($_SESSION['flash_error']) ?></div>
<?php unset($_SESSION['flash_error']); endif; ?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">Достижения и сертификаты</div>
    <div class="page-header-sub">Всего: <?= $totalAch ?> достижений · <?= $totalCert ?> сертификатов</div>
  </div>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <?php if (in_array($role, ['admin','teacher','director'])): ?>
    <a href="<?= SITE_URL ?>/cert_review.php" class="btn btn-secondary"> Загрузить PDF</a>
    <button class="btn btn-secondary" onclick="openModal('modal-cert-add')">+ Сертификат</button>
    <button class="btn btn-primary"   onclick="openModal('modal-ach-add')">+ Достижение</button>
    <?php endif; ?>
  </div>
</div>

<div class="card anim-fade" style="padding:var(--space-4);margin-bottom:var(--space-5)">
  <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
    <div class="form-group" style="margin:0;flex:2;min-width:180px">
      <label class="form-label">Поиск</label>
      <input type="text" name="q" class="form-control" placeholder="ФИО или название…" value="<?= h($filterSearch) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:130px">
      <label class="form-label">Группа</label>
      <select name="group_id" class="form-control">
        <option value="">Все группы</option>
        <?php foreach ($allGroups as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $filterGroup===$g['id']?'selected':'' ?>><?= h($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:130px">
      <label class="form-label">Категория</label>
      <select name="category" class="form-control">
        <option value="">Все</option>
        <option value="olympiad"   <?= $filterCategory==='olympiad'?'selected':'' ?>>Олимпиада</option>
        <option value="conference" <?= $filterCategory==='conference'?'selected':'' ?>>Конференция</option>
        <option value="sport"      <?= $filterCategory==='sport'?'selected':'' ?>>Спорт</option>
        <option value="art"        <?= $filterCategory==='art'?'selected':'' ?>>Творчество</option>
        <option value="science"    <?= $filterCategory==='science'?'selected':'' ?>>Наука</option>
        <option value="other"      <?= $filterCategory==='other'?'selected':'' ?>>Другое</option>
      </select>
    </div>
    <div style="display:flex;gap:.5rem">
      <button type="submit" class="btn btn-primary">Найти</button>
      <a href="achievements.php?tab=<?= h($activeTab) ?>" class="btn btn-secondary">Сбросить</a>
    </div>
  </form>
</div>

<div class="card anim-fade">
  <div style="display:flex;border-bottom:1px solid var(--border)">
    <?php
    $tabs = [
      'achievements' => [' Достижения', $totalAch],
      'certs'        => [' Сертификаты', $totalCert],
    ];
    foreach ($tabs as $tid => [$tlabel, $tcount]):
      $isActive = $activeTab === $tid;
    ?>
    <a href="achievements.php?tab=<?= $tid ?>&q=<?= urlencode($filterSearch) ?>&group_id=<?= $filterGroup ?>&category=<?= urlencode($filterCategory) ?>"
       style="padding:.75rem 1.25rem;font-size:.8125rem;font-weight:600;
              border-bottom:2px solid <?= $isActive?'var(--blue)':'transparent' ?>;
              color:<?= $isActive?'var(--blue)':'var(--text-m)' ?>;
              margin-bottom:-1px;text-decoration:none">
      <?= $tlabel ?>
      <span class="badge badge-<?= $isActive?'blue':'gray' ?>" style="margin-left:4px"><?= $tcount ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap">
  <?php if ($activeTab === 'achievements'): ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th><th>Студент / Преподаватель</th><th>Группа</th>
          <th>Достижение</th><th>Категория</th><th>Уровень</th>
          <th>Место</th><th>Дата</th><th>Файл</th>
          <?php if (in_array($role,['admin','teacher','director'])): ?><th>Действия</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php
        $allAchs = array_merge(
            array_map(fn($r) => $r + ['_type'=>'student'], $studentAchs),
            array_map(fn($r) => $r + ['_type'=>'teacher'], $teacherAchs)
        );
        if (empty($allAchs)):
      ?>
        <tr><td colspan="10" style="text-align:center;padding:var(--space-10);color:var(--text-m)">Достижений не найдено</td></tr>
      <?php else: foreach ($allAchs as $i => $a):
        $lc = $lvlColors[$a['level']??''] ?? 'gray';
        $isT = $a['_type'] === 'teacher';
        $rc  = $isT ? 'blue' : 'green';
        $rt  = $isT ? 'Преподаватель' : 'Студент';
      ?>
        <tr>
          <td><span class="badge badge-gray"><?= $i+1 ?></span></td>
          <td>
            <span style="font-weight:600"><?= h($a['full_name']) ?></span>
            <span class="badge badge-<?= $rc ?>" style="margin-left:4px;font-size:.65rem"><?= $rt ?></span>
          </td>
          <td><?= h($a['group_name']??'—') ?></td>
          <td>
            <div style="font-weight:600"><?= h($a['title']) ?></div>
            <?php if (!empty($a['description'])): ?>
              <div style="font-size:.72rem;color:var(--text-m)"><?= h(mb_strimwidth($a['description'],0,50,'…')) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-blue"><?= categoryLabel($a['category']??'') ?></span></td>
          <td><span class="badge badge-<?= $lc ?>"><?= levelLabel($a['level']??'') ?></span></td>
          <td><?= $a['place'] ? '<span class="badge badge-amber"> '.h($a['place']).'</span>' : '—' ?></td>
          <td><?= h($a['date_event']??'—') ?></td>
          <td>
            <?php if (!empty($a['file_path'])): ?>
              <div style="display:flex;gap:.35rem">
                <a href="<?= SITE_URL ?>/uploads/<?= h($a['file_path']) ?>"
                   target="_blank" class="btn btn-secondary btn-sm" title="Просмотреть">👁</a>
                <a href="<?= SITE_URL ?>/uploads/<?= h($a['file_path']) ?>"
                   target="_blank" class="btn btn-secondary btn-sm" download title="Скачать">⬇</a>
              </div>
            <?php else: ?>—<?php endif; ?>
          </td>
          <?php if (in_array($role,['admin','teacher','director'])): ?>
          <td>
            <a href="<?= SITE_URL ?>/actions/achievement_delete.php?id=<?= $a['id'] ?>&user_id=<?= $a['user_id']??0 ?>&tab=achievements"
               class="btn btn-danger btn-sm" onclick="return confirm('Удалить?')">🗑</a>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>

  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th><th>Студент / Преподаватель</th><th>Тип / Название</th>
          <th>Мероприятие</th><th>Выдавшая орг.</th><th>Уровень</th>
          <th>Результат</th><th>Дата</th><th>Файл</th>
          <?php if (in_array($role,['admin','teacher','director'])): ?><th>Действия</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php
        $allCerts = array_merge(
            array_map(fn($r) => $r + ['_type'=>'student'], $studentCerts),
            array_map(fn($r) => $r + ['_type'=>'teacher'], $teacherCerts)
        );
        if (empty($allCerts)):
      ?>
        <tr><td colspan="9" style="text-align:center;padding:var(--space-10);color:var(--text-m)">Сертификатов не найдено</td></tr>
      <?php else: foreach ($allCerts as $i => $c):
        $isT = $c['_type'] === 'teacher';
        $rc  = $isT ? 'blue' : 'green';
        $rt  = $isT ? 'Преподаватель' : 'Студент';
      ?>
        <tr>
          <td><span class="badge badge-gray"><?= $i+1 ?></span></td>
          <td>
            <span style="font-weight:600"><?= h($c['full_name']??'—') ?></span>
            <?php if (!empty($c['group_name'])): ?>
              <div style="font-size:.72rem;color:var(--text-m)"><?= h($c['group_name']) ?></div>
            <?php endif; ?>
            <span class="badge badge-<?= $rc ?>" style="font-size:.65rem"><?= $rt ?></span>
          </td>
          <td>
            <div style="font-weight:600;font-size:.8125rem"><?= h($c['title']??'—') ?></div>
            <?php if (!empty($c['nomination'])): ?>
              <div style="font-size:.72rem;color:var(--text-m)"><?= h($c['nomination']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;max-width:200px">
            <?php if (!empty($c['event_name'])): ?>
              <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px" title="<?= h($c['event_name']) ?>">
                <?= h(mb_strimwidth($c['event_name'],0,40,'…')) ?>
              </div>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td style="font-size:.8rem;max-width:180px">
            <?php $org = $c['issuer'] ?? ''; ?>
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px" title="<?= h($org) ?>">
              <?= $org ? h(mb_strimwidth($org,0,35,'…')) : '—' ?>
            </div>
            <?php if (!empty($c['recipient_org'])): ?>
              <div style="font-size:.7rem;color:var(--text-m);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px" title="<?= h($c['recipient_org']) ?>">
                <?= h(mb_strimwidth($c['recipient_org'],0,35,'…')) ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <?php
              $lvl = $c['level'] ?? '';
              $lvlLabel = ['college'=>'Колледж','city'=>'Город','regional'=>'Область','national'=>'Республика','international'=>'Международный'][$lvl] ?? $lvl;
              $lvlColor = ['international'=>'purple','national'=>'blue','regional'=>'green','city'=>'amber','college'=>'gray'][$lvl] ?? 'gray';
            ?>
            <?= $lvl ? '<span class="badge badge-'.$lvlColor.'">'.$lvlLabel.'</span>' : '—' ?>
          </td>
          <td><?= !empty($c['place']) ? '<span class="badge badge-amber">'.h($c['place']).'</span>' : '—' ?></td>
          <td style="font-size:.8rem"><?= h($c['issue_date']??'—') ?></td>
          <td>
            <?php if (!empty($c['file_path'])): ?>
              <div style="display:flex;gap:.35rem">
                <a href="<?= SITE_URL ?>/uploads/<?= h($c['file_path']) ?>"
                   target="_blank" class="btn btn-secondary btn-sm" title="Просмотреть">👁</a>
                <a href="<?= SITE_URL ?>/uploads/<?= h($c['file_path']) ?>"
                   target="_blank" class="btn btn-secondary btn-sm" download title="Скачать">⬇</a>
              </div>
            <?php else: ?>—<?php endif; ?>
          </td>
          <?php if (in_array($role,['admin','teacher','director'])): ?>
          <td>
            <div style="display:flex;gap:.375rem">
              <form method="POST" action="<?= SITE_URL ?>/actions/cert_duplicate.php" style="display:inline">
                <input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm" title="Дублировать">📋</button>
              </form>
              <a href="<?= SITE_URL ?>/actions/cert_delete.php?id=<?= $c['id'] ?>&user_id=<?= $c['user_id']??0 ?>"
                 class="btn btn-danger btn-sm" onclick="return confirm('Удалить?')">🗑</a>
            </div>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>
</div>

<?php if (in_array($role, ['admin','teacher','director'])): ?>

<!-- ============================================================ -->
<!-- Modal: Добавить достижение                                   -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modal-ach-add">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"> Добавить достижение</div>
      <button class="modal-close" onclick="closeModal('modal-ach-add')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/achievement_save.php" enctype="multipart/form-data">
        <input type="hidden" name="tab" value="achievements">

        <!-- Студент или Преподаватель -->
        <div class="form-group">
          <div style="display:flex;gap:.75rem;margin-bottom:.75rem">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="radio" name="ach_ptype" value="student" checked onchange="switchAchType('student')"> Студент
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="radio" name="ach_ptype" value="teacher" onchange="switchAchType('teacher')"> Преподаватель
            </label>
          </div>
        </div>

        <!-- Поиск студента -->
        <div id="ach-block-student">
          <div class="form-group">
            <label class="form-label">Поиск студента</label>
            <input type="text" id="ach-student-search" class="form-control"
                   placeholder="Введите фамилию или имя…" autocomplete="off"
                   oninput="searchPerson('ach-student-search','ach-student-results','ach-student-id','ach-student-selected','students')">
            <div id="ach-student-results" class="search-dropdown"></div>
          </div>
          <input type="hidden" name="edu_student_id" id="ach-student-id" value="">
          <div id="ach-student-selected" class="selected-person" style="display:none"></div>
          <!-- Соавторы-студенты -->
          <div class="form-group">
            <label class="form-label">Ещё студенты <span style="font-size:.75rem;color:var(--text-m);font-weight:400">(если достижение совместное)</span></label>
            <div id="ach-co-students-list" style="display:flex;flex-direction:column;gap:.4rem;margin-bottom:.4rem"></div>
            <div style="position:relative">
              <input type="text" id="ach-co-student-search" class="form-control"
                     placeholder="Добавить ещё студента…" autocomplete="off"
                     oninput="searchAchCoStudent(this.value)">
              <div id="ach-co-student-results" class="search-dropdown"></div>
            </div>
          </div>
          <div id="ach-co-student-ids"></div>
        </div>

        <!-- Поиск преподавателя -->
        <div id="ach-block-teacher" style="display:none">
          <div class="form-group">
            <label class="form-label">Поиск преподавателя</label>
            <input type="text" id="ach-teacher-search" class="form-control"
                   placeholder="Введите фамилию…" autocomplete="off"
                   oninput="searchPerson('ach-teacher-search','ach-teacher-results','ach-teacher-id','ach-teacher-selected','teachers')">
            <div id="ach-teacher-results" class="search-dropdown"></div>
          </div>
          <input type="hidden" name="user_id" id="ach-teacher-id" value="">
          <div id="ach-teacher-selected" class="selected-person" style="display:none"></div>
          <!-- Соавторы-преподаватели -->
          <div class="form-group">
            <label class="form-label">Ещё преподаватели <span style="font-size:.75rem;color:var(--text-m);font-weight:400">(если достижение совместное)</span></label>
            <div id="ach-co-teachers-list" style="display:flex;flex-direction:column;gap:.4rem;margin-bottom:.4rem"></div>
            <div style="position:relative">
              <input type="text" id="ach-co-teacher-search" class="form-control"
                     placeholder="Добавить ещё преподавателя…" autocomplete="off"
                     oninput="searchAchCoTeacher(this.value)">
              <div id="ach-co-teacher-results" class="search-dropdown"></div>
            </div>
          </div>
          <div id="ach-co-teacher-ids"></div>
        </div>

        <!-- Поля достижения -->
        <div class="form-group">
          <label class="form-label">Название достижения</label>
          <input type="text" name="title" class="form-control" placeholder="Олимпиада по программированию" required>
        </div>
        <div class="form-group">
          <label class="form-label">Описание</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Краткое описание…"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Категория</label>
            <select name="category" class="form-control" required>
              <option value="olympiad">Олимпиада</option>
              <option value="conference">Конференция</option>
              <option value="sport">Спорт</option>
              <option value="art">Творчество</option>
              <option value="science">Наука</option>
              <option value="other">Другое</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Уровень</label>
            <select name="level" class="form-control" required>
              <option value="college">Колледж</option>
              <option value="city">Город</option>
              <option value="regional">Регион</option>
              <option value="national">Республика</option>
              <option value="international">Международный</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Место (1, 2, 3…)</label>
            <input type="number" name="place" class="form-control" min="1" max="99" placeholder="Необязательно">
          </div>
          <div class="form-group">
            <label class="form-label">Дата</label>
            <input type="date" name="date_event" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">PDF / Фото грамоты</label>
          <input type="file" name="pdf_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="height:auto;padding:.5rem">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Сохранить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-ach-add')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- Modal: Добавить сертификат                                   -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modal-cert-add">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"> Добавить сертификат</div>
      <button class="modal-close" onclick="closeModal('modal-cert-add')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/cert_save.php" enctype="multipart/form-data">
        <div class="form-group">
          <div style="display:flex;gap:.75rem;margin-bottom:.75rem">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="radio" name="cert_ptype" value="student" checked onchange="switchCertType('student')"> Студент
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="radio" name="cert_ptype" value="user" onchange="switchCertType('user')"> Преподаватель
            </label>
          </div>
        </div>
        <input type="hidden" name="cert_type" id="cert-type-val" value="student">

        <!-- Поиск студента -->
        <div id="cert-block-student">
          <div class="form-group">
            <label class="form-label">Поиск студента</label>
            <input type="text" id="cert-student-search" class="form-control"
                   placeholder="Введите фамилию или имя…" autocomplete="off"
                   oninput="searchPerson('cert-student-search','cert-student-results','cert-student-id','cert-student-selected','students')">
            <div id="cert-student-results" class="search-dropdown"></div>
          </div>
          <input type="hidden" name="edu_student_id" id="cert-student-id" value="">
          <div id="cert-student-selected" class="selected-person" style="display:none"></div>
          <!-- Соавторы-студенты -->
          <div class="form-group">
            <label class="form-label">Ещё студенты <span style="font-size:.75rem;color:var(--text-m);font-weight:400">(если сертификат на нескольких)</span></label>
            <div id="cert-co-students-list" style="display:flex;flex-direction:column;gap:.4rem;margin-bottom:.4rem"></div>
            <div style="position:relative">
              <input type="text" id="cert-co-student-search" class="form-control"
                     placeholder="Добавить ещё студента…" autocomplete="off"
                     oninput="searchCertCoStudent(this.value)">
              <div id="cert-co-student-results" class="search-dropdown"></div>
            </div>
          </div>
          <div id="cert-co-student-ids"></div>
        </div>

        <!-- Поиск преподавателя -->
        <div id="cert-block-user" style="display:none">
          <div class="form-group">
            <label class="form-label">Преподаватель</label>
            <input type="text" id="cert-teacher-search" class="form-control"
                   placeholder="Введите фамилию…" autocomplete="off"
                   oninput="searchPerson('cert-teacher-search','cert-teacher-results','cert-user-id','cert-teacher-selected','teachers')">
            <div id="cert-teacher-results" class="search-dropdown"></div>
          </div>
          <input type="hidden" name="user_id" id="cert-user-id" value="">
          <div id="cert-teacher-selected" class="selected-person" style="display:none"></div>

          <!-- Совместные владельцы -->
          <div class="form-group">
            <label class="form-label">
              Ещё преподаватели
              <span style="font-size:.75rem;color:var(--text-m);font-weight:400">(если документ на нескольких)</span>
            </label>
            <div id="co-owners-list" style="display:flex;flex-direction:column;gap:.4rem;margin-bottom:.4rem"></div>
            <div style="position:relative">
              <input type="text" id="co-owner-search" class="form-control"
                     placeholder="Добавить ещё преподавателя…" autocomplete="off"
                     oninput="searchCoOwner(this.value)">
              <div id="co-owner-results" class="search-dropdown"></div>
            </div>
          </div>
          <div id="co-owner-ids"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Название сертификата</label>
          <input type="text" name="title" class="form-control" placeholder="Веб-разработка на Python" required>
        </div>
        <div class="form-group">
          <label class="form-label">Организация / выдал</label>
          <input type="text" name="issuer" class="form-control" placeholder="Coursera, Stepik, РЦРО…">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Дата выдачи</label>
            <input type="date" name="issue_date" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Место / результат</label>
            <input type="text" name="place" class="form-control" placeholder="1 место, Диплом I степени…">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">PDF / JPG / PNG файл</label>
          <input type="file" name="pdf_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="height:auto;padding:.5rem">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Сохранить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-cert-add')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- Modal: Загрузить PDF                                         -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modal-cert-pdf">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"> Загрузить документ — автораспознавание</div>
      <button class="modal-close" onclick="closeModal('modal-cert-pdf')">✕</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info"> Система автоматически распознает данные из PDF/фото грамоты.</div>
      <form method="POST" action="<?= SITE_URL ?>/actions/cert_pdf_parse.php" enctype="multipart/form-data">
        <div class="form-group">
          <div style="display:flex;gap:.75rem;margin-bottom:.75rem">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="radio" name="pdf_ptype" value="student" checked onchange="switchPdfType('student')"> Студент
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="radio" name="pdf_ptype" value="teacher" onchange="switchPdfType('teacher')"> Преподаватель
            </label>
          </div>
        </div>

        <!-- Поиск студента -->
        <div id="pdf-block-student">
          <div class="form-group">
            <label class="form-label">Поиск студента</label>
            <input type="text" id="pdf-student-search" class="form-control"
                   placeholder="Введите фамилию или имя…" autocomplete="off"
                   oninput="searchPerson('pdf-student-search','pdf-student-results','pdf-student-id','pdf-student-selected','students')">
            <div id="pdf-student-results" class="search-dropdown"></div>
          </div>
          <input type="hidden" name="edu_student_id" id="pdf-student-id" value="">
          <div id="pdf-student-selected" class="selected-person" style="display:none"></div>
        </div>

        <!-- Поиск преподавателя -->
        <div id="pdf-block-teacher" style="display:none">
          <div class="form-group">
            <label class="form-label">Поиск преподавателя</label>
            <input type="text" id="pdf-teacher-search" class="form-control"
                   placeholder="Введите фамилию…" autocomplete="off"
                   oninput="searchPerson('pdf-teacher-search','pdf-teacher-results','pdf-teacher-id','pdf-teacher-selected','teachers')">
            <div id="pdf-teacher-results" class="search-dropdown"></div>
          </div>
          <input type="hidden" name="pdf_user_id" id="pdf-teacher-id" value="">
          <div id="pdf-teacher-selected" class="selected-person" style="display:none"></div>
        </div>

        <div class="form-group">
          <label class="form-label">PDF / JPG / PNG файл</label>
          <input type="file" name="pdf_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="height:auto;padding:.5rem">
          <div style="font-size:.72rem;color:var(--text-m);margin-top:4px">Макс. 10 МБ</div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"> Загрузить и распознать</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-cert-pdf')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<style>
.search-dropdown {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  max-height: 200px;
  overflow-y: auto;
  display: none;
  background: var(--color-surface);
  box-shadow: var(--shadow-md);
  margin-top: 2px;
}
.search-dropdown .search-item {
  padding: .5rem .75rem;
  cursor: pointer;
  font-size: .875rem;
  border-bottom: 1px solid var(--color-divider);
}
.search-dropdown .search-item:hover { background: var(--color-surface-offset); }
.search-dropdown .search-item:last-child { border-bottom: none; }
.selected-person {
  padding: .5rem .75rem;
  background: var(--color-primary-highlight);
  border-radius: var(--radius-md);
  font-size: .875rem;
  color: var(--color-primary);
  margin-bottom: .75rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.selected-person .clear-btn {
  color: var(--color-text-muted);
  cursor: pointer;
  font-size: 1rem;
  background: none;
  border: none;
  padding: 0;
}
</style>

<script>
// Данные для поиска
const allStudentsData = <?= json_encode(array_map(fn($s) => [
    'id'    => $s['id'],
    'name'  => $s['full_name'],
    'group' => $s['group_name'] ?? ''
], $allStudents)) ?>;

const allTeachersData = <?= json_encode(array_map(fn($t) => [
    'id'   => $t['id'],
    'name' => $t['full_name']
], $allTeachers)) ?>;

// Универсальная функция поиска
function searchPerson(inputId, resultsId, hiddenId, selectedId, type) {
    const q     = document.getElementById(inputId).value.trim().toLowerCase();
    const box   = document.getElementById(resultsId);
    const data  = type === 'students' ? allStudentsData : allTeachersData;

    if (q.length < 2) { box.style.display = 'none'; return; }

    const results = data.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.group && p.group.toLowerCase().includes(q))
    ).slice(0, 10);

    if (!results.length) { box.style.display = 'none'; return; }

    box.innerHTML = results.map(p => `
        <div class="search-item" onclick="selectPerson(${p.id}, '${p.name.replace(/'/g,"\\'")}', '${(p.group||'').replace(/'/g,"\\'")}', '${inputId}', '${resultsId}', '${hiddenId}', '${selectedId}')">
            <strong>${p.name}</strong>${p.group ? ' <span style="color:var(--color-text-muted);font-size:.8rem">— ' + p.group + '</span>' : ''}
        </div>
    `).join('');
    box.style.display = 'block';
}

function selectPerson(id, name, group, inputId, resultsId, hiddenId, selectedId) {
    document.getElementById(hiddenId).value = id;
    document.getElementById(inputId).value  = '';
    document.getElementById(resultsId).style.display = 'none';

    const sel = document.getElementById(selectedId);
    sel.style.display = 'flex';
    sel.innerHTML = '<span> ' + name + (group ? ' — ' + group : '') + '</span>'
        + '<button type="button" class="clear-btn" onclick="clearPerson(\'' + hiddenId + '\',\'' + selectedId + '\')">✕</button>';
}

function clearPerson(hiddenId, selectedId) {
    document.getElementById(hiddenId).value = '';
    document.getElementById(selectedId).style.display = 'none';
}

// Переключение типов
function switchAchType(type) {
    document.getElementById('ach-block-student').style.display = type === 'student' ? '' : 'none';
    document.getElementById('ach-block-teacher').style.display = type === 'teacher' ? '' : 'none';
}

function switchCertType(type) {
    document.getElementById('cert-type-val').value = type;
    document.getElementById('cert-block-student').style.display = type === 'student' ? '' : 'none';
    document.getElementById('cert-block-user').style.display    = type === 'user'    ? '' : 'none';
}

function switchPdfType(type) {
    document.getElementById('pdf-block-student').style.display = type === 'student' ? '' : 'none';
    document.getElementById('pdf-block-teacher').style.display = type === 'teacher' ? '' : 'none';
}

// Закрываем дропдаун при клике вне
document.addEventListener('click', function(e) {
    document.querySelectorAll('.search-dropdown').forEach(d => {
        if (!d.previousElementSibling?.contains(e.target)) d.style.display = 'none';
    });
});

// ─── Совместные владельцы сертификата ────────────────────────
const coOwners = []; // [{id, name}]

function searchCoOwner(q) {
    const box = document.getElementById('co-owner-results');
    q = q.trim().toLowerCase();
    if (!q) { box.style.display='none'; return; }
    const used = new Set(coOwners.map(o=>o.id));
    const mainId = parseInt(document.getElementById('cert-user-id').value||0);
    if (mainId) used.add(mainId);
    const matches = allUsers.filter(u =>
        u.full_name.toLowerCase().includes(q) && !used.has(u.id)
    ).slice(0,8);
    if (!matches.length) {
        box.innerHTML='<div style="padding:.5rem .75rem;font-size:.8125rem;color:var(--text-m)">Не найдено</div>';
    } else {
        box.innerHTML = matches.map(u=>
            `<div style="padding:.45rem .75rem;font-size:.8125rem;cursor:pointer;border-bottom:1px solid var(--border)"
                  onmousedown="addCoOwner(${u.id},'${u.full_name.replace(/'/g,"\\'")}')">
               ${u.full_name}
             </div>`
        ).join('');
    }
    box.style.display='block';
}

function addCoOwner(id, name) {
    if (coOwners.find(o=>o.id===id)) return;
    coOwners.push({id, name});
    renderCoOwners();
    document.getElementById('co-owner-search').value='';
    document.getElementById('co-owner-results').style.display='none';
}

function removeCoOwner(id) {
    const idx = coOwners.findIndex(o=>o.id===id);
    if (idx>=0) coOwners.splice(idx,1);
    renderCoOwners();
}

function renderCoOwners() {
    const list = document.getElementById('co-owners-list');
    const ids  = document.getElementById('co-owner-ids');
    list.innerHTML = coOwners.map(o=>
        `<div style="display:flex;align-items:center;justify-content:space-between;padding:.35rem .6rem;background:var(--blue-bg);border:1px solid var(--blue-bd);border-radius:var(--r-md);font-size:.8rem">
           <span>👤 ${o.name}</span>
           <button type="button" onclick="removeCoOwner(${o.id})"
                   style="background:none;border:none;cursor:pointer;color:var(--text-m);font-size:.9rem">✕</button>
         </div>`
    ).join('');
    ids.innerHTML = coOwners.map(o=>
        `<input type="hidden" name="co_owner_ids[]" value="${o.id}">`
    ).join('');
}

// ─── Соавторы достижений и сертификатов ──────────────────────
const achCoStudents  = [];
const achCoTeachers  = [];
const certCoStudents = [];

function makeCoSearch(inputId, resultsId, arr, dataArr, nameKey, addFn) {
    return function(q) {
        const box = document.getElementById(resultsId);
        q = q.trim().toLowerCase();
        if (!q) { box.style.display='none'; return; }
        const used = new Set(arr.map(o=>o.id));
        const matches = dataArr.filter(d=>(d[nameKey]||'').toLowerCase().includes(q)&&!used.has(d.id)).slice(0,8);
        if (!matches.length) {
            box.innerHTML='<div style="padding:.5rem .75rem;font-size:.8125rem;color:var(--text-m)">Не найдено</div>';
        } else {
            box.innerHTML=matches.map(d=>
                `<div style="padding:.45rem .75rem;font-size:.8125rem;cursor:pointer;border-bottom:1px solid var(--border)"
                      onmousedown="${addFn}(${d.id},'${(d[nameKey]||'').replace(/'/g,"\\'")}')">
                   ${d[nameKey]}${d.group_name?' — '+d.group_name:''}
                 </div>`
            ).join('');
        }
        box.style.display='block';
    };
}

function makeRender(arr, listId, idsId, removeFn, color, fieldName) {
    return function() {
        const list=document.getElementById(listId);
        const ids=document.getElementById(idsId);
        if(!list||!ids) return;
        list.innerHTML=arr.map(o=>
            `<div style="display:flex;align-items:center;justify-content:space-between;padding:.35rem .6rem;background:var(--${color}-bg);border:1px solid var(--${color}-bd);border-radius:var(--r-md);font-size:.8rem">
               <span>👤 ${o.name}</span>
               <button type="button" onclick="${removeFn}(${o.id})" style="background:none;border:none;cursor:pointer;color:var(--text-m)">✕</button>
             </div>`
        ).join('');
        ids.innerHTML=arr.map(o=>`<input type="hidden" name="${fieldName}" value="${o.id}">`).join('');
    };
}

// Достижения — студенты
const renderAchCoStudents=makeRender(achCoStudents,'ach-co-students-list','ach-co-student-ids','removeAchCoStudent','green','ach_co_student_ids[]');
function addAchCoStudent(id,name){if(!achCoStudents.find(o=>o.id===id)){achCoStudents.push({id,name});renderAchCoStudents();document.getElementById('ach-co-student-search').value='';document.getElementById('ach-co-student-results').style.display='none';}}
function removeAchCoStudent(id){const i=achCoStudents.findIndex(o=>o.id===id);if(i>=0)achCoStudents.splice(i,1);renderAchCoStudents();}
const searchAchCoStudent=makeCoSearch('ach-co-student-search','ach-co-student-results',achCoStudents,allStudents,'full_name','addAchCoStudent');

// Достижения — преподаватели
const renderAchCoTeachers=makeRender(achCoTeachers,'ach-co-teachers-list','ach-co-teacher-ids','removeAchCoTeacher','blue','ach_co_teacher_ids[]');
function addAchCoTeacher(id,name){if(!achCoTeachers.find(o=>o.id===id)){achCoTeachers.push({id,name});renderAchCoTeachers();document.getElementById('ach-co-teacher-search').value='';document.getElementById('ach-co-teacher-results').style.display='none';}}
function removeAchCoTeacher(id){const i=achCoTeachers.findIndex(o=>o.id===id);if(i>=0)achCoTeachers.splice(i,1);renderAchCoTeachers();}
const searchAchCoTeacher=makeCoSearch('ach-co-teacher-search','ach-co-teacher-results',achCoTeachers,allUsers,'full_name','addAchCoTeacher');

// Сертификат — студенты
const renderCertCoStudents=makeRender(certCoStudents,'cert-co-students-list','cert-co-student-ids','removeCertCoStudent','green','cert_co_student_ids[]');
function addCertCoStudent(id,name){if(!certCoStudents.find(o=>o.id===id)){certCoStudents.push({id,name});renderCertCoStudents();document.getElementById('cert-co-student-search').value='';document.getElementById('cert-co-student-results').style.display='none';}}
function removeCertCoStudent(id){const i=certCoStudents.findIndex(o=>o.id===id);if(i>=0)certCoStudents.splice(i,1);renderCertCoStudents();}
const searchCertCoStudent=makeCoSearch('cert-co-student-search','cert-co-student-results',certCoStudents,allStudents,'full_name','addCertCoStudent');
</script>

<?php require_once 'includes/footer.php'; ?>