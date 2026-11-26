<?php
declare(strict_types=1);
require __DIR__ . '/_chat_common.php';
require_method('GET');
$db = database();
$me = authenticated_user($db);
$storeId = chat_positive_id($_GET['store_id'] ?? null, 'store_id');
try {
    $stmt = $db->prepare('SELECT s.owner_user_id, u.id, u.username, u.full_name, u.farm_name, u.role '
        . 'FROM catalog_stores s LEFT JOIN users u ON u.id = s.owner_user_id '
        . 'WHERE s.id = ? AND s.is_active = 1 LIMIT 1');
    $stmt->execute([$storeId]);
    $row = $stmt->fetch();
    if (!$row) respond(404, ['success' => false, 'message' => 'Toko tidak ditemukan.']);
    if ($row['id'] === null || $row['role'] !== 'farmer') {
        respond(404, ['success' => false, 'message' => 'Toko belum terhubung dengan akun Farmer. Hubungi admin untuk menghubungkan pemilik toko.']);
    }
    if ((int)$row['id'] === (int)$me['id']) {
        respond(422, ['success' => false, 'message' => 'Tidak bisa chat dengan toko milik akun sendiri.']);
    }
    respond(200, ['success' => true, 'recipient' => chat_person($row)]);
} catch (Throwable $e) {
    error_log('FruitSenses chat store owner: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal mencari akun pemilik toko.']);
}
