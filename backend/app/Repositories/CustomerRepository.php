<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;

/**
 * Customers (shared by CRM, quotations, bookings, invoices).
 * Shared contract — other modules rely on these signatures; add new methods freely, don't change these.
 */
final class CustomerRepository
{
    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    /** Normalise a phone for matching: digits only, Indian 10-digit numbers without +91/0. */
    public static function normalizePhone(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        } elseif (strlen($d) === 11 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }
        return $d;
    }

    /**
     * Find a customer by phone or create one. Fills empty email/city on an existing record.
     * @param array{name:string, phone:string, email?:?string, city?:?string, source?:?string} $data
     * @return int customer id
     */
    public static function findOrCreate(array $data): int
    {
        $phone = self::normalizePhone((string) $data['phone']);
        if ($phone === '') {
            throw new \InvalidArgumentException('Customer phone is required');
        }
        $existing = Database::fetch('SELECT * FROM customers WHERE phone = ?', [$phone]);
        if ($existing) {
            $fill = [];
            foreach (['email', 'city'] as $k) {
                if (empty($existing[$k]) && !empty($data[$k])) {
                    $fill[$k] = $data[$k];
                }
            }
            if ($fill) {
                Database::update('customers', $fill, 'id = ?', [$existing['id']]);
            }
            return (int) $existing['id'];
        }
        return (int) Database::insert('customers', [
            'name' => trim((string) $data['name']) !== '' ? mb_substr(trim((string) $data['name']), 0, 120) : 'Customer',
            'phone' => $phone,
            'email' => ($data['email'] ?? '') !== '' ? $data['email'] : null,
            'city' => ($data['city'] ?? '') !== '' ? $data['city'] : null,
            'source' => ($data['source'] ?? '') !== '' ? $data['source'] : 'website',
            'created_by' => Auth::id(),
        ]);
    }

    /** For <select> pickers: latest customers. @return list<array{id:int,name:string,phone:string}> */
    public static function options(int $limit = 500): array
    {
        return Database::fetchAll('SELECT id, name, phone FROM customers ORDER BY name LIMIT ' . max(1, min(5000, $limit)));
    }

    // ── CRM additions (customers module) ─────────────────────────────────────

    /** Find by phone (any format — normalised first). @return array<string,mixed>|null */
    public static function findByPhone(string $phone, ?int $exceptId = null): ?array
    {
        $phone = self::normalizePhone($phone);
        if ($phone === '') {
            return null;
        }
        return $exceptId === null
            ? Database::fetch('SELECT * FROM customers WHERE phone = ?', [$phone])
            : Database::fetch('SELECT * FROM customers WHERE phone = ? AND id <> ?', [$phone, $exceptId]);
    }

    /**
     * Normalise list filters (unknown values dropped).
     * @return array{q:string,tag:string,source:string}
     */
    public static function filtersFrom(mixed $q, mixed $tag, mixed $source): array
    {
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';
        $tag = is_string($tag) ? mb_strtolower(mb_substr(trim($tag), 0, 40)) : '';
        $sources = config('crm')['sources'] ?? [];
        $source = is_string($source) && isset($sources[$source]) ? $source : '';
        return ['q' => $q, 'tag' => $tag, 'source' => $source];
    }

    /** @param array{q:string,tag:string,source:string} $f @return array{0:string,1:list<mixed>} */
    private static function where(array $f): array
    {
        $sql = [];
        $params = [];
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $digits = self::normalizePhone($f['q']);
            $sql[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.city LIKE ?)';
            array_push($params, $like, strlen($digits) >= 3 ? '%' . $digits . '%' : $like, $like, $like);
        }
        if ($f['tag'] !== '') {
            $sql[] = "FIND_IN_SET(?, REPLACE(LOWER(COALESCE(c.tags, '')), ', ', ',')) > 0";
            $params[] = $f['tag'];
        }
        if ($f['source'] !== '') {
            $sql[] = 'c.source = ?';
            $params[] = $f['source'];
        }
        return [$sql === [] ? '1=1' : implode(' AND ', $sql), $params];
    }

    /** List columns: trips (non-cancelled bookings), total spent (their booking totals), last activity. */
    private const LIST_SELECT = "SELECT c.*,
            (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = c.id AND b.status <> 'cancelled') AS trips,
            (SELECT COALESCE(SUM(b.total_amount), 0) FROM bookings b WHERE b.customer_id = c.id AND b.status <> 'cancelled') AS total_spent,
            GREATEST(
                c.updated_at,
                COALESCE((SELECT MAX(la.created_at) FROM lead_activities la WHERE la.customer_id = c.id), c.created_at),
                COALESCE((SELECT MAX(e.created_at) FROM enquiries e WHERE e.customer_id = c.id), c.created_at),
                COALESCE((SELECT MAX(b.created_at) FROM bookings b WHERE b.customer_id = c.id), c.created_at)
            ) AS last_activity_at
        FROM customers c";

    /**
     * @param array{q:string,tag:string,source:string} $f
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}
     */
    public static function paginate(array $f, int $page, int $perPage = 20): array
    {
        [$where, $params] = self::where($f);
        $total = (int) Database::value("SELECT COUNT(*) FROM customers c WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            self::LIST_SELECT . " WHERE {$where} ORDER BY last_activity_at DESC, c.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** @param array{q:string,tag:string,source:string} $f @return list<array<string,mixed>> */
    public static function forExport(array $f, int $limit = 20000): array
    {
        [$where, $params] = self::where($f);
        return Database::fetchAll(self::LIST_SELECT . " WHERE {$where} ORDER BY c.name LIMIT {$limit}", $params);
    }

    /** Every tag in use, for the filter. @return list<string> */
    public static function allTags(): array
    {
        $tags = [];
        foreach (Database::fetchAll("SELECT tags FROM customers WHERE tags IS NOT NULL AND tags <> ''") as $r) {
            foreach (self::tagList((string) $r['tags']) as $t) {
                $tags[$t] = true;
            }
        }
        $tags = array_keys($tags);
        sort($tags);
        return $tags;
    }

    /** "VIP, repeat , vip" → ['vip', 'repeat'] @return list<string> */
    public static function tagList(?string $tags): array
    {
        $out = [];
        foreach (explode(',', (string) $tags) as $t) {
            $t = mb_strtolower(trim($t));
            if ($t !== '' && !in_array($t, $out, true)) {
                $out[] = mb_substr($t, 0, 40);
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        return Database::insert('customers', self::columns($data) + ['created_by' => Auth::id()]);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('customers', self::columns($data), 'id = ?', [$id]);
    }

    /** @param array<string,mixed> $d @return array<string,mixed> */
    private static function columns(array $d): array
    {
        $tags = self::tagList($d['tags'] ?? '');
        return [
            'name' => $d['name'],
            'phone' => self::normalizePhone((string) $d['phone']),
            'email' => $d['email'] ?? null,
            'city' => $d['city'] ?? null,
            'address' => $d['address'] ?? null,
            'date_of_birth' => $d['date_of_birth'] ?? null,
            'anniversary' => $d['anniversary'] ?? null,
            'source' => ($d['source'] ?? '') !== '' ? $d['source'] : 'other',
            'tags' => $tags === [] ? null : implode(', ', $tags),
            'notes' => $d['notes'] ?? null,
        ];
    }

    /** What would stop a delete: counts of bookings / quotations / invoices. @return array{bookings:int,quotations:int,invoices:int} */
    public static function dependants(int $id): array
    {
        return [
            'bookings' => (int) Database::value('SELECT COUNT(*) FROM bookings WHERE customer_id = ?', [$id]),
            'quotations' => (int) Database::value('SELECT COUNT(*) FROM quotations WHERE customer_id = ?', [$id]),
            'invoices' => (int) Database::value('SELECT COUNT(*) FROM invoices WHERE customer_id = ?', [$id]),
        ];
    }

    /** Delete a customer with no bookings/quotations/invoices; unlinks enquiries, notes and tasks. */
    public static function delete(int $id): void
    {
        Database::transaction(static function () use ($id): void {
            Database::query('UPDATE enquiries SET customer_id = NULL WHERE customer_id = ?', [$id]);
            Database::query('DELETE FROM lead_activities WHERE customer_id = ? AND enquiry_id IS NULL', [$id]);
            Database::query('UPDATE lead_activities SET customer_id = NULL WHERE customer_id = ?', [$id]);
            Database::query("UPDATE tasks SET related_type = 'none', related_id = NULL WHERE related_type = 'customer' AND related_id = ?", [$id]);
            Database::query('DELETE FROM customers WHERE id = ?', [$id]);
        });
    }

    /** Money summary across their bookings. @return array{trips:int,total:float,paid:float,balance:float} */
    public static function totals(int $id): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS trips, COALESCE(SUM(b.total_amount), 0) AS total,
                    COALESCE(SUM((SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id)), 0) AS paid
             FROM bookings b WHERE b.customer_id = ? AND b.status <> 'cancelled'",
            [$id],
        ) ?? ['trips' => 0, 'total' => 0, 'paid' => 0];
        $total = (float) $row['total'];
        $paid = (float) $row['paid'];
        return ['trips' => (int) $row['trips'], 'total' => $total, 'paid' => $paid, 'balance' => $total - $paid];
    }

    /** @return list<array<string,mixed>> with paid + balance per booking */
    public static function bookings(int $id): array
    {
        return Database::fetchAll(
            'SELECT b.*, (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id) AS paid
             FROM bookings b WHERE b.customer_id = ? ORDER BY b.travel_start DESC, b.id DESC LIMIT 100',
            [$id],
        );
    }

    /** @return list<array<string,mixed>> */
    public static function quotations(int $id): array
    {
        return Database::fetchAll(
            'SELECT id, number, title, total, status, travel_start, created_at FROM quotations WHERE customer_id = ? ORDER BY created_at DESC, id DESC LIMIT 100',
            [$id],
        );
    }

    /** @return list<array<string,mixed>> */
    public static function enquiries(int $id): array
    {
        return Database::fetchAll('SELECT * FROM enquiries WHERE customer_id = ? ORDER BY created_at DESC, id DESC LIMIT 100', [$id]);
    }

    /** Display phone: 10-digit Indian numbers as "+91 98765 43210". */
    public static function displayPhone(?string $phone): string
    {
        $d = (string) $phone;
        return strlen($d) === 10 && ctype_digit($d) ? '+91 ' . substr($d, 0, 5) . ' ' . substr($d, 5) : $d;
    }

    /** "tel:+919876543210" */
    public static function telHref(?string $phone): string
    {
        $d = preg_replace('/[^0-9+]/', '', (string) $phone) ?? '';
        return 'tel:' . (strlen($d) === 10 ? '+91' . $d : $d);
    }

    /** Days until the next yearly occurrence of a date (birthday/anniversary), or null. */
    public static function daysUntil(?string $date): ?int
    {
        if (!$date || !($ts = strtotime($date))) {
            return null;
        }
        $today = new \DateTimeImmutable('today');
        $md = date('m-d', $ts);
        if ($md === '02-29' && !checkdate(2, 29, (int) $today->format('Y'))) {
            $md = '02-28';
        }
        $next = new \DateTimeImmutable($today->format('Y') . '-' . $md);
        if ($next < $today) {
            $year = (int) $today->format('Y') + 1;
            $next = new \DateTimeImmutable($year . '-' . ($md === '02-29' && !checkdate(2, 29, $year) ? '02-28' : $md));
        }
        return (int) $today->diff($next)->days;
    }

    public static function sourceLabel(?string $source): string
    {
        $s = (string) $source;
        return config('crm')['sources'][$s] ?? ($s === '' ? '—' : ucfirst($s));
    }
}
