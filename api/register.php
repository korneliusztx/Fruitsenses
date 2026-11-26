<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_method('POST');
$data = read_json();

$username = trim(input_string($data, 'username'));
$email = strtolower(trim(input_string($data, 'email')));
$password = input_string($data, 'password');
$role = input_string($data, 'role');
$fullName = trim(input_string($data, 'fullName', $username));
$phone = trim(input_string($data, 'phone'));
$farmName = trim(input_string($data, 'farmName'));
$farmLocation = trim(input_string($data, 'farmLocation'));

if (!preg_match('/^[a-zA-Z0-9_.-]{4,80}$/', $username)) {
    respond(422, ['success' => false, 'message' => 'Username 4–80 karakter: huruf, angka, titik, garis bawah atau minus.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    respond(422, ['success' => false, 'message' => 'Email tidak valid.']);
}
if (strlen($password) < 8 || strlen($password) > 72) {
    respond(422, ['success' => false, 'message' => 'Password harus 8–72 karakter (maksimum 72 byte).']);
}
if (!in_array($role, ['user', 'farmer'], true)) {
    respond(422, ['success' => false, 'message' => 'Jenis akun harus user atau farmer.']);
}
if (strlen($fullName) > 150 || strlen($phone) > 25 || strlen($farmName) > 150 || strlen($farmLocation) > 255) {
    respond(422, ['success' => false, 'message' => 'Data profil terlalu panjang.']);
}
if ($role !== 'farmer') {
    $farmName = '';
    $farmLocation = '';
}
try {
    $db = database();
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->beginTransaction();
    $stmt = $db->prepare('INSERT INTO users '
        . '(username, email, password_hash, role, full_name, phone, farm_name, farm_location) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $email, $hash, $role, $fullName, $phone, $farmName, $farmLocation]);
    $newId = (string)$db->lastInsertId();
    $token = issue_token($db, $newId);
    $query = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $query->execute([$newId]);
    $user = $query->fetch();
    $db->commit();
    respond(201, ['success' => true, 'message' => 'Registrasi berhasil.',
        'token' => $token, 'user' => user_payload($user)]);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    if (is_unique_violation($e)) {
        respond(409, ['success' => false, 'message' => 'Username atau email sudah digunakan.']);
    }
    error_log('FruitSenses register error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Registrasi gagal di server.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('FruitSenses register error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Registrasi gagal di server.']);
}
