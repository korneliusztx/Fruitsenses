<?php
declare(strict_types=1);
require __DIR__ . '/_catalog_common.php';
require_method('GET');
try {
    $db = database();
    $l = (int)$db->query('SELECT COUNT(*) FROM catalog_locations WHERE is_active = 1')->fetchColumn();
    $s = (int)$db->query('SELECT COUNT(*) FROM catalog_stores WHERE is_active = 1')->fetchColumn();
    $p = (int)$db->query('SELECT COUNT(*) FROM catalog_products WHERE is_active = 1')->fetchColumn();
    respond(200, [
        'success' => true,
        'service' => 'fruitsenses-catalog',
        'database' => 'connected',
        'locations' => $l,
        'stores' => $s,
        'products' => $p,
    ]);
} catch (Throwable $e) {
    error_log('FruitSenses catalog health: ' . $e->getMessage());
    respond(503, [
        'success' => false,
        'message' => 'Tabel katalog belum tersedia / tidak bisa dibaca.',
    ]);
}
