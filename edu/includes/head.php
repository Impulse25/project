<?php
/**
 * includes/head.php
 * Общий <head> для всех страниц портала.
 *
 * Ожидает переменную $pageTitle, определённую до подключения:
 *   $pageTitle = 'Название страницы';
 *   require 'includes/head.php';
 *
 * Дополнительные шрифты можно передать через $extraFonts:
 *   $extraFonts = ['Playfair+Display:wght@700'];
 */
$pageTitle  = $pageTitle  ?? 'СВГТК Портал';
$extraFonts = $extraFonts ?? [];

$fontFamilies = array_merge(
    ['Inter:wght@300..700', 'Montserrat:wght@600;700'],
    $extraFonts
);
$fontQuery = implode('&family=', $fontFamilies);
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=<?= $fontQuery ?>&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
