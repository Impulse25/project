<?php
// Тонкая обёртка → единый portal_sidebar.php
// qr-страницы используют $activePage; portal_sidebar ждёт $activeModule
$activeModule = $activePage ?? 'qr';
$moduleTitle  = 'QR-Посещаемость';
require_once __DIR__ . '/../includes/portal_sidebar.php';
