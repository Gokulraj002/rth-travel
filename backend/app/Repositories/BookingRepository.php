<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Sequence;

/**
 * Bookings + travellers. Money: paid = SUM(payments.amount), balance = total_amount - paid.
 * Other modules may read from here (dashboard, reports) — keep public signatures stable.
 */
final class BookingRepository
{
    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /** List tabs (key => label). '' = all. */
    public const TABS = [
        'upcoming' => 'Upcoming',
        'ongoing' => 'On tour now',
        'past' => 'Past',
        'cancelled' => 'Cancelled',
        'all' => 'All',
    ];

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    /** Paid-per-booking derived table (joined as p). */
    private const PAID_JOIN = 'LEFT JOIN (SELECT booking_id, SUM(amount) AS paid FROM payments GROUP BY booking_id) p ON p.booking_id = b.id';

    private const LIST_COLUMNS = 'b.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
        COALESCE(p.paid, 0) AS paid, (b.total_amount - COALESCE(p.paid, 0)) AS balance';

    // ── Filters ──────────────────────────────────────────────────────────────

    /**
     * @return array{q:string,status:string,month:string,due:string,tab:string}
     */
    public static function filtersFrom(mixed $q, mixed $status, mixed $month, mixed $due, mixed $tab): array
    {
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';
        $status = is_string($status) && isset(self::STATUSES[$status]) ? $status : '';
        $month = is_string($month) && self::validMonth($month) ? $month : '';
        $due = $due === '1' || $due === 1 ? '1' : '';
        if (!is_string($tab) || !isset(self::TABS[$tab])) {
            // Searching or filtering by status → look everywhere; otherwise start on upcoming trips.
            $tab = ($q !== '' || $status !== '' || $month !== '' || $due !== '') ? 'all' : 'upcoming';
        }
        return ['q' => $q, 'status' => $status, 'month' => $month, 'due' => $due, 'tab' => $tab];
    }

    public static function validMonth(string $m): bool
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $m)) {
            return false;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m', $m);
        return $d !== false && $d->format('Y-m') === $m && (int) substr($m, 0, 4) >= 2000 && (int) substr($m, 0, 4) <= 2100;
    }

    public static function validDate(mixed $v): bool
    {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return false;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d !== false && $d->format('Y-m-d') === $v;
    }

    /**
     * @param array{q:string,status:string,month:string,due:string,tab:string} $f
     * @return array{0:string,1:list<mixed>}
     */
    private static function where(array $f, bool $withTab = true): array
    {
        $sql = [];
        $params = [];
        $today = date('Y-m-d');
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $parts = ['b.number LIKE ?', 'b.title LIKE ?', 'c.name LIKE ?', 'c.phone LIKE ?'];
            array_push($params, $like, $like, $like, $like);
            $digits = CustomerRepository::normalizePhone($f['q']);
            if (strlen($digits) >= 4 && $digits !== $f['q']) {
                $parts[] = 'c.phone LIKE ?';
                $params[] = '%' . $digits . '%';
            }
            $sql[] = '(' . implode(' OR ', $parts) . ')';
        }
        if ($f['status'] !== '') {
            $sql[] = 'b.status = ?';
            $params[] = $f['status'];
        }
        if ($f['month'] !== '') {
            $start = $f['month'] . '-01';
            $end = date('Y-m-t', strtotime($start));
            $sql[] = 'b.travel_start <= ? AND b.travel_end >= ?';
            array_push($params, $end, $start);
        }
        if ($f['due'] !== '') {
            $sql[] = "b.status <> 'cancelled' AND (b.total_amount - COALESCE(p.paid, 0)) > 0.009";
        }
        if ($withTab) {
            [$tabSql, $tabParams] = self::tabWhere($f['tab'], $today);
            if ($tabSql !== '') {
                $sql[] = $tabSql;
                array_push($params, ...$tabParams);
            }
        }
        return [$sql === [] ? '1=1' : implode(' AND ', $sql), $params];
    }

    /** @return array{0:string,1:list<mixed>} */
    private static function tabWhere(string $tab, string $today): array
    {
        return match ($tab) {
            'upcoming' => ["b.status <> 'cancelled' AND b.travel_start > ?", [$today]],
            'ongoing' => ["b.status <> 'cancelled' AND b.travel_start <= ? AND b.travel_end >= ?", [$today, $today]],
            'past' => ["b.status <> 'cancelled' AND b.travel_end < ?", [$today]],
            'cancelled' => ["b.status = 'cancelled'", []],
            default => ['', []],
        };
    }

    private static function orderFor(string $tab): string
    {
        return match ($tab) {
            'upcoming', 'ongoing' => 'b.travel_start ASC, b.id ASC',
            default => 'b.travel_start DESC, b.id DESC',
        };
    }

    /**
     * @param array{q:string,status:string,month:string,due:string,tab:string} $f
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}
     */
    public static function paginate(array $f, int $page, int $perPage = 20): array
    {
        [$where, $params] = self::where($f);
        $from = 'FROM bookings b JOIN customers c ON c.id = b.customer_id ' . self::PAID_JOIN;
        $total = (int) Database::value("SELECT COUNT(*) {$from} WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            'SELECT ' . self::LIST_COLUMNS . " {$from} WHERE {$where} ORDER BY " . self::orderFor($f['tab']) . " LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Count per tab for the current filters (ignoring the tab itself).
     * @param array{q:string,status:string,month:string,due:string,tab:string} $f
     * @return array<string,int>
     */
    public static function tabCounts(array $f): array
    {
        [$where, $params] = self::where($f, false);
        $today = date('Y-m-d');
        $row = Database::fetch(
            "SELECT
                SUM(b.status <> 'cancelled' AND b.travel_start > ?) AS upcoming,
                SUM(b.status <> 'cancelled' AND b.travel_start <= ? AND b.travel_end >= ?) AS ongoing,
                SUM(b.status <> 'cancelled' AND b.travel_end < ?) AS past,
                SUM(b.status = 'cancelled') AS cancelled,
                COUNT(*) AS `all`
             FROM bookings b JOIN customers c ON c.id = b.customer_id " . self::PAID_JOIN . " WHERE {$where}",
            [$today, $today, $today, $today, ...$params],
        ) ?? [];
        $out = [];
        foreach (array_keys(self::TABS) as $k) {
            $out[$k] = (int) ($row[$k] ?? 0);
        }
        return $out;
    }

    /**
     * @param array{q:string,status:string,month:string,due:string,tab:string} $f
     * @return list<array<string,mixed>>
     */
    public static function forExport(array $f, int $limit = 20000): array
    {
        [$where, $params] = self::where($f);
        return Database::fetchAll(
            'SELECT ' . self::LIST_COLUMNS . ', a.name AS assigned_name
             FROM bookings b JOIN customers c ON c.id = b.customer_id ' . self::PAID_JOIN . '
             LEFT JOIN admins a ON a.id = b.assigned_to
             WHERE ' . $where . ' ORDER BY ' . self::orderFor($f['tab']) . ' LIMIT ' . max(1, $limit),
            $params,
        );
    }

    // ── Single booking ───────────────────────────────────────────────────────

    /** Booking with customer + money fields, or null. @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT ' . self::LIST_COLUMNS . ', c.city AS customer_city,
                    a.name AS assigned_name, a.phone AS assigned_phone, pk.title AS package_title,
                    q.number AS quotation_number
             FROM bookings b JOIN customers c ON c.id = b.customer_id ' . self::PAID_JOIN . '
             LEFT JOIN admins a ON a.id = b.assigned_to
             LEFT JOIN packages pk ON pk.id = b.package_id
             LEFT JOIN quotations q ON q.id = b.quotation_id
             WHERE b.id = ?',
            [$id],
        );
    }

    /** @return list<array<string,mixed>> */
    public static function travellers(int $bookingId): array
    {
        return Database::fetchAll('SELECT * FROM booking_travellers WHERE booking_id = ? ORDER BY position, id', [$bookingId]);
    }

    public static function paidTotal(int $bookingId): float
    {
        return (float) Database::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = ?', [$bookingId]);
    }

    public static function paymentCount(int $bookingId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM payments WHERE booking_id = ?', [$bookingId]);
    }

    /** GST invoices raised for this booking (0 when the invoices table is unused). */
    public static function invoiceCount(int $bookingId): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM invoices WHERE booking_id = ? AND status <> 'void'", [$bookingId]);
    }

    /**
     * Create a booking (+ travellers) and return its id. Number from Sequence inside the transaction.
     * @param array<string,mixed> $data columns of `bookings` (without number)
     * @param list<array<string,mixed>> $travellers
     */
    public static function create(array $data, array $travellers): int
    {
        return Database::transaction(static function () use ($data, $travellers): int {
            $data['number'] = Sequence::next('booking');
            $data['created_by'] = Auth::id();
            $id = Database::insert('bookings', $data);
            self::replaceTravellers($id, $travellers);
            return $id;
        });
    }

    /**
     * @param array<string,mixed> $data
     * @param list<array<string,mixed>> $travellers
     */
    public static function update(int $id, array $data, array $travellers): void
    {
        Database::transaction(static function () use ($id, $data, $travellers): void {
            Database::update('bookings', $data, 'id = ?', [$id]);
            self::replaceTravellers($id, $travellers);
        });
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::update('bookings', ['status' => $status], 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        // travellers, payments and booking_services cascade via foreign keys.
        Database::query('DELETE FROM bookings WHERE id = ?', [$id]);
    }

    /** @param list<array<string,mixed>> $travellers */
    private static function replaceTravellers(int $bookingId, array $travellers): void
    {
        Database::query('DELETE FROM booking_travellers WHERE booking_id = ?', [$bookingId]);
        foreach (array_values($travellers) as $i => $t) {
            Database::insert('booking_travellers', [
                'booking_id' => $bookingId,
                'name' => $t['name'],
                'age' => $t['age'],
                'gender' => $t['gender'],
                'phone' => $t['phone'],
                'id_proof' => $t['id_proof'],
                'position' => $i,
            ]);
        }
    }

    // ── Voucher helpers ──────────────────────────────────────────────────────

    /**
     * Day-wise plan: the linked quotation's days if any, else the package itinerary.
     * @return list<array{day:int,title:string,description:string,stay:?string}>
     */
    public static function itinerary(array $booking): array
    {
        if (!empty($booking['quotation_id'])) {
            $days = Database::fetchAll('SELECT day, title, description, stay FROM quotation_days WHERE quotation_id = ? ORDER BY day, id', [(int) $booking['quotation_id']]);
            if ($days !== []) {
                return $days;
            }
        }
        if (!empty($booking['package_id'])) {
            return Database::fetchAll('SELECT day, title, description, stay FROM package_itinerary WHERE package_id = ? ORDER BY day, id', [(int) $booking['package_id']]);
        }
        return [];
    }

    /**
     * Inclusions / exclusions (lines): quotation first, then package.
     * @return array{inclusions:list<string>,exclusions:list<string>}
     */
    public static function inclusions(array $booking): array
    {
        $lines = static fn (?string $s): array => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $s) ?: []), static fn ($l) => $l !== ''));
        if (!empty($booking['quotation_id'])) {
            $q = Database::fetch('SELECT inclusions, exclusions FROM quotations WHERE id = ?', [(int) $booking['quotation_id']]);
            if ($q && (trim((string) $q['inclusions']) !== '' || trim((string) $q['exclusions']) !== '')) {
                return ['inclusions' => $lines($q['inclusions']), 'exclusions' => $lines($q['exclusions'])];
            }
        }
        if (!empty($booking['package_id'])) {
            $rows = Database::fetchAll("SELECT type, text FROM package_items WHERE package_id = ? AND type IN ('inclusion','exclusion') ORDER BY position, id", [(int) $booking['package_id']]);
            $out = ['inclusions' => [], 'exclusions' => []];
            foreach ($rows as $r) {
                $out[$r['type'] === 'inclusion' ? 'inclusions' : 'exclusions'][] = (string) $r['text'];
            }
            return $out;
        }
        return ['inclusions' => [], 'exclusions' => []];
    }

    // ── Calendar ─────────────────────────────────────────────────────────────

    /** Non-cancelled bookings overlapping [start, end]. @return list<array<string,mixed>> */
    public static function overlapping(string $start, string $end): array
    {
        return Database::fetchAll(
            "SELECT b.id, b.number, b.title, b.travel_start, b.travel_end, b.status, b.adults, b.children, b.pickup_point,
                    c.name AS customer_name, c.phone AS customer_phone
             FROM bookings b JOIN customers c ON c.id = b.customer_id
             WHERE b.status <> 'cancelled' AND b.travel_start <= ? AND b.travel_end >= ?
             ORDER BY b.travel_start, b.travel_end DESC, b.id",
            [$end, $start],
        );
    }

    // ── Pickers ──────────────────────────────────────────────────────────────

    /** @return list<array{id:int,title:string,nights:int,days:int}> */
    public static function packageOptions(): array
    {
        return Database::fetchAll('SELECT id, title, nights, days FROM packages ORDER BY title');
    }

    /** Active staff for "assigned to". @return list<array{id:int,name:string}> */
    public static function staffOptions(): array
    {
        return Database::fetchAll('SELECT id, name FROM admins WHERE is_active = 1 ORDER BY name');
    }

    // ── Display helpers ──────────────────────────────────────────────────────

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? ucfirst($status);
    }

    public static function people(array $b): string
    {
        $a = (int) ($b['adults'] ?? 0);
        $c = (int) ($b['children'] ?? 0);
        $s = $a . ' adult' . ($a === 1 ? '' : 's');
        if ($c > 0) {
            $s .= ', ' . $c . ' child' . ($c === 1 ? '' : 'ren');
        }
        return $s;
    }

    public static function nights(array $b): int
    {
        $s = strtotime((string) $b['travel_start']);
        $e = strtotime((string) $b['travel_end']);
        return ($s && $e && $e >= $s) ? (int) round(($e - $s) / 86400) : 0;
    }

    /** "5N / 6D" */
    public static function duration(array $b): string
    {
        $n = self::nights($b);
        return $n . 'N / ' . ($n + 1) . 'D';
    }

    /** "12 – 17 Nov 2026" style range. */
    public static function dateRange(?string $start, ?string $end): string
    {
        $s = $start ? strtotime($start) : false;
        $e = $end ? strtotime($end) : false;
        if (!$s) {
            return '—';
        }
        if (!$e || $e === $s) {
            return date('j M Y', $s);
        }
        if (date('Y-m', $s) === date('Y-m', $e)) {
            return date('j', $s) . ' – ' . date('j M Y', $e);
        }
        if (date('Y', $s) === date('Y', $e)) {
            return date('j M', $s) . ' – ' . date('j M Y', $e);
        }
        return date('j M Y', $s) . ' – ' . date('j M Y', $e);
    }

    /**
     * Friendly timing hint: ['text' => 'in 3 days', 'tone' => 'soon'] — tones: today|soon|later|ongoing|done|''.
     * @return array{text:string,tone:string}
     */
    public static function travelHint(array $b): array
    {
        if (($b['status'] ?? '') === 'cancelled') {
            return ['text' => '', 'tone' => ''];
        }
        $today = new \DateTimeImmutable('today');
        $start = new \DateTimeImmutable((string) $b['travel_start']);
        $end = new \DateTimeImmutable((string) $b['travel_end']);
        if ($start > $today) {
            $days = (int) $today->diff($start)->days;
            if ($days === 1) {
                return ['text' => 'tomorrow', 'tone' => 'soon'];
            }
            return ['text' => 'in ' . $days . ' days', 'tone' => $days <= 7 ? 'soon' : 'later'];
        }
        if ($start == $today) {
            return ['text' => 'starts today', 'tone' => 'today'];
        }
        if ($end >= $today) {
            $day = (int) $start->diff($today)->days + 1;
            return ['text' => 'ongoing · day ' . $day, 'tone' => 'ongoing'];
        }
        return ['text' => 'trip over', 'tone' => 'done'];
    }

    /** Template variables for WhatsApp messages about this booking. @return array<string,string> */
    public static function messageVars(array $b): array
    {
        return [
            'customer_name' => (string) $b['customer_name'],
            'booking_number' => (string) $b['number'],
            'trip' => (string) $b['title'],
            'travel_start' => nice_day((string) $b['travel_start']),
            'travel_end' => nice_day((string) $b['travel_end']),
            'amount' => inr($b['total_amount']),
            'paid' => inr($b['paid']),
            'balance' => inr(max(0, (float) $b['balance'])),
        ];
    }
}
