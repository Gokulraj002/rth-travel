<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Response;
use App\Repositories\ReportRepository;

/** Business reports (Pro) with CSV export. */
final class ReportController extends Controller
{
    public const PRESETS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'this_fy' => 'This financial year',
        'last_fy' => 'Last financial year',
        'custom' => 'Custom dates',
    ];

    public function index(): never
    {
        $range = $this->range();
        $tabs = self::tabs();
        $tab = (string) $this->request->query('tab', 'sales');
        if (!isset($tabs[$tab])) {
            $tab = 'sales';
        }
        [$from, $to] = [$range['from'], $range['to']];
        $report = match ($tab) {
            'sales' => ReportRepository::sales($from, $to),
            'outstanding' => ['rows' => ReportRepository::outstanding()],
            'leads' => ReportRepository::leads($from, $to),
            'top' => ['packages' => ReportRepository::topPackages($from, $to), 'destinations' => ReportRepository::topDestinations($from, $to)],
            'profit' => ReportRepository::profit($from, $to),
            'gst' => ReportRepository::gst($from, $to),
        };
        $this->view('admin/reports/index', [
            'title' => 'Reports',
            'pretitle' => 'Accounts',
            'range' => $range,
            'tabs' => $tabs,
            'tab' => $tab,
            'report' => $report,
        ]);
    }

    public function export(string $section): never
    {
        $tabs = self::tabs();
        if (!isset($tabs[$section])) {
            Response::abort(404);
        }
        $r = $this->range();
        [$from, $to] = [$r['from'], $r['to']];
        $rows = [];
        switch ($section) {
            case 'sales':
                $d = ReportRepository::sales($from, $to);
                $rows[] = ['Month', 'Bookings', 'Booking value (INR)', 'Collections (INR)'];
                foreach ($d['months'] as $m) {
                    $rows[] = [self::monthLabel($m['month']), $m['bookings'], self::n($m['value']), self::n($m['collected'])];
                }
                $rows[] = ['Total', $d['count'], self::n($d['value']), self::n($d['collected'])];
                $rows[] = [];
                $rows[] = ['Payment method', 'Collections (INR)'];
                foreach ($d['methods'] as $method => $v) {
                    $rows[] = [self::methodLabel($method), self::n($v)];
                }
                break;
            case 'outstanding':
                $rows[] = ['Booking', 'Customer', 'Phone', 'Trip', 'Travel from', 'Travel to', 'Status', 'Total (INR)', 'Paid (INR)', 'Balance due (INR)'];
                $due = 0.0;
                foreach (ReportRepository::outstanding() as $b) {
                    $bal = (float) $b['total_amount'] - (float) $b['paid'];
                    $due += $bal;
                    $rows[] = [$b['number'], $b['customer_name'], $b['customer_phone'], $b['title'], $b['travel_start'], $b['travel_end'], $b['status'],
                               self::n($b['total_amount']), self::n($b['paid']), self::n($bal)];
                }
                $rows[] = ['Total due', '', '', '', '', '', '', '', '', self::n($due)];
                break;
            case 'leads':
                $d = ReportRepository::leads($from, $to);
                $sources = config('crm')['sources'] ?? [];
                $stages = config('crm')['stages'] ?? [];
                $rows[] = ['Section', 'Name', 'Enquiries'];
                foreach ($d['sources'] as $k => $n) {
                    $rows[] = ['Source', $sources[$k] ?? ucfirst($k), $n];
                }
                foreach ($stages as $k => $st) {
                    $rows[] = ['Stage', $st['label'], $d['stages'][$k] ?? 0];
                }
                $rows[] = ['Total', '', $d['total']];
                $rows[] = ['Conversion rate (won ÷ won+lost)', '', $d['conversion'] === null ? '' : round($d['conversion'] * 100, 1) . '%'];
                break;
            case 'top':
                $rows[] = ['Type', 'Name', 'Bookings', 'Revenue (INR)'];
                foreach (ReportRepository::topPackages($from, $to, 100) as $p) {
                    $rows[] = ['Package', $p['name'], $p['bookings'], self::n($p['revenue'])];
                }
                foreach (ReportRepository::topDestinations($from, $to, 100) as $p) {
                    $rows[] = ['Destination', $p['name'], $p['bookings'], self::n($p['revenue'])];
                }
                break;
            case 'profit':
                $d = ReportRepository::profit($from, $to);
                $rows[] = ['Booking', 'Booked on', 'Customer', 'Trip', 'Travel from', 'Revenue (INR)', 'Supplier cost (INR)', 'Profit (INR)', 'Margin %'];
                foreach ($d['bookings'] as $b) {
                    $rows[] = [$b['number'], substr((string) $b['created_at'], 0, 10), $b['customer_name'], $b['title'], $b['travel_start'],
                               self::n($b['revenue']), self::n($b['cost']), self::n($b['profit']), $b['margin'] === null ? '' : round($b['margin'] * 100, 1)];
                }
                $rows[] = ['Total', '', '', '', '', self::n($d['revenue']), self::n($d['cost']), self::n($d['profit']), $d['margin'] === null ? '' : round($d['margin'] * 100, 1)];
                break;
            case 'gst':
                $d = ReportRepository::gst($from, $to);
                $rows[] = ['Month', 'Invoices', 'Taxable value (INR)', 'CGST (INR)', 'SGST (INR)', 'IGST (INR)', 'Invoice total (INR)'];
                foreach ($d['months'] as $m) {
                    $rows[] = [self::monthLabel($m['month']), $m['invoices'], self::n($m['taxable']), self::n($m['cgst']), self::n($m['sgst']), self::n($m['igst']), self::n($m['total'])];
                }
                $t = $d['totals'];
                $rows[] = ['Total', $t['invoices'], self::n($t['taxable']), self::n($t['cgst']), self::n($t['sgst']), self::n($t['igst']), self::n($t['total'])];
                $rows[] = [];
                $rows[] = ['Place of supply', 'Invoices', 'Taxable value (INR)', 'CGST (INR)', 'SGST (INR)', 'IGST (INR)', 'Invoice total (INR)'];
                foreach ($d['states'] as $s) {
                    $rows[] = [$s['state'], $s['invoices'], self::n($s['taxable']), self::n($s['cgst']), self::n($s['sgst']), self::n($s['igst']), self::n($s['total'])];
                }
                break;
        }

        Activity::log('exported', 'report', null, 'Exported ' . strtolower($tabs[$section]) . ' report (' . $from . ' to ' . $to . ')');
        $name = 'rth-' . $section . '-report-' . $from . '-to-' . $to . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'cell'], $row), ',', '"', '');
        }
        fclose($out);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Report sections the current user may see. @return array<string,string> */
    public static function tabs(): array
    {
        $tabs = ['sales' => 'Sales', 'outstanding' => 'Outstanding', 'leads' => 'Leads', 'top' => 'Top packages'];
        if (feature('profit') && can('profit')) {
            $tabs['profit'] = 'Profit';
        }
        if (feature('invoices')) {
            $tabs['gst'] = 'GST summary';
        }
        return $tabs;
    }

    /** @return array{preset:string,from:string,to:string,label:string} */
    private function range(): array
    {
        $preset = (string) $this->request->query('preset', 'this_fy');
        if (!isset(self::PRESETS[$preset])) {
            $preset = 'this_fy';
        }
        $today = new \DateTimeImmutable('today');
        $y = (int) $today->format('Y');
        $m = (int) $today->format('n');
        $fyStart = $m >= 4 ? $y : $y - 1;
        switch ($preset) {
            case 'this_month':
                $from = $today->modify('first day of this month');
                $to = $today->modify('last day of this month');
                break;
            case 'last_month':
                $from = $today->modify('first day of last month');
                $to = $today->modify('last day of last month');
                break;
            case 'this_quarter':
                $qStart = intdiv($m - 1, 3) * 3 + 1;
                $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $y, $qStart));
                $to = $from->modify('+2 months')->modify('last day of this month');
                break;
            case 'last_fy':
                $from = new \DateTimeImmutable(($fyStart - 1) . '-04-01');
                $to = new \DateTimeImmutable($fyStart . '-03-31');
                break;
            case 'custom':
                $from = self::date((string) $this->request->query('from', '')) ?? $today->modify('first day of this month');
                $to = self::date((string) $this->request->query('to', '')) ?? $today;
                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }
                if ($from < $to->modify('-3 years')) {
                    $from = $to->modify('-3 years')->modify('+1 day');
                }
                break;
            default: // this_fy
                $from = new \DateTimeImmutable($fyStart . '-04-01');
                $to = new \DateTimeImmutable(($fyStart + 1) . '-03-31');
        }
        $label = match ($preset) {
            'this_fy', 'last_fy' => 'FY ' . $from->format('Y') . '–' . $to->format('y') . ' (' . $from->format('j M Y') . ' – ' . $to->format('j M Y') . ')',
            default => $from->format('j M Y') . ' – ' . $to->format('j M Y'),
        };
        return ['preset' => $preset, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'label' => $label];
    }

    private static function date(string $s): ?\DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        return $d !== false && $d->format('Y-m-d') === $s && (int) $d->format('Y') >= 2000 && (int) $d->format('Y') <= 2100 ? $d : null;
    }

    public static function monthLabel(string $ym): string
    {
        $ts = strtotime($ym . '-01');
        return $ts ? date('M Y', $ts) : $ym;
    }

    public static function methodLabel(string $m): string
    {
        return ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank transfer', 'card' => 'Card', 'cheque' => 'Cheque', 'other' => 'Other'][$m] ?? ucfirst($m);
    }

    private static function n(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    /** Neutralise spreadsheet formulas in text cells (CSV injection). */
    private static function cell(mixed $v): string
    {
        $s = (string) ($v ?? '');
        if ($s !== '' && !is_numeric($s) && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $s;
        }
        return $s;
    }
}
