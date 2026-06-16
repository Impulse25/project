<?php
function getPrompt(): string {
    return 'Ты специалист по анализу официальных документов системы образования Казахстана.

Проанализируй этот документ (сертификат, диплом, алғыс хат, грамота) и верни ТОЛЬКО JSON без пояснений и без блоков ```json:
{
    "title": "Тип: Диплом / Грамота / Сертификат / Алғыс хат / Марапаттама / Свидетельство",
    "recipient_name": "ФИО получателя — кому выдан, или пустая строка",
    "position": "Должность получателя если указана, иначе пустая строка",
    "recipient_org": "Организация получателя если указана, иначе пустая строка",
    "issuer": "Название организации которая выдала документ",
    "event_name": "Полное название конкурса или мероприятия, иначе пустая строка",
    "level": "college / city / regional / national / international — выбери подходящий",
    "place": "1 место / 2 место / 3 место / Победитель / Призёр / Участник / Организатор",
    "nomination": "Номинация или компетенция если указана, иначе пустая строка",
    "doc_number": "Номер документа если указан, иначе пустая строка",
    "doc_lang": "Казахский / Русский / Английский",
    "date": "Дата в формате YYYY-MM-DD или пустая строка",
    "curator_name": "ФИО куратора или наставника если указан, иначе пустая строка",
    "notes": "Важные пометки или пустая строка"
}
Если данных нет — пустая строка. ФИО оставь точно как в оригинале.';
}

function parseResponse(string $text): array {
    $text   = preg_replace('/```json|```/i', '', $text);
    $parsed = json_decode(trim($text), true);

    if (!is_array($parsed)) {
        return [
            'title'=>'','recipient_name'=>'','position'=>'','recipient_org'=>'',
            'curator_name'=>'','issuer'=>'','event_name'=>'','level'=>'',
            'place'=>'','nomination'=>'','doc_number'=>'','doc_lang'=>'Казахский',
            'date'=>'','notes'=>'','error'=>'Не удалось распарсить ответ AI'
        ];
    }

    // Конвертация даты DD.MM.YYYY → YYYY-MM-DD
    $date = trim($parsed['date'] ?? '');
    if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        if (preg_match('/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $date, $m)) {
            $date = $m[3].'-'.str_pad($m[2],2,'0',STR_PAD_LEFT).'-'.str_pad($m[1],2,'0',STR_PAD_LEFT);
        } else {
            $date = '';
        }
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

function parseWithGemini(string $filePath, string $ext): array {
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';

    // Проверяем что ключ настоящий (должен начинаться с AIza)
    if (!$apiKey || $apiKey === 'YOUR_KEY_HERE' || !str_starts_with($apiKey, 'AIza')) {
        return ['error' => 'Gemini ключ не настроен', '__skip' => true];
    }

    $content = file_get_contents($filePath);
    if (!$content) {
        return ['error' => 'Не удалось прочитать файл', '__skip' => true];
    }

    $mimeType = match($ext) {
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        default => 'image/jpeg'
    };

    $requestData = [
        'contents' => [[
            'parts' => [
                ['text' => getPrompt()],
                ['inline_data' => [
                    'mime_type' => $mimeType,
                    'data'      => base64_encode($content)
                ]],
            ]
        ]],
        'generationConfig' => [
            'temperature'     => 0.1,
            'responseMimeType'=> 'application/json',
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;

    $ch = curl_init($url);
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

    // 429 = лимит — переключаемся на OpenRouter
    if ($httpCode === 429) {
        return ['error' => 'Gemini: лимит исчерпан', '__fallback' => true];
    }

    if ($httpCode !== 200 || !$response) {
        return ['error' => 'Gemini API: HTTP ' . $httpCode, '__fallback' => true];
    }

    $resp = json_decode($response, true);

    if (!empty($resp['error'])) {
        $msg = $resp['error']['message'] ?? 'Ошибка API';
        if (str_contains($msg, 'quota') || str_contains($msg, 'RESOURCE_EXHAUSTED')) {
            return ['error' => $msg, '__fallback' => true];
        }
        return ['error' => $msg];
    }

    $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return parseResponse($text);
}

function parseWithOpenRouter(string $filePath, string $ext): array {
    $apiKey = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';

    if (!$apiKey) {
        return ['error' => 'OpenRouter ключ не настроен в config.php'];
    }

    $content = file_get_contents($filePath);
    if (!$content) {
        return ['error' => 'Не удалось прочитать файл'];
    }

    // PDF конвертируем в JPG через ImageMagick если доступен
    $imagePath = $filePath;
    $mimeType  = match($ext) {
        'png'  => 'image/png',
        'webp' => 'image/webp',
        default => 'image/jpeg'
    };

    if ($ext === 'pdf') {
        $tempImg = sys_get_temp_dir() . '/cert_or_' . time() . '.jpg';
        @exec('convert -density 150 ' . escapeshellarg($filePath.'[0]') . ' -quality 90 -flatten ' . escapeshellarg($tempImg) . ' 2>/dev/null');
        if (file_exists($tempImg) && filesize($tempImg) > 0) {
            $imagePath = $tempImg;
            $mimeType  = 'image/jpeg';
            $content   = file_get_contents($imagePath);
        }
    }

    $requestData = [
        'model'    => 'google/gemini-flash-1.5',
        'messages' => [[
            'role'    => 'user',
            'content' => [
                [
                    'type'      => 'image_url',
                    'image_url' => ['url' => 'data:' . $mimeType . ';base64,' . base64_encode($content)]
                ],
                [
                    'type' => 'text',
                    'text' => getPrompt()
                ],
            ]
        ]],
        'temperature' => 0.1,
    ];

    if (isset($tempImg)) @unlink($tempImg);

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

    if ($httpCode !== 200 || !$response) {
        return ['error' => 'OpenRouter API: HTTP ' . $httpCode];
    }

    $resp = json_decode($response, true);
    if (!empty($resp['error'])) {
        return ['error' => 'OpenRouter: ' . ($resp['error']['message'] ?? 'Ошибка API')];
    }

    $text = $resp['choices'][0]['message']['content'] ?? '';
    return parseResponse($text);
}