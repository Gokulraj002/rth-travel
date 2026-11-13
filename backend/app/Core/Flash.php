<?php

declare(strict_types=1);

namespace App\Core;

/** One-request flash messages + old form input + validation errors. */
final class Flash
{
    public static function success(string $msg): void { self::add('success', $msg); }
    public static function error(string $msg): void { self::add('danger', $msg); }
    public static function info(string $msg): void { self::add('info', $msg); }
    public static function warning(string $msg): void { self::add('warning', $msg); }

    private static function add(string $type, string $msg): void
    {
        $all = Session::get('_flash', []);
        $all[] = ['type' => $type, 'message' => $msg];
        Session::put('_flash', $all);
    }

    /** @return list<array{type:string,message:string}> */
    public static function pull(): array
    {
        $all = Session::get('_flash', []);
        Session::forget('_flash');
        return is_array($all) ? $all : [];
    }

    /** Keep submitted input + errors for the next request (after a redirect back). */
    public static function withInput(array $input, array $errors = []): void
    {
        unset($input['_token'], $input['password'], $input['password_confirmation'], $input['current_password']);
        Session::put('_old', $input);
        Session::put('_errors', $errors);
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::get('_old_now', []);
        return $old[$key] ?? $default;
    }

    /** @return array<string,string> */
    public static function errors(): array
    {
        return Session::get('_errors_now', []);
    }

    /** Called once per request: moves stored old input/errors into "now" and clears them. */
    public static function age(): void
    {
        Session::put('_old_now', Session::get('_old', []));
        Session::put('_errors_now', Session::get('_errors', []));
        Session::forget('_old');
        Session::forget('_errors');
    }
}
