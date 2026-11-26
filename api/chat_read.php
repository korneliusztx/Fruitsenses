<?php
declare(strict_types=1);
require __DIR__ . '/_chat_common.php';
require_method('POST');
$db = database();
$me = authenticated_user($db);
$data = read_json();
$conversationId = chat_positive_id($data['conversation_id'] ?? null, 'conversation_id');
$upTo = isset($data['up_to_id']) ? chat_positive_id($data['up_to_id'], 'up_to_id') : null;
try {
    chat_conversation($db, $conversationId, (int)$me['id']);
    $sql = 'UPDATE chat_messages SET read_at=UTC_TIMESTAMP() '
         . 'WHERE conversation_id=? AND sender_id <> ? AND read_at IS NULL';
    $params = [$conversationId, (int)$me['id']];
    if ($upTo !== null) { $sql .= ' AND id <= ?'; $params[] = $upTo; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    respond(200, ['success' => true, 'updated' => $stmt->rowCount()]);
} catch (Throwable $e) {
    error_log('FruitSenses chat read: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memperbarui status pesan.']);
}
