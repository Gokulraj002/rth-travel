<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Sequence;

/**
 * Quotations (quotations + quotation_items + quotation_days). Child rows cascade on delete.
 * Money columns are DECIMAL → strings from PDO; totals are always recalculated here (never trust the browser).
 */
final class QuotationRepository
{
    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
    ];

    public const ITEM_TYPES = [
        'package' => 'Package',
        'hotel' => 'Hotel / stay',
        'transport' => 'Transport',
        'activity' => 'Activity',
        'flight' => 'Flight',
        'other' => 'Other',
    ];

    /** Same choices as the website enquiry form, plus "Mixed". */
    public const HOTEL_CATEGORIES = ['Budget', 'Standard', 'Deluxe', 'Luxury', 'Houseboat', 'Mixed'];

    /** Statuses still waiting for the customer (they auto-expire after valid_until). */
    private const OPEN = ['draft', 'sent'];

    // ── Status helpers ──────────────────────────────────────────────────────────

    /** Stored status, but draft/sent quotes past their valid-until date show as "expired". */
    public static function effectiveStatus(array $q): string
    {
        $status = (string) ($q['status'] ?? 'draft');
        $until = (string) ($q['valid_until'] ?? '');
        if (in_array($status, self::OPEN, true) && $until !== '' && $until < date('Y-m-d')) {
            return 'expired';
        }
        return $status;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? ucfirst($status);
    }

    // ── Totals ──────────────────────────────────────────────────────────────────

    /**
     * Server-side money maths. GST is charged on (subtotal − discount).
     * @param list<array{qty:float,unit_price:float}> $items
     * @return array{subtotal:float,discount:float,tax_percent:float,tax_amount:float,total:float,amounts:list<float>}
     */
    public static function calculate(array $items, float $discount, float $taxPercent): array
    {
        $amounts = [];
        $subtotal = 0.0;
        foreach ($items as $it) {
            $amount = round(max(0.0, (float) $it['qty']) * max(0.0, (float) $it['unit_price']), 2);
            $amounts[] = $amount;
            $subtotal += $amount;
        }
        $subtotal = round($subtotal, 2);
        $discount = round(min(max(0.0, $discount), $subtotal), 2);
        $taxPercent = round(min(max(0.0, $taxPercent), 100.0), 2);
        $tax = round(($subtotal - $discount) * $taxPercent / 100, 2);
        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_percent' => $taxPercent,
            'tax_amount' => $tax,
            'total' => round($subtotal - $discount + $tax, 2),
            'amounts' => $amounts,
        ];
    }

    // ── Lists ───────────────────────────────────────────────────────────────────

    /**
     * @param array{q?:string,status?:string} $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $where[] = '(q.number LIKE ? OR q.title LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status === 'expired') {
            $where[] = "(q.status = 'expired' OR (q.status IN ('draft','sent') AND q.valid_until < CURDATE()))";
        } elseif ($status === 'draft' || $status === 'sent') {
            $where[] = 'q.status = ? AND (q.valid_until IS NULL OR q.valid_until >= CURDATE())';
            $params[] = $status;
        } elseif ($status === 'open') {
            $where[] = "q.status IN ('draft','sent') AND (q.valid_until IS NULL OR q.valid_until >= CURDATE())";
        } elseif (isset(self::STATUSES[$status])) {
            $where[] = 'q.status = ?';
            $params[] = $status;
        }
        $customerId = (int) ($filters['customer_id'] ?? 0);
        if ($customerId > 0) {
            $where[] = 'q.customer_id = ?';
            $params[] = $customerId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $from = 'FROM quotations q LEFT JOIN customers c ON c.id = q.customer_id';

        $total = (int) Database::value("SELECT COUNT(*) {$from} {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT q.id, q.number, q.title, q.travel_start, q.travel_end, q.adults, q.children, q.total,
                    q.status, q.valid_until, q.created_at, q.customer_id, c.name AS customer_name, c.phone AS customer_phone
               {$from} {$whereSql}
              ORDER BY q.created_at DESC, q.id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** Numbers for the chips above the list. @return array<string,float|int> */
    public static function summary(): array
    {
        $open = Database::fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS v FROM quotations
              WHERE status IN ('draft','sent') AND (valid_until IS NULL OR valid_until >= CURDATE())",
        ) ?? ['n' => 0, 'v' => 0];
        $accepted = Database::fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS v FROM quotations
              WHERE status = 'accepted' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
        ) ?? ['n' => 0, 'v' => 0];
        $expired = (int) Database::value(
            "SELECT COUNT(*) FROM quotations WHERE status = 'expired' OR (status IN ('draft','sent') AND valid_until < CURDATE())",
        );
        return [
            'open_count' => (int) $open['n'], 'open_value' => (float) $open['v'],
            'accepted_count' => (int) $accepted['n'], 'accepted_value' => (float) $accepted['v'],
            'expired_count' => $expired,
        ];
    }

    // ── Single quotation ────────────────────────────────────────────────────────

    /** Quotation + customer columns (customer_name, customer_phone, customer_email, customer_city). */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT q.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.city AS customer_city,
                    c.address AS customer_address
               FROM quotations q LEFT JOIN customers c ON c.id = q.customer_id
              WHERE q.id = ?',
            [$id],
        );
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $row = Database::fetch('SELECT id FROM quotations WHERE public_token = ?', [$token]);
        return $row ? self::find((int) $row['id']) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function items(int $id): array
    {
        return Database::fetchAll('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY position, id', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public static function days(int $id): array
    {
        return Database::fetchAll('SELECT * FROM quotation_days WHERE quotation_id = ? ORDER BY day, id', [$id]);
    }

    /** Booking already created from this quotation (id, number) — or null. */
    public static function booking(int $id): ?array
    {
        return Database::fetch('SELECT id, number FROM bookings WHERE quotation_id = ? ORDER BY id LIMIT 1', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public static function packageOptions(): array
    {
        return Database::fetchAll('SELECT id, title, nights, days, price_from, status FROM packages ORDER BY sort_order, title');
    }

    public static function packageExists(int $id): bool
    {
        return (bool) Database::value('SELECT 1 FROM packages WHERE id = ?', [$id]);
    }

    // ── Writes ──────────────────────────────────────────────────────────────────

    /**
     * Create (id = null) or update a quotation and replace its items + days in one transaction.
     * @param array<string,mixed> $data  quotation columns (totals already calculated)
     * @param list<array{type:string,description:string,qty:float,unit_price:float,amount:float}> $items
     * @param list<array{title:string,description:string,stay:?string}> $days
     * @return int quotation id
     */
    public static function save(?int $id, array $data, array $items, array $days, ?callable $before = null): int
    {
        return Database::transaction(static function () use ($id, $data, $items, $days, $before): int {
            if ($before !== null) {
                $data = $before($data);
            }
            if ($id === null) {
                $data['number'] = Sequence::next('quotation');
                $data['public_token'] = bin2hex(random_bytes(16));
                $data['status'] = 'draft';
                $data['created_by'] = Auth::id();
                $id = Database::insert('quotations', $data);
            } else {
                Database::update('quotations', $data, 'id = ?', [$id]);
                Database::query('DELETE FROM quotation_items WHERE quotation_id = ?', [$id]);
                Database::query('DELETE FROM quotation_days WHERE quotation_id = ?', [$id]);
            }
            self::insertChildren($id, $items, $days);
            return $id;
        });
    }

    /** @param list<array<string,mixed>> $items @param list<array<string,mixed>> $days */
    private static function insertChildren(int $id, array $items, array $days): void
    {
        foreach (array_values($items) as $i => $it) {
            Database::insert('quotation_items', [
                'quotation_id' => $id,
                'type' => $it['type'],
                'description' => $it['description'],
                'qty' => $it['qty'],
                'unit_price' => $it['unit_price'],
                'amount' => $it['amount'],
                'position' => $i,
            ]);
        }
        foreach (array_values($days) as $i => $d) {
            Database::insert('quotation_days', [
                'quotation_id' => $id,
                'day' => min(255, $i + 1),
                'title' => $d['title'],
                'description' => (string) ($d['description'] ?? ''),
                'stay' => ($d['stay'] ?? '') !== '' ? $d['stay'] : null,
            ]);
        }
    }

    /** Copy as a fresh draft (new number + share link, new validity). Returns the new id. */
    public static function duplicate(int $id, int $validityDays): ?int
    {
        $q = Database::fetch('SELECT * FROM quotations WHERE id = ?', [$id]);
        if ($q === null) {
            return null;
        }
        return Database::transaction(static function () use ($q, $id, $validityDays): int {
            $copy = $q;
            unset($copy['id'], $copy['created_at'], $copy['updated_at']);
            $copy['number'] = Sequence::next('quotation');
            $copy['public_token'] = bin2hex(random_bytes(16));
            $copy['status'] = 'draft';
            $copy['sent_at'] = null;
            $copy['valid_until'] = date('Y-m-d', strtotime('+' . max(1, $validityDays) . ' days'));
            $copy['title'] = mb_substr((string) $q['title'], 0, 200);
            $copy['created_by'] = Auth::id();
            $newId = Database::insert('quotations', $copy);
            self::insertChildren($newId, self::items($id), self::days($id));
            return $newId;
        });
    }

    public static function setStatus(int $id, string $status): void
    {
        if ($status === 'sent') {
            Database::query("UPDATE quotations SET status = 'sent', sent_at = COALESCE(sent_at, NOW()) WHERE id = ?", [$id]);
            return;
        }
        Database::update('quotations', ['status' => $status], 'id = ?', [$id]);
    }

    /**
     * Mark as sent (only from draft/expired-draft — never downgrades accepted/rejected) and move a linked
     * lead from New/Contacted to "Quote sent".
     */
    public static function markSent(array $q): void
    {
        if (in_array((string) $q['status'], ['draft', 'sent', 'expired'], true)) {
            self::setStatus((int) $q['id'], 'sent');
        }
        $enquiryId = (int) ($q['enquiry_id'] ?? 0);
        if ($enquiryId > 0) {
            Database::query(
                "UPDATE enquiries SET stage = 'quoted', status = 'quoted' WHERE id = ? AND stage IN ('new','contacted')",
                [$enquiryId],
            );
        }
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM quotations WHERE id = ?', [$id]);
    }

    /**
     * Create a confirmed booking from a quotation (inside one transaction, row-locked against double clicks).
     * @return array{id:int,number:string,created:bool}
     */
    public static function convertToBooking(int $id, string $title): array
    {
        return Database::transaction(static function () use ($id, $title): array {
            $q = Database::fetch('SELECT * FROM quotations WHERE id = ? FOR UPDATE', [$id]);
            if ($q === null) {
                throw new \RuntimeException('Quotation not found');
            }
            $existing = Database::fetch('SELECT id, number FROM bookings WHERE quotation_id = ? ORDER BY id LIMIT 1', [$id]);
            if ($existing) {
                return ['id' => (int) $existing['id'], 'number' => (string) $existing['number'], 'created' => false];
            }
            $number = Sequence::next('booking');
            $bookingId = Database::insert('bookings', [
                'number' => $number,
                'customer_id' => (int) $q['customer_id'],
                'quotation_id' => $id,
                'enquiry_id' => $q['enquiry_id'] !== null ? (int) $q['enquiry_id'] : null,
                'package_id' => $q['package_id'] !== null ? (int) $q['package_id'] : null,
                'title' => mb_substr($title, 0, 200),
                'travel_start' => $q['travel_start'],
                'travel_end' => $q['travel_end'],
                'adults' => (int) $q['adults'],
                'children' => (int) $q['children'],
                'total_amount' => $q['total'],
                'status' => 'confirmed',
                'created_by' => Auth::id(),
            ]);
            Database::query("UPDATE quotations SET status = 'accepted' WHERE id = ?", [$id]);
            if ($q['enquiry_id'] !== null) {
                Database::query("UPDATE enquiries SET stage = 'won', status = 'booked' WHERE id = ?", [(int) $q['enquiry_id']]);
            }
            return ['id' => $bookingId, 'number' => $number, 'created' => true];
        });
    }
}
