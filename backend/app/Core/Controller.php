<?php

declare(strict_types=1);

namespace App\Core;

/** Base controller for admin + API controllers. */
abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    /** Render an admin page inside the admin layout. @param array<string,mixed> $data */
    protected function view(string $view, array $data = [], string $layout = 'layouts/admin'): never
    {
        echo View::render($view, $data, $layout);
        exit;
    }

    protected function redirect(string $path): never
    {
        Response::redirect(url($path));
    }

    /** Redirect back to the form with old input + errors. @param array<string,string> $errors */
    protected function back(string $path, array $errors, string $message = 'Please fix the highlighted fields.'): never
    {
        Flash::withInput($this->request->all(), $errors);
        Flash::error($message);
        Response::redirect(url($path));
    }

    /** @param array<string,mixed>|list<mixed> $data */
    protected function json(array $data, int $status = 200, array $headers = []): never
    {
        Response::json($data, $status, $headers);
    }

    /** Positive int route param or 404. */
    protected function id(string $raw): int
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            Response::abort(404);
        }
        return $id;
    }

    /**
     * Read a repeatable list field from the form, e.g. highlights[] → trimmed, non-empty strings.
     * @return list<string>
     */
    protected function listInput(string $key, int $maxItems = 50, int $maxLen = 300): array
    {
        $raw = $this->request->input($key, []);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (!is_string($v)) {
                continue;
            }
            $v = trim($v);
            if ($v !== '') {
                $out[] = mb_substr($v, 0, $maxLen);
            }
            if (count($out) >= $maxItems) {
                break;
            }
        }
        return $out;
    }
}
