<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,mixed>|null */
    private ?array $json = null;

    public function method(): string
    {
        $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // HTML forms can only POST; allow _method=PUT/PATCH/DELETE overrides on POST.
        if ($m === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $m;
    }

    public function path(): string
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** POST/JSON body value (trimmed strings). */
    public function input(string $key, mixed $default = null): mixed
    {
        $src = $this->isJson() ? $this->json() : $_POST;
        $v = $src[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->isJson() ? $this->json() : $_POST;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        return is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $f : null;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if ($this->json === null) {
            $raw = file_get_contents('php://input', false, null, 0, 1_000_000) ?: '';
            $decoded = json_decode($raw, true);
            $this->json = is_array($decoded) ? $decoded : [];
        }
        return $this->json;
    }

    public function ip(): string
    {
        // Forwarded headers are client-controlled; only trust them when the site really sits
        // behind a proxy/CDN (TRUST_PROXY=true, e.g. Cloudflare). Otherwise REMOTE_ADDR only.
        $headers = Env::bool('TRUST_PROXY', false)
            ? ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR']
            : ['REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', (string) $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }
}
