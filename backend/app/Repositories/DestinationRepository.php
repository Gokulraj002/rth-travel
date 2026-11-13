<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Destinations + their ordered children (highlights, things to do, gallery).
 * Callers wrap multi-table writes in Database::transaction().
 */
final class DestinationRepository
{
    public const REGIONS = ['kashmir' => 'Kashmir', 'ladakh' => 'Ladakh', 'jammu' => 'Jammu'];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM destinations WHERE id = ?', [$id]);
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $exceptId === null
            ? (bool) Database::value('SELECT 1 FROM destinations WHERE slug = ? LIMIT 1', [$slug])
            : (bool) Database::value('SELECT 1 FROM destinations WHERE slug = ? AND id <> ? LIMIT 1', [$slug, $exceptId]);
    }

    /**
     * Filtered, paginated list.
     * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int}
     */
    public static function paginate(string $q = '', string $status = '', int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(name LIKE ? OR slug LIKE ? OR tagline LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (in_array($status, ['published', 'draft'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value('SELECT COUNT(*) FROM destinations' . $sqlWhere, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            'SELECT id, slug, name, region, tagline, altitude_m, image, card_image, status, sort_order, updated_at
               FROM destinations' . $sqlWhere . ' ORDER BY sort_order, id LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** @return array{highlights:list<string>, things:list<string>} */
    public static function items(int $id): array
    {
        $out = ['highlights' => [], 'things' => []];
        foreach (Database::fetchAll('SELECT type, text FROM destination_items WHERE destination_id = ? ORDER BY type, position, id', [$id]) as $r) {
            $out[$r['type'] === 'highlight' ? 'highlights' : 'things'][] = (string) $r['text'];
        }
        return $out;
    }

    /** @return list<string> */
    public static function gallery(int $id): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['image'],
            Database::fetchAll('SELECT image FROM destination_gallery WHERE destination_id = ? ORDER BY position, id', [$id]),
        );
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        $data['sort_order'] = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) FROM destinations') + 10;
        return Database::insert('destinations', $data);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('destinations', $data, 'id = ?', [$id]);
    }

    /**
     * Replace highlights / things to do / gallery with the given ordered lists.
     * @param list<string> $highlights @param list<string> $things @param list<string> $gallery
     */
    public static function replaceChildren(int $id, array $highlights, array $things, array $gallery): void
    {
        Database::query('DELETE FROM destination_items WHERE destination_id = ?', [$id]);
        Database::query('DELETE FROM destination_gallery WHERE destination_id = ?', [$id]);
        foreach ($highlights as $i => $text) {
            Database::insert('destination_items', ['destination_id' => $id, 'type' => 'highlight', 'text' => $text, 'position' => $i]);
        }
        foreach ($things as $i => $text) {
            Database::insert('destination_items', ['destination_id' => $id, 'type' => 'thing', 'text' => $text, 'position' => $i]);
        }
        foreach ($gallery as $i => $image) {
            Database::insert('destination_gallery', ['destination_id' => $id, 'image' => $image, 'position' => $i]);
        }
    }

    /** Packages refer to destinations by slug — keep them linked when a slug changes. */
    public static function renameSlugInPackages(string $old, string $new): void
    {
        Database::query('UPDATE IGNORE package_destinations SET destination_slug = ? WHERE destination_slug = ?', [$new, $old]);
        Database::query('DELETE FROM package_destinations WHERE destination_slug = ?', [$old]);
    }

    /** @return list<array{id:int,title:string}> packages that still list this destination */
    public static function packagesUsing(string $slug): array
    {
        return Database::fetchAll(
            'SELECT p.id, p.title FROM package_destinations pd JOIN packages p ON p.id = pd.package_id
              WHERE pd.destination_slug = ? ORDER BY p.sort_order, p.id',
            [$slug],
        );
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM destinations WHERE id = ?', [$id]);   // children cascade
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::update('destinations', ['status' => $status], 'id = ?', [$id]);
    }

    /**
     * Save a drag-and-drop order. The rows shown keep the same sort_order "slots" they had,
     * redistributed in the new order — so reordering a filtered/paged list never disturbs hidden rows.
     * @param list<int> $ids
     */
    public static function reorder(array $ids): int
    {
        return self::reorderTable('destinations', $ids);
    }

    /** @param list<int> $ids */
    public static function reorderTable(string $table, array $ids): int
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($v) => is_int($v) && $v > 0)));
        if ($ids === []) {
            return 0;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::fetchAll("SELECT id, sort_order FROM `{$table}` WHERE id IN ({$marks})", $ids);
        $known = array_map('intval', array_column($rows, 'id'));
        $ids = array_values(array_filter($ids, static fn (int $id) => in_array($id, $known, true)));
        $total = (int) Database::value("SELECT COUNT(*) FROM `{$table}`");

        if (count($ids) === $total) {
            $slots = array_map(static fn (int $i) => ($i + 1) * 10, array_keys($ids));
        } else {
            $slots = array_map('intval', array_column($rows, 'sort_order'));
            sort($slots);
            for ($i = 1, $n = count($slots); $i < $n; $i++) {
                if ($slots[$i] <= $slots[$i - 1]) {
                    $slots[$i] = $slots[$i - 1] + 1;
                }
            }
        }
        foreach ($ids as $i => $id) {
            Database::update($table, ['sort_order' => $slots[$i]], 'id = ?', [$id]);
        }
        return count($ids);
    }
}
