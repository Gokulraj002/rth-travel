<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File-based fixed-window rate limiter (no Redis on shared hosting).
 *   if (!RateLimiter::hit('enquiry:' . $ip, 5, 600)) { 429 }
 */
final class RateLimiter
{
    public static function hit(string $key, int $max, int $windowSeconds): bool
    {
        $dir = STORAGE_PATH . '/cache/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $now = time();
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return true; // fail open rather than block real customers
        }
        flock($fh, LOCK_EX);
        $data = json_decode((string) stream_get_contents($fh), true);
        if (!is_array($data) || ($data['reset'] ?? 0) < $now) {
            $data = ['count' => 0, 'reset' => $now + $windowSeconds];
        }
        $data['count']++;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) json_encode($data));
        flock($fh, LOCK_UN);
        fclose($fh);
        return $data['count'] <= $max;
    }
}
