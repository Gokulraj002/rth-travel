<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Generic key/value access to the `settings` table (any key — website, business, license).
 *   Settings::get('company_gstin')          → stored value, or the config/business.php default, or ''
 *   Settings::set(['upi_id' => 'x@upi'])     → upsert
 * (App\Repositories\SettingRepository keeps handling the public website keys.)
 */
final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::fetchAll('SELECT `key`, `value` FROM settings') as $row) {
                self::$cache[(string) $row['key']] = (string) ($row['value'] ?? '');
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): string
    {
        $value = self::all()[$key] ?? '';
        if ($value !== '') {
            return $value;
        }
        if ($default !== null) {
            return $default;
        }
        $defaults = config('business');
        return isset($defaults[$key]) ? (string) $defaults[$key] : '';
    }

    /** @param array<string,string|int|float|null> $values */
    public static function set(array $values): void
    {
        Database::transaction(static function () use ($values): void {
            foreach ($values as $key => $value) {
                $value = (string) ($value ?? '');
                Database::query(
                    'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?',
                    [(string) $key, $value, $value],
                );
            }
        });
        self::$cache = null;
    }

    public static function forget(string $key): void
    {
        Database::query('DELETE FROM settings WHERE `key` = ?', [$key]);
        self::$cache = null;
    }
}
