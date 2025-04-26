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

// Получаем весь сырой запрос
$input = file_get_contents('php://input');

// Адрес OpenAI (фиксированный или передавать через заголовок если нужно)
$openai_url = 'https://api.openai.com/v1'; // Можно изменить базовый URL

// Заголовок Authorization: Bearer должен прийти в запросе
$authHeader = getallheaders()['Authorization'] ?? '';

if (empty($authHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Authorization header missing']);
    exit;
}

// Конечная точка OpenAI (например: /chat/completions или /threads/{id}/messages)
$path = $_GET['path'] ?? '';

if (empty($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing OpenAI API path']);
    exit;
}

$url = rtrim($openai_url, '/') . '/' . ltrim($path, '/');

// Инициируем запрос к OpenAI
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: ' . $authHeader
    ],
    CURLOPT_POSTFIELDS => $input
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Обработка ошибок curl
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl error', 'details' => $curlError]);
    exit;
}

// Отдаем ровно то, что вернул OpenAI
http_response_code($httpCode);
echo $response;
