<?php
declare(strict_types=1);
require __DIR__ . '/_catalog_common.php';
require_method('GET');
try {
    $rows = catalog_all_location_rows(database());
    respond(200, [
        'success' => true,
        'locations' => array_map('catalog_location_payload', $rows),
    ]);
} catch (Throwable $e) {
    error_log('FruitSenses catalog locations: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memuat lokasi.']);
}
