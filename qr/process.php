<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

// Тот же уровень доступа, что и у qr/index.php — иначе любой залогиненный
// пользователь (teacher/technician) может слать сканирования напрямую,
// минуя страницу журнала, доступную только admin/director.
if (!in_array($_SESSION['role'] ?? '', ['admin', 'director'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// ─────────────────────────────────────────────────────────────
// 1. Базовые входные данные
// ─────────────────────────────────────────────────────────────
$iin    = trim($_POST['iin']    ?? '');
$action = trim($_POST['action'] ?? '');

if (!preg_match('/^\d{12}$/', $iin)) {
    echo json_encode(['success' => false, 'message' => 'Неверный формат ИИН']);
    exit;
}

if (!in_array($action, ['entry', 'exit'])) {
    echo json_encode(['success' => false, 'message' => 'Неверное действие']);
    exit;
}

// ─────────────────────────────────────────────────────────────
// 2. Проверка геолокации на сервере
// ─────────────────────────────────────────────────────────────

$campuses = [
    'main'    => ['lat' => 49.802336515317165, 'lng' => 72.82927229999896],
    'sport'   => ['lat' => 49.793952329641684, 'lng' => 72.81703731562591],
    'school'  => ['lat' => 49.79409883139033, 'lng' => 72.81945789003095],
    'foreign' => ['lat' => 49.79415538925407, 'lng' => 72.81880781723116],
];

$campus = trim($_POST['campus'] ?? '');
$geoLat = (float)($_POST['geo_lat'] ?? 0);
$geoLng = (float)($_POST['geo_lng'] ?? 0);

if (!isset($campuses[$campus])) {
    echo json_encode(['success' => false, 'message' => 'Корпус не выбран']);
    exit;
}

if ($geoLat == 0 || $geoLng == 0) {
    echo json_encode(['success' => false, 'message' => 'Координаты не получены. Разрешите геолокацию.']);
    exit;
}

function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat/2) * sin($dLat/2)
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
          * sin($dLng/2) * sin($dLng/2);
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$dist = haversineMeters($geoLat, $geoLng, $campuses[$campus]['lat'], $campuses[$campus]['lng']);

if ($dist > 100) {
    echo json_encode(['success' => false, 'message' => 'Вы находитесь вне зоны выбранного корпуса.']);
    exit;
}

// ─────────────────────────────────────────────────────────────
// 3. Сбор информации об устройстве
// ─────────────────────────────────────────────────────────────

// --- Сеть ---
$device_ip = $_SERVER['HTTP_X_FORWARDED_FOR']
           ?? $_SERVER['HTTP_X_REAL_IP']
           ?? $_SERVER['REMOTE_ADDR']
           ?? null;
// Берём только первый IP если их несколько (proxy chain)
if ($device_ip) {
    $device_ip = trim(explode(',', $device_ip)[0]);
}

// --- User-Agent (браузер, ОС, тип устройства) ---
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

function parseUserAgent(string $ua): array {
    $browser_name    = 'Unknown';
    $browser_version = '';
    $os_name         = 'Unknown';
    $device_type     = 'unknown';

    // ── Браузер ──────────────────────────────────────────────
    $browsers = [
        'Edg'     => 'Edge',
        'OPR'     => 'Opera',
        'Opera'   => 'Opera',
        'YaBrowser' => 'Yandex Browser',
        'SamsungBrowser' => 'Samsung Browser',
        'UCBrowser' => 'UC Browser',
        'Chrome'  => 'Chrome',
        'Safari'  => 'Safari',
        'Firefox' => 'Firefox',
        'MSIE'    => 'Internet Explorer',
        'Trident' => 'Internet Explorer',
    ];
    foreach ($browsers as $pattern => $name) {
        if (stripos($ua, $pattern) !== false) {
            $browser_name = $name;
            // Версия
            if (preg_match('/' . preg_quote($pattern, '/') . '[\/\s]?([\d\.]+)/i', $ua, $m)) {
                $browser_version = $m[1];
            }
            break;
        }
    }

    // ── ОС ───────────────────────────────────────────────────
    $systems = [
        'Windows NT 10' => 'Windows 10/11',
        'Windows NT 6.3'=> 'Windows 8.1',
        'Windows NT 6.2'=> 'Windows 8',
        'Windows NT 6.1'=> 'Windows 7',
        'Windows'       => 'Windows',
        'Mac OS X'      => 'macOS',
        'Android'       => 'Android',
        'iPhone'        => 'iOS (iPhone)',
        'iPad'          => 'iOS (iPad)',
        'Linux'         => 'Linux',
        'CrOS'          => 'Chrome OS',
    ];
    foreach ($systems as $pattern => $name) {
        if (stripos($ua, $pattern) !== false) {
            $os_name = $name;
            break;
        }
    }

    // ── Тип устройства ───────────────────────────────────────
    if (preg_match('/tablet|ipad|kindle|playbook|silk/i', $ua)) {
        $device_type = 'tablet';
    } elseif (preg_match('/mobile|android|iphone|ipod|blackberry|phone|opera mini/i', $ua)) {
        $device_type = 'mobile';
    } elseif ($ua) {
        $device_type = 'desktop';
    }

    return compact('browser_name', 'browser_version', 'os_name', 'device_type');
}

$ua_parsed = parseUserAgent($user_agent);

// --- Клиентские данные (из JS через POST) ---
$screen_width  = filter_var($_POST['screen_w']   ?? null, FILTER_VALIDATE_INT) ?: null;
$screen_height = filter_var($_POST['screen_h']   ?? null, FILTER_VALIDATE_INT) ?: null;
$timezone      = substr(trim($_POST['timezone']  ?? ''), 0, 80) ?: null;
$language      = substr(trim($_POST['language']  ?? ''), 0, 20) ?: null;
$platform      = substr(trim($_POST['platform']  ?? ''), 0, 100) ?: null;

// ─────────────────────────────────────────────────────────────
// 4. Device Fingerprint — хэш без личных данных
//    Комбинация: UA + разрешение + timezone + язык + платформа
//    НЕ содержит IP → корректно работает при общем Wi-Fi
// ─────────────────────────────────────────────────────────────
$fingerprint_source = implode('|', [
    $user_agent,
    $screen_width  ?? '',
    $screen_height ?? '',
    $timezone      ?? '',
    $language      ?? '',
    $platform      ?? '',
]);
$device_fingerprint = hash('sha256', $fingerprint_source);

// ─────────────────────────────────────────────────────────────
// 5. ЗАЩИТА: один fingerprint — один студент за 60 минут
//    (заменяет старую проверку по IP)
// ─────────────────────────────────────────────────────────────
if ($action === 'entry') {
    $stmt_fp = $pdo->prepare("
        SELECT COUNT(DISTINCT iin) AS cnt FROM qr_attendance
        WHERE device_fingerprint = ?
          AND action = 'entry'
          AND TIMESTAMPDIFF(MINUTE, action_time, NOW()) <= 60
    ");
    $stmt_fp->execute([$device_fingerprint]);
    $fp_count = $stmt_fp->fetch()['cnt'];

    if ($fp_count >= 1) {
        echo json_encode([
            'success' => false,
            'message' => 'С этого устройства уже отмечен студент. Попробуйте через час.'
        ]);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
// 6. ЗАЩИТА: логика вход/выход для конкретного ИИН
// ─────────────────────────────────────────────────────────────
$stmt_last = $pdo->prepare("
    SELECT action, action_time FROM qr_attendance
    WHERE iin = ?
    ORDER BY action_time DESC LIMIT 1
");
$stmt_last->execute([$iin]);
$last = $stmt_last->fetch();

if ($action === 'entry') {
    if ($last && $last['action'] === 'entry') {
        echo json_encode([
            'success' => false,
            'message' => 'Вы уже отметили вход. Сначала отметьте выход.'
        ]);
        exit;
    }

    if ($last && $last['action'] === 'exit') {
        $time_diff = $pdo->prepare("SELECT TIMESTAMPDIFF(MINUTE, ?, NOW()) AS diff_min");
        $time_diff->execute([$last['action_time']]);
        $diff_min = $time_diff->fetch()['diff_min'];

        if ($diff_min < 60) {
            $wait = ceil(60 - $diff_min);
            echo json_encode([
                'success' => false,
                'message' => 'Повторный вход возможен через ' . $wait . ' мин.'
            ]);
            exit;
        }
    }
}

if ($action === 'exit') {
    if (!$last || $last['action'] === 'exit') {
        echo json_encode([
            'success' => false,
            'message' => 'Сначала отметьте вход.'
        ]);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
// 7. Поиск студента в БД
// ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.surname, s.name, s.patronymic, s.group_id, g.name AS group_name
    FROM edu_students s
    LEFT JOIN edu_groups g ON g.id = s.group_id
    WHERE s.iin = ?
");
$stmt->execute([$iin]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'ИИН не найден в базе']);
    exit;
}

// ─────────────────────────────────────────────────────────────
// 8. Запись события в qr_attendance
// ─────────────────────────────────────────────────────────────
$stmt_ins = $pdo->prepare("
    INSERT INTO qr_attendance
      (iin, surname, name, patronymic, group_id, action,
       device_ip, user_agent, browser_name, browser_version,
       os_name, device_type,
       screen_width, screen_height, timezone, language, platform,
       device_fingerprint)
    VALUES
      (?, ?, ?, ?, ?, ?,
       ?, ?, ?, ?,
       ?, ?,
       ?, ?, ?, ?, ?,
       ?)
");
$stmt_ins->execute([
    $iin,
    $student['surname'],
    $student['name'],
    $student['patronymic'],
    $student['group_id'],
    $action,

    $device_ip,
    $user_agent,
    $ua_parsed['browser_name'],
    $ua_parsed['browser_version'],
    $ua_parsed['os_name'],
    $ua_parsed['device_type'],

    $screen_width,
    $screen_height,
    $timezone,
    $language,
    $platform,

    $device_fingerprint,
]);

// ─────────────────────────────────────────────────────────────
// 9. Ответ
// ─────────────────────────────────────────────────────────────
$time_row = $pdo->query("
    SELECT DATE_FORMAT(NOW(), '%H:%i') AS t,
           DATE_FORMAT(NOW(), '%d.%m.%Y') AS d
")->fetch();

$full = trim($student['surname'] . ' ' . $student['name'] . ' ' . $student['patronymic']);

echo json_encode([
    'success'    => true,
    'student'    => $full,
    'group_name' => $student['group_name'] ?? 'Группа #' . $student['group_id'],
    'action'     => $action,
    'time'       => $time_row['t'],
    'date'       => $time_row['d'],
]);
?>
