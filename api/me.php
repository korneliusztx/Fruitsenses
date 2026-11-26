<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_method('GET');
bearer_token(); // Tolak request tanpa token sebelum membuka koneksi DB.
try {
    $user = authenticated_user(database());
    respond(200, ['success' => true, 'user' => user_payload($user)]);
} catch (Throwable $e) {
    error_log('FruitSenses me error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Gagal membaca profil.']);
}
