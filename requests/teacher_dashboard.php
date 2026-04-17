<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

requireRole('teacher');
$user = getCurrentUser();

if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: teacher_dashboard.php?tab=' . ($_GET['tab'] ?? 'active'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action    = $_POST['action'];
    $requestId = (int)($_POST['request_id'] ?? 0);
    $tab       = $_POST['tab'] ?? 'active';
    if ($action === 'confirm_work') {
        $comment = $_POST['comment'] ?? '';
        $pdo->prepare("UPDATE requests SET status='completed', confirmed_at=NOW(), confirmed_by=?, completed_at=NOW() WHERE id=?")->execute([$user['id'], $requestId]);
        if ($comment) $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?,?,?)")->execute([$requestId, $user['id'], 'Отзыв: '.$comment]);
        $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?,?,'confirmed','awaiting_approval','completed',?)")->execute([$requestId, $user['id'], $comment ?: 'Принято']);
    } elseif ($action === 'reject_work') {
        $reason = $_POST['reason'] ?? 'Требуется доработка';
        $pdo->prepare("UPDATE requests SET status='in_progress', teacher_feedback=? WHERE id=?")->execute([$reason, $requestId]);
        $pdo->prepare("INSERT INTO comments (request_id, user_id, comment) VALUES (?,?,?)")->execute([$requestId, $user['id'], 'Возврат: '.$reason]);
        $pdo->prepare("INSERT INTO request_logs (request_id, user_id, action, old_status, new_status, comment) VALUES (?,?,'returned','awaiting_approval','in_progress',?)")->execute([$requestId, $user['id'], $reason]);
    }
    header('Location: teacher_dashboard.php?tab='.$tab); exit();
}

$tab = $_GET['tab'] ?? 'active';
$requests = [];
if ($tab === 'pending') {
    $s = $pdo->prepare("SELECT * FROM requests WHERE created_by=? AND status IN ('pending','approved') ORDER BY created_at DESC");
    $s->execute([$user['id']]); $requests = $s->fetchAll();
} elseif ($tab === 'waiting') {
    $s = $pdo->prepare("SELECT r.*, u.full_name as tech_name FROM requests r LEFT JOIN users u ON r.assigned_to=u.id WHERE r.created_by=? AND r.status='awaiting_approval' AND r.sent_to_director=0 ORDER BY r.approval_requested_at DESC");
    $s->execute([$user['id']]); $requests = $s->fetchAll();
} elseif ($tab === 'archive') {
    $s = $pdo->prepare("SELECT r.*, u.full_name as tech_name FROM requests r LEFT JOIN users u ON r.assigned_to=u.id WHERE r.created_by=? AND r.status='completed' ORDER BY r.confirmed_at DESC");
    $s->execute([$user['id']]); $requests = $s->fetchAll();
} else {
    $s = $pdo->prepare("SELECT r.*, u.full_name as tech_name FROM requests r LEFT JOIN users u ON r.assigned_to=u.id WHERE r.created_by=? AND r.status='in_progress' ORDER BY r.created_at DESC");
    $s->execute([$user['id']]); $requests = $s->fetchAll();
}
$s=$pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by=? AND status='in_progress'"); $s->execute([$user['id']]); $activeCount=$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by=? AND status IN ('pending','approved')"); $s->execute([$user['id']]); $pendingCount=$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by=? AND status='awaiting_approval' AND sent_to_director=0"); $s->execute([$user['id']]); $waitingCount=$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM requests WHERE created_by=? AND status='completed'"); $s->execute([$user['id']]); $archiveCount=$s->fetchColumn();
$totalCount = $activeCount+$pendingCount+$waitingCount+$archiveCount;
$currentLang = getCurrentLanguage();
$nameParts = explode(' ', trim($user['full_name']));
$initials  = implode('', array_map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)), array_slice($nameParts,0,2)));
$page_title = 'Заявки в ИТ';

function statusBadge($s){
    $m=['pending'=>['Ожидает одобрения','badge-amber'],'approved'=>['Одобрена','badge-blue'],
        'in_progress'=>['В работе','badge-blue'],'awaiting_approval'=>['Ожидает подтверждения','badge-gray'],
        'waiting_confirmation'=>['Ожидает подтверждения','badge-gray'],
        'completed'=>['Завершена','badge-green'],'rejected'=>['Отклонена','badge-red']];
    $d=$m[$s]??[$s,'badge-gray'];
    return '<span class="badge '.$d[1].'">'.htmlspecialchars($d[0]).'</span>';
}
function typeLabel($t){return ['repair'=>'Ремонт и обслуживание','software'=>'Установка ПО','1c_database'=>'База данных 1С','general_question'=>'Вопрос / Консультация'][$t]??$t;}
function typeIconPath($t){return ['repair'=>'<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>','software'=>'<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>','1c_database'=>'<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>','general_question'=>'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'][$t]??'<circle cx="12" cy="12" r="10"/>';}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
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

/* === Дополнения для модуля заявок === */
.nav-badge{margin-left:auto;background:var(--color-primary);color:var(--color-text-inverse);
  font-size:11px;font-weight:700;padding:1px 7px;border-radius:var(--radius-full);min-width:20px;text-align:center}
.tabs-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);margin-bottom:var(--space-5)}
.tabs-list{display:flex;gap:var(--space-2);flex-wrap:wrap}
.tab-item{display:inline-flex;align-items:center;gap:var(--space-2);padding:var(--space-2) var(--space-4);
  border-radius:var(--radius-md);font-size:var(--text-sm);font-weight:500;
  border:1px solid var(--color-border);background:var(--color-surface);color:var(--color-text-muted);
  transition:all var(--transition);white-space:nowrap}
.tab-item:hover{border-color:var(--color-primary);color:var(--color-primary);background:var(--color-primary-highlight)}
.tab-item.active{background:var(--color-primary);color:var(--color-text-inverse);border-color:var(--color-primary);font-weight:600}
.tab-cnt{display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;
  padding:1px 7px;border-radius:var(--radius-full);min-width:20px}
.tab-item.active .tab-cnt{background:rgba(255,255,255,.25);color:#fff}
.tab-item:not(.active) .tab-cnt{background:var(--color-surface-offset);color:var(--color-text-muted)}
.req-list{display:flex;flex-direction:column;gap:var(--space-3)}
.req-card{background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-xl);
  padding:var(--space-5);transition:box-shadow var(--transition),border-color var(--transition);box-shadow:var(--shadow-sm)}
.req-card:hover{box-shadow:var(--shadow-md);border-color:var(--color-primary-highlight)}
.req-top{display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-3);margin-bottom:var(--space-3)}
.req-id{font-size:var(--text-xs);color:var(--color-text-faint);font-weight:500;margin-bottom:var(--space-1)}
.req-type{display:flex;align-items:center;gap:var(--space-2);font-size:var(--text-base);font-weight:600;color:var(--color-text)}
.req-type-icon{width:30px;height:30px;border-radius:var(--radius-md);background:var(--color-primary-highlight);
  color:var(--color-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.req-desc{font-size:var(--text-sm);color:var(--color-text-muted);margin-bottom:var(--space-3);line-height:1.5}
.req-meta{display:flex;gap:var(--space-5);font-size:var(--text-xs);color:var(--color-text-faint);flex-wrap:wrap;margin-bottom:var(--space-3)}
.req-meta-item{display:flex;align-items:center;gap:var(--space-1)}
.req-actions{display:flex;gap:var(--space-2);flex-wrap:wrap}
.btn-success{background:var(--color-success);color:#fff;border-color:var(--color-success)}
.btn-success:hover{background:#15803d}
.btn-danger-soft{background:var(--color-error-highlight);color:var(--color-error);border-color:#fca5a5}
.btn-danger-soft:hover{background:#fecaca}
.empty-state{text-align:center;padding:var(--space-16) var(--space-4)}
.empty-state svg{margin:0 auto var(--space-4);color:var(--color-border)}
.empty-title{font-size:var(--text-base);font-weight:600;color:var(--color-text-muted);margin-bottom:var(--space-1)}
.empty-sub{font-size:var(--text-sm);color:var(--color-text-faint)}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:200;
  align-items:center;justify-content:center;backdrop-filter:blur(3px)}
.modal-overlay.open{display:flex}
.modal-box{background:var(--color-surface);border-radius:var(--radius-xl);padding:var(--space-8);
  width:90%;max-width:440px;box-shadow:var(--shadow-lg);border:1px solid var(--color-border)}
.modal-title{font-family:var(--font-display);font-size:var(--text-base);font-weight:700;
  color:var(--color-text);margin-bottom:var(--space-5);display:flex;align-items:center;gap:var(--space-2)}
.modal-actions{display:flex;gap:var(--space-2);margin-top:var(--space-6);justify-content:flex-end}
  </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<?php
$activePage = 'dashboard';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- ══ MAIN ══ -->
<div class="main-wrapper" id="mainWrapper">
  <header class="topbar">
    <div class="topbar-left">
      <button class="mobile-menu-btn" id="mobileMenuBtn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="breadcrumb">
        <span class="breadcrumb-root">СВГТК</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="breadcrumb-current"><?= htmlspecialchars($page_title) ?></span>
      </div>
    </div>
    <div class="topbar-right">
      <a href="?tab=<?= $tab ?>&lang=ru" class="btn btn-sm <?= $currentLang==='ru'?'btn-primary':'btn-outline' ?>" style="min-width:36px;justify-content:center">Рус</a>
      <a href="?tab=<?= $tab ?>&lang=kk" class="btn btn-sm <?= $currentLang==='kk'?'btn-primary':'btn-outline' ?>" style="min-width:36px;justify-content:center">Қаз</a>
      <button class="theme-toggle" id="themeToggle" title="Тема">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <div class="user-avatar" title="<?= htmlspecialchars($user['full_name']) ?>"><?= $initials ?></div>
      <span style="width:1px;height:20px;background:var(--color-divider);flex-shrink:0"></span>
      <a href="logout.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Выход
      </a>
    </div>
  </header>

  <main class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Мои заявки</h1>
        <p class="page-subtitle">Управление заявками в ИТ-отдел</p>
      </div>
      <div class="page-actions">
        <a href="create_request.php" class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Создать заявку
        </a>
      </div>
    </div>

    <div class="tabs-row">
      <div class="tabs-list">
        <a href="?tab=active" class="tab-item <?= $tab==='active'?'active':'' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
          В работе <span class="tab-cnt"><?= $activeCount ?></span>
        </a>
        <a href="?tab=pending" class="tab-item <?= $tab==='pending'?'active':'' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Ожидают одобрения <span class="tab-cnt"><?= $pendingCount ?></span>
        </a>
        <a href="?tab=waiting" class="tab-item <?= $tab==='waiting'?'active':'' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Ожидают подтверждения <span class="tab-cnt"><?= $waitingCount ?></span>
        </a>
        <a href="?tab=archive" class="tab-item <?= $tab==='archive'?'active':'' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
          Архив <span class="tab-cnt"><?= $archiveCount ?></span>
        </a>
      </div>
    </div>

    <?php if(empty($requests)): ?>
      <div class="empty-state">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        <div class="empty-title">Нет заявок</div>
        <div class="empty-sub"><?= ['active'=>'Нет активных заявок в работе','pending'=>'Нет заявок, ожидающих одобрения','waiting'=>'Нет заявок на подтверждение','archive'=>'Архив пуст'][$tab]??'Нет данных' ?></div>
      </div>
    <?php else: ?>
      <div class="req-list">
        <?php foreach($requests as $req): ?>
          <div class="req-card">
            <div class="req-top">
              <div>
                <div class="req-id">Заявка #<?= $req['id'] ?></div>
                <div class="req-type">
                  <div class="req-type-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= typeIconPath($req['request_type']) ?></svg>
                  </div>
                  <?= htmlspecialchars(typeLabel($req['request_type'])) ?> — каб. <?= htmlspecialchars($req['cabinet']) ?>
                </div>
              </div>
              <div><?= statusBadge($req['status']) ?></div>
            </div>
            <?php if(!empty($req['description'])): ?>
              <div class="req-desc"><?= htmlspecialchars(mb_substr($req['description'],0,160)) ?><?= mb_strlen($req['description'])>160?'…':'' ?></div>
            <?php endif ?>
            <div class="req-meta">
              <span class="req-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= date('d.m.Y H:i', strtotime($req['created_at'])) ?>
              </span>
              <?php if(!empty($req['tech_name'])): ?>
                <span class="req-meta-item">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <?= htmlspecialchars($req['tech_name']) ?>
                </span>
              <?php endif ?>
              <?php if(!empty($req['deadline'])): ?>
                <span class="req-meta-item" style="color:var(--color-warning)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  Срок: <?= date('d.m.Y', strtotime($req['deadline'])) ?>
                </span>
              <?php endif ?>
            </div>
            <div class="req-actions">
              <a href="view_request.php?id=<?= $req['id'] ?>" class="btn btn-outline btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Просмотр
              </a>
              <?php if($req['status']==='awaiting_approval'): ?>
                <button onclick="openConfirm(<?= $req['id'] ?>)" class="btn btn-success btn-sm">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  Принять работу
                </button>
                <button onclick="openReject(<?= $req['id'] ?>)" class="btn btn-danger-soft btn-sm">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
                  Вернуть
                </button>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </main>
</div>

<!-- Modal: Принять -->
<div class="modal-overlay" id="modal-confirm">
  <div class="modal-box">
    <div class="modal-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      Подтвердить выполнение работы
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="confirm_work">
      <input type="hidden" name="request_id" id="confirm-rid">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <label class="form-label">Отзыв о работе (необязательно)</label>
      <textarea name="comment" class="form-control" rows="3" placeholder="Всё выполнено корректно..."></textarea>
      <div class="modal-actions">
        <button type="button" onclick="closeMods()" class="btn btn-outline btn-sm">Отмена</button>
        <button type="submit" class="btn btn-success btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Подтвердить</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Вернуть -->
<div class="modal-overlay" id="modal-reject">
  <div class="modal-box">
    <div class="modal-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
      Вернуть на доработку
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reject_work">
      <input type="hidden" name="request_id" id="reject-rid">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <label class="form-label">Причина возврата <span style="color:var(--color-error)">*</span></label>
      <textarea name="reason" class="form-control" rows="3" placeholder="Укажите что нужно исправить..." required></textarea>
      <div class="modal-actions">
        <button type="button" onclick="closeMods()" class="btn btn-outline btn-sm">Отмена</button>
        <button type="submit" class="btn btn-danger-soft btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg> Вернуть</button>
      </div>
    </form>
  </div>
</div>

<script>
const sidebar=document.getElementById('sidebar');
const mainWrapper=document.getElementById('mainWrapper');
document.getElementById('sidebarToggle').addEventListener('click',()=>{
  sidebar.classList.toggle('collapsed');
  mainWrapper.classList.toggle('sidebar-collapsed');
});
document.getElementById('mobileMenuBtn').addEventListener('click',()=>sidebar.classList.toggle('mobile-open'));
document.addEventListener('click',e=>{
  if(window.innerWidth<=768&&!sidebar.contains(e.target)&&!document.getElementById('mobileMenuBtn').contains(e.target))
    sidebar.classList.remove('mobile-open');
});
const html=document.documentElement;
html.setAttribute('data-theme',localStorage.getItem('theme')||'light');
document.getElementById('themeToggle').addEventListener('click',()=>{
  const next=html.getAttribute('data-theme')==='dark'?'light':'dark';
  html.setAttribute('data-theme',next);localStorage.setItem('theme',next);
});
function openConfirm(id){document.getElementById('confirm-rid').value=id;document.getElementById('modal-confirm').classList.add('open')}
function openReject(id){document.getElementById('reject-rid').value=id;document.getElementById('modal-reject').classList.add('open')}
function closeMods(){document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'))}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeMods()}))
</script>
</body>
</html>
