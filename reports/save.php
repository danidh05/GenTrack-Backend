<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid, 'body' => $body] = verifyFirebaseToken();

// --- Validation ---
$month                 = requireParam($body, 'month');
$totalCustomersBilled  = requireParam($body, 'total_customers_billed');
$totalExpectedRevenue  = requireParam($body, 'total_expected_revenue');

if (!preg_match('/^\d{4}-\d{2}$/', $month))
    respondError('month must be in YYYY-MM format');

if (filter_var($totalCustomersBilled, FILTER_VALIDATE_INT) === false || (int)$totalCustomersBilled < 0)
    respondError('total_customers_billed must be a non-negative integer');

if (!isPositiveDecimal($totalExpectedRevenue))
    respondError('total_expected_revenue must be a non-negative number');

// --- Upsert ---
try {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO monthly_reports
          (owner_uid, month, total_customers_billed, total_expected_revenue, created_at)
        VALUES
          (:uid, :month, :total_customers, :total_revenue, :now)
        ON DUPLICATE KEY UPDATE
          total_customers_billed = VALUES(total_customers_billed),
          total_expected_revenue = VALUES(total_expected_revenue)
    ");

    $stmt->execute([
        ':uid'            => $uid,
        ':month'          => $month,
        ':total_customers'=> (int)$totalCustomersBilled,
        ':total_revenue'  => (float)$totalExpectedRevenue,
        ':now'            => now(),
    ]);

    respond(['message' => 'Report saved']);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
