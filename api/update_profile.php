<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_method('POST');
$data = read_json();
$username = trim(input_string($data, 'username'));
$email = strtolower(trim(input_string($data, 'email')));
if (!preg_match('/^[a-zA-Z0-9_.-]{4,80}$/', $username)) {
    respond(422, ['success' => false, 'message' => 'Username harus 4–80 karakter (huruf, angka, _, ., -).']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    respond(422, ['success' => false, 'message' => 'Format email tidak valid.']);
}
try {
    $db = database();
    $user = authenticated_user($db);
    $stmt = $db->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
    $stmt->execute([$username, $email, $user['id']]);
    $query = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $query->execute([$user['id']]);
    $updated = $query->fetch();
    respond(200, ['success' => true, 'message' => 'Profil diperbarui.', 'user' => user_payload($updated)]);
} catch (PDOException $e) {
    if (is_unique_violation($e)) {
        respond(409, ['success' => false, 'message' => 'Username atau email sudah digunakan.']);
    }
    error_log('FruitSenses profile error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memperbarui profil.']);
} catch (Throwable $e) {
    error_log('FruitSenses profile error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memperbarui profil.']);
}
