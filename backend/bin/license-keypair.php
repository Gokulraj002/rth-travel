<?php

declare(strict_types=1);

/**
 * ONE-TIME (vendor only): create the Ed25519 signing keypair for license keys.
 *
 *   php backend/bin/license-keypair.php            → writes ../license-keys/rth-license-private.key
 *                                                    and puts the public key into app/Core/License.php
 *   php backend/bin/license-keypair.php --force    → replace an existing keypair (old keys stop working!)
 *
 * The PRIVATE key never goes to a client server or to git (license-keys/ is gitignored). Back it up safely —
 * if you lose it you cannot issue keys for existing installs.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../app/bootstrap.php';

$dir = dirname(BASE_PATH) . '/license-keys';
$privFile = $dir . '/rth-license-private.key';
if (is_file($privFile) && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "A private key already exists at {$privFile}. Use --force to replace it (existing license keys will stop working).\n");
    exit(1);
}
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "Cannot create {$dir}\n");
    exit(1);
}
$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public = base64_encode(sodium_crypto_sign_publickey($pair));
file_put_contents($privFile, base64_encode($secret) . "\n");
chmod($privFile, 0600);
file_put_contents($dir . '/rth-license-public.key', $public . "\n");

$lic = APP_PATH . '/Core/License.php';
$src = (string) file_get_contents($lic);
$src = (string) preg_replace("/public const PUBLIC_KEY = '[^']*';/", "public const PUBLIC_KEY = '{$public}';", $src, 1);
file_put_contents($lic, $src);

echo "Keypair created.\n  Private key: {$privFile}  (BACK THIS UP, never share it)\n  Public key:  {$public}  (written into app/Core/License.php)\n";
