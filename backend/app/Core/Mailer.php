<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny plain-text wrapper around PHP mail() (Hostinger supports it out of the box).
 * Header-injection safe: recipient and From come from config only and are validated;
 * the subject is stripped of CR/LF; user-supplied text belongs in the body only.
 * Never throws — returns false on any failure (callers treat mail as best-effort).
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        try {
            $to = trim($to);
            $from = trim((string) Env::get('MAIL_FROM', ''));
            if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }
            $subject = trim((string) preg_replace('/[\r\n]+/', ' ', $subject));
            $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
            $body = str_replace(["\r\n", "\r"], "\n", $body);

            $headers = [
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Transfer-Encoding' => '8bit',
                'X-Mailer' => 'RTH-CMS',
            ];
            $params = '';
            if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false) {
                $headers['From'] = $from;
                $params = '-f' . $from; // envelope sender (validated above, no shell metacharacters)
            }

            $ok = $params !== ''
                ? @mail($to, $subject, $body, $headers, $params)
                : @mail($to, $subject, $body, $headers);
            if (!$ok) {
                error_log('[mailer] mail() returned false for notification email');
            }
            return $ok;
        } catch (\Throwable $e) {
            error_log('[mailer] ' . $e->getMessage());
            return false;
        }
    }
}
