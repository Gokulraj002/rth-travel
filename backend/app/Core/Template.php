<?php

declare(strict_types=1);

namespace App\Core;

/**
 * {{placeholder}} message templates (table message_templates) for WhatsApp / email.
 *   $text = Template::render($tpl['body'], ['customer_name' => 'Asha', 'trip' => 'Kashmir 5N/6D']);
 *   $tpl  = Template::find('Booking confirmed');           // active template by name (or null)
 *   $text = Template::message('Booking confirmed', $vars, $fallbackText);
 * Company placeholders are always available: company_name, company_phone, company_email, company_website.
 * Unknown placeholders are removed.
 */
final class Template
{
    /** @param array<string,string|int|float|null> $vars */
    public static function render(string $body, array $vars): string
    {
        $vars = [...self::companyVars(), ...$vars];
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', static function ($m) use ($vars) {
            $v = $vars[strtolower($m[1])] ?? '';
            return (string) $v;
        }, $body);
    }

    /** @return array<string,mixed>|null */
    public static function find(string $name, string $channel = 'whatsapp'): ?array
    {
        $row = Database::fetch('SELECT * FROM message_templates WHERE name = ? AND channel = ? AND is_active = 1 LIMIT 1', [$name, $channel]);
        if ($row) {
            return $row;
        }
        // Fall back to a loose match (letters/digits only), so a dash typed differently — or a database
        // imported with the wrong character set — never silently drops the template.
        $key = self::nameKey($name);
        foreach (Database::fetchAll('SELECT * FROM message_templates WHERE channel = ? AND is_active = 1', [$channel]) as $candidate) {
            if (self::nameKey((string) $candidate['name']) === $key) {
                return $candidate;
            }
        }
        return null;
    }

    private static function nameKey(string $name): string
    {
        return mb_strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $name));
    }

    /** @param array<string,string|int|float|null> $vars */
    public static function message(string $name, array $vars, string $fallback, string $channel = 'whatsapp'): string
    {
        $tpl = self::find($name, $channel);
        return self::render($tpl ? (string) $tpl['body'] : $fallback, $vars);
    }

    /** @return array<string,string> */
    public static function companyVars(): array
    {
        $s = Settings::all();
        return [
            'company_name' => ($s['site_name'] ?? '') !== '' ? $s['site_name'] : Settings::get('company_legal_name'),
            'company_phone' => ($s['phone_primary'] ?? '') !== '' ? $s['phone_primary'] : Settings::get('company_phone'),
            'company_email' => ($s['email_primary'] ?? '') !== '' ? $s['email_primary'] : Settings::get('company_email'),
            'company_website' => Settings::get('company_website', (string) Env::get('WEBSITE_URL', '')),
        ];
    }

    /** Placeholders documented on the templates page. @return array<string,string> */
    public static function placeholders(): array
    {
        return [
            'customer_name' => 'Customer name', 'trip' => 'Trip / package title', 'amount' => 'Total amount',
            'quotation_number' => 'Quotation no.', 'quotation_link' => 'Quotation share link', 'valid_until' => 'Quote valid till',
            'booking_number' => 'Booking no.', 'travel_start' => 'Trip start date', 'travel_end' => 'Trip end date',
            'paid' => 'Amount paid', 'balance' => 'Balance due', 'payment_amount' => 'This payment', 'receipt_no' => 'Receipt no.',
            'company_name' => 'Your company name', 'company_phone' => 'Your phone', 'company_email' => 'Your email', 'company_website' => 'Your website',
        ];
    }
}
