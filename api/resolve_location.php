<?php
declare(strict_types=1);
require __DIR__ . '/_catalog_common.php';
require_method('GET');

$latitude = filter_var($_GET['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_GET['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
if ($latitude === false || $latitude === null || $latitude < -90 || $latitude > 90
    || $longitude === false || $longitude === null || $longitude < -180 || $longitude > 180) {
    respond(422, ['success' => false, 'message' => 'latitude/longitude GPS tidak valid.']);
}
try {
    $best = null;
    $bestDistance = PHP_FLOAT_MAX;
    foreach (catalog_all_location_rows(database()) as $r) {
        $distance = catalog_distance_km(
            (float)$latitude, (float)$longitude, (float)$r['latitude'], (float)$r['longitude']
        );
        if ($distance <= (float)$r['service_radius_km'] && $distance < $bestDistance) {
            $best = $r;
            $bestDistance = $distance;
        }
    }
    respond(200, [
        'success' => true,
        'location' => $best === null ? null : catalog_location_payload($best),
        'message' => $best === null ? 'Belum ada produk untuk area GPS ini. Pilih lokasi tersedia secara manual.' : 'Lokasi terdeteksi.',
    ]);
} catch (Throwable $e) {
    error_log('FruitSenses catalog resolve location: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal mengenali lokasi.']);
}
