<?php
// Тонкая обёртка → единый portal_sidebar.php
// Маппинг $activePage (requests) → $activeModule (portal)
$_pageMap = [
    'dashboard' => 'requests',
    'admin'     => 'admin',
    'users'     => 'users',
    'requests'  => 'allrequests',
    'create'    => 'create',
    'logs'      => '',
    'cabinets'  => '',
];
$activeModule = $_pageMap[$activePage ?? ''] ?? ($activePage ?? '');
$moduleTitle  = in_array($_SESSION['role'] ?? '', ['admin', 'director']) ? 'Администратор' : 'Заявки в ИТ';
require_once __DIR__ . '/../../includes/portal_sidebar.php';
