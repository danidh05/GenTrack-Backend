<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid] = verifyFirebaseToken();

$billLocalId = $_GET['bill_local_id'] ?? null;

if ($billLocalId === null)
    respondError('bill_local_id query parameter is required');

if (!isPositiveInt($billLocalId))
    respondError('bill_local_id must be a positive integer');

try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT local_id, bill_local_id, amount_paid, date, remaining_balance, created_at, updated_at
        FROM payments
        WHERE owner_uid = :uid AND bill_local_id = :bid
        ORDER BY date DESC
    ");
    $stmt->execute([':uid' => $uid, ':bid' => (int)$billLocalId]);
    $payments = $stmt->fetchAll();

    respond(['payments' => $payments]);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
