<?php
// create_request.php - Создание заявки

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireLogin();

$user = getCurrentUser();

// Проверка права на создание заявок
$stmt = $pdo->prepare("SELECT can_create_request FROM roles WHERE role_code = ?");
$stmt->execute([$user['role']]);
$permission = $stmt->fetch();

// Если роль не найдена или нет прав - используем фоллбэк для старых ролей
if (!$permission) {
    // Для старых ролей без записи в таблице roles
    if ($user['role'] !== 'teacher') {
        header('Location: teacher_dashboard.php');
        exit();
    }
} else {
    // Для новых ролей проверяем права
    if (!$permission['can_create_request']) {
        header('Location: teacher_dashboard.php');
        exit();
    }
}

// Обработка смены языка
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: create_request.php');
    exit();
}

$success = '';
$error = '';

// Обработка создания заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestType = $_POST['request_type'];
    $fullName = $_POST['full_name'];
    $position = $_POST['position'];
    $cabinet = $_POST['cabinet'];
    $priority = $_POST['priority'] ?? 'normal'; // Получаем приоритет
    
    try {
        if ($requestType === 'repair') {
            // Заявка на ремонт
            $equipmentType = $_POST['equipment_type'];
            $inventoryNumber = $_POST['inventory_number'];
            $description = $_POST['description'];
            
            $stmt = $pdo->prepare("INSERT INTO requests (request_type, created_by, full_name, position, cabinet, equipment_type, inventory_number, description, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestType, $user['id'], $fullName, $position, $cabinet, $equipmentType, $inventoryNumber, $description, $priority]);
            
        } elseif ($requestType === 'software') {
            // Заявка на установку ПО
            $computerInventory = $_POST['computer_inventory'];
            $softwareList = $_POST['software_list'];
            $justification = $_POST['justification'];
            
            $stmt = $pdo->prepare("INSERT INTO requests (request_type, created_by, full_name, position, cabinet, computer_inventory, software_list, justification, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestType, $user['id'], $fullName, $position, $cabinet, $computerInventory, $softwareList, $justification, $priority]);
            
        } elseif ($requestType === '1c_database') {
            // Заявка на создание БД 1С
            $groupNumber = $_POST['group_number'];
            $databasePurpose = $_POST['database_purpose'];
            
            // Сбор списка студентов
            $students = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'student_') === 0 && !empty($value)) {
                    $students[] = $value;
                }
            }
            $studentsList = json_encode($students, JSON_UNESCAPED_UNICODE);
            
            $stmt = $pdo->prepare("INSERT INTO requests (request_type, created_by, full_name, position, cabinet, group_number, database_purpose, students_list, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestType, $user['id'], $fullName, $position, $cabinet, $groupNumber, $databasePurpose, $studentsList, $priority]);
            
        } elseif ($requestType === 'general_question') {
            // Заявка на общие вопросы/консультацию
            $questionDescription = $_POST['question_description'];
            $softwareOrSystem = $_POST['software_or_system'] ?? '';
            
            $stmt = $pdo->prepare("INSERT INTO requests (request_type, created_by, full_name, position, cabinet, question_description, software_or_system, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestType, $user['id'], $fullName, $position, $cabinet, $questionDescription, $softwareOrSystem, $priority]);
        }
        
        $success = 'Заявка успешно отправлена!';
        
        // Редирект на страницу преподавателя
        header('Location: teacher_dashboard.php?tab=active&created=1');
        exit();
        
    } catch (PDOException $e) {
        $error = 'Ошибка при создании заявки: ' . $e->getMessage();
    }
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo t('create_request'); ?> — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
/* ============================================================
   СВГТК Портал — Стили дашборда
   Цветовая схема: синий + белый (как svgtk.kz)
   ============================================================ */

@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap');

:root, [data-theme="light"] {
  --color-bg:              #f0f4f8;
  --color-surface:         #ffffff;
  --color-surface-2:       #f8fafc;
  --color-surface-offset:  #eef2f7;
  --color-divider:         #e2e8f0;
  --color-border:          #cbd5e1;
  --color-text:            #1e293b;
  --color-text-muted:      #64748b;
  --color-text-faint:      #94a3b8;
  --color-text-inverse:    #ffffff;

  --color-primary:           #1a56db;
  --color-primary-hover:     #1346c2;
  --color-primary-active:    #0c38a8;
  --color-primary-highlight: #dbeafe;

  --color-success:           #16a34a;
  --color-success-highlight: #dcfce7;
  --color-warning:           #d97706;
  --color-warning-highlight: #fef3c7;
  --color-error:             #dc2626;
  --color-error-highlight:   #fee2e2;
  --color-gold:              #ca8a04;
  --color-gold-highlight:    #fef9c3;

  --radius-sm:   0.375rem;
  --radius-md:   0.5rem;
  --radius-lg:   0.75rem;
  --radius-xl:   1rem;
  --radius-full: 9999px;

  --transition: 180ms cubic-bezier(0.16, 1, 0.3, 1);

  --shadow-sm: 0 1px 3px rgba(30,41,59,.08);
  --shadow-md: 0 4px 12px rgba(30,41,59,.10);
  --shadow-lg: 0 12px 32px rgba(30,41,59,.13);

  --font-body:    'Inter', -apple-system, sans-serif;
  --font-display: 'Montserrat', 'Inter', sans-serif;

  --text-xs:   clamp(0.75rem,  0.7rem  + 0.25vw, 0.875rem);
  --text-sm:   clamp(0.875rem, 0.8rem  + 0.35vw, 1rem);
  --text-base: clamp(1rem,     0.95rem + 0.25vw, 1.125rem);
  --text-lg:   clamp(1.125rem, 1rem    + 0.75vw, 1.5rem);
  --text-xl:   clamp(1.5rem,   1.2rem  + 1.25vw, 2rem);

  --space-1:  0.25rem;
  --space-2:  0.5rem;
  --space-3:  0.75rem;
  --space-4:  1rem;
  --space-5:  1.25rem;
  --space-6:  1.5rem;
  --space-8:  2rem;
  --space-10: 2.5rem;
  --space-12: 3rem;
  --space-16: 4rem;

  --sidebar-width: 240px;
  --topbar-height: 56px;
}

[data-theme="dark"] {
  --color-bg:              #0f172a;
  --color-surface:         #1e293b;
  --color-surface-2:       #263449;
  --color-surface-offset:  #1a2740;
  --color-divider:         #2d3f57;
  --color-border:          #374f6b;
  --color-text:            #e2e8f0;
  --color-text-muted:      #94a3b8;
  --color-text-faint:      #64748b;
  --color-text-inverse:    #0f172a;

  --color-primary:           #3b82f6;
  --color-primary-hover:     #60a5fa;
  --color-primary-active:    #93c5fd;
  --color-primary-highlight: #1e3a5f;

  --color-success:           #22c55e;
  --color-success-highlight: #14532d;
  --color-warning:           #f59e0b;
  --color-warning-highlight: #451a03;
  --color-error:             #ef4444;
  --color-error-highlight:   #450a0a;
  --color-gold:              #eab308;
  --color-gold-highlight:    #422006;

  --shadow-sm: 0 1px 3px rgba(0,0,0,.25);
  --shadow-md: 0 4px 12px rgba(0,0,0,.35);
  --shadow-lg: 0 12px 32px rgba(0,0,0,.45);
}

/* ── Reset ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html {
  scroll-behavior: smooth;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
}

body {
  font-family: var(--font-body);
  font-size: var(--text-sm);
  color: var(--color-text);
  background: var(--color-bg);
  min-height: 100dvh;
  display: flex;
  line-height: 1.6;
}

img, svg { display: block; max-width: 100%; }
a { color: inherit; text-decoration: none; }
button { cursor: pointer; background: none; border: none; font: inherit; color: inherit; }
table { border-collapse: collapse; width: 100%; }
input, select, textarea { font: inherit; color: inherit; }
h1, h2, h3, h4 { text-wrap: balance; line-height: 1.2; }

:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}

/* ── Sidebar ───────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-width);
  min-height: 100dvh;
  background: var(--color-surface);
  border-right: 1px solid var(--color-divider);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
  transition: width var(--transition), transform var(--transition);
  overflow: hidden;
}

.sidebar.collapsed { width: 60px; }
.sidebar.collapsed .logo-text,
.sidebar.collapsed .nav-section-label,
.sidebar.collapsed .nav-item span,
.sidebar.collapsed .sidebar-footer { opacity: 0; pointer-events: none; }
.sidebar.collapsed .nav-item { justify-content: center; padding-inline: 0; }
.sidebar.collapsed .sidebar-toggle { transform: rotate(180deg); }

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-4);
  border-bottom: 1px solid var(--color-divider);
  min-height: var(--topbar-height);
  gap: var(--space-2);
}

.logo { display: flex; align-items: center; gap: var(--space-3); min-width: 0; }
.logo-text { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.logo-title {
  font-family: var(--font-display);
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.logo-sub { font-size: var(--text-xs); color: var(--color-text-muted); white-space: nowrap; }

.sidebar-toggle {
  width: 28px; height: 28px; flex-shrink: 0;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  color: var(--color-text-muted);
  transition: background var(--transition), transform var(--transition), color var(--transition);
}
.sidebar-toggle:hover { background: var(--color-surface-offset); color: var(--color-text); }

.sidebar-nav {
  flex: 1;
  padding: var(--space-3) var(--space-3);
  overflow-y: auto;
  overflow-x: hidden;
}

.nav-section-label {
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-faint);
  padding: var(--space-4) var(--space-3) var(--space-2);
  white-space: nowrap;
  overflow: hidden;
  transition: opacity var(--transition);
}

.nav-item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  transition: background var(--transition), color var(--transition);
  white-space: nowrap;
  margin-bottom: var(--space-1);
  overflow: hidden;
}
.nav-item:hover { background: var(--color-surface-offset); color: var(--color-text); }
.nav-item.active {
  background: var(--color-primary-highlight);
  color: var(--color-primary);
  font-weight: 600;
}
.nav-item svg { flex-shrink: 0; }

.sidebar-footer {
  padding: var(--space-4);
  border-top: 1px solid var(--color-divider);
  transition: opacity var(--transition);
  overflow: hidden;
}
.college-info { display: flex; flex-direction: column; gap: 2px; }
.college-info span { font-size: var(--text-xs); color: var(--color-text-faint); white-space: nowrap; }

/* ── Main wrapper ──────────────────────────────────── */
.main-wrapper {
  margin-left: var(--sidebar-width);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
  min-width: 0;
  transition: margin-left var(--transition);
}
.main-wrapper.sidebar-collapsed { margin-left: 60px; }

/* ── Topbar ────────────────────────────────────────── */
.topbar {
  height: var(--topbar-height);
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-divider);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--space-6);
  position: sticky;
  top: 0;
  z-index: 50;
  gap: var(--space-4);
}

.topbar-left { display: flex; align-items: center; gap: var(--space-4); min-width: 0; }

.mobile-menu-btn {
  display: none;
  width: 36px; height: 36px;
  align-items: center; justify-content: center;
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  flex-shrink: 0;
  transition: background var(--transition);
}
.mobile-menu-btn:hover { background: var(--color-surface-offset); }

.breadcrumb {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
  min-width: 0;
  overflow: hidden;
}
.breadcrumb-root { color: var(--color-text-muted); white-space: nowrap; }
.breadcrumb svg { color: var(--color-text-faint); flex-shrink: 0; }
.breadcrumb-current {
  color: var(--color-text);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.topbar-right { display: flex; align-items: center; gap: var(--space-3); flex-shrink: 0; }

.academic-year-badge {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  background: var(--color-surface-offset);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
  border: 1px solid var(--color-border);
  white-space: nowrap;
}

.theme-toggle {
  width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center;
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  transition: background var(--transition), color var(--transition);
  flex-shrink: 0;
}
.theme-toggle:hover { background: var(--color-surface-offset); color: var(--color-text); }

.user-avatar {
  width: 32px; height: 32px;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-text-inverse);
  display: flex; align-items: center; justify-content: center;
  font-size: var(--text-xs);
  font-weight: 700;
  flex-shrink: 0;
}

/* ── Page content ──────────────────────────────────── */
.page-content { flex: 1; padding: var(--space-6); min-width: 0; }

.page-header {
  margin-bottom: var(--space-6);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-4);
}

.page-title {
  font-family: var(--font-display);
  font-size: var(--text-xl);
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.2;
}
.page-subtitle {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin-top: var(--space-1);
}
.page-actions { display: flex; gap: var(--space-3); flex-wrap: wrap; align-items: center; }

/* ── Buttons ───────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: 500;
  border: 1px solid transparent;
  white-space: nowrap;
  transition: background var(--transition), color var(--transition),
              border-color var(--transition), box-shadow var(--transition);
}
.btn-primary {
  background: var(--color-primary);
  color: var(--color-text-inverse);
  border-color: var(--color-primary);
}
.btn-primary:hover {
  background: var(--color-primary-hover);
  border-color: var(--color-primary-hover);
  box-shadow: var(--shadow-sm);
}
.btn-outline {
  background: transparent;
  color: var(--color-text);
  border-color: var(--color-border);
}
.btn-outline:hover {
  background: var(--color-surface-offset);
  border-color: var(--color-text-muted);
}
.btn-sm { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); }

/* ── KPI Cards ─────────────────────────────────────── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(220px, 100%), 1fr));
  gap: var(--space-4);
  margin-bottom: var(--space-6);
}

.kpi-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  padding: var(--space-5);
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--transition), transform var(--transition);
}
.kpi-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }

.kpi-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}
.kpi-label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.kpi-icon {
  width: 36px; height: 36px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}
.kpi-icon.blue  { background: var(--color-primary-highlight); color: var(--color-primary); }
.kpi-icon.green { background: var(--color-success-highlight); color: var(--color-success); }
.kpi-icon.amber { background: var(--color-warning-highlight); color: var(--color-warning); }
.kpi-icon.gold  { background: var(--color-gold-highlight);    color: var(--color-gold); }

.kpi-value {
  font-family: var(--font-display);
  font-size: var(--text-xl);
  font-weight: 700;
  line-height: 1;
  color: var(--color-text);
  font-variant-numeric: tabular-nums;
}
.kpi-change {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  margin-top: var(--space-1);
}
.kpi-change.up   { color: var(--color-success); }
.kpi-change.down { color: var(--color-error); }

/* ── Cards ─────────────────────────────────────────── */
.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.card-header {
  padding: var(--space-4) var(--space-6);
  border-bottom: 1px solid var(--color-divider);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-3);
}
.card-title { font-size: var(--text-base); font-weight: 600; color: var(--color-text); }
.card-body  { padding: var(--space-6); }
.card-body-flush { padding: 0; }

/* ── Charts grid ────────────────────────────────────── */
.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(380px, 100%), 1fr));
  gap: var(--space-6);
  margin-bottom: var(--space-6);
}
.chart-container { position: relative; width: 100%; }
.chart-container canvas { max-height: 280px; }

/* ── Table ──────────────────────────────────────────── */
.table-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.data-table { width: 100%; min-width: 600px; }

.data-table th {
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-muted);
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-2);
  border-bottom: 1px solid var(--color-divider);
  white-space: nowrap;
  text-align: left;
}
.data-table td {
  padding: var(--space-3) var(--space-4);
  border-bottom: 1px solid var(--color-divider);
  font-size: var(--text-sm);
  color: var(--color-text);
  font-variant-numeric: tabular-nums;
  vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: var(--color-surface-offset); }

/* ── Badges ─────────────────────────────────────────── */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: 500;
  white-space: nowrap;
}
.badge-blue  { background: var(--color-primary-highlight); color: var(--color-primary); }
.badge-green { background: var(--color-success-highlight); color: var(--color-success); }
.badge-amber { background: var(--color-warning-highlight); color: var(--color-warning); }
.badge-red   { background: var(--color-error-highlight);   color: var(--color-error); }
.badge-gold  { background: var(--color-gold-highlight);    color: var(--color-gold); }
.badge-gray  { background: var(--color-surface-offset);    color: var(--color-text-muted); }

/* Оценки */
.grade-5 { background: #dcfce7; color: #15803d; }
.grade-4 { background: #dbeafe; color: #1d4ed8; }
.grade-3 { background: #fef9c3; color: #a16207; }
.grade-2 { background: #fee2e2; color: #dc2626; }

/* ── Progress bar ───────────────────────────────────── */
.progress-bar-wrap {
  background: var(--color-surface-offset);
  border-radius: var(--radius-full);
  height: 6px;
  overflow: hidden;
}
.progress-bar {
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  transition: width 0.6s ease;
}
.progress-bar.green { background: var(--color-success); }
.progress-bar.amber { background: var(--color-warning); }
.progress-bar.red   { background: var(--color-error); }

/* ── Filters / Forms ────────────────────────────────── */
.filters-bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  margin-bottom: var(--space-5);
  align-items: flex-end;
}
.form-group { display: flex; flex-direction: column; gap: var(--space-1); }
.form-label { font-size: var(--text-xs); font-weight: 500; color: var(--color-text-muted); }
.form-control {
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
  min-width: 160px;
  transition: border-color var(--transition), box-shadow var(--transition);
}
.form-control:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px color-mix(in oklab, var(--color-primary) 15%, transparent);
}

/* ── Summary strip ──────────────────────────────────── */
.summary-strip {
  display: flex;
  gap: var(--space-6);
  flex-wrap: wrap;
  padding: var(--space-4) var(--space-6);
  background: var(--color-surface-2);
  border-bottom: 1px solid var(--color-divider);
}
.summary-item { display: flex; flex-direction: column; gap: 2px; }
.summary-value {
  font-weight: 700;
  font-size: var(--text-base);
  font-variant-numeric: tabular-nums;
}
.summary-label { font-size: var(--text-xs); color: var(--color-text-muted); }

/* ── Empty cell ─────────────────────────────────────── */
.empty-cell {
  text-align: center !important;
  padding: var(--space-8) var(--space-4) !important;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

/* ── Page footer ────────────────────────────────────── */
.page-footer {
  padding: var(--space-4) var(--space-6);
  border-top: 1px solid var(--color-divider);
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-faint);
  background: var(--color-surface);
}

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.mobile-open { transform: translateX(0); box-shadow: var(--shadow-lg); }
  .main-wrapper { margin-left: 0 !important; }
  .mobile-menu-btn { display: flex; }
  .topbar { padding: 0 var(--space-4); }
  .page-content { padding: var(--space-4); }
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .charts-grid { grid-template-columns: 1fr; }
  .academic-year-badge { display: none; }
  .summary-strip { gap: var(--space-4); padding: var(--space-4); }
}

@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr; }
  .page-header { flex-direction: column; }
  .filters-bar { flex-direction: column; align-items: stretch; }
  .form-control { min-width: unset; width: 100%; }
}

@media print {
  .sidebar, .topbar, .page-footer,
  .page-actions, .filters-bar, .theme-toggle { display: none !important; }
  .main-wrapper { margin-left: 0 !important; }
  .page-content { padding: 0; }
  .card { box-shadow: none !important; border: 1px solid #ccc !important; border-radius: 0 !important; margin-bottom: 16px; }
  .kpi-grid { grid-template-columns: repeat(4, 1fr); }
  .charts-grid { grid-template-columns: repeat(2, 1fr); }
  body { background: white; }
}

/* Форма */
.form-card{background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-xl);padding:2rem;box-shadow:var(--shadow-sm)}
.form-label{display:block;font-size:.875rem;font-weight:500;color:var(--color-text-muted);margin-bottom:.5rem}
.form-input{width:100%;border:1.5px solid var(--color-border);border-radius:var(--radius-md);padding:.625rem .875rem;font:inherit;font-size:.9375rem;color:var(--color-text);background:var(--color-surface);transition:border-color var(--transition)}
.form-input:focus{outline:none;border-color:var(--color-primary);box-shadow:0 0 0 3px rgba(26,86,219,.1)}
.form-section{margin-bottom:1.25rem}
.hidden{display:none}
/* Карточки типа */
.type-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;margin-bottom:1.5rem}
.type-card{padding:1rem;border:2px solid var(--color-border);border-radius:var(--radius-lg);text-align:center;cursor:pointer;transition:all var(--transition)}
.type-card:hover{border-color:var(--color-primary);background:var(--color-primary-highlight)}
.type-card.active-repair{border-color:#dc2626;background:#fee2e2}
.type-card.active-software{border-color:#2563eb;background:#dbeafe}
.type-card.active-1c{border-color:#7c3aed;background:#ede9fe}
.type-card.active-question{border-color:#16a34a;background:#dcfce7}
.type-card i{font-size:1.75rem;margin-bottom:.5rem;display:block}
.type-card .type-name{font-weight:600;font-size:.9375rem;color:var(--color-text)}
/* Приоритет */
.priority-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem}
.priority-card{padding:.75rem;border:2px solid var(--color-border);border-radius:var(--radius-lg);text-align:center;cursor:pointer;transition:all var(--transition)}
.priority-card:hover{filter:brightness(.97)}
.priority-card i{font-size:1.5rem;margin-bottom:.25rem;display:block}
.priority-card .p-name{font-weight:700;font-size:.875rem}
.priority-card .p-sub{font-size:.75rem;margin-top:.2rem}
.priority-card.active-low{border-color:#6b7280;background:#f3f4f6}
.priority-card.active-normal{border-color:#2563eb;background:#dbeafe}
.priority-card.active-high{border-color:#d97706;background:#fef3c7}
.priority-card.active-urgent{border-color:#dc2626;background:#fee2e2}
  </style>
</head>
<body>

<!-- SIDEBAR -->
<?php
$activePage = 'create';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- MAIN -->
<div class="main-wrapper" id="mainWrapper">
  <header class="topbar">
    <div class="topbar-left">
      <div class="breadcrumb">
        <span class="breadcrumb-root"><a href="teacher_dashboard.php" style="color:inherit">Заявки в ИТ</a></span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="breadcrumb-current">Создать заявку</span>
      </div>
    </div>
    <div class="topbar-right">
      <a href="?lang=ru" class="btn btn-sm <?= $currentLang==='ru'?'btn-primary':'btn-outline' ?>">Рус</a>
      <a href="?lang=kk" class="btn btn-sm <?= $currentLang==='kk'?'btn-primary':'btn-outline' ?>">Қаз</a>
      <button class="theme-toggle" id="themeToggle">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <div class="user-avatar" title="<?= htmlspecialchars($user['full_name']) ?>">
        <?php
          $parts = explode(' ', trim($user['full_name']));
          echo implode('', array_map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)), array_slice($parts,0,2)));
        ?>
      </div>
      <span style="width:1px;height:20px;background:var(--color-divider)"></span>
      <a href="logout.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Выход
      </a>
    </div>
  </header>

  <main class="page-content">
    <div style="margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between">
      <div>
        <h1 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--color-text)"><?php echo t('new_request'); ?></h1>
        <p style="font-size:.9375rem;color:var(--color-text-muted);margin-top:.25rem">Заполните форму — системотехник получит уведомление</p>
      </div>
      <a href="teacher_dashboard.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Назад
      </a>
    </div>

    <?php if ($success): ?>
    <div class="card" style="margin-bottom:1rem;background:var(--color-success-highlight);border-color:#a7f3d0">
      <div class="card-body" style="padding:.75rem 1.5rem;color:var(--color-success)"><?= htmlspecialchars($success) ?></div>
    </div>
    <?php endif ?>
    <?php if ($error): ?>
    <div class="card" style="margin-bottom:1rem;background:var(--color-error-highlight);border-color:#fca5a5">
      <div class="card-body" style="padding:.75rem 1.5rem;color:var(--color-error)"><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif ?>

    <div class="form-card">
      <form method="POST" id="requestForm">

        <!-- Тип заявки -->
        <div style="margin-bottom:1.5rem">
          <label class="form-label"><?php echo t('request_type'); ?></label>
          <div class="type-grid">
            <label>
              <input type="radio" name="request_type" value="repair" class="hidden request-type-radio" checked>
              <div class="type-card active-repair" data-type="repair">
                <i class="fas fa-wrench" style="color:#dc2626"></i>
                <div class="type-name"><?php echo t('repair'); ?></div>
              </div>
            </label>
            <label>
              <input type="radio" name="request_type" value="software" class="hidden request-type-radio">
              <div class="type-card" data-type="software">
                <i class="fas fa-laptop-code" style="color:#2563eb"></i>
                <div class="type-name"><?php echo t('software'); ?></div>
              </div>
            </label>
            <label>
              <input type="radio" name="request_type" value="1c_database" class="hidden request-type-radio">
              <div class="type-card" data-type="1c">
                <i class="fas fa-database" style="color:#7c3aed"></i>
                <div class="type-name"><?php echo t('1c_database'); ?></div>
              </div>
            </label>
            <label>
              <input type="radio" name="request_type" value="general_question" class="hidden request-type-radio">
              <div class="type-card" data-type="question">
                <i class="fas fa-question-circle" style="color:#16a34a"></i>
                <div class="type-name"><?php echo t('general_question'); ?></div>
              </div>
            </label>
          </div>
        </div>

        <!-- Общие поля -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div>
            <label class="form-label"><?php echo t('full_name'); ?></label>
            <input type="text" name="full_name" required value="<?= htmlspecialchars($user['full_name']) ?>" class="form-input">
          </div>
          <div>
            <label class="form-label"><?php echo t('position'); ?></label>
            <input type="text" name="position" required value="<?= htmlspecialchars($user['position']) ?>" class="form-input">
          </div>
        </div>
        <div style="margin-bottom:1.5rem">
          <label class="form-label"><?php echo t('cabinet'); ?></label>
          <input type="text" name="cabinet" required class="form-input" placeholder="Пример: 403">
        </div>

        <!-- Приоритет -->
        <div style="margin-bottom:1.5rem;padding:1.25rem;background:var(--color-surface-2);border-radius:var(--radius-lg);border:1px solid var(--color-border)">
          <label class="form-label" style="margin-bottom:.75rem">
            <i class="fas fa-flag" style="margin-right:.5rem;color:var(--color-primary)"></i>
            Приоритет заявки
          </label>
          <div class="priority-grid">
            <label>
              <input type="radio" name="priority" value="low" class="hidden priority-radio">
              <div class="priority-card" data-priority="low">
                <i class="fas fa-angle-double-down" style="color:#6b7280"></i>
                <div class="p-name" style="color:#374151">Низкий</div>
                <div class="p-sub" style="color:#6b7280">Не срочно</div>
              </div>
            </label>
            <label>
              <input type="radio" name="priority" value="normal" class="hidden priority-radio" checked>
              <div class="priority-card active-normal" data-priority="normal">
                <i class="fas fa-minus" style="color:#2563eb"></i>
                <div class="p-name" style="color:#1d4ed8">Обычный</div>
                <div class="p-sub" style="color:#3b82f6">Стандартно</div>
              </div>
            </label>
            <label>
              <input type="radio" name="priority" value="high" class="hidden priority-radio">
              <div class="priority-card" data-priority="high">
                <i class="fas fa-angle-double-up" style="color:#d97706"></i>
                <div class="p-name" style="color:#92400e">Высокий</div>
                <div class="p-sub" style="color:#d97706">Важно</div>
              </div>
            </label>
            <label>
              <input type="radio" name="priority" value="urgent" class="hidden priority-radio">
              <div class="priority-card" data-priority="urgent">
                <i class="fas fa-exclamation-triangle" style="color:#dc2626"></i>
                <div class="p-name" style="color:#991b1b">Срочный</div>
                <div class="p-sub" style="color:#dc2626">Очень важно!</div>
              </div>
            </label>
          </div>
          <p style="font-size:.75rem;color:var(--color-text-muted);margin-top:.75rem;padding:.5rem .75rem;background:var(--color-surface);border-radius:var(--radius-md);border:1px solid var(--color-border)">
            <i class="fas fa-info-circle" style="color:var(--color-primary);margin-right:.25rem"></i>
            Системотехники обрабатывают заявки в порядке приоритета
          </p>
        </div>

        <!-- Поля ремонта -->
        <div id="repairFields" class="form-section">
          <div style="margin-bottom:1rem">
            <label class="form-label"><?php echo t('equipment_type'); ?></label>
            <select name="equipment_type" class="form-input">
              <option>Системный блок</option><option>Монитор</option>
              <option>Принтер</option><option>Проектор</option>
              <option>Сканер</option><option>Другое</option>
            </select>
          </div>
          <div style="margin-bottom:1rem">
            <label class="form-label"><?php echo t('inventory_number'); ?> (при наличии)</label>
            <input type="text" name="inventory_number" class="form-input" placeholder="СБ-2024-001">
          </div>
          <div>
            <label class="form-label"><?php echo t('description'); ?></label>
            <textarea name="description" rows="4" class="form-input" placeholder="Опишите проблему: не включается, не печатает, шумит вентилятор и т.п."></textarea>
          </div>
        </div>

        <!-- Поля ПО -->
        <div id="softwareFields" class="form-section hidden">
          <div style="margin-bottom:1rem">
            <label class="form-label"><?php echo t('inventory_number'); ?> компьютера</label>
            <input type="text" name="computer_inventory" class="form-input" placeholder="ПК-2024-001">
          </div>
          <div style="margin-bottom:1rem">
            <label class="form-label"><?php echo t('software_list'); ?></label>
            <textarea name="software_list" rows="3" class="form-input" placeholder="AutoCAD 2024&#10;Adobe Photoshop CC"></textarea>
          </div>
          <div>
            <label class="form-label"><?php echo t('justification'); ?></label>
            <textarea name="justification" rows="2" class="form-input" placeholder="Производственная необходимость, учебный процесс и т.д."></textarea>
          </div>
        </div>

        <!-- Поля 1С -->
        <div id="databaseFields" class="form-section hidden">
          <div style="margin-bottom:1rem">
            <label class="form-label"><?php echo t('group_number'); ?></label>
            <input type="text" name="group_number" class="form-input" placeholder="ИС-21">
          </div>
          <div style="margin-bottom:1rem">
            <label class="form-label"><?php echo t('database_purpose'); ?></label>
            <textarea name="database_purpose" rows="2" class="form-input" placeholder="Организация учебного процесса, лабораторные работы"></textarea>
          </div>
          <div>
            <label class="form-label"><?php echo t('students_list'); ?> (количество: <span id="studentCount">4</span>)</label>
            <input type="range" id="studentCountSlider" min="1" max="30" value="4" style="width:100%;margin-bottom:.75rem;accent-color:var(--color-primary)">
            <div id="studentsList"></div>
          </div>
        </div>

        <!-- Поля вопроса -->
        <div id="generalQuestionFields" class="form-section hidden">
          <div style="margin-bottom:1rem">
            <label class="form-label"><?php echo t('software_or_system'); ?></label>
            <input type="text" name="software_or_system" class="form-input" placeholder="Microsoft Word, 1С:Предприятие, Windows 10">
          </div>
          <div>
            <label class="form-label"><?php echo t('question_description'); ?> <span style="color:var(--color-error)">*</span></label>
            <textarea name="question_description" rows="5" class="form-input" placeholder="Опишите подробно ваш вопрос или проблему"></textarea>
          </div>
        </div>

        <!-- Кнопки -->
        <div style="display:flex;gap:.75rem;padding-top:1.5rem;border-top:1px solid var(--color-divider);margin-top:1.5rem">
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            <?php echo t('send'); ?>
          </button>
          <a href="teacher_dashboard.php" class="btn btn-outline"><?php echo t('cancel'); ?></a>
        </div>

      </form>
    </div>
  </main>
</div>

<script>
// Sidebar
const sidebar=document.getElementById('sidebar');
const mainWrapper=document.getElementById('mainWrapper');
document.getElementById('sidebarToggle').addEventListener('click',()=>{sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('sidebar-collapsed');});
const html=document.documentElement;
html.setAttribute('data-theme',localStorage.getItem('theme')||'light');
document.getElementById('themeToggle').addEventListener('click',()=>{const next=html.getAttribute('data-theme')==='dark'?'light':'dark';html.setAttribute('data-theme',next);localStorage.setItem('theme',next);});

// Тип заявки
const typeColors = {repair:'active-repair',software:'active-software','1c':'active-1c',question:'active-question'};
document.querySelectorAll('.request-type-radio').forEach(radio => {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.type-card').forEach(c => c.className='type-card');
    const card = this.parentElement.querySelector('.type-card');
    const t = card.dataset.type;
    card.classList.add(typeColors[t]||'');
    document.querySelectorAll('.form-section').forEach(s=>s.classList.add('hidden'));
    const map = {repair:'repairFields',software:'softwareFields','1c_database':'databaseFields',general_question:'generalQuestionFields'};
    document.getElementById(map[this.value])?.classList.remove('hidden');
  });
});

// Приоритет
const priColors = {low:'active-low',normal:'active-normal',high:'active-high',urgent:'active-urgent'};
document.querySelectorAll('.priority-radio').forEach(radio => {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.priority-card').forEach(c=>{c.className='priority-card';});
    const card = this.parentElement.querySelector('.priority-card');
    card.classList.add(priColors[this.value]||'');
  });
});

// Студенты
const slider=document.getElementById('studentCountSlider');
const countDisplay=document.getElementById('studentCount');
const studentsList=document.getElementById('studentsList');
function updateStudents(){
  const count=slider.value;
  countDisplay.textContent=count;
  studentsList.innerHTML='';
  for(let i=1;i<=count;i++){
    const inp=document.createElement('input');
    inp.type='text';inp.name='student_'+i;
    inp.className='form-input';inp.style.marginBottom='.5rem';
    inp.placeholder=i+'. ФИО студента';
    studentsList.appendChild(inp);
  }
}
if(slider){slider.addEventListener('input',updateStudents);updateStudents();}
</script>
</body>
</html>
