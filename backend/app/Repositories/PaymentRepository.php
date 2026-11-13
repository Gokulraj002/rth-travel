<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Sequence;

/** Money received from customers against bookings (receipts). */
final class PaymentRepository
{
    public const METHODS = [
        'upi' => 'UPI',
        'cash' => 'Cash',
        'bank' => 'Bank transfer',
        'card' => 'Card',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ];

    private const COLUMNS = 'pm.*, b.number AS booking_number, b.title AS booking_title, b.total_amount, b.status AS booking_status,
        b.customer_id, c.name AS customer_name, c.phone AS customer_phone';

    private const FROM = 'FROM payments pm JOIN bookings b ON b.id = pm.booking_id JOIN customers c ON c.id = b.customer_id';

    public static function methodLabel(?string $m): string
    {
        return self::METHODS[(string) $m] ?? ucfirst((string) $m);
    }

    // ── Filters ──────────────────────────────────────────────────────────────

    /** @return array{from:string,to:string,method:string,q:string} Defaults to the current month. */
    public static function filtersFrom(mixed $from, mixed $to, mixed $method, mixed $q, bool $hasAnyQuery): array
    {
        $from = BookingRepository::validDate($from) ? (string) $from : '';
        $to = BookingRepository::validDate($to) ? (string) $to : '';
        if (!$hasAnyQuery) {
            $from = date('Y-m-01');
            $to = date('Y-m-t');
        }
        if ($from !== '' && $to !== '' && $to < $from) {
            [$from, $to] = [$to, $from];
        }
        return [
            'from' => $from,
            'to' => $to,
            'method' => is_string($method) && isset(self::METHODS[$method]) ? $method : '',
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
        ];
    }

    /** @param array{from:string,to:string,method:string,q:string} $f @return array{0:string,1:list<mixed>} */
    private static function where(array $f): array
    {
        $sql = [];
        $params = [];
        if ($f['from'] !== '') {
            $sql[] = 'pm.paid_at >= ?';
            $params[] = $f['from'];
        }
        if ($f['to'] !== '') {
            $sql[] = 'pm.paid_at <= ?';
            $params[] = $f['to'];
        }
        if ($f['method'] !== '') {
            $sql[] = 'pm.method = ?';
            $params[] = $f['method'];
        }
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql[] = '(pm.receipt_no LIKE ? OR pm.reference LIKE ? OR b.number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return [$sql === [] ? '1=1' : implode(' AND ', $sql), $params];
    }

    /**
     * @param array{from:string,to:string,method:string,q:string} $f
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}
     */
    public static function paginate(array $f, int $page, int $perPage = 20): array
    {
        [$where, $params] = self::where($f);
        $total = (int) Database::value('SELECT COUNT(*) ' . self::FROM . " WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            'SELECT ' . self::COLUMNS . ' ' . self::FROM . " WHERE {$where} ORDER BY pm.paid_at DESC, pm.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Totals for the filtered range: overall + per method.
     * @param array{from:string,to:string,method:string,q:string} $f
     * @return array{total:float,count:int,methods:array<string,array{amount:float,count:int}>}
     */
    public static function summary(array $f): array
    {
        [$where, $params] = self::where($f);
        $rows = Database::fetchAll(
            'SELECT pm.method, SUM(pm.amount) AS amount, COUNT(*) AS cnt ' . self::FROM . " WHERE {$where} GROUP BY pm.method ORDER BY amount DESC",
            $params,
        );
        $out = ['total' => 0.0, 'count' => 0, 'methods' => []];
        foreach ($rows as $r) {
            $out['methods'][(string) $r['method']] = ['amount' => (float) $r['amount'], 'count' => (int) $r['cnt']];
            $out['total'] += (float) $r['amount'];
            $out['count'] += (int) $r['cnt'];
        }
        return $out;
    }

    /** @param array{from:string,to:string,method:string,q:string} $f @return list<array<string,mixed>> */
    public static function forExport(array $f, int $limit = 20000): array
    {
        [$where, $params] = self::where($f);
        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . ', a.name AS recorded_by ' . self::FROM . ' LEFT JOIN admins a ON a.id = pm.created_by'
            . " WHERE {$where} ORDER BY pm.paid_at ASC, pm.id ASC LIMIT " . max(1, $limit),
            $params,
        );
    }

    // ── Single payment ───────────────────────────────────────────────────────

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT ' . self::COLUMNS . ', b.travel_start, b.travel_end, c.email AS customer_email, c.city AS customer_city,
                    a.name AS recorded_by ' . self::FROM . ' LEFT JOIN admins a ON a.id = pm.created_by WHERE pm.id = ?',
            [$id],
        );
    }

    /** Payments of one booking, oldest first. @return list<array<string,mixed>> */
    public static function forBooking(int $bookingId): array
    {
        return Database::fetchAll(
            'SELECT pm.*, a.name AS recorded_by FROM payments pm LEFT JOIN admins a ON a.id = pm.created_by
             WHERE pm.booking_id = ? ORDER BY pm.paid_at ASC, pm.id ASC',
            [$bookingId],
        );
    }

    /** Total received on this booking up to and including $payment (by date, then id). */
    public static function paidUpTo(array $payment): float
    {
        return (float) Database::value(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = ? AND (paid_at < ? OR (paid_at = ? AND id <= ?))',
            [(int) $payment['booking_id'], $payment['paid_at'], $payment['paid_at'], (int) $payment['id']],
        );
    }

    /**
     * Record a payment; receipt number from Sequence inside the transaction.
     * @param array{amount:float,method:string,reference:?string,paid_at:string,notes:?string} $data
     * @return array{id:int,receipt_no:string}
     */
    public static function create(int $bookingId, array $data): array
    {
        return Database::transaction(static function () use ($bookingId, $data): array {
            $receipt = Sequence::next('receipt');
            $id = Database::insert('payments', [
                'receipt_no' => $receipt,
                'booking_id' => $bookingId,
                'amount' => round($data['amount'], 2),
                'method' => $data['method'],
                'reference' => $data['reference'],
                'paid_at' => $data['paid_at'],
                'notes' => $data['notes'],
                'created_by' => Auth::id(),
            ]);
            return ['id' => $id, 'receipt_no' => $receipt];
        });
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM payments WHERE id = ?', [$id]);
    }

    /** WhatsApp "Payment received" variables. @return array<string,string> */
    public static function messageVars(array $payment, float $balanceAfter): array
    {
        return [
            'customer_name' => (string) $payment['customer_name'],
            'booking_number' => (string) $payment['booking_number'],
            'trip' => (string) ($payment['booking_title'] ?? ''),
            'payment_amount' => inr2($payment['amount']),
            'receipt_no' => (string) $payment['receipt_no'],
            'amount' => inr($payment['total_amount'] ?? 0),
            'balance' => inr(max(0, $balanceAfter)),
        ];
    }
}
