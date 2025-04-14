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

// Проверка необходимых полей
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

// Создание thread
$ch = curl_init('https://api.openai.com/v1/threads');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
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

// Добавляем сообщения в thread
foreach ($messages as $msg) {
    $ch = curl_init("https://api.openai.com/v1/threads/$threadId/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'role' => $msg['role'],
            'content' => $msg['content']
        ]),
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Запуск ассистента
$ch = curl_init("https://api.openai.com/v1/threads/$threadId/runs");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'assistant_id' => $assistantId
    ]),
]);
$runResponse = curl_exec($ch);
$runData = json_decode($runResponse, true);
$runId = $runData['id'] ?? null;
curl_close($ch);

if (!$runId) {
    echo json_encode(['error' => 'Run creation failed', 'debug' => $runData]);
    exit;
}

// Ожидаем завершения выполнения
$status = 'queued';
do {
    sleep(1);
    $ch = curl_init("https://api.openai.com/v1/threads/$threadId/runs/$runId");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey
        ]
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

// Получаем итоговое сообщение
$ch = curl_init("https://api.openai.com/v1/threads/$threadId/messages");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey
    ]
]);
$messagesResponse = curl_exec($ch);
curl_close($ch);

$messagesData = json_decode($messagesResponse, true);
$last = end($messagesData['data']);
$content = $last['content'][0]['text']['value'] ?? 'Ответ не получен';

echo json_encode(['content' => $content, 'raw' => $messagesData]);
