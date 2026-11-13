<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Role permissions (config/permissions.php).
 *   Gate::can('bookings')          → bool for the signed-in admin
 *   Gate::authorize('payments')    → 403 page unless allowed
 * Route middleware: ['auth', 'can:bookings'].  View helper: can('bookings').
 */
final class Gate
{
    public static function can(string $permission): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $role = (string) ($user['role'] ?? '');
        if ($role === 'owner') {
            return true;
        }
        $perms = config('permissions')['roles'][$role]['permissions'] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    public static function authorize(string $permission): void
    {
        if (!self::can($permission)) {
            Response::abort(403, 'Your role does not have access to this section. Ask the owner if you need it.');
        }
    }

    /** @return array<string,array{label:string,desc:string,permissions:list<string>}> */
    public static function roles(): array
    {
        return config('permissions')['roles'];
    }

    /** Roles the owner may assign on the current plan. @return list<string> */
    public static function assignableRoles(): array
    {
        return License::has('roles') ? array_keys(self::roles()) : config('permissions')['basic_roles'];
    }

    public static function roleLabel(?string $role): string
    {
        return (string) (self::roles()[(string) $role]['label'] ?? ucfirst((string) $role));
    }
}
