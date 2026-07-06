<?php
require_once 'includes/header.php';

if ($role === 'student') {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$pdo   = getPDO();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$filterSearch = trim($_GET['q'] ?? '');
$activeTab    = $_GET['ctab'] ?? 'students';

try {
    $hasEduColC = (bool)$pdo->query("SHOW COLUMNS FROM certificates LIKE 'edu_student_id'")->fetch();
} catch(Exception $e) { $hasEduColC = false; }

if ($hasEduColC) {
    $sql = "SELECT c.*,
        COALESCE(
            CONCAT(es.surname,' ',es.name,
                IF(es.patronymic!='' AND es.patronymic IS NOT NULL,
                   CONCAT(' ',es.patronymic),'')),
            u.full_name,
            'Неизвестно'
        ) AS full_name,
        COALESCE(u.role, 'student') AS user_role,
        g.name AS group_name,
        curator.full_name AS curator_name,
        added.full_name AS added_by_name,
        IF(c.edu_student_id IS NOT NULL, 1, 0) AS is_edu_student
        FROM certificates c
        LEFT JOIN edu_students es ON c.edu_student_id = es.id
        LEFT JOIN edu_groups g ON es.group_id = g.id
        LEFT JOIN users curator ON g.curator_id = curator.id
        LEFT JOIN users u ON c.user_id = u.id AND c.user_id != 0
        LEFT JOIN users added ON c.added_by = added.id
        WHERE 1=1
        ORDER BY c.created_at DESC";
    $certs = $pdo->prepare($sql);
    $certs->execute();
    $allCerts = $certs->fetchAll();
} else {
    $certs = $pdo->prepare("SELECT c.*, u.full_name, u.role AS user_role,
        g.name AS group_name, added.full_name AS added_by_name,
        NULL AS curator_name,
        0 AS is_edu_student
        FROM certificates c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN users added ON c.added_by = added.id
        ORDER BY c.created_at DESC");
    $certs->execute();
    $allCerts = $certs->fetchAll();
}

if ($role === 'teacher') {
    $studentCerts = array_values(array_filter($allCerts,
        fn($c) => !empty($c['edu_student_id']) || !empty($c['is_edu_student'])
    ));
    $myCerts = array_values(array_filter($allCerts,
        fn($c) => empty($c['edu_student_id']) && empty($c['is_edu_student'])
               && ($c['user_id'] == $user['id'] || ($c['added_by'] ?? 0) == $user['id'])
    ));
} else {
    $studentCerts = $allCerts;
    $myCerts      = [];
}

$allUsers = $pdo->query("SELECT id, full_name, role FROM users
    WHERE role IN ('teacher','admin','director')
    ORDER BY full_name")->fetchAll();

try {
    $allEduStudents = $pdo->query("SELECT
        es.id,
        CONCAT(es.surname,' ',es.name,
            IF(es.patronymic!='' AND es.patronymic IS NOT NULL,
               CONCAT(' ',es.patronymic),'')) AS full_name,
        g.name AS group_name,
        g.id AS group_id
        FROM edu_students es
        LEFT JOIN edu_groups g ON es.group_id = g.id
        ORDER BY g.name, es.surname, es.name")->fetchAll();
} catch (Exception $ex) { $allEduStudents = []; }

try {
    $allGroups = $pdo->query("SELECT id, name FROM edu_groups ORDER BY name")->fetchAll();
} catch (Exception $ex) { $allGroups = []; }
?>

<?php if ($flash): ?>
<div class="alert alert-success anim-fade">✅ <?= h($flash) ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert alert-error anim-fade">⚠️ <?= h($_SESSION['flash_error']) ?></div>
<?php unset($_SESSION['flash_error']); endif; ?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">📜 Сертификаты</div>
    <div class="page-header-sub">
      Сертификаты · найдено: <?= count($allCerts) ?>
    </div>
  </div>
  <?php if (in_array($role, ['admin','teacher','director'])): ?>
  <div style="display:flex;gap:.75rem">
    <a href="<?= SITE_URL ?>/cert_review.php" class="btn btn-secondary">📤 Загрузить PDF</a>
    <button class="btn btn-primary" onclick="openModal('modal-cert-add')">+ Добавить</button>
  </div>
  <?php endif; ?>
</div>

<div class="card anim-fade" style="padding:var(--space-4);margin-bottom:var(--space-5)">
  <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="ctab" value="<?= h($activeTab) ?>">
    <div class="form-group" style="margin:0;flex:2;min-width:200px">
      <label class="form-label">Поиск</label>
      <input type="text" name="q" class="form-control"
             placeholder="Название, организация, ФИО…" value="<?= h($filterSearch) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:180px">
      <label class="form-label">Группа</label>
      <select name="group_id" class="form-control">
        <option value="">Все группы</option>
        <?php foreach ($allGroups as $g): ?>
          <option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;gap:.5rem">
      <button type="submit" class="btn btn-primary">Найти</button>
      <a href="certificates.php" class="btn btn-secondary">Сбросить</a>
    </div>
  </form>
</div>

<div class="card anim-fade">

  <?php if ($role === 'teacher'): ?>
  <div style="display:flex;border-bottom:1px solid var(--border)">
    <a href="certificates.php?ctab=students"
       style="padding:.75rem 1.25rem;font-size:.8125rem;font-weight:600;
              border-bottom:2px solid <?= $activeTab==='students'?'var(--blue)':'transparent' ?>;
              color:<?= $activeTab==='students'?'var(--blue)':'var(--text-m)' ?>;
              margin-bottom:-1px;text-decoration:none">
      🎓 Студенты
      <span class="badge badge-<?= $activeTab==='students'?'blue':'gray' ?>" style="margin-left:4px"><?= count($studentCerts) ?></span>
    </a>
    <a href="certificates.php?ctab=my"
       style="padding:.75rem 1.25rem;font-size:.8125rem;font-weight:600;
              border-bottom:2px solid <?= $activeTab==='my'?'var(--blue)':'transparent' ?>;
              color:<?= $activeTab==='my'?'var(--blue)':'var(--text-m)' ?>;
              margin-bottom:-1px;text-decoration:none">
      🏅 Мои сертификаты
      <span class="badge badge-<?= $activeTab==='my'?'blue':'gray' ?>" style="margin-left:4px"><?= count($myCerts) ?></span>
    </a>
  </div>
  <?php endif; ?>

  <div class="table-wrap">
    <?php
      $displayCerts = ($role === 'teacher')
        ? ($activeTab === 'my' ? $myCerts : $studentCerts)
        : $allCerts;
    ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Студент / Преподаватель</th>
          <th>Тип / Название</th>
          <th>Мероприятие</th>
          <th>Выдавшая орг.</th>
          <th>Уровень</th>
          <th>Результат</th>
          <th>Дата</th>
          <th>Файл</th>
          <?php if (in_array($role,['admin','teacher','director'])): ?><th>Действия</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($displayCerts)): ?>
        <tr><td colspan="10" style="text-align:center;padding:var(--space-10);color:var(--text-m)">
          <?= ($role==='teacher' && $activeTab==='my') ? 'У вас пока нет сертификатов' : 'Сертификатов не найдено' ?>
        </td></tr>
      <?php else: ?>
      <?php foreach ($displayCerts as $i => $c):
        $isEduSt = !empty($c['is_edu_student']) || !empty($c['edu_student_id']);
        $isT     = !$isEduSt && in_array($c['user_role'] ?? '', ['teacher','admin','director']);
        $rc      = $isT ? 'blue' : 'green';
        $rt      = $isT ? 'Преподаватель' : 'Студент';
        $lvl     = $c['level'] ?? '';
        $lvlLabel = ['college'=>'Колледж','city'=>'Город','regional'=>'Область','national'=>'Республика','international'=>'Международный'][$lvl] ?? $lvl;
        $lvlColor = ['international'=>'purple','national'=>'blue','regional'=>'green','city'=>'amber','college'=>'gray'][$lvl] ?? 'gray';
      ?>
        <tr class="anim-fade" style="animation-delay:<?= $i*0.02 ?>s">
          <td><span class="badge badge-gray"><?= $i+1 ?></span></td>
          <td>
            <span style="font-weight:600"><?= h($c['full_name'] ?: '—') ?></span>
            <?php if (!empty($c['group_name'])): ?>
              <div style="font-size:.72rem;color:var(--text-m)"><?= h($c['group_name']) ?></div>
            <?php endif; ?>
            <span class="badge badge-<?= $rc ?>" style="margin-left:4px;font-size:.65rem"><?= $rt ?></span>
          </td>
          <td>
            <strong><?= h($c['title'] ?? '—') ?></strong>
            <?php if (!empty($c['nomination'])): ?>
              <div style="font-size:.72rem;color:var(--text-m)"><?= h($c['nomination']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;max-width:180px">
            <?php if (!empty($c['event_name'])): ?>
              <span title="<?= h($c['event_name']) ?>"><?= h(mb_strimwidth($c['event_name'],0,35,'…')) ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td style="font-size:.8rem;max-width:160px">
            <?php $org = $c['issuer'] ?? ''; ?>
            <?= $org ? '<span title="'.h($org).'">'.h(mb_strimwidth($org,0,30,'…')).'</span>' : '—' ?>
            <?php if (!empty($c['recipient_org'])): ?>
              <div style="font-size:.7rem;color:var(--text-m)" title="<?= h($c['recipient_org']) ?>"><?= h(mb_strimwidth($c['recipient_org'],0,30,'…')) ?></div>
            <?php endif; ?>
          </td>
          <td><?= $lvl ? '<span class="badge badge-'.$lvlColor.'">'.$lvlLabel.'</span>' : '—' ?></td>
          <td><?= !empty($c['place']) ? '<span class="badge badge-amber">'.h($c['place']).'</span>' : '—' ?></td>
          <td style="font-size:.8rem"><?= h($c['issue_date'] ?? '—') ?></td>
          <td>
            <?php if (!empty($c['file_path'])): ?>
              <a href="<?= SITE_URL ?>/uploads/<?= h($c['file_path']) ?>"
                 target="_blank" class="btn btn-secondary btn-sm"
                 download="<?= h(basename($c['file_path'])) ?>">⬇ Скачать</a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <?php if (in_array($role,['admin','teacher','director'])): ?>
          <td>
            <a href="<?= SITE_URL ?>/actions/cert_delete.php?id=<?= $c['id'] ?>&user_id=<?= $c['user_id'] ?>"
               class="btn btn-danger btn-sm" onclick="return confirm('Удалить сертификат?')">🗑</a>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (in_array($role, ['admin','teacher','director'])): ?>

<div class="modal-overlay" id="modal-cert-add">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">📜 Добавить сертификат</div>
      <button class="modal-close" onclick="closeModal('modal-cert-add')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/actions/cert_save.php" enctype="multipart/form-data">
        <div class="form-group">
          <?php if (in_array($role, ['admin','director'])): ?>
          <div style="display:flex;gap:.75rem;margin-bottom:.75rem">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="radio" name="cert_ptype" value="student" checked
                     onchange="switchCertType('student')"> Студент
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="radio" name="cert_ptype" value="user"
                     onchange="switchCertType('user')"> Преподаватель / сотрудник
            </label>
          </div>
          <?php endif; ?>
          <input type="hidden" name="cert_type" id="cert-type-val" value="student">

          <div id="cert-block-student">
            <div class="form-row" style="margin-bottom:.75rem">
              <div class="form-group" style="margin:0">
                <label class="form-label">Группа</label>
                <select id="cert-filter-group" class="form-control" onchange="certFilterStudents()">
                  <option value="">Все группы</option>
                  <?php foreach ($allGroups as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <label class="form-label">Студент</label>
            <select name="edu_student_id" id="cert-student-select" class="form-control" required>
              <option value="">Выберите студента</option>
              <?php foreach ($allEduStudents as $st): ?>
                <option value="<?= $st['id'] ?>" data-group="<?= $st['group_id'] ?>">
                  <?= h($st['full_name']) ?>
                  <?= $st['group_name'] ? ' — '.h($st['group_name']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if (in_array($role, ['admin','director'])): ?>
          <div id="cert-block-user" style="display:none">
            <label class="form-label">Пользователь</label>
            <select name="user_id" id="cert-user-select" class="form-control">
              <option value="">Выберите пользователя</option>
              <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['id'] ?>"><?= h($u['full_name']) ?> (<?= $u['role'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label">Название сертификата</label>
          <input type="text" name="title" class="form-control"
                 placeholder="Веб-разработка на Python" required>
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
          <input type="file" name="pdf_file" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png" style="height:auto;padding:.5rem">
          <div style="font-size:.72rem;color:var(--text-m);margin-top:4px">Макс. 10 МБ · PDF, JPG, PNG</div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Сохранить</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-cert-add')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Модал: Загрузить PDF — с выбором студента ИЛИ преподавателя -->
<!-- Мини-попап: выбор владельца документа (появляется после выбора файла) -->
<div id="quick-owner-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;width:100%;max-width:480px;padding:1.5rem;box-shadow:0 20px 60px rgba(0,0,0,.2)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
      <div style="font-weight:600;font-size:.9375rem">Кому принадлежит документ?</div>
      <button onclick="closeQuickOwner()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-m)">✕</button>
    </div>
    <div id="quick-file-badge" style="padding:.35rem .75rem;background:var(--green-bg);border:1px solid var(--green-bd);border-radius:var(--r-md);font-size:.8rem;color:var(--green);margin-bottom:1rem"></div>

    <!-- Студент / Преподаватель -->
    <div style="display:flex;gap:2rem;margin-bottom:1rem">
      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem">
        <input type="radio" name="quick_ptype" value="student" checked onchange="switchQuickType('student')"> Студент
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem">
        <input type="radio" name="quick_ptype" value="teacher" onchange="switchQuickType('teacher')"> Преподаватель
      </label>
    </div>

    <!-- Поиск студента -->
    <div id="quick-block-student">
      <div style="font-size:.8125rem;font-weight:500;color:var(--text-2);margin-bottom:.35rem">Поиск студента</div>
      <div style="position:relative">
        <input type="text" id="quick-student-search" class="form-control"
               placeholder="Введите фамилию или имя..." autocomplete="off"
               oninput="quickSearchStudents(this.value)">
        <div id="quick-student-results"
             style="position:absolute;left:0;right:0;top:100%;z-index:9999;border:1px solid var(--border-2);border-radius:var(--r-md);max-height:200px;overflow-y:auto;display:none;background:#fff;box-shadow:var(--sh-md)">
        </div>
      </div>
      <div id="quick-selected-student" style="display:none;margin-top:.5rem;padding:.4rem .75rem;background:var(--green-bg);border:1px solid var(--green-bd);border-radius:var(--r-md);font-size:.8125rem;color:var(--green)"></div>
      <!-- Скрытый select для данных -->
      <select id="quick-student-select" style="display:none">
        <option value="">—</option>
        <?php foreach ($allEduStudents as $st): ?>
          <option value="<?= $st['id'] ?>"
                  data-name="<?= h(mb_strtolower($st['full_name'])) ?>">
            <?= h($st['full_name']) ?><?= $st['group_name'] ? ' — '.h($st['group_name']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Поиск преподавателя -->
    <div id="quick-block-teacher" style="display:none">
      <div style="font-size:.8125rem;font-weight:500;color:var(--text-2);margin-bottom:.35rem">Поиск преподавателя</div>
      <div style="position:relative">
        <input type="text" id="quick-teacher-search" class="form-control"
               placeholder="Введите фамилию или имя..." autocomplete="off"
               oninput="quickSearchTeachers(this.value)">
        <div id="quick-teacher-results"
             style="position:absolute;left:0;right:0;top:100%;z-index:9999;border:1px solid var(--border-2);border-radius:var(--r-md);max-height:200px;overflow-y:auto;display:none;background:#fff;box-shadow:var(--sh-md)">
        </div>
      </div>
      <div id="quick-selected-teacher" style="display:none;margin-top:.5rem;padding:.4rem .75rem;background:var(--green-bg);border:1px solid var(--green-bd);border-radius:var(--r-md);font-size:.8125rem;color:var(--green)"></div>
      <select id="quick-teacher-select" style="display:none">
        <option value="">—</option>
        <?php foreach ($allUsers as $u): ?>
          <option value="<?= $u['id'] ?>"
                  data-name="<?= h(mb_strtolower($u['full_name'])) ?>">
            <?= h($u['full_name']) ?> (<?= $u['role'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:flex;gap:.75rem;margin-top:1.25rem;border-top:1px solid var(--border);padding-top:1rem">
      <button id="quick-submit-btn" class="btn btn-primary" style="flex:1" onclick="quickSubmit()" disabled>
        📤 Загрузить и распознать
      </button>
      <button class="btn btn-secondary" onclick="closeQuickOwner()">Отмена</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-pdf-upload">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <div class="modal-title">Загрузить документ — автораспознавание</div>
      <button class="modal-close" onclick="closeModal('modal-pdf-upload')">✕</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info" style="font-size:.8125rem">
        Система автоматически распознает данные из PDF/фото грамоты.
      </div>

      <form method="POST" action="<?= SITE_URL ?>/actions/cert_pdf_parse.php"
            enctype="multipart/form-data" id="form-pdf-upload">

        <!-- Студент / Преподаватель -->
        <div class="form-group">
          <div style="display:flex;gap:2rem">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem">
              <input type="radio" name="pdf_ptype" value="student" checked
                     onchange="switchPdfType('student')"> Студент
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem">
              <input type="radio" name="pdf_ptype" value="teacher"
                     onchange="switchPdfType('teacher')"> Преподаватель
            </label>
          </div>
        </div>

        <!-- Поиск студента -->
        <div id="pdf-block-student">
          <div class="form-group" style="position:relative">
            <label class="form-label">Поиск студента</label>
            <input type="text" id="pdf-student-search" class="form-control"
                   placeholder="Введите фамилию или имя..."
                   autocomplete="off"
                   oninput="pdfSearchStudents(this.value)">
            <select name="edu_student_id" id="pdf-student-select" style="display:none" required>
              <option value="">— выберите —</option>
              <?php foreach ($allEduStudents as $st): ?>
                <option value="<?= $st['id'] ?>"
                        data-group="<?= $st['group_id'] ?>"
                        data-name="<?= h(mb_strtolower($st['full_name'])) ?>">
                  <?= h($st['full_name']) ?><?= $st['group_name'] ? ' — '.h($st['group_name']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div id="pdf-search-results" style="position:absolute;left:0;right:0;top:100%;z-index:999;border:1px solid var(--border-2);border-radius:var(--r-md);max-height:200px;overflow-y:auto;display:none;background:#fff;box-shadow:var(--sh-md)"></div>
            <div id="pdf-selected-student" style="display:none;margin-top:.5rem;padding:.4rem .75rem;background:var(--green-bg);border:1px solid var(--green-bd);border-radius:var(--r-md);font-size:.8125rem;color:var(--green);align-items:center;gap:.5rem"></div>
          </div>
        </div>

        <!-- Поиск преподавателя -->
        <div id="pdf-block-teacher" style="display:none">
          <div class="form-group" style="position:relative">
            <label class="form-label">Поиск преподавателя</label>
            <input type="text" id="pdf-teacher-search" class="form-control"
                   placeholder="Введите фамилию или имя..."
                   autocomplete="off"
                   oninput="pdfSearchTeachers(this.value)">
            <select name="pdf_user_id" id="pdf-teacher-select" style="display:none">
              <option value="">— выберите —</option>
              <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['id'] ?>"
                        data-name="<?= h(mb_strtolower($u['full_name'])) ?>">
                  <?= h($u['full_name']) ?> (<?= $u['role'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div id="pdf-teacher-results" style="position:absolute;left:0;right:0;top:100%;z-index:999;border:1px solid var(--border-2);border-radius:var(--r-md);max-height:200px;overflow-y:auto;display:none;background:#fff;box-shadow:var(--sh-md)"></div>
            <div id="pdf-selected-teacher" style="display:none;margin-top:.5rem;padding:.4rem .75rem;background:var(--green-bg);border:1px solid var(--green-bd);border-radius:var(--r-md);font-size:.8125rem;color:var(--green)"></div>
          </div>
        </div>

        <!-- Файл -->
        <div class="form-group">
          <label class="form-label">PDF / JPG / PNG файл</label>
          <input type="file" name="pdf_file" id="pdf-file-input" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png,.webp" required
                 style="height:auto;padding:.5rem"
                 onchange="pdfAutoSubmit(this)">
          <div style="font-size:.72rem;color:var(--text-m);margin-top:4px">Макс. 10 МБ</div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" id="btn-upload-parse">
            📤 Загрузить и распознать
          </button>
          <button type="button" class="btn btn-secondary"
                  onclick="closeModal('modal-pdf-upload')">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
function switchCertType(type) {
  document.getElementById('cert-type-val').value = type;
  document.getElementById('cert-block-student').style.display = type === 'student' ? '' : 'none';
  document.getElementById('cert-block-user').style.display    = type === 'user'    ? '' : 'none';
  document.getElementById('cert-student-select').required = (type === 'student');
  const us = document.getElementById('cert-user-select');
  if (us) us.required = (type === 'user');
}
function certFilterStudents() {
  const gid = document.getElementById('cert-filter-group').value;
  const sel = document.getElementById('cert-student-select');
  [...sel.options].forEach(opt => {
    if (!opt.value) return;
    opt.hidden = gid ? opt.dataset.group !== gid : false;
  });
  sel.value = '';
}
// ═══════════════════════════════════════════════════════════════════
// БЫСТРАЯ ЗАГРУЗКА — без модалки
// ═══════════════════════════════════════════════════════════════════

function quickUploadClick() {
  // Сбрасываем предыдущий выбор
  document.getElementById('quick-student-search').value = '';
  document.getElementById('quick-teacher-search').value = '';
  document.getElementById('quick-selected-student').style.display = 'none';
  document.getElementById('quick-selected-teacher').style.display = 'none';
  document.getElementById('quick-student-select').value = '';
  document.getElementById('quick-teacher-select').value = '';
  document.getElementById('quick-submit-btn').disabled = true;
  document.querySelector('input[name=quick_ptype][value=student]').checked = true;
  switchQuickType('student');
  // Открываем диалог выбора файла
  document.getElementById('quick-file').value = '';
  document.getElementById('quick-file').click();
}

function quickFileChosen(input) {
  if (!input.files.length) return;
  const name = input.files[0].name;
  document.getElementById('quick-file-badge').textContent = '📎 ' + name;
  // Показываем попап выбора владельца
  document.getElementById('quick-owner-overlay').style.display = 'flex';
  setTimeout(() => document.getElementById('quick-student-search').focus(), 100);
}

function closeQuickOwner() {
  document.getElementById('quick-owner-overlay').style.display = 'none';
  document.getElementById('quick-file').value = '';
}

function switchQuickType(type) {
  document.getElementById('quick-block-student').style.display = type === 'student' ? '' : 'none';
  document.getElementById('quick-block-teacher').style.display = type === 'teacher' ? '' : 'none';
  document.getElementById('quick-ptype').value = type;
  checkQuickReady();
}

function checkQuickReady() {
  const type = document.querySelector('input[name=quick_ptype]:checked').value;
  const sid = document.getElementById('quick-student-select').value;
  const tid = document.getElementById('quick-teacher-select').value;
  const ready = (type === 'student' && sid) || (type === 'teacher' && tid);
  document.getElementById('quick-submit-btn').disabled = !ready;
  if (ready) quickSubmit(); // автосабмит!
}

function quickSubmit() {
  const type = document.querySelector('input[name=quick_ptype]:checked').value;
  document.getElementById('quick-ptype').value   = type;
  document.getElementById('quick-student').value = document.getElementById('quick-student-select').value;
  document.getElementById('quick-teacher').value = document.getElementById('quick-teacher-select').value;
  document.getElementById('quick-submit-btn').disabled = true;
  document.getElementById('quick-submit-btn').textContent = '⏳ Загружаю...';
  document.getElementById('quick-upload-form').submit();
}

// Поиск студента в quick-попапе
function quickSearchStudents(q) {
  const sel  = document.getElementById('quick-student-select');
  const box  = document.getElementById('quick-student-results');
  const opts = [...sel.options].filter(o => o.value);
  q = q.trim().toLowerCase();
  if (!q) { box.style.display = 'none'; return; }
  const matches = opts.filter(o => o.dataset.name.includes(q)).slice(0, 12);
  if (!matches.length) {
    box.innerHTML = '<div style="padding:.5rem .75rem;font-size:.8125rem;color:var(--text-m)">Не найдено</div>';
  } else {
    box.innerHTML = matches.map(o =>
      `<div class="qsr-item" data-val="${o.value}" data-label="${o.text}"
            style="padding:.45rem .75rem;font-size:.8125rem;cursor:pointer;border-bottom:1px solid var(--border)"
            onmousedown="quickPickStudent(this)">${o.text}</div>`
    ).join('');
  }
  box.style.display = 'block';
}

function quickPickStudent(el) {
  document.getElementById('quick-student-select').value = el.dataset.val;
  document.getElementById('quick-student-search').value = el.dataset.label.split(' — ')[0];
  document.getElementById('quick-student-results').style.display = 'none';
  const d = document.getElementById('quick-selected-student');
  d.style.display = 'block';
  d.textContent = '✅ ' + el.dataset.label;
  checkQuickReady();
}

// Поиск преподавателя в quick-попапе
function quickSearchTeachers(q) {
  const sel  = document.getElementById('quick-teacher-select');
  const box  = document.getElementById('quick-teacher-results');
  const opts = [...sel.options].filter(o => o.value);
  q = q.trim().toLowerCase();
  if (!q) { box.style.display = 'none'; return; }
  const matches = opts.filter(o => o.dataset.name.includes(q)).slice(0, 12);
  if (!matches.length) {
    box.innerHTML = '<div style="padding:.5rem .75rem;font-size:.8125rem;color:var(--text-m)">Не найдено</div>';
  } else {
    box.innerHTML = matches.map(o =>
      `<div class="qsr-item" data-val="${o.value}" data-label="${o.text}"
            style="padding:.45rem .75rem;font-size:.8125rem;cursor:pointer;border-bottom:1px solid var(--border)"
            onmousedown="quickPickTeacher(this)">${o.text}</div>`
    ).join('');
  }
  box.style.display = 'block';
}

function quickPickTeacher(el) {
  document.getElementById('quick-teacher-select').value = el.dataset.val;
  document.getElementById('quick-teacher-search').value = el.dataset.label;
  document.getElementById('quick-teacher-results').style.display = 'none';
  const d = document.getElementById('quick-selected-teacher');
  d.style.display = 'block';
  d.textContent = '✅ ' + el.dataset.label;
  checkQuickReady();
}

// Закрытие по Escape и клику вне
document.addEventListener('keydown', e => { if (e.key==='Escape') closeQuickOwner(); });
document.getElementById('quick-owner-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeQuickOwner();
});

// ═══════════════════════════════════════════════════════════════════

function switchPdfType(type) {
  document.getElementById('pdf-block-student').style.display = type === 'student' ? '' : 'none';
  document.getElementById('pdf-block-teacher').style.display = type === 'teacher' ? '' : 'none';
  document.getElementById('pdf-student-select').required = (type === 'student');
  document.getElementById('pdf-teacher-select').required = (type === 'teacher');
}

// Автосабмит когда выбран файл — если владелец уже выбран
function pdfAutoSubmit(input) {
  if (!input.files.length) return;
  const type = document.querySelector('input[name=pdf_ptype]:checked').value;
  const studentId = document.getElementById('pdf-student-select').value;
  const teacherId = document.getElementById('pdf-teacher-select').value;
  if (type === 'student' && !studentId) return; // ждём выбора студента
  if (type === 'teacher' && !teacherId) return; // ждём выбора препода
  // Всё выбрано — сабмитим
  const btn = document.getElementById('btn-upload-parse');
  btn.textContent = '⏳ Загружаю...';
  btn.disabled = true;
  document.getElementById('form-pdf-upload').submit();
}

// ── Поиск студента ────────────────────────────────────────────────────────────
function pdfSearchStudents(q) {
  const sel     = document.getElementById('pdf-student-select');
  const box     = document.getElementById('pdf-search-results');
  const chosen  = document.getElementById('pdf-selected-student');
  const opts    = [...sel.options].filter(o => o.value);
  q = q.trim().toLowerCase();
  if (!q) { box.style.display = 'none'; return; }
  const matches = opts.filter(o => o.dataset.name.includes(q)).slice(0, 12);
  if (!matches.length) {
    box.innerHTML = '<div style="padding:.5rem .75rem;font-size:.8125rem;color:var(--text-m)">Не найдено</div>';
    box.style.display = 'block'; return;
  }
  box.innerHTML = matches.map(o =>
    `<div class="pdf-result-item" data-val="${o.value}" data-label="${o.text}"
          style="padding:.45rem .75rem;font-size:.8125rem;cursor:pointer;border-bottom:1px solid var(--border)"
          onmousedown="pdfSelectStudent(this)">${o.text}</div>`
  ).join('');
  box.style.display = 'block';
}
function pdfSelectStudent(el) {
  const sel = document.getElementById('pdf-student-select');
  sel.value = el.dataset.val;
  document.getElementById('pdf-student-search').value = el.dataset.label.split(' — ')[0];
  document.getElementById('pdf-search-results').style.display = 'none';
  const chosen = document.getElementById('pdf-selected-student');
  chosen.style.display = 'flex';
  chosen.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> ${el.dataset.label}`;
  // если файл уже выбран — автосабмит
  const fi = document.getElementById('pdf-file-input');
  if (fi && fi.files.length) pdfAutoSubmit(fi);
}
document.addEventListener('click', function(e) {
  const box = document.getElementById('pdf-search-results');
  if (box && !box.contains(e.target) && e.target.id !== 'pdf-student-search')
    box.style.display = 'none';
});

// ── Поиск преподавателя ───────────────────────────────────────────────────────
function pdfSearchTeachers(q) {
  const sel    = document.getElementById('pdf-teacher-select');
  const box    = document.getElementById('pdf-teacher-results');
  const opts   = [...sel.options].filter(o => o.value);
  q = q.trim().toLowerCase();
  if (!q) { box.style.display = 'none'; return; }
  const matches = opts.filter(o => o.dataset.name.includes(q)).slice(0, 12);
  if (!matches.length) {
    box.innerHTML = '<div style="padding:.5rem .75rem;font-size:.8125rem;color:var(--text-m)">Не найдено</div>';
    box.style.display = 'block'; return;
  }
  box.innerHTML = matches.map(o =>
    `<div class="pdf-result-item" data-val="${o.value}" data-label="${o.text}"
          style="padding:.45rem .75rem;font-size:.8125rem;cursor:pointer;border-bottom:1px solid var(--border)"
          onmousedown="pdfSelectTeacher(this)">${o.text}</div>`
  ).join('');
  box.style.display = 'block';
}
function pdfSelectTeacher(el) {
  const sel = document.getElementById('pdf-teacher-select');
  sel.value = el.dataset.val;
  document.getElementById('pdf-teacher-search').value = el.dataset.label;
  document.getElementById('pdf-teacher-results').style.display = 'none';
  const chosen = document.getElementById('pdf-selected-teacher');
  chosen.style.display = 'block';
  chosen.innerHTML = `✅ ${el.dataset.label}`;
  // если файл уже выбран — автосабмит
  const fi = document.getElementById('pdf-file-input');
  if (fi && fi.files.length) pdfAutoSubmit(fi);
}

// ── Ховер на результатах ──────────────────────────────────────────────────────
document.addEventListener('mouseover', function(e) {
  if (e.target.classList.contains('pdf-result-item'))
    e.target.style.background = 'var(--bg)';
});
document.addEventListener('mouseout', function(e) {
  if (e.target.classList.contains('pdf-result-item'))
    e.target.style.background = '';
});
</script>

<?php require_once 'includes/footer.php'; ?>