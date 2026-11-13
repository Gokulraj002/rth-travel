<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Paid-plan unlocking with Ed25519-signed license keys (PHP sodium; no internet call needed).
 *
 *   Key format:  RTH1.<base64url(JSON payload)>.<base64url(signature)>
 *   Payload:     {"plan":"pro","domain":"example.com","licensee":"…","issued":"2026-11-13","expires":"2027-11-13"|null,"id":"…"}
 *   Signature:   Ed25519 over "RTH1.<payload part>" with the vendor's PRIVATE key (kept off the server —
 *                see bin/license-generate.php). Only the PUBLIC key below ships with the app.
 *
 * The key is stored in settings.license_key and is valid for its domain and every sub-domain
 * (admin.example.com is covered by "example.com"). Expired / wrong-domain keys fall back to Basic.
 *
 * Local development: when running under PHP's built-in server only, LICENSE_DEV_PLAN=premium in .env
 * unlocks a plan without a key (never honoured on a real web server).
 */
final class License
{
    /** Vendor public key (base64). Replaced by bin/license-keypair.php. */
    public const PUBLIC_KEY = 'u0hFdjpXsuBfSs6MvQCxKHz7wobC2w/PSBm2lML0Vo8=';

    private const PREFIX = 'RTH1';

    /** @var array<string,mixed>|null */
    private static ?array $state = null;

    /**
     * Current license state.
     * @return array{plan:string, source:string, licensee:?string, domain:?string, expires:?string, issued:?string, error:?string}
     */
    public static function state(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }
        $state = ['plan' => 'basic', 'source' => 'none', 'licensee' => null, 'domain' => null, 'expires' => null, 'issued' => null, 'error' => null];

        $key = '';
        try {
            $key = Settings::get('license_key', '');
        } catch (\Throwable) {
            // settings table not ready (fresh install) → basic
        }

        if ($key !== '') {
            $check = self::verify($key, self::currentHost());
            if ($check['ok']) {
                $p = $check['payload'];
                $state = ['plan' => $p['plan'], 'source' => 'key', 'licensee' => $p['licensee'] ?? null, 'domain' => $p['domain'],
                          'expires' => $p['expires'] ?? null, 'issued' => $p['issued'] ?? null, 'error' => null];
            } else {
                $state['error'] = $check['error'];
                $state['source'] = 'invalid';
                if (isset($check['payload']) && is_array($check['payload'])) {
                    $state['licensee'] = $check['payload']['licensee'] ?? null;
                    $state['domain'] = $check['payload']['domain'] ?? null;
                    $state['expires'] = $check['payload']['expires'] ?? null;
                }
            }
        }

        if ($state['source'] !== 'key' && PHP_SAPI === 'cli-server') {
            $dev = (string) Env::get('LICENSE_DEV_PLAN', '');
            if (isset(config('plans')['plans'][$dev])) {
                $state['plan'] = $dev;
                $state['source'] = 'dev';
            }
        }
        return self::$state = $state;
    }

    public static function plan(): string
    {
        return self::state()['plan'];
    }

    public static function planLabel(?string $plan = null): string
    {
        $plan ??= self::plan();
        return (string) (config('plans')['plans'][$plan]['label'] ?? ucfirst($plan));
    }

    /** Does the current plan include this feature? Unknown features are treated as Basic (allowed). */
    public static function has(string $feature): bool
    {
        $required = self::requiredPlan($feature);
        $plans = config('plans')['plans'];
        return ($plans[self::plan()]['rank'] ?? 1) >= ($plans[$required]['rank'] ?? 1);
    }

    public static function requiredPlan(string $feature): string
    {
        return (string) (config('plans')['features'][$feature]['plan'] ?? 'basic');
    }

    /** @return array{plan:string,label:string,desc:string}|null */
    public static function feature(string $feature): ?array
    {
        return config('plans')['features'][$feature] ?? null;
    }

    /**
     * Verify a key's signature, plan, domain and expiry.
     * @return array{ok:bool, error:?string, payload?:array<string,mixed>}
     */
    public static function verify(string $key, ?string $host = null): array
    {
        $key = trim(preg_replace('/\s+/', '', $key) ?? '');
        $parts = explode('.', $key);
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return ['ok' => false, 'error' => 'This does not look like a valid license key.'];
        }
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return ['ok' => false, 'error' => 'The server is missing the PHP "sodium" extension (enable it in Hostinger → PHP Configuration).'];
        }
        $publicKey = base64_decode(self::PUBLIC_KEY, true);
        $payloadJson = self::b64urlDecode($parts[1]);
        $sig = self::b64urlDecode($parts[2]);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || $payloadJson === null || $sig === null || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return ['ok' => false, 'error' => 'This license key is damaged. Please copy it again exactly as you received it.'];
        }
        if (!sodium_crypto_sign_verify_detached($sig, $parts[0] . '.' . $parts[1], $publicKey)) {
            return ['ok' => false, 'error' => 'This license key is not genuine.'];
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || !isset($payload['plan'], $payload['domain']) || !isset(config('plans')['plans'][$payload['plan']])) {
            return ['ok' => false, 'error' => 'This license key is not valid for this version.'];
        }
        $payload['domain'] = strtolower((string) $payload['domain']);
        if ($host !== null && !self::domainMatches($host, $payload['domain'])) {
            return ['ok' => false, 'payload' => $payload,
                    'error' => 'This license is for ' . $payload['domain'] . ', but this admin panel runs on ' . $host . '.'];
        }
        if (!empty($payload['expires']) && strtotime((string) $payload['expires'] . ' 23:59:59') < time()) {
            return ['ok' => false, 'payload' => $payload,
                    'error' => 'This license expired on ' . date('j M Y', (int) strtotime((string) $payload['expires'])) . '. Please renew to keep the ' . self::planLabel((string) $payload['plan']) . ' features.'];
        }
        return ['ok' => true, 'error' => null, 'payload' => $payload];
    }

    /** Days until expiry (null = never expires / no key). */
    public static function daysLeft(): ?int
    {
        $exp = self::state()['expires'];
        if (!$exp || self::state()['source'] !== 'key') {
            return null;
        }
        return (int) floor((strtotime($exp . ' 23:59:59') - time()) / 86400);
    }

    public static function currentHost(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            $host = (string) (parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST) ?? '');
        }
        return strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
    }

    public static function domainMatches(string $host, string $domain): bool
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $domain = preg_replace('/^www\./', '', strtolower($domain)) ?? $domain;
        return $domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain));
    }

    public static function reset(): void
    {
        self::$state = null;
    }

    public static function b64urlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): ?string
    {
        if ($s === '' || preg_match('/[^A-Za-z0-9_-]/', $s)) {
            return null;
        }
        $out = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        return $out === false ? null : $out;
    }
}
