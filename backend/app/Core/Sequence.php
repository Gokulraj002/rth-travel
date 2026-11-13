<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gap-free, race-safe document numbers per type and year.
 *   Sequence::next('quotation') → "QT-2026-0001"
 *   Types: quotation | booking | invoice | receipt  (prefix from Business details: {type}_prefix)
 */
final class Sequence
{
    private const DEFAULT_PREFIX = ['quotation' => 'QT', 'booking' => 'BK', 'invoice' => 'INV', 'receipt' => 'RC'];

    public static function next(string $type): string
    {
        $year = date('Y');
        // LAST_INSERT_ID(expr) makes the increment atomic on this connection.
        Database::query(
            'INSERT INTO sequences (name, last_no) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1)',
            [$type . ':' . $year],
        );
        $n = (int) Database::value('SELECT LAST_INSERT_ID()');
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Settings::get($type . '_prefix', self::DEFAULT_PREFIX[$type] ?? strtoupper(substr($type, 0, 3)))) ?: 'DOC');
        return sprintf('%s-%s-%04d', $prefix, $year, $n);
    }
}
