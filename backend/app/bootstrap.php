<?php

declare(strict_types=1);

/*
 * Reyaan Trip Holiday — admin + API bootstrap.
 * Loaded by public/index.php and bin/* scripts. Plain PHP 8.2+, no Composer needed.
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
// The web root can be `public/` (local, VPS) or Hostinger's `public_html/` (the contents of public/
// moved there) — public/index.php defines it from its own location; CLI scripts fall back to public/.
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', is_dir(BASE_PATH . '/public_html') ? BASE_PATH . '/public_html' : BASE_PATH . '/public');
}
define('STORAGE_PATH', BASE_PATH . '/storage');

// PSR-4-style autoloader for the App\ namespace.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Core/helpers.php';

App\Core\Env::load(BASE_PATH . '/.env');

$debug = App\Core\Env::bool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
date_default_timezone_set(App\Core\Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');

// Last-resort handler: log the real error, show a generic page/JSON (never leak details in production).
set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log(sprintf('[%s] %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isApi = str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/api/');
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Server error', 'detail' => $debug ? $e->getMessage() : null]);
        return;
    }
    echo $debug
        ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>'
        : '<h1>Something went wrong</h1><p>Please try again. If it keeps happening, check storage/logs/php-error.log.</p>';
});
