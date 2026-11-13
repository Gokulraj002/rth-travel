<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Env;
use App\Core\License;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Repositories\EnquiryRepository;
use App\Repositories\LeadActivityRepository;

/**
 * POST /api/v1/enquiries — website enquiry form (JSON or form-encoded).
 * OPTIONS preflight is answered by the 'api' middleware before this runs.
 */
final class EnquiryController extends Controller
{
    private const SOURCES = ['website', 'planner', 'package', 'whatsapp'];
    private const NO_STORE = ['Cache-Control' => 'no-store'];

    public function store(): never
    {
        // 1. Browser calls must come from an allowed website origin (server-to-server: no Origin).
        $origin = (string) ($this->request->header('Origin') ?? '');
        if ($origin !== '' && !in_array($origin, $this->allowedOrigins(), true)) {
            $this->json(['error' => 'Origin not allowed.'], 403, self::NO_STORE);
        }

        // 2. Rate limit per IP (counts every attempt, valid or not).
        $ip = $this->request->ip();
        if (!RateLimiter::hit('enquiry:' . $ip, 5, 600)) {
            $this->json(
                ['error' => 'Too many enquiries from your network. Please try again in a few minutes, or call/WhatsApp us.'],
                429,
                self::NO_STORE + ['Retry-After' => '600']
            );
        }

        $input = $this->request->all();

        // 3. Honeypot: real visitors never see/fill "website". Pretend success, save nothing.
        $honeypot = $input['website'] ?? '';
        if (!is_string($honeypot) || trim($honeypot) !== '') {
            $this->json(['ok' => true, 'id' => random_int(1000, 999999)], 201, self::NO_STORE);
        }

        // 4. Validate + normalise.
        [$data, $errors] = $this->validate($input);
        if ($errors !== []) {
            $this->json(['error' => 'Please check the highlighted fields.', 'fields' => $errors], 422, self::NO_STORE);
        }

        $id = Database::insert('enquiries', $data + [
            'status' => 'new',
            'ip' => $ip,
            'user_agent' => $this->request->userAgent() ?: null,
        ]);

        $this->linkCustomer($id);
        $this->notify($id, $data);

        $this->json(['ok' => true, 'id' => $id], 201, self::NO_STORE);
    }

    /**
     * Paid plans: file the enquiry under a customer (found by phone, else created with source "website")
     * and start it in the "new" pipeline stage. Best-effort — the public form must never fail because of this.
     */
    private function linkCustomer(int $id): void
    {
        $customers = License::has('customers');
        if (!$customers && !License::has('crm')) {
            return;
        }
        try {
            $lead = EnquiryRepository::find($id);
            if ($lead === null) {
                return;
            }
            $customerId = null;
            if ($customers) {
                $customerId = EnquiryRepository::ensureCustomer(['source' => 'website'] + $lead);
            }
            Database::update('enquiries', ['stage' => 'new', 'status' => 'new'], 'id = ?', [$id]);
            LeadActivityRepository::log($id, $customerId, 'system', 'Enquiry received from website', null);
        } catch (\Throwable $e) {
            error_log('[enquiry → customer] #' . $id . ' ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $in
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function validate(array $in): array
    {
        $errors = [];

        // Text fields: [input key => [db column, max length, required, multiline]]
        $fields = [
            'name' => ['name', 120, true, false],
            'email' => ['email', 190, false, false],
            // destination column is VARCHAR(120) — keep within it so strict SQL mode never errors.
            'destination' => ['destination', 120, false, false],
            'package' => ['package', 160, false, false],
            'travelMonth' => ['travel_month', 40, false, false],
            'hotelCategory' => ['hotel_category', 40, false, false],
            'message' => ['message', 2000, false, true],
        ];
        $labels = [
            'name' => 'Name', 'email' => 'Email', 'destination' => 'Destination', 'package' => 'Package',
            'travelMonth' => 'Travel month', 'hotelCategory' => 'Hotel category', 'message' => 'Message',
        ];

        $data = [];
        foreach ($fields as $key => [$column, $max, $required, $multiline]) {
            $value = $this->text($in[$key] ?? null, $multiline);
            if ($value === false) {
                $errors[$key] = "{$labels[$key]} is invalid.";
                continue;
            }
            if ($value === '') {
                if ($required) {
                    $errors[$key] = "{$labels[$key]} is required.";
                }
                $data[$column] = null;
                continue;
            }
            if (mb_strlen($value) > $max) {
                $errors[$key] = "{$labels[$key]} may not be longer than {$max} characters.";
                continue;
            }
            $data[$column] = $value;
        }

        if (isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Phone: Indian mobile, 10 digits starting 6-9, optional +91 / 91 / 0 prefix → "+91XXXXXXXXXX".
        $phoneRaw = $this->text($in['phone'] ?? null, false);
        if ($phoneRaw === false || $phoneRaw === '') {
            $errors['phone'] = 'Phone number is required.';
        } else {
            $compact = (string) preg_replace('/[\s\-().]/', '', $phoneRaw);
            if (preg_match('/^(?:\+91|91|0)?([6-9]\d{9})$/', $compact, $m) === 1) {
                $data['phone'] = '+91' . $m[1];
            } else {
                $errors['phone'] = 'Please enter a valid 10-digit Indian mobile number.';
            }
        }

        $data['adults'] = $this->int($in['adults'] ?? null, 1, 1, 30, 'adults', 'Adults', $errors);
        $data['children'] = $this->int($in['children'] ?? null, 0, 0, 20, 'children', 'Children', $errors);

        $source = $this->text($in['source'] ?? null, false);
        if ($source === false || ($source !== '' && !in_array($source, self::SOURCES, true))) {
            $errors['source'] = 'Source has an invalid value.';
        }
        $data['source'] = is_string($source) && $source !== '' ? $source : 'website';

        return [$data, $errors];
    }

    /** Trimmed, tag-free string; '' when empty/missing; false when not a scalar string/number. */
    private function text(mixed $v, bool $multiline): string|false
    {
        if ($v === null) {
            return '';
        }
        if (is_int($v) || is_float($v)) {
            $v = (string) $v;
        }
        if (!is_string($v) || !mb_check_encoding($v, 'UTF-8')) {
            return false;
        }
        $v = strip_tags($v);
        $v = $multiline
            ? (string) preg_replace('/[^\P{C}\n\t]/u', '', str_replace(["\r\n", "\r"], "\n", $v)) // keep newlines/tabs
            : (string) preg_replace('/\s+/u', ' ', (string) preg_replace('/\p{C}/u', ' ', $v));
        return trim($v);
    }

    /** @param array<string,string> $errors */
    private function int(mixed $v, int $default, int $min, int $max, string $key, string $label, array &$errors): int
    {
        if ($v === null || $v === '') {
            return $default;
        }
        $n = filter_var(is_string($v) ? trim($v) : $v, FILTER_VALIDATE_INT);
        if ($n === false || is_bool($v) || $n < $min || $n > $max) {
            $errors[$key] = "{$label} must be a whole number between {$min} and {$max}.";
            return $default;
        }
        return $n;
    }

    /** @return list<string> */
    private function allowedOrigins(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) Env::get('API_ALLOWED_ORIGINS', ''))
        )));
    }

    /** Best-effort plain-text notification. User data goes in the body only — never in headers. */
    private function notify(int $id, array $d): void
    {
        $to = trim((string) Env::get('ENQUIRY_NOTIFY_EMAIL', ''));
        if ($to === '') {
            return;
        }
        try {
            $lines = [
                'A new enquiry has arrived from the website.',
                '',
                'Name:           ' . $d['name'],
                'Phone:          ' . $d['phone'],
                'Email:          ' . ($d['email'] ?? '-'),
                'Destination:    ' . ($d['destination'] ?? '-'),
                'Package:        ' . ($d['package'] ?? '-'),
                'Travel month:   ' . ($d['travel_month'] ?? '-'),
                'Adults:         ' . $d['adults'],
                'Children:       ' . $d['children'],
                'Hotel category: ' . ($d['hotel_category'] ?? '-'),
                'Source:         ' . $d['source'],
                '',
                'Message:',
                (string) ($d['message'] ?? '-'),
                '',
                'Open it in the admin panel: ' . rtrim((string) Env::get('APP_URL', ''), '/') . '/enquiries/' . $id,
            ];
            Mailer::send($to, 'New website enquiry #' . $id, implode("\n", $lines));
        } catch (\Throwable $e) {
            error_log('[enquiry notify] ' . $e->getMessage());
        }
    }
}
