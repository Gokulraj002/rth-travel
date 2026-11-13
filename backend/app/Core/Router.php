<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny router with {param} placeholders and middleware.
 *   $r->get('/packages/{id}/edit', [PackageController::class, 'edit'], ['auth']);
 * Middleware: 'auth' (admin session), 'guest' (logged-out only), 'csrf' (auto on non-GET admin routes),
 * 'owner' (owner role), 'api' (JSON + CORS),
 * 'feature:quotations' (paid plan feature — shows the "Upgrade to unlock" page when the plan lacks it),
 * 'can:bookings' (role permission — config/permissions.php).
 */
final class Router
{
    /** @var list<array{method:string, regex:string, keys:list<string>, handler:array{0:class-string,1:string}, mw:list<string>}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler @param list<string> $mw */
    public function add(string $method, string $pattern, array $handler, array $mw = []): void
    {
        $keys = [];
        $regex = preg_replace_callback('#\{([a-z_]+)\}#', static function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '([^/]+)';
        }, rtrim($pattern, '/') ?: '/');
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'keys' => $keys,
            'handler' => $handler,
            'mw' => $mw,
        ];
    }

    public function get(string $p, array $h, array $mw = []): void { $this->add('GET', $p, $h, $mw); }
    public function post(string $p, array $h, array $mw = []): void { $this->add('POST', $p, $h, $mw); }
    public function put(string $p, array $h, array $mw = []): void { $this->add('PUT', $p, $h, $mw); }
    public function delete(string $p, array $h, array $mw = []): void { $this->add('DELETE', $p, $h, $mw); }
    public function options(string $p, array $h, array $mw = []): void { $this->add('OPTIONS', $p, $h, $mw); }

    public function dispatch(Request $req): void
    {
        $method = $req->method();
        $path = $req->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = [];
            foreach ($route['keys'] as $i => $key) {
                $params[$key] = $m[$i + 1];
            }
            $this->runMiddleware($route['mw'], $req, $method);
            [$class, $action] = $route['handler'];
            (new $class($req))->{$action}(...$params);
            return;
        }

        if ($allowed !== []) {
            str_starts_with($path, '/api/')
                ? Response::json(['error' => 'Method not allowed'], 405)
                : Response::abort(405, 'Method not allowed');
        }
        str_starts_with($path, '/api/')
            ? Response::json(['error' => 'Not found'], 404)
            : Response::abort(404);
    }

    /** @param list<string> $mw */
    private function runMiddleware(array $mw, Request $req, string $method): void
    {
        $isApi = in_array('api', $mw, true);

        if ($isApi) {
            Api::cors();
            if ($method === 'OPTIONS') {
                http_response_code(204);
                exit;
            }
        }

        if (in_array('auth', $mw, true) && !Auth::check()) {
            Session::put('intended', $req->path());
            Response::redirect(url('/login'));
        }
        if (in_array('guest', $mw, true) && Auth::check()) {
            Response::redirect(url('/'));
        }
        if (in_array('owner', $mw, true) && !Auth::isOwner()) {
            Response::abort(403, 'Only the account owner can do this.');
        }
        foreach ($mw as $m) {
            if (str_starts_with($m, 'feature:')) {
                $feature = substr($m, 8);
                if (!License::has($feature)) {
                    if ($method !== 'GET' && $method !== 'HEAD') {
                        Response::abort(403, 'This feature needs the ' . License::planLabel(License::requiredPlan($feature)) . ' plan.');
                    }
                    http_response_code(200);
                    echo View::render('admin/license/locked', ['title' => 'Upgrade to unlock', 'lockedFeature' => $feature]);
                    exit;
                }
            } elseif (str_starts_with($m, 'can:')) {
                Gate::authorize(substr($m, 4));
            }
        }
        // CSRF for every state-changing admin request (API routes use their own protections).
        if (!$isApi && $method !== 'GET' && $method !== 'HEAD') {
            $token = $_POST['_token'] ?? $req->header('X-CSRF-Token');
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                Response::abort(419, 'Your session expired. Please go back, refresh the page and try again.');
            }
        }
    }
}
