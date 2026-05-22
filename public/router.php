<?php
// router.php — PHP built-in server router

// Serve existing files as-is
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // let PHP serve the requested file
}

// Otherwise, route all requests to index.php (front controller)
require __DIR__ . '/index.php';