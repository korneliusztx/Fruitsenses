<?php
declare(strict_types=1);

return [
    'model_path' => __DIR__ . '/models/model_klasifikasi_buah.tflite',
    'tflite_library' => __DIR__ . '/runtime/' . (PHP_OS_FAMILY === 'Windows'
        ? 'tensorflowlite_c.dll'
        : (PHP_OS_FAMILY === 'Darwin' ? 'libtensorflowlite_c.dylib' : 'libtensorflowlite_c.so')),
    'image_size' => 160,
    'labels' => ['Apple', 'Banana', 'Guava', 'Orange', 'Lime', 'Pomegranate'],
    'max_upload_bytes' => 5 * 1024 * 1024,
    'max_image_pixels' => 20_000_000,
    'num_threads' => 2,
    'output_format' => 'auto', 
    'binary_labels' => [],
    'debug' => false,
];
