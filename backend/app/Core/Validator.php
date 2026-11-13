<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small rule-based validator.
 *   $v = Validator::make($input, [
 *     'title' => 'required|string|max:160',
 *     'nights' => 'required|int|min:0|max:60',
 *     'region' => 'required|in:kashmir,ladakh,jammu',
 *     'email' => 'nullable|email|max:190',
 *     'slug' => 'required|slug|max:120',
 *   ]);
 *   if ($v->fails()) { ... $v->errors() ... }  $clean = $v->validated();
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $clean = [];

    /** @param array<string,mixed> $data @param array<string,string> $rules @param array<string,string> $labels */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self();
        foreach ($rules as $field => $ruleString) {
            $v->check($field, $data[$field] ?? null, explode('|', $ruleString), $labels[$field] ?? self::label($field));
        }
        return $v;
    }

    public function fails(): bool { return $this->errors !== []; }

    /** @return array<string,string> */
    public function errors(): array { return $this->errors; }

    /** @return array<string,mixed> */
    public function validated(): array { return $this->clean; }

    /** @param list<string> $rules */
    private function check(string $field, mixed $value, array $rules, string $label): void
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        $empty = $value === null || $value === '' || $value === [];
        $nullable = in_array('nullable', $rules, true);

        if ($empty) {
            if (in_array('required', $rules, true)) {
                $this->errors[$field] = "{$label} is required.";
            } elseif ($nullable) {
                $this->clean[$field] = null;
            } elseif (in_array('bool', $rules, true)) {
                $this->clean[$field] = 0;
            }
            return;
        }

        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $error = match ($name) {
                'required', 'nullable', 'string' => null,
                'int' => filter_var($value, FILTER_VALIDATE_INT) === false ? "{$label} must be a whole number." : null,
                'numeric' => !is_numeric($value) ? "{$label} must be a number." : null,
                'bool' => null,
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "{$label} must be a valid email address." : null,
                'url' => filter_var($value, FILTER_VALIDATE_URL) === false ? "{$label} must be a valid URL (https://…)." : null,
                'slug' => !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value) ? "{$label} may only contain lowercase letters, numbers and hyphens." : null,
                'phone' => !preg_match('/^\+?[0-9 ()-]{7,20}$/', (string) $value) ? "{$label} must be a valid phone number." : null,
                'in' => !in_array((string) $value, explode(',', (string) $arg), true) ? "{$label} has an invalid value." : null,
                'min' => $this->tooSmall($value, (float) $arg, $rules) ? "{$label} must be at least {$arg}" . ($this->isNumericRule($rules) ? '.' : ' characters.') : null,
                'max' => $this->tooBig($value, (float) $arg, $rules) ? "{$label} may not be greater than {$arg}" . ($this->isNumericRule($rules) ? '.' : ' characters.') : null,
                default => throw new \InvalidArgumentException("Unknown validation rule: {$name}"),
            };
            if ($error !== null) {
                $this->errors[$field] = $error;
                return;
            }
        }

        $this->clean[$field] = match (true) {
            in_array('int', $rules, true) => (int) $value,
            in_array('numeric', $rules, true) => (float) $value,
            in_array('bool', $rules, true) => in_array($value, [1, '1', 'on', 'true', true], true) ? 1 : 0,
            default => is_string($value) ? $value : $value,
        };
    }

    /** @param list<string> $rules */
    private function isNumericRule(array $rules): bool
    {
        return in_array('int', $rules, true) || in_array('numeric', $rules, true);
    }

    /** @param list<string> $rules */
    private function tooSmall(mixed $v, float $min, array $rules): bool
    {
        return $this->isNumericRule($rules) ? (float) $v < $min : mb_strlen((string) $v) < $min;
    }

    /** @param list<string> $rules */
    private function tooBig(mixed $v, float $max, array $rules): bool
    {
        return $this->isNumericRule($rules) ? (float) $v > $max : mb_strlen((string) $v) > $max;
    }

    private static function label(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
