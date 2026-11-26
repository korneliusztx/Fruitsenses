<?php
declare(strict_types=1);
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/_init.php'; 
function catalog_product_payload(array $r): array
{
    $price = (int)$r['price_idr'];
    $unit = (string)$r['unit'];
    return [
        'id' => (int)$r['id'],
        'name' => (string)$r['name'],
        'category' => (string)$r['category'],
        'description' => (string)($r['description'] ?? ''),
        'price' => $price,
        'unit' => $unit,
        'price_formatted' => 'Rp' . number_format($price, 0, ',', '.') . ' /' . $unit,
        'stock_qty' => (float)$r['stock_qty'],
        'image_url' => (string)$r['image_url'],
        'store' => [
            'id' => (int)$r['store_id'],
            'name' => (string)$r['store_name'],
            'address' => (string)$r['store_address'],
            'rating' => (float)$r['store_rating'],
            'image_url' => (string)$r['store_image_url'],
        ],
        'location' => [
            'id' => (int)$r['location_id'],
            'code' => (string)$r['location_code'],
            'name' => (string)$r['location_name'],
        ],
    ];
}

function catalog_location_payload(array $r): array
{
    return [
        'id' => (int)$r['id'],
        'code' => (string)$r['code'],
        'name' => (string)$r['name'],
        'address' => (string)$r['display_address'],
        'latitude' => (float)$r['latitude'],
        'longitude' => (float)$r['longitude'],
        'radius_km' => (float)$r['service_radius_km'],
    ];
}

function catalog_all_location_rows(PDO $db): array
{
    return $db->query('SELECT id, code, name, display_address, latitude, longitude, service_radius_km '
        . 'FROM catalog_locations WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
}

/** Haversine kilometer; hanya untuk pemilihan area layanan terdekat, bukan batas administratif. */
function catalog_distance_km(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371.0088;
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;
    return 2 * $r * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
}
