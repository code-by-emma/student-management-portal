<?php
/**
 * Database connection (PDO).
 * Either edit the fallback values directly below, or set DB_HOST / DB_NAME /
 * DB_USER / DB_PASS as environment variables (the fallbacks are used when
 * an environment variable isn't set — which is the normal case on XAMPP).
 */

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'smp_db';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Never leak credentials or raw exception details to the browser.
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Check config/database.php and your DB server.');
}
