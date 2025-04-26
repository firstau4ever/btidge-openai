<?php
// Получаем путь из параметра
$path = $_GET['path'] ?? '';

if (!$path) {
    http_response_code(400);
    echo json_encode(["error" => "Missing path parameter"]);
    exit;
}

// Оригинальный адрес OpenAI
$url = "https://api.openai.com/v1/" . $path;

// Заголовки
$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[] = "$name: $value";
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
