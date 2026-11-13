<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Leads from the website enquiry form (admin "Enquiries" inbox). Not public content — never revalidated. */
final class EnquiryRepository
{
    /** status => [label, Tabler badge class] */
    public const STATUSES = [
        'new' => ['New', 'bg-blue-lt'],
        'contacted' => ['Contacted', 'bg-cyan-lt'],
        'quoted' => ['Quoted', 'bg-purple-lt'],
        'booked' => ['Booked', 'bg-green-lt'],
        'closed' => ['Closed', 'bg-secondary-lt'],
    ];

    public const SOURCES = [
        'website' => 'Contact form',
        'planner' => 'Trip planner',
        'package' => 'Package page',
    ];

    /**
     * Normalise list filters from the query string (unknown / malformed values are dropped).
     * $stage (CRM lead stage) is optional — Basic-plan callers simply leave it out.
     * @return array{q:string,status:string,from:string,to:string,stage:string}
     */
    public static function filtersFrom(mixed $q, mixed $status, mixed $from, mixed $to, mixed $stage = ''): array
    {
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';
        $status = is_string($status) && isset(self::STATUSES[$status]) ? $status : '';
        $stage = is_string($stage) && isset(self::stages()[$stage]) ? $stage : '';
        return ['q' => $q, 'status' => $status, 'from' => self::date($from), 'to' => self::date($to), 'stage' => $stage];
    }

    private static function date(mixed $v): string
    {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return '';
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : '';
    }

    /**
     * @param array{q:string,status:string,from:string,to:string,stage?:string} $f
     * @return array{0:string,1:list<mixed>}
     */
    private static function where(array $f): array
    {
        $sql = [];
        $params = [];
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR message LIKE ? OR destination LIKE ? OR package LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        if ($f['status'] !== '') {
            $sql[] = 'status = ?';
            $params[] = $f['status'];
        }
        if (($f['stage'] ?? '') !== '') {
            $sql[] = 'stage = ?';
            $params[] = $f['stage'];
        }
        if ($f['from'] !== '') {
            $sql[] = 'created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $sql[] = 'created_at < ?';
            $params[] = date('Y-m-d', strtotime($f['to'] . ' +1 day')) . ' 00:00:00';
        }
        return [$sql === [] ? '1=1' : implode(' AND ', $sql), $params];
    }

    /**
     * @param array{q:string,status:string,from:string,to:string,stage?:string} $f
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}
     */
    public static function paginate(array $f, int $page, int $perPage = 20): array
    {
        [$where, $params] = self::where($f);
        $total = (int) Database::value("SELECT COUNT(*) FROM enquiries WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            "SELECT * FROM enquiries WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * All matching rows for the CSV export (newest first, capped for safety).
     * @param array{q:string,status:string,from:string,to:string,stage?:string} $f
     * @return list<array<string,mixed>>
     */
    public static function forExport(array $f, int $limit = 20000): array
    {
        [$where, $params] = self::where($f);
        return Database::fetchAll(
            "SELECT * FROM enquiries WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT {$limit}",
            $params,
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM enquiries WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public static function recent(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        return Database::fetchAll("SELECT * FROM enquiries ORDER BY created_at DESC, id DESC LIMIT {$limit}");
    }

    public static function countByStatus(string $status): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM enquiries WHERE status = ?', [$status]);
    }

    public static function countSinceDays(int $days): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM enquiries WHERE created_at >= ?',
            [date('Y-m-d H:i:s', time() - $days * 86400)],
        );
    }

    public static function updateStatusNotes(int $id, string $status, ?string $notes): void
    {
        Database::update('enquiries', ['status' => $status, 'notes' => $notes], 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM enquiries WHERE id = ?', [$id]);
    }

    // ── Presentation helpers (used by views) ───────────────────────────────────

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status][0] ?? ucfirst($status);
    }

    public static function statusClass(string $status): string
    {
        return self::STATUSES[$status][1] ?? 'bg-secondary-lt';
    }

    public static function sourceLabel(?string $source): string
    {
        $source = (string) $source;
        return self::SOURCES[$source] ?? config('crm')['sources'][$source] ?? ($source === '' ? '—' : ucfirst($source));
    }

    /** "tel:+916006045211" */
    public static function telHref(?string $phone): string
    {
        return 'tel:' . preg_replace('/[^0-9+]/', '', (string) $phone);
    }

    /**
     * WhatsApp number: digits only. A bare 10-digit Indian mobile gets the 91 country code;
     * a leading 0 (trunk prefix) is dropped first.
     */
    public static function whatsappDigits(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($d) === 11 && $d[0] === '0') {
            $d = substr($d, 1);
        }
        return strlen($d) === 10 ? '91' . $d : $d;
    }

    public static function whatsappUrl(?string $phone, string $text = ''): string
    {
        $url = 'https://wa.me/' . self::whatsappDigits($phone);
        return $text !== '' ? $url . '?text=' . rawurlencode($text) : $url;
    }

    /** What the person asked about: package, else destination. */
    public static function tripLabel(array $row): string
    {
        $pkg = trim((string) ($row['package'] ?? ''));
        $dest = trim((string) ($row['destination'] ?? ''));
        if ($pkg !== '' && $dest !== '') {
            return $pkg . ' · ' . $dest;
        }
        return $pkg !== '' ? $pkg : ($dest !== '' ? $dest : '—');
    }

    public static function people(array $row): string
    {
        $a = (int) ($row['adults'] ?? 0);
        $c = (int) ($row['children'] ?? 0);
        $out = $a . ' ' . ($a === 1 ? 'adult' : 'adults');
        if ($c > 0) {
            $out .= ', ' . $c . ' ' . ($c === 1 ? 'child' : 'children');
        }
        return $out;
    }

    // ── CRM: lead pipeline (feature "crm") ─────────────────────────────────────

    public const PRIORITIES = ['high' => 'High', 'normal' => 'Normal', 'low' => 'Low'];

    /** @return array<string,array{label:string,color:string,status:string}> */
    public static function stages(): array
    {
        return config('crm')['stages'] ?? [];
    }

    public static function stageLabel(?string $stage): string
    {
        return self::stages()[(string) $stage]['label'] ?? ucfirst((string) $stage);
    }

    public static function stageColor(?string $stage): string
    {
        return self::stages()[(string) $stage]['color'] ?? 'secondary';
    }

    /** Basic-plan inbox status that matches a stage (config crm.stages.*.status). */
    public static function statusForStage(string $stage): string
    {
        return self::stages()[$stage]['status'] ?? 'new';
    }

    /** Stage to use when someone changes the Basic status: keep the current stage if it already maps to it. */
    public static function stageForStatus(string $status, string $currentStage): string
    {
        if (self::statusForStage($currentStage) === $status && isset(self::stages()[$currentStage])) {
            return $currentStage;
        }
        foreach (self::stages() as $key => $s) {
            if ($s['status'] === $status) {
                return $key;
            }
        }
        return $currentStage;
    }

    /** Every lead source (config crm + the website form's own values). @return array<string,string> */
    public static function allSources(): array
    {
        return [...(config('crm')['sources'] ?? []), ...self::SOURCES];
    }

    /** Active team members a lead can be assigned to. @return list<array{id:int,name:string}> */
    public static function assignees(): array
    {
        return Database::fetchAll('SELECT id, name FROM admins WHERE is_active = 1 ORDER BY name');
    }

    /**
     * Lead board / list filters.
     * @return array{q:string,assigned:string,priority:string,source:string,stage:string}
     */
    public static function leadFilters(mixed $q, mixed $assigned, mixed $priority, mixed $source, mixed $stage = ''): array
    {
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';
        $assigned = is_string($assigned) && ($assigned === 'me' || $assigned === 'none' || ctype_digit($assigned)) ? $assigned : '';
        $priority = is_string($priority) && isset(self::PRIORITIES[$priority]) ? $priority : '';
        $source = is_string($source) && isset(self::allSources()[$source]) ? $source : '';
        $stage = is_string($stage) && isset(self::stages()[$stage]) ? $stage : '';
        return ['q' => $q, 'assigned' => $assigned, 'priority' => $priority, 'source' => $source, 'stage' => $stage];
    }

    /** @param array{q:string,assigned:string,priority:string,source:string,stage:string} $f @return array{0:string,1:list<mixed>} */
    private static function leadWhere(array $f): array
    {
        $sql = [];
        $params = [];
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql[] = '(e.name LIKE ? OR e.phone LIKE ? OR e.email LIKE ? OR e.destination LIKE ? OR e.package LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($f['assigned'] === 'me') {
            $sql[] = 'e.assigned_to = ?';
            $params[] = (int) \App\Core\Auth::id();
        } elseif ($f['assigned'] === 'none') {
            $sql[] = 'e.assigned_to IS NULL';
        } elseif ($f['assigned'] !== '') {
            $sql[] = 'e.assigned_to = ?';
            $params[] = (int) $f['assigned'];
        }
        if ($f['priority'] !== '') {
            $sql[] = 'e.priority = ?';
            $params[] = $f['priority'];
        }
        if ($f['source'] !== '') {
            $sql[] = 'e.source = ?';
            $params[] = $f['source'];
        }
        if ($f['stage'] !== '') {
            $sql[] = 'e.stage = ?';
            $params[] = $f['stage'];
        }
        return [$sql === [] ? '1=1' : implode(' AND ', $sql), $params];
    }

    private const LEAD_SELECT = 'SELECT e.*, a.name AS assigned_name FROM enquiries e LEFT JOIN admins a ON a.id = e.assigned_to';

    /**
     * Kanban data: up to $perStage cards per stage (open stages by follow-up, closed ones newest first) + full counts.
     * @param array{q:string,assigned:string,priority:string,source:string,stage:string} $f
     * @return array{cards:array<string,list<array<string,mixed>>>,counts:array<string,int>,values:array<string,float>}
     */
    public static function board(array $f, int $perStage = 50): array
    {
        $f['stage'] = '';
        [$where, $params] = self::leadWhere($f);
        $counts = [];
        $values = [];
        foreach (Database::fetchAll("SELECT e.stage, COUNT(*) AS n, COALESCE(SUM(e.budget), 0) AS v FROM enquiries e WHERE {$where} GROUP BY e.stage", $params) as $r) {
            $counts[(string) $r['stage']] = (int) $r['n'];
            $values[(string) $r['stage']] = (float) $r['v'];
        }
        $cards = [];
        foreach (array_keys(self::stages()) as $stage) {
            $order = in_array($stage, ['won', 'lost'], true)
                ? 'e.updated_at DESC, e.id DESC'
                : "e.next_follow_up_at IS NULL, e.next_follow_up_at ASC, FIELD(e.priority, 'high', 'normal', 'low'), e.created_at DESC";
            $cards[$stage] = ($counts[$stage] ?? 0) === 0 ? [] : Database::fetchAll(
                self::LEAD_SELECT . " WHERE {$where} AND e.stage = ? ORDER BY {$order} LIMIT " . max(1, $perStage),
                [...$params, $stage],
            );
            $counts[$stage] ??= 0;
            $values[$stage] ??= 0.0;
        }
        return ['cards' => $cards, 'counts' => $counts, 'values' => $values];
    }

    /**
     * @param array{q:string,assigned:string,priority:string,source:string,stage:string} $f
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}
     */
    public static function leadPaginate(array $f, int $page, int $perPage = 20): array
    {
        [$where, $params] = self::leadWhere($f);
        $total = (int) Database::value("SELECT COUNT(*) FROM enquiries e WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            self::LEAD_SELECT . " WHERE {$where} ORDER BY e.created_at DESC, e.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** One lead with the assignee's name. @return array<string,mixed>|null */
    public static function findLead(int $id): ?array
    {
        return Database::fetch(self::LEAD_SELECT . ' WHERE e.id = ?', [$id]);
    }

    /** Move a lead to another stage; keeps the Basic status in sync and sets/clears the lost reason. */
    public static function moveStage(int $id, string $stage, ?string $lostReason = null): void
    {
        Database::update('enquiries', [
            'stage' => $stage,
            'status' => self::statusForStage($stage),
            'lost_reason' => $stage === 'lost' ? ($lostReason !== null && $lostReason !== '' ? mb_substr($lostReason, 0, 200) : null) : null,
        ], 'id = ?', [$id]);
    }

    /**
     * Save the CRM fields from the enquiry page.
     * @param array{stage:string,priority:string,assigned_to:?int,budget:?int,travel_date:?string,next_follow_up_at:?string,notes:?string,lost_reason:?string} $d
     */
    public static function updateLead(int $id, array $d): void
    {
        Database::update('enquiries', [
            'stage' => $d['stage'],
            'status' => self::statusForStage($d['stage']),
            'priority' => $d['priority'],
            'assigned_to' => $d['assigned_to'],
            'budget' => $d['budget'],
            'travel_date' => $d['travel_date'],
            'next_follow_up_at' => $d['next_follow_up_at'],
            'notes' => $d['notes'],
            'lost_reason' => $d['stage'] === 'lost' ? $d['lost_reason'] : null,
        ], 'id = ?', [$id]);
    }

    /** Manual entry (phone call, walk-in…). @param array<string,mixed> $d */
    public static function create(array $d): int
    {
        $stage = (string) ($d['stage'] ?? 'new');
        return Database::insert('enquiries', [
            'customer_id' => $d['customer_id'] ?? null,
            'name' => $d['name'],
            'phone' => $d['phone'],
            'email' => $d['email'] ?? null,
            'destination' => $d['destination'] ?? null,
            'package' => $d['package'] ?? null,
            'travel_month' => $d['travel_month'] ?? null,
            'travel_date' => $d['travel_date'] ?? null,
            'adults' => $d['adults'] ?? 2,
            'children' => $d['children'] ?? 0,
            'hotel_category' => $d['hotel_category'] ?? null,
            'budget' => $d['budget'] ?? null,
            'message' => $d['message'] ?? null,
            'source' => $d['source'] ?? 'phone',
            'status' => self::statusForStage($stage),
            'stage' => $stage,
            'priority' => $d['priority'] ?? 'normal',
            'assigned_to' => $d['assigned_to'] ?? null,
            'next_follow_up_at' => $d['next_follow_up_at'] ?? null,
        ]);
    }

    public static function linkCustomer(int $id, int $customerId): void
    {
        Database::update('enquiries', ['customer_id' => $customerId], 'id = ?', [$id]);
    }

    public static function setFollowUp(int $id, ?string $at): void
    {
        Database::update('enquiries', ['next_follow_up_at' => $at], 'id = ?', [$id]);
    }

    /** @param array<string,mixed> $row */
    public static function followUpOverdue(array $row): bool
    {
        $at = $row['next_follow_up_at'] ?? null;
        return $at !== null && !in_array($row['stage'] ?? '', ['won', 'lost'], true) && strtotime((string) $at) < time();
    }

    /** "Dec 2026" / "15 Dec 2026" — travel date if known, else travel month text. @param array<string,mixed> $row */
    public static function whenLabel(array $row): string
    {
        if (!empty($row['travel_date'])) {
            return nice_day((string) $row['travel_date']);
        }
        return trim((string) ($row['travel_month'] ?? '')) ?: '';
    }

    /**
     * Make sure a lead is linked to a customer (find by phone or create), tagging its timeline too.
     * Returns the customer id. Callers check License::has('customers') first.
     * @param array<string,mixed> $lead
     */
    public static function ensureCustomer(array $lead): int
    {
        if (!empty($lead['customer_id']) && CustomerRepository::find((int) $lead['customer_id'])) {
            return (int) $lead['customer_id'];
        }
        $source = (string) ($lead['source'] ?? '');
        $customerId = CustomerRepository::findOrCreate([
            'name' => (string) $lead['name'],
            'phone' => (string) $lead['phone'],
            'email' => ($lead['email'] ?? '') !== '' ? $lead['email'] : null,
            'source' => in_array($source, ['website', 'planner', 'package'], true) ? 'website' : ($source !== '' ? $source : 'website'),
        ]);
        self::linkCustomer((int) $lead['id'], $customerId);
        LeadActivityRepository::attachCustomer((int) $lead['id'], $customerId);
        return $customerId;
    }
}
