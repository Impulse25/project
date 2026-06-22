<?php
// hr/templates/layout.php — общий HTML-каркас страницы
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HR-Аналитика — СВГТК Портал</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/hr.css">
</head>
<body>
<?php require __DIR__ . '/sidebar.php'; ?>
<?php require __DIR__ . '/main.php'; ?>
<?php require __DIR__ . '/modals.php'; ?>
<script type="application/json" id="hrNewStudentsData"><?= json_encode(array_map(fn($s) => [
  'id'    => (int)$s['id'],
  'name'  => trim(($s['surname'] ?? '') . ' ' . ($s['name'] ?? '') . ' ' . ($s['patronymic'] ?? '')),
  'group' => $s['group_name'] ?? ''
], $newStudents), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="hrDocsData"><?= json_encode($docsByEmployment ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="hrChartData"><?= json_encode($hrChartData ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
<script src="assets/js/hr.js"></script>
</body>
</html>
