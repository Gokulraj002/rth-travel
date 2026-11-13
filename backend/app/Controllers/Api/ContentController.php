<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api;
use App\Core\Controller;
use App\Core\Database;
use App\Repositories\PublicContent;

/**
 * Public read-only JSON API consumed by the Next.js website.
 * GET responses carry Cache-Control (Api::cacheHeaders) + a strong ETag; If-None-Match → 304.
 */
final class ContentController extends Controller
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** GET /api/v1/health — DB ping. */
    public function health(): never
    {
        try {
            $ok = (int) Database::value('SELECT 1') === 1;
        } catch (\Throwable $e) {
            error_log('[api/health] ' . $e->getMessage());
            $ok = false;
        }
        $headers = ['Cache-Control' => 'no-store'];
        $ok
            ? $this->json(['ok' => true, 'time' => date(DATE_ATOM)], 200, $headers)
            : $this->json(['ok' => false, 'error' => 'Database unavailable'], 503, $headers);
    }

    /** GET /api/v1/content — everything the website needs in one call. */
    public function content(): never
    {
        $payload = [
            'settings' => PublicContent::settings(),
            'destinations' => PublicContent::destinations(),
            'packages' => PublicContent::packages(),
            'testimonials' => PublicContent::testimonials(),
            'faqs' => PublicContent::faqs(),
        ];
        // The ETag covers the content only, so the timestamp doesn't defeat 304s.
        $etag = $this->etag($payload);
        $payload['generatedAt'] = date(DATE_ATOM);
        $this->send($payload, $etag);
    }

    public function packages(): never
    {
        $this->send(PublicContent::packages());
    }

    public function package(string $slug): never
    {
        $this->send($this->findOr404(PublicContent::package(...), $slug, 'Package not found'));
    }

    public function destinations(): never
    {
        $this->send(PublicContent::destinations());
    }

    public function destination(string $slug): never
    {
        $this->send($this->findOr404(PublicContent::destination(...), $slug, 'Destination not found'));
    }

    public function testimonials(): never
    {
        $this->send(PublicContent::testimonials());
    }

    public function faqs(): never
    {
        $this->send(PublicContent::faqs());
    }

    public function settings(): never
    {
        $this->send(PublicContent::settings());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @param callable(string):(array<string,mixed>|null) $finder @return array<string,mixed> */
    private function findOr404(callable $finder, string $slug, string $message): array
    {
        $row = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1 && strlen($slug) <= 120
            ? $finder($slug)
            : null;
        if ($row === null) {
            $this->json(['error' => $message], 404, ['Cache-Control' => 'public, max-age=30']);
        }
        return $row;
    }

    /** @param array<mixed> $data */
    private function etag(array $data): string
    {
        return '"' . sha1(json_encode($data, self::JSON_FLAGS)) . '"';
    }

    /**
     * Send JSON with cache headers + ETag; answer 304 when If-None-Match matches.
     * @param array<mixed> $data
     */
    private function send(array $data, ?string $etag = null): never
    {
        $body = json_encode($data, self::JSON_FLAGS);
        $etag ??= '"' . sha1($body) . '"';

        foreach (Api::cacheHeaders(60) as $k => $v) {
            header("{$k}: {$v}");
        }
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');

        if ($this->etagMatches($etag)) {
            http_response_code(304);
            exit;
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        if ($this->request->method() !== 'HEAD') {
            echo $body;
        }
        exit;
    }

    private function etagMatches(string $etag): bool
    {
        $header = (string) ($this->request->header('If-None-Match') ?? '');
        if ($header === '') {
            return false;
        }
        if (trim($header) === '*') {
            return true;
        }
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2); // weak comparison is fine for GET
            }
            if ($candidate === $etag) {
                return true;
            }
        }
        return false;
    }
}
