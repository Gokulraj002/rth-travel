<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;

/**
 * Audit trail (table activity_log — written by App\Core\Activity::log()).
 * activity_log has no IP column, so none is shown.
 */
final class ActivityRepository
{
    public const PER_PAGE = 50;

    /** Friendly names for entity keys used across the modules (unknown keys are humanised). */
    private const ENTITY_LABELS = [
        'admin' => 'Team member', 'package' => 'Package', 'destination' => 'Destination', 'testimonial' => 'Review',
        'faq' => 'FAQ', 'enquiry' => 'Enquiry', 'settings' => 'Settings', 'setting' => 'Settings', 'customer' => 'Customer',
        'quotation' => 'Quotation', 'booking' => 'Booking', 'payment' => 'Payment', 'invoice' => 'Invoice',
        'vendor' => 'Vendor', 'vehicle' => 'Vehicle', 'template' => 'Message template', 'backup' => 'Backup',
        'task' => 'Follow-up', 'lead' => 'Lead', 'license' => 'License', 'business' => 'Business details',
        'media' => 'Image', 'site' => 'Website', 'booking_service' => 'Booking service', 'export' => 'Export',
    ];

    /**
     * Admin pages per entity: [url pattern, plan feature|null, permission|null|'owner'].
     * Links are only shown when the current user could open them.
     */
    private const ENTITY_LINKS = [
        'package' => ['/packages/{id}/edit', null, 'website'],
        'destination' => ['/destinations/{id}/edit', null, 'website'],
        'testimonial' => ['/testimonials/{id}/edit', null, 'website'],
        'faq' => ['/faqs/{id}/edit', null, 'website'],
        'enquiry' => ['/enquiries/{id}', null, 'enquiries'],
        'customer' => ['/customers/{id}', 'customers', 'customers'],
        'quotation' => ['/quotations/{id}', 'quotations', 'quotations'],
        'booking' => ['/bookings/{id}', 'bookings', 'bookings'],
        'invoice' => ['/invoices/{id}', 'invoices', 'invoices'],
        'vendor' => ['/vendors/{id}', 'vendors', 'vendors'],
        'template' => ['/templates/{id}/edit', 'templates', 'templates'],
        'admin' => ['/users/{id}/edit', null, 'owner'],
    ];

    /**
     * @param array{user:string,entity:string,action:string,from:string,to:string,q:string} $f
     * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int}
     */
    public static function paginate(array $f, int $page, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = self::where($f);
        $total = (int) Database::value("SELECT COUNT(*) FROM activity_log l WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            "SELECT l.*, a.name AS admin_name, a.role AS admin_role FROM activity_log l
             LEFT JOIN admins a ON a.id = l.admin_id
             WHERE {$where} ORDER BY l.created_at DESC, l.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Matching rows for the CSV export, newest first, in batches (keeps memory low).
     * @param array{user:string,entity:string,action:string,from:string,to:string,q:string} $f
     * @return \Generator<int,array<string,mixed>>
     */
    public static function cursor(array $f, int $limit = 100000, int $batch = 1000): \Generator
    {
        [$where, $params] = self::where($f);
        $offset = 0;
        while ($offset < $limit) {
            $rows = Database::fetchAll(
                "SELECT l.*, a.name AS admin_name FROM activity_log l LEFT JOIN admins a ON a.id = l.admin_id
                 WHERE {$where} ORDER BY l.created_at DESC, l.id DESC LIMIT {$batch} OFFSET {$offset}",
                $params,
            );
            foreach ($rows as $r) {
                yield $r;
            }
            if (count($rows) < $batch) {
                return;
            }
            $offset += $batch;
        }
    }

    /**
     * @param array{user:string,entity:string,action:string,from:string,to:string,q:string} $f
     * @return array{0:string,1:list<mixed>}
     */
    private static function where(array $f): array
    {
        $sql = ['1=1'];
        $params = [];
        if ($f['user'] !== '') {
            if ($f['user'] === 'system') {
                $sql[] = 'l.admin_id IS NULL';
            } else {
                $sql[] = 'l.admin_id = ?';
                $params[] = (int) $f['user'];
            }
        }
        if ($f['entity'] !== '') {
            $sql[] = 'l.entity = ?';
            $params[] = $f['entity'];
        }
        if ($f['action'] !== '') {
            $sql[] = 'l.action = ?';
            $params[] = $f['action'];
        }
        if ($f['from'] !== '') {
            $sql[] = 'l.created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $sql[] = 'l.created_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            // Who is matched through a sub-select so the COUNT(*) query needs no join.
            $sql[] = '(l.summary LIKE ? OR l.admin_id IN (SELECT a2.id FROM admins a2 WHERE a2.name LIKE ?) OR l.entity_id = ?)';
            array_push($params, $like, $like, ctype_digit($f['q']) ? (int) $f['q'] : -1);
        }
        return [implode(' AND ', $sql), $params];
    }

    /** @return list<string> */
    public static function entities(): array
    {
        return array_map('strval', array_column(Database::fetchAll('SELECT DISTINCT entity FROM activity_log ORDER BY entity'), 'entity'));
    }

    /** @return list<string> */
    public static function actions(): array
    {
        return array_map('strval', array_column(Database::fetchAll('SELECT DISTINCT action FROM activity_log ORDER BY action'), 'action'));
    }

    public static function entityLabel(string $entity): string
    {
        return self::ENTITY_LABELS[$entity] ?? ucfirst(str_replace(['_', '-'], ' ', $entity));
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'login' => 'Signed in',
            'logout' => 'Signed out',
            'backup' => 'Backup',
            default => ucfirst(str_replace(['_', '-'], ' ', $action)),
        };
    }

    /** CSS modifier for the action badge. */
    public static function actionTone(string $action): string
    {
        return match (true) {
            in_array($action, ['created', 'added', 'converted', 'restored'], true) => 'green',
            in_array($action, ['updated', 'edited', 'toggled', 'reordered', 'duplicated', 'status', 'stage'], true) => 'blue',
            in_array($action, ['deleted', 'removed', 'cancelled', 'voided'], true) => 'red',
            in_array($action, ['exported', 'backup', 'downloaded'], true) => 'purple',
            in_array($action, ['login', 'logout'], true) => 'gray',
            default => 'amber',
        };
    }

    /** Admin URL for the entity, or null when there is no page / the user cannot open it. */
    public static function entityUrl(string $entity, mixed $entityId, string $action): ?string
    {
        if ($entityId === null || $action === 'deleted' || !isset(self::ENTITY_LINKS[$entity])) {
            return null;
        }
        [$pattern, $feature, $perm] = self::ENTITY_LINKS[$entity];
        if ($feature !== null && !feature($feature)) {
            return null;
        }
        if ($perm === 'owner' ? !Auth::isOwner() : ($perm !== null && !can($perm))) {
            return null;
        }
        return str_replace('{id}', (string) (int) $entityId, $pattern);
    }

    /** "just now", "5 min ago", "3 hours ago", "yesterday", "4 days ago", else the date. */
    public static function ago(?string $dt): string
    {
        $ts = $dt ? strtotime($dt) : false;
        if (!$ts) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 0) {
            return nice_date($dt);
        }
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = intdiv($diff, 60);
            return $m . ' min ago';
        }
        if ($diff < 86400) {
            $h = intdiv($diff, 3600);
            return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
        }
        $days = (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $ts))) / 86400);
        if ($days <= 1) {
            return 'yesterday';
        }
        if ($days < 7) {
            return $days . ' days ago';
        }
        return date('j M Y', $ts);
    }
}
