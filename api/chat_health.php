<?php
declare(strict_types=1);
require __DIR__ . '/_chat_common.php';
require_method('GET');
try {
    $db = database();
    $stmt = $db->query("SELECT COUNT(*) AS ready FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users','auth_tokens','chat_conversations','chat_messages')");
    $ready = (int)$stmt->fetchColumn() === 4;
    respond($ready ? 200 : 503, ['success' => $ready, 'service' => 'fruitsenses-chat', 'database' => $ready ? 'ready' : 'schema_incomplete']);
} catch (Throwable $e) {
    error_log('FruitSenses chat health: ' . $e->getMessage());
    respond(503, ['success' => false, 'service' => 'fruitsenses-chat', 'message' => 'Database tidak tersedia.']);
}
