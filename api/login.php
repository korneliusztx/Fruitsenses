<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_method('POST');
$data = read_json();
$identifier = trim(input_string($data, 'identifier'));
$password = input_string($data, 'password');
$role = input_string($data, 'role');
if ($identifier === '' || $password === '' || !in_array($role, ['user', 'farmer'], true)) {
    respond(422, ['success' => false, 'message' => 'Username/email, password, dan jenis akun wajib diisi.']);
}
if (strlen($identifier) > 190) {
    respond(422, ['success' => false, 'message' => 'Username/email terlalu panjang.']);
}
try {
    $db = database();
    $stmt = $db->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND role = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier, $role]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        respond(401, ['success' => false, 'message' => 'Username/email, password, atau jenis akun tidak sesuai.']);
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $update = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([$newHash, $user['id']]);
    }
    $token = issue_token($db, (string)$user['id']);
    respond(200, ['success' => true, 'message' => 'Login berhasil.',
        'token' => $token, 'user' => user_payload($user)]);
} catch (Throwable $e) {
    error_log('FruitSenses login error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memproses login.']);
}
