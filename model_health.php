<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Gunakan GET.']);
    exit;
}

try {
    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('config.php tidak ditemukan');
    }
    $cfg = require $configPath;
    if (!is_array($cfg)) {
        throw new RuntimeException('config.php tidak valid');
    }
    $ffiSetting = strtolower(trim((string) ini_get('ffi.enable')));
    $checks = [
        'php_version_supported' => PHP_VERSION_ID >= 80100,
        'classifier_file' => is_file(__DIR__ . '/lib/TfLiteClassifier.php'),
        'model_tflite' => isset($cfg['model_path']) && is_file((string) $cfg['model_path']),
        'runtime_native' => isset($cfg['tflite_library']) && is_file((string) $cfg['tflite_library']),
        'extension_ffi' => extension_loaded('FFI'),
        'ffi_enabled' => in_array($ffiSetting, ['1', 'on', 'true'], true),
        'extension_gd' => extension_loaded('gd'),
    ];
    $ready = !in_array(false, array_values($checks), true);
    http_response_code($ready ? 200 : 503);
    echo json_encode([
        'success' => $ready,
        'service' => 'FruitSense PHP TFLite',
        'checks' => $checks,
        'message' => $ready
            ? 'Dependensi utama tersedia. Upload gambar untuk menguji inference.'
            : 'Ada dependensi yang belum tersedia. Periksa nilai false pada checks.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[FruitSense model health] ' . $e);
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Konfigurasi model gagal dibaca. Periksa config.php dan error log hosting.']);
}
