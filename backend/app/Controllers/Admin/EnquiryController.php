<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\License;
use App\Core\Response;
use App\Core\Template;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CustomerRepository;
use App\Repositories\EnquiryRepository;
use App\Repositories\EnquiryRepository as Enq;
use App\Repositories\LeadActivityRepository;
use App\Repositories\TaskRepository;

/**
 * Lead inbox. Enquiries are private (not website content) → no Revalidator::ping here.
 * Basic plan: status + notes. With the "crm" feature: stages, priority, owner, follow-ups, timeline, manual entry.
 */
final class EnquiryController extends Controller
{
    public function index(): never
    {
        $filters = $this->filters();
        $page = filter_var($this->request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $result = EnquiryRepository::paginate($filters, $page, 20);
        $active = array_filter($filters, static fn ($v) => $v !== '');
        $exportUrl = url('/enquiries/export') . ($active === [] ? '' : '?' . http_build_query($active));

        $actions = '<a href="' . e($exportUrl) . '" class="btn" title="Download the enquiries shown below as a spreadsheet (CSV)">'
            . icon('arrow-down') . ' Download CSV</a>';
        if (feature('crm')) {
            $actions .= '<a href="' . e(url('/enquiries/create')) . '" class="btn btn-primary" title="Add a phone call, WhatsApp or walk-in enquiry">'
                . icon('plus') . ' New enquiry</a>';
        }

        $this->view('admin/enquiries/index', [
            'title' => 'Enquiries',
            'pretitle' => 'Leads from your website',
            'filters' => $filters,
            'result' => $result,
            'newCount' => EnquiryRepository::countByStatus('new'),
            'crm' => feature('crm'),
            'pageActions' => $actions,
        ]);
    }

    public function show(string $id): never
    {
        // GET /enquiries/create is matched by the older /enquiries/{id} route first (routes.php) — hand it over.
        if ($id === 'create') {
            $this->create();
        }
        $enquiry = $this->findOr404($id);
        $crm = feature('crm');
        $eid = (int) $enquiry['id'];
        $data = [
            'title' => 'Enquiry from ' . $enquiry['name'],
            'pretitle' => 'Enquiry #' . $enquiry['id'],
            'enquiry' => $enquiry,
            'crm' => $crm,
        ];
        if ($crm) {
            $lead = Enq::findLead($eid) ?? $enquiry;
            $trip = Enq::tripLabel($lead);
            $data += [
                'enquiry' => $lead,
                'activities' => LeadActivityRepository::forEnquiry($eid),
                'tasks' => TaskRepository::forRelated('enquiry', $eid),
                'assignees' => Enq::assignees(),
                'customer' => $lead['customer_id'] ? CustomerRepository::find((int) $lead['customer_id']) : null,
                'waText' => Template::message('Enquiry — first reply', [
                    'customer_name' => $lead['name'],
                    'trip' => $trip !== '—' ? $trip : 'your trip',
                ], 'Hi {{customer_name}}, thank you for contacting {{company_name}} about {{trip}}. We will share the best options shortly. — {{company_phone}}'),
            ];
        }
        $this->view('admin/enquiries/show', $data);
    }

    /** Manual entry form (phone call / WhatsApp / walk-in). Needs the "crm" feature. */
    public function create(): never
    {
        if (!License::has('crm')) {
            echo View::render('admin/license/locked', ['title' => 'Upgrade to unlock', 'lockedFeature' => 'crm']);
            exit;
        }
        $this->view('admin/enquiries/create', [
            'title' => 'New enquiry',
            'pretitle' => 'Enquiries',
            'assignees' => Enq::assignees(),
            'packages' => \App\Core\Database::fetchAll("SELECT title FROM packages ORDER BY status = 'published' DESC, sort_order, title LIMIT 200"),
            'destinations' => \App\Core\Database::fetchAll('SELECT name FROM destinations ORDER BY sort_order, name LIMIT 200'),
        ]);
    }

    /** POST /enquiries — save a manual enquiry. */
    public function store(): never
    {
        $in = $this->request->all();
        $stages = array_keys(array_filter(Enq::stages(), static fn ($s, $k) => $k !== 'lost', ARRAY_FILTER_USE_BOTH));
        $v = Validator::make($in, [
            'name' => 'required|string|max:120',
            'phone' => 'required|phone|max:30',
            'email' => 'nullable|email|max:190',
            'destination' => 'nullable|string|max:120',
            'package' => 'nullable|string|max:160',
            'travel_month' => 'nullable|string|max:40',
            'travel_date' => 'nullable|string|max:10',
            'adults' => 'required|int|min:1|max:30',
            'children' => 'nullable|int|min:0|max:20',
            'hotel_category' => 'nullable|string|max:40',
            'budget' => 'nullable|int|min:0|max:100000000',
            'source' => 'required|in:' . implode(',', array_keys(config('crm')['sources'] ?? [])),
            'stage' => 'required|in:' . implode(',', $stages),
            'priority' => 'required|in:' . implode(',', array_keys(Enq::PRIORITIES)),
            'assigned_to' => 'nullable|int|min:1',
            'next_follow_up_at' => 'nullable|string|max:20',
            'message' => 'nullable|string|max:2000',
        ], ['travel_date' => 'Travel date', 'next_follow_up_at' => 'Next follow-up', 'assigned_to' => 'Assigned to', 'message' => 'Requirements']);
        $errors = $v->errors();
        $d = $v->validated();
        $this->checkCrmFields($d, $errors);

        $phone = null;
        if (!isset($errors['phone'])) {
            $phone = $this->formatPhone((string) $d['phone']);
            if ($phone === null) {
                $errors['phone'] = 'Please enter a valid phone number (10-digit Indian mobile, or with country code).';
            }
        }
        if ($errors !== []) {
            $this->back('/enquiries/create', $errors);
        }

        $d['phone'] = $phone;
        $d['children'] ??= 0;
        $id = Enq::create($d);
        $lead = Enq::find($id);
        $customerId = null;
        if (License::has('customers') && $lead) {
            try {
                $customerId = Enq::ensureCustomer($lead);
            } catch (\Throwable $e) {
                error_log('[manual enquiry → customer] ' . $e->getMessage());
            }
        }
        $me = Auth::user()['name'] ?? 'the team';
        LeadActivityRepository::log($id, $customerId, 'system', sprintf('Enquiry added by %s (%s)', $me, Enq::sourceLabel($d['source'])));
        if ($d['next_follow_up_at'] !== null) {
            TaskRepository::syncEnquiryFollowUp($id, (string) $d['name'], null, $d['next_follow_up_at'], $d['assigned_to']);
        }
        Activity::log('created', 'enquiry', $id, sprintf('Added enquiry from “%s” (%s)', $d['name'], Enq::sourceLabel($d['source'])));
        Flash::success('Enquiry from ' . $d['name'] . ' saved.');
        $this->redirect('/enquiries/' . $id);
    }

    public function update(string $id): never
    {
        $enquiry = $this->findOr404($id);
        if (feature('crm') && $this->request->input('stage') !== null) {
            $this->updateLead($enquiry);
        }

        $v = Validator::make($this->request->all(), [
            'status' => 'required|in:' . implode(',', array_keys(EnquiryRepository::STATUSES)),
            'notes' => 'nullable|string|max:5000',
        ], ['notes' => 'Internal notes']);
        if ($v->fails()) {
            $this->back('/enquiries/' . $enquiry['id'], $v->errors());
        }
        $data = $v->validated();
        EnquiryRepository::updateStatusNotes((int) $enquiry['id'], $data['status'], $data['notes']);

        // Keep the pipeline stage in step with the Basic status (e.g. after a downgrade/upgrade).
        $stage = Enq::stageForStatus($data['status'], (string) $enquiry['stage']);
        if ($stage !== $enquiry['stage']) {
            Enq::moveStage((int) $enquiry['id'], $stage, $stage === 'lost' ? $enquiry['lost_reason'] : null);
        }

        $summary = $data['status'] !== $enquiry['status']
            ? sprintf('Marked enquiry from “%s” as %s', $enquiry['name'], EnquiryRepository::statusLabel($data['status']))
            : sprintf('Updated notes on enquiry from “%s”', $enquiry['name']);
        Activity::log('updated', 'enquiry', (int) $enquiry['id'], $summary);
        Flash::success('Enquiry saved.');
        $this->redirect('/enquiries/' . $enquiry['id']);
    }

    /** CRM version of the save: stage, priority, owner, budget, dates, notes. @param array<string,mixed> $enquiry */
    private function updateLead(array $enquiry): never
    {
        $eid = (int) $enquiry['id'];
        $v = Validator::make($this->request->all(), [
            'stage' => 'required|in:' . implode(',', array_keys(Enq::stages())),
            'priority' => 'required|in:' . implode(',', array_keys(Enq::PRIORITIES)),
            'assigned_to' => 'nullable|int|min:1',
            'budget' => 'nullable|int|min:0|max:100000000',
            'travel_date' => 'nullable|string|max:10',
            'next_follow_up_at' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:5000',
            'lost_reason' => 'nullable|in:' . implode(',', config('crm')['lost_reasons'] ?? []),
            'lost_note' => 'nullable|string|max:150',
        ], ['notes' => 'Internal notes', 'travel_date' => 'Travel date', 'next_follow_up_at' => 'Next follow-up',
            'assigned_to' => 'Assigned to', 'lost_reason' => 'Reason lost', 'lost_note' => 'Details']);
        $errors = $v->errors();
        $d = $v->validated();
        $this->checkCrmFields($d, $errors);

        $reason = null;
        if (($d['stage'] ?? '') === 'lost' && !isset($errors['lost_reason'])) {
            if (($d['lost_reason'] ?? null) === null) {
                // Keep a previously saved reason if the form did not change it.
                if (($enquiry['lost_reason'] ?? '') !== '') {
                    $reason = (string) $enquiry['lost_reason'];
                } else {
                    $errors['lost_reason'] = 'Please choose why this lead was lost.';
                }
            } else {
                $note = trim((string) ($d['lost_note'] ?? ''));
                $reason = mb_substr($d['lost_reason'] . ($note !== '' ? ' — ' . $note : ''), 0, 200);
            }
        }
        if ($errors !== []) {
            $this->back('/enquiries/' . $eid, $errors);
        }

        Enq::updateLead($eid, [
            'stage' => $d['stage'],
            'priority' => $d['priority'],
            'assigned_to' => $d['assigned_to'],
            'budget' => $d['budget'],
            'travel_date' => $d['travel_date'],
            'next_follow_up_at' => $d['next_follow_up_at'],
            'notes' => $d['notes'],
            'lost_reason' => $reason,
        ]);

        $customerId = $enquiry['customer_id'] !== null ? (int) $enquiry['customer_id'] : null;
        if ($d['stage'] === 'won' && $customerId === null && License::has('customers')) {
            try {
                $customerId = Enq::ensureCustomer($enquiry);
            } catch (\Throwable $e) {
                error_log('[lead won → customer] ' . $e->getMessage());
            }
        }

        if ($d['stage'] !== $enquiry['stage'] || ($d['stage'] === 'lost' && $reason !== $enquiry['lost_reason'])) {
            LeadActivityRepository::log($eid, $customerId, 'stage', sprintf('Moved from %s to %s', Enq::stageLabel((string) $enquiry['stage']), Enq::stageLabel($d['stage']))
                . ($d['stage'] === 'lost' && $reason ? ' — reason: ' . $reason : ''));
        }
        if ((int) ($enquiry['assigned_to'] ?? 0) !== (int) ($d['assigned_to'] ?? 0)) {
            $name = 'nobody';
            foreach (Enq::assignees() as $a) {
                if ((int) $a['id'] === (int) $d['assigned_to']) {
                    $name = $a['name'];
                }
            }
            LeadActivityRepository::log($eid, $customerId, 'system', 'Assigned to ' . $name);
        }
        TaskRepository::syncEnquiryFollowUp($eid, (string) $enquiry['name'], $enquiry['next_follow_up_at'], $d['next_follow_up_at'], $d['assigned_to']);

        Activity::log('updated', 'enquiry', $eid, sprintf('Updated lead “%s” (%s)', $enquiry['name'], Enq::stageLabel($d['stage'])));
        $msg = 'Lead saved.';
        if ($d['stage'] === 'won' && $enquiry['stage'] !== 'won') {
            $msg = 'Lead won — well done! ' . (feature('bookings') && can('bookings') ? 'Use “Create booking” to confirm the trip.' : '');
        }
        Flash::success(trim($msg));
        $this->redirect('/enquiries/' . $eid);
    }

    /** POST /enquiries/{id}/activities — log a note / call / WhatsApp / email / meeting. */
    public function activity(string $id): never
    {
        $enquiry = $this->findOr404($id);
        $v = Validator::make($this->request->all(), [
            'type' => 'required|in:' . implode(',', LeadActivityRepository::MANUAL_TYPES),
            'body' => 'required|string|max:2000',
        ], ['type' => 'Activity type', 'body' => 'What happened']);
        if ($v->fails()) {
            $this->back('/enquiries/' . $enquiry['id'] . '#timeline', $v->errors());
        }
        $d = $v->validated();
        $eid = (int) $enquiry['id'];
        LeadActivityRepository::log($eid, $enquiry['customer_id'] !== null ? (int) $enquiry['customer_id'] : null, $d['type'], $d['body']);

        // Talking to a brand-new lead moves it to "Contacted".
        if ($enquiry['stage'] === 'new' && in_array($d['type'], ['call', 'whatsapp', 'email', 'meeting'], true)) {
            Enq::moveStage($eid, 'contacted');
            LeadActivityRepository::log($eid, $enquiry['customer_id'] !== null ? (int) $enquiry['customer_id'] : null, 'stage', 'Moved from New to Contacted');
        }
        Activity::log('updated', 'enquiry', $eid, sprintf('Logged %s with “%s”', strtolower(LeadActivityRepository::label($d['type'])), $enquiry['name']));
        Flash::success(LeadActivityRepository::label($d['type']) . ' added to the timeline.');
        $this->redirect('/enquiries/' . $eid . '#timeline');
    }

    /** POST /enquiries/{id}/customer — link to an existing customer (same phone) or create one. */
    public function convert(string $id): never
    {
        $enquiry = $this->findOr404($id);
        $eid = (int) $enquiry['id'];
        if ($enquiry['customer_id'] && ($c = CustomerRepository::find((int) $enquiry['customer_id']))) {
            Flash::info($enquiry['name'] . ' is already linked to customer ' . $c['name'] . '.');
            $this->redirect('/customers/' . $c['id']);
        }
        $existing = CustomerRepository::findByPhone((string) $enquiry['phone']);
        try {
            $customerId = Enq::ensureCustomer($enquiry);
        } catch (\Throwable $e) {
            error_log('[convert enquiry] ' . $e->getMessage());
            Flash::error('Could not create the customer — please check the phone number on this enquiry.');
            $this->redirect('/enquiries/' . $eid);
        }
        LeadActivityRepository::log($eid, $customerId, 'system', $existing ? 'Linked to existing customer ' . $existing['name'] : 'Converted to a customer');
        Activity::log($existing ? 'updated' : 'created', 'customer', $customerId, $existing
            ? sprintf('Linked enquiry #%d to customer “%s”', $eid, $existing['name'])
            : sprintf('Created customer “%s” from enquiry #%d', $enquiry['name'], $eid));
        Flash::success($existing
            ? 'This phone number already belonged to ' . $existing['name'] . ' — the enquiry is now linked to them.'
            : $enquiry['name'] . ' is now a customer.');
        $this->redirect('/customers/' . $customerId);
    }

    public function destroy(string $id): never
    {
        $enquiry = $this->findOr404($id);
        EnquiryRepository::delete((int) $enquiry['id']);
        if (feature('crm') || feature('customers')) {
            \App\Core\Database::query('DELETE FROM lead_activities WHERE enquiry_id = ? AND customer_id IS NULL', [(int) $enquiry['id']]);
            // Its follow-up reminders go too (a "call back" for a deleted lead is just noise).
            \App\Core\Database::query("DELETE FROM tasks WHERE related_type = 'enquiry' AND related_id = ?", [(int) $enquiry['id']]);
        }
        Activity::log('deleted', 'enquiry', (int) $enquiry['id'], sprintf('Deleted enquiry from “%s”', $enquiry['name']));
        Flash::success('Enquiry deleted.');
        $this->redirect('/enquiries');
    }

    /** CSV download of the current filtered list (UTF-8 with BOM so Excel shows ₹ and names correctly). */
    public function export(): never
    {
        $filters = $this->filters();
        $rows = EnquiryRepository::forExport($filters);
        $crm = feature('crm');
        $names = [];
        if ($crm) {
            foreach (Enq::assignees() as $a) {
                $names[(int) $a['id']] = $a['name'];
            }
        }

        Activity::log('exported', 'enquiry', null, sprintf('Downloaded %d enquir%s as CSV', count($rows), count($rows) === 1 ? 'y' : 'ies'));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="enquiries-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        $head = [
            'ID', 'Received', 'Status', 'Name', 'Phone', 'Email', 'Destination', 'Package', 'Travel month',
            'Adults', 'Children', 'Hotel category', 'Message', 'Source', 'Internal notes',
        ];
        if ($crm) {
            array_push($head, 'Stage', 'Priority', 'Assigned to', 'Budget', 'Travel date', 'Next follow-up', 'Lost reason');
        }
        $this->csvRow($out, $head);
        foreach ($rows as $r) {
            $line = [
                $r['id'],
                (string) $r['created_at'],
                EnquiryRepository::statusLabel((string) $r['status']),
                $r['name'],
                $r['phone'],
                $r['email'],
                $r['destination'],
                $r['package'],
                $r['travel_month'],
                $r['adults'],
                $r['children'],
                $r['hotel_category'],
                $r['message'],
                EnquiryRepository::sourceLabel($r['source']),
                $r['notes'],
            ];
            if ($crm) {
                array_push(
                    $line,
                    Enq::stageLabel((string) $r['stage']),
                    Enq::PRIORITIES[$r['priority']] ?? $r['priority'],
                    $names[(int) $r['assigned_to']] ?? '',
                    $r['budget'],
                    $r['travel_date'],
                    $r['next_follow_up_at'],
                    $r['lost_reason'],
                );
            }
            $this->csvRow($out, $line);
        }
        fclose($out);
        exit;
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** @return array{q:string,status:string,from:string,to:string,stage:string} */
    private function filters(): array
    {
        return EnquiryRepository::filtersFrom(
            $this->request->query('q', ''),
            $this->request->query('status', ''),
            $this->request->query('from', ''),
            $this->request->query('to', ''),
            feature('crm') ? $this->request->query('stage', '') : '',
        );
    }

    /**
     * Shared checks for the CRM fields: dates, assignee. Normalises $d in place.
     * @param array<string,mixed> $d @param array<string,string> $errors
     */
    private function checkCrmFields(array &$d, array &$errors): void
    {
        if (!isset($errors['travel_date']) && ($d['travel_date'] ?? null) !== null) {
            $t = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $d['travel_date']);
            if (!$t || $t->format('Y-m-d') !== $d['travel_date'] || (int) $t->format('Y') < 2000 || (int) $t->format('Y') > 2100) {
                $errors['travel_date'] = 'Please pick a valid travel date.';
            }
        }
        if (!isset($errors['next_follow_up_at']) && ($d['next_follow_up_at'] ?? null) !== null) {
            $dt = null;
            foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $fmt) {
                $x = \DateTimeImmutable::createFromFormat('!' . $fmt, (string) $d['next_follow_up_at']);
                if ($x && $x->format($fmt) === $d['next_follow_up_at']) {
                    $dt = $x;
                    break;
                }
            }
            if ($dt === null || (int) $dt->format('Y') < 2000 || (int) $dt->format('Y') > 2100) {
                $errors['next_follow_up_at'] = 'Please pick a valid date and time.';
            } else {
                $d['next_follow_up_at'] = $dt->format('Y-m-d H:i:00');
            }
        }
        if (!isset($errors['assigned_to']) && ($d['assigned_to'] ?? null) !== null
            && !in_array((int) $d['assigned_to'], array_map(static fn ($a) => (int) $a['id'], Enq::assignees()), true)) {
            $errors['assigned_to'] = 'Please choose a team member from the list.';
        }
    }

    /** Indian 10-digit mobiles → "+91XXXXXXXXXX" (same as the website form); other numbers kept compact. */
    private function formatPhone(string $raw): ?string
    {
        $compact = (string) preg_replace('/[\s\-().]/', '', $raw);
        if (preg_match('/^(?:\+91|91|0)?([6-9]\d{9})$/', $compact, $m) === 1) {
            return '+91' . $m[1];
        }
        $digits = preg_replace('/\D+/', '', $compact) ?? '';
        return strlen($digits) >= 7 && strlen($digits) <= 15 ? (str_starts_with($compact, '+') ? '+' : '') . $digits : null;
    }

    /** @return array<string,mixed> */
    private function findOr404(string $rawId): array
    {
        $enquiry = EnquiryRepository::find($this->id($rawId));
        if ($enquiry === null) {
            Response::abort(404);
        }
        return $enquiry;
    }

    /**
     * Write one CSV line, neutralising spreadsheet formula injection:
     * cells starting with = + - @ (or tab / CR) get a leading single quote.
     * @param resource $out
     * @param list<mixed> $cells
     */
    private function csvRow($out, array $cells): void
    {
        $clean = array_map(static function (mixed $v): string {
            $s = (string) ($v ?? '');
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $s = "'" . $s;
            }
            return $s;
        }, $cells);
        fputcsv($out, $clean, ',', '"', '');
    }
}
