<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Uploader;

/**
 * Image uploads for every [data-image-field] in the admin (see app.js) + a JSON list for future pickers.
 * CSRF is verified by the Router (X-CSRF-Token header or _token field).
 */
final class MediaController extends Controller
{
    private const PER_PAGE = 60;

    /** GET /media?page=N → [{id,path,url,width,height,created_at}] (newest first, 60 per page). */
    public function index(): never
    {
        $page = max(1, (int) filter_var($this->request->query('page', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $total = (int) Database::value('SELECT COUNT(*) FROM media');
        $rows = Database::fetchAll(
            'SELECT id, path, width, height, created_at FROM media ORDER BY id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
        );
        $items = array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'path' => (string) $r['path'],
            'url' => media_url((string) $r['path']),
            'width' => (int) $r['width'],
            'height' => (int) $r['height'],
            'created_at' => (string) $r['created_at'],
        ], $rows);

        $this->json($items, 200, [
            'X-Total-Count' => (string) $total,
            'X-Page' => (string) $page,
            'X-Per-Page' => (string) self::PER_PAGE,
            'Cache-Control' => 'no-store',
        ]);
    }

    /** POST /media/upload (multipart field "file") → {path, url} | 422 {error}. */
    public function upload(): never
    {
        $file = $this->request->file('file');
        if ($file === null) {
            // When the body exceeds post_max_size PHP silently drops $_FILES — explain that instead of "choose an image".
            $tooBig = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_FILES === [];
            $this->json(['error' => $tooBig ? 'The image is too large.' : 'Please choose an image.'], 422);
        }
        if (!is_string($file['tmp_name'] ?? null)) {
            $this->json(['error' => 'Please upload one image at a time.'], 422);
        }
        try {
            $path = Uploader::image($file);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
        $this->json(['path' => $path, 'url' => media_url($path)]);
    }
}
