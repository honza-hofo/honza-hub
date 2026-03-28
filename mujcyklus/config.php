<?php
// Database config - change these for your hosting
define('DB_HOST', 'localhost');
define('DB_NAME', 'mujcyklus');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SESSION_LIFETIME', 30 * 24 * 60 * 60); // 30 days

// Connect to DB
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}
