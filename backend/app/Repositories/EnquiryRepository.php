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
     * @return array{q:string,status:string,from:string,to:string}
     */
    public static function filtersFrom(mixed $q, mixed $status, mixed $from, mixed $to): array
    {
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';
        $status = is_string($status) && isset(self::STATUSES[$status]) ? $status : '';
        return ['q' => $q, 'status' => $status, 'from' => self::date($from), 'to' => self::date($to)];
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
     * @param array{q:string,status:string,from:string,to:string} $f
     * @return array{0:string,1:list<mixed>}
     */
    private static function where(array $f): array
    {
        $sql = [];
        $params = [];
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR message LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($f['status'] !== '') {
            $sql[] = 'status = ?';
            $params[] = $f['status'];
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
     * @param array{q:string,status:string,from:string,to:string} $f
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
     * @param array{q:string,status:string,from:string,to:string} $f
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
        return self::SOURCES[$source] ?? ($source === '' ? '—' : ucfirst($source));
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
}
