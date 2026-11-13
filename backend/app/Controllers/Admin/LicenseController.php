<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\License;
use App\Core\Settings;

/** Plan & upgrade page: shows the current plan, the plan comparison, and accepts a license key. */
final class LicenseController extends Controller
{
    public function index(): never
    {
        $this->view('admin/license/index', [
            'title' => 'Plan & upgrade',
            'state' => License::state(),
            'host' => License::currentHost(),
            'daysLeft' => License::daysLeft(),
        ]);
    }

    public function update(): never
    {
        $key = trim(preg_replace('/\s+/', '', (string) $this->request->input('license_key', '')) ?? '');
        if ($key === '') {
            Flash::withInput($this->request->all(), ['license_key' => 'Paste the license key you received.']);
            Flash::error('Please paste your license key.');
            $this->redirect('/license');
        }
        $check = License::verify($key, License::currentHost());
        if (!$check['ok']) {
            Flash::withInput($this->request->all(), ['license_key' => (string) $check['error']]);
            Flash::error((string) $check['error']);
            $this->redirect('/license');
        }
        Settings::set(['license_key' => $key]);
        License::reset();
        $plan = License::planLabel((string) $check['payload']['plan']);
        Activity::log('updated', 'license', null, 'Activated the ' . $plan . ' plan');
        Flash::success('🎉 ' . $plan . ' plan activated. All ' . $plan . ' features are now unlocked.');
        $this->redirect('/license');
    }

    public function destroy(): never
    {
        Settings::forget('license_key');
        License::reset();
        Activity::log('deleted', 'license', null, 'Removed the license key');
        Flash::info('License key removed. The admin panel is back on the Basic plan — your data is kept.');
        $this->redirect('/license');
    }
}
