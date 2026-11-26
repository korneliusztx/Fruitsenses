<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
function respond(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, [
        'success' => false,
        'error' => 'Gunakan metode POST.'
    ]);
}

if (
    !isset($_FILES['image']) ||
    $_FILES['image']['error'] !== UPLOAD_ERR_OK
) {
    respond(400, [
        'success' => false,
        'error' => 'Gambar tidak valid.'
    ]);
}

if (!extension_loaded('curl')) {
    respond(503, [
        'success' => false,
        'error' => 'Ekstensi cURL tidak tersedia.'
    ]);
}

$tmp = $_FILES['image']['tmp_name'];

$mime = mime_content_type($tmp);

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    respond(415, [
        'success' => false,
        'error' => 'Format gambar tidak didukung.'
    ]);
}

$ch = curl_init('http://127.0.0.1:5001/classify');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'image' => new CURLFile(
            $tmp,
            $mime,
            'fruit.jpg'
        )
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_FOLLOWLOCATION => false
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    respond(502, [
        'success' => false,
        'error' => 'Server Python tidak dapat dihubungi.'
    ]);
}

$data = json_decode($response, true);

if (!is_array($data)) {
    respond(502, [
        'success' => false,
        'error' => 'Python tidak mengembalikan JSON.'
    ]);
}

respond(
    $status >= 100 && $status <= 599 ? $status : 502,
    $data
);
