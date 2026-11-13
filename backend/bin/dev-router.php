<?php

/*
 * Local development only:  php -S 127.0.0.1:8090 -t public bin/dev-router.php
 * (In the Hostinger layout the web root is called public_html — this picks whichever exists.)
 * Mimics the Apache .htaccess: serve real files from the web root, everything else → index.php.
 */
$root = is_dir(__DIR__ . '/../public') ? __DIR__ . '/../public' : __DIR__ . '/../public_html';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = $root . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php') && !str_contains($path, '/.')) {
    return false;
}
require $root . '/index.php';
