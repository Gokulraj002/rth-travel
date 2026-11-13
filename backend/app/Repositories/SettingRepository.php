<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Site-wide key/value settings (contact details, social links …). */
final class SettingRepository
{
    public const KEYS = [
        'site_name', 'tagline', 'description',
        'phone_primary', 'phone_secondary', 'whatsapp_number',
        'email_primary', 'email_secondary',
        'address_1_line1', 'address_1_line2', 'address_2_line1', 'address_2_line2',
        'map_query',
        'instagram_url', 'facebook_url', 'youtube_url',
    ];

    /** Every known key (missing ones as ''). @return array<string,string> */
    public static function all(): array
    {
        $out = array_fill_keys(self::KEYS, '');
        foreach (Database::fetchAll('SELECT `key`, `value` FROM settings') as $row) {
            if (array_key_exists($row['key'], $out)) {
                $out[$row['key']] = (string) $row['value'];
            }
        }
        return $out;
    }

    /**
     * Upsert the given known keys in one transaction.
     * @param array<string,string|null> $values
     */
    public static function save(array $values): void
    {
        Database::transaction(static function () use ($values): void {
            foreach (self::KEYS as $key) {
                if (!array_key_exists($key, $values)) {
                    continue;
                }
                $value = (string) ($values[$key] ?? '');
                Database::query(
                    'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?',
                    [$key, $value, $value],
                );
            }
        });
    }
}
