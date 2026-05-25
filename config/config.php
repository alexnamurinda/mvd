<?php
/**
 * BusinessPro - Main Application Configuration
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application constants
define('APP_NAME', 'BusinessPro');
define('APP_TAGLINE', 'Smart Multi-Business Manager');
define('APP_VERSION', '1.0.0');

// Base URL — change to your own server / domain path
// Auto-detect base URL (works for sub-folder installs too)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = dirname($_SERVER['SCRIPT_NAME']);
$base   = rtrim(str_replace('\\', '/', $script), '/');
// Walk up if we're inside /modules/xxx/
if (preg_match('#/(modules|api)(/[^/]+)?$#', $base)) {
    $base = preg_replace('#/(modules|api)(/[^/]+)?$#', '', $base);
}
define('BASE_URL', $scheme . '://' . $host . $base);

// Date & timezone
date_default_timezone_set('Africa/Kampala');

// Currency default
define('DEFAULT_CURRENCY', 'UGX');

// Include database
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
