<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Hotels, houseboats, transporters, guides … (suppliers we pay).
 * "Payable" = SUM(cost_price − paid_to_vendor) over the vendor's non-cancelled booking_services.
 */
final class VendorRepository
{
    public const TYPES = [
        'hotel' => 'Hotel',
        'houseboat' => 'Houseboat',
        'transport' => 'Transport',
        'guide' => 'Guide / pony',
        'activity' => 'Activity',
        'other' => 'Other',
    ];

    public const TYPE_ICONS = [
        'hotel' => 'building',
        'houseboat' => 'bed',
        'transport' => 'car',
        'guide' => 'user',
        'activity' => 'sparkles',
        'other' => 'briefcase',
    ];

    /** Aggregated money per vendor (non-cancelled services only). */
    private const STATS_JOIN = "LEFT JOIN (
            SELECT vendor_id,
                   COUNT(DISTINCT booking_id) AS bookings_served,
                   COUNT(*) AS services_count,
                   SUM(cost_price) AS total_cost,
                   SUM(paid_to_vendor) AS total_paid,
                   SUM(cost_price - paid_to_vendor) AS payable
            FROM booking_services
            WHERE vendor_id IS NOT NULL AND status <> 'cancelled'
            GROUP BY vendor_id
        ) s ON s.vendor_id = v.id";

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[(string) $type] ?? ucfirst((string) $type);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT v.*, COALESCE(s.bookings_served, 0) AS bookings_served, COALESCE(s.services_count, 0) AS services_count,
                    COALESCE(s.total_cost, 0) AS total_cost, COALESCE(s.total_paid, 0) AS total_paid, COALESCE(s.payable, 0) AS payable
             FROM vendors v ' . self::STATS_JOIN . ' WHERE v.id = ?',
            [$id],
        );
    }

    /**
     * @param array{q?:string,type?:string,city?:string,active?:string} $f
     * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int}
     */
    public static function paginate(array $f, int $page = 1, int $perPage = 20): array
    {
        [$sqlWhere, $params] = self::where($f, true);
        $total = (int) Database::value('SELECT COUNT(*) FROM vendors v' . $sqlWhere, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $rows = Database::fetchAll(
            'SELECT v.*, COALESCE(s.bookings_served, 0) AS bookings_served, COALESCE(s.payable, 0) AS payable
             FROM vendors v ' . self::STATS_JOIN . $sqlWhere .
            ' ORDER BY v.is_active DESC, v.name, v.id LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Count per type for the tabs (respects search / city / active filters, ignores the type filter).
     * @param array{q?:string,type?:string,city?:string,active?:string} $f
     * @return array<string,int>
     */
    public static function typeCounts(array $f): array
    {
        [$sqlWhere, $params] = self::where($f, false);
        $out = array_fill_keys(array_keys(self::TYPES), 0);
        foreach (Database::fetchAll('SELECT v.type, COUNT(*) AS n FROM vendors v' . $sqlWhere . ' GROUP BY v.type', $params) as $r) {
            $out[(string) $r['type']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * @param array{q?:string,type?:string,city?:string,active?:string} $f
     * @return array{0:string,1:list<mixed>}
     */
    private static function where(array $f, bool $withType): array
    {
        $where = [];
        $params = [];
        $q = (string) ($f['q'] ?? '');
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(v.name LIKE ? OR v.contact_person LIKE ? OR v.phone LIKE ? OR v.email LIKE ? OR v.city LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($withType && isset(self::TYPES[(string) ($f['type'] ?? '')])) {
            $where[] = 'v.type = ?';
            $params[] = $f['type'];
        }
        if (($f['city'] ?? '') !== '') {
            $where[] = 'v.city = ?';
            $params[] = $f['city'];
        }
        if (in_array($f['active'] ?? '', ['0', '1'], true)) {
            $where[] = 'v.is_active = ?';
            $params[] = (int) $f['active'];
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /** @return list<string> */
    public static function cities(): array
    {
        return array_map('strval', array_column(
            Database::fetchAll("SELECT DISTINCT city FROM vendors WHERE city IS NOT NULL AND city <> '' ORDER BY city"),
            'city',
        ));
    }

    /**
     * Vendors for <select> menus: active ones, plus $includeId even if inactive (so an edit form keeps its value).
     * @return list<array{id:int,name:string,type:string,city:?string}>
     */
    public static function options(?int $includeId = null): array
    {
        $rows = Database::fetchAll(
            'SELECT id, name, type, city, is_active FROM vendors WHERE is_active = 1 OR id = ? ORDER BY name',
            [$includeId ?? 0],
        );
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'] . ((int) $r['is_active'] === 1 ? '' : ' (inactive)'),
            'type' => (string) $r['type'],
            'city' => $r['city'] !== null ? (string) $r['city'] : null,
        ], $rows);
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        return Database::insert('vendors', $data);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('vendors', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM vendors WHERE id = ?', [$id]);
    }

    public static function toggle(int $id): void
    {
        Database::query('UPDATE vendors SET is_active = 1 - is_active WHERE id = ?', [$id]);
    }

    /** How many booking services (any status) point to this vendor. */
    public static function usageCount(int $id): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM booking_services WHERE vendor_id = ?', [$id]);
    }

    /**
     * Service history for the vendor profile (newest trip first).
     * @return list<array<string,mixed>>
     */
    public static function services(int $id, int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT bs.*, b.number AS booking_number, b.title AS booking_title, b.travel_start, b.status AS booking_status
             FROM booking_services bs
             JOIN bookings b ON b.id = bs.booking_id
             WHERE bs.vendor_id = ?
             ORDER BY COALESCE(bs.service_date, b.travel_start) DESC, bs.id DESC
             LIMIT ' . (int) $limit,
            [$id],
        );
    }

    /**
     * Vendor-wise outstanding payables (only vendors we still owe money to).
     * @return list<array<string,mixed>>
     */
    public static function payables(string $type = ''): array
    {
        $params = [];
        $typeSql = '';
        if (isset(self::TYPES[$type])) {
            $typeSql = ' AND v.type = ?';
            $params[] = $type;
        }
        return Database::fetchAll(
            "SELECT v.id, v.name, v.type, v.phone, v.city, v.contact_person, v.bank_details, v.is_active,
                    COUNT(*) AS services_count,
                    SUM(CASE WHEN bs.cost_price - bs.paid_to_vendor > 0 THEN 1 ELSE 0 END) AS unpaid_count,
                    COUNT(DISTINCT bs.booking_id) AS bookings_served,
                    SUM(bs.cost_price) AS total_cost,
                    SUM(bs.paid_to_vendor) AS total_paid,
                    SUM(bs.cost_price - bs.paid_to_vendor) AS payable,
                    MIN(CASE WHEN bs.cost_price - bs.paid_to_vendor > 0 THEN COALESCE(bs.service_date, b.travel_start) END) AS oldest_due
             FROM booking_services bs
             JOIN vendors v ON v.id = bs.vendor_id
             JOIN bookings b ON b.id = bs.booking_id
             WHERE bs.status <> 'cancelled'" . $typeSql . '
             GROUP BY v.id, v.name, v.type, v.phone, v.city, v.contact_person, v.bank_details, v.is_active
             HAVING payable > 0
             ORDER BY payable DESC, v.name',
            $params,
        );
    }
}
