<?php
require_once 'vendor/autoload.php'; // если используете Composer

use Medoo\Medoo;

$database = new Medoo([
    'type' => 'mysql',
    'host' => 'localhost',
    'database' => 'autoprovision',
    'username' => 'root',
    'password' => '123456',
    'charset' => 'utf8mb4'
]);



function getFullRequest() {
    // Метод запроса (GET, POST, etc.)
    $method = $_SERVER['REQUEST_METHOD'];

    // URI запроса
    $uri = $_SERVER['REQUEST_URI'];

    // Протокол
    $protocol = $_SERVER['SERVER_PROTOCOL'];

    // Заголовки
    $headers = getallheaders();

    // GET параметры
    $getParams = $_GET;

    // POST данные
    $postData = file_get_contents('php://input');

    // Cookies
    $cookies = $_COOKIE;

    // Формируем строку запроса
    $request = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $method,
        'uri' => $uri,
        'protocol' => $protocol,
        'headers' => $headers,
        'get' => $getParams,
        'post_raw' => $postData,
        'post_array' => $_POST,
        'cookies' => $cookies,
        'files' => $_FILES,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];

    return $request;
}