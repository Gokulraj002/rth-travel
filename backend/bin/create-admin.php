<?php

declare(strict_types=1);

/*
 * Create (or reset the password of) an admin from the command line — e.g. over Hostinger SSH:
 *   php bin/create-admin.php owner@example.com "Your Name" owner
 * You will be prompted for the password (hidden). Role: owner | editor (default owner).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

[$script, $email, $name, $role] = array_pad($argv, 4, null);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php <email> \"<name>\" [owner|editor]\n");
    exit(1);
}
$name = $name ?: 'Admin';
$role = in_array($role, ['owner', 'editor'], true) ? $role : 'owner';

$password = getenv('RTH_ADMIN_PASSWORD') ?: '';
if ($password === '') {
    fwrite(STDOUT, 'Password (min 10 chars): ');
    if (stripos(PHP_OS, 'WIN') !== 0) {
        system('stty -echo');
    }
    $password = trim((string) fgets(STDIN));
    if (stripos(PHP_OS, 'WIN') !== 0) {
        system('stty echo');
    }
    fwrite(STDOUT, PHP_EOL);
}
if (mb_strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$email = mb_strtolower($email);
$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = Database::fetch('SELECT id FROM admins WHERE email = ?', [$email]);
if ($existing) {
    Database::update('admins', ['password_hash' => $hash, 'name' => $name, 'role' => $role, 'is_active' => 1], 'id = ?', [$existing['id']]);
    fwrite(STDOUT, "Updated admin {$email} ({$role}).\n");
} else {
    Database::insert('admins', ['name' => $name, 'email' => $email, 'password_hash' => $hash, 'role' => $role]);
    fwrite(STDOUT, "Created admin {$email} ({$role}).\n");
}
