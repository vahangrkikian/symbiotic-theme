<?php
/**
 * Router for PHP built-in server.
 * Routes all requests through WordPress index.php unless the file exists on disk.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve existing files directly (CSS, JS, images, etc.)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route everything else through WordPress
$_SERVER['PATH_INFO'] = $uri;
require_once __DIR__ . '/index.php';
