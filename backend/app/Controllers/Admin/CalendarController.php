<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\BookingRepository as Bookings;

/** Monthly trip calendar: bookings as bars across their travel days + open follow-ups (tasks). Server-rendered. */
final class CalendarController extends Controller
{
    public function index(): never
    {
        $m = $this->request->query('month', '');
        $month = is_string($m) && Bookings::validMonth($m) ? $m : date('Y-m');
        $first = new \DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');

        // Grid: Monday of the first week → Sunday of the last week.
        $gridStart = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
        $gridEnd = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');

        $bookings = Bookings::overlapping($gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d'));
        $tasks = Database::fetchAll(
            "SELECT id, title, due_at, related_type, related_id FROM tasks
             WHERE done_at IS NULL AND due_at >= ? AND due_at < ? ORDER BY due_at, id",
            [$gridStart->format('Y-m-d') . ' 00:00:00', $gridEnd->modify('+1 day')->format('Y-m-d') . ' 00:00:00'],
        );
        $tasksByDay = [];
        foreach ($tasks as $t) {
            $tasksByDay[substr((string) $t['due_at'], 0, 10)][] = $t;
        }

        // Build weeks with bar segments (each booking clipped to the week).
        $weeks = [];
        for ($ws = $gridStart; $ws <= $gridEnd; $ws = $ws->modify('+7 days')) {
            $we = $ws->modify('+6 days');
            $wsS = $ws->format('Y-m-d');
            $weS = $we->format('Y-m-d');
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $d = $ws->modify("+{$i} days");
                $days[] = $d->format('Y-m-d');
            }
            $segments = [];
            foreach ($bookings as $b) {
                if ($b['travel_start'] > $weS || $b['travel_end'] < $wsS) {
                    continue;
                }
                $segStart = max($b['travel_start'], $wsS);
                $segEnd = min($b['travel_end'], $weS);
                $col = (int) (new \DateTimeImmutable($wsS))->diff(new \DateTimeImmutable($segStart))->days + 1;
                $span = (int) (new \DateTimeImmutable($segStart))->diff(new \DateTimeImmutable($segEnd))->days + 1;
                $segments[] = [
                    'booking' => $b,
                    'col' => $col,
                    'span' => $span,
                    'starts' => $segStart === $b['travel_start'],
                    'ends' => $segEnd === $b['travel_end'],
                ];
            }
            $weeks[] = ['days' => $days, 'segments' => $segments];
        }

        // Agenda (mobile): per day in this month — departures, returns, follow-ups.
        $agenda = [];
        foreach ($bookings as $b) {
            foreach (['travel_start' => 'departs', 'travel_end' => 'returns'] as $col => $kind) {
                $day = (string) $b[$col];
                if ($day >= $first->format('Y-m-d') && $day <= $last->format('Y-m-d')) {
                    if ($kind === 'returns' && $b['travel_end'] === $b['travel_start']) {
                        continue;
                    }
                    $agenda[$day]['trips'][] = ['kind' => $kind, 'booking' => $b];
                }
            }
        }
        foreach ($tasksByDay as $day => $list) {
            if ($day >= $first->format('Y-m-d') && $day <= $last->format('Y-m-d')) {
                $agenda[$day]['tasks'] = $list;
            }
        }
        ksort($agenda);

        $inMonth = array_filter($bookings, static fn ($b) => $b['travel_start'] <= $last->format('Y-m-d') && $b['travel_end'] >= $first->format('Y-m-d'));
        $departures = array_filter($bookings, static fn ($b) => substr((string) $b['travel_start'], 0, 7) === $month);

        $prev = $first->modify('-1 month')->format('Y-m');
        $next = $first->modify('+1 month')->format('Y-m');
        $actions = '<div class="btn-group" role="group" aria-label="Change month">'
            . '<a class="btn btn-icon" href="' . e(url('/calendar?month=' . $prev)) . '" title="Previous month" aria-label="Previous month">' . icon('chevron-left') . '</a>'
            . '<a class="btn" href="' . e(url('/calendar')) . '">Today</a>'
            . '<a class="btn btn-icon" href="' . e(url('/calendar?month=' . $next)) . '" title="Next month" aria-label="Next month">' . icon('chevron-right') . '</a>'
            . '</div>';
        if (can('bookings')) {
            $actions .= '<a class="btn btn-primary" href="' . e(url('/bookings/create')) . '">' . icon('plus') . ' New booking</a>';
        }

        $this->view('admin/calendar/index', [
            'title' => 'Trip calendar — ' . $first->format('F Y'),
            'pageTitle' => $first->format('F Y'),
            'pretitle' => 'Trip calendar',
            'month' => $month,
            'first' => $first,
            'weeks' => $weeks,
            'tasksByDay' => $tasksByDay,
            'agenda' => $agenda,
            'tripCount' => count($inMonth),
            'departureCount' => count($departures),
            'taskCount' => array_sum(array_map(static fn ($d) => count($d['tasks'] ?? []), $agenda)),
            'today' => date('Y-m-d'),
            'pageActions' => $actions,
        ]);
    }
}
