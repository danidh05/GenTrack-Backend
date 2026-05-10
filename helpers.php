<?php
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function respondError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireParam(array $data, string $key): mixed {
    if (!isset($data[$key]) || $data[$key] === '') {
        respondError("Missing required field: $key");
    }
    return $data[$key];
}

function isValidStatus(string $val, array $allowed): bool {
    return in_array($val, $allowed, true);
}

function isPositiveInt(mixed $val): bool {
    return filter_var($val, FILTER_VALIDATE_INT) !== false && (int)$val > 0;
}

function isPositiveDecimal(mixed $val): bool {
    return filter_var($val, FILTER_VALIDATE_FLOAT) !== false && (float)$val >= 0;
}

function now(): string {
    return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}
