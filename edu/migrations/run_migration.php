<?php
/**
 * Запускает SQL-миграции из папки migrations.
 * Доступ только для admin. После применения файл лучше удалить.
 */
require_once __DIR__ . '/../../config/db.php';
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Доступ запрещён. Нужна роль admin.');
}

if (($pdo ?? null) instanceof PDO) {
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' && defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    } catch (Throwable $e) {
        // Не критично: на некоторых сборках PHP атрибут может быть недоступен.
    }
}

$dir   = __DIR__;
$files = glob($dir . '/*.sql');
sort($files);

if (empty($files)) {
    die('<h2>Файлы миграций не найдены в ' . htmlspecialchars($dir) . '</h2>');
}

$results = [];

foreach ($files as $file) {
    $name       = basename($file);
    $sql        = file_get_contents($file);
    $statements = _split_sql($sql);
    $errors     = [];

    foreach ($statements as $stmt) {
        $stmt = _strip_leading_sql_comments(trim($stmt));
        if ($stmt === '') continue;

        try {
            _execute_sql_statement($pdo, $stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Игнорируем безопасные ошибки повторного запуска миграции.
            if (!str_contains($msg, 'already exists') &&
                !str_contains($msg, 'Duplicate key') &&
                !str_contains($msg, 'Duplicate column') &&
                !str_contains($msg, 'Multiple primary key') &&
                !str_contains($msg, '42S01') &&
                !str_contains($msg, '42S21')) {
                $errors[] = $msg;
            }
        }
    }

    $results[$name] = $errors
        ? ['status' => 'error', 'errors' => $errors]
        : ['status' => 'ok'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Миграции edu</title>
<style>
  body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;margin:0}
  h1{color:#38bdf8;margin-bottom:1.5rem}
  .file{margin:1rem 0;padding:1rem 1.25rem;border-radius:8px;line-height:1.6}
  .ok{background:#14532d;border:1px solid #16a34a}
  .error{background:#450a0a;border:1px solid #dc2626}
  pre{margin:.5rem 0;white-space:pre-wrap;font-size:.8rem;opacity:.85}
  .hint{color:#94a3b8;margin-top:2rem;font-size:.875rem}
</style>
</head>
<body>
<h1>Миграции edu</h1>
<?php foreach ($results as $name => $r): ?>
<div class="file <?= $r['status'] ?>">
  <strong><?= htmlspecialchars($name) ?></strong>
  — <?= $r['status'] === 'ok' ? '✅ OK' : '❌ Ошибки' ?>
  <?php foreach ($r['errors'] ?? [] as $err): ?>
  <pre><?= htmlspecialchars($err) ?></pre>
  <?php endforeach ?>
</div>
<?php endforeach ?>
<p class="hint">
  Готово. Удалите <code>edu/migrations/run_migration.php</code> после применения.
</p>
</body>
</html>
<?php

function _execute_sql_statement(PDO $pdo, string $stmt): void
{
    $stmt = trim($stmt);
    if ($stmt === '') return;

    $q = $pdo->prepare($stmt);
    $q->execute();

    try {
        do {
            if ($q->columnCount() > 0) {
                $q->fetchAll(PDO::FETCH_ASSOC);
            }
        } while ($q->nextRowset());
    } catch (PDOException $e) {
        // Некоторые драйверы ругаются на nextRowset() для одиночных запросов.
    } finally {
        $q->closeCursor();
    }
}

function _strip_leading_sql_comments(string $stmt): string
{
    $lines = preg_split('/\R/', $stmt);
    while ($lines && (trim($lines[0]) === '' || str_starts_with(ltrim($lines[0]), '--') || str_starts_with(ltrim($lines[0]), '#'))) {
        array_shift($lines);
    }
    return trim(implode("\n", $lines));
}

function _split_sql(string $sql): array
{
    // Убираем построчные SQL-комментарии до разделения запросов.
    // Старый разборщик мог отправить строку комментария в MySQL как отдельный запрос.
    $cleanLines = [];
    foreach (preg_split('/\R/', $sql) as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }
        $cleanLines[] = $line;
    }
    $sql = implode("\n", $cleanLines);

    $statements = [];
    $current = '';
    $len = strlen($sql);
    $quote = null;
    $escape = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $current .= $ch;

        if ($escape) {
            $escape = false;
            continue;
        }

        if ($quote !== null) {
            if ($ch === '\\') {
                $escape = true;
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            continue;
        }

        if ($ch === ';') {
            $stmt = trim(substr($current, 0, -1));
            if ($stmt !== '') $statements[] = $stmt;
            $current = '';
        }
    }

    $tail = trim($current);
    if ($tail !== '') $statements[] = $tail;

    return $statements;
}
