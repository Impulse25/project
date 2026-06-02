<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$sql = "
    SELECT
        id,
        username,
        full_name,
        COALESCE(NULLIF(full_name, ''), username) AS display_name
    FROM users
    WHERE role IN ('teacher', '3')
";
if ($q !== '') {
    $sql .= " AND (full_name LIKE :q_full OR username LIKE :q_username)";
    $params[':q_full'] = '%' . $q . '%';
    $params[':q_username'] = '%' . $q . '%';
}
$sql .= " ORDER BY COALESCE(NULLIF(full_name, ''), username), id LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
