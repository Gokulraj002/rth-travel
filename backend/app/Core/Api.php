<?php

declare(strict_types=1);

namespace App\Core;

/** Helpers for the public JSON API consumed by the Next.js website. */
final class Api
{
    /** CORS: only the website origin(s) listed in API_ALLOWED_ORIGINS may call from a browser. */
    public static function cors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = array_filter(array_map('trim', explode(',', (string) Env::get('API_ALLOWED_ORIGINS', ''))));
        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Accept');
            header('Access-Control-Max-Age: 86400');
        }
    }

    /**
     * Server-to-server key (the Next.js server sends it). Optional for public read endpoints,
     * required where noted. Comparison is constant-time.
     */
    public static function hasValidKey(Request $req): bool
    {
        $expected = (string) Env::get('API_READ_KEY', '');
        $given = (string) ($req->header('X-RTH-Key') ?? '');
        return $expected !== '' && $given !== '' && hash_equals($expected, $given);
    }

    /** Cache headers for public GET responses (CDN/browser), short + stale-while-revalidate. */
    public static function cacheHeaders(int $maxAge = 60): array
    {
        return ['Cache-Control' => "public, max-age={$maxAge}, stale-while-revalidate=300"];
    }
}
