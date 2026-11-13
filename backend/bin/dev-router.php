<?php

/*
 * Local development only:  php -S 127.0.0.1:8090 -t public bin/dev-router.php
 * Mimics the Apache .htaccess: serve real files from public/, everything else → index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php') && !str_contains($path, '/.')) {
    return false;
}
require __DIR__ . '/../public/index.php';
