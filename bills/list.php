<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid] = verifyFirebaseToken();

$customerLocalId = $_GET['customer_local_id'] ?? null;

try {
    $db = getDB();

    if ($customerLocalId !== null) {
        if (!isPositiveInt($customerLocalId))
            respondError('customer_local_id must be a positive integer');

        $stmt = $db->prepare("
            SELECT local_id, customer_local_id, month, amps, price_per_amp,
                   total, previous_balance, final_total, status, created_at, updated_at
            FROM bills
            WHERE owner_uid = :uid AND customer_local_id = :cid
            ORDER BY created_at DESC
        ");
        $stmt->execute([':uid' => $uid, ':cid' => (int)$customerLocalId]);
    } else {
        $stmt = $db->prepare("
            SELECT local_id, customer_local_id, month, amps, price_per_amp,
                   total, previous_balance, final_total, status, created_at, updated_at
            FROM bills
            WHERE owner_uid = :uid
            ORDER BY created_at DESC
        ");
        $stmt->execute([':uid' => $uid]);
    }

    $bills = $stmt->fetchAll();

    respond(['bills' => $bills]);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
