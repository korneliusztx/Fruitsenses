<?php
declare(strict_types=1);
require __DIR__ . '/_chat_common.php';
require_method('GET');
$db = database();
$me = authenticated_user($db);
$conversationId = chat_positive_id($_GET['conversation_id'] ?? null, 'conversation_id');
$after = isset($_GET['after_id']) ? chat_positive_id($_GET['after_id'], 'after_id') : null;
$before = isset($_GET['before_id']) ? chat_positive_id($_GET['before_id'], 'before_id') : null;
if ($after !== null && $before !== null) {
    respond(422, ['success' => false, 'message' => 'Gunakan after_id atau before_id, bukan keduanya.']);
}
try {
    chat_conversation($db, $conversationId, (int)$me['id']);
    $sql = 'SELECT id,conversation_id,sender_id,body,created_at,read_at FROM chat_messages WHERE conversation_id = ?';
    $args = [$conversationId];
    if ($after !== null) { $sql .= ' AND id > ?'; $args[] = $after; }
    if ($before !== null) { $sql .= ' AND id < ?'; $args[] = $before; }
    $sql .= $after !== null ? ' ORDER BY id ASC' : ' ORDER BY id DESC';
    $sql .= ' LIMIT 51';
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
    $hasMore = count($rows) > 50;
    if ($hasMore) array_pop($rows);
    if ($after === null) $rows = array_reverse($rows);
    respond(200, [
        'success' => true,
        'messages' => array_map(fn(array $row): array => chat_message_payload($row, (int)$me['id']), $rows),
        'has_more' => $hasMore,
    ]);
} catch (Throwable $e) {
    error_log('FruitSenses chat messages: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memuat pesan.']);
}
