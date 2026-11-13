<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Own fleet + drivers, document expiry and upcoming assignments. */
final class VehicleRepository
{
    public const TYPES = [
        'sedan' => 'Sedan',
        'suv' => 'SUV',
        'tempo' => 'Tempo Traveller',
        'bus' => 'Bus / coach',
        '4x4' => '4x4',
        'other' => 'Other',
    ];

    /** Documents expiring within this many days get an orange badge. */
    public const SOON_DAYS = 30;

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[(string) $type] ?? ucfirst((string) $type);
    }

    /**
     * Expiry state for a document date: null (not set) | 'expired' | 'soon' (≤30 days) | 'ok'.
     * @return array{state:?string,days:?int}
     */
    public static function expiry(?string $date): array
    {
        if ($date === null || $date === '') {
            return ['state' => null, 'days' => null];
        }
        $today = new \DateTimeImmutable('today');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        if ($d === false) {
            return ['state' => null, 'days' => null];
        }
        $days = (int) $today->diff($d)->format('%r%a');
        return ['state' => $days < 0 ? 'expired' : ($days <= self::SOON_DAYS ? 'soon' : 'ok'), 'days' => $days];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM vehicles WHERE id = ?', [$id]);
    }

    /**
     * @param array{q?:string,type?:string,active?:string,docs?:string} $f
     * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int}
     */
    public static function paginate(array $f, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];
        $q = (string) ($f['q'] ?? '');
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(v.name LIKE ? OR v.registration_no LIKE ? OR v.driver_name LIKE ? OR v.driver_phone LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (isset(self::TYPES[(string) ($f['type'] ?? '')])) {
            $where[] = 'v.type = ?';
            $params[] = $f['type'];
        }
        if (in_array($f['active'] ?? '', ['0', '1'], true)) {
            $where[] = 'v.is_active = ?';
            $params[] = (int) $f['active'];
        }
        if (($f['docs'] ?? '') === 'attention') {
            $where[] = '(v.insurance_expiry <= DATE_ADD(CURDATE(), INTERVAL ' . self::SOON_DAYS . ' DAY)
                         OR v.permit_expiry <= DATE_ADD(CURDATE(), INTERVAL ' . self::SOON_DAYS . ' DAY))';
        }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value('SELECT COUNT(*) FROM vehicles v' . $sqlWhere, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $rows = Database::fetchAll(
            "SELECT v.*,
                    (SELECT MIN(bs.service_date) FROM booking_services bs
                      WHERE bs.vehicle_id = v.id AND bs.status <> 'cancelled' AND bs.service_date >= CURDATE()) AS next_date,
                    (SELECT COUNT(*) FROM booking_services bs
                      WHERE bs.vehicle_id = v.id AND bs.status <> 'cancelled' AND bs.service_date >= CURDATE()) AS upcoming_count
             FROM vehicles v" . $sqlWhere .
            ' ORDER BY v.is_active DESC, v.name, v.id LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** Active vehicles whose insurance or permit is expired / expiring within 30 days. */
    public static function attentionCount(): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM vehicles WHERE is_active = 1 AND (insurance_expiry <= DATE_ADD(CURDATE(), INTERVAL ' . self::SOON_DAYS . ' DAY)
               OR permit_expiry <= DATE_ADD(CURDATE(), INTERVAL ' . self::SOON_DAYS . ' DAY))',
        );
    }

    /**
     * Upcoming (today onwards) non-cancelled services that use a vehicle.
     * @return list<array<string,mixed>>
     */
    public static function nextAssignments(?int $vehicleId = null, int $limit = 20): array
    {
        $params = [];
        $vehicleSql = '';
        if ($vehicleId !== null) {
            $vehicleSql = ' AND bs.vehicle_id = ?';
            $params[] = $vehicleId;
        }
        return Database::fetchAll(
            "SELECT bs.id, bs.booking_id, bs.vehicle_id, bs.description, bs.service_date, bs.nights, bs.status,
                    b.number AS booking_number, b.title AS booking_title, b.pickup_point, b.adults, b.children,
                    c.name AS customer_name, c.phone AS customer_phone,
                    vh.name AS vehicle_name, vh.registration_no, vh.driver_name, vh.driver_phone
             FROM booking_services bs
             JOIN bookings b ON b.id = bs.booking_id
             JOIN vehicles vh ON vh.id = bs.vehicle_id
             LEFT JOIN customers c ON c.id = b.customer_id
             WHERE bs.vehicle_id IS NOT NULL AND bs.status <> 'cancelled' AND bs.service_date >= CURDATE()" . $vehicleSql . '
             ORDER BY bs.service_date, bs.id
             LIMIT ' . (int) $limit,
            $params,
        );
    }

    /**
     * Other non-cancelled services using this vehicle whose dates overlap [date, date + nights days].
     * @return list<array<string,mixed>>
     */
    public static function conflicts(int $vehicleId, string $date, int $nights = 0, ?int $excludeServiceId = null): array
    {
        $nights = max(0, $nights);
        return Database::fetchAll(
            "SELECT bs.id, bs.service_date, bs.nights, b.id AS booking_id, b.number AS booking_number, b.title AS booking_title
             FROM booking_services bs
             JOIN bookings b ON b.id = bs.booking_id
             WHERE bs.vehicle_id = ? AND bs.status <> 'cancelled' AND bs.service_date IS NOT NULL AND bs.id <> ?
               AND bs.service_date <= DATE_ADD(?, INTERVAL ? DAY)
               AND DATE_ADD(bs.service_date, INTERVAL COALESCE(bs.nights, 0) DAY) >= ?
             ORDER BY bs.service_date
             LIMIT 5",
            [$vehicleId, $excludeServiceId ?? 0, $date, $nights, $date],
        );
    }

    /**
     * Vehicles for <select> menus (active + $includeId).
     * @return list<array{id:int,label:string}>
     */
    public static function options(?int $includeId = null): array
    {
        $rows = Database::fetchAll(
            'SELECT id, name, registration_no, seats, driver_name, is_active FROM vehicles WHERE is_active = 1 OR id = ? ORDER BY name',
            [$includeId ?? 0],
        );
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'label' => trim((string) $r['name'] . ($r['registration_no'] ? ' · ' . $r['registration_no'] : '')
                . ' · ' . (int) $r['seats'] . ' seats' . ($r['driver_name'] ? ' · ' . $r['driver_name'] : '')
                . ((int) $r['is_active'] === 1 ? '' : ' (inactive)')),
        ], $rows);
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        return Database::insert('vehicles', $data);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('vehicles', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM vehicles WHERE id = ?', [$id]);
    }

    public static function toggle(int $id): void
    {
        Database::query('UPDATE vehicles SET is_active = 1 - is_active WHERE id = ?', [$id]);
    }

    public static function usageCount(int $id): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM booking_services WHERE vehicle_id = ?', [$id]);
    }
}
