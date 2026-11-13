<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * What a booking consumes (hotel nights, houseboat, cab days, guide …) and what it costs us.
 * Profit = booking total_amount − SUM(cost_price) of non-cancelled services.
 */
final class BookingServiceRepository
{
    public const TYPES = [
        'hotel' => 'Hotel',
        'houseboat' => 'Houseboat',
        'transport' => 'Transport',
        'guide' => 'Guide / pony',
        'activity' => 'Activity',
        'flight' => 'Flight',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
    ];

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[(string) $type] ?? ucfirst((string) $type);
    }

    /** @return array<string,mixed>|null */
    public static function booking(int $id): ?array
    {
        return Database::fetch('SELECT * FROM bookings WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT bs.*, v.name AS vendor_name, vh.name AS vehicle_name
             FROM booking_services bs
             LEFT JOIN vendors v ON v.id = bs.vendor_id
             LEFT JOIN vehicles vh ON vh.id = bs.vehicle_id
             WHERE bs.id = ?',
            [$id],
        );
    }

    /** @return list<array<string,mixed>> */
    public static function forBooking(int $bookingId): array
    {
        return Database::fetchAll(
            'SELECT bs.*, v.name AS vendor_name, v.phone AS vendor_phone, v.type AS vendor_type,
                    vh.name AS vehicle_name, vh.registration_no, vh.driver_name, vh.driver_phone
             FROM booking_services bs
             LEFT JOIN vendors v ON v.id = bs.vendor_id
             LEFT JOIN vehicles vh ON vh.id = bs.vehicle_id
             WHERE bs.booking_id = ?
             ORDER BY bs.position, bs.service_date IS NULL, bs.service_date, bs.id',
            [$bookingId],
        );
    }

    /**
     * Money summary for the "Costs & profit" card.
     * @param array<string,mixed> $booking
     * @param list<array<string,mixed>> $services
     * @return array{selling:float,cost:float,paid:float,payable:float,profit:float,margin:?float,count:int,cancelled:int}
     */
    public static function totals(array $booking, array $services): array
    {
        $selling = (float) ($booking['total_amount'] ?? 0);
        $cost = 0.0;
        $paid = 0.0;
        $count = 0;
        $cancelled = 0;
        foreach ($services as $s) {
            if (($s['status'] ?? '') === 'cancelled') {
                $cancelled++;
                continue;
            }
            $count++;
            $cost += (float) $s['cost_price'];
            $paid += (float) $s['paid_to_vendor'];
        }
        $profit = $selling - $cost;
        return [
            'selling' => $selling,
            'cost' => round($cost, 2),
            'paid' => round($paid, 2),
            'payable' => round($cost - $paid, 2),
            'profit' => round($profit, 2),
            'margin' => $selling > 0 ? round($profit / $selling * 100, 1) : null,
            'count' => $count,
            'cancelled' => $cancelled,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function create(int $bookingId, array $data): int
    {
        $data['booking_id'] = $bookingId;
        $data['position'] = (int) Database::value('SELECT COALESCE(MAX(position), 0) FROM booking_services WHERE booking_id = ?', [$bookingId]) + 10;
        return Database::insert('booking_services', $data);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('booking_services', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM booking_services WHERE id = ?', [$id]);
    }

    /** Mark the supplier as fully paid (paid_to_vendor = cost_price). */
    public static function markPaid(int $id): void
    {
        Database::query('UPDATE booking_services SET paid_to_vendor = cost_price WHERE id = ?', [$id]);
    }
}
