<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_method('POST');
try {
    $db = database();
    $tokenHash = hash('sha256', bearer_token());
    $stmt = $db->prepare('DELETE FROM auth_tokens WHERE token_hash = ?');
    $stmt->execute([$tokenHash]);
    respond(200, ['success' => true, 'message' => 'Logout berhasil.']);
} catch (Throwable $e) {
    error_log('FruitSenses logout error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal logout di server.']);
}
