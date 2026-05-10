<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid] = verifyFirebaseToken();

try {
    $db = getDB();

    $s = $db->prepare("
        SELECT local_id, name, phone, location, amps, status, notes, image_url, created_at, updated_at
        FROM customers
        WHERE owner_uid = :uid
        ORDER BY name ASC
    ");
    $s->execute([':uid' => $uid]);
    $customers = $s->fetchAll();

    $s = $db->prepare("
        SELECT local_id, customer_local_id, month, amps, price_per_amp,
               total, previous_balance, final_total, status, created_at, updated_at
        FROM bills
        WHERE owner_uid = :uid
        ORDER BY created_at DESC
    ");
    $s->execute([':uid' => $uid]);
    $bills = $s->fetchAll();

    $s = $db->prepare("
        SELECT local_id, bill_local_id, amount_paid, date, remaining_balance, created_at, updated_at
        FROM payments
        WHERE owner_uid = :uid
        ORDER BY date DESC
    ");
    $s->execute([':uid' => $uid]);
    $payments = $s->fetchAll();

    $s = $db->prepare("
        SELECT default_price_per_amp, generator_capacity
        FROM remote_config
        WHERE owner_uid = :uid
    ");
    $s->execute([':uid' => $uid]);
    $configRow = $s->fetch();

    $config = $configRow
        ? ['default_price_per_amp' => (float)$configRow['default_price_per_amp'], 'generator_capacity' => (int)$configRow['generator_capacity']]
        : ['default_price_per_amp' => 0, 'generator_capacity' => 0];

    respond([
        'customers' => $customers,
        'bills'     => $bills,
        'payments'  => $payments,
        'config'    => $config,
    ]);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
