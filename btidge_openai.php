<?php

// Разрешаем CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Получаем тело запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Проверка обязательных полей
if (
    empty($data['model']) ||
    empty($data['messages']) ||
    empty($data['api_key']) ||
    empty($data['assistant_id'])
) {
    echo json_encode(['error' => 'Missing required fields: model, messages, api_key, assistant_id']);
    exit;
}

$apiKey = $data['api_key'];
$assistantId = $data['assistant_id'];
$messages = $data['messages'];

$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey,
    'OpenAI-Beta: assistants=v2'
];

// Создание thread
$ch = curl_init('https://api.openai.com/v1/threads');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode([]),
]);
$threadResponse = curl_exec($ch);
curl_close($ch);

$threadData = json_decode($threadResponse, true);
$threadId = $threadData['id'] ?? null;

if (!$threadId) {
    echo json_encode(['error' => 'Unable to create thread', 'debug' => $threadData]);
    exit;
}

// Добавление сообщений
foreach ($messages as $msg) {
    $ch = curl_init("https://api.openai.com/v1/threads/$threadId/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode([
            'role' => $msg['role'],
            'content' => $msg['content']
        ]),
    ]);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo json_encode(['error' => 'Message append failed', 'debug' => $res]);
        exit;
    }
}

// Запуск ассистента с dynamic_data
$ch = curl_init("https://api.openai.com/v1/threads/$threadId/runs");
$runPayload = [
    'assistant_id' => $assistantId,
];

if (!empty($data['dynamic_data'])) {
    $runPayload['additional_messages'] = [
        [
            'role' => 'system',
            'content' => 'dynamic_data: ' . json_encode($data['dynamic_data'], JSON_UNESCAPED_UNICODE)
        ]
    ];
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($runPayload)
]);
$runResponse = curl_exec($ch);
$runData = json_decode($runResponse, true);
$runId = $runData['id'] ?? null;
curl_close($ch);

if (!$runId) {
    echo json_encode(['error' => 'Run creation failed', 'debug' => $runData]);
    exit;
}

// Ожидание завершения run
$status = 'queued';
do {
    sleep(1);
    $ch = curl_init("https://api.openai.com/v1/threads/$threadId/runs/$runId");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $statusResponse = curl_exec($ch);
    curl_close($ch);

    $statusData = json_decode($statusResponse, true);
    $status = $statusData['status'] ?? 'failed';
} while ($status !== 'completed' && $status !== 'failed');

if ($status !== 'completed') {
    echo json_encode(['error' => 'Run did not complete', 'debug' => $statusData]);
    exit;
}

// Получение сообщений
$ch = curl_init("https://api.openai.com/v1/threads/$threadId/messages");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers
]);
$messagesResponse = curl_exec($ch);
curl_close($ch);

$messagesData = json_decode($messagesResponse, true);

// Последний валидный ответ от ассистента
$assistantMessages = array_filter($messagesData['data'], function ($msg) {
    return $msg['role'] === 'assistant'
        && isset($msg['content'][0]['text']['value'])
        && trim($msg['content'][0]['text']['value']) !== ''
        && trim($msg['content'][0]['text']['value']) !== 'Нет ответа от Assistant.';
});

$last = !empty($assistantMessages) ? array_values($assistantMessages)[0] : null;
$content = $last['content'][0]['text']['value'] ?? 'Ответ не получен';

// Удаление thread (необязательно)
$ch = curl_init("https://api.openai.com/v1/threads/$threadId");
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers
]);
curl_exec($ch);
curl_close($ch);

// Ответ
echo json_encode([
    'status' => 'ok',
    'content' => $content,
    'raw' => $messagesData
]);
