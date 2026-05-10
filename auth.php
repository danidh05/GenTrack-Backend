<?php
function verifyFirebaseToken(): array {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $body = is_array($body) ? $body : [];

    $idToken = $body['id_token'] ?? '';

    if ($idToken === '') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing id_token in request body']);
        exit;
    }

    $cfg = require __DIR__ . '/config.php';
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . $cfg['firebase_web_api_key'];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$result) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Token verification failed']);
        exit;
    }

    $data = json_decode($result, true);
    $uid  = $data['users'][0]['localId'] ?? null;

    if (!$uid) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    return ['uid' => $uid, 'body' => $body];
}
