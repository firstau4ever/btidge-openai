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
    empty($data['api_key'])
) {
    echo json_encode(['error' => 'Invalid request: missing model, messages, or api_key']);
    exit;
}

$apiKey = $data['api_key'];

// Инициализация запроса к OpenAI
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => $data['model'],
        'messages' => $data['messages'],
    ]),
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);
http_response_code($http_code);
echo $response;
