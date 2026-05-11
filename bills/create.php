<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid, 'body' => $body] = verifyFirebaseToken();

// --- Validation ---
$localId          = requireParam($body, 'local_id');
$customerLocalId  = requireParam($body, 'customer_local_id');
$month            = requireParam($body, 'month');
$amps             = requireParam($body, 'amps');
$pricePerAmp      = requireParam($body, 'price_per_amp');
$total            = requireParam($body, 'total');
$previousBalance  = requireParam($body, 'previous_balance');
$finalTotal       = requireParam($body, 'final_total');
$status           = requireParam($body, 'status');
$billingModel     = requireParam($body, 'billing_model');
$tierFee          = requireParam($body, 'tier_fee');
$createdAt        = requireParam($body, 'created_at');
$updatedAt        = requireParam($body, 'updated_at');

// Optional meter reading fields — default to 0 for flat billing model
$currentReading  = isset($body['current_reading'])  ? $body['current_reading']  : 0;
$previousReading = isset($body['previous_reading']) ? $body['previous_reading'] : 0;
$consumption     = isset($body['consumption'])      ? $body['consumption']      : 0;

if (!isPositiveInt($localId))
    respondError('local_id must be a positive integer');

if (!isPositiveInt($customerLocalId))
    respondError('customer_local_id must be a positive integer');

if (!preg_match('/^\d{4}-\d{2}$/', $month))
    respondError('month must be in YYYY-MM format');

if (!isPositiveInt($amps))
    respondError('amps must be a positive integer');

if (!isPositiveDecimal($pricePerAmp))
    respondError('price_per_amp must be a non-negative number');

if (!isPositiveDecimal($total))
    respondError('total must be a non-negative number');

if (!isPositiveDecimal($previousBalance))
    respondError('previous_balance must be a non-negative number');

if (!isPositiveDecimal($finalTotal))
    respondError('final_total must be a non-negative number');

if (!isValidStatus($status, ['Paid', 'Partial', 'Unpaid']))
    respondError('status must be Paid, Partial, or Unpaid');

if (!isValidStatus($billingModel, ['flat', 'base_consumption']))
    respondError('billing_model must be flat or base_consumption');

if (!isPositiveDecimal($tierFee))
    respondError('tier_fee must be a non-negative number');

if (!isPositiveDecimal($currentReading))
    respondError('current_reading must be a non-negative number');

if (!isPositiveDecimal($previousReading))
    respondError('previous_reading must be a non-negative number');

if (!isPositiveDecimal($consumption))
    respondError('consumption must be a non-negative number');

// --- Upsert ---
try {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO bills
          (owner_uid, local_id, customer_local_id, month, amps, price_per_amp,
           total, previous_balance, final_total, status,
           billing_model, current_reading, previous_reading, consumption, tier_fee,
           created_at, updated_at)
        VALUES
          (:uid, :local_id, :customer_local_id, :month, :amps, :price_per_amp,
           :total, :previous_balance, :final_total, :status,
           :billing_model, :current_reading, :previous_reading, :consumption, :tier_fee,
           :created_at, :updated_at)
        ON DUPLICATE KEY UPDATE
          customer_local_id = VALUES(customer_local_id),
          month             = VALUES(month),
          amps              = VALUES(amps),
          price_per_amp     = VALUES(price_per_amp),
          total             = VALUES(total),
          previous_balance  = VALUES(previous_balance),
          final_total       = VALUES(final_total),
          status            = VALUES(status),
          billing_model     = VALUES(billing_model),
          current_reading   = VALUES(current_reading),
          previous_reading  = VALUES(previous_reading),
          consumption       = VALUES(consumption),
          tier_fee          = VALUES(tier_fee),
          updated_at        = VALUES(updated_at)
    ");

    $stmt->execute([
        ':uid'              => $uid,
        ':local_id'         => (int)$localId,
        ':customer_local_id'=> (int)$customerLocalId,
        ':month'            => $month,
        ':amps'             => (int)$amps,
        ':price_per_amp'    => (float)$pricePerAmp,
        ':total'            => (float)$total,
        ':previous_balance' => (float)$previousBalance,
        ':final_total'      => (float)$finalTotal,
        ':status'           => $status,
        ':billing_model'    => $billingModel,
        ':current_reading'  => (float)$currentReading,
        ':previous_reading' => (float)$previousReading,
        ':consumption'      => (float)$consumption,
        ':tier_fee'         => (float)$tierFee,
        ':created_at'       => $createdAt,
        ':updated_at'       => $updatedAt,
    ]);

    respond(['message' => 'Bill saved']);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
