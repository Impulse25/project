<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$role      = edu_current_role();
$userId    = edu_current_user_id();
$isAdmin   = edu_is_admin();
$isDirector = edu_is_director();
$isTeacher = edu_is_teacher();
$canEdit   = false;
$canEduEditStudents = edu_can($pdo, 'can_edu_edit_students');
$canEduStudentCard  = edu_can($pdo, 'can_edu_student_card');
$canEduDiplomaBook  = edu_can($pdo, 'can_edu_diploma_book');


$studentCardExtraColumns = [
    'gender' => 'Пол',
    'birth_place' => 'Место рождения',
    'enrollment_order' => '№ и дата приказа о зачислении',
    'previous_education' => 'Образование до поступления',
    'school_finished' => 'Оконченный класс, школа и год',
    'promotion_orders' => 'Приказы о переводе на курсы',
    'graduation_order' => 'Приказ о выпуске',
    'job_assignment' => 'Направление на работу / должность',
    'state_exam_1' => 'Государственный экзамен 1',
    'state_exam_2' => 'Государственный экзамен 2',
    'state_exam_3' => 'Государственный экзамен 3',
    'diploma_topic' => 'Тема диплома',
    'diploma_score' => 'Оценка за диплом (0–100)',
];
$studentCardColumns = [];
try {
    $studentCardColumns = array_map(static fn($r) => $r['Field'], $pdo->query('SHOW COLUMNS FROM edu_student_cards')->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    $studentCardColumns = [];
}
$studentCardEnabledExtraColumns = array_values(array_filter(array_keys($studentCardExtraColumns), static fn($c) => in_array($c, $studentCardColumns, true)));

function edu_about_card_value(array $row, string $key): string {
    return trim((string)($row['card_' . $key] ?? ''));
}


// ── Получаем student_id из POST или GET ────────────────────────────────────
$studentId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if (!$studentId) {
    header('Location: index.php');
    exit;
}

$accessStmt = $pdo->prepare("
    SELECT s.group_id, g.curator_id
    FROM edu_students s
    LEFT JOIN edu_groups g ON g.id = s.group_id
    WHERE s.id = ?
");
$accessStmt->execute([$studentId]);
$accessRow = $accessStmt->fetch(PDO::FETCH_ASSOC);
if (!$accessRow) {
    header('Location: index.php');
    exit;
}

$accessibleGroupIds = edu_accessible_group_ids($pdo, $userId, $role);
$teacherOwnsStudent = $isTeacher && in_array((int)($accessRow['group_id'] ?? 0), $accessibleGroupIds, true);
$canView = $isAdmin || $isDirector || $teacherOwnsStudent;
$canEdit = $canEduEditStudents && ($isAdmin || $teacherOwnsStudent);
if (!$canView) {
    header('Location: index.php');
    exit;
}

// ── Обработка сохранения ───────────────────────────────────────────────────
$message     = '';
$messageType = '';

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_card'])) {
    $surname      = trim($_POST['surname']      ?? '');
    $name         = trim($_POST['name']         ?? '');
    $patronymic   = trim($_POST['patronymic']   ?? '');
    $birth_date   = trim($_POST['birth_date']   ?? '');
    $iin          = trim($_POST['iin']          ?? '');
    $group_id     = ($_POST['group_id'] ?? '') !== '' ? (int)$_POST['group_id'] : null;
    $speciality_id= ($_POST['speciality_id'] ?? '') !== '' ? (int)$_POST['speciality_id'] : null;
    $citizenship  = trim($_POST['citizenship']  ?? '');
    $nationality  = trim($_POST['nationality']  ?? '');
    $notes        = trim($_POST['notes']        ?? '');
    $groupAccessDenied = false;

    if ($isTeacher && $group_id !== null) {
        if (!in_array($group_id, $accessibleGroupIds, true)) {
            $message = 'Нет доступа к выбранной группе.';
            $messageType = 'error';
            $groupAccessDenied = true;
        }
    }

    // Загрузка фото
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            $uploadDir = __DIR__ . '/uploads/students/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename  = $studentId . '.' . $ext;
            $destPath  = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                $photoPath = 'uploads/students/' . $filename;
            }
        }
    }

    if (!$groupAccessDenied) {
    try {
        // Обновляем edu_students
        $pdo->prepare("
            UPDATE edu_students
            SET surname=?, name=?, patronymic=?, birth_date=?,
                iin=?, group_id=?, speciality_id=?, citizenship=?, nationality=?
            WHERE id=?
        ")->execute([$surname, $name, $patronymic, $birth_date ?: null,
                     $iin, $group_id, $speciality_id, $citizenship, $nationality,
                     $studentId]);

        // Upsert edu_student_cards. Дополнительные поля появятся после миграции 008.
        $cardInsertCols = ['student_id'];
        $cardInsertVals = [$studentId];
        $cardUpdateCols = [];

        if ($photoPath !== null && in_array('photo_path', $studentCardColumns, true)) {
            $cardInsertCols[] = 'photo_path';
            $cardInsertVals[] = $photoPath;
            $cardUpdateCols[] = 'photo_path';
        }
        if (in_array('notes', $studentCardColumns, true)) {
            $cardInsertCols[] = 'notes';
            $cardInsertVals[] = $notes;
            $cardUpdateCols[] = 'notes';
        }
        foreach ($studentCardEnabledExtraColumns as $extraCol) {
            $cardInsertCols[] = $extraCol;
            if ($extraCol === 'diploma_score') {
                $rawScore = trim((string)($_POST[$extraCol] ?? ''));
                if ($rawScore === '') {
                    $cardInsertVals[] = null;
                } else {
                    $score = (int)round((float)str_replace(',', '.', $rawScore));
                    $score = max(0, min(100, $score));
                    $cardInsertVals[] = $score;
                }
            } else {
                $cardInsertVals[] = trim((string)($_POST[$extraCol] ?? ''));
            }
            $cardUpdateCols[] = $extraCol;
        }
        if ($cardUpdateCols) {
            $quotedCols = array_map(static fn($c) => '`' . str_replace('`', '``', $c) . '`', $cardInsertCols);
            $placeholders = implode(', ', array_fill(0, count($cardInsertCols), '?'));
            $updates = implode(', ', array_map(static fn($c) => '`' . str_replace('`', '``', $c) . '`=VALUES(`' . str_replace('`', '``', $c) . '`)', $cardUpdateCols));
            $sql = 'INSERT INTO edu_student_cards (' . implode(', ', $quotedCols) . ') VALUES (' . $placeholders . ') ON DUPLICATE KEY UPDATE ' . $updates;
            $pdo->prepare($sql)->execute($cardInsertVals);
        }

        $message     = 'Данные успешно сохранены.';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message     = 'Ошибка сохранения: ' . $e->getMessage();
        $messageType = 'error';
    }
    }
}

// ── Загружаем данные из БД ─────────────────────────────────────────────────
$extraCardSelect = '';
foreach ($studentCardEnabledExtraColumns as $extraCol) {
    $extraCardSelect .= ', sc.`' . str_replace('`', '``', $extraCol) . '` AS card_' . $extraCol;
}
$stmt = $pdo->prepare("
    SELECT s.*,
           g.name        AS group_name,
           g.curator_id  AS curator_id,
           sp.name_ru    AS specialty_name,
           sc.photo_path AS card_photo,
           sc.notes      AS card_notes
           $extraCardSelect
    FROM edu_students s
    LEFT JOIN edu_groups      g  ON g.id  = s.group_id
    LEFT JOIN edu_specialties sp ON sp.id = s.speciality_id
    LEFT JOIN edu_student_cards sc ON sc.student_id = s.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$s) {
    header('Location: index.php');
    exit;
}

// Все группы и специальности для формы редактирования
if ($canEdit && $isAdmin) {
    $allGroups = $pdo->query("SELECT id, name FROM edu_groups ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($canEdit && $isTeacher) {
    $allGroups = $accessibleGroupIds
        ? $pdo->query("SELECT id, name FROM edu_groups WHERE id IN (" . edu_in_int_list($accessibleGroupIds) . ") ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
        : [];
} else {
    $allGroups = [];
}
$allSpecialties = $canEdit
    ? $pdo->query("SELECT id, name_ru FROM edu_specialties ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC)
    : [];

// ── Вычисляемые поля ───────────────────────────────────────────────────────
$fullName = trim($s['surname'] . ' ' . $s['name'] . ' ' . $s['patronymic']);
$initials = mb_strtoupper(mb_substr($s['surname'], 0, 1) . mb_substr($s['name'], 0, 1));

$age = '';
if (!empty($s['birth_date'])) {
    try {
        $age = (new DateTime($s['birth_date']))->diff(new DateTime())->y . ' лет';
    } catch (Exception $e) {}
}

// Фото: сначала из card, потом filesystem fallback
$photoPath = '';
if (!empty($s['card_photo']) && file_exists(__DIR__ . '/' . $s['card_photo'])) {
    $photoPath = $s['card_photo'];
} else {
    foreach ([
        "uploads/students/{$s['iin']}.jpg",
        "uploads/students/{$s['iin']}.png",
        "uploads/students/{$s['id']}.jpg",
        "uploads/students/{$s['id']}.png",
    ] as $p) {
        if (file_exists(__DIR__ . '/' . $p)) { $photoPath = $p; break; }
    }
}

// Текущий учебный год
$y = (int)date('n') >= 9 ? (int)date('Y') : (int)date('Y') - 1;
$academicYear = $y . '–' . ($y + 1);

$pageTitle       = htmlspecialchars($fullName ?: 'Личная карточка');
$activeNav       = 'edu';
$sidebarSubtitle = 'Личная карточка';
$extraFonts      = ['Playfair+Display:wght@700'];
$breadcrumbs     = [
    ['label' => 'СВГТК',           'href' => '../'],
    ['label' => 'Учебный процесс', 'href' => 'index.php'],
    ['label' => $fullName ?: 'Карточка'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <?php require 'includes/head.php' ?>
  <style>
    .page-content { padding: 2rem 1.5rem; }

    /* Hero */
    .hero-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-2xl); box-shadow: var(--shadow-lg); overflow: hidden; margin-bottom: 1.5rem; }
    .hero-banner { height: 140px; background: linear-gradient(135deg, #1a56db 0%, #7c3aed 50%, #0ea5e9 100%); position: relative; overflow: hidden; }
    .hero-banner::before { content: ''; position: absolute; inset: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
    .hero-banner-orb { position: absolute; border-radius: 50%; opacity: 0.2; }
    .hero-banner-orb-1 { width: 200px; height: 200px; background: #fff; top: -80px; right: -50px; }
    .hero-banner-orb-2 { width: 120px; height: 120px; background: #fff; bottom: -60px; left: 15%; }
    .hero-body { padding: 0 2rem 2rem; display: flex; gap: 2rem; align-items: flex-end; flex-wrap: wrap; }

    /* Фото */
    .student-photo-wrap { margin-top: -52px; flex-shrink: 0; position: relative; }
    .student-photo { width: 104px; height: 104px; border-radius: var(--radius-xl); border: 4px solid var(--color-surface); box-shadow: var(--shadow-md); object-fit: cover; display: block; }
    .student-avatar-fallback { width: 104px; height: 104px; border-radius: var(--radius-xl); border: 4px solid var(--color-surface); box-shadow: var(--shadow-md); background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-accent) 100%); display: flex; align-items: center; justify-content: center; font-size: 2.25rem; font-weight: 700; color: #fff; letter-spacing: -1px; }

    .hero-info { flex: 1; min-width: 0; padding-top: 0.5rem; }
    .hero-name { font-family: var(--font-serif, serif); font-size: clamp(1.5rem, 1.2rem + 1vw, 2rem); font-weight: 700; color: var(--color-text); line-height: 1.15; margin-bottom: 0.5rem; word-break: break-word; }
    .hero-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
    .meta-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 99px; font-size: 0.8125rem; font-weight: 500; background: var(--color-surface-2); color: var(--color-text-muted); border: 1px solid var(--color-border); }
    .chip-group { background: var(--color-primary-highlight); color: var(--color-primary); border-color: transparent; }
    .chip-id    { background: var(--color-surface-offset); }

    /* Info grid */
    .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
    .info-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); overflow: hidden; transition: box-shadow var(--transition), transform var(--transition); }
    .info-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .info-card-header { display: flex; align-items: center; gap: 0.75rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-divider); background: var(--color-surface-2); }
    .info-card-icon { width: 34px; height: 34px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .icon-blue   { background: var(--color-primary-highlight); color: var(--color-primary); }
    .icon-green  { background: var(--color-success-highlight); color: var(--color-success); }
    .icon-purple { background: var(--color-accent-light); color: var(--color-accent); }
    .info-card-title { font-weight: 600; font-size: 0.9375rem; }
    .info-rows { padding: 0.5rem 0; }
    .info-row { display: flex; align-items: baseline; gap: 1rem; padding: 0.625rem 1.25rem; border-bottom: 1px solid var(--color-divider); }
    .info-row:last-child { border-bottom: none; }
    .info-label { font-size: 0.875rem; color: var(--color-text-muted); font-weight: 500; min-width: 130px; flex-shrink: 0; }
    .info-value { font-size: 0.9375rem; color: var(--color-text); font-weight: 500; word-break: break-word; }
    .info-value.mono { font-family: 'SF Mono','Fira Code',monospace; font-size: 0.875rem; letter-spacing: 0.03em; }
    .info-value .empty { color: var(--color-text-faint); font-style: italic; font-weight: 400; }

    /* Форма редактирования */
    .edit-section { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 1.5rem; }
    .edit-section-header { display: flex; align-items: center; gap: 0.75rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-divider); background: var(--color-surface-2); }
    .edit-section-body { padding: 1.5rem; }
    .edit-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 1rem; }
    .edit-field { display: flex; flex-direction: column; gap: .375rem; }
    .edit-field label { font-size: .8125rem; font-weight: 500; color: var(--color-text-muted); }
    .edit-field input, .edit-field select, .edit-field textarea {
        padding: .5rem .75rem; border: 1px solid var(--color-border);
        border-radius: var(--radius-md); background: var(--color-surface);
        color: var(--color-text); font-size: .9375rem;
        transition: border-color var(--transition);
    }
    .edit-field input:focus, .edit-field select:focus, .edit-field textarea:focus {
        outline: none; border-color: var(--color-primary);
    }
    .edit-field textarea { resize: vertical; min-height: 80px; }
    .photo-preview-wrap { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .photo-preview-thumb { width: 80px; height: 80px; border-radius: var(--radius-lg); object-fit: cover; border: 2px solid var(--color-border); flex-shrink: 0; }
    .photo-preview-fallback-sm { width: 80px; height: 80px; border-radius: var(--radius-lg); background: var(--color-surface-offset); border: 2px dashed var(--color-border); display: flex; align-items: center; justify-content: center; color: var(--color-text-faint); flex-shrink: 0; }

    /* Actions */
    .actions-bar { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: 1.5rem; }

    /* Print */
    @media print {
      .sidebar, .topbar, .actions-bar, .edit-section, .theme-toggle { display: none !important; }
      .main-wrapper { margin-left: 0 !important; }
      .page-content { padding: 1rem; }
      .hero-card, .info-card { box-shadow: none !important; border: 1px solid #ccc !important; }
      .hero-banner { height: 80px !important; }
      body { background: white !important; }
    }
    @media (max-width: 768px) {
      .hero-body { padding: 0 1rem 1.5rem; gap: 1rem; }
      .hero-banner { height: 100px; }
      .student-photo, .student-avatar-fallback { width: 80px; height: 80px; font-size: 1.75rem; }
      .info-label { min-width: 90px; }
    }
  </style>
</head>
<body>

<?php require 'includes/sidebar.php' ?>

<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>

  <main class="page-content">

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="margin-bottom:1rem">
      <?php if ($messageType === 'success'): ?>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php else: ?>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?php endif ?>
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif ?>

    <!-- Кнопки действий -->
    <div class="actions-bar">
      <a href="index.php" class="btn btn-outline">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Назад к списку
      </a>
      <?php if ($canEduStudentCard): ?>
      <a href="export_student_card.php?student_id=<?= $studentId ?>" class="btn btn-outline">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Личная карточка
      </a>
      <?php endif ?>
      <?php if ($canEduDiplomaBook): ?>
      <a href="export_diploma_book.php?student_id=<?= $studentId ?>" class="btn btn-outline">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Дипломная книга
      </a>
      <?php endif ?>
      <?php if ($canEdit): ?>
      <button class="btn btn-primary" onclick="toggleEdit()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Редактировать
      </button>
      <?php endif ?>
    </div>

    <!-- Hero -->
    <div class="hero-card">
      <div class="hero-banner">
        <div class="hero-banner-orb hero-banner-orb-1"></div>
        <div class="hero-banner-orb hero-banner-orb-2"></div>
      </div>
      <div class="hero-body">
        <div class="student-photo-wrap">
          <?php if ($photoPath): ?>
          <img src="<?= htmlspecialchars($photoPath) ?>" alt="Фото" class="student-photo">
          <?php else: ?>
          <div class="student-avatar-fallback"><?= htmlspecialchars($initials) ?></div>
          <?php endif ?>
        </div>
        <div class="hero-info">
          <div class="hero-name"><?= htmlspecialchars($fullName ?: 'Имя не указано') ?></div>
          <div class="hero-meta">
            <?php if (!empty($s['group_name'])): ?>
            <span class="meta-chip chip-group">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              <?= htmlspecialchars($s['group_name']) ?>
            </span>
            <?php endif ?>
            <span class="meta-chip chip-id">ID: <?= $s['id'] ?></span>
            <?php if ($age): ?>
            <span class="meta-chip chip-id"><?= $age ?></span>
            <?php endif ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Форма редактирования (скрыта по умолчанию) -->
    <?php if ($canEdit): ?>
    <div class="edit-section" id="editSection" style="display:none">
      <div class="edit-section-header">
        <div class="info-card-icon icon-blue">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <span style="font-weight:600;font-size:.9375rem">Редактирование карточки</span>
      </div>
      <div class="edit-section-body">
        <form method="POST" action="about_student.php?id=<?= $studentId ?>" enctype="multipart/form-data">
          <input type="hidden" name="id"        value="<?= $studentId ?>">
          <input type="hidden" name="save_card" value="1">

          <div class="edit-grid" style="margin-bottom:1.25rem">
            <div class="edit-field">
              <label>Фамилия <span style="color:var(--color-danger)">*</span></label>
              <input type="text" name="surname" maxlength="255" required value="<?= htmlspecialchars($s['surname']) ?>">
            </div>
            <div class="edit-field">
              <label>Имя <span style="color:var(--color-danger)">*</span></label>
              <input type="text" name="name" maxlength="255" required value="<?= htmlspecialchars($s['name']) ?>">
            </div>
            <div class="edit-field">
              <label>Отчество</label>
              <input type="text" name="patronymic" maxlength="255" value="<?= htmlspecialchars($s['patronymic']) ?>">
            </div>
            <div class="edit-field">
              <label>Дата рождения</label>
              <input type="date" name="birth_date" value="<?= htmlspecialchars($s['birth_date'] ?? '') ?>">
            </div>
            <div class="edit-field">
              <label>ИИН</label>
              <input type="text" name="iin" maxlength="12" pattern="\d{12}" placeholder="12 цифр" value="<?= htmlspecialchars($s['iin']) ?>">
            </div>
            <div class="edit-field">
              <label>Группа</label>
              <select name="group_id">
                <option value="">— не указана —</option>
                <?php foreach ($allGroups as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $s['group_id'] == $g['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($g['name']) ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="edit-field">
              <label>Специальность</label>
              <select name="speciality_id">
                <option value="">— не указана —</option>
                <?php foreach ($allSpecialties as $sp): ?>
                <option value="<?= $sp['id'] ?>" <?= $s['speciality_id'] == $sp['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sp['name_ru']) ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="edit-field">
              <label>Гражданство</label>
              <input type="text" name="citizenship" maxlength="100" value="<?= htmlspecialchars($s['citizenship'] ?? '') ?>">
            </div>
            <div class="edit-field">
              <label>Национальность</label>
              <input type="text" name="nationality" maxlength="100" value="<?= htmlspecialchars($s['nationality'] ?? '') ?>">
            </div>
            <?php if (in_array('gender', $studentCardEnabledExtraColumns, true)): ?>
            <div class="edit-field">
              <label>Пол для личной карточки</label>
              <select name="gender">
                <option value="">— не указан —</option>
                <?php foreach (['мужской', 'женский'] as $genderOption): ?>
                <option value="<?= $genderOption ?>" <?= edu_about_card_value($s, 'gender') === $genderOption ? 'selected' : '' ?>><?= $genderOption ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <?php endif ?>
            <?php foreach (['birth_place', 'enrollment_order', 'previous_education', 'school_finished'] as $extraField): ?>
              <?php if (in_array($extraField, $studentCardEnabledExtraColumns, true)): ?>
              <div class="edit-field">
                <label><?= htmlspecialchars($studentCardExtraColumns[$extraField]) ?></label>
                <input type="text" name="<?= htmlspecialchars($extraField) ?>" maxlength="255" value="<?= htmlspecialchars(edu_about_card_value($s, $extraField)) ?>">
              </div>
              <?php endif ?>
            <?php endforeach ?>
          </div>

          <!-- Фото -->
          <div style="margin-bottom:1.25rem">
            <div class="edit-field">
              <label>Фотография (JPG, PNG · рек. 300×400 px)</label>
              <div class="photo-preview-wrap" style="margin-bottom:.5rem">
                <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" class="photo-preview-thumb" id="photoPreviewImg">
                <?php else: ?>
                <div class="photo-preview-fallback-sm" id="photoPreviewFallback">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <?php endif ?>
                <span style="font-size:.8125rem;color:var(--color-text-muted)" id="photoFileName">
                  <?= $photoPath ? basename($photoPath) : 'Фото не загружено' ?>
                </span>
              </div>
              <input type="file" name="photo" accept=".jpg,.jpeg,.png" id="photoInput">
            </div>
          </div>

          <?php if ($studentCardEnabledExtraColumns): ?>
          <div class="edit-grid" style="margin-bottom:1.25rem">
            <?php foreach (['promotion_orders', 'graduation_order', 'job_assignment', 'state_exam_1', 'state_exam_2', 'state_exam_3', 'diploma_topic', 'diploma_score'] as $extraField): ?>
              <?php if (in_array($extraField, $studentCardEnabledExtraColumns, true)): ?>
              <div class="edit-field" style="<?= in_array($extraField, ['promotion_orders', 'job_assignment', 'diploma_topic'], true) ? 'grid-column:1/-1' : '' ?>">
                <label><?= htmlspecialchars($studentCardExtraColumns[$extraField]) ?></label>
                <?php if (in_array($extraField, ['promotion_orders', 'job_assignment', 'diploma_topic'], true)): ?>
                <textarea name="<?= htmlspecialchars($extraField) ?>"><?= htmlspecialchars(edu_about_card_value($s, $extraField)) ?></textarea>
                <?php elseif ($extraField === 'diploma_score'): ?>
                <input type="number" name="<?= htmlspecialchars($extraField) ?>" min="0" max="100" step="1" value="<?= htmlspecialchars(edu_about_card_value($s, $extraField)) ?>">
                <?php else: ?>
                <input type="text" name="<?= htmlspecialchars($extraField) ?>" maxlength="255" value="<?= htmlspecialchars(edu_about_card_value($s, $extraField)) ?>">
                <?php endif ?>
              </div>
              <?php endif ?>
            <?php endforeach ?>
          </div>
          <?php endif ?>

          <!-- Заметки -->
          <div class="edit-field" style="margin-bottom:1.25rem">
            <label>Заметки / примечания</label>
            <textarea name="notes"><?= htmlspecialchars($s['card_notes'] ?? '') ?></textarea>
          </div>

          <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Сохранить
            </button>
            <button type="button" class="btn btn-outline" onclick="toggleEdit()">Отмена</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif ?>

    <!-- Карточки с данными -->
    <div class="info-grid">

      <!-- Личные данные -->
      <div class="info-card">
        <div class="info-card-header">
          <div class="info-card-icon icon-blue">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <span class="info-card-title">Личные данные</span>
        </div>
        <div class="info-rows">
          <div class="info-row"><span class="info-label">Фамилия</span>
            <span class="info-value"><?= $s['surname'] ? htmlspecialchars($s['surname']) : '<span class="empty">Не указана</span>' ?></span></div>
          <div class="info-row"><span class="info-label">Имя</span>
            <span class="info-value"><?= $s['name'] ? htmlspecialchars($s['name']) : '<span class="empty">Не указано</span>' ?></span></div>
          <div class="info-row"><span class="info-label">Отчество</span>
            <span class="info-value"><?= $s['patronymic'] ? htmlspecialchars($s['patronymic']) : '<span class="empty">—</span>' ?></span></div>
          <div class="info-row"><span class="info-label">Дата рождения</span>
            <span class="info-value"><?= !empty($s['birth_date']) ? htmlspecialchars($s['birth_date']) : '<span class="empty">Не указана</span>' ?></span></div>
          <?php if ($age): ?>
          <div class="info-row"><span class="info-label">Возраст</span>
            <span class="info-value"><?= $age ?></span></div>
          <?php endif ?>
        </div>
      </div>

      <!-- Идентификация -->
      <div class="info-card">
        <div class="info-card-header">
          <div class="info-card-icon icon-green">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
          <span class="info-card-title">Идентификация</span>
        </div>
        <div class="info-rows">
          <div class="info-row"><span class="info-label">ID студента</span>
            <span class="info-value mono"><?= $s['id'] ?></span></div>
          <div class="info-row"><span class="info-label">ИИН</span>
            <span class="info-value mono"><?= $s['iin'] ? htmlspecialchars($s['iin']) : '<span class="empty">Не указан</span>' ?></span></div>
          <div class="info-row"><span class="info-label">Гражданство</span>
            <span class="info-value"><?= !empty($s['citizenship']) ? htmlspecialchars($s['citizenship']) : '<span class="empty">Не указано</span>' ?></span></div>
          <div class="info-row"><span class="info-label">Национальность</span>
            <span class="info-value"><?= !empty($s['nationality']) ? htmlspecialchars($s['nationality']) : '<span class="empty">Не указана</span>' ?></span></div>
        </div>
      </div>

      <!-- Учебная информация -->
      <div class="info-card">
        <div class="info-card-header">
          <div class="info-card-icon icon-purple">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
          </div>
          <span class="info-card-title">Учебная информация</span>
        </div>
        <div class="info-rows">
          <div class="info-row">
            <span class="info-label">Группа</span>
            <span class="info-value">
              <?php if (!empty($s['group_name'])): ?>
              <span style="display:inline-flex;align-items:center;background:var(--color-primary-highlight);color:var(--color-primary);padding:2px 10px;border-radius:var(--radius-full);font-size:.875rem;font-weight:600"><?= htmlspecialchars($s['group_name']) ?></span>
              <?php else: ?><span class="empty">Не указана</span><?php endif ?>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">Специальность</span>
            <span class="info-value" style="font-size:.875rem"><?= !empty($s['specialty_name']) ? htmlspecialchars($s['specialty_name']) : '<span class="empty">Не указана</span>' ?></span>
          </div>
          <div class="info-row"><span class="info-label">Учебный год</span>
            <span class="info-value"><?= $academicYear ?></span></div>
          <div class="info-row"><span class="info-label">Учреждение</span>
            <span class="info-value" style="font-size:.875rem">СВГТК им. Абая Кунанбаева</span></div>
        </div>
      </div>

    </div>

    <!-- Заметки (если есть) -->
    <?php if (!empty($s['card_notes'])): ?>
    <div class="info-card" style="margin-bottom:1.5rem">
      <div class="info-card-header">
        <div class="info-card-icon icon-blue">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <span class="info-card-title">Заметки</span>
      </div>
      <div style="padding:1rem 1.25rem;font-size:.9375rem;color:var(--color-text);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars($s['card_notes']) ?></div>
    </div>
    <?php endif ?>

  </main>
</div>

<script src="assets/app.js"></script>
<script>
function toggleEdit() {
  const sec = document.getElementById('editSection');
  if (!sec) return;
  const visible = sec.style.display !== 'none';
  sec.style.display = visible ? 'none' : 'block';
  if (!visible) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

if (new URLSearchParams(window.location.search).get('edit') === '1') {
  const sec = document.getElementById('editSection');
  if (sec) {
    sec.style.display = 'block';
    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// Превью фото при выборе файла
const photoInput = document.getElementById('photoInput');
if (photoInput) {
  photoInput.addEventListener('change', function () {
    if (!this.files.length) return;
    const file = this.files[0];
    document.getElementById('photoFileName').textContent = file.name;
    const reader = new FileReader();
    reader.onload = e => {
      let img = document.getElementById('photoPreviewImg');
      let fb  = document.getElementById('photoPreviewFallback');
      if (!img) {
        img = document.createElement('img');
        img.id        = 'photoPreviewImg';
        img.className = 'photo-preview-thumb';
        if (fb) fb.replaceWith(img);
      }
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

</script>
</body>
</html>
