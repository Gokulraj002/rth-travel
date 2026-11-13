<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\License;
use App\Repositories\DashboardRepository as Dash;

/**
 * Home screen. Every widget is shown only when the plan has the feature AND the role has the
 * permission (feature() / can()), so a Basic-plan editor and a Premium owner both get a tidy page.
 */
final class DashboardController extends Controller
{
    public function index(): never
    {
        $user = Auth::user();
        $hour = (int) date('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = trim(explode(' ', trim((string) ($user['name'] ?? '')))[0] ?? '');

        $show = [
            'enquiries' => can('enquiries'),
            'crm' => feature('crm') && can('enquiries'),
            'bookings' => feature('bookings') && can('bookings'),
            'payments' => feature('payments') && can('payments'),
            'quotations' => feature('quotations') && can('quotations'),
            'customers' => feature('customers') && can('customers'),
            'vehicles' => feature('vehicles') && can('vendors'),
            'reports' => feature('reports') && can('reports'),
            'website' => can('website'),
        ];
        $isBasic = License::plan() === 'basic';

        // ── Date windows ────────────────────────────────────────────────────────
        $tomorrow = date('Y-m-d 00:00:00', strtotime('tomorrow'));
        $weekStart = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $prevWeekStart = date('Y-m-d 00:00:00', strtotime('-13 days'));
        $monthStart = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('first day of next month'));
        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
        $lastMonthSameDay = min($monthStart, date('Y-m-d', strtotime($lastMonthStart . ' +' . date('j') . ' days')));

        // ── KPI cards ──────────────────────────────────────────────────────────
        $kpis = [];
        if ($show['enquiries']) {
            $week = Dash::enquiriesBetween($weekStart, $tomorrow);
            $prev = Dash::enquiriesBetween($prevWeekStart, $weekStart);
            $kpis[] = $this->kpi('New enquiries', (string) $week, 'last 7 days', '/enquiries?from=' . substr($weekStart, 0, 10), 'inbox', 'navy', self::trend($week, $prev), 'vs previous 7 days');
        }
        if ($show['crm']) {
            $kpis[] = $this->kpi('Open leads', (string) Dash::openLeads(), Dash::newLeadsUntouched() . ' not contacted yet', '/leads', 'target', 'violet');
        }
        if ($show['bookings']) {
            $b = Dash::bookingsCreatedBetween($monthStart . ' 00:00:00', $nextMonth . ' 00:00:00');
            $kpis[] = $this->kpi('Bookings this month', (string) $b['count'], inr($b['value']) . ' booked', '/bookings', 'briefcase', 'sky');
        }
        if ($show['payments']) {
            $now = Dash::collectedBetween($monthStart, $nextMonth);
            $before = Dash::collectedBetween($lastMonthStart, $lastMonthSameDay);
            $kpis[] = $this->kpi('Collected this month', inr($now), '', '/payments', 'wallet', 'green', self::trend($now, $before), 'vs same days last month');
            $o = Dash::outstanding();
            $kpis[] = $this->kpi('Outstanding balance', inr($o['amount']), $o['count'] . ' booking' . ($o['count'] === 1 ? '' : 's') . ' with dues', $show['reports'] ? '/reports' : '/payments', 'clock', 'gold');
        }
        if ($show['crm']) {
            $c = Dash::conversion();
            $rate = $c['closed'] > 0 ? (int) round($c['won'] * 100 / $c['closed']) : null;
            $kpis[] = $this->kpi('Conversion rate', $rate === null ? '—' : $rate . '%', $c['closed'] > 0 ? $c['won'] . ' won of ' . $c['closed'] . ' closed' : 'No closed leads yet', $show['reports'] ? '/reports' : '/leads', 'trending-up', 'teal', null, '', $rate);
        }
        // Basic plan (or a role that sees little of the sales suite): website content at a glance.
        if (count($kpis) < 4 && $show['website']) {
            $w = Dash::websiteCounts();
            $kpis[] = $this->kpi('Packages live', (string) $w['packages'], $w['packagesDraft'] > 0 ? $w['packagesDraft'] . ' in draft' : 'on your website', '/packages', 'package', 'gold');
            $kpis[] = $this->kpi('Destinations', (string) $w['destinations'], $w['destinations'] === 1 ? 'place' : 'places', '/destinations', 'map-pin', 'teal');
            $kpis[] = $this->kpi('Reviews', (string) $w['reviews'], $w['reviews'] === 1 ? 'customer review' : 'customer reviews', '/testimonials', 'star', 'rose');
        }

        // ── Charts & lists ─────────────────────────────────────────────────────
        $money = $show['payments'] ? Dash::monthlyMoney(6) : null;
        $enqDaily = $show['enquiries'] ? Dash::enquiriesPerDay(30) : null;
        $sources = $show['crm'] ? Dash::leadSources(90) : null;
        $tasks = $show['crm'] ? Dash::dueTasks(7) : null;
        $departures = $show['bookings'] ? Dash::upcomingDepartures(14, 7) : null;
        $latest = $show['enquiries'] ? Dash::latestEnquiries(6) : null;
        $payments = $show['payments'] ? Dash::recentPayments(6) : null;

        // ── Alerts ─────────────────────────────────────────────────────────────
        $alertsOn = $show['vehicles'] || $show['quotations'] || $show['bookings'];
        $alerts = [];
        $today = date('Y-m-d');
        if ($show['bookings']) {
            foreach (Dash::balanceDueSoon(7) as $b) {
                $alerts[] = [
                    'tone' => 'warn', 'icon' => 'wallet', 'sort' => (string) $b['travel_start'],
                    'title' => inr($b['balance']) . ' due · ' . ($b['customer_name'] ?? $b['number']),
                    'meta' => $b['number'] . ' · trip starts ' . self::relDay((string) $b['travel_start'], $today),
                    'href' => '/bookings/' . (int) $b['id'],
                ];
            }
        }
        if ($show['quotations']) {
            foreach (Dash::quotationsExpiring(3) as $q) {
                $alerts[] = [
                    'tone' => 'warn', 'icon' => 'file-text', 'sort' => (string) $q['valid_until'],
                    'title' => 'Quotation ' . $q['number'] . ' expires ' . self::relDay((string) $q['valid_until'], $today),
                    'meta' => trim(($q['customer_name'] ?? '') . ' · ' . inr($q['total']), ' ·'),
                    'href' => '/quotations/' . (int) $q['id'],
                ];
            }
        }
        if ($show['vehicles']) {
            foreach (Dash::vehicleExpiries(30) as $v) {
                $expired = $v['date'] < $today;
                $alerts[] = [
                    'tone' => $expired ? 'danger' : 'warn', 'icon' => 'car', 'sort' => $v['date'],
                    'title' => $v['what'] . ($expired ? ' expired' : ' expires') . ' · ' . $v['name'],
                    'meta' => ($v['registration_no'] !== '' ? $v['registration_no'] . ' · ' : '') . nice_day($v['date']) . ' (' . self::relDay($v['date'], $today) . ')',
                    'href' => '/vehicles',
                ];
            }
        }
        usort($alerts, static fn (array $a, array $b): int => [$a['tone'] !== 'danger', $a['sort']] <=> [$b['tone'] !== 'danger', $b['sort']]);

        // ── Quick actions (page header) ────────────────────────────────────────
        $actions = '';
        if ($show['quotations']) {
            $actions .= '<a href="' . e(url('/quotations/create')) . '" class="btn btn-primary">' . icon('file-text') . ' New quotation</a>';
        }
        if ($show['bookings']) {
            $actions .= '<a href="' . e(url('/bookings/create')) . '" class="btn' . ($actions === '' ? ' btn-primary' : '') . '">' . icon('briefcase') . ' New booking</a>';
        }
        if ($show['website']) {
            $actions .= '<a href="' . e(url('/packages/create')) . '" class="btn' . ($actions === '' ? ' btn-primary' : '') . '">' . icon('plus') . ' Add package</a>';
        }

        $this->view('admin/dashboard/index', [
            'title' => 'Dashboard',
            'pageTitle' => $greeting . ($firstName !== '' ? ', ' . $firstName : ''),
            'pretitle' => date('l, j F Y'),
            'pageActions' => $actions,
            'show' => $show,
            'isBasic' => $isBasic,
            'showTeaser' => $isBasic && Auth::isOwner(),
            'kpis' => $kpis,
            'money' => $money,
            'enqDaily' => $enqDaily,
            'sources' => $sources,
            'tasks' => $tasks,
            'departures' => $departures,
            'latest' => $latest,
            'payments' => $payments,
            'alertsOn' => $alertsOn,
            'alerts' => array_slice($alerts, 0, 8),
            'alertsMore' => max(0, count($alerts) - 8),
        ]);
    }

    /**
     * @param array{dir:string,text:string}|null $trend
     * @return array<string,mixed>
     */
    private function kpi(string $label, string $value, string $hint, string $href, string $icon, string $tone, ?array $trend = null, string $trendNote = '', ?int $progress = null): array
    {
        return compact('label', 'value', 'hint', 'href', 'icon', 'tone', 'trend', 'trendNote', 'progress');
    }

    /** @return array{dir:string,text:string}|null */
    private static function trend(float|int $now, float|int $before): ?array
    {
        if ($now == 0 && $before == 0) {
            return null;
        }
        if ($before == 0) {
            return ['dir' => 'up', 'text' => 'New'];
        }
        $pct = (int) round(($now - $before) * 100 / $before);
        return ['dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'), 'text' => ($pct > 0 ? '+' : '') . $pct . '%'];
    }

    /** "today", "tomorrow", "in 3 days", "yesterday", "4 days ago". */
    public static function relDay(string $date, string $today): string
    {
        $diff = (int) round((strtotime($date) - strtotime($today)) / 86400);
        return match (true) {
            $diff === 0 => 'today',
            $diff === 1 => 'tomorrow',
            $diff === -1 => 'yesterday',
            $diff > 1 => 'in ' . $diff . ' days',
            default => abs($diff) . ' days ago',
        };
    }
}
