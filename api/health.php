<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_method('GET');
if (!extension_loaded('pdo_mysql')) {
    respond(503, [
        'success' => false,
        'service' => 'fruitsenses-auth',
        'message' => 'Ekstensi PHP pdo_mysql belum tersedia.',
    ]);
}
try {
    $db = database();
    foreach (['users', 'auth_tokens'] as $table) {
        $db->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
    }
    respond(200, [
        'success' => true,
        'service' => 'fruitsenses-auth',
        'database' => 'connected',
        'schema' => 'ready',
    ]);
} catch (Throwable $e) {
    error_log('FruitSenses health error: ' . $e->getMessage());
    respond(503, [
        'success' => false,
        'service' => 'fruitsenses-auth',
        'message' => 'Database tersambung tetapi tabel users/auth_tokens belum siap. Periksa migrasi SQL.',
    ]);
}
