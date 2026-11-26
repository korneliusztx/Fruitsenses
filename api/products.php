<?php
declare(strict_types=1);
require __DIR__ . '/_catalog_common.php';
require_method('GET');

$locationId = filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$category = $_GET['category'] ?? 'fresh';
$search = $_GET['search'] ?? '';
if (!$locationId || !is_string($category) || !in_array($category, ['fresh', 'processed'], true)
    || !is_string($search) || strlen($search) > 120) {
    respond(422, ['success' => false, 'message' => 'location_id, category, atau search tidak valid.']);
}
try {
    $db = database();
    $location = $db->prepare('SELECT id, code, name, display_address, latitude, longitude, service_radius_km '
        . 'FROM catalog_locations WHERE id = ? AND is_active = 1 LIMIT 1');
    $location->execute([$locationId]);
    $locRow = $location->fetch();
    if (!$locRow) respond(404, ['success' => false, 'message' => 'Lokasi tidak tersedia.']);

    $sql = 'SELECT p.id, p.name, p.category, p.description, p.price_idr, p.unit, p.stock_qty, p.image_url,
                   s.id AS store_id, s.name AS store_name, s.address AS store_address, s.rating AS store_rating,
                   s.image_url AS store_image_url, l.id AS location_id, l.code AS location_code, l.name AS location_name
            FROM catalog_products p
            JOIN catalog_stores s ON s.id = p.store_id
            JOIN catalog_locations l ON l.id = s.location_id
            WHERE s.location_id = :location_id AND l.is_active = 1 AND s.is_active = 1
                  AND p.is_active = 1 AND p.stock_qty >= 1 AND p.category = :category';
    $params = ['location_id' => $locationId, 'category' => $category];
    if (trim($search) !== '') {
        $sql .= ' AND p.name LIKE :search';
        $params['search'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($search)) . '%';
        $sql .= " ESCAPE '!'";
    }
    $sql .= ' ORDER BY p.sort_order ASC, p.name ASC, p.id ASC LIMIT 100';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = array_map('catalog_product_payload', $stmt->fetchAll());

    respond(200, [
        'success' => true,
        'location' => catalog_location_payload($locRow),
        'category' => $category,
        'count' => count($products),
        'products' => $products,
    ]);
} catch (Throwable $e) {
    error_log('FruitSenses catalog products: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memuat produk.']);
}
