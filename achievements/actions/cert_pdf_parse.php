<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$pdfPtype     = $_POST['pdf_ptype']        ?? 'student';
$eduStudentId = (int)($_POST['edu_student_id'] ?? 0);
$pdfUserId    = (int)($_POST['pdf_user_id']    ?? 0);
$redirectUrl  = SITE_URL . '/achievements.php?tab=certs';

if ($pdfPtype === 'student' && !$eduStudentId)
    redirectError('Не выбран студент.', $redirectUrl);
if ($pdfPtype === 'teacher' && !$pdfUserId)
    redirectError('Не выбран преподаватель.', $redirectUrl);
if (empty($_FILES['pdf_file']['name']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK)
    redirectError('Файл не выбран или ошибка загрузки.', $redirectUrl);

$file    = $_FILES['pdf_file'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf','jpg','jpeg','png'];

if (!in_array($ext, $allowed) || $file['size'] > 10*1024*1024)
    redirectError('Неверный формат или размер файла (макс. 10 МБ).', $redirectUrl);

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ownerId  = $pdfPtype === 'student' ? $eduStudentId : $pdfUserId;
$filename = 'cert_' . $ownerId . '_' . time() . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest))
    redirectError('Ошибка при сохранении файла.', $redirectUrl);

$pdo       = getPDO();
$ownerName = '';
try {
    if ($pdfPtype === 'student') {
        $s = $pdo->prepare("SELECT CONCAT(surname,' ',name,
            IF(patronymic!='' AND patronymic IS NOT NULL, CONCAT(' ',patronymic),''))
            AS full_name FROM edu_students WHERE id = ?");
        $s->execute([$eduStudentId]);
        $ownerName = $s->fetchColumn() ?: '';
    } else {
        $s = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $s->execute([$pdfUserId]);
        $ownerName = $s->fetchColumn() ?: '';
    }
} catch (Exception $e) {}

// ============================================================
// Промпт — общий для всех API
// ============================================================
function getPrompt(): string {
    return 'Ты специалист по анализу официальных документов (дипломы, грамоты, сертификаты, алғыс хат, марапаттама на русском и казахском языке).

Проанализируй этот документ и верни ТОЛЬКО JSON без пояснений и без блоков ```json:
{
    "title": "Тип документа: Диплом / Грамота / Сертификат / Благодарственное письмо / Алғыс хат / Марапаттама / Свидетельство — выбери подходящее",
    "recipient_name": "ФИО получателя — кому выдан документ, или пустая строка",
    "position": "Должность получателя если указана (Преподаватель, Студент и т.д.), иначе пустая строка",
    "recipient_org": "Организация получателя если указана, иначе пустая строка",
    "issuer": "Название организации которая выдала документ",
    "event_name": "Полное название конкурса, мероприятия или олимпиады, иначе пустая строка",
    "level": "Уровень: college / city / regional / national / international — выбери подходящий или пустая строка",
    "place": "Место или результат: 1 место / 2 место / 3 место / Победитель / Призёр / Лауреат / Участник / Организатор / Содействие / или точное значение из документа",
    "nomination": "Номинация, направление или компетенция если указана, иначе пустая строка",
    "doc_number": "Номер документа если указан (например ОЭБ 00116), иначе пустая строка",
    "doc_lang": "Язык документа: Казахский / Русский / Английский",
    "date": "Дата в формате YYYY-MM-DD или пустая строка",
    "curator_name": "ФИО куратора, наставника или жетекші если указан, иначе пустая строка",
    "notes": "Важные пометки на документе (печать Копия верна, особые отметки, подпись директора) или пустая строка"
}
Если данных нет — пустая строка. ФИО оставь точно как в оригинале.';
}

function parseResponse(string $text): array {
    $text   = preg_replace('/```json|```/i', '', $text);
    $parsed = json_decode(trim($text), true);

    if (!is_array($parsed))
        return [
            'title'=>'','recipient_name'=>'','position'=>'','recipient_org'=>'',
            'curator_name'=>'','issuer'=>'','event_name'=>'','level'=>'',
            'place'=>'','nomination'=>'','doc_number'=>'','doc_lang'=>'Казахский',
            'date'=>'','notes'=>'','error'=>'Не удалось распарсить: '.$text
        ];

    $date = trim($parsed['date'] ?? '');
    if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        if (preg_match('/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $date, $m))
            $date = $m[3].'-'.str_pad($m[2],2,'0',STR_PAD_LEFT).'-'.str_pad($m[1],2,'0',STR_PAD_LEFT);
        else
            $date = '';
    }

    return [
        'title'          => trim($parsed['title']          ?? ''),
        'recipient_name' => trim($parsed['recipient_name'] ?? ''),
        'position'       => trim($parsed['position']       ?? ''),
        'recipient_org'  => trim($parsed['recipient_org']  ?? ''),
        'curator_name'   => trim($parsed['curator_name']   ?? ''),
        'issuer'         => trim($parsed['issuer']         ?? ''),
        'event_name'     => trim($parsed['event_name']     ?? ''),
        'level'          => trim($parsed['level']          ?? ''),
        'place'          => trim($parsed['place']          ?? ''),
        'nomination'     => trim($parsed['nomination']     ?? ''),
        'doc_number'     => trim($parsed['doc_number']     ?? ''),
        'doc_lang'       => trim($parsed['doc_lang']       ?? 'Казахский'),
        'date'           => $date,
        'notes'          => trim($parsed['notes']          ?? ''),
        'error'          => '',
    ];
}

// ============================================================
// GEMINI API
// ============================================================
function parseWithGemini(string $filePath, string $ext): array {
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (!$apiKey)
        return ['error' => 'GEMINI_API_KEY не задан', '__skip' => true];

    $content = file_get_contents($filePath);
    if (!$content)
        return ['error' => 'Не удалось прочитать файл', '__skip' => true];

    $mimeType = match($ext) {
        'pdf'   => 'application/pdf',
        'png'   => 'image/png',
        default => 'image/jpeg',
    };

    $requestData = [
        'contents' => [[
            'parts' => [
                ['text' => getPrompt()],
                ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($content)]],
            ]
        ]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature'      => 0.1,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($requestData),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 429 = лимит исчерпан — переключаемся на OpenRouter
    if ($httpCode === 429)
        return ['error' => 'Gemini: лимит исчерпан', '__fallback' => true];

    if ($httpCode !== 200 || !$response)
        return ['error' => 'Gemini API: HTTP '.$httpCode, '__fallback' => true];

    $resp = json_decode($response, true);
    if (!empty($resp['error'])) {
        $msg = $resp['error']['message'] ?? 'Ошибка API';
        // Если ошибка квоты — переключаемся
        if (str_contains($msg, 'quota') || str_contains($msg, 'RESOURCE_EXHAUSTED'))
            return ['error' => $msg, '__fallback' => true];
        return ['error' => $msg];
    }

    $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return parseResponse($text);
}

// ============================================================
// OPENROUTER API (запасной)
// ============================================================
function parseWithOpenRouter(string $filePath, string $ext): array {
    $apiKey = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
    if (!$apiKey)
        return ['error' => 'OPENROUTER_API_KEY не задан в config.php'];

    $content = file_get_contents($filePath);
    if (!$content)
        return ['error' => 'Не удалось прочитать файл'];

    $mimeType = match($ext) {
        'pdf'   => 'application/pdf',
        'png'   => 'image/png',
        default => 'image/jpeg',
    };

    // OpenRouter не поддерживает PDF напрямую — конвертируем в JPG если есть ImageMagick
    $imagePath = $filePath;
    $tempImg   = null;
    if ($ext === 'pdf') {
        $tempImg = sys_get_temp_dir() . '/cert_or_' . time() . '.jpg';
        @exec('convert -density 150 ' . escapeshellarg($filePath.'[0]') . ' -quality 90 -flatten ' . escapeshellarg($tempImg) . ' 2>/dev/null');
        if (file_exists($tempImg) && filesize($tempImg) > 0) {
            $imagePath = $tempImg;
            $mimeType  = 'image/jpeg';
            $content   = file_get_contents($imagePath);
        }
    }

    $base64 = base64_encode($content);
    if ($tempImg) @unlink($tempImg);

    $requestData = [
        'model'    => 'google/gemini-flash-1.5',
        'messages' => [[
            'role'    => 'user',
            'content' => [
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'data:'.$mimeType.';base64,'.$base64],
                ],
                [
                    'type' => 'text',
                    'text' => getPrompt(),
                ],
            ],
        ]],
        'temperature' => 0.1,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . SITE_URL,
            'X-Title: СВГТК Портал',
        ],
        CURLOPT_POSTFIELDS     => json_encode($requestData),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response)
        return ['error' => 'OpenRouter API: HTTP '.$httpCode.' — '.$response];

    $resp = json_decode($response, true);
    if (!empty($resp['error']))
        return ['error' => 'OpenRouter: '.($resp['error']['message'] ?? 'Ошибка API')];

    $text = $resp['choices'][0]['message']['content'] ?? '';
    return parseResponse($text);
}

// ============================================================
// ОСНОВНАЯ ЛОГИКА — Gemini → OpenRouter
// ============================================================
$result = parseWithGemini($dest, $ext);

// Если Gemini вернул флаг fallback — пробуем OpenRouter
if (!empty($result['__fallback'])) {
    $geminiError = $result['error'];
    $result = parseWithOpenRouter($dest, $ext);
    // Если и OpenRouter не сработал — показываем обе ошибки
    if (!empty($result['error']))
        $result['error'] = 'Gemini: '.$geminiError.' | OpenRouter: '.$result['error'];
}

// Убираем служебные флаги
unset($result['__fallback'], $result['__skip']);

$_SESSION['cert_parse'] = [
    'edu_student_id' => $pdfPtype === 'student' ? $eduStudentId : 0,
    'user_id'        => $pdfPtype === 'teacher'  ? $pdfUserId   : 0,
    'owner_name'     => $ownerName,
    'ptype'          => $pdfPtype,
    'filename'       => $filename,
    'title'          => mb_strimwidth($result['title']          ?? '', 0, 200, '…'),
    'recipient_name' => mb_strimwidth($result['recipient_name'] ?? '', 0, 255, '…'),
    'position'       => mb_strimwidth($result['position']       ?? '', 0, 255, '…'),
    'recipient_org'  => mb_strimwidth($result['recipient_org']  ?? '', 0, 512, '…'),
    'curator_name'   => mb_strimwidth($result['curator_name']   ?? '', 0, 255, '…'),
    'issuer'         => mb_strimwidth($result['issuer']         ?? '', 0, 255, '…'),
    'event_name'     => $result['event_name']  ?? '',
    'level'          => $result['level']       ?? '',
    'place'          => $result['place']       ?? '',
    'nomination'     => mb_strimwidth($result['nomination'] ?? '', 0, 255, '…'),
    'doc_number'     => mb_strimwidth($result['doc_number'] ?? '', 0, 100, '…'),
    'doc_lang'       => $result['doc_lang']    ?? 'Казахский',
    'date'           => $result['date']        ?? '',
    'notes'          => $result['notes']       ?? '',
    'error'          => $result['error']       ?? '',
];

header('Location: ' . SITE_URL . '/cert_review.php');