<?php
// Разрешаем CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Получаем тело запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data['api_key']) || empty($data['endpoint'])) {
    echo json_encode(['error' => 'Missing required fields: api_key, endpoint']);
    exit;
}

$apiKey = $data['api_key'];
$endpoint = $data['endpoint'];
$payload = $data['payload'] ?? [];

// Подготовка запроса к OpenAI
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
];

// Отправка запроса на указанный endpoint OpenAI
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Проверка ошибок запроса
if ($response === false) {
    echo json_encode(['error' => 'Curl error', 'details' => $curlError]);
    exit;
}

// Возврат оригинального ответа OpenAI
http_response_code($httpCode);
echo $response;
