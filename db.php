<?php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/config.php';
        try {
            $pdo = new PDO(
                "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4",
                $cfg['db_user'],
                $cfg['db_pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('[GenTrack] DB Connection Error: ' . $e->getMessage());
            throw $e;
        }
    }
    return $pdo;
}
