<?php
declare(strict_types=1);
require __DIR__ . '/_chat_common.php';
require_method('POST');
$db = database();
$me = authenticated_user($db);
$data = read_json();
$conversationId = chat_positive_id($data['conversation_id'] ?? null, 'conversation_id');
$text = input_string($data, 'text');
$text = trim($text);
$length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : preg_match_all('/./us', $text);
if ($text === '' || $length === false || $length > 2000 || strlen($text) > 8000) {
    respond(422, ['success' => false, 'message' => 'Pesan harus berisi 1–2000 karakter (maksimal 8000 byte).']);
}
$clientId = input_string($data, 'client_message_id');
if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $clientId)) {
    respond(422, ['success' => false, 'message' => 'client_message_id tidak valid.']);
}
$myId = (int)$me['id'];
try {
    chat_conversation($db, $conversationId, $myId);
    $stmt = $db->prepare('INSERT INTO chat_messages(conversation_id,sender_id,body,client_message_id) '
        . 'VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
    $stmt->execute([$conversationId, $myId, $text, $clientId]);
    $messageId = (int)$db->lastInsertId();
    $db->prepare('UPDATE chat_conversations SET updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$conversationId]);
    $read = $db->prepare('SELECT id,conversation_id,sender_id,body,created_at,read_at FROM chat_messages WHERE id=? LIMIT 1');
    $read->execute([$messageId]);
    $row = $read->fetch();
    if (!$row) throw new RuntimeException('Message row missing');
    respond(200, ['success' => true, 'message' => chat_message_payload($row, $myId)]);
} catch (Throwable $e) {
    error_log('FruitSenses chat send: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Pesan gagal dikirim. Silakan coba lagi.']);
}
