<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';

function scan_storage_dir(): string
{
    $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'fruitsenses_scan_private';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan foto tidak bisa dibuat.');
    }
    return $directory;
}

function scan_history_payload(array $r): array
{
    return [
        'id' => (string)$r['id'],
        'prediction' => (string)$r['prediction'],
        'quality' => $r['quality'] === null ? null : (string)$r['quality'],
        'confidence' => (float)$r['confidence'],
        'probability_raw' => $r['probability_raw'] === null ? null : (float)$r['probability_raw'],
        'image_url' => '/api/scan_history_image.php?id=' . rawurlencode((string)$r['id']),
        'created_at' => str_replace(' ', 'T', (string)$r['created_at']) . 'Z',
    ];
}

function scan_history_by_uuid(PDO $db, string $userId, string $scanUuid): ?array
{
    $stmt = $db->prepare('SELECT * FROM scan_histories WHERE user_id = ? AND scan_uuid = ? LIMIT 1');
    $stmt->execute([$userId, $scanUuid]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function scan_fail(int $status, string $message): never
{
    respond($status, ['success' => false, 'message' => $message]);
}
