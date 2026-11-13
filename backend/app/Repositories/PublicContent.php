<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Env;

/**
 * Read-only queries for the public JSON API.
 * Shapes match the website's TypeScript types (src/types/index.ts) exactly.
 * Only published rows; ordered by sort_order, id. Children are loaded with one query per
 * child table for all parent ids (no N+1) and grouped in PHP.
 * Deliberately independent of the admin repositories.
 */
final class PublicContent
{
    // ── Destinations ─────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public static function destinations(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM destinations WHERE status = 'published' ORDER BY sort_order, id"
        );
        return self::mapDestinations($rows);
    }

    /** @return array<string,mixed>|null */
    public static function destination(string $slug): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM destinations WHERE status = 'published' AND slug = ? LIMIT 1",
            [$slug]
        );
        return $row === null ? null : self::mapDestinations([$row])[0];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function mapDestinations(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $in = self::placeholders($ids);

        $items = [];
        foreach (Database::fetchAll(
            "SELECT destination_id, type, text FROM destination_items
             WHERE destination_id IN ({$in}) ORDER BY destination_id, type, position, id",
            $ids
        ) as $it) {
            $items[(int) $it['destination_id']][(string) $it['type']][] = (string) $it['text'];
        }

        $gallery = [];
        foreach (Database::fetchAll(
            "SELECT destination_id, image FROM destination_gallery
             WHERE destination_id IN ({$in}) ORDER BY destination_id, position, id",
            $ids
        ) as $g) {
            $gallery[(int) $g['destination_id']][] = self::image((string) $g['image']);
        }

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $card = trim((string) ($r['card_image'] ?? ''));
            $out[] = [
                'slug' => (string) $r['slug'],
                'name' => (string) $r['name'],
                'displayName' => (string) $r['display_name'],
                'region' => (string) $r['region'],
                'tagline' => (string) $r['tagline'],
                'description' => (string) $r['description'],
                'geo' => ['lat' => (float) $r['lat'], 'lng' => (float) $r['lng']],
                'altitudeM' => (int) $r['altitude_m'],
                'bestTime' => (string) $r['best_time'],
                'distanceNote' => (string) $r['distance_note'],
                'highlights' => $items[$id]['highlight'] ?? [],
                'thingsToDo' => $items[$id]['thing'] ?? [],
                'image' => self::image((string) $r['image']),
                'cardImage' => $card === '' ? null : self::image($card),
                'gallery' => $gallery[$id] ?? [],
                'credit' => (string) $r['credit'],
            ];
        }
        return $out;
    }

    // ── Packages ─────────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public static function packages(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM packages WHERE status = 'published' ORDER BY sort_order, id"
        );
        return self::mapPackages($rows);
    }

    /** @return array<string,mixed>|null */
    public static function package(string $slug): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM packages WHERE status = 'published' AND slug = ? LIMIT 1",
            [$slug]
        );
        return $row === null ? null : self::mapPackages([$row])[0];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function mapPackages(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $in = self::placeholders($ids);

        $categories = [];
        foreach (Database::fetchAll(
            "SELECT package_id, category FROM package_categories
             WHERE package_id IN ({$in}) ORDER BY package_id, position, category",
            $ids
        ) as $c) {
            $categories[(int) $c['package_id']][] = (string) $c['category'];
        }

        $destinations = [];
        foreach (Database::fetchAll(
            "SELECT package_id, destination_slug FROM package_destinations
             WHERE package_id IN ({$in}) ORDER BY package_id, position, destination_slug",
            $ids
        ) as $d) {
            $destinations[(int) $d['package_id']][] = (string) $d['destination_slug'];
        }

        $items = [];
        foreach (Database::fetchAll(
            "SELECT package_id, type, text FROM package_items
             WHERE package_id IN ({$in}) ORDER BY package_id, type, position, id",
            $ids
        ) as $it) {
            $items[(int) $it['package_id']][(string) $it['type']][] = (string) $it['text'];
        }

        $itinerary = [];
        foreach (Database::fetchAll(
            "SELECT package_id, day, title, description, stay FROM package_itinerary
             WHERE package_id IN ({$in}) ORDER BY package_id, day, id",
            $ids
        ) as $day) {
            $stay = trim((string) ($day['stay'] ?? ''));
            $itinerary[(int) $day['package_id']][] = [
                'day' => (int) $day['day'],
                'title' => (string) $day['title'],
                'description' => (string) $day['description'],
                'stay' => $stay === '' ? null : $stay,
            ];
        }

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[] = [
                'slug' => (string) $r['slug'],
                'title' => (string) $r['title'],
                'subtitle' => (string) $r['subtitle'],
                'destinations' => $destinations[$id] ?? [],
                'region' => (string) $r['region'],
                'categories' => $categories[$id] ?? [],
                'nights' => (int) $r['nights'],
                'days' => (int) $r['days'],
                'priceFrom' => (int) $r['price_from'],
                'image' => self::image((string) $r['image']),
                'featured' => (bool) (int) $r['featured'],
                'highlights' => $items[$id]['highlight'] ?? [],
                'inclusions' => $items[$id]['inclusion'] ?? [],
                'exclusions' => $items[$id]['exclusion'] ?? [],
                'itinerary' => $itinerary[$id] ?? [],
            ];
        }
        return $out;
    }

    // ── Testimonials & FAQs ──────────────────────────────────────────────────

    /** @return list<array{name:string,from:string,trip:string,quote:string,rating:int}> */
    public static function testimonials(): array
    {
        return array_map(static fn (array $r): array => [
            'name' => (string) $r['name'],
            'from' => (string) $r['from_city'],
            'trip' => (string) $r['trip'],
            'quote' => (string) $r['quote'],
            'rating' => (int) $r['rating'],
        ], Database::fetchAll(
            "SELECT name, from_city, trip, quote, rating FROM testimonials
             WHERE status = 'published' ORDER BY sort_order, id"
        ));
    }

    /** @return list<array{q:string,a:string}> */
    public static function faqs(): array
    {
        return array_map(static fn (array $r): array => [
            'q' => (string) $r['question'],
            'a' => (string) $r['answer'],
        ], Database::fetchAll(
            "SELECT question, answer FROM faqs WHERE status = 'published' ORDER BY sort_order, id"
        ));
    }

    // ── Settings ─────────────────────────────────────────────────────────────

    /** Site settings mapped to the website's `site` shape. @return array<string,mixed> */
    public static function settings(): array
    {
        $s = [];
        foreach (Database::fetchAll('SELECT `key`, `value` FROM settings') as $row) {
            $s[(string) $row['key']] = trim((string) $row['value']);
        }
        $get = static fn (string $k): string => $s[$k] ?? '';

        $phones = [];
        foreach (['phone_primary', 'phone_secondary'] as $k) {
            $display = $get($k);
            $digits = preg_replace('/\D+/', '', $display) ?? '';
            if ($display !== '' && $digits !== '') {
                $phones[] = ['display' => $display, 'raw' => '+' . $digits];
            }
        }

        $emails = [];
        foreach (['email_primary', 'email_secondary'] as $k) {
            if ($get($k) !== '') {
                $emails[] = $get($k);
            }
        }

        $addresses = [];
        foreach ([1, 2] as $n) {
            $line1 = $get("address_{$n}_line1");
            $line2 = $get("address_{$n}_line2");
            if ($line1 !== '' || $line2 !== '') {
                $addresses[] = ['line1' => $line1, 'line2' => $line2];
            }
        }

        return [
            'name' => $get('site_name'),
            'tagline' => $get('tagline'),
            'description' => $get('description'),
            'phones' => $phones,
            'whatsapp' => preg_replace('/\D+/', '', $get('whatsapp_number')) ?? '',
            'emails' => $emails,
            'addresses' => $addresses,
            'mapQuery' => $get('map_query'),
            'social' => [
                'instagram' => $get('instagram_url'),
                'facebook' => $get('facebook_url'),
                'youtube' => $get('youtube_url'),
            ],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Public image URL: "/uploads/…" (admin uploads) → absolute via APP_URL;
     * "/images/…" (website files) and absolute URLs → unchanged.
     */
    public static function image(string $path): string
    {
        $path = trim($path);
        if (str_starts_with($path, '/uploads/')) {
            return rtrim((string) Env::get('APP_URL', ''), '/') . $path;
        }
        return $path;
    }

    /** @param list<int> $ids */
    private static function placeholders(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}
