<?php

declare(strict_types=1);

namespace App\Core;

/** Per-session CSRF token. Every admin POST/PUT/PATCH/DELETE form must include csrf_field(). */
final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_csrf');
        if (!is_string($t) || strlen($t) < 32) {
            $t = bin2hex(random_bytes(32));
            Session::put('_csrf', $t);
        }
        return $t;
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get('_csrf');
        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }
}
