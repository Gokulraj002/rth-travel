<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Read-only business reports. Every method takes an inclusive date range (Y-m-d).
 * Booking-based figures use the booking date (created_at) and skip cancelled bookings;
 * collections use the payment date; GST uses the invoice date (issued + paid only).
 */
final class ReportRepository
{
    /** @return list<string> "YYYY-MM" for every month touching the range (max 48). */
    public static function months(string $from, string $to): array
    {
        $out = [];
        $d = new \DateTimeImmutable(substr($from, 0, 7) . '-01');
        $end = new \DateTimeImmutable(substr($to, 0, 7) . '-01');
        while ($d <= $end && count($out) < 48) {
            $out[] = $d->format('Y-m');
            $d = $d->modify('+1 month');
        }
        return $out;
    }

    /** @return array{0:string,1:string} datetime bounds */
    private static function dt(string $from, string $to): array
    {
        return [$from . ' 00:00:00', $to . ' 23:59:59'];
    }

    // ── Sales ────────────────────────────────────────────────────────────────

    /** @return array{months:list<array{month:string,bookings:int,value:float,collected:float}>,methods:array<string,float>,count:int,value:float,collected:float,avg:float} */
    public static function sales(string $from, string $to): array
    {
        [$a, $b] = self::dt($from, $to);
        $bk = [];
        foreach (Database::fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n, COALESCE(SUM(total_amount),0) AS v
             FROM bookings WHERE status <> 'cancelled' AND created_at BETWEEN ? AND ? GROUP BY m",
            [$a, $b],
        ) as $r) {
            $bk[$r['m']] = $r;
        }
        $col = [];
        foreach (Database::fetchAll(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS m, COALESCE(SUM(amount),0) AS v FROM payments WHERE paid_at BETWEEN ? AND ? GROUP BY m",
            [$from, $to],
        ) as $r) {
            $col[$r['m']] = (float) $r['v'];
        }
        $months = [];
        $count = 0;
        $value = 0.0;
        $collected = 0.0;
        foreach (self::months($from, $to) as $m) {
            $row = ['month' => $m, 'bookings' => (int) ($bk[$m]['n'] ?? 0), 'value' => (float) ($bk[$m]['v'] ?? 0), 'collected' => $col[$m] ?? 0.0];
            $count += $row['bookings'];
            $value += $row['value'];
            $collected += $row['collected'];
            $months[] = $row;
        }
        $methods = [];
        foreach (Database::fetchAll(
            'SELECT method, COALESCE(SUM(amount),0) AS v FROM payments WHERE paid_at BETWEEN ? AND ? GROUP BY method ORDER BY v DESC',
            [$from, $to],
        ) as $r) {
            $methods[(string) $r['method']] = (float) $r['v'];
        }
        return ['months' => $months, 'methods' => $methods, 'count' => $count, 'value' => $value, 'collected' => $collected,
                'avg' => $count > 0 ? $value / $count : 0.0];
    }

    // ── Outstanding (as of today, not date-filtered) ─────────────────────────

    /** @return list<array<string,mixed>> */
    public static function outstanding(): array
    {
        return Database::fetchAll(
            "SELECT * FROM (
                SELECT b.id, b.number, b.title, b.travel_start, b.travel_end, b.status, b.total_amount,
                       c.id AS customer_id, c.name AS customer_name, c.phone AS customer_phone,
                       (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.booking_id = b.id) AS paid
                FROM bookings b JOIN customers c ON c.id = b.customer_id
                WHERE b.status <> 'cancelled'
             ) t
             WHERE t.total_amount - t.paid > 0.004
             ORDER BY t.travel_start, t.id
             LIMIT 1000",
        );
    }

    // ── Leads ────────────────────────────────────────────────────────────────

    /** @return array{total:int,sources:array<string,int>,stages:array<string,int>,won:int,lost:int,conversion:float|null} */
    public static function leads(string $from, string $to): array
    {
        [$a, $b] = self::dt($from, $to);
        $sources = [];
        foreach (Database::fetchAll('SELECT source, COUNT(*) AS n FROM enquiries WHERE created_at BETWEEN ? AND ? GROUP BY source ORDER BY n DESC', [$a, $b]) as $r) {
            $sources[(string) $r['source']] = (int) $r['n'];
        }
        $stages = [];
        foreach (Database::fetchAll('SELECT stage, COUNT(*) AS n FROM enquiries WHERE created_at BETWEEN ? AND ? GROUP BY stage', [$a, $b]) as $r) {
            $stages[(string) $r['stage']] = (int) $r['n'];
        }
        $won = $stages['won'] ?? 0;
        $lost = $stages['lost'] ?? 0;
        return ['total' => array_sum($sources), 'sources' => $sources, 'stages' => $stages, 'won' => $won, 'lost' => $lost,
                'conversion' => $won + $lost > 0 ? $won / ($won + $lost) : null];
    }

    // ── Top packages & destinations ──────────────────────────────────────────

    /** @return list<array{name:string,bookings:int,revenue:float}> */
    public static function topPackages(string $from, string $to, int $limit = 10): array
    {
        [$a, $b] = self::dt($from, $to);
        $rows = Database::fetchAll(
            "SELECT COALESCE(p.title, 'Custom trips (no package)') AS name, COUNT(*) AS bookings, COALESCE(SUM(b.total_amount),0) AS revenue
             FROM bookings b LEFT JOIN packages p ON p.id = b.package_id
             WHERE b.status <> 'cancelled' AND b.created_at BETWEEN ? AND ?
             GROUP BY b.package_id, p.title ORDER BY revenue DESC, bookings DESC LIMIT " . max(1, min(100, $limit)),
            [$a, $b],
        );
        return array_map(static fn ($r) => ['name' => (string) $r['name'], 'bookings' => (int) $r['bookings'], 'revenue' => (float) $r['revenue']], $rows);
    }

    /** A booking counts once for every destination in its package. @return list<array{name:string,bookings:int,revenue:float}> */
    public static function topDestinations(string $from, string $to, int $limit = 10): array
    {
        [$a, $b] = self::dt($from, $to);
        $rows = Database::fetchAll(
            "SELECT COALESCE(d.name, pd.destination_slug) AS name, COUNT(DISTINCT b.id) AS bookings, COALESCE(SUM(b.total_amount),0) AS revenue
             FROM bookings b
             JOIN package_destinations pd ON pd.package_id = b.package_id
             LEFT JOIN destinations d ON d.slug = pd.destination_slug
             WHERE b.status <> 'cancelled' AND b.created_at BETWEEN ? AND ?
             GROUP BY pd.destination_slug, d.name ORDER BY bookings DESC, revenue DESC LIMIT " . max(1, min(100, $limit)),
            [$a, $b],
        );
        return array_map(static fn ($r) => ['name' => (string) $r['name'], 'bookings' => (int) $r['bookings'], 'revenue' => (float) $r['revenue']], $rows);
    }

    // ── Profit ───────────────────────────────────────────────────────────────

    /** @return array{months:list<array{month:string,revenue:float,cost:float,profit:float}>,bookings:list<array<string,mixed>>,revenue:float,cost:float,profit:float,margin:float|null} */
    public static function profit(string $from, string $to): array
    {
        [$a, $b] = self::dt($from, $to);
        $rows = Database::fetchAll(
            "SELECT b.id, b.number, b.title, b.created_at, b.travel_start, b.total_amount, c.name AS customer_name,
                    (SELECT COALESCE(SUM(s.cost_price),0) FROM booking_services s WHERE s.booking_id = b.id AND s.status <> 'cancelled') AS cost
             FROM bookings b JOIN customers c ON c.id = b.customer_id
             WHERE b.status <> 'cancelled' AND b.created_at BETWEEN ? AND ?
             ORDER BY b.created_at DESC, b.id DESC LIMIT 2000",
            [$a, $b],
        );
        $byMonth = [];
        foreach (self::months($from, $to) as $m) {
            $byMonth[$m] = ['month' => $m, 'revenue' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
        }
        $rev = 0.0;
        $cost = 0.0;
        foreach ($rows as &$r) {
            $r['revenue'] = (float) $r['total_amount'];
            $r['cost'] = (float) $r['cost'];
            $r['profit'] = $r['revenue'] - $r['cost'];
            $r['margin'] = $r['revenue'] > 0 ? $r['profit'] / $r['revenue'] : null;
            $m = substr((string) $r['created_at'], 0, 7);
            if (isset($byMonth[$m])) {
                $byMonth[$m]['revenue'] += $r['revenue'];
                $byMonth[$m]['cost'] += $r['cost'];
                $byMonth[$m]['profit'] += $r['profit'];
            }
            $rev += $r['revenue'];
            $cost += $r['cost'];
        }
        unset($r);
        return ['months' => array_values($byMonth), 'bookings' => $rows, 'revenue' => $rev, 'cost' => $cost, 'profit' => $rev - $cost,
                'margin' => $rev > 0 ? ($rev - $cost) / $rev : null];
    }

    // ── GST summary (for filing) ─────────────────────────────────────────────

    /** @return array{months:list<array<string,mixed>>,states:list<array<string,mixed>>,totals:array<string,float|int>} */
    public static function gst(string $from, string $to): array
    {
        $byMonth = [];
        foreach (self::months($from, $to) as $m) {
            $byMonth[$m] = ['month' => $m, 'invoices' => 0, 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'total' => 0.0];
        }
        foreach (Database::fetchAll(
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS m, COUNT(*) AS n, SUM(subtotal - discount) AS taxable,
                    SUM(cgst) AS cgst, SUM(sgst) AS sgst, SUM(igst) AS igst, SUM(total) AS total
             FROM invoices WHERE status IN ('issued','paid') AND issue_date BETWEEN ? AND ? GROUP BY m",
            [$from, $to],
        ) as $r) {
            if (isset($byMonth[$r['m']])) {
                $byMonth[$r['m']] = ['month' => $r['m'], 'invoices' => (int) $r['n'], 'taxable' => (float) $r['taxable'], 'cgst' => (float) $r['cgst'],
                                     'sgst' => (float) $r['sgst'], 'igst' => (float) $r['igst'], 'total' => (float) $r['total']];
            }
        }
        $states = array_map(static fn ($r) => [
            'state' => (string) ($r['place_of_supply'] ?: '—'), 'invoices' => (int) $r['n'], 'taxable' => (float) $r['taxable'],
            'cgst' => (float) $r['cgst'], 'sgst' => (float) $r['sgst'], 'igst' => (float) $r['igst'], 'total' => (float) $r['total'],
        ], Database::fetchAll(
            "SELECT place_of_supply, COUNT(*) AS n, SUM(subtotal - discount) AS taxable, SUM(cgst) AS cgst, SUM(sgst) AS sgst, SUM(igst) AS igst, SUM(total) AS total
             FROM invoices WHERE status IN ('issued','paid') AND issue_date BETWEEN ? AND ? GROUP BY place_of_supply ORDER BY taxable DESC",
            [$from, $to],
        ));
        $totals = ['invoices' => 0, 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'total' => 0.0];
        foreach ($byMonth as $row) {
            foreach ($totals as $k => $v) {
                $totals[$k] = $v + $row[$k];
            }
        }
        return ['months' => array_values($byMonth), 'states' => $states, 'totals' => $totals];
    }
}
