<?php
declare(strict_types=1);
require __DIR__ . '/_chat_common.php';
require_method('GET');
$db = database();
$me = authenticated_user($db);
$myId = (int)$me['id'];
try {
    $sql = 'SELECT c.id, c.created_at, c.updated_at, c.user_low_id, c.user_high_id,
                   u.id AS peer_id, u.username AS peer_username, u.full_name AS peer_full_name,
                   u.farm_name AS peer_farm_name, u.role AS peer_role,
                   lm.id AS last_id, lm.sender_id AS last_sender_id, lm.body AS last_body,
                   lm.created_at AS last_created_at, lm.read_at AS last_read_at,
                   (SELECT COUNT(*) FROM chat_messages m
                    WHERE m.conversation_id = c.id AND m.sender_id <> ? AND m.read_at IS NULL) AS unread_count
            FROM chat_conversations c
            JOIN users u ON u.id = CASE WHEN c.user_low_id = ? THEN c.user_high_id ELSE c.user_low_id END
            LEFT JOIN chat_messages lm ON lm.id = (
                SELECT m2.id FROM chat_messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.id DESC LIMIT 1
            )
            WHERE c.user_low_id = ? OR c.user_high_id = ?
            ORDER BY COALESCE(lm.id,0) DESC, c.updated_at DESC LIMIT 100';
    $stmt = $db->prepare($sql);
    $stmt->execute([$myId, $myId, $myId, $myId]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $last = null;
        if ($row['last_id'] !== null) {
            $last = chat_message_payload([
                'id' => $row['last_id'],
                'conversation_id' => $row['id'],
                'sender_id' => $row['last_sender_id'],
                'body' => $row['last_body'],
                'created_at' => $row['last_created_at'],
                'read_at' => $row['last_read_at'],
            ], $myId);
        }
        $items[] = chat_conversation_payload($row, (int)$row['unread_count'], $last);
    }
    respond(200, ['success' => true, 'conversations' => $items]);
} catch (Throwable $e) {
    error_log('FruitSenses chat list: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memuat percakapan.']);
}
