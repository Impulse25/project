<?php
// Тонкая обёртка → единый portal_sidebar.php
// edu-страницы используют $activeNav; portal_sidebar ждёт $activeModule
$activeModule = $activeModule ?? $activeNav ?? 'edu';
$moduleTitle  = 'Учебный процесс';
require_once __DIR__ . '/../../includes/portal_sidebar.php';
