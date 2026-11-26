<?php
declare(strict_types=1);
require __DIR__ . '/_chat_common.php';
require_method('POST');
$db = database();
$me = authenticated_user($db);
$data = read_json();
$recipientId = chat_positive_id($data['recipient_id'] ?? null, 'recipient_id');
$meId = (int)$me['id'];
if ($recipientId === $meId) respond(422, ['success' => false, 'message' => 'Tidak bisa mengirim pesan ke akun sendiri.']);
try {
    $check = $db->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $check->execute([$recipientId]);
    if (!$check->fetch()) respond(404, ['success' => false, 'message' => 'Pengguna tujuan tidak ditemukan.']);
    $low = min($meId, $recipientId);
    $high = max($meId, $recipientId);
    $stmt = $db->prepare('INSERT INTO chat_conversations(user_low_id,user_high_id) VALUES (?,?) '
        . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $stmt->execute([$low, $high]);
    $id = (int)$db->lastInsertId();
    $row = chat_conversation($db, $id, $meId);
    respond(200, ['success' => true, 'conversation' => chat_conversation_payload($row)]);
} catch (Throwable $e) {
    error_log('FruitSenses chat start: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal membuka percakapan.']);
}
