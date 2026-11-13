<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Revalidator;

final class SiteController extends Controller
{
    /** "Update website now" button in the sidebar. */
    public function refresh(): never
    {
        Revalidator::ping(['packages', 'destinations', 'testimonials', 'faqs', 'settings'])
            ? Flash::success('The website has been refreshed — changes are live.')
            : Flash::error('Could not reach the website. Check NEXT_REVALIDATE_URL / NEXT_REVALIDATE_SECRET in .env (changes will still appear within a few minutes).');
        $back = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path = (string) parse_url($back, PHP_URL_PATH);
        $this->redirect($path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : '/');
    }
}
