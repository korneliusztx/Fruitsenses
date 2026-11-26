<?php
declare(strict_types=1);
require __DIR__ . '/_catalog_common.php';
require_method('GET');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$locationId = filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id || !$locationId) respond(422, ['success' => false, 'message' => 'id dan location_id wajib diisi.']);
try {
    $db = database();
    $select = 'SELECT p.id, p.name, p.category, p.description, p.price_idr, p.unit, p.stock_qty, p.image_url,
                     s.id AS store_id, s.name AS store_name, s.address AS store_address, s.rating AS store_rating,
                     s.image_url AS store_image_url, l.id AS location_id, l.code AS location_code, l.name AS location_name
               FROM catalog_products p JOIN catalog_stores s ON s.id = p.store_id
               JOIN catalog_locations l ON l.id = s.location_id
               WHERE l.id = :loc AND l.is_active = 1 AND s.is_active = 1
                     AND p.is_active = 1 AND p.stock_qty >= 1';
    $stmt = $db->prepare($select . ' AND p.id = :id LIMIT 1');
    $stmt->execute(['loc' => $locationId, 'id' => $id]);
    $product = $stmt->fetch();
    if (!$product) respond(404, ['success' => false, 'message' => 'Produk tidak tersedia di lokasi ini.']);
    $other = $db->prepare($select . ' AND p.name = :name AND p.category = :category ORDER BY p.price_idr, p.id LIMIT 30');
    $other->execute(['loc' => $locationId, 'name' => $product['name'], 'category' => $product['category']]);
    $offers = array_map('catalog_product_payload', $other->fetchAll());
    respond(200, [
        'success' => true,
        'product' => catalog_product_payload($product),
        'offers' => $offers,
    ]);
} catch (Throwable $e) {
    error_log('FruitSenses catalog product detail: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal memuat detail produk.']);
}
