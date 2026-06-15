<?php
// Перенаправление на Главную панель при входе в модуль
require_once 'includes/auth.php';
requireLogin();
if (empty($_GET) && empty($_POST)) {
    header('Location: dashboard.php');
    exit;
}
require_once 'includes/header.php';

$pdo = getPDO();

// Текущий авторизованный пользователь (кто добавляет запись)
$current_user_id = $_SESSION['user_id'] ?? 1; 
$role = $_SESSION['role'] ?? 'student';

// Определение активной вкладки
$activeTab = $_GET['tab'] ?? 'students';

// Получение фильтров
$filterSearch   = trim($_GET['q'] ?? '');
$filterGroup    = (int)($_GET['group_id'] ?? 0);
$filterCategory = trim($_GET['category'] ?? '');
$filterLevel    = trim($_GET['level'] ?? '');


// ==========================================
// 3. ОБРАБОТКА ДОБАВЛЕНИЯ ДОСТИЖЕНИЙ (POST)
// ==========================================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_student_achievement') {
            $user_id     = (int)$_POST['student_user_id'];
            $title       = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            $category    = trim($_POST['category']);
            $level       = trim($_POST['level']);
            $place       = trim($_POST['place'] ?? '');
            $date_event  = $_POST['date_event'] ?: null;

            if ($user_id > 0 && !empty($title)) {
                $stmt = $pdo->prepare("INSERT INTO achievements (user_id, title, description, category, level, place, date_event, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $title, $description, $category, $level, $place, $date_event, $current_user_id]);
                $message = "🏆 Достижение студента успешно добавлено!";
                $messageType = "success";
            }
        } 
        
        if ($_POST['action'] === 'add_teacher_achievement') {
            $user_id     = (int)$_POST['teacher_user_id'];
            $title       = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            $category    = trim($_POST['category']);
            $level       = trim($_POST['level']);
            $place       = trim($_POST['place'] ?? '');
            $date_event  = $_POST['date_event'] ?: null;

            if ($user_id > 0 && !empty($title)) {
                $stmt = $pdo->prepare("INSERT INTO achievements (user_id, title, description, category, level, place, date_event, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $title, $description, $category, $level, $place, $date_event, $current_user_id]);
                $message = "👑 Достижение преподавателя успешно добавлено!";
                $messageType = "success";
            }
        }
    } catch (Exception $e) {
        $message = "❌ Ошибка при добавлении: " . $e->getMessage();
        $messageType = "error";
    }
}


// --- 1. СБОР ДАННЫХ ДЛЯ ВЕРХНИХ ВИДЖЕТОВ (СТАТИСТИКА) ---
try {
    $countStudentsAch = $pdo->query("SELECT COUNT(*) FROM achievements a JOIN users u ON a.user_id = u.id WHERE u.role = 'student'")->fetchColumn();
    $countTeachersAch = $pdo->query("SELECT COUNT(*) FROM achievements a JOIN users u ON a.user_id = u.id WHERE u.role = 'teacher'")->fetchColumn();
    
    $stmtLevels = $pdo->query("SELECT level, COUNT(*) as cnt FROM achievements GROUP BY level");
    $levelStats = $stmtLevels->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $countStudentsAch = 0; $countTeachersAch = 0; $levelStats = [];
}

$countInter = $levelStats['international'] ?? 0;
$countNat   = $levelStats['national'] ?? 0;
$countReg   = $levelStats['regional'] ?? 0;
$countCity  = $levelStats['city'] ?? 0;
$countColl  = $levelStats['college'] ?? 0;


// --- 2. ПОСТРОЕНИЕ ДИНАМИЧЕСКОГО SQL-ЗАПРОСА ТАБЛИЦЫ ---
$whereClauses = [];
$params = [];

if ($activeTab === 'teachers') {
    $whereClauses[] = "u.role = 'teacher'";
} else {
    $whereClauses[] = "u.role = 'student'";
}

if ($filterSearch !== '') {
    $whereClauses[] = "(u.full_name LIKE ? OR a.title LIKE ? OR a.description LIKE ?)";
    $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%";
}

if ($filterGroup > 0 && $activeTab === 'students') {
    $whereClauses[] = "s.group_id = ?";
    $params[] = $filterGroup;
}

if ($filterCategory !== '') {
    $whereClauses[] = "a.category = ?";
    $params[] = $filterCategory;
}

if ($filterLevel !== '') {
    $whereClauses[] = "a.level = ?";
    $params[] = $filterLevel;
}

$whereSQL = implode(' AND ', $whereClauses);

try {
    $sql = "
        SELECT a.*, u.full_name, u.id AS user_id, u.role,
               eg.name AS group_name,
               added.full_name AS added_by_name
        FROM achievements a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN students s ON s.user_id = u.id
        LEFT JOIN groups_list eg ON s.group_id = eg.id
        LEFT JOIN users added ON a.added_by = added.id
        WHERE {$whereSQL}
        ORDER BY a.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $achievements = $stmt->fetchAll();
} catch (Exception $e) {
    $achievements = [];
}

// Списки для выпадающих меню в модальных окнах
try {
    $groupsList = $pdo->query("SELECT id, name FROM groups_list ORDER BY name ASC")->fetchAll();
    $allStudents = $pdo->query("SELECT id, full_name FROM users WHERE role = 'student' ORDER BY full_name ASC")->fetchAll();
    $allTeachers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name ASC")->fetchAll();
} catch (Exception $e) {
    $groupsList = []; $allStudents = []; $allTeachers = [];
}
?>

<style>
  :root {
    --primary-blue: #0284c7;
    --primary-hover: #0369a1;
    --primary-light: #e0f2fe;
    --text-dark: #0f2240;
    --text-muted: #64748b;
    --bg-workspace: #f1f5f9;
    --border-color: #e2e8f0;
    --radius-container: 16px;
    --radius-element: 10px;
  }

  body {
    background-color: var(--bg-workspace);
    color: var(--text-dark);
    font-family: 'Segoe UI', system-ui, sans-serif;
  }

  /* Панель заголовка */
  .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; }
  .dashboard-title h1 { font-size: 1.75rem; font-weight: 800; color: var(--text-dark); margin: 0; display: flex; align-items: center; gap: 10px; }
  .dashboard-subtitle { font-size: 0.88rem; color: var(--text-muted); margin-top: 4px; }

  /* Кнопки */
  .action-btn-group { display: flex; gap: 12px; }
  .btn-action { padding: 10px 20px; font-size: 0.9rem; font-weight: 700; border-radius: var(--radius-element); border: none; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px; }
  .btn-action-blue { background-color: var(--primary-blue); color: #fff; }
  .btn-action-blue:hover { background-color: var(--primary-hover); }
  .btn-action-white { background-color: #fff; color: var(--text-dark); border: 1px solid var(--border-color); }
  .btn-action-white:hover { background-color: #f8fafc; }

  /* Сетка карточек аналитики */
  .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 14px; margin-bottom: 24px; }
  .analytics-card { background: #fff; padding: 14px; border-radius: var(--radius-container); border: 1px solid var(--border-color); text-align: center; box-shadow: 0 4px 6px -1px rgba(15, 34, 64, 0.02); }
  .analytics-card-icon { font-size: 1.1rem; margin-bottom: 4px; }
  .analytics-card-title { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.02em; }
  .analytics-card-value { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 4px; }

  /* Уведомления */
  .alert-box { padding: 14px 20px; border-radius: var(--radius-element); margin-bottom: 20px; font-weight: 600; font-size: 0.95rem; }
  .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
  .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

  /* Фильтры */
  .filter-box { background: #fff; padding: 20px; border-radius: var(--radius-container); border: 1px solid var(--border-color); margin-bottom: 24px; }
  .filter-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto auto; gap: 12px; align-items: flex-end; }
  .filter-item { display: flex; flex-direction: column; gap: 6px; }
  .filter-item label { font-size: 0.82rem; font-weight: 700; color: var(--text-dark); }
  .filter-input { padding: 10px 14px; font-size: 0.9rem; border: 1.5px solid var(--border-color); border-radius: var(--radius-element); background: #f8fafc; color: var(--text-dark); outline: none; height: 40px; box-sizing: border-box; }
  .btn-filter-search { background: var(--primary-blue); color: #fff; border: none; font-weight: 700; padding: 0 22px; height: 40px; border-radius: var(--radius-element); cursor: pointer; }
  .btn-filter-clear { background: #fff; color: var(--text-dark); border: 1.5px solid var(--border-color); font-weight: 600; padding: 0 16px; height: 40px; border-radius: var(--radius-element); display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }

  /* Табы */
  .navigation-tabs { display: flex; gap: 6px; background: #fff; padding: 10px 20px 0 20px; border-top-left-radius: var(--radius-container); border-top-right-radius: var(--radius-container); border: 1px solid var(--border-color); border-bottom: none; }
  .tab-item { padding: 10px 16px; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); text-decoration: none; border-bottom: 3px solid transparent; display: flex; align-items: center; gap: 6px; }
  .tab-item .tab-counter { background: #f1f5f9; color: var(--text-muted); font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; }
  .tab-item.active { color: var(--primary-blue); border-bottom-color: var(--primary-blue); }
  .tab-item.active .tab-counter { background: var(--primary-light); color: var(--primary-blue); }

  /* Таблица */
  .table-container-card { background: #fff; border-bottom-left-radius: var(--radius-container); border-bottom-right-radius: var(--radius-container); border: 1px solid var(--border-color); border-top: none; overflow: hidden; }
  .main-data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
  .main-data-table th { background: #f8fafc; color: var(--text-muted); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; padding: 14px 20px; border-bottom: 1.5px solid var(--border-color); }
  .main-data-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; }
  .profile-link { color: var(--primary-blue); font-weight: 700; text-decoration: none; }
  .achievement-name { font-weight: 700; color: var(--text-dark); }
  .achievement-details { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }
  .badge-pill { display: inline-block; padding: 4px 10px; font-size: 0.78rem; font-weight: 600; border-radius: 8px; background: #f1f5f9; color: #475569; }
  .badge-category { background: #e0f2fe; color: #0369a1; }
  .badge-level { background: #f0fdf4; color: #166534; }

  /* Медали */
  .medal-container { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 0.8rem; font-weight: 700; border-radius: 12px; }
  .gold-medal { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
  .silver-medal { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
  .bronze-medal { background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }
  .delete-action-btn { background: #fee2e2; color: #ef4444; border: none; width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }

  /* ==========================================
     СТИЛИ МОДАЛЬНЫХ ОКОН (MODALS)
     ========================================== */
  .modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 34, 64, 0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.25s ease;
  }
  .modal-overlay.active { display: flex; opacity: 1; }
  .modal-window {
    background: #fff;
    width: 100%;
    max-width: 500px;
    border-radius: var(--radius-container);
    padding: 28px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    transform: translateY(-20px);
    transition: transform 0.25s ease;
  }
  .modal-overlay.active .modal-window { transform: translateY(0); }
  .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .modal-header h3 { font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0; }
  .modal-close-x { font-size: 1.5rem; color: var(--text-muted); cursor: pointer; border: none; background: none; }
  .modal-close-x:hover { color: #ef4444; }
  .modal-form { display: flex; flex-direction: column; gap: 14px; }
  .modal-form-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px; }
</style>

<?php if ($message): ?>
  <div class="alert-box alert-<?= $messageType ?>"><?= $message ?></div>
<?php endif; ?>

<div class="dashboard-header">
  <div class="dashboard-title">
    <h1>🏆 Достижения</h1>
  </div>
  
  <?php if (in_array($role, ['admin','teacher'])): ?>
  <div class="action-btn-group">
    <button class="btn-action btn-action-blue" onclick="openModal('modal-ach-student')">+ Студент</button>
    <?php if ($role === 'admin'): ?>
    <button class="btn-action btn-action-white" onclick="openModal('modal-ach-teacher')">+ Преподаватель</button>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="analytics-grid">
  <div class="analytics-card"><div class="analytics-card-icon">🎓</div><div class="analytics-card-title">Студенты</div><div class="analytics-card-value"><?= $countStudentsAch ?></div></div>
  <div class="analytics-card"><div class="analytics-card-icon">💼</div><div class="analytics-card-title">Преподаватели</div><div class="analytics-card-value"><?= $countTeachersAch ?></div></div>
  <div class="analytics-card"><div class="analytics-card-icon">🌍</div><div class="analytics-card-title">Международный</div><div class="analytics-card-value"><?= $countInter ?></div></div>
  <div class="analytics-card"><div class="analytics-card-icon">🇰🇿</div><div class="analytics-card-title">Республика</div><div class="analytics-card-value"><?= $countNat ?></div></div>
  <div class="analytics-card"><div class="analytics-card-icon">🏙️</div><div class="analytics-card-title">Регион</div><div class="analytics-card-value"><?= $countReg ?></div></div>
  <div class="analytics-card"><div class="analytics-card-icon">🏢</div><div class="analytics-card-title">Город</div><div class="analytics-card-value"><?= $countCity ?></div></div>
  <div class="analytics-card"><div class="analytics-card-icon">🏫</div><div class="analytics-card-title">Колледж</div><div class="analytics-card-value"><?= $countColl ?></div></div>
</div>

<div class="filter-box">
  <form method="GET" action="" class="filter-grid">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
    <div class="filter-item"><label>Поиск</label><input type="text" name="q" class="filter-input" placeholder="Название или ФИО..." value="<?= htmlspecialchars($filterSearch) ?>"></div>
    <div class="filter-item">
      <label>Группа</label>
      <select name="group_id" class="filter-input" <?= $activeTab === 'teachers' ? 'disabled' : '' ?>>
        <option value="0">Все группы</option>
        <?php foreach ($groupsList as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $filterGroup === (int)$g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-item">
      <label>Категория</label>
      <select name="category" class="filter-input">
        <option value="">Все</option>
        <option value="olympiad" <?= $filterCategory === 'olympiad' ? 'selected' : '' ?>>Олимпиада</option>
        <option value="conference" <?= $filterCategory === 'conference' ? 'selected' : '' ?>>Конференция</option>
        <option value="sport" <?= $filterCategory === 'sport' ? 'selected' : '' ?>>Спорт</option>
        <option value="art" <?= $filterCategory === 'art' ? 'selected' : '' ?>>Творчество</option>
        <option value="science" <?= $filterCategory === 'science' ? 'selected' : '' ?>>Наука</option>
        <option value="other" <?= $filterCategory === 'other' ? 'selected' : '' ?>>Другое</option>
      </select>
    </div>
    <div class="filter-item">
      <label>Уровень</label>
      <select name="level" class="filter-input">
        <option value="">Все</option>
        <option value="college" <?= $filterLevel === 'college' ? 'selected' : '' ?>>Колледж</option>
        <option value="city" <?= $filterLevel === 'city' ? 'selected' : '' ?>>Город</option>
        <option value="regional" <?= $filterLevel === 'regional' ? 'selected' : '' ?>>Регион</option>
        <option value="national" <?= $filterLevel === 'national' ? 'selected' : '' ?>>Республика</option>
        <option value="international" <?= $filterLevel === 'international' ? 'selected' : '' ?>>Международный</option>
      </select>
    </div>
    <button type="submit" class="btn-filter-search">Найти</button>
    <a href="index.php?tab=<?= $activeTab ?>" class="btn-filter-clear">Сбросить</a>
  </form>
</div>

<div class="navigation-tabs">
  <a href="index.php?tab=students" class="tab-item <?= $activeTab === 'students' ? 'active' : '' ?>">🎓 Студенты <span class="tab-counter"><?= $countStudentsAch ?></span></a>
  <a href="index.php?tab=teachers" class="tab-item <?= $activeTab === 'teachers' ? 'active' : '' ?>">💼 Преподаватели <span class="tab-counter"><?= $countTeachersAch ?></span></a>
</div>

<div class="table-container-card">
  <div class="table-responsive">
    <table class="main-data-table">
      <thead>
        <tr>
          <th style="width: 50px; text-align: center;">#</th>
          <th><?= $activeTab === 'teachers' ? 'Преподаватель' : 'Студент' ?></th>
          <th>Группа</th>
          <th>Достижение</th>
          <th>Категория</th>
          <th>Уровень</th>
          <th>Место</th>
          <th>Дата</th>
          <th>Добавил</th>
          <th style="width: 80px; text-align: center;">Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($achievements)): ?>
          <tr><td colspan="10" style="text-align:center; padding: 50px; color: var(--text-muted);">Записей не найдено</td></tr>
        <?php else: ?>
          <?php foreach ($achievements as $i => $a): ?>
          <?php
            $categories = ['olympiad'=>'Олимпиада','conference'=>'Конференция','sport'=>'Спорт','art'=>'Творчество','science'=>'Наука','other'=>'Другое'];
            $levels = ['college'=>'Колледж','city'=>'Город','regional'=>'Регион','national'=>'Республика','international'=>'Международный'];
            
            $medalOutput = '—';
            if ($a['place'] == '1') $medalOutput = '<span class="medal-container gold-medal">🥇 1 место</span>';
            elseif ($a['place'] == '2') $medalOutput = '<span class="medal-container silver-medal">🥈 2 место</span>';
            elseif ($a['place'] == '3') $medalOutput = '<span class="medal-container bronze-medal">🥉 3 место</span>';
            elseif (!empty($a['place'])) $medalOutput = '<span class="badge-pill">' . htmlspecialchars($a['place']) . '</span>';
          ?>
          <tr>
            <td style="text-align: center; font-weight: 600; color: var(--text-muted);"><?= $i + 1 ?></td>
            <td><a href="profile.php?id=<?= $a['user_id'] ?>" class="profile-link"><?= htmlspecialchars($a['full_name']) ?></a></td>
            <td><strong><?= htmlspecialchars($a['group_name'] ?? '—') ?></strong></td>
            <td>
              <div class="achievement-name"><?= htmlspecialchars($a['title']) ?></div>
              <?php if (!empty($a['description'])): ?><div class="achievement-details"><?= htmlspecialchars($a['description']) ?></div><?php endif; ?>
            </td>
            <td><span class="badge-pill badge-category"><?= $categories[$a['category']] ?? $a['category'] ?></span></td>
            <td><span class="badge-pill badge-level"><?= $levels[$a['level']] ?? $a['level'] ?></span></td>
            <td><?= $medalOutput ?></td>
            <td><span style="white-space: nowrap;"><?= $a['date_event'] ? date('Y-m-d', strtotime($a['date_event'])) : '—' ?></span></td>
            <td><span style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($a['added_by_name'] ?? '—') ?></span></td>
            <td style="text-align: center;">
              <?php if (in_array($role, ['admin','teacher'])): ?>
                <a href="actions/achievement_delete.php?id=<?= $a['id'] ?>&user_id=<?= $a['user_id'] ?>" class="delete-action-btn" onclick="return confirm('Удалить?')">✕</a>
              <?php else: ?>
                <span style="color: var(--text-muted);">🔒</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>


<div id="modal-ach-student" class="modal-overlay" onclick="closeModalOnOverlay(event, 'modal-ach-student')">
  <div class="modal-window">
    <div class="modal-header">
      <h3>🏆 Добавить достижение студента</h3>
      <button class="modal-close-x" onclick="closeModal('modal-ach-student')">&times;</button>
    </div>
    <form method="POST" action="" class="modal-form">
      <input type="hidden" name="action" value="add_student_achievement">
      
      <div class="filter-item">
        <label>Выберите студента *</label>
        <select name="student_user_id" class="filter-input" required>
          <option value="">-- Выберите из списка --</option>
          <?php foreach ($allStudents as $st): ?>
            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-item">
        <label>Наименование конкурса / олимпиады *</label>
        <input type="text" name="title" class="filter-input" placeholder="Например: WorldSkills 2026" required>
      </div>

      <div class="filter-item">
        <label>Краткое описание (необязательно)</label>
        <input type="text" name="description" class="filter-input" placeholder="Занял призовое место в секции ИТ...">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div class="filter-item">
          <label>Категория *</label>
          <select name="category" class="filter-input" required>
            <option value="olympiad">Олимпиада</option>
            <option value="conference">Конференция</option>
            <option value="sport">Спорт</option>
            <option value="art">Творчество</option>
            <option value="science">Наука</option>
            <option value="other">Другое</option>
          </select>
        </div>
        <div class="filter-item">
          <label>Уровень *</label>
          <select name="level" class="filter-input" required>
            <option value="college">Колледж</option>
            <option value="city">Город</option>
            <option value="regional">Регион</option>
            <option value="national">Республика</option>
            <option value="international">Международный</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div class="filter-item">
          <label>Занятое место (1, 2, 3...)</label>
          <input type="text" name="place" class="filter-input" placeholder="Например: 1">
        </div>
        <div class="filter-item">
          <label>Дата проведения</label>
          <input type="date" name="date_event" class="filter-input">
        </div>
      </div>

      <div class="modal-form-footer">
        <button type="button" class="btn-action btn-action-white" onclick="closeModal('modal-ach-student')">Отмена</button>
        <button type="submit" class="btn-action btn-action-blue">Сохранить</button>
      </div>
    </form>
  </div>
</div>

<div id="modal-ach-teacher" class="modal-overlay" onclick="closeModalOnOverlay(event, 'modal-ach-teacher')">
  <div class="modal-window">
    <div class="modal-header">
      <h3>💼 Добавить достижение преподавателя</h3>
      <button class="modal-close-x" onclick="closeModal('modal-ach-teacher')">&times;</button>
    </div>
    <form method="POST" action="" class="modal-form">
      <input type="hidden" name="action" value="add_teacher_achievement">
      
      <div class="filter-item">
        <label>Выберите преподавателя *</label>
        <select name="teacher_user_id" class="filter-input" required>
          <option value="">-- Выберите из списка --</option>
          <?php foreach ($allTeachers as $tc): ?>
            <option value="<?= $tc['id'] ?>"><?= htmlspecialchars($tc['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-item">
        <label>Наименование публикации / награды *</label>
        <input type="text" name="title" class="filter-input" placeholder="Например: Лучший преподаватель года" required>
      </div>

      <div class="filter-item">
        <label>Описание</label>
        <input type="text" name="description" class="filter-input" placeholder="За разработку учебных пособий...">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div class="filter-item">
          <label>Категория *</label>
          <select name="category" class="filter-input" required>
            <option value="olympiad">Олимпиада</option>
            <option value="conference">Конференция</option>
            <option value="science">Наука / Статья</option>
            <option value="other">Другое</option>
          </select>
        </div>
        <div class="filter-item">
          <label>Уровень *</label>
          <select name="level" class="filter-input" required>
            <option value="college">Колледж</option>
            <option value="city">Город</option>
            <option value="regional">Регион</option>
            <option value="national">Республика</option>
            <option value="international">Международный</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div class="filter-item">
          <label>Результат / Место</label>
          <input type="text" name="place" class="filter-input" placeholder="Например: Диплом I степени">
        </div>
        <div class="filter-item">
          <label>Дата</label>
          <input type="date" name="date_event" class="filter-input">
        </div>
      </div>

      <div class="modal-form-footer">
        <button type="button" class="btn-action btn-action-white" onclick="closeModal('modal-ach-teacher')">Отмена</button>
        <button type="submit" class="btn-action btn-action-blue">Сохранить</button>
      </div>
    </form>
  </div>
</div>


<script>
  // Функция открытия модального окна
  function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden'; // Запрещаем прокрутку фона
    }
  }

  // Функция закрытия модального окна
  function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = ''; // Возвращаем прокрутку
    }
  }

  // Закрытие при клике по затемненной области вокруг окна
  function closeModalOnOverlay(event, id) {
    if (event.target === event.currentTarget) {
      closeModal(id);
    }
  }
</script>

<?php require_once 'includes/footer.php'; ?>
