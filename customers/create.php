<?php
error_reporting(0);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

['uid' => $uid, 'body' => $body] = verifyFirebaseToken();

// --- Validation ---
$localId  = requireParam($body, 'local_id');
$name     = requireParam($body, 'name');
$amps     = requireParam($body, 'amps');
$status   = requireParam($body, 'status');
$createdAt = requireParam($body, 'created_at');
$updatedAt = requireParam($body, 'updated_at');

if (!isPositiveInt($localId))
    respondError('local_id must be a positive integer');

if (!is_string($name) || trim($name) === '' || strlen($name) > 255)
    respondError('name must be a non-empty string (max 255 chars)');

if (!isPositiveInt($amps) || (int)$amps > 1000)
    respondError('amps must be a positive integer (max 1000)');

if (!isValidStatus($status, ['Active', 'Unpaid', 'Disconnected']))
    respondError('status must be Active, Unpaid, or Disconnected');

$phone    = isset($body['phone'])    ? (string)$body['phone']    : null;
$location = isset($body['location']) ? (string)$body['location'] : null;
$notes    = isset($body['notes'])    ? (string)$body['notes']    : null;
$imageUrl = isset($body['image_url']) ? (string)$body['image_url'] : null;

if ($phone !== null && strlen($phone) > 32)
    respondError('phone must be max 32 chars');

if ($location !== null && strlen($location) > 255)
    respondError('location must be max 255 chars');

// --- Upsert ---
try {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO customers
          (owner_uid, local_id, name, phone, location, amps, status, notes, image_url, created_at, updated_at)
        VALUES
          (:uid, :local_id, :name, :phone, :location, :amps, :status, :notes, :image_url, :created_at, :updated_at)
        ON DUPLICATE KEY UPDATE
          name       = VALUES(name),
          phone      = VALUES(phone),
          location   = VALUES(location),
          amps       = VALUES(amps),
          status     = VALUES(status),
          notes      = VALUES(notes),
          image_url  = VALUES(image_url),
          updated_at = VALUES(updated_at)
    ");

    $stmt->execute([
        ':uid'        => $uid,
        ':local_id'   => (int)$localId,
        ':name'       => trim($name),
        ':phone'      => $phone,
        ':location'   => $location,
        ':amps'       => (int)$amps,
        ':status'     => $status,
        ':notes'      => $notes,
        ':image_url'  => $imageUrl,
        ':created_at' => $createdAt,
        ':updated_at' => $updatedAt,
    ]);

    respond(['message' => 'Customer saved']);

} catch (PDOException $e) {
    respondError('DB Error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondError('General Error: ' . $e->getMessage(), 500);
}
