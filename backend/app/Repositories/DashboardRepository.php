<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Read-only numbers and short lists for the dashboard. Every query is small (indexed columns,
 * LIMITed, grouped in SQL) and uses ? params; dates are computed in PHP so they follow APP_TIMEZONE.
 * Money columns come back as DECIMAL strings — everything returned here is already cast.
 */
final class DashboardRepository
{
    // ── Enquiries / leads ──────────────────────────────────────────────────────

    public static function enquiriesBetween(string $from, string $to): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM enquiries WHERE created_at >= ? AND created_at < ?', [$from, $to]);
    }

    /** Leads still in play (pipeline stage not won / lost). */
    public static function openLeads(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM enquiries WHERE stage NOT IN ('won', 'lost')");
    }

    public static function newLeadsUntouched(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM enquiries WHERE stage = 'new'");
    }

    /** @return array{won:int, closed:int} */
    public static function conversion(): array
    {
        $row = Database::fetch(
            "SELECT SUM(stage = 'won') AS won, SUM(stage IN ('won', 'lost')) AS closed FROM enquiries WHERE stage IN ('won', 'lost')",
        );
        return ['won' => (int) ($row['won'] ?? 0), 'closed' => (int) ($row['closed'] ?? 0)];
    }

    /** Enquiries per day for the last $days days (oldest first). @return array{labels:list<string>, values:list<int>, total:int} */
    public static function enquiriesPerDay(int $days = 30): array
    {
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $rows = Database::fetchAll(
            'SELECT DATE(created_at) AS d, COUNT(*) AS n FROM enquiries WHERE created_at >= ? GROUP BY DATE(created_at)',
            [$start . ' 00:00:00'],
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['d']] = (int) $r['n'];
        }
        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $ts = strtotime($start . ' +' . $i . ' days');
            $labels[] = date('j M', $ts);
            $values[] = $map[date('Y-m-d', $ts)] ?? 0;
        }
        return ['labels' => $labels, 'values' => $values, 'total' => array_sum($values)];
    }

    /** Lead sources in the last $days days, biggest first (max 8 slices; the rest grouped as "Other"). @return array{labels:list<string>, values:list<int>} */
    public static function leadSources(int $days = 90): array
    {
        $rows = Database::fetchAll(
            'SELECT source, COUNT(*) AS n FROM enquiries WHERE created_at >= ? GROUP BY source ORDER BY n DESC',
            [date('Y-m-d 00:00:00', strtotime('-' . $days . ' days'))],
        );
        $names = (array) (config('crm')['sources'] ?? []) + ['planner' => 'Trip planner', 'package' => 'Package page'];
        $labels = [];
        $values = [];
        $other = 0;
        foreach ($rows as $i => $r) {
            if ($i >= 7) {
                $other += (int) $r['n'];
                continue;
            }
            $src = (string) $r['source'];
            $labels[] = $names[$src] ?? ($src === '' ? 'Unknown' : ucfirst($src));
            $values[] = (int) $r['n'];
        }
        if ($other > 0) {
            $labels[] = 'Other';
            $values[] = $other;
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /** @return list<array<string,mixed>> */
    public static function latestEnquiries(int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        return Database::fetchAll(
            "SELECT id, name, phone, destination, package, source, status, stage, priority, created_at
               FROM enquiries ORDER BY created_at DESC, id DESC LIMIT {$limit}",
        );
    }

    // ── Follow-ups (tasks) ──────────────────────────────────────────────────────

    /** Open tasks due today or earlier, oldest first. @return array{rows:list<array<string,mixed>>, total:int, overdue:int} */
    public static function dueTasks(int $limit = 7): array
    {
        $limit = max(1, min(20, $limit));
        $tomorrow = date('Y-m-d 00:00:00', strtotime('tomorrow'));
        $today = date('Y-m-d 00:00:00');
        $rows = Database::fetchAll(
            "SELECT t.id, t.title, t.due_at, t.related_type, t.related_id, a.name AS assignee
               FROM tasks t
               LEFT JOIN admins a ON a.id = t.assigned_to
              WHERE t.done_at IS NULL AND t.due_at < ?
              ORDER BY t.due_at ASC, t.id ASC
              LIMIT {$limit}",
            [$tomorrow],
        );
        $counts = Database::fetch(
            'SELECT COUNT(*) AS total, SUM(due_at < ?) AS overdue FROM tasks WHERE done_at IS NULL AND due_at < ?',
            [$today, $tomorrow],
        );
        return ['rows' => $rows, 'total' => (int) ($counts['total'] ?? 0), 'overdue' => (int) ($counts['overdue'] ?? 0)];
    }

    // ── Bookings & money ────────────────────────────────────────────────────────

    /** Bookings created this month (not cancelled). @return array{count:int, value:float} */
    public static function bookingsCreatedBetween(string $from, string $to): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total_amount), 0) AS v FROM bookings
              WHERE created_at >= ? AND created_at < ? AND status <> 'cancelled'",
            [$from, $to],
        );
        return ['count' => (int) ($row['n'] ?? 0), 'value' => (float) ($row['v'] ?? 0)];
    }

    /** Money received between two dates (payments.paid_at is a DATE; $to exclusive). */
    public static function collectedBetween(string $from, string $to): float
    {
        return (float) Database::value(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE paid_at >= ? AND paid_at < ?',
            [$from, $to],
        );
    }

    /** Balance still to collect on non-cancelled bookings. @return array{amount:float, count:int} */
    public static function outstanding(): array
    {
        $row = Database::fetch(
            "SELECT COALESCE(SUM(b.total_amount - COALESCE(p.paid, 0)), 0) AS due, COUNT(*) AS n
               FROM bookings b
               LEFT JOIN (SELECT booking_id, SUM(amount) AS paid FROM payments GROUP BY booking_id) p ON p.booking_id = b.id
              WHERE b.status <> 'cancelled' AND b.total_amount - COALESCE(p.paid, 0) > 0.009",
        );
        return ['amount' => (float) ($row['due'] ?? 0), 'count' => (int) ($row['n'] ?? 0)];
    }

    /**
     * Last $months calendar months (oldest first): money collected (payments) and value booked (new bookings).
     * @return array{labels:list<string>, collected:list<float>, booked:list<float>}
     */
    public static function monthlyMoney(int $months = 6): array
    {
        $start = date('Y-m-01', strtotime(date('Y-m-01') . ' -' . ($months - 1) . ' months'));
        $collected = [];
        foreach (Database::fetchAll(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, SUM(amount) AS v FROM payments WHERE paid_at >= ? GROUP BY ym",
            [$start],
        ) as $r) {
            $collected[(string) $r['ym']] = (float) $r['v'];
        }
        $booked = [];
        foreach (Database::fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(total_amount) AS v FROM bookings
              WHERE created_at >= ? AND status <> 'cancelled' GROUP BY ym",
            [$start . ' 00:00:00'],
        ) as $r) {
            $booked[(string) $r['ym']] = (float) $r['v'];
        }
        $out = ['labels' => [], 'collected' => [], 'booked' => []];
        for ($i = 0; $i < $months; $i++) {
            $ts = strtotime($start . ' +' . $i . ' months');
            $ym = date('Y-m', $ts);
            $out['labels'][] = date('M', $ts) . ($i === 0 || date('m', $ts) === '01' ? ' ' . date('y', $ts) : '');
            $out['collected'][] = $collected[$ym] ?? 0.0;
            $out['booked'][] = $booked[$ym] ?? 0.0;
        }
        return $out;
    }

    /** Trips starting in the next $days days (pending / confirmed). @return list<array<string,mixed>> */
    public static function upcomingDepartures(int $days = 14, int $limit = 7): array
    {
        $limit = max(1, min(20, $limit));
        $rows = Database::fetchAll(
            "SELECT b.id, b.number, b.title, b.travel_start, b.travel_end, b.adults, b.children, b.status, b.total_amount,
                    c.name AS customer_name, c.phone AS customer_phone,
                    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id) AS paid
               FROM bookings b
               LEFT JOIN customers c ON c.id = b.customer_id
              WHERE b.travel_start >= ? AND b.travel_start <= ? AND b.status IN ('pending', 'confirmed')
              ORDER BY b.travel_start ASC, b.id ASC
              LIMIT {$limit}",
            [date('Y-m-d'), date('Y-m-d', strtotime('+' . $days . ' days'))],
        );
        foreach ($rows as &$r) {
            $r['balance'] = max(0.0, (float) $r['total_amount'] - (float) $r['paid']);
        }
        unset($r);
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public static function recentPayments(int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        return Database::fetchAll(
            "SELECT p.id, p.receipt_no, p.amount, p.method, p.paid_at, p.booking_id, b.number AS booking_number, c.name AS customer_name
               FROM payments p
               LEFT JOIN bookings b ON b.id = p.booking_id
               LEFT JOIN customers c ON c.id = b.customer_id
              ORDER BY p.paid_at DESC, p.id DESC
              LIMIT {$limit}",
        );
    }

    // ── Alerts ─────────────────────────────────────────────────────────────────

    /** Active vehicles whose insurance or permit expires within $days days (or already expired). @return list<array<string,mixed>> */
    public static function vehicleExpiries(int $days = 30, int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        $until = date('Y-m-d', strtotime('+' . $days . ' days'));
        $rows = Database::fetchAll(
            "SELECT id, name, registration_no, insurance_expiry, permit_expiry FROM vehicles
              WHERE is_active = 1 AND ((insurance_expiry IS NOT NULL AND insurance_expiry <= ?) OR (permit_expiry IS NOT NULL AND permit_expiry <= ?))
              ORDER BY LEAST(COALESCE(insurance_expiry, '9999-12-31'), COALESCE(permit_expiry, '9999-12-31')) ASC
              LIMIT {$limit}",
            [$until, $until],
        );
        $out = [];
        foreach ($rows as $r) {
            foreach (['insurance_expiry' => 'Insurance', 'permit_expiry' => 'Permit'] as $col => $what) {
                if ($r[$col] !== null && (string) $r[$col] <= $until) {
                    $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'registration_no' => (string) ($r['registration_no'] ?? ''),
                              'what' => $what, 'date' => (string) $r[$col]];
                }
            }
        }
        usort($out, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
        return array_slice($out, 0, $limit);
    }

    /** Sent quotations whose validity ends within $days days. @return list<array<string,mixed>> */
    public static function quotationsExpiring(int $days = 3, int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        return Database::fetchAll(
            "SELECT q.id, q.number, q.title, q.total, q.valid_until, c.name AS customer_name
               FROM quotations q
               LEFT JOIN customers c ON c.id = q.customer_id
              WHERE q.status = 'sent' AND q.valid_until >= ? AND q.valid_until <= ?
              ORDER BY q.valid_until ASC, q.id ASC
              LIMIT {$limit}",
            [date('Y-m-d'), date('Y-m-d', strtotime('+' . $days . ' days'))],
        );
    }

    /** Trips starting within $days days that still have a balance to collect. @return list<array<string,mixed>> */
    public static function balanceDueSoon(int $days = 7, int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        $rows = Database::fetchAll(
            "SELECT b.id, b.number, b.title, b.travel_start, b.total_amount, c.name AS customer_name, COALESCE(p.paid, 0) AS paid
               FROM bookings b
               LEFT JOIN customers c ON c.id = b.customer_id
               LEFT JOIN (SELECT booking_id, SUM(amount) AS paid FROM payments GROUP BY booking_id) p ON p.booking_id = b.id
              WHERE b.status IN ('pending', 'confirmed') AND b.travel_start >= ? AND b.travel_start <= ?
                AND b.total_amount - COALESCE(p.paid, 0) > 0.009
              ORDER BY b.travel_start ASC, b.id ASC
              LIMIT {$limit}",
            [date('Y-m-d'), date('Y-m-d', strtotime('+' . $days . ' days'))],
        );
        foreach ($rows as &$r) {
            $r['balance'] = (float) $r['total_amount'] - (float) $r['paid'];
        }
        unset($r);
        return $rows;
    }

    // ── Website content (Basic plan) ──────────────────────────────────────────

    /** @return array{packages:int, packagesDraft:int, destinations:int, reviews:int} */
    public static function websiteCounts(): array
    {
        $row = Database::fetch(
            "SELECT (SELECT COUNT(*) FROM packages WHERE status = 'published') AS packages,
                    (SELECT COUNT(*) FROM packages WHERE status <> 'published') AS packagesDraft,
                    (SELECT COUNT(*) FROM destinations) AS destinations,
                    (SELECT COUNT(*) FROM testimonials) AS reviews",
        );
        return [
            'packages' => (int) ($row['packages'] ?? 0),
            'packagesDraft' => (int) ($row['packagesDraft'] ?? 0),
            'destinations' => (int) ($row['destinations'] ?? 0),
            'reviews' => (int) ($row['reviews'] ?? 0),
        ];
    }
}
