<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Admin panel user accounts. Never select password_hash unless it is needed. */
final class AdminRepository
{
    /** Role labels (kept for older views; the source of truth is config/permissions.php via Gate). */
    public const ROLES = ['owner' => 'Owner', 'manager' => 'Manager', 'sales' => 'Sales', 'accounts' => 'Accounts', 'editor' => 'Editor'];

    private const COLUMNS = 'id, name, email, phone, role, is_active, last_login_at, created_at, updated_at';

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Database::fetchAll('SELECT ' . self::COLUMNS . ' FROM admins ORDER BY role = \'owner\' DESC, name ASC, id ASC');
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . ' FROM admins WHERE id = ?', [$id]);
    }

    public static function passwordHash(int $id): ?string
    {
        $h = Database::value('SELECT password_hash FROM admins WHERE id = ?', [$id]);
        return is_string($h) ? $h : null;
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?',
            [mb_strtolower($email), $exceptId ?? 0],
        ) > 0;
    }

    /** Active owners, optionally not counting one admin. */
    public static function activeOwnerCount(?int $exceptId = null): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM admins WHERE role = 'owner' AND is_active = 1 AND id <> ?",
            [$exceptId ?? 0],
        );
    }

    /** id => name for filters / dropdowns (all admins, active first). @return array<int,string> */
    public static function options(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT id, name FROM admins ORDER BY is_active DESC, name ASC, id ASC') as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }
        return $out;
    }

    /** Number of admins per role. @return array<string,int> */
    public static function countByRole(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT role, COUNT(*) AS n FROM admins GROUP BY role') as $r) {
            $out[(string) $r['role']] = (int) $r['n'];
        }
        return $out;
    }

    /** @param array{name:string,email:string,phone?:?string,role:string,is_active:int} $data */
    public static function create(array $data, string $password): int
    {
        return Database::insert('admins', [
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'is_active' => $data['is_active'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    /** @param array<string,mixed> $data name/email/phone/role/is_active (any subset) */
    public static function update(int $id, array $data): void
    {
        $allowed = array_intersect_key($data, array_flip(['name', 'email', 'phone', 'role', 'is_active']));
        if (isset($allowed['email'])) {
            $allowed['email'] = mb_strtolower((string) $allowed['email']);
        }
        if ($allowed !== []) {
            Database::update('admins', $allowed, 'id = ?', [$id]);
        }
    }

    public static function setPassword(int $id, string $password): void
    {
        Database::update('admins', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM admins WHERE id = ?', [$id]);
    }
}
