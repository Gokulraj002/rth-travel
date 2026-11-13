<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\CustomerRepository as Customers;
use App\Repositories\LeadActivityRepository;
use App\Repositories\TaskRepository;

/** Customer database (feature "customers", permission "customers"). */
final class CustomerController extends Controller
{
    public function index(): never
    {
        $filters = $this->filters();
        $page = filter_var($this->request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $active = array_filter($filters, static fn ($v) => $v !== '');
        $exportUrl = url('/customers/export') . ($active === [] ? '' : '?' . http_build_query($active));

        $this->view('admin/customers/index', [
            'title' => 'Customers',
            'pretitle' => 'Your travellers',
            'filters' => $filters,
            'result' => Customers::paginate($filters, $page),
            'tags' => Customers::allTags(),
            'pageActions' => '<a href="' . e($exportUrl) . '" class="btn" title="Download the customers shown below as a spreadsheet (CSV)">'
                . icon('download') . ' Download CSV</a>'
                . '<a href="' . e(url('/customers/create')) . '" class="btn btn-primary">' . icon('plus') . ' Add customer</a>',
        ]);
    }

    public function create(): never
    {
        $this->view('admin/customers/form', [
            'title' => 'Add customer',
            'pretitle' => 'Customers',
            'customer' => null,
            'tags' => Customers::allTags(),
        ]);
    }

    public function store(): never
    {
        $data = $this->validated('/customers/create', null);
        $id = Customers::create($data);
        LeadActivityRepository::log(null, $id, 'system', 'Customer added');
        Activity::log('created', 'customer', $id, sprintf('Added customer “%s”', $data['name']));
        Flash::success('Customer “' . $data['name'] . '” added.');
        $this->redirect('/customers/' . $id);
    }

    public function show(string $id): never
    {
        $c = $this->findOr404($id);
        $cid = (int) $c['id'];
        $actions = '';
        if (feature('quotations') && can('quotations')) {
            $actions .= '<a href="' . e(url('/quotations/create?customer_id=' . $cid)) . '" class="btn">' . icon('file-text') . ' New quotation</a>';
        }
        if (feature('bookings') && can('bookings')) {
            $actions .= '<a href="' . e(url('/bookings/create?customer_id=' . $cid)) . '" class="btn btn-primary">' . icon('briefcase') . ' New booking</a>';
        }
        $actions .= '<a href="' . e(url('/customers/' . $cid . '/edit')) . '" class="btn">' . icon('edit') . ' Edit</a>';

        $this->view('admin/customers/show', [
            'title' => $c['name'],
            'pretitle' => 'Customer',
            'customer' => $c,
            'totals' => Customers::totals($cid),
            'bookings' => Customers::bookings($cid),
            'quotations' => Customers::quotations($cid),
            'enquiries' => Customers::enquiries($cid),
            'activities' => LeadActivityRepository::forCustomer($cid),
            'tasks' => feature('crm') && can('enquiries') ? TaskRepository::forRelated('customer', $cid) : [],
            'dependants' => Customers::dependants($cid),
            'pageActions' => $actions,
        ]);
    }

    public function edit(string $id): never
    {
        $c = $this->findOr404($id);
        $this->view('admin/customers/form', [
            'title' => 'Edit ' . $c['name'],
            'pretitle' => 'Customers',
            'customer' => $c,
            'tags' => Customers::allTags(),
        ]);
    }

    public function update(string $id): never
    {
        $c = $this->findOr404($id);
        $data = $this->validated('/customers/' . $c['id'] . '/edit', (int) $c['id']);
        Customers::update((int) $c['id'], $data);
        Activity::log('updated', 'customer', (int) $c['id'], sprintf('Updated customer “%s”', $data['name']));
        Flash::success('Customer saved.');
        $this->redirect('/customers/' . $c['id']);
    }

    public function destroy(string $id): never
    {
        $c = $this->findOr404($id);
        $dep = Customers::dependants((int) $c['id']);
        $blocking = array_filter([
            $dep['bookings'] ? $dep['bookings'] . ' booking' . ($dep['bookings'] === 1 ? '' : 's') : null,
            $dep['quotations'] ? $dep['quotations'] . ' quotation' . ($dep['quotations'] === 1 ? '' : 's') : null,
            $dep['invoices'] ? $dep['invoices'] . ' invoice' . ($dep['invoices'] === 1 ? '' : 's') : null,
        ]);
        if ($blocking !== []) {
            Flash::error(sprintf(
                '%s can’t be deleted because they have %s. Those records hold trip, payment and tax history that must stay linked to a customer. You can edit their details instead.',
                $c['name'],
                implode(' and ', $blocking),
            ));
            $this->redirect('/customers/' . $c['id']);
        }
        Customers::delete((int) $c['id']);
        Activity::log('deleted', 'customer', (int) $c['id'], sprintf('Deleted customer “%s”', $c['name']));
        Flash::success('Customer “' . $c['name'] . '” deleted. Their enquiries are kept.');
        $this->redirect('/customers');
    }

    /** POST /customers/{id}/activities — add a note / call / WhatsApp log to the timeline. */
    public function activity(string $id): never
    {
        $c = $this->findOr404($id);
        $v = Validator::make($this->request->all(), [
            'type' => 'required|in:' . implode(',', LeadActivityRepository::MANUAL_TYPES),
            'body' => 'required|string|max:2000',
        ], ['type' => 'Activity type', 'body' => 'What happened']);
        if ($v->fails()) {
            $this->back('/customers/' . $c['id'] . '#timeline', $v->errors());
        }
        $d = $v->validated();
        LeadActivityRepository::log(null, (int) $c['id'], $d['type'], $d['body']);
        Activity::log('updated', 'customer', (int) $c['id'], sprintf('Logged %s for “%s”', strtolower(LeadActivityRepository::label($d['type'])), $c['name']));
        Flash::success(LeadActivityRepository::label($d['type']) . ' added to the timeline.');
        $this->redirect('/customers/' . $c['id'] . '#timeline');
    }

    /** CSV of the filtered list (UTF-8 BOM so Excel shows ₹ and names correctly). */
    public function export(): never
    {
        $rows = Customers::forExport($this->filters());
        Activity::log('exported', 'customer', null, sprintf('Downloaded %d customer%s as CSV', count($rows), count($rows) === 1 ? '' : 's'));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        $this->csvRow($out, ['ID', 'Name', 'Phone', 'Email', 'City', 'Address', 'Birthday', 'Anniversary', 'Source', 'Tags',
            'Trips', 'Total spent', 'Last activity', 'Added on', 'Notes']);
        foreach ($rows as $r) {
            $this->csvRow($out, [
                $r['id'], $r['name'], $r['phone'], $r['email'], $r['city'], $r['address'],
                $r['date_of_birth'], $r['anniversary'], Customers::sourceLabel($r['source']), $r['tags'],
                $r['trips'], round((float) $r['total_spent'], 2), $r['last_activity_at'], $r['created_at'], $r['notes'],
            ]);
        }
        fclose($out);
        exit;
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** @return array{q:string,tag:string,source:string} */
    private function filters(): array
    {
        return Customers::filtersFrom($this->request->query('q', ''), $this->request->query('tag', ''), $this->request->query('source', ''));
    }

    /** @return array<string,mixed> */
    private function validated(string $backPath, ?int $exceptId): array
    {
        $input = $this->request->all();
        $v = Validator::make($input, [
            'name' => 'required|string|max:120',
            'phone' => 'required|phone|max:30',
            'email' => 'nullable|email|max:190',
            'city' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|string|max:10',
            'anniversary' => 'nullable|string|max:10',
            'source' => 'nullable|in:' . implode(',', array_keys(config('crm')['sources'] ?? [])),
            'tags' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ], ['date_of_birth' => 'Birthday', 'phone' => 'Phone number']);
        $errors = $v->errors();
        $data = $v->validated();

        foreach (['date_of_birth' => 'Birthday', 'anniversary' => 'Anniversary'] as $k => $label) {
            if (!isset($errors[$k]) && ($data[$k] ?? null) !== null) {
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data[$k]);
                if (!$d || $d->format('Y-m-d') !== $data[$k] || $d > new \DateTimeImmutable('today')) {
                    $errors[$k] = $label . ' must be a valid past date.';
                }
            }
        }

        $duplicate = null;
        if (!isset($errors['phone'])) {
            $digits = Customers::normalizePhone((string) $data['phone']);
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                $errors['phone'] = 'Please enter a valid phone number (10-digit Indian mobile, or with country code).';
            } elseif ($duplicate = Customers::findByPhone($digits, $exceptId)) {
                $errors['phone'] = 'This number already belongs to ' . $duplicate['name'] . '.';
            }
        }

        if ($errors !== []) {
            // Like $this->back(), plus the id of the existing customer so the form can link to them.
            Flash::withInput($input + ['_duplicate_id' => $duplicate ? (string) $duplicate['id'] : ''], $errors);
            Flash::error($duplicate ? 'A customer with this phone number already exists.' : 'Please fix the highlighted fields.');
            Response::redirect(url($backPath));
        }
        return $data;
    }

    /** @return array<string,mixed> */
    private function findOr404(string $rawId): array
    {
        $c = Customers::find($this->id($rawId));
        if ($c === null) {
            Response::abort(404);
        }
        return $c;
    }

    /** @param resource $out @param list<mixed> $cells */
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
