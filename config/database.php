<?php

require_once __DIR__ . '/../bootstrap/env.php';

loadEnv(__DIR__ . '/../.env');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = env('DB_HOST', '127.0.0.1');
$port = (int) env('DB_PORT', '3306');
$name = env('DB_NAME', '');
$user = env('DB_USER', '');
$pass = env('DB_PASS', '');

if ($name === '' || $user === '') {
    throw new RuntimeException('DB_NAME / DB_USER 未配置');
}

$conn = new mysqli($host, $user, $pass, $name, $port);
$conn->set_charset('utf8mb4');

return $conn;
