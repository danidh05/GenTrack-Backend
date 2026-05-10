<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid] = verifyFirebaseToken();

try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT local_id, name, phone, location, amps, status, notes, image_url, created_at, updated_at
        FROM customers
        WHERE owner_uid = :uid
        ORDER BY name ASC
    ");
    $stmt->execute([':uid' => $uid]);
    $customers = $stmt->fetchAll();

    respond(['customers' => $customers]);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
