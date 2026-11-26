<?php
declare(strict_types=1);
require __DIR__ . '/_scan_history_common.php';
require_method('GET');
bearer_token();
$db = database();
$user = authenticated_user($db);
$userId = (string)$user['id'];
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
$offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT);
$limit = ($limit !== false && $limit !== null) ? max(1, min(100, $limit)) : 30;
$offset = ($offset !== false && $offset !== null) ? max(0, $offset) : 0;
try {
    $countStmt = $db->prepare('SELECT COUNT(*) FROM scan_histories WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetchColumn();
    $stmt = $db->prepare('SELECT * FROM scan_histories WHERE user_id = :uid ORDER BY id DESC LIMIT :lim OFFSET :off');
    $stmt->bindValue(':uid', $userId);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    respond(200, [
        'success' => true,
        'total' => $total,
        'has_more' => ($offset + count($rows)) < $total,
        'histories' => array_map('scan_history_payload', $rows),
    ]);
} catch (Throwable $e) {
    error_log('[FruitSenses] scan_history_list: ' . $e->getMessage());
    scan_fail(500, 'Gagal memuat riwayat scan.');
}
