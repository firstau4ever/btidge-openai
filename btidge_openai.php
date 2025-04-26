<?php
// Разрешаем CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Получаем все заголовки запроса
$requestHeaders = getallheaders();

// Проверка наличия Authorization
if (empty($requestHeaders['Authorization'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Authorization header missing']);
    exit;
}

// Собираем все заголовки для передачи на OpenAI
$forwardHeaders = [];
foreach ($requestHeaders as $key => $value) {
    $forwardHeaders[] = $key . ': ' . $value;
}

// Получаем тело запроса
$input = file_get_contents('php://input');

// Конечная точка OpenAI (например: ?path=chat/completions)
$openaiBaseUrl = 'https://api.openai.com/v1/';
$path = $_GET['path'] ?? '';

if (empty($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing OpenAI API path']);
    exit;
}

$url = rtrim($openaiBaseUrl, '/') . '/' . ltrim($path, '/');

// Отправка запроса
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $forwardHeaders,
    CURLOPT_POSTFIELDS => $input,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Ошибка curl
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl error', 'details' => $curlError]);
    exit;
}

// Ответ от OpenAI
http_response_code($httpCode);
echo $response;
