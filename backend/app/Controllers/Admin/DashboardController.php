<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Env;
use App\Repositories\EnquiryRepository;

final class DashboardController extends Controller
{
    public function index(): never
    {
        $user = Auth::user();
        $hour = (int) date('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = trim(explode(' ', trim((string) ($user['name'] ?? '')))[0] ?? '');

        $packageCounts = ['published' => 0, 'draft' => 0];
        foreach (Database::fetchAll('SELECT status, COUNT(*) AS n FROM packages GROUP BY status') as $row) {
            $packageCounts[(string) $row['status']] = (int) $row['n'];
        }

        $stats = [
            'newEnquiries' => EnquiryRepository::countByStatus('new'),
            'enquiries30' => EnquiryRepository::countSinceDays(30),
            'packagesPublished' => $packageCounts['published'] ?? 0,
            'packagesDraft' => $packageCounts['draft'] ?? 0,
            'destinations' => (int) Database::value('SELECT COUNT(*) FROM destinations'),
            'reviews' => (int) Database::value('SELECT COUNT(*) FROM testimonials'),
        ];

        $activity = Database::fetchAll(
            'SELECT l.action, l.entity, l.entity_id, l.summary, l.created_at, a.name AS admin_name
               FROM activity_log l
               LEFT JOIN admins a ON a.id = l.admin_id
              ORDER BY l.created_at DESC, l.id DESC
              LIMIT 10',
        );

        $this->view('admin/dashboard/index', [
            'title' => 'Dashboard',
            'pageTitle' => $greeting . ($firstName !== '' ? ', ' . $firstName : '') . '!',
            'pretitle' => date('l, j F Y'),
            'stats' => $stats,
            'recent' => EnquiryRepository::recent(8),
            'activity' => $activity,
            'websiteUrl' => rtrim((string) Env::get('WEBSITE_URL', ''), '/'),
        ]);
    }
}
