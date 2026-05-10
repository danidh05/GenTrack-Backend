<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid, 'body' => $body] = verifyFirebaseToken();

// --- Validation ---
$localId = requireParam($body, 'local_id');

if (!isPositiveInt($localId))
    respondError('local_id must be a positive integer');

try {
    $db = getDB();

    // Block deletion if any bill references this customer
    $check = $db->prepare("
        SELECT COUNT(*) FROM bills
        WHERE owner_uid = :uid AND customer_local_id = :local_id
    ");
    $check->execute([':uid' => $uid, ':local_id' => (int)$localId]);

    if ((int)$check->fetchColumn() > 0)
        respondError('Customer has existing bills and cannot be deleted', 409);

    // Safe to delete
    $stmt = $db->prepare("
        DELETE FROM customers
        WHERE owner_uid = :uid AND local_id = :local_id
    ");
    $stmt->execute([':uid' => $uid, ':local_id' => (int)$localId]);

    if ($stmt->rowCount() === 0)
        respondError('Customer not found', 404);

    respond(['message' => 'Customer deleted']);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
