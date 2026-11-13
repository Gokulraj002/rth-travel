<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tells the live Next.js website to drop its cached CMS data so edits appear within seconds.
 * Calls  POST {NEXT_REVALIDATE_URL}  with header  x-revalidate-secret: {NEXT_REVALIDATE_SECRET}
 * (handled by src/app/api/revalidate/route.ts in the website). Failures are logged, never fatal —
 * the website also refreshes on its own every few minutes as a fallback.
 */
final class Revalidator
{
    /** @param list<string> $tags e.g. ['packages'] — the website maps these to cache tags */
    public static function ping(array $tags = ['cms']): bool
    {
        $url = (string) Env::get('NEXT_REVALIDATE_URL', '');
        $secret = (string) Env::get('NEXT_REVALIDATE_SECRET', '');
        if ($url === '' || $secret === '' || !function_exists('curl_init')) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['tags' => array_values(array_unique(['cms', ...$tags]))]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-revalidate-secret: ' . $secret],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            error_log("Revalidate failed ({$status}): " . ($err ?: (string) $body));
            return false;
        }
        return true;
    }
}
