<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-FruitSense-PHP-Probe: executed-v1');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uploaded = isset($_FILES['image']) && is_array($_FILES['image']);
$uploadError = $uploaded ? (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) : null;

http_response_code(200);
echo json_encode([
    'success' => true,
    'probe' => 'FRUITSENSE_PHP_EXECUTED_V1',
    'method' => $method,
    'request_uri' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH),
    'image_field_received' => $uploaded,
    'upload_error' => $uploadError,
    'json_from_php' => true,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
