<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid] = verifyFirebaseToken();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3;
if ($limit < 1 || $limit > 12) $limit = 3;

try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT month, total_customers_billed, total_expected_revenue
        FROM monthly_reports
        WHERE owner_uid = :uid
        ORDER BY month DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':uid',   $uid,   PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $reports = $stmt->fetchAll();

    foreach ($reports as &$r) {
        $r['total_customers_billed'] = (int)$r['total_customers_billed'];
        $r['total_expected_revenue'] = (float)$r['total_expected_revenue'];
    }

    respond(['reports' => $reports]);

} catch (PDOException $e) {
    error_log('[GenTrack] PDO Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] General Error: ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
