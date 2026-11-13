<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PHP templates in app/Views. Always echo user data through e() (HTML-escaped).
 *   View::render('admin/packages/index', ['packages' => $rows], 'layouts/admin');
 * Inside a template: $this->section('title', '...') is not needed — pass $title in data.
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/admin'): string
    {
        $content = self::capture($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, [...$data, 'content' => $content]);
    }

    /** Render a partial (no layout) — use from inside templates: <?= partial('partials/flash') ?> */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture($view, $data);
    }

    /** @param array<string,mixed> $data */
    private static function capture(string $view, array $data): string
    {
        $file = APP_PATH . '/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
