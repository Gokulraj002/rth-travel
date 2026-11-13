<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @param array<string,mixed>|list<mixed> $data */
    public static function json(array $data, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        foreach ($headers as $k => $v) {
            header("{$k}: {$v}");
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function redirect(string $to, int $status = 302): never
    {
        header('Location: ' . $to, true, $status);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        $view = $status === 404 ? 'errors/404' : 'errors/error';
        echo View::render($view, ['status' => $status, 'message' => $message], 'layouts/blank');
        exit;
    }
}
