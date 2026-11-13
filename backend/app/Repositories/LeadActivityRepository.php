<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;

/** Timeline entries on a lead / customer (table lead_activities): notes, calls, WhatsApp, emails, stage changes. */
final class LeadActivityRepository
{
    /** type => [label, icon, colour] — the first five can be logged by hand. */
    public const TYPES = [
        'note' => ['Note', 'note', 'secondary'],
        'call' => ['Call', 'phone', 'blue'],
        'whatsapp' => ['WhatsApp', 'whatsapp', 'green'],
        'email' => ['Email', 'mail', 'azure'],
        'meeting' => ['Meeting', 'users', 'purple'],
        'stage' => ['Stage changed', 'columns', 'orange'],
        'system' => ['System', 'sparkles', 'secondary'],
    ];

    /** Types a person can log from the "Log activity" form. */
    public const MANUAL_TYPES = ['note', 'call', 'whatsapp', 'email', 'meeting'];

    public static function log(?int $enquiryId, ?int $customerId, string $type, string $body, ?int $adminId = null): int
    {
        if (!isset(self::TYPES[$type])) {
            $type = 'note';
        }
        return Database::insert('lead_activities', [
            'enquiry_id' => $enquiryId,
            'customer_id' => $customerId,
            'admin_id' => $adminId ?? Auth::id(),
            'type' => $type,
            'body' => mb_substr($body, 0, 5000),
        ]);
    }

    /** @return list<array<string,mixed>> newest first, with the admin's name */
    public static function forEnquiry(int $enquiryId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT la.*, a.name AS admin_name FROM lead_activities la
             LEFT JOIN admins a ON a.id = la.admin_id
             WHERE la.enquiry_id = ? ORDER BY la.created_at DESC, la.id DESC LIMIT {$limit}",
            [$enquiryId],
        );
    }

    /**
     * Everything about a customer: entries tagged with the customer, plus entries on any of their enquiries.
     * @return list<array<string,mixed>>
     */
    public static function forCustomer(int $customerId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT la.*, a.name AS admin_name, e.name AS enquiry_name FROM lead_activities la
             LEFT JOIN admins a ON a.id = la.admin_id
             LEFT JOIN enquiries e ON e.id = la.enquiry_id
             WHERE la.customer_id = ? OR la.enquiry_id IN (SELECT id FROM enquiries WHERE customer_id = ?)
             ORDER BY la.created_at DESC, la.id DESC LIMIT {$limit}",
            [$customerId, $customerId],
        );
    }

    /** Tag existing enquiry entries with a customer (after "Convert to customer"). */
    public static function attachCustomer(int $enquiryId, int $customerId): void
    {
        Database::query('UPDATE lead_activities SET customer_id = ? WHERE enquiry_id = ? AND customer_id IS NULL', [$customerId, $enquiryId]);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type][0] ?? ucfirst($type);
    }

    public static function icon(string $type): string
    {
        return self::TYPES[$type][1] ?? 'note';
    }

    public static function colour(string $type): string
    {
        return self::TYPES[$type][2] ?? 'secondary';
    }
}
