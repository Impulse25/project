<?php
/**
 * edu/curriculum_import.php
 * Загрузка xlsx/xls РУПл → парсинг → превью → сохранение в БД.
 */
require 'vendor/autoload.php';
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/curriculum_parser.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

function _clear_rupl_import_session(): void
{
    if (!empty($_SESSION['rupl_tmp']) && is_file($_SESSION['rupl_tmp'])) @unlink($_SESSION['rupl_tmp']);
    unset($_SESSION['rupl_tmp'], $_SESSION['rupl_name'], $_SESSION['rupl_parsed']);
}

if (isset($_GET['cancel'])) {
    _clear_rupl_import_session();
    header('Location: curricula.php');
    exit;
}

if (isset($_GET['reset'])) {
    _clear_rupl_import_session();
    header('Location: curriculum_import.php');
    exit;
}

$step = 'upload';
$parsed = null;
$message = '';
$msgType = '';

function _h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function _clean_upload_name(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $name) ?: 'rupl.xlsx';
    return trim($name, '._') ?: 'rupl.xlsx';
}

function _curriculum_index_aliases(string $idx): array
{
    $base = trim($idx);
    if ($base === '') return [];
    $withoutDot = rtrim($base, '.');
    $compact = preg_replace('/\s+/u', '', $withoutDot) ?? $withoutDot;
    $items = [$base, $withoutDot, $compact, $compact . '.'];
    return array_values(array_unique(array_filter($items, static fn($v) => $v !== '')));
}

function _curriculum_is_parent_section_code(string $idx): bool
{
    $code = str_replace(["\xc2\xa0", ' '], '', trim($idx));
    if (function_exists('mb_strtoupper')) $code = mb_strtoupper($code, 'UTF-8');
    else $code = strtoupper($code);
    return $code !== '' && (bool)preg_match('/^(ООМ|БМ|ПМ)(?:\d+\.?)?$/u', $code);
}

function _sum_credits(array $modules): float
{
    $best = 0.0;
    foreach ($modules as $m) {
        if (!empty($m['is_summary']) && isset($m['credits']) && $m['credits'] !== null) {
            $best = max($best, (float)$m['credits']);
        }
    }
    if ($best > 0) return $best;

    $sum = 0.0;
    foreach ($modules as $m) {
        if (empty($m['is_summary']) && isset($m['credits']) && $m['credits'] !== null) {
            $idx = (string)($m['index_code'] ?? '');
            if (!_curriculum_is_parent_section_code($idx) && (str_contains($idx, '.') || in_array($m['module_type'] ?? '', ['ООД','ООМ','К','Ф','ПА','ИА','ДП'], true))) {
                $sum += (float)$m['credits'];
            }
        }
    }
    return $sum;
}

function _sum_hours(array $modules): int
{
    $best = 0;
    foreach ($modules as $m) {
        if (!empty($m['is_summary']) && isset($m['total_hours']) && $m['total_hours'] !== null) {
            $best = max($best, (int)$m['total_hours']);
        }
    }
    if ($best > 0) return $best;

    $sum = 0;
    foreach ($modules as $m) {
        if (empty($m['is_summary']) && isset($m['total_hours']) && $m['total_hours'] !== null) {
            $idx = (string)($m['index_code'] ?? '');
            if (!_curriculum_is_parent_section_code($idx) && (str_contains($idx, '.') || in_array($m['module_type'] ?? '', ['ООД','ООМ','К','Ф','ПА','ИА','ДП'], true))) {
                $sum += (int)$m['total_hours'];
            }
        }
    }
    return $sum;
}

function _duration_years(array $modules): int
{
    $maxSem = 0;
    foreach ($modules as $m) {
        // Считаем срок обучения только по реальным семестрам, а не по числам из строк «Итого».
        if (!empty($m['is_summary'])) continue;

        foreach (array_keys($m['distribution'] ?? []) as $sem) {
            $sem = (int)$sem;
            if ($sem >= 1 && $sem <= 8) $maxSem = max($maxSem, $sem);
        }
        foreach (['exam_semester','credit_semester','control_work'] as $key) {
            if (!empty($m[$key]) && preg_match_all('/\d+/u', (string)$m[$key], $mm)) {
                foreach ($mm[0] as $sem) {
                    $sem = (int)$sem;
                    if ($sem >= 1 && $sem <= 8) $maxSem = max($maxSem, $sem);
                }
            }
        }
    }
    return max(1, min(4, (int)ceil(max(1, $maxSem) / 2)));
}

function _module_depth(array $m): int
{
    $idx = trim((string)($m['index_code'] ?? ''));
    if ($idx === '') return 0;
    if (preg_match('/^[\p{L}]+\s*\d+\.\d+/u', $idx)) return 2;
    if (preg_match('/^[\p{L}]+\s*\d+/u', $idx)) return 1;
    return 0;
}

function _module_color(string $type): string
{
    return match ($type) {
        'ООД', 'ООМ' => 'var(--color-primary)',
        'БМ'  => 'var(--color-accent)',
        'ПМ'  => 'var(--color-success)',
        'ПА', 'ИА', 'ДП' => 'var(--color-warning)',
        default => 'var(--color-text-muted)',
    };
}


function _ensure_curriculum_extra_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS edu_curriculum_passport_fields (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            curriculum_id INT UNSIGNED NOT NULL,
            label VARCHAR(255) NOT NULL DEFAULT '',
            value MEDIUMTEXT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_ecpf_curriculum (curriculum_id),
            CONSTRAINT fk_ecpf_curriculum FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS edu_curriculum_process_schedule (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            curriculum_id INT UNSIGNED NOT NULL,
            course_label VARCHAR(10) NOT NULL DEFAULT '',
            week_num TINYINT UNSIGNED NOT NULL DEFAULT 0,
            month_name VARCHAR(30) NOT NULL DEFAULT '',
            value_text VARCHAR(255) NOT NULL DEFAULT '',
            span_weeks TINYINT UNSIGNED NOT NULL DEFAULT 1,
            INDEX idx_ecps_curriculum (curriculum_id),
            INDEX idx_ecps_course_week (curriculum_id, course_label, week_num),
            CONSTRAINT fk_ecps_curriculum FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $hasSpan = (int)$pdo->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'edu_curriculum_process_schedule'
          AND COLUMN_NAME = 'span_weeks'
    ")->fetchColumn();
    if ($hasSpan === 0) {
        $pdo->exec("ALTER TABLE edu_curriculum_process_schedule ADD COLUMN span_weeks TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER value_text");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS edu_curriculum_process_legend (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            curriculum_id INT UNSIGNED NOT NULL,
            code VARCHAR(20) NOT NULL DEFAULT '',
            description VARCHAR(255) NOT NULL DEFAULT '',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_ecpl_curriculum (curriculum_id),
            CONSTRAINT fk_ecpl_curriculum FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS edu_curriculum_summary (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            curriculum_id INT UNSIGNED NOT NULL,
            course_label VARCHAR(10) NOT NULL DEFAULT '',
            theory_weeks DECIMAL(5,2) NULL,
            theory_hours SMALLINT UNSIGNED NULL,
            theory_credits DECIMAL(5,2) NULL,
            interim_attestation_hours SMALLINT UNSIGNED NULL,
            production_practice_hours SMALLINT UNSIGNED NULL,
            diploma_design_hours SMALLINT UNSIGNED NULL,
            final_attestation_hours SMALLINT UNSIGNED NULL,
            holiday_hours SMALLINT UNSIGNED NULL,
            vacation_weeks SMALLINT UNSIGNED NULL,
            total_weeks SMALLINT UNSIGNED NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_ecs_curriculum (curriculum_id),
            CONSTRAINT fk_ecs_curriculum FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS edu_curriculum_semester_meta (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            curriculum_id INT UNSIGNED NOT NULL,
            semester_num TINYINT UNSIGNED NOT NULL,
            study_weeks DECIMAL(5,2) NULL,
            weekly_hours SMALLINT UNSIGNED NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_ecsm_curriculum_semester (curriculum_id, semester_num),
            INDEX idx_ecsm_curriculum (curriculum_id),
            CONSTRAINT fk_ecsm_curriculum FOREIGN KEY (curriculum_id) REFERENCES edu_curricula(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // v44+: импорт использует component_name. Создаём поле здесь тоже,
    // чтобы сохранение РУПЛ не падало, если администратор забыл запустить миграции.
    $hasComponentName = (int)$pdo->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'edu_curriculum_modules'
          AND COLUMN_NAME = 'component_name'
    ")->fetchColumn();
    if ($hasComponentName === 0) {
        $pdo->exec("ALTER TABLE edu_curriculum_modules ADD COLUMN component_name TEXT NULL AFTER module_type");
    }

    // v45+: новые РУПл используют индекс/тип «ООМ».
    // Старое поле module_type было ENUM без значения «ООМ», из-за этого
    // такие строки либо не импортировались парсером, либо не могли сохраниться в БД.
    $moduleTypeInfo = $pdo->query("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'edu_curriculum_modules'
          AND COLUMN_NAME = 'module_type'
    ")->fetch(PDO::FETCH_ASSOC);
    if ($moduleTypeInfo && strpos((string)$moduleTypeInfo['COLUMN_TYPE'], "'ООМ'") === false) {
        $pdo->exec("ALTER TABLE edu_curriculum_modules MODIFY module_type ENUM('ООД','ООМ','БМ','ПМ','ПА','ИА','ДП','К','Ф','ИТОГО') NOT NULL DEFAULT 'ООД'");
    }
}

// ── Шаг 1: загрузка файла ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['rupl_file'])) {
    $file = $_FILES['rupl_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Ошибка загрузки файла.';
        $msgType = 'error';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            $message = 'Допустимые форматы: .xlsx, .xls';
            $msgType = 'error';
        } else {
            if (!empty($_SESSION['rupl_tmp']) && is_file($_SESSION['rupl_tmp'])) @unlink($_SESSION['rupl_tmp']);

            $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rupl_' . uniqid('', true) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
                $message = 'Не удалось сохранить загруженный файл во временную папку.';
                $msgType = 'error';
            } else {
                $parser = new CurriculumParser();
                $parsed = $parser->parse($tmpPath);

                if (empty($parsed['ok'])) {
                    $message = 'Ошибки парсинга: ' . implode('; ', $parsed['errors'] ?? ['неизвестная ошибка']);
                    $msgType = 'error';
                    @unlink($tmpPath);
                } else {
                    $_SESSION['rupl_tmp'] = $tmpPath;
                    $_SESSION['rupl_name'] = $file['name'];
                    $_SESSION['rupl_parsed'] = serialize($parsed);
                    $step = 'preview';
                }
            }
        }
    }
}

// ── Шаг 2: подтверждение и сохранение ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    if (empty($_SESSION['rupl_parsed'])) {
        $message = 'Сессия истекла. Загрузите файл заново.';
        $msgType = 'error';
    } else {
        $parsed = unserialize($_SESSION['rupl_parsed'], ['allowed_classes' => false]);
        $tmpPath = $_SESSION['rupl_tmp'] ?? '';
        $origName = $_SESSION['rupl_name'] ?? 'rupl.xlsx';
        $chosenSheet = trim((string)($_POST['plan_sheet'] ?? ($parsed['selected_plan_sheet'] ?? '')));

        if ($tmpPath && is_file($tmpPath)) {
            $parser = new CurriculumParser();
            $reparsed = $parser->parse($tmpPath, $chosenSheet !== '' ? $chosenSheet : null);
            if (empty($reparsed['ok'])) {
                $message = 'Ошибки парсинга выбранного листа: ' . implode('; ', $reparsed['errors'] ?? ['неизвестная ошибка']);
                $msgType = 'error';
                $parsed = $reparsed;
                $step = 'preview';
            } else {
                $parsed = $reparsed;
                $_SESSION['rupl_parsed'] = serialize($parsed);
            }
        } else {
            $message = 'Временный файл не найден. Загрузите РУПл заново.';
            $msgType = 'error';
        }

        if ($msgType !== 'error') {
            $enrollment = (int)($_POST['enrollment_year'] ?? date('Y'));
            if ($enrollment < 2000 || $enrollment > 2099) $enrollment = (int)date('Y');

            $baseEdu = in_array($_POST['base_education'] ?? '', ['9 класс', '11 класс'], true)
                ? $_POST['base_education'] : '9 класс';
            $planName = trim((string)($_POST['plan_name'] ?? ''));
            if ($planName === '') $planName = pathinfo($origName, PATHINFO_FILENAME) ?: 'РУПл';
            $specialityId = (int)($_POST['speciality_id'] ?? 0) ?: null;

            $uploadDir = __DIR__ . '/uploads/curricula/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $savedName = date('Ymd_His') . '_' . _clean_upload_name($origName);
            $savedPath = 'uploads/curricula/' . $savedName;

            try {
                _ensure_curriculum_extra_tables($pdo);
                $pdo->beginTransaction();

                $passport = $parsed['passport'] ?? [];
                $pdo->prepare("\n                    INSERT INTO edu_curricula\n                        (speciality_id, specialty_code, specialty_name, qualification,\n                         base_education, enrollment_year, duration_years,\n                         total_credits, total_hours, name, file_path, imported_at, imported_by)\n                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)\n                ")->execute([
                    $specialityId,
                    $passport['specialty_code'] ?? '',
                    $passport['specialty_name'] ?? $planName,
                    $passport['qualification'] ?? null,
                    $baseEdu,
                    $enrollment,
                    _duration_years($parsed['modules']),
                    _sum_credits($parsed['modules']),
                    _sum_hours($parsed['modules']),
                    $planName,
                    $savedPath,
                    $_SESSION['user_id'] ?? null,
                ]);
                $curriculumId = (int)$pdo->lastInsertId();

                $compStmt = $pdo->prepare("\n                    INSERT INTO edu_competencies (curriculum_id, code, name, sort_order)\n                    VALUES (?, ?, ?, ?)\n                ");
                foreach (($parsed['competencies'] ?? []) as $comp) {
                    $compStmt->execute([$curriculumId, $comp['code'], $comp['name'], $comp['sort_order']]);
                }

                $moduleStmt = $pdo->prepare("\n                    INSERT INTO edu_curriculum_modules\n                        (curriculum_id, parent_id, index_code, module_type, component_name, name,\n                         credits, total_hours, theory_hours, practice_hours,\n                         coursework_hours, srsp_hours, srs_hours, production_hours,\n                         individual_hours, exam_semester, credit_semester, control_work,\n                         is_summary, sort_order)\n                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n                ");
                $distStmt = $pdo->prepare("\n                    INSERT INTO edu_curriculum_distribution (module_id, semester_num, hours)\n                    VALUES (?, ?, ?)\n                ");

                $indexToDbId = [];
                foreach ($parsed['modules'] as $mod) {
                    $parentDbId = null;
                    if ($mod['_parent_key'] !== null && isset($indexToDbId[$mod['_parent_key']])) {
                        $parentDbId = $indexToDbId[$mod['_parent_key']];
                    }

                    $moduleStmt->execute([
                        $curriculumId,
                        $parentDbId,
                        $mod['index_code'],
                        $mod['module_type'],
                        $mod['component_name'] ?? null,
                        $mod['name'],
                        $mod['credits'],
                        $mod['total_hours'],
                        $mod['theory_hours'],
                        $mod['practice_hours'],
                        $mod['coursework_hours'],
                        $mod['srsp_hours'],
                        $mod['srs_hours'],
                        $mod['production_hours'],
                        $mod['individual_hours'],
                        $mod['exam_semester'],
                        $mod['credit_semester'],
                        $mod['control_work'],
                        $mod['is_summary'],
                        $mod['sort_order'],
                    ]);

                    $moduleDbId = (int)$pdo->lastInsertId();
                    if ($mod['index_code'] !== '') {
                        foreach (_curriculum_index_aliases((string)$mod['index_code']) as $alias) {
                            $indexToDbId[$alias] = $moduleDbId;
                        }
                    }

                    foreach (($mod['distribution'] ?? []) as $semNum => $hours) {
                        $distStmt->execute([$moduleDbId, (int)$semNum, (int)$hours]);
                    }
                }

                $passportFieldStmt = $pdo->prepare("
                    INSERT INTO edu_curriculum_passport_fields (curriculum_id, label, value, sort_order)
                    VALUES (?, ?, ?, ?)
                ");
                foreach (($parsed['passport_rows'] ?? []) as $row) {
                    $passportFieldStmt->execute([
                        $curriculumId,
                        (string)($row['label'] ?? ''),
                        (string)($row['value'] ?? ''),
                        (int)($row['sort_order'] ?? 0),
                    ]);
                }

                $calendarStmt = $pdo->prepare("
                    INSERT INTO edu_curriculum_process_schedule (curriculum_id, course_label, week_num, month_name, value_text, span_weeks)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach (($parsed['calendar']['courses'] ?? []) as $courseRow) {
                    foreach (($courseRow['items'] ?? []) as $item) {
                        $calendarStmt->execute([
                            $curriculumId,
                            (string)($courseRow['course'] ?? ''),
                            (int)($item['week_num'] ?? 0),
                            (string)($item['month'] ?? ''),
                            (string)($item['value'] ?? ''),
                            max(1, min(52, (int)($item['span'] ?? 1))),
                        ]);
                    }
                }

                $legendStmt = $pdo->prepare("
                    INSERT INTO edu_curriculum_process_legend (curriculum_id, code, description, sort_order)
                    VALUES (?, ?, ?, ?)
                ");
                $legendOrder = 0;
                foreach (($parsed['calendar']['legend'] ?? []) as $code => $description) {
                    $legendStmt->execute([
                        $curriculumId,
                        (string)$code,
                        (string)$description,
                        ++$legendOrder,
                    ]);
                }

                $semesterMetaStmt = $pdo->prepare("
                    INSERT INTO edu_curriculum_semester_meta
                        (curriculum_id, semester_num, study_weeks, weekly_hours, sort_order)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        study_weeks = VALUES(study_weeks),
                        weekly_hours = VALUES(weekly_hours),
                        sort_order = VALUES(sort_order)
                ");
                foreach (($parsed['semester_meta'] ?? []) as $semNum => $row) {
                    $semesterMetaStmt->execute([
                        $curriculumId,
                        (int)($row['semester_num'] ?? $semNum),
                        $row['study_weeks'] ?? null,
                        $row['weekly_hours'] ?? null,
                        (int)($row['semester_num'] ?? $semNum),
                    ]);
                }

                $summaryStmt = $pdo->prepare("
                    INSERT INTO edu_curriculum_summary
                        (curriculum_id, course_label, theory_weeks, theory_hours, theory_credits,
                         interim_attestation_hours, production_practice_hours, diploma_design_hours,
                         final_attestation_hours, holiday_hours, vacation_weeks, total_weeks, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach (($parsed['summary'] ?? []) as $row) {
                    $summaryStmt->execute([
                        $curriculumId,
                        (string)($row['course'] ?? ''),
                        $row['theory_weeks'] ?? null,
                        $row['theory_hours'] ?? null,
                        $row['theory_credits'] ?? null,
                        $row['interim_attestation'] ?? null,
                        $row['production_practice'] ?? null,
                        $row['diploma_design'] ?? null,
                        $row['final_attestation'] ?? null,
                        $row['holidays'] ?? null,
                        $row['vacations'] ?? null,
                        $row['total_weeks'] ?? null,
                        (int)($row['sort_order'] ?? 0),
                    ]);
                }

                if (!rename($tmpPath, __DIR__ . '/' . $savedPath)) {
                    throw new RuntimeException('Не удалось перенести Excel-файл в uploads/curricula.');
                }

                $pdo->commit();
                _clear_rupl_import_session();

                $step = 'done';
                $message = "РУПл «{$planName}» импортирован. ID плана: {$curriculumId}.";
                $msgType = 'success';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = 'Ошибка сохранения: ' . $e->getMessage();
                $msgType = 'error';
                $step = 'preview';
            }
        }
    }
}

if ($step === 'upload' && !empty($_SESSION['rupl_parsed'])) {
    $parsed = unserialize($_SESSION['rupl_parsed'], ['allowed_classes' => false]);
    if (!empty($parsed['ok'])) $step = 'preview';
}

$specialties = $pdo->query('SELECT id, name_ru FROM edu_specialties ORDER BY name_ru')->fetchAll();

$totalSubjects = 0;
$totalModules = 0;
if ($parsed && !empty($parsed['ok'])) {
    foreach ($parsed['modules'] as $m) {
        if (!empty($m['is_summary'])) continue;
        $idx = (string)($m['index_code'] ?? '');
        if (!_curriculum_is_parent_section_code($idx) && (str_contains($idx, '.') || in_array($m['module_type'], ['ООД','ООМ','К','Ф','ПА','ИА','ДП'], true))) $totalSubjects++;
        else $totalModules++;
    }
}

$pageTitle = 'Импорт РУПл';
$activeNav = 'edu';
$sidebarSubtitle = 'Учебный процесс';
$breadcrumbs = [
    ['label' => 'СВГТК', 'href' => '../'],
    ['label' => 'Учебный процесс', 'href' => 'index.php'],
    ['label' => 'Учебные планы (РУПл)', 'href' => 'curricula.php'],
    ['label' => 'Импорт РУПл'],
];
$controlStyle = 'padding:.5rem .75rem;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-surface);color:var(--color-text);font-size:.9375rem';
$labelStyle = 'display:block;font-size:.8125rem;font-weight:500;color:var(--color-text-muted);margin-bottom:.35rem';
$thStyle = 'font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);padding:.75rem 1rem;background:var(--color-surface-2);border-bottom:1px solid var(--color-divider);text-align:left;white-space:nowrap';
$tdStyle = 'padding:.65rem 1rem;border-bottom:1px solid var(--color-divider);font-size:.875rem;vertical-align:top';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= _h($pageTitle) ?> — СВГТК</title>
  <?php require 'includes/head.php' ?>
</head>
<body>
<?php require 'includes/sidebar.php' ?>
<div class="main-wrapper" id="mainWrapper">
  <?php require 'includes/topbar.php' ?>
  <main class="page-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Импорт РУПл</h1>
        <p class="page-subtitle">Загрузка рабочего учебного плана из Excel и проверка данных перед сохранением</p>
      </div>
      <div class="page-actions">
        <?php if ($step === 'preview'): ?>
        <a href="curriculum_import.php?cancel=1" class="btn btn-outline" onclick="return confirm('Отменить импорт и удалить временный файл?')">Отменить импорт</a>
        <?php endif ?>
        <a href="curricula.php" class="btn btn-outline">← К списку планов</a>
      </div>
    </div>

    <div class="card">
      <div class="card-body" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;text-align:center">
        <?php
          $steps = [
            'upload' => '1. Загрузка файла',
            'preview' => '2. Проверка данных',
            'done' => '3. Сохранение',
          ];
          $seenActive = false;
          foreach ($steps as $key => $label):
            $isActive = $step === $key;
            $isDone = !$seenActive && !$isActive;
            if ($isActive) $seenActive = true;
            $bg = $isActive ? 'var(--color-primary)' : ($isDone ? 'var(--color-success-highlight)' : 'var(--color-surface-2)');
            $color = $isActive ? '#fff' : ($isDone ? 'var(--color-success)' : 'var(--color-text-muted)');
        ?>
        <div style="padding:.625rem 1rem;border:1px solid var(--color-border);border-radius:var(--radius-md);background:<?= $bg ?>;color:<?= $color ?>;font-weight:600;font-size:.875rem">
          <?= _h($label) ?>
        </div>
        <?php endforeach ?>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>" style="margin-bottom:1rem">
      <?= _h($message) ?>
    </div>
    <?php endif ?>

    <?php if ($step === 'upload'): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Выберите файл РУПл</span></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <div style="margin-bottom:1.25rem">
            <label style="<?= $labelStyle ?>">Файл рабочего учебного плана</label>
            <input type="file" name="rupl_file" accept=".xlsx,.xls" required style="<?= $controlStyle ?>;width:100%">
            <p style="font-size:.8125rem;color:var(--color-text-muted);margin-top:.35rem">
              Поддерживаются варианты РУПл с разным расположением колонок, включая листы для базы 9 и 11 классов.
            </p>
          </div>
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Загрузить и проверить
          </button>
        </form>
      </div>
    </div>

    <?php elseif ($step === 'preview' && $parsed && !empty($parsed['ok'])): ?>
    <?php $p = $parsed['passport'] ?? []; $planSheets = $parsed['plan_sheets'] ?? []; ?>

    <div class="card">
      <div class="card-header"><span class="card-title">Данные из файла</span></div>
      <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
        <div>
          <p style="font-size:.8125rem;color:var(--color-text-muted)">Лист учебного плана</p>
          <p style="font-weight:600"><?= _h($parsed['selected_plan_sheet'] ?? '—') ?></p>
        </div>
        <div>
          <p style="font-size:.8125rem;color:var(--color-text-muted)">Код специальности</p>
          <p style="font-weight:600"><?= _h($p['specialty_code'] ?? '—') ?></p>
        </div>
        <div>
          <p style="font-size:.8125rem;color:var(--color-text-muted)">Специальность</p>
          <p style="font-weight:600"><?= _h($p['specialty_name'] ?? '—') ?></p>
        </div>
        <div>
          <p style="font-size:.8125rem;color:var(--color-text-muted)">Дисциплин</p>
          <p style="font-weight:700;font-size:1.25rem"><?= $totalSubjects ?></p>
        </div>
        <div>
          <p style="font-size:.8125rem;color:var(--color-text-muted)">Компетенций</p>
          <p style="font-weight:700;font-size:1.25rem"><?= count($parsed['competencies'] ?? []) ?></p>
        </div>
        <div style="grid-column:1/-1">
          <p style="font-size:.8125rem;color:var(--color-text-muted);margin-bottom:.35rem">Квалификации</p>
          <?php $quals = json_decode($p['qualification'] ?? '[]', true) ?: []; ?>
          <?php if ($quals): foreach ($quals as $q): ?>
            <span class="badge badge-blue" style="margin-right:.25rem;margin-bottom:.25rem"><?= _h($q) ?></span>
          <?php endforeach; else: ?>
            <span style="color:var(--color-text-faint)">—</span>
          <?php endif ?>
        </div>
      </div>
    </div>

    <?php if (!empty($parsed['warnings'])): ?>
    <div class="alert" style="background:var(--color-warning-highlight);color:var(--color-warning);border:1px solid color-mix(in srgb,var(--color-warning) 30%,transparent);margin-bottom:1rem">
      <?= _h('Предупреждения: ' . implode('; ', $parsed['warnings'])) ?>
    </div>
    <?php endif ?>

    <div class="card">
      <div class="card-header"><span class="card-title">Параметры импорта</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="confirm_import" value="1">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.25rem">
            <div>
              <label style="<?= $labelStyle ?>">Название плана</label>
              <input type="text" name="plan_name" value="<?= _h($_SESSION['rupl_name'] ?? '') ?>" required style="<?= $controlStyle ?>;width:100%">
            </div>
            <div>
              <label style="<?= $labelStyle ?>">Год поступления</label>
              <input type="number" name="enrollment_year" value="<?= date('Y') ?>" min="2000" max="2099" required style="<?= $controlStyle ?>;width:100%">
            </div>
            <div>
              <label style="<?= $labelStyle ?>">База образования</label>
              <select name="base_education" style="<?= $controlStyle ?>;width:100%">
                <option value="9 класс" selected>9 класс</option>
                <option value="11 класс">11 класс</option>
              </select>
            </div>
            <div>
              <label style="<?= $labelStyle ?>">Лист учебного плана</label>
              <select name="plan_sheet" style="<?= $controlStyle ?>;width:100%">
                <?php foreach ($planSheets as $sheetName): ?>
                <option value="<?= _h($sheetName) ?>" <?= ($sheetName === ($parsed['selected_plan_sheet'] ?? '')) ? 'selected' : '' ?>><?= _h($sheetName) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div style="grid-column:1/-1">
              <label style="<?= $labelStyle ?>">Специальность из справочника</label>
              <select name="speciality_id" style="<?= $controlStyle ?>;width:100%">
                <option value="">— Не выбрана —</option>
                <?php foreach ($specialties as $sp): ?>
                <option value="<?= (int)$sp['id'] ?>" <?= ($sp['name_ru'] === ($p['specialty_name'] ?? '')) ? 'selected' : '' ?>>
                  <?= _h($sp['name_ru']) ?>
                </option>
                <?php endforeach ?>
              </select>
            </div>
          </div>
          <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary">Подтвердить импорт</button>
            <a href="curriculum_import.php?reset=1" class="btn btn-outline">Загрузить другой файл</a>
            <a href="curriculum_import.php?cancel=1" class="btn btn-outline" style="color:var(--color-error);border-color:var(--color-error)" onclick="return confirm('Отменить импорт и удалить временный файл?')">Отменить импорт</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Предварительный просмотр учебного плана</span>
        <span style="font-size:.875rem;color:var(--color-text-muted)"><?= count($parsed['modules']) ?> строк</span>
      </div>
      <div style="overflow:auto;max-height:520px">
        <table style="width:100%;min-width:1350px;border-collapse:collapse">
          <thead>
            <tr>
              <?php foreach (['Индекс','Модуль/дисц.','Название','Экз.','Зач.','Контр. раб.','Кред.','Часы','Теор.','Практ.','Курс.р.','СРСП','СРС','Произв. обуч.','Индив.','С1','С2','С3','С4','С5','С6','С7','С8'] as $th): ?>
              <th style="<?= $thStyle ?><?= str_starts_with($th, 'С') ? ';color:var(--color-primary)' : '' ?>"><?= _h($th) ?></th>
              <?php endforeach ?>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($parsed['semester_meta'])): ?>
            <tr style="background:var(--color-surface-offset);font-weight:700">
              <td style="<?= $tdStyle ?>" colspan="15">Количество учебных недель</td>
              <?php for ($s = 1; $s <= 8; $s++): ?>
              <td style="<?= $tdStyle ?>;text-align:center;color:var(--color-primary)"><?= _h($parsed['semester_meta'][$s]['study_weeks'] ?? '') ?></td>
              <?php endfor ?>
            </tr>
            <tr style="background:var(--color-surface-offset);font-weight:700">
              <td style="<?= $tdStyle ?>" colspan="15">Итого в неделю</td>
              <?php for ($s = 1; $s <= 8; $s++): ?>
              <td style="<?= $tdStyle ?>;text-align:center;color:var(--color-primary)"><?= _h($parsed['semester_meta'][$s]['weekly_hours'] ?? '') ?></td>
              <?php endfor ?>
            </tr>
          <?php endif ?>
          <?php foreach ($parsed['modules'] as $m):
              $depth = _module_depth($m);
              $isSummary = !empty($m['is_summary']);
              $rowBg = $isSummary ? 'background:var(--color-surface-offset);font-weight:700' : '';
              $typeColor = _module_color((string)($m['module_type'] ?? ''));
          ?>
            <tr style="<?= $rowBg ?>">
              <td style="<?= $tdStyle ?>;white-space:nowrap;color:<?= $typeColor ?>;font-weight:600"><?= _h($m['index_code']) ?></td>
              <td style="<?= $tdStyle ?>;min-width:190px"><?= _h(mb_strimwidth((string)($m['component_name'] ?? ''), 0, 70, '…')) ?></td>
              <td style="<?= $tdStyle ?>;min-width:300px;padding-left:<?= 1 + $depth * 1.25 ?>rem"><?= _h(mb_strimwidth((string)$m['name'], 0, 100, '…')) ?></td>
              <td style="<?= $tdStyle ?>"><?= _h($m['exam_semester'] ?? '') ?></td>
              <td style="<?= $tdStyle ?>"><?= _h($m['credit_semester'] ?? '') ?></td>
              <td style="<?= $tdStyle ?>"><?= _h($m['control_work'] ?? '') ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['credits'] !== null ? _h($m['credits']) : '' ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['total_hours'] ?? '' ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['theory_hours'] ?? '' ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['practice_hours'] ?? '' ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['coursework_hours'] ?? '' ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['srsp_hours'] ?? '' ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['srs_hours'] ?? '' ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['production_hours'] ?? '' ?></td>
              <td style="<?= $tdStyle ?>"><?= $m['individual_hours'] ?? '' ?></td>
              <?php for ($s = 1; $s <= 8; $s++): ?>
              <td style="<?= $tdStyle ?>;text-align:center;color:<?= isset($m['distribution'][$s]) ? 'var(--color-primary)' : 'var(--color-text-faint)' ?>;font-weight:<?= isset($m['distribution'][$s]) ? '600' : '400' ?>">
                <?= $m['distribution'][$s] ?? '' ?>
              </td>
              <?php endfor ?>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php elseif ($step === 'done'): ?>
    <div class="card">
      <div class="card-body" style="text-align:center;padding:3rem">
        <div style="font-size:3rem;margin-bottom:1rem">✓</div>
        <h2 style="font-weight:700;margin-bottom:.5rem">Импорт завершён</h2>
        <p style="color:var(--color-text-muted);margin-bottom:1.5rem"><?= _h($message) ?></p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
          <a href="curricula.php" class="btn btn-primary">Перейти к списку планов</a>
          <a href="curriculum_import.php" class="btn btn-outline">Загрузить ещё</a>
        </div>
      </div>
    </div>
    <?php endif ?>

  </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
