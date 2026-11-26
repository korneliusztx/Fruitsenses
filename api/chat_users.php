<?php
declare(strict_types=1);
require __DIR__ . '/_chat_common.php';
require_method('GET');
$db = database();
$me = authenticated_user($db);
$q = $_GET['q'] ?? '';
$role = $_GET['role'] ?? '';
if (!is_string($q) || strlen($q) > 120 || !is_string($role) || !in_array($role, ['', 'user', 'farmer'], true)) {
    respond(422, ['success' => false, 'message' => 'Parameter pencarian tidak valid.']);
}
try {
    $sql = 'SELECT id, username, full_name, farm_name, role FROM users WHERE id <> ?';
    $args = [(int)$me['id']];
    if ($role !== '') {
        $sql .= ' AND role = ?';
        $args[] = $role;
    }
    $q = trim($q);
    if ($q !== '') {
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $sql .= " AND (username LIKE ? ESCAPE '!' OR full_name LIKE ? ESCAPE '!' OR farm_name LIKE ? ESCAPE '!')";
        array_push($args, $pattern, $pattern, $pattern);
    }
    $sql .= ' ORDER BY CASE WHEN role = \'farmer\' THEN 0 ELSE 1 END, username ASC LIMIT 60';
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    respond(200, ['success' => true, 'users' => array_map('chat_person', $stmt->fetchAll())]);
} catch (Throwable $e) {
    error_log('FruitSenses chat users: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memuat daftar pengguna.']);
}
