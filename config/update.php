<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid, 'body' => $body] = verifyFirebaseToken();

// --- Validation ---
$price        = requireParam($body, 'default_price_per_amp');
$capacity     = requireParam($body, 'generator_capacity');
$price5a      = requireParam($body, 'price_5a');
$price10a     = requireParam($body, 'price_10a');
$price15a     = requireParam($body, 'price_15a');
$priceKwh     = requireParam($body, 'price_per_kwh');
$basePrice5a  = requireParam($body, 'base_price_5a');
$basePrice10a = requireParam($body, 'base_price_10a');
$basePrice15a = requireParam($body, 'base_price_15a');

if (!isPositiveDecimal($price))
    respondError('default_price_per_amp must be a non-negative number');

if (filter_var($capacity, FILTER_VALIDATE_INT) === false || (int)$capacity < 0)
    respondError('generator_capacity must be a non-negative integer');

if (!isPositiveDecimal($price5a))
    respondError('price_5a must be a non-negative number');

if (!isPositiveDecimal($price10a))
    respondError('price_10a must be a non-negative number');

if (!isPositiveDecimal($price15a))
    respondError('price_15a must be a non-negative number');

if (!isPositiveDecimal($priceKwh))
    respondError('price_per_kwh must be a non-negative number');

if (!isPositiveDecimal($basePrice5a))
    respondError('base_price_5a must be a non-negative number');

if (!isPositiveDecimal($basePrice10a))
    respondError('base_price_10a must be a non-negative number');

if (!isPositiveDecimal($basePrice15a))
    respondError('base_price_15a must be a non-negative number');

// --- Upsert ---
try {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO remote_config
          (owner_uid, default_price_per_amp, generator_capacity,
           price_5a, price_10a, price_15a, price_per_kwh,
           base_price_5a, base_price_10a, base_price_15a, updated_at)
        VALUES
          (:uid, :price, :capacity, :price5a, :price10a, :price15a, :price_kwh,
           :base5a, :base10a, :base15a, :now)
        ON DUPLICATE KEY UPDATE
          default_price_per_amp = VALUES(default_price_per_amp),
          generator_capacity    = VALUES(generator_capacity),
          price_5a              = VALUES(price_5a),
          price_10a             = VALUES(price_10a),
          price_15a             = VALUES(price_15a),
          price_per_kwh         = VALUES(price_per_kwh),
          base_price_5a         = VALUES(base_price_5a),
          base_price_10a        = VALUES(base_price_10a),
          base_price_15a        = VALUES(base_price_15a),
          updated_at            = VALUES(updated_at)
    ");

    $stmt->execute([
        ':uid'       => $uid,
        ':price'     => (float)$price,
        ':capacity'  => (int)$capacity,
        ':price5a'   => (float)$price5a,
        ':price10a'  => (float)$price10a,
        ':price15a'  => (float)$price15a,
        ':price_kwh' => (float)$priceKwh,
        ':base5a'    => (float)$basePrice5a,
        ':base10a'   => (float)$basePrice10a,
        ':base15a'   => (float)$basePrice15a,
        ':now'       => now(),
    ]);

    respond(['message' => 'Config updated']);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
