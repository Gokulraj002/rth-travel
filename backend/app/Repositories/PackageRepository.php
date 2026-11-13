<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * All SQL for tour packages (packages + package_categories / package_destinations /
 * package_items / package_itinerary). Child rows cascade on delete (FK ON DELETE CASCADE).
 */
final class PackageRepository
{
    public const REGIONS = ['kashmir' => 'Kashmir', 'ladakh' => 'Ladakh', 'jammu' => 'Jammu'];
    public const STATUSES = ['published' => 'Published', 'draft' => 'Draft'];
    /** Same labels as src/data/packages.ts → categoryLabels */
    public const CATEGORIES = [
        'honeymoon' => 'Honeymoon',
        'family' => 'Family',
        'adventure' => 'Adventure',
        'pilgrimage' => 'Pilgrimage',
        'snow' => 'Snow & Winter',
        'group' => 'Group Tours',
    ];
    public const ITEM_TYPES = ['highlight' => 'highlights', 'inclusion' => 'inclusions', 'exclusion' => 'exclusions'];

    /**
     * Paginated, filtered list for the admin index.
     * @param array{q?:string,status?:string,region?:string} $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $where[] = '(p.title LIKE ? OR p.slug LIKE ? OR p.subtitle LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $status = (string) ($filters['status'] ?? '');
        if (isset(self::STATUSES[$status])) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        $region = (string) ($filters['region'] ?? '');
        if (isset(self::REGIONS[$region])) {
            $where[] = 'p.region = ?';
            $params[] = $region;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) Database::value("SELECT COUNT(*) FROM packages p {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT p.id, p.slug, p.title, p.subtitle, p.region, p.nights, p.days, p.price_from, p.image,
                    p.featured, p.status, p.sort_order, p.updated_at,
                    (SELECT GROUP_CONCAT(c.category ORDER BY c.position, c.category SEPARATOR ',')
                       FROM package_categories c WHERE c.package_id = p.id) AS categories
               FROM packages p {$whereSql}
              ORDER BY p.sort_order, p.id
              LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );
        foreach ($rows as &$r) {
            $r['categories'] = $r['categories'] ? explode(',', (string) $r['categories']) : [];
        }
        unset($r);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** @return array<string,mixed>|null */
    public function findBasic(int $id): ?array
    {
        return Database::fetch('SELECT * FROM packages WHERE id = ?', [$id]);
    }

    /**
     * Package with all child rows:
     *   categories: list<string>, destinations: list<string> (in visit order),
     *   highlights / inclusions / exclusions: list<string>, itinerary: list<{day,title,description,stay}>
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $p = $this->findBasic($id);
        if ($p === null) {
            return null;
        }
        $p['categories'] = array_column(
            Database::fetchAll('SELECT category FROM package_categories WHERE package_id = ? ORDER BY position, category', [$id]),
            'category',
        );
        $p['destinations'] = array_column(
            Database::fetchAll('SELECT destination_slug FROM package_destinations WHERE package_id = ? ORDER BY position, destination_slug', [$id]),
            'destination_slug',
        );
        foreach (self::ITEM_TYPES as $plural) {
            $p[$plural] = [];
        }
        foreach (Database::fetchAll('SELECT type, text FROM package_items WHERE package_id = ? ORDER BY type, position, id', [$id]) as $item) {
            $p[self::ITEM_TYPES[$item['type']]][] = (string) $item['text'];
        }
        $p['itinerary'] = Database::fetchAll(
            'SELECT day, title, description, stay FROM package_itinerary WHERE package_id = ? ORDER BY day, id',
            [$id],
        );
        return $p;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $exceptId === null
            ? (bool) Database::value('SELECT 1 FROM packages WHERE slug = ? LIMIT 1', [$slug])
            : (bool) Database::value('SELECT 1 FROM packages WHERE slug = ? AND id <> ? LIMIT 1', [$slug, $exceptId]);
    }

    /**
     * Create (id = null) or update a package and replace all its child rows in one transaction.
     * @param array{slug:string,title:string,subtitle:string,region:string,nights:int,days:int,price_from:int,image:string,featured:int,status:string} $data
     * @param array{categories:list<string>,destinations:list<string>,highlights:list<string>,inclusions:list<string>,exclusions:list<string>,itinerary:list<array{title:string,description:string,stay:?string}>} $children
     */
    public function save(?int $id, array $data, array $children): int
    {
        return Database::transaction(function () use ($id, $data, $children): int {
            if ($id === null) {
                $data['sort_order'] = $this->nextSortOrder();
                $id = Database::insert('packages', $data);
            } else {
                Database::update('packages', $data, 'id = ?', [$id]);
            }
            $this->replaceChildren($id, $children);
            return $id;
        });
    }

    /** @param array<string,mixed> $children */
    private function replaceChildren(int $id, array $children): void
    {
        Database::query('DELETE FROM package_categories WHERE package_id = ?', [$id]);
        Database::query('DELETE FROM package_destinations WHERE package_id = ?', [$id]);
        Database::query('DELETE FROM package_items WHERE package_id = ?', [$id]);
        Database::query('DELETE FROM package_itinerary WHERE package_id = ?', [$id]);

        foreach (array_values(array_unique($children['categories'] ?? [])) as $i => $cat) {
            Database::insert('package_categories', ['package_id' => $id, 'category' => $cat, 'position' => $i]);
        }
        foreach (array_values(array_unique($children['destinations'] ?? [])) as $i => $slug) {
            Database::insert('package_destinations', ['package_id' => $id, 'destination_slug' => $slug, 'position' => $i + 1]);
        }
        foreach (self::ITEM_TYPES as $type => $plural) {
            foreach (array_values($children[$plural] ?? []) as $i => $text) {
                Database::insert('package_items', ['package_id' => $id, 'type' => $type, 'text' => $text, 'position' => $i + 1]);
            }
        }
        foreach (array_values($children['itinerary'] ?? []) as $i => $day) {
            Database::insert('package_itinerary', [
                'package_id' => $id,
                'day' => $i + 1,
                'title' => $day['title'],
                'description' => $day['description'],
                'stay' => ($day['stay'] ?? '') !== '' ? $day['stay'] : null,
            ]);
        }
    }

    private function nextSortOrder(): int
    {
        return (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) FROM packages') + 10;
    }

    /** Copy a package (with all children) as a draft: "-copy" slug, "(copy)" title. Returns the new id. */
    public function duplicate(int $id): ?int
    {
        $p = $this->find($id);
        if ($p === null) {
            return null;
        }
        $data = [
            'slug' => $this->uniqueCopySlug((string) $p['slug']),
            'title' => mb_substr((string) $p['title'], 0, 160 - 7) . ' (copy)',
            'subtitle' => (string) $p['subtitle'],
            'region' => (string) $p['region'],
            'nights' => (int) $p['nights'],
            'days' => (int) $p['days'],
            'price_from' => (int) $p['price_from'],
            'image' => (string) $p['image'],
            'featured' => 0,
            'status' => 'draft',
        ];
        $children = [
            'categories' => $p['categories'],
            'destinations' => $p['destinations'],
            'highlights' => $p['highlights'],
            'inclusions' => $p['inclusions'],
            'exclusions' => $p['exclusions'],
            'itinerary' => array_map(static fn (array $d) => [
                'title' => (string) $d['title'],
                'description' => (string) $d['description'],
                'stay' => $d['stay'] !== null ? (string) $d['stay'] : null,
            ], $p['itinerary']),
        ];
        return $this->save(null, $data, $children);
    }

    private function uniqueCopySlug(string $slug): string
    {
        $base = substr(preg_replace('/-copy(-\d+)?$/', '', $slug) ?? $slug, 0, 110) . '-copy';
        $candidate = $base;
        for ($n = 2; $this->slugExists($candidate); $n++) {
            $candidate = $base . '-' . $n;
        }
        return $candidate;
    }

    public function setStatus(int $id, string $status): void
    {
        if (!isset(self::STATUSES[$status])) {
            throw new \InvalidArgumentException('Invalid status');
        }
        Database::update('packages', ['status' => $status], 'id = ?', [$id]);
    }

    /** Child rows are removed by FK ON DELETE CASCADE. */
    public function delete(int $id): void
    {
        Database::transaction(static function () use ($id): void {
            Database::query('DELETE FROM packages WHERE id = ?', [$id]);
        });
    }

    /**
     * Save a new order. $ids may be a subset (e.g. one filtered page): those rows are re-ordered
     * among the slots they already occupy; every package then gets a clean 10, 20, 30… sort_order.
     * @param list<int> $ids
     */
    public function reorder(array $ids): void
    {
        Database::transaction(static function () use ($ids): void {
            $all = array_map('intval', array_column(
                Database::fetchAll('SELECT id FROM packages ORDER BY sort_order, id FOR UPDATE'),
                'id',
            ));
            $moving = array_values(array_intersect($ids, $all));
            $movingSet = array_flip($moving);
            $queue = $moving;
            $final = [];
            foreach ($all as $id) {
                $final[] = isset($movingSet[$id]) ? array_shift($queue) : $id;
            }
            foreach ($final as $i => $id) {
                Database::query('UPDATE packages SET sort_order = ? WHERE id = ? AND sort_order <> ?', [($i + 1) * 10, $id, ($i + 1) * 10]);
            }
        });
    }

    /** Is an image path still referenced by any package (other than $exceptPackageId) or destination? */
    public function imageInUse(string $path, ?int $exceptPackageId = null): bool
    {
        $pkg = $exceptPackageId === null
            ? Database::value('SELECT 1 FROM packages WHERE image = ? LIMIT 1', [$path])
            : Database::value('SELECT 1 FROM packages WHERE image = ? AND id <> ? LIMIT 1', [$path, $exceptPackageId]);
        if ($pkg) {
            return true;
        }
        if (Database::value('SELECT 1 FROM destinations WHERE image = ? OR card_image = ? LIMIT 1', [$path, $path])) {
            return true;
        }
        return (bool) Database::value('SELECT 1 FROM destination_gallery WHERE image = ? LIMIT 1', [$path]);
    }

    /**
     * Destinations for the "Destinations covered" selects (published + draft).
     * @return list<array{slug:string,name:string,region:string,status:string}>
     */
    public function destinationOptions(): array
    {
        return Database::fetchAll('SELECT slug, name, region, status FROM destinations ORDER BY sort_order, name');
    }
}
