<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Revalidator;
use App\Core\Validator;
use App\Repositories\SettingRepository;

final class SettingController extends Controller
{
    private const RULES = [
        'site_name' => 'required|string|max:120',
        'tagline' => 'nullable|string|max:200',
        'description' => 'nullable|string|max:1000',
        'phone_primary' => 'required|phone|max:30',
        'phone_secondary' => 'nullable|phone|max:30',
        'whatsapp_number' => 'required|string|max:20',
        'email_primary' => 'required|email|max:190',
        'email_secondary' => 'nullable|email|max:190',
        'address_1_line1' => 'nullable|string|max:200',
        'address_1_line2' => 'nullable|string|max:200',
        'address_2_line1' => 'nullable|string|max:200',
        'address_2_line2' => 'nullable|string|max:200',
        'map_query' => 'nullable|string|max:200',
        'instagram_url' => 'nullable|url|max:255',
        'facebook_url' => 'nullable|url|max:255',
        'youtube_url' => 'nullable|url|max:255',
    ];

    private const LABELS = [
        'site_name' => 'Business name',
        'phone_primary' => 'Main phone',
        'phone_secondary' => 'Second phone',
        'whatsapp_number' => 'WhatsApp number',
        'email_primary' => 'Main email',
        'email_secondary' => 'Second email',
        'address_1_line1' => 'Address 1, line 1',
        'address_1_line2' => 'Address 1, line 2',
        'address_2_line1' => 'Address 2, line 1',
        'address_2_line2' => 'Address 2, line 2',
        'map_query' => 'Map location',
        'instagram_url' => 'Instagram link',
        'facebook_url' => 'Facebook link',
        'youtube_url' => 'YouTube link',
    ];

    public function edit(): never
    {
        $this->view('admin/settings/edit', [
            'title' => 'Site settings',
            'pretitle' => 'Shown across your website',
            'settings' => SettingRepository::all(),
        ]);
    }

    public function update(): never
    {
        $input = $this->request->all();
        $v = Validator::make($input, self::RULES, self::LABELS);
        $errors = $v->errors();
        $clean = $v->validated();

        // WhatsApp: digits only, with country code, no "+".
        if (!isset($errors['whatsapp_number']) && !preg_match('/^[1-9][0-9]{7,14}$/', (string) ($clean['whatsapp_number'] ?? ''))) {
            $errors['whatsapp_number'] = 'WhatsApp number must be digits only: country code + number, no + or spaces (e.g. 916006045211).';
        }
        // Social links must be normal web links (blocks javascript: and similar).
        foreach (['instagram_url', 'facebook_url', 'youtube_url'] as $k) {
            if (!isset($errors[$k]) && ($clean[$k] ?? null) !== null && !preg_match('#^https?://#i', (string) $clean[$k])) {
                $errors[$k] = self::LABELS[$k] . ' must start with https://';
            }
        }

        if ($errors !== []) {
            $this->back('/settings', $errors);
        }

        $before = SettingRepository::all();
        $values = [];
        foreach (SettingRepository::KEYS as $key) {
            $values[$key] = (string) ($clean[$key] ?? '');
        }
        SettingRepository::save($values);

        $changed = array_keys(array_diff_assoc($values, $before));
        Revalidator::ping(['settings']);
        Activity::log('updated', 'settings', null, $changed === []
            ? 'Saved site settings (no changes)'
            : 'Updated site settings: ' . str_limit(implode(', ', array_map(static fn ($k) => self::LABELS[$k] ?? ucfirst(str_replace('_', ' ', $k)), $changed)), 200));
        Flash::success('Settings saved. The website will show the new details within a few seconds.');
        $this->redirect('/settings');
    }
}
