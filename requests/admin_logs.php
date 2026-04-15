<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';
requireRole('admin');
$user = getCurrentUser();
if (isset($_GET['lang'])) { setLanguage($_GET['lang']); header('Location: admin_logs.php'); exit(); }

try {
    $stmt=$pdo->query("SELECT COUNT(*) FROM login_logs WHERE action='login' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)"); $totalLogins=$stmt->fetchColumn();
    $stmt=$pdo->query("SELECT COUNT(DISTINCT user_id) FROM login_logs WHERE action='login' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)"); $uniqueUsers=$stmt->fetchColumn();
    $stmt=$pdo->query("SELECT COUNT(*) FROM login_logs WHERE (action='login_failed' OR success=0) AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)"); $failedLogins=$stmt->fetchColumn();
    $recentLogs=$pdo->query("SELECT * FROM login_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();
    $topUsers=$pdo->query("SELECT user_id,full_name,role,COUNT(*) as login_count,MAX(created_at) as last_login FROM login_logs WHERE action='login' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY user_id,full_name,role ORDER BY login_count DESC LIMIT 10")->fetchAll();
    $roleStats=$pdo->query("SELECT role,COUNT(*) as login_count,COUNT(DISTINCT user_id) as unique_users FROM login_logs WHERE action='login' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY role ORDER BY login_count DESC")->fetchAll();
} catch(Exception $e){ $totalLogins=0; $uniqueUsers=0; $failedLogins=0; $recentLogs=[]; $topUsers=[]; $roleStats=[]; }

$currentLang=getCurrentLanguage();
$nameParts=explode(' ',trim($user['full_name']));
$initials=implode('',array_map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)),array_slice($nameParts,0,2)));
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" data-theme="light">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Журнал входов — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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

.nav-badge{margin-left:auto;background:var(--color-primary);color:#fff;font-size:11px;font-weight:700;padding:1px 7px;border-radius:var(--radius-full);min-width:20px;text-align:center}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:white;padding:30px;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
.permission-checkbox{display:flex;align-items:center;padding:12px;background:#f9fafb;border-radius:8px;border:2px solid #e5e7eb;transition:all 0.2s;margin-bottom:8px}
.permission-checkbox:hover{background:#f3f4f6;border-color:#d1d5db}

  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#1a56db"/><text x="16" y="22" text-anchor="middle" font-family="Montserrat,sans-serif" font-weight="700" font-size="13" fill="white">СП</text></svg>
      <div class="logo-text"><span class="logo-title">СВГТК Портал</span><span class="logo-sub">Администратор</span></div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
  </div>
  <nav class="sidebar-nav">

    <div class="nav-section-label">Система</div>
    <a href="admin_dashboard.php" class="nav-item ">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      <span>Дашборд</span>
    </a>
    <a href="users.php" class="nav-item ">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Пользователи и роли</span>
    </a>
    <a href="admin_cabinets.php" class="nav-item ">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Кабинеты и отделения</span>
    </a>
    <a href="admin_logs.php" class="nav-item active">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span>Журнал входов</span>
    </a>

    <div class="nav-section-label" style="margin-top:var(--space-4)">Заявки в ИТ</div>
    <a href="teacher_dashboard.php" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
      <span>Все заявки</span>
    </a>

    <div class="nav-section-label" style="margin-top:var(--space-4)">Учебный процесс</div>
    <a href="../edu/" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span>Расписание и группы</span>
    </a>
    <a href="../edu/students.php" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Студенты</span>
    </a>

    <div class="nav-section-label" style="margin-top:var(--space-4)">Посещаемость</div>
    <a href="../attendance/" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <span>Журнал посещаемости</span>
    </a>

    <div class="nav-section-label" style="margin-top:var(--space-4)">Достижения</div>
    <a href="../achievements/" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
      <span>Портфолио</span>
    </a>

    <div class="nav-section-label" style="margin-top:var(--space-4)">УМР</div>
    <a href="../umr/" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <span>Документы</span>
    </a>

    <div class="nav-section-label" style="margin-top:var(--space-4)">HR-аналитика</div>
    <a href="../hr/" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      <span>Выпускники</span>
    </a>

    <div class="nav-section-label" style="margin-top:var(--space-4)">Аналитика</div>
    <a href="../analytics/" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Сводные отчёты</span>
    </a>

    <div class="nav-section-label" style="margin-top:var(--space-4)">Портал</div>
    <a href="../" class="nav-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Главная портала</span>
    </a>

  </nav>
  <div class="sidebar-footer"><div class="college-info"><span>СВГТК им. Абая Кунанбаева</span><span>г. Сарань</span></div></div>
</aside><div class="main-wrapper" id="mainWrapper">
  <header class="topbar">
    <div class="topbar-left">
      <div class="breadcrumb">
        <span class="breadcrumb-root"><a href="admin_dashboard.php" style="color:inherit">Дашборд</a></span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="breadcrumb-current">Журнал входов</span>
      </div>
    </div>
    <div class="topbar-right">
      <a href="?lang=ru" class="btn btn-sm <?= $currentLang==='ru'?'btn-primary':'btn-outline' ?>">Рус</a>
      <a href="?lang=kk" class="btn btn-sm <?= $currentLang==='kk'?'btn-primary':'btn-outline' ?>">Қаз</a>
      <button class="theme-toggle" id="themeToggle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
      <div class="user-avatar"><?= $initials ?></div>
      <span style="width:1px;height:20px;background:var(--color-divider)"></span>
      <a href="logout.php" class="btn btn-outline btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Выход</a>
    </div>
  </header>
  <main class="page-content">
    <div style="margin-bottom:1.5rem">
      <h1 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--color-text)">Журнал входов</h1>
      <p style="font-size:.9375rem;color:var(--color-text-muted);margin-top:.25rem">История авторизаций за последние 30 дней</p>
    </div>
<!-- Контент вкладки Логи -->
        
            <!-- Статистика за 30 дней -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Всего входов</p>
                            <p class="text-3xl font-bold text-indigo-600"><?php echo $totalLogins; ?></p>
                            <p class="text-xs text-gray-500 mt-1">За последние 30 дней</p>
                        </div>
                        <i class="fas fa-sign-in-alt text-4xl text-indigo-600"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Уникальных пользователей</p>
                            <p class="text-3xl font-bold text-green-600"><?php echo $uniqueUsers; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Активных за месяц</p>
                        </div>
                        <i class="fas fa-users text-4xl text-green-600"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Неудачные попытки</p>
                            <p class="text-3xl font-bold text-red-600"><?php echo $failedLogins; ?></p>
                            <p class="text-xs text-gray-500 mt-1">За последние 30 дней</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-4xl text-red-600"></i>
                    </div>
                </div>
            </div>
            
            <!-- Топ пользователей и статистика по ролям -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Топ-10 активных -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-trophy mr-2"></i>
                        Топ-10 активных (30 дней)
                    </h3>
                    <?php if (!empty($topUsers)): ?>
                        <div class="space-y-3">
                            <?php foreach ($topUsers as $index => $tu): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center font-bold text-indigo-600">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?php echo $tu['full_name']; ?></p>
                                            <p class="text-xs text-gray-500"><?php echo t($tu['role']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-indigo-600"><?php echo $tu['login_count']; ?> входов</p>
                                        <p class="text-xs text-gray-500"><?php echo date('d.m.Y', strtotime($tu['last_login'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">Нет данных</p>
                    <?php endif; ?>
                </div>
                
                <!-- Статистика по ролям -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-user-tag mr-2"></i>
                        Активность по ролям (30 дней)
                    </h3>
                    <?php if (!empty($roleStats)): ?>
                        <div class="space-y-2">
                            <?php foreach ($roleStats as $rs): 
                                $roleColors = [
                                    'admin' => 'bg-purple-100 text-purple-800',
                                    'director' => 'bg-red-100 text-red-800',
                                    'teacher' => 'bg-blue-100 text-blue-800',
                                    'technician' => 'bg-green-100 text-green-800'
                                ];
                            ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $roleColors[$rs['role']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo t($rs['role']); ?>
                                    </span>
                                    <span class="text-sm text-gray-600">
                                        <?php echo $rs['login_count']; ?> входов / <?php echo $rs['unique_users']; ?> польз.
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">Нет данных</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Таблица логов -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-list mr-2"></i>
                        История входов (последние 50 записей)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Пользователь</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действие</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип авторизации</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP-адрес</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата и время</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($recentLogs)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-2"></i>
                                        <p>Логи отсутствуют</p>
                                        <p class="text-xs mt-2">Убедитесь что выполнен SQL: create_logs_table.sql</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentLogs as $log): 
                                    $actionColors = [
                                        'login' => 'bg-green-100 text-green-800',
                                        'login_failed' => 'bg-red-100 text-red-800',
                                        'failed_login' => 'bg-red-100 text-red-800',
                                        'logout' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $actionIcons = [
                                        'login' => 'fa-sign-in-alt',
                                        'login_failed' => 'fa-times-circle',
                                        'failed_login' => 'fa-times-circle',
                                        'logout' => 'fa-sign-out-alt'
                                    ];
                                    $actionTexts = [
                                        'login' => 'Вход',
                                        'login_failed' => 'Неудачный вход',
                                        'failed_login' => 'Неудачный вход',
                                        'logout' => 'Выход'
                                    ];
                                    $authTypeColors = [
                                        'local' => 'bg-blue-100 text-blue-800',
                                        'ldap' => 'bg-purple-100 text-purple-800',
                                        'session' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $authTypeTexts = [
                                        'local' => 'Локальная',
                                        'ldap' => 'LDAP/AD',
                                        'session' => 'Сессия'
                                    ];
                                    $roleColors = [
                                        'admin' => 'bg-purple-100 text-purple-800',
                                        'director' => 'bg-red-100 text-red-800',
                                        'teacher' => 'bg-blue-100 text-blue-800',
                                        'technician' => 'bg-green-100 text-green-800'
                                    ];
                                    // Если success = 0, показать как неудачный
                                    $displayAction = $log['action'];
                                    if (isset($log['success']) && $log['success'] == 0 && $log['action'] === 'login') {
                                        $displayAction = 'login_failed';
                                    }
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-900">#<?php echo $log['id']; ?></td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['full_name'] ?? '-'); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['username']); ?></div>
                                            <?php if (!empty($log['error_message'])): ?>
                                                <div class="text-xs text-red-500 mt-1"><?php echo htmlspecialchars($log['error_message']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $roleColors[$log['role']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo $log['role'] ? t($log['role']) : '-'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $actionColors[$displayAction] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <i class="fas <?php echo $actionIcons[$displayAction] ?? 'fa-circle'; ?> mr-1"></i>
                                                <?php echo $actionTexts[$displayAction] ?? $displayAction; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $authTypeColors[$log['auth_type'] ?? ''] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo $authTypeTexts[$log['auth_type'] ?? ''] ?? ($log['auth_type'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo $log['ip_address']; ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
    </div>  </main>
</div>
<script>
const sidebar=document.getElementById('sidebar');
const mainWrapper=document.getElementById('mainWrapper');
document.getElementById('sidebarToggle').addEventListener('click',()=>{sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('sidebar-collapsed');});
const html=document.documentElement;
html.setAttribute('data-theme',localStorage.getItem('theme')||'light');
document.getElementById('themeToggle').addEventListener('click',()=>{const next=html.getAttribute('data-theme')==='dark'?'light':'dark';html.setAttribute('data-theme',next);localStorage.setItem('theme',next);});
function openModal(id){document.getElementById(id).classList.add('active')}
function closeModal(id){document.getElementById(id).classList.remove('active')}
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('active')}));
</script>
</body>
</html>