<?php
require_once 'includes/header.php';

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$pdo   = getPDO();

// Создаём таблицу скрытых студентов если нет
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rating_hidden_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        edu_student_id INT UNSIGNED NOT NULL,
        hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_student (edu_student_id)
    )");
} catch(Exception $e) {}

$activeTab = $_GET['tab'] ?? 'students';

$studentRating = [];
try {
    $hasEduAch  = (bool)$pdo->query("SHOW COLUMNS FROM achievements       LIKE 'edu_student_id'")->fetch();
    $hasEduCert = (bool)$pdo->query("SHOW COLUMNS FROM certificates       LIKE 'edu_student_id'")->fetch();
    $hasEduPart = (bool)$pdo->query("SHOW COLUMNS FROM event_participants LIKE 'edu_student_id'")->fetch();

    $achCol  = $hasEduAch  ? "COALESCE((SELECT COUNT(*) FROM achievements       a WHERE a.edu_student_id = es.id), 0)" : "0";
    $certCol = $hasEduCert ? "COALESCE((SELECT COUNT(*) FROM certificates       c WHERE c.edu_student_id = es.id), 0)" : "0";
    $partCol = $hasEduPart ? "COALESCE((SELECT COUNT(*) FROM event_participants p WHERE p.edu_student_id = es.id), 0)" : "0";

    $studentRating = $pdo->query("SELECT
        es.id,
        CONCAT(es.surname,' ',es.name,
            IF(es.patronymic!='' AND es.patronymic IS NOT NULL,
               CONCAT(' ',es.patronymic),'')) AS full_name,
        g.name AS group_name,
        $achCol  AS achievements_count,
        $certCol AS certificates_count,
        $partCol AS events_count,
        ($achCol * 30 + $certCol * 20 + $partCol * 10) AS total_points
        FROM edu_students es
        LEFT JOIN edu_groups g ON es.group_id = g.id
        WHERE ($achCol + $certCol + $partCol) > 0
        AND es.id NOT IN (SELECT edu_student_id FROM rating_hidden_students)
        ORDER BY total_points DESC
        LIMIT 100")->fetchAll();
} catch (Exception $e) { $studentRating = []; }

$teacherRating = [];
try {
    if ($role === 'teacher') {
        $allRatingRaw = getRating(100);
        foreach ($allRatingRaw as $r) {
            if ($r['user_id'] == $user['id']) {
                $teacherRating[] = $r; break;
            }
        }
    } else {
        $teacherRating = getRating(100);
    }
} catch (Exception $e) { $teacherRating = []; }
?>

<?php if ($flash): ?>
<div class="alert alert-success anim-fade">✅ <?= h($flash) ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert alert-error anim-fade">⚠️ <?= h($_SESSION['flash_error']) ?></div>
<?php unset($_SESSION['flash_error']); endif; ?>

<div class="page-header anim-fade">
  <div>
    <div class="page-header-title">📊 Рейтинг</div>
    <div class="page-header-sub">Топ по достижениям, сертификатам и участию</div>
  </div>
</div>

<div class="card anim-fade">
  <div style="display:flex;border-bottom:1px solid var(--border)">
    <a href="rating.php?tab=students"
       style="padding:.75rem 1.25rem;font-size:.8125rem;font-weight:600;
              border-bottom:2px solid <?= $activeTab==='students'?'var(--blue)':'transparent' ?>;
              color:<?= $activeTab==='students'?'var(--blue)':'var(--text-m)' ?>;
              margin-bottom:-1px;text-decoration:none">
      🎓 Студенты <span class="badge badge-<?= $activeTab==='students'?'blue':'gray' ?>" style="margin-left:4px"><?= count($studentRating) ?></span>
    </a>
    <a href="rating.php?tab=teachers"
       style="padding:.75rem 1.25rem;font-size:.8125rem;font-weight:600;
              border-bottom:2px solid <?= $activeTab==='teachers'?'var(--blue)':'transparent' ?>;
              color:<?= $activeTab==='teachers'?'var(--blue)':'var(--text-m)' ?>;
              margin-bottom:-1px;text-decoration:none">
      <?= $role==='teacher' ? '🏅 Мой рейтинг' : '📚 Преподаватели' ?>
      <span class="badge badge-<?= $activeTab==='teachers'?'blue':'gray' ?>" style="margin-left:4px"><?= count($teacherRating) ?></span>
    </a>
  </div>

  <div class="table-wrap">

    <?php if ($activeTab === 'students'): ?>
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th>Студент</th>
          <th>Группа</th>
          <th>🏆 Достижения</th>
          <th>📜 Сертификаты</th>
          <th>📅 Мероприятия</th>
          <th>Баллы</th>
          <?php if (in_array($role, ['admin','director'])): ?><th>Действия</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($studentRating)): ?>
        <tr><td colspan="8" style="text-align:center;padding:var(--space-10);color:var(--text-m)">Рейтинг пуст</td></tr>
      <?php else: ?>
      <?php foreach ($studentRating as $i => $r): ?>
        <tr class="anim-fade" style="animation-delay:<?= $i*0.03 ?>s<?=
          $i===0?';background:rgba(250,204,21,.06)':($i===1?';background:rgba(148,163,184,.04)':($i===2?';background:rgba(251,146,60,.05)':'')) ?>">
          <td>
            <?php if ($i===0): ?><span class="rank-badge r1">🥇</span>
            <?php elseif ($i===1): ?><span class="rank-badge r2">🥈</span>
            <?php elseif ($i===2): ?><span class="rank-badge r3">🥉</span>
            <?php else: ?><span style="color:var(--text-m);font-weight:600;padding-left:6px"><?= $i+1 ?></span>
            <?php endif; ?>
          </td>
          <td style="font-weight:600"><?= h($r['full_name']) ?></td>
          <td><?= h($r['group_name'] ?? '—') ?></td>
          <td><span class="badge badge-green"><?= $r['achievements_count'] ?></span></td>
          <td><span class="badge badge-amber"><?= $r['certificates_count'] ?></span></td>
          <td><span class="badge badge-blue"><?= $r['events_count'] ?></span></td>
          <td><strong style="font-size:1.05rem;color:var(--blue)"><?= $r['total_points'] ?></strong></td>
          <?php if (in_array($role, ['admin','director'])): ?>
          <td>
            <a href="<?= SITE_URL ?>/actions/rating_student_delete.php?student_id=<?= $r['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Удалить «<?= h(addslashes($r['full_name'])) ?>» из рейтинга?')">
              🗑 Удалить
            </a>
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
          <th style="width:50px">#</th>
          <th><?= $role==='teacher' ? 'Преподаватель' : 'Пользователь' ?></th>
          <th>🏆 Достижения</th>
          <th>📜 Сертификаты</th>
          <th>📅 Мероприятия</th>
          <th>Баллы</th>
          <?php if (in_array($role, ['admin','director'])): ?><th>Действия</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($teacherRating)): ?>
        <tr><td colspan="7" style="text-align:center;padding:var(--space-10);color:var(--text-m)">
          <?= $role==='teacher' ? 'У вас пока нет баллов в рейтинге' : 'Рейтинг пуст' ?>
        </td></tr>
      <?php else: ?>
      <?php foreach ($teacherRating as $i => $r): ?>
        <tr class="anim-fade" style="animation-delay:<?= $i*0.03 ?>s<?=
          $i===0?';background:rgba(250,204,21,.06)':($i===1?';background:rgba(148,163,184,.04)':($i===2?';background:rgba(251,146,60,.05)':'')) ?>">
          <td>
            <?php if ($i===0): ?><span class="rank-badge r1">🥇</span>
            <?php elseif ($i===1): ?><span class="rank-badge r2">🥈</span>
            <?php elseif ($i===2): ?><span class="rank-badge r3">🥉</span>
            <?php else: ?><span style="color:var(--text-m);font-weight:600;padding-left:6px"><?= $i+1 ?></span>
            <?php endif; ?>
          </td>
          <td style="font-weight:600"><?= h($r['full_name']) ?></td>
          <td><span class="badge badge-green"><?= $r['achievements_count'] ?></span></td>
          <td><span class="badge badge-amber"><?= $r['certificates_count'] ?></span></td>
          <td><span class="badge badge-blue"><?= $r['events_count'] ?></span></td>
          <td><strong style="font-size:1.05rem;color:var(--blue)"><?= $r['total_points'] ?></strong></td>
          <?php if (in_array($role, ['admin','director'])): ?>
          <td>
            <a href="<?= SITE_URL ?>/actions/rating_delete.php?user_id=<?= $r['user_id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Удалить «<?= h(addslashes($r['full_name'])) ?>» из рейтинга?')">
              🗑 Удалить
            </a>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <?php endif; ?>

  </div>
</div>

<div class="card anim-fade" style="margin-top:var(--space-5);padding:var(--space-4) var(--space-5)">
  <strong>Формула рейтинга:</strong>
  🏆 Достижение × 30 + 📜 Сертификат × 20 + 📅 Мероприятие × 10
</div>

<?php require_once 'includes/footer.php'; ?>