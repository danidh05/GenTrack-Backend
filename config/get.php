<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid] = verifyFirebaseToken();

try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT default_price_per_amp, generator_capacity,
               price_5a, price_10a, price_15a, price_per_kwh,
               base_price_5a, base_price_10a, base_price_15a,
               currency
        FROM remote_config
        WHERE owner_uid = :uid
    ");
    $stmt->execute([':uid' => $uid]);
    $row = $stmt->fetch();

    if (!$row) {
        respond([
            'default_price_per_amp' => 0,
            'generator_capacity'    => 0,
            'price_5a'              => 0,
            'price_10a'             => 0,
            'price_15a'             => 0,
            'price_per_kwh'         => 0,
            'base_price_5a'         => 0,
            'base_price_10a'        => 0,
            'base_price_15a'        => 0,
            'currency'              => 'USD',
        ]);
    }

    respond([
        'default_price_per_amp' => (float)$row['default_price_per_amp'],
        'generator_capacity'    => (int)$row['generator_capacity'],
        'price_5a'              => (float)$row['price_5a'],
        'price_10a'             => (float)$row['price_10a'],
        'price_15a'             => (float)$row['price_15a'],
        'price_per_kwh'         => (float)$row['price_per_kwh'],
        'base_price_5a'         => (float)$row['base_price_5a'],
        'base_price_10a'        => (float)$row['base_price_10a'],
        'base_price_15a'        => (float)$row['base_price_15a'],
        'currency'              => (string)$row['currency'],
    ]);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
