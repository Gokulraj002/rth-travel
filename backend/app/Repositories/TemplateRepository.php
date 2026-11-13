<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** WhatsApp / email message templates (table message_templates, read by App\Core\Template). */
final class TemplateRepository
{
    public const CHANNELS = ['whatsapp' => 'WhatsApp', 'email' => 'Email'];

    /**
     * Names other modules look up with Template::message('<name>', …) — renaming or deleting one
     * makes those buttons fall back to their built-in default text.
     * name => [channel, where it is used]
     */
    public const SYSTEM = [
        'Enquiry — first reply' => ['whatsapp', 'the first-reply WhatsApp button on enquiries'],
        'Quotation shared' => ['whatsapp', 'sharing a quotation on WhatsApp'],
        'Booking confirmed' => ['whatsapp', 'the booking confirmation WhatsApp message'],
        'Payment received' => ['whatsapp', 'the payment receipt WhatsApp message'],
        'Trip reminder' => ['whatsapp', 'trip reminder WhatsApp messages'],
        'Quotation email' => ['email', 'emailing a quotation to the customer'],
    ];

    /** @return list<array<string,mixed>> */
    public static function all(string $q = ''): array
    {
        $sql = 'SELECT * FROM message_templates';
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $sql .= ' WHERE name LIKE ? OR subject LIKE ? OR body LIKE ?';
            $params = [$like, $like, $like];
        }
        return Database::fetchAll($sql . " ORDER BY channel = 'whatsapp' DESC, name ASC, id ASC", $params);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM message_templates WHERE id = ?', [$id]);
    }

    public static function nameTaken(string $name, string $channel, ?int $exceptId = null): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM message_templates WHERE name = ? AND channel = ? AND id <> ?',
            [$name, $channel, $exceptId ?? 0],
        ) > 0;
    }

    /** @param array{channel:string,name:string,subject:?string,body:string,is_active:int} $data */
    public static function create(array $data): int
    {
        return Database::insert('message_templates', self::only($data));
    }

    /** @param array{channel:string,name:string,subject:?string,body:string,is_active:int} $data */
    public static function update(int $id, array $data): void
    {
        Database::update('message_templates', self::only($data), 'id = ?', [$id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::update('message_templates', ['is_active' => $active ? 1 : 0], 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM message_templates WHERE id = ?', [$id]);
    }

    /** Copy a template as "<name> (copy)", "(copy 2)", … — the copy starts switched off. */
    public static function duplicate(array $tpl): int
    {
        $base = mb_substr((string) $tpl['name'], 0, 100);
        $name = $base . ' (copy)';
        for ($n = 2; self::nameTaken($name, (string) $tpl['channel']); $n++) {
            $name = $base . ' (copy ' . $n . ')';
        }
        return self::create([
            'channel' => (string) $tpl['channel'],
            'name' => $name,
            'subject' => $tpl['subject'] !== null ? (string) $tpl['subject'] : null,
            'body' => (string) $tpl['body'],
            'is_active' => 0,
        ]);
    }

    /** Is this one of the names other modules use (for its channel)? */
    public static function isSystem(string $name, string $channel): bool
    {
        return isset(self::SYSTEM[$name]) && self::SYSTEM[$name][0] === $channel;
    }

    /** Placeholders like {{name}} used in a text that Template::placeholders() does not know. @return list<string> */
    public static function unknownPlaceholders(string $text, array $known): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $m);
        $unknown = array_diff(array_unique(array_map('strtolower', $m[1])), $known);
        return array_values($unknown);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private static function only(array $data): array
    {
        return array_intersect_key($data, array_flip(['channel', 'name', 'subject', 'body', 'is_active']));
    }
}
