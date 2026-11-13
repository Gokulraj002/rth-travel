<?php

declare(strict_types=1);

/*
 * Front controller — every request to the admin/API subdomain lands here (see .htaccess).
 * Document root on Hostinger = this `public/` folder; app/, config/, storage/ and .env stay outside it.
 */

define('PUBLIC_PATH', __DIR__);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Flash;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

$request = new Request();
$isApi = str_starts_with($request->path(), '/api/');

// Security headers for every response.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

if (!$isApi) {
    // Admin pages: sessions, no caching, strict CSP (all assets are self-hosted).
    Session::start();
    Flash::age();
    header('Cache-Control: no-store, max-age=0');
    // Image previews also come from the website (WEBSITE_URL), which may be plain http:// in local dev.
    $siteOrigin = '';
    $siteUrl = (string) App\Core\Env::get('WEBSITE_URL', '');
    if (preg_match('#^https?://[a-z0-9.\-]+(:\d+)?#i', $siteUrl, $m)) {
        $siteOrigin = ' ' . $m[0];
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:{$siteOrigin}; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

$router = new Router();
require dirname(__DIR__) . '/app/routes.php';
$router->dispatch($request);
