<?php
/**
 * Database Configuration - BusinessPro
 * Edit these values to match your hosting environment.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'businesspro');
define('DB_USER', 'root');         // <-- change for production
define('DB_PASS', '');             // <-- change for production
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
