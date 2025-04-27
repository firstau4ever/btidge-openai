<?php
// Получаем путь из параметра
$path = $_GET['path'] ?? '';
$path = str_replace('clothes', 'threads', $path);

if (!$path) {
    http_response_code(400);
    echo json_encode(["error" => "Missing path parameter"]);
    exit;
}

// Оригинальный адрес OpenAI
$url = "https://api.openai.com/v1/" . ltrim($path, '/');

// Заголовки
$incoming_headers = getallheaders();
$headers = [];

// Проверяем наличие заголовка Accept
$has_accept = false;
foreach ($incoming_headers as $name => $value) {
    if (strtolower($name) === 'accept') {
        $has_accept = true;
    }
    $headers[] = "$name: $value";
}

// Если Accept не был передан — добавляем его
if (!$has_accept) {
    $headers[] = "Accept: application/json";
}

// Тело запроса
$body = file_get_contents('php://input');

// Инициализация CURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Выполняем запрос
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Возвращаем ответ
http_response_code($http_code);
echo $response;
?>
