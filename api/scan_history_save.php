<?php
declare(strict_types=1);
require __DIR__ . '/_scan_history_common.php';
require_method('POST');
bearer_token();

$db = database();
$user = authenticated_user($db);
$userId = (string)$user['id'];

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 6 * 1024 * 1024) {
    scan_fail(413, 'Ukuran permintaan melebihi 6 MB.');
}
if (!is_string($_POST['scan_uuid'] ?? null)) scan_fail(422, 'scan_uuid wajib teks.');
$uuid = strtolower(trim($_POST['scan_uuid']));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
    scan_fail(422, 'scan_uuid tidak valid.');
}

$existing = scan_history_by_uuid($db, $userId, $uuid);
if ($existing !== null) {
    respond(200, ['success' => true, 'message' => 'Riwayat sudah tersimpan.', 'history' => scan_history_payload($existing)]);
}

if (!is_string($_POST['prediction'] ?? null)) scan_fail(422, 'prediction wajib teks.');
$prediction = trim($_POST['prediction']);
if ($prediction === '' || strlen($prediction) > 100) {
    scan_fail(422, 'Nama prediksi tidak valid.');
}
$confidenceRaw = $_POST['confidence'] ?? null;
if (!is_string($confidenceRaw) || !is_numeric($confidenceRaw)) {
    scan_fail(422, 'Confidence wajib angka.');
}
$confidence = (float)$confidenceRaw;
if (!is_finite($confidence) || $confidence < 0 || $confidence > 1) {
    scan_fail(422, 'Confidence harus bernilai 0 sampai 1.');
}
$raw = $_POST['probability_raw'] ?? null;
$probabilityRaw = null;
if ($raw !== null && $raw !== '') {
    if (!is_string($raw) || !is_numeric($raw) || !is_finite((float)$raw) || (float)$raw < 0 || (float)$raw > 1) {
        scan_fail(422, 'probability_raw tidak valid.');
    }
    $probabilityRaw = (float)$raw;
}
if (isset($_POST['quality']) && !is_string($_POST['quality'])) scan_fail(422, 'Kualitas harus teks.');
$quality = trim($_POST['quality'] ?? '');
if (strlen($quality) > 60) scan_fail(422, 'Kualitas terlalu panjang.');
$quality = $quality === '' ? null : $quality;

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    scan_fail(422, 'Foto hasil scan wajib dikirim sebagai image.');
}
$upload = $_FILES['image'];
if (($upload['error'] ?? -1) !== UPLOAD_ERR_OK) {
    scan_fail(400, 'Gagal mengunggah gambar.');
}
$tmp = $upload['tmp_name'] ?? '';
$bytes = (int)($upload['size'] ?? 0);
if (!is_string($tmp) || !is_uploaded_file($tmp) || $bytes < 1 || $bytes > 5 * 1024 * 1024) {
    scan_fail(413, 'Gambar harus valid dan maksimal 5 MB.');
}
$info = @getimagesize($tmp);
if (!is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1 || ($info[0] * $info[1]) > 25000000) {
    scan_fail(422, 'Gambar tidak valid atau dimensinya terlalu besar.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extensions[$mime])) scan_fail(422, 'Hanya JPG, PNG, atau WEBP yang didukung.');

$filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
$path = scan_storage_dir() . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($tmp, $path)) {
    scan_fail(500, 'Gagal menyimpan foto di hosting.');
}
@chmod($path, 0600);

try {
    $insert = $db->prepare('INSERT INTO scan_histories '
        . '(user_id, scan_uuid, prediction, quality, confidence, probability_raw, image_filename, image_mime) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$userId, $uuid, $prediction, $quality, $confidence, $probabilityRaw, $filename, $mime]);
    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM scan_histories WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Insert tidak dapat dibaca kembali.');
    respond(201, ['success' => true, 'message' => 'Hasil scan tersimpan.', 'history' => scan_history_payload($row)]);
} catch (PDOException $e) {
    @unlink($path);
    if ($e->getCode() === '23000') {
        $row = scan_history_by_uuid($db, $userId, $uuid);
        if ($row !== null) {
            respond(200, ['success' => true, 'message' => 'Riwayat sudah tersimpan.', 'history' => scan_history_payload($row)]);
        }
    }
    error_log('[FruitSenses] scan_history_save: ' . $e->getMessage());
    scan_fail(500, 'Gagal menyimpan history ke database.');
} catch (Throwable $e) {
    error_log('[FruitSenses] scan_history_save: ' . $e->getMessage());
    scan_fail(500, 'Gagal membaca hasil penyimpanan.');
}
