<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Guest reviews shown on the website. */
final class TestimonialRepository
{
    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM testimonials WHERE id = ?', [$id]);
    }

    /** @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int} */
    public static function paginate(string $q = '', string $status = '', int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(name LIKE ? OR from_city LIKE ? OR trip LIKE ? OR quote LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (in_array($status, ['published', 'draft'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value('SELECT COUNT(*) FROM testimonials' . $sqlWhere, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $rows = Database::fetchAll(
            'SELECT * FROM testimonials' . $sqlWhere . ' ORDER BY sort_order, id LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params,
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        $data['sort_order'] = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) FROM testimonials') + 10;
        return Database::insert('testimonials', $data);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('testimonials', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM testimonials WHERE id = ?', [$id]);
    }

    /** @param list<int> $ids */
    public static function reorder(array $ids): int
    {
        return DestinationRepository::reorderTable('testimonials', $ids);
    }
}
