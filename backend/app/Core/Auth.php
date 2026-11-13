<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session-based admin authentication with brute-force throttling.
 * Throttle: max 5 failed attempts per email and 20 per IP within 15 minutes.
 */
final class Auth
{
    private const WINDOW_MINUTES = 15;
    private const MAX_PER_EMAIL = 5;
    private const MAX_PER_IP = 20;

    /** @var array<string,mixed>|null|false  false = not loaded yet */
    private static array|null|false $user = false;

    /** @return 'ok'|'invalid'|'throttled'|'inactive' */
    public static function attempt(string $email, string $password, string $ip): string
    {
        $email = mb_strtolower(trim($email));
        if (self::isThrottled($email, $ip)) {
            return 'throttled';
        }

        $admin = Database::fetch('SELECT * FROM admins WHERE email = ? LIMIT 1', [$email]);
        // Always run password_verify (even for unknown emails) to keep timing uniform.
        $hash = $admin['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $valid = password_verify($password, $hash);

        if (!$admin || !$valid) {
            Database::insert('login_attempts', ['ip' => $ip, 'email' => $email]);
            return 'invalid';
        }
        if ((int) $admin['is_active'] !== 1) {
            return 'inactive';
        }

        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('admins', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$admin['id']]);
        }

        Database::query('DELETE FROM login_attempts WHERE email = ?', [$email]);
        Database::update('admins', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);

        Session::regenerate(); // prevent session fixation
        Session::put('admin_id', (int) $admin['id']);
        Session::forget('_csrf');
        self::$user = false;
        Activity::log('login', 'admin', (int) $admin['id'], 'Signed in');
        return 'ok';
    }

    public static function isThrottled(string $email, string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $byEmail = (int) Database::value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > ?', [$email, $since]);
        $byIp = (int) Database::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', [$ip, $since]);
        return $byEmail >= self::MAX_PER_EMAIL || $byIp >= self::MAX_PER_IP;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$user === false) {
            $id = Session::get('admin_id');
            self::$user = $id
                ? Database::fetch('SELECT id, name, email, role, is_active, last_login_at FROM admins WHERE id = ? AND is_active = 1', [(int) $id])
                : null;
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isOwner(): bool
    {
        return (self::user()['role'] ?? '') === 'owner';
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
    }
}
