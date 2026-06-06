<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/export_helpers.php';

$role    = edu_current_role();
$userId  = edu_current_user_id();
$isAdmin = edu_is_admin();
$isDir   = edu_is_director();
$isTeacher = edu_is_teacher();

if (!in_array($role, ['admin', 'teacher', 'director'], true)) {
    header('Location: index.php');
    exit;
}

function edu_grades_column_exists(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'edu_grades'
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$column]);
    return $cache[$column] = ((int)$stmt->fetchColumn() > 0);
}

$sheetId = (int)($_GET['sheet_id'] ?? 0);
if (!$sheetId) { header('Location: grade_sheets.php'); exit; }

// Загружаем ведомость
$stmt = $pdo->prepare("
    SELECT gs.*,
           g.name AS group_name, g.curator_id,
           COALESCE(NULLIF(m.name, ''), sub.name_ru) AS subject_name,
           COALESCE(NULLIF(m.index_code, ''), sub.code) AS subject_code,
           m.id AS module_id, m.curriculum_id AS module_curriculum_id,
           sem.year_start, sem.year_end, sem.semester_num,
           u.full_name AS teacher_name
    FROM edu_grade_sheets gs
    LEFT JOIN edu_groups    g   ON g.id   = gs.group_id
    LEFT JOIN edu_curriculum_modules m ON m.id = gs.curriculum_module_id
    LEFT JOIN edu_subjects  sub ON sub.id = gs.subject_id
    LEFT JOIN edu_semesters sem ON sem.id = gs.semester_id
    LEFT JOIN users         u   ON u.id   = gs.teacher_id
    WHERE gs.id = ?
");
$stmt->execute([$sheetId]);
$sheet = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sheet) { header('Location: grade_sheets.php'); exit; }

$accessibleGroupIds = edu_accessible_group_ids($pdo, $userId, $role);
// Проверка доступа: admin/director или доступная группа преподавателя
$isCurator = ((int)$sheet['teacher_id'] === $userId || in_array((int)$sheet['group_id'], $accessibleGroupIds, true));
if (!$isAdmin && !$isDir && !$isCurator) {
    header('Location: grade_sheets.php');
    exit;
}

// Можно редактировать только если draft и мы куратор/владелец или admin
$canEdit = in_array($sheet['status'], ['draft', 'rejected'], true) && ($isAdmin || ($isTeacher && $isCurator));
$canReview = ($isAdmin || $isDir) && in_array($sheet['status'], ['submitted', 'approved'], true);

$message = ''; $messageType = '';

// ── Сохранение оценок ─────────────────────────────────────────────────────
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $grades   = $_POST['grade']   ?? [];
    $absents  = $_POST['absent']  ?? [];
    $passed   = $_POST['passed']  ?? [];
    $comments = $_POST['comment'] ?? [];
    $date     = trim($_POST['grade_date'] ?? '');

    $hasGradeModule = edu_grades_column_exists($pdo, 'curriculum_module_id');
    $hasGradeSemester = edu_grades_column_exists($pdo, 'curriculum_semester');
    if ($hasGradeModule && $hasGradeSemester) {
        $upd = $pdo->prepare("
            UPDATE edu_grades
            SET grade=?, passed=?, absent=?, comment=?, date=?, curriculum_module_id=?, curriculum_semester=?, updated_at=NOW()
            WHERE grade_sheet_id=? AND student_id=?
        ");
    } else {
        $upd = $pdo->prepare("
            UPDATE edu_grades
            SET grade=?, passed=?, absent=?, comment=?, date=?, updated_at=NOW()
            WHERE grade_sheet_id=? AND student_id=?
        ");
    }

    try {
        $pdo->beginTransaction();
        // Получаем все строки ведомости
        $rows = $pdo->prepare("SELECT id, student_id FROM edu_grades WHERE grade_sheet_id = ?");
        $rows->execute([$sheetId]);
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sid     = $row['student_id'];
            $rawGrade = isset($grades[$sid]) ? trim((string)$grades[$sid]) : '';
            $g       = $rawGrade !== '' ? edu_normalize_score($rawGrade) : null;
            if ($rawGrade !== '' && $g === null) {
                throw new PDOException('Оценка должна быть числом от 0 до 100.');
            }
            $abs     = isset($absents[$sid])   ? 1 : 0;
            $pas     = isset($passed[$sid])    ? 1 : 0;
            $com     = isset($comments[$sid])  ? trim($comments[$sid]) : null;
            $d       = $date ?: null;
            if ($hasGradeModule && $hasGradeSemester) {
                $upd->execute([$g, $pas, $abs, $com ?: null, $d, (int)($sheet['curriculum_module_id'] ?? 0) ?: null, (int)($sheet['curriculum_semester'] ?? 0) ?: null, $sheetId, $sid]);
            } else {
                $upd->execute([$g, $pas, $abs, $com ?: null, $d, $sheetId, $sid]);
            }
        }
        $pdo->commit();
        $message = 'Оценки сохранены.'; $messageType = 'success';
        // Перезагружаем ведомость
        $stmt->execute([$sheetId]);
        $sheet = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = 'Ошибка: ' . $e->getMessage(); $messageType = 'error';
    }
}

// ── Загрузка студентов с оценками ─────────────────────────────────────────
$studentRows = $pdo->prepare("
    SELECT eg.*, s.surname, s.name, s.patronymic, s.iin
    FROM edu_grades eg
    JOIN edu_students s ON s.id = eg.student_id
    WHERE eg.grade_sheet_id = ?
    ORDER BY s.surname, s.name
");
$studentRows->execute([$sheetId]);
$students = $studentRows->fetchAll(PDO::FETCH_ASSOC);

// Статистика
$graded  = array_filter($students, fn($s) => $s['grade'] !== null);
$absent  = array_filter($students, fn($s) => $s['absent']);
$avg     = count($graded) ? round(array_sum(array_map(fn($g) => (int)$g['grade'], $graded)) / count($graded), 2) : null;
$total   = count($students);

$TYPE_LABELS   = ['exam'=>'Экзамен','credit'=>'Зачёт','coursework'=>'Курсовая','practice'=>'Практика','current'=>'Тек. контроль'];
$STATUS_LABELS = ['draft'=>'Черновик','submitted'=>'На проверке','approved'=>'Утверждена','rejected'=>'Доработка'];
$STATUS_BADGE  = ['draft'=>'badge-gray','submitted'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'];
$isCredit      = in_array($sheet['type'], ['credit','practice']);

$pageTitle = 'Оценки — ' . htmlspecialchars($sheet['group_name'] . ' / ' . $sheet['subject_name']);
$breadcrumbs = [
    ['label'=>'СВГТК',           'href'=>'../'],
    ['label'=>'Учебный процесс', 'href'=>'index.php'],
    ['label'=>'Оценки',          'href'=>'grade_sheets.php'],
    ['label'=>$sheet['group_name'].' / '.$sheet['subject_code']],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <?php require 'includes/head.php' ?>
  <style>
    .sheet-meta { display:flex; gap:1.25rem; flex-wrap:wrap; padding:1rem 1.5rem; background:var(--color-surface-2); border-bottom:1px solid var(--color-divider); }
    .meta-item  { display:flex; flex-direction:column; gap:2px; }
    .meta-label { font-size:.75rem; color:var(--color-text-muted); font-weight:500; }
    .meta-val   { font-size:.9375rem; font-weight:600; color:var(--color-text); }
    .grades-table { width:100%; min-width:650px; }
    .grades-table th { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-muted); padding:.75rem 1rem; background:var(--color-surface-2); border-bottom:1px solid var(--color-divider); text-align:left; white-space:nowrap; }
    .grades-table td { padding:.5rem 1rem; border-bottom:1px solid var(--color-divider); font-size:.9375rem; vertical-align:middle; }
    .grades-table tr:last-child td { border-bottom:none; }
    .grades-table tr.absent-row { opacity:.55; }
    .grade-input { width:88px; padding:.3rem .5rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); color:var(--color-text); font-size:.9375rem; }
    .grade-input:focus { outline:none; border-color:var(--color-primary); }
    .grade-select { width:70px; padding:.3rem .5rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); color:var(--color-text); font-size:.9375rem; }
    .grade-select:focus { outline:none; border-color:var(--color-primary); }
    .grade-select.grade-5 { border-color:#22c55e; background:color-mix(in srgb,#22c55e 10%,transparent); }
    .grade-select.grade-4 { border-color:#3b82f6; background:color-mix(in srgb,#3b82f6 10%,transparent); }
    .grade-select.grade-3 { border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 10%,transparent); }
    .grade-low { border-color:#ef4444; background:color-mix(in srgb,#ef4444 10%,transparent); }
    .grade-mid { border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 10%,transparent); }
    .grade-good { border-color:#3b82f6; background:color-mix(in srgb,#3b82f6 10%,transparent); }
    .grade-excellent { border-color:#22c55e; background:color-mix(in srgb,#22c55e 10%,transparent); }
    .comment-input { width:100%; min-width:140px; padding:.3rem .5rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); color:var(--color-text); font-size:.875rem; }
    .comment-input:focus { outline:none; border-color:var(--color-primary); }
    .table-wrapper { overflow-x:auto; }
    .stats-strip { display:flex; gap:2rem; flex-wrap:wrap; padding:1rem 1.5rem; background:var(--color-surface-2); border-bottom:1px solid var(--color-divider); }
    .stat-item  { display:flex; flex-direction:column; gap:2px; }
    .stat-value { font-weight:700; font-size:1.125rem; font-variant-numeric:tabular-nums; }
    .stat-label { font-size:.75rem; color:var(--color-text-muted); }
    @media print {
      .sidebar,.topbar,.form-actions,.btn { display:none!important; }
      .main-wrapper { margin-left:0!important; }
      .grades-table input, .grades-table select { border:none!important; background:transparent!important; -webkit-appearance:none; }
    }
  </style>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title"><?= htmlspecialchars($sheet['group_name']) ?> / <?= htmlspecialchars($sheet['subject_name']) ?></h1>
        <p class="page-subtitle"><?= $TYPE_LABELS[$sheet['type']] ?> · <?= !empty($sheet['curriculum_semester']) ? ((int)$sheet['curriculum_semester'] . ' семестр РУПл') : ($sheet['year_start'].'/'.$sheet['year_end'].' · '.$sheet['semester_num'].' семестр') ?></p>
      </div>
      <div class="page-actions">
        <a href="export_grade_sheet.php?sheet_id=<?= $sheetId ?>" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Экспорт ведомости
        </a>
        <?php if ($canReview && $sheet['status'] === 'submitted'): ?>
        <a href="grade_sheets.php?id=<?= $sheetId ?>&status=approved"
           class="btn btn-success"
           onclick="return confirm('Принять и утвердить ведомость?')">
          Принять
        </a>
        <?php endif ?>
        <?php if ($canReview): ?>
        <a href="grade_sheets.php?id=<?= $sheetId ?>&status=rejected"
           class="btn btn-outline"
           onclick="return confirm('Отправить ведомость преподавателю на доработку?')">
          На доработку
        </a>
        <?php endif ?>
        <button class="btn btn-outline" onclick="window.print()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Печать
        </button>
        <a href="grade_sheets.php" class="btn btn-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          К оценкам
        </a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="margin-bottom:1rem">
      <?php if ($messageType==='success'): ?><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><?php else: ?><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/></svg><?php endif ?>
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif ?>

    <div class="card">
      <!-- Мета -->
      <div class="sheet-meta">
        <div class="meta-item"><span class="meta-label">Группа</span><span class="meta-val"><?= htmlspecialchars($sheet['group_name']) ?></span></div>
        <div class="meta-item"><span class="meta-label">Дисциплина</span><span class="meta-val"><?= htmlspecialchars($sheet['subject_name']) ?></span></div>
        <div class="meta-item"><span class="meta-label">Тип</span><span class="meta-val"><?= $TYPE_LABELS[$sheet['type']] ?></span></div>
        <div class="meta-item"><span class="meta-label">Семестр</span><span class="meta-val"><?= $sheet['year_start'].'/'.$sheet['year_end'] ?> – <?= $sheet['semester_num'] ?> сем.</span></div>
        <div class="meta-item"><span class="meta-label">Преподаватель</span><span class="meta-val"><?= htmlspecialchars($sheet['teacher_name'] ?? '—') ?></span></div>
        <div class="meta-item"><span class="meta-label">Статус</span><span class="badge <?= $STATUS_BADGE[$sheet['status']] ?>"><?= $STATUS_LABELS[$sheet['status']] ?></span></div>
      </div>

      <!-- Статистика -->
      <div class="stats-strip">
        <div class="stat-item"><span class="stat-value"><?= $total ?></span><span class="stat-label">Студентов</span></div>
        <div class="stat-item"><span class="stat-value"><?= count($graded) ?></span><span class="stat-label">Оценок выставлено</span></div>
        <div class="stat-item"><span class="stat-value"><?= count($absent) ?></span><span class="stat-label">Неявок</span></div>
        <?php if ($avg !== null): ?>
        <div class="stat-item"><span class="stat-value"><?= $avg ?></span><span class="stat-label">Средний балл</span></div>
        <?php endif ?>
      </div>

      <!-- Таблица оценок -->
      <form method="POST" action="grades.php?sheet_id=<?= $sheetId ?>">
        <input type="hidden" name="save_grades" value="1">

        <?php if ($canEdit): ?>
        <div style="padding:.75rem 1.5rem;background:var(--color-surface-2);border-bottom:1px solid var(--color-divider);display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
          <label style="font-size:.8125rem;font-weight:500;color:var(--color-text-muted)">Дата проведения:</label>
          <input type="date" name="grade_date" class="comment-input" style="width:auto" value="<?= date('Y-m-d') ?>">
        </div>
        <?php endif ?>

        <div class="table-wrapper">
          <table class="grades-table">
            <thead>
              <tr>
                <th>#</th><th>ФИО студента</th><th>ИИН</th>
                <?php if ($isCredit): ?><th>Зачтено</th><?php else: ?><th>Оценка</th><?php endif ?>
                <th>Неявка</th><th>Комментарий</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $i => $s):
                $cls = $s['absent'] ? ' class="absent-row"' : '';
              ?>
              <tr<?= $cls ?>>
                <td style="color:var(--color-text-muted)"><?= $i+1 ?></td>
                <td style="font-weight:500"><?= htmlspecialchars($s['surname'].' '.$s['name'].' '.$s['patronymic']) ?></td>
                <td style="font-family:monospace;font-size:.875rem"><?= htmlspecialchars($s['iin']) ?></td>
                <td>
                  <?php if ($canEdit): ?>
                    <?php if ($isCredit): ?>
                    <input type="checkbox" name="passed[<?= $s['student_id'] ?>]" value="1" <?= $s['passed'] ? 'checked' : '' ?>>
                    <?php else: ?>
                    <input type="number"
                           name="grade[<?= $s['student_id'] ?>]"
                           class="grade-input <?= edu_score_badge_class(edu_normalize_score($s['grade'])) === 'badge-green' ? 'grade-excellent' : (edu_score_badge_class(edu_normalize_score($s['grade'])) === 'badge-blue' ? 'grade-good' : (edu_score_badge_class(edu_normalize_score($s['grade'])) === 'badge-amber' ? 'grade-mid' : (edu_normalize_score($s['grade']) !== null ? 'grade-low' : ''))) ?>"
                           min="0" max="100" step="1" inputmode="numeric"
                           placeholder="0–100"
                           value="<?= htmlspecialchars($s['grade'] ?? '') ?>">
                    <?php endif ?>
                  <?php else: ?>
                    <?php if ($isCredit): ?>
                      <span class="badge <?= $s['passed']?'badge-green':'badge-gray' ?>"><?= $s['passed']?'Зачтено':'—' ?></span>
                    <?php else: ?>
                      <?php $score = edu_normalize_score($s['grade']); ?>
                      <?php if ($score !== null): ?>
                      <span class="badge <?= edu_score_badge_class($score) ?>"><?= $score ?> / <?= edu_score_letter($score) ?></span>
                      <?php else: ?><span style="color:var(--color-text-faint)">—</span><?php endif ?>
                    <?php endif ?>
                  <?php endif ?>
                </td>
                <td>
                  <?php if ($canEdit): ?>
                  <input type="checkbox" name="absent[<?= $s['student_id'] ?>]" value="1"
                         <?= $s['absent'] ? 'checked' : '' ?>
                         onchange="this.closest('tr').classList.toggle('absent-row',this.checked)">
                  <?php else: ?>
                  <?= $s['absent'] ? '<span class="badge badge-red">н</span>' : '—' ?>
                  <?php endif ?>
                </td>
                <td>
                  <?php if ($canEdit): ?>
                  <input type="text" name="comment[<?= $s['student_id'] ?>]"
                         class="comment-input" maxlength="512" placeholder="Примечание…"
                         value="<?= htmlspecialchars($s['comment'] ?? '') ?>">
                  <?php else: ?>
                  <span style="font-size:.875rem;color:var(--color-text-muted)"><?= htmlspecialchars($s['comment'] ?? '—') ?></span>
                  <?php endif ?>
                </td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>

        <?php if ($canEdit): ?>
        <div class="form-actions" style="padding:1rem 1.5rem;border-top:1px solid var(--color-divider);display:flex;gap:.75rem;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Сохранить оценки
          </button>
          <a href="grade_sheets.php?id=<?= $sheetId ?>&status=submitted"
             class="btn btn-outline"
             onclick="return confirm('Сдать ведомость на проверку? Редактирование будет закрыто.')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M3 17v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/></svg>
            Сдать на проверку
          </a>
        </div>
        <?php elseif ($sheet['status'] !== 'draft'): ?>
        <div style="padding:1rem 1.5rem;border-top:1px solid var(--color-divider);color:var(--color-text-muted);font-size:.875rem">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Ведомость заблокирована (статус: <?= $STATUS_LABELS[$sheet['status']] ?>). Директор или администратор может вернуть ведомость на доработку. Если ведомость находится на проверке, её также можно принять.
        </div>
        <?php endif ?>
      </form>
    </div>

  </main>
</div>
<script src="assets/app.js"></script>
<script>
// Подсветка select при загрузке
document.querySelectorAll('input.grade-input').forEach(input => {
  const paint = () => {
    const v = input.value === '' ? null : Number(input.value);
    input.classList.remove('grade-low','grade-mid','grade-good','grade-excellent');
    if (v === null || Number.isNaN(v)) return;
    if (v >= 90) input.classList.add('grade-excellent');
    else if (v >= 70) input.classList.add('grade-good');
    else if (v >= 50) input.classList.add('grade-mid');
    else input.classList.add('grade-low');
  };
  input.addEventListener('input', paint);
  paint();
});
</script>
</body>
</html>
