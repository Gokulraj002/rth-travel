<?php

declare(strict_types=1);

/**
 * Vendor tool: issue a license key for a client after payment.
 *
 *   php backend/bin/license-generate.php --plan=pro --domain=reyaantrip.com --licensee="Reyaan Trip Holiday" [--expires=2027-11-13] [--months=12]
 *
 *   --plan       basic | pro | premium
 *   --domain     the client's domain (covers all sub-domains, e.g. admin.reyaantrip.com)
 *   --licensee   client / business name shown on the Plan page
 *   --expires    YYYY-MM-DD (or --months=N from today; omit both for a lifetime key)
 *   --key        path to the private key (default: ../license-keys/rth-license-private.key)
 *
 * Send the printed key to the client; they paste it on Admin → Plan & upgrade.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../app/bootstrap.php';

$opt = getopt('', ['plan:', 'domain:', 'licensee::', 'expires::', 'months::', 'key::']);
$plan = strtolower((string) ($opt['plan'] ?? ''));
$domain = strtolower(trim((string) ($opt['domain'] ?? '')));
$domain = (string) preg_replace('#^https?://#', '', $domain);
$domain = (string) preg_replace('#[/:].*$#', '', $domain);
$domain = (string) preg_replace('/^www\./', '', $domain);
if (!isset(config('plans')['plans'][$plan]) || $domain === '' || !preg_match('/^[a-z0-9.-]+$/', $domain)) {
    fwrite(STDERR, "Usage: php bin/license-generate.php --plan=pro|premium --domain=example.com --licensee=\"Name\" [--expires=YYYY-MM-DD | --months=12]\n");
    exit(1);
}
$expires = null;
if (!empty($opt['expires'])) {
    $ts = strtotime((string) $opt['expires']);
    if (!$ts) {
        fwrite(STDERR, "Invalid --expires date\n");
        exit(1);
    }
    $expires = date('Y-m-d', $ts);
} elseif (!empty($opt['months'])) {
    $expires = date('Y-m-d', strtotime('+' . (int) $opt['months'] . ' months'));
}
$keyFile = (string) ($opt['key'] ?? dirname(BASE_PATH) . '/license-keys/rth-license-private.key');
$secret = is_file($keyFile) ? base64_decode(trim((string) file_get_contents($keyFile)), true) : false;
if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    fwrite(STDERR, "Private key not found or invalid: {$keyFile}\nRun bin/license-keypair.php first (once).\n");
    exit(1);
}
$payload = [
    'plan' => $plan,
    'domain' => $domain,
    'licensee' => (string) ($opt['licensee'] ?? ''),
    'issued' => date('Y-m-d'),
    'expires' => $expires,
    'id' => bin2hex(random_bytes(6)),
];
$body = 'RTH1.' . App\Core\License::b64urlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$key = $body . '.' . App\Core\License::b64urlEncode(sodium_crypto_sign_detached($body, $secret));

$check = App\Core\License::verify($key, $domain);
if (!$check['ok']) {
    fwrite(STDERR, "Generated key failed self-check: {$check['error']}\n(Does app/Core/License.php contain the matching public key?)\n");
    exit(1);
}
echo "License key — " . strtoupper($plan) . " for {$domain}" . ($expires ? " until {$expires}" : ' (lifetime)') . ":\n\n{$key}\n";
