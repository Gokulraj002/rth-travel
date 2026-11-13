<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;

/** Follow-ups / reminders (table tasks). Optional link to an enquiry, customer, quotation or booking. */
final class TaskRepository
{
    public const TABS = [
        'overdue' => 'Overdue',
        'today' => 'Today',
        'upcoming' => 'Upcoming',
        'done' => 'Done',
    ];

    public const RELATED_TYPES = ['enquiry', 'customer', 'quotation', 'booking', 'none'];

    /**
     * Open follow-ups due by the end of today (overdue + today) — for a sidebar / dashboard badge.
     * Pass an admin id to count only tasks assigned to that person.
     */
    public static function dueCount(?int $adminId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM tasks WHERE done_at IS NULL AND due_at < ?';
        $params = [self::endOfToday()];
        if ($adminId !== null) {
            $sql .= ' AND assigned_to = ?';
            $params[] = $adminId;
        }
        return (int) Database::value($sql, $params);
    }

    /**
     * @param array{q:string,assigned:string} $f
     * @return array{0:string,1:list<mixed>}
     */
    private static function where(string $tab, array $f): array
    {
        $now = date('Y-m-d H:i:s');
        $sql = [];
        $params = [];
        switch ($tab) {
            case 'overdue':
                $sql[] = 't.done_at IS NULL AND t.due_at < ?';
                $params[] = $now;
                break;
            case 'today':
                $sql[] = 't.done_at IS NULL AND t.due_at >= ? AND t.due_at < ?';
                array_push($params, $now, self::endOfToday());
                break;
            case 'upcoming':
                $sql[] = 't.done_at IS NULL AND t.due_at >= ?';
                $params[] = self::endOfToday();
                break;
            case 'done':
                $sql[] = 't.done_at IS NOT NULL';
                break;
        }
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql[] = '(t.title LIKE ? OR t.notes LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($f['assigned'] === 'me') {
            $sql[] = 't.assigned_to = ?';
            $params[] = (int) Auth::id();
        } elseif ($f['assigned'] === 'none') {
            $sql[] = 't.assigned_to IS NULL';
        } elseif ($f['assigned'] !== '' && ctype_digit($f['assigned'])) {
            $sql[] = 't.assigned_to = ?';
            $params[] = (int) $f['assigned'];
        }
        return [$sql === [] ? '1=1' : implode(' AND ', $sql), $params];
    }

    private const SELECT = "SELECT t.*, a.name AS assigned_name,
            e.name AS enquiry_name, c.name AS customer_name,
            q.number AS quotation_number, b.number AS booking_number
        FROM tasks t
        LEFT JOIN admins a ON a.id = t.assigned_to
        LEFT JOIN enquiries e ON t.related_type = 'enquiry' AND e.id = t.related_id
        LEFT JOIN customers c ON t.related_type = 'customer' AND c.id = t.related_id
        LEFT JOIN quotations q ON t.related_type = 'quotation' AND q.id = t.related_id
        LEFT JOIN bookings b ON t.related_type = 'booking' AND b.id = t.related_id";

    /**
     * @param array{q:string,assigned:string} $f
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}
     */
    public static function paginate(string $tab, array $f, int $page, int $perPage = 20): array
    {
        [$where, $params] = self::where($tab, $f);
        $total = (int) Database::value("SELECT COUNT(*) FROM tasks t WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $order = $tab === 'done' ? 't.done_at DESC, t.id DESC' : 't.due_at ASC, t.id ASC';
        $rows = Database::fetchAll(self::SELECT . " WHERE {$where} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}", $params);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** @param array{q:string,assigned:string} $f @return array<string,int> */
    public static function counts(array $f): array
    {
        $out = [];
        foreach (array_keys(self::TABS) as $tab) {
            [$where, $params] = self::where($tab, $f);
            $out[$tab] = (int) Database::value("SELECT COUNT(*) FROM tasks t WHERE {$where}", $params);
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(self::SELECT . ' WHERE t.id = ?', [$id]);
    }

    /** Open (and recently done) tasks linked to one record. @return list<array<string,mixed>> */
    public static function forRelated(string $type, int $id, bool $includeDone = false): array
    {
        $done = $includeDone ? '' : ' AND t.done_at IS NULL';
        return Database::fetchAll(
            self::SELECT . " WHERE t.related_type = ? AND t.related_id = ?{$done} ORDER BY t.done_at IS NOT NULL, t.due_at ASC LIMIT 50",
            [$type, $id],
        );
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        return Database::insert('tasks', [
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'due_at' => $data['due_at'],
            'related_type' => $data['related_type'] ?? 'none',
            'related_id' => $data['related_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('tasks', [
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'due_at' => $data['due_at'],
            'related_type' => $data['related_type'] ?? 'none',
            'related_id' => $data['related_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
        ], 'id = ?', [$id]);
    }

    public static function markDone(int $id): void
    {
        Database::query('UPDATE tasks SET done_at = NOW() WHERE id = ? AND done_at IS NULL', [$id]);
    }

    public static function reopen(int $id): void
    {
        Database::query('UPDATE tasks SET done_at = NULL WHERE id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM tasks WHERE id = ?', [$id]);
    }

    /** Unlink tasks from a record that is being deleted (keeps the reminder itself). */
    public static function detach(string $type, int $id): void
    {
        Database::query("UPDATE tasks SET related_type = 'none', related_id = NULL WHERE related_type = ? AND related_id = ?", [$type, $id]);
    }

    /** Earliest open follow-up for an enquiry (or null). */
    public static function nextOpenForEnquiry(int $enquiryId): ?string
    {
        $v = Database::value(
            "SELECT MIN(due_at) FROM tasks WHERE related_type = 'enquiry' AND related_id = ? AND done_at IS NULL",
            [$enquiryId],
        );
        return $v === null ? null : (string) $v;
    }

    /**
     * Keep one open "Follow up with …" task in step with enquiries.next_follow_up_at:
     * moves the open task that matched the old date, or creates a new one.
     */
    public static function syncEnquiryFollowUp(int $enquiryId, string $name, ?string $oldDue, ?string $newDue, ?int $assignedTo): void
    {
        if ($newDue === null || $newDue === '' || self::sameMinute($oldDue, $newDue)) {
            return;
        }
        $existing = null;
        if ($oldDue !== null && $oldDue !== '') {
            $existing = Database::fetch(
                "SELECT id FROM tasks WHERE related_type = 'enquiry' AND related_id = ? AND done_at IS NULL AND due_at = ? LIMIT 1",
                [$enquiryId, date('Y-m-d H:i:s', (int) strtotime($oldDue))],
            );
        }
        if ($existing) {
            Database::update('tasks', ['due_at' => $newDue], 'id = ?', [$existing['id']]);
            return;
        }
        self::create([
            'title' => mb_substr('Follow up with ' . $name, 0, 200),
            'due_at' => $newDue,
            'related_type' => 'enquiry',
            'related_id' => $enquiryId,
            'assigned_to' => $assignedTo ?? Auth::id(),
        ]);
    }

    // ── Pickers for the task form ────────────────────────────────────────────

    /** "Related to" options grouped by type. @return array<string,list<array{value:string,label:string}>> */
    public static function relatedOptions(): array
    {
        $groups = [];
        $groups['Enquiries'] = array_map(
            static fn ($r) => ['value' => 'enquiry:' . $r['id'], 'label' => $r['name'] . ' — ' . EnquiryRepository::tripLabel($r) . ' (#' . $r['id'] . ')'],
            Database::fetchAll("SELECT id, name, destination, package FROM enquiries WHERE stage NOT IN ('won','lost') ORDER BY created_at DESC LIMIT 200"),
        );
        $groups['Customers'] = array_map(
            static fn ($r) => ['value' => 'customer:' . $r['id'], 'label' => $r['name'] . ' (' . $r['phone'] . ')'],
            Database::fetchAll('SELECT id, name, phone FROM customers ORDER BY name LIMIT 300'),
        );
        $groups['Quotations'] = array_map(
            static fn ($r) => ['value' => 'quotation:' . $r['id'], 'label' => $r['number'] . ' — ' . $r['title']],
            Database::fetchAll('SELECT id, number, title FROM quotations ORDER BY created_at DESC LIMIT 100'),
        );
        $groups['Bookings'] = array_map(
            static fn ($r) => ['value' => 'booking:' . $r['id'], 'label' => $r['number'] . ' — ' . $r['title']],
            Database::fetchAll('SELECT id, number, title FROM bookings ORDER BY travel_start DESC LIMIT 100'),
        );
        return array_filter($groups);
    }

    /** Does the related record exist? */
    public static function relatedExists(string $type, int $id): bool
    {
        $table = match ($type) {
            'enquiry' => 'enquiries',
            'customer' => 'customers',
            'quotation' => 'quotations',
            'booking' => 'bookings',
            default => null,
        };
        return $table !== null && (int) Database::value("SELECT COUNT(*) FROM `{$table}` WHERE id = ?", [$id]) > 0;
    }

    // ── Presentation ─────────────────────────────────────────────────────────

    /** @param array<string,mixed> $t @return array{label:string,url:?string}|null */
    public static function related(array $t): ?array
    {
        $id = (int) ($t['related_id'] ?? 0);
        return match ((string) $t['related_type']) {
            'enquiry' => $t['enquiry_name'] !== null ? ['label' => 'Enquiry: ' . $t['enquiry_name'], 'url' => '/enquiries/' . $id] : null,
            'customer' => $t['customer_name'] !== null ? ['label' => 'Customer: ' . $t['customer_name'], 'url' => feature('customers') && can('customers') ? '/customers/' . $id : null] : null,
            'quotation' => $t['quotation_number'] !== null ? ['label' => 'Quotation ' . $t['quotation_number'], 'url' => feature('quotations') && can('quotations') ? '/quotations/' . $id : null] : null,
            'booking' => $t['booking_number'] !== null ? ['label' => 'Booking ' . $t['booking_number'], 'url' => feature('bookings') && can('bookings') ? '/bookings/' . $id : null] : null,
            default => null,
        };
    }

    /** @param array<string,mixed> $t */
    public static function isOverdue(array $t): bool
    {
        return $t['done_at'] === null && strtotime((string) $t['due_at']) < time();
    }

    private static function endOfToday(): string
    {
        return date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
    }

    private static function sameMinute(?string $a, ?string $b): bool
    {
        if ($a === null || $a === '' || $b === null || $b === '') {
            return false;
        }
        return date('Y-m-d H:i', (int) strtotime($a)) === date('Y-m-d H:i', (int) strtotime($b));
    }
}
