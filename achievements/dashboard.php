<?php
require_once 'includes/header.php';

$pdo = getPDO();

$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalAch      = $pdo->query("SELECT COUNT(*) FROM achievements")->fetchColumn();
$totalCerts    = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();

// Последние достижения
try {
    $hasEduCol = (bool)$pdo->query("SHOW COLUMNS FROM achievements LIKE 'edu_student_id'")->fetch();
} catch(Exception $e) { $hasEduCol = false; }

if ($hasEduCol) {
    $recentAch = $pdo->query("
        SELECT a.title, a.created_at,
            COALESCE(
                CONCAT(es.surname,' ',es.name,
                    IF(es.patronymic!='' AND es.patronymic IS NOT NULL,
                       CONCAT(' ',es.patronymic),'')),
                u.full_name
            ) AS full_name,
            CASE
                WHEN a.edu_student_id IS NOT NULL THEN 'student'
                ELSE COALESCE(u.role, 'teacher')
            END AS user_role,
            COALESCE(u.id, 0) AS user_id
        FROM achievements a
        LEFT JOIN edu_students es ON a.edu_student_id = es.id
        LEFT JOIN users u ON a.user_id = u.id AND a.user_id > 0
        WHERE (es.id IS NOT NULL OR (u.id IS NOT NULL AND u.full_name != ''))
        ORDER BY a.created_at DESC
        LIMIT 6
    ")->fetchAll();
} else {
    $recentAch = $pdo->query("SELECT a.*, u.id AS user_id, u.full_name, u.role AS user_role
        FROM achievements a
        JOIN users u ON a.user_id=u.id
        ORDER BY a.created_at DESC LIMIT 6")->fetchAll();
}
?>

<div class="anim-fade" style="margin-bottom:var(--space-6)">
  <h1 style="font-size:1.6rem;font-weight:800;color:var(--color-text);margin-bottom:4px">
     Главная панель
  </h1>
  <p style="font-size:var(--text-sm);color:var(--color-text-muted)">
    Добро пожаловать, <strong><?= h($user['full_name']) ?></strong>! 
  </p>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-4);margin-bottom:var(--space-6)">
  <div class="card anim-fade" style="padding:var(--space-5)">
    <div style="display:flex;align-items:center;gap:var(--space-4)">
      <div style="width:48px;height:48px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div>
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-text-muted)">Студентов</div>
        <div style="font-size:2rem;font-weight:800;color:var(--color-text);line-height:1.1"><?= $totalStudents ?></div>
        <div style="font-size:0.72rem;color:var(--color-text-muted)">Активных студентов</div>
      </div>
    </div>
  </div>

  <div class="card anim-fade anim-delay-1" style="padding:var(--space-5)">
    <div style="display:flex;align-items:center;gap:var(--space-4)">
      <div style="width:48px;height:48px;border-radius:12px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2">
          <circle cx="12" cy="8" r="6"/>
          <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
        </svg>
      </div>
      <div>
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-text-muted)">Достижений</div>
        <div style="font-size:2rem;font-weight:800;color:var(--color-text);line-height:1.1"><?= $totalAch ?></div>
        <div style="font-size:0.72rem;color:var(--color-text-muted)">Всего достижений</div>
      </div>
    </div>
  </div>

  <div class="card anim-fade anim-delay-2" style="padding:var(--space-5)">
    <div style="display:flex;align-items:center;gap:var(--space-4)">
      <div style="width:48px;height:48px;border-radius:12px;background:#fffbeb;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
      </div>
      <div>
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-text-muted)">Сертификатов</div>
        <div style="font-size:2rem;font-weight:800;color:var(--color-text);line-height:1.1"><?= $totalCerts ?></div>
        <div style="font-size:0.72rem;color:var(--color-text-muted)">Выдано сертификатов</div>
      </div>
    </div>
  </div>
</div>

<div class="card anim-fade" style="border-top:3px solid #d97706;max-width:700px">
  <div style="background:#fffbeb;padding:var(--space-4) var(--space-5);display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--color-border)">
    <span style="font-size:1.1rem"></span>
    <span style="font-weight:700;color:#d97706;font-size:var(--text-base)">Последние достижения</span>
  </div>
  <table class="data-table">
    <thead>
      <tr>
        <th>Пользователь</th>
        <th>Достижение</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($recentAch)): ?>
      <tr><td colspan="2" style="text-align:center;color:var(--color-text-muted);padding:40px 0">Нет данных</td></tr>
    <?php else: ?>
    <?php foreach ($recentAch as $a):
      $isTeacher = in_array($a['user_role'], ['teacher','admin','director']);
      $roleColor = $isTeacher ? '#0284c7' : '#16a34a';
      $roleBg    = $isTeacher ? '#eff6ff' : '#f0fdf4';
      $roleText  = $isTeacher ? 'Преподаватель' : 'Студент';
    ?>
      <tr>
        <td>
          <span style="font-weight:600;font-size:var(--text-sm);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:250px">
            <?= h($a['full_name']) ?>
          </span>
          <span style="font-size:0.7rem;color:<?= $roleColor ?>;background:<?= $roleBg ?>;padding:1px 7px;border-radius:20px;display:inline-block;margin-top:2px">
            <?= $roleText ?>
          </span>
        </td>
        <td>
          <span class="badge badge-amber" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-block;font-size:0.72rem">
            <?= h(mb_strimwidth($a['title'], 0, 28, '…')) ?>
          </span>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once 'includes/footer.php'; ?>