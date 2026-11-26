<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        respond(405, ['success' => false, 'message' => 'Metode tidak didukung.']);
    }
}

function read_json(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!str_starts_with(strtolower($type), 'application/json')) {
        respond(415, ['success' => false, 'message' => 'Gunakan Content-Type: application/json.']);
    }
    if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 16384) {
        respond(413, ['success' => false, 'message' => 'Body request terlalu besar.']);
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 16384) {
        respond(413, ['success' => false, 'message' => 'Body request tidak valid.']);
    }
    try {
        $decoded = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        respond(400, ['success' => false, 'message' => 'JSON tidak valid.']);
    }
    if (!$decoded instanceof stdClass) {
        respond(400, ['success' => false, 'message' => 'Objek JSON diperlukan.']);
    }
    return (array)$decoded;
}

function input_string(array $data, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $data)) return $default;
    if (!is_string($data[$key])) {
        respond(422, ['success' => false, 'message' => "Field $key harus teks."]);
    }
    return $data[$key];
}

function database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = require __DIR__ . '/db_config.php';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'], $config['port'], $config['database']);
    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    } catch (PDOException $e) {
        error_log('FruitSenses DB connection error: ' . $e->getMessage());
        respond(503, ['success' => false, 'message' => 'Database belum tersedia.']);
    }
}

function user_payload(array $row): array
{
    return [
        'id' => (string)$row['id'],
        'user_id' => (string)$row['id'],
        'username' => (string)$row['username'],
        'email' => $row['email'] === null ? '' : (string)$row['email'],
        'role' => (string)$row['role'],
        'fullName' => (string)$row['full_name'],
        'phone' => (string)$row['phone'],
        'farmName' => (string)$row['farm_name'],
        'farmLocation' => (string)$row['farm_location'],
        'createdAt' => (string)$row['created_at'],
    ];
}

function issue_token(PDO $db, string $userId): string
{
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $expiry = gmdate('Y-m-d H:i:s', time() + 30 * 86400);
    $stmt = $db->prepare('INSERT INTO auth_tokens(user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $hash, $expiry]);
    return $raw;
}

function bearer_token(): string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($auth === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strcasecmp((string)$key, 'Authorization') === 0) {
                $auth = (string)$value;
                break;
            }
        }
    }
    if (!preg_match('/^Bearer ([a-f0-9]{64})$/i', trim($auth), $m)) {
        respond(401, ['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    }
    return strtolower($m[1]);
}

function authenticated_user(PDO $db): array
{
    $hash = hash('sha256', bearer_token());
    $sql = 'SELECT u.* FROM auth_tokens t JOIN users u ON u.id = t.user_id '
         . 'WHERE t.token_hash = ? AND t.expires_at > UTC_TIMESTAMP() LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    if (!$user) {
        respond(401, ['success' => false, 'message' => 'Sesi telah habis. Silakan login lagi.']);
    }
    return $user;
}

function is_unique_violation(PDOException $e): bool
{
    return $e->getCode() === '23000';
}
