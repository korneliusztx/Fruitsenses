<?php
declare(strict_types=1);
require __DIR__ . '/_scan_history_common.php';
require_method('POST');
bearer_token();
$db = database();
$user = authenticated_user($db);
$data = read_json();
$userId = (string)$user['id'];
$deleteAll = ($data['delete_all'] ?? false) === true;
$id = $data['id'] ?? null;
if (!$deleteAll && (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1)) {
    scan_fail(422, 'Kirim id atau delete_all: true.');
}
try {
    $db->beginTransaction();
    if ($deleteAll) {
        $stmt = $db->prepare('SELECT image_filename FROM scan_histories WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $filenames = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $delete = $db->prepare('DELETE FROM scan_histories WHERE user_id = ?');
        $delete->execute([$userId]);
    } else {
        $stmt = $db->prepare('SELECT image_filename FROM scan_histories WHERE user_id = ? AND id = ? FOR UPDATE');
        $stmt->execute([$userId, (string)$id]);
        $filenames = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($filenames === []) {
            $db->rollBack();
            scan_fail(404, 'Riwayat tidak ditemukan.');
        }
        $delete = $db->prepare('DELETE FROM scan_histories WHERE user_id = ? AND id = ?');
        $delete->execute([$userId, (string)$id]);
    }
    $removed = $delete->rowCount();
    $db->commit();
    $directory = scan_storage_dir();
    foreach ($filenames as $filename) {
        if (is_string($filename) && preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/', $filename)) {
            @unlink($directory . DIRECTORY_SEPARATOR . $filename);
        }
    }
    respond(200, ['success' => true, 'deleted' => $removed, 'message' => 'Riwayat dihapus.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[FruitSenses] scan_history_delete: ' . $e->getMessage());
    scan_fail(500, 'Gagal menghapus riwayat.');
}
