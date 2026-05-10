<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid, 'body' => $body] = verifyFirebaseToken();

// --- Validation ---
$localId          = requireParam($body, 'local_id');
$billLocalId      = requireParam($body, 'bill_local_id');
$amountPaid       = requireParam($body, 'amount_paid');
$date             = requireParam($body, 'date');
$remainingBalance = requireParam($body, 'remaining_balance');
$createdAt        = requireParam($body, 'created_at');
$updatedAt        = requireParam($body, 'updated_at');

if (!isPositiveInt($localId))
    respondError('local_id must be a positive integer');

if (!isPositiveInt($billLocalId))
    respondError('bill_local_id must be a positive integer');

if (filter_var($amountPaid, FILTER_VALIDATE_FLOAT) === false || (float)$amountPaid <= 0)
    respondError('amount_paid must be a number greater than 0');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(
    (int)substr($date, 5, 2),
    (int)substr($date, 8, 2),
    (int)substr($date, 0, 4)
))
    respondError('date must be a valid date in YYYY-MM-DD format');

if (!isPositiveDecimal($remainingBalance))
    respondError('remaining_balance must be a non-negative number');

// --- Upsert ---
try {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO payments
          (owner_uid, local_id, bill_local_id, amount_paid, date, remaining_balance, created_at, updated_at)
        VALUES
          (:uid, :local_id, :bill_local_id, :amount_paid, :date, :remaining_balance, :created_at, :updated_at)
        ON DUPLICATE KEY UPDATE
          bill_local_id     = VALUES(bill_local_id),
          amount_paid       = VALUES(amount_paid),
          date              = VALUES(date),
          remaining_balance = VALUES(remaining_balance),
          updated_at        = VALUES(updated_at)
    ");

    $stmt->execute([
        ':uid'               => $uid,
        ':local_id'          => (int)$localId,
        ':bill_local_id'     => (int)$billLocalId,
        ':amount_paid'       => (float)$amountPaid,
        ':date'              => $date,
        ':remaining_balance' => (float)$remainingBalance,
        ':created_at'        => $createdAt,
        ':updated_at'        => $updatedAt,
    ]);

    respond(['message' => 'Payment saved']);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
