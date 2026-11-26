<?php
declare(strict_types=1);
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/_init.php';

function chat_positive_id(mixed $value, string $field): int
{
    if (!(is_int($value) || (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value)))) {
        respond(422, ['success' => false, 'message' => "$field tidak valid."]);
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) respond(422, ['success' => false, 'message' => "$field tidak valid."]);
    return (int)$id;
}

function chat_utc(mixed $datetime): ?string
{
    if (!$datetime) return null;
    return str_replace(' ', 'T', (string)$datetime) . 'Z';
}

function chat_person(array $row): array
{
    $name = trim((string)($row['full_name'] ?? ''));
    if (($row['role'] ?? '') === 'farmer' && trim((string)($row['farm_name'] ?? '')) !== '') {
        $name = trim((string)$row['farm_name']);
    } elseif ($name === '') {
        $name = (string)$row['username'];
    }
    return [
        'id' => (int)$row['id'],
        'name' => $name,
        'username' => (string)$row['username'],
        'role' => (string)$row['role'],
    ];
}

function chat_conversation(PDO $db, int $id, int $userId): array
{
    $sql = 'SELECT c.id, c.created_at, c.updated_at, c.user_low_id, c.user_high_id,
                   u.id AS peer_id, u.username AS peer_username, u.full_name AS peer_full_name,
                   u.farm_name AS peer_farm_name, u.role AS peer_role
            FROM chat_conversations c
            JOIN users u ON u.id = CASE WHEN c.user_low_id = ? THEN c.user_high_id ELSE c.user_low_id END
            WHERE c.id = ? AND (c.user_low_id = ? OR c.user_high_id = ?) LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $id, $userId, $userId]);
    $row = $stmt->fetch();
    if (!$row) respond(404, ['success' => false, 'message' => 'Percakapan tidak ditemukan atau bukan milik Anda.']);
    return $row;
}

function chat_conversation_payload(array $row, int $unread = 0, ?array $lastMessage = null): array
{
    $peer = chat_person([
        'id' => $row['peer_id'],
        'username' => $row['peer_username'],
        'full_name' => $row['peer_full_name'],
        'farm_name' => $row['peer_farm_name'],
        'role' => $row['peer_role'],
    ]);
    return [
        'id' => (int)$row['id'],
        'participant' => $peer,
        'last_message' => $lastMessage,
        'unread_count' => $unread,
        'created_at' => chat_utc($row['created_at']),
        'updated_at' => chat_utc($row['updated_at']),
    ];
}

function chat_message_payload(array $row, int $currentUserId): array
{
    return [
        'id' => (int)$row['id'],
        'conversation_id' => (int)$row['conversation_id'],
        'sender_id' => (int)$row['sender_id'],
        'is_me' => (int)$row['sender_id'] === $currentUserId,
        'text' => (string)$row['body'],
        'created_at' => chat_utc($row['created_at']),
        'read_at' => chat_utc($row['read_at']),
    ];
}
