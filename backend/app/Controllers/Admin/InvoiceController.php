<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Validator;
use App\Repositories\CustomerRepository;
use App\Repositories\InvoiceRepository;

/** GST tax invoices (Premium). */
final class InvoiceController extends Controller
{
    private const MAX_LINES = 30;

    public function index(): never
    {
        $f = [
            'q' => mb_substr((string) $this->request->query('q', ''), 0, 100),
            'status' => (string) $this->request->query('status', ''),
            'month' => (string) $this->request->query('month', ''),
        ];
        if (!isset(InvoiceRepository::STATUSES[$f['status']])) {
            $f['status'] = '';
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $f['month'])) {
            $f['month'] = '';
        }
        $this->view('admin/invoices/index', [
            'title' => 'GST invoices',
            'pretitle' => 'Accounts',
            'filters' => $f,
            'result' => InvoiceRepository::paginate($f, (int) $this->request->query('page', 1)),
            'pageActions' => '<a href="' . e(url('/invoices/create')) . '" class="btn btn-primary">' . icon('plus') . ' New invoice</a>',
        ]);
    }

    public function create(): never
    {
        $bookingId = (int) $this->request->query('booking_id', 0);
        $booking = $bookingId > 0 ? InvoiceRepository::booking($bookingId) : null;
        if ($bookingId > 0 && $booking === null) {
            Flash::error('That booking no longer exists — you can still create an invoice without it.');
            $this->redirect('/invoices/create');
        }
        $customerId = $booking ? (int) $booking['customer_id'] : (int) $this->request->query('customer_id', 0);
        $customer = $customerId > 0 ? CustomerRepository::find($customerId) : null;

        $sac = Settings::get('invoice_hsn_sac');
        $items = [['description' => '', 'hsn_sac' => $sac, 'qty' => '1', 'unit_price' => '']];
        if ($booking) {
            $items[0]['description'] = self::bookingLine($booking);
            $items[0]['unit_price'] = number_format((float) $booking['total_amount'], 2, '.', '');
        }
        $guess = $customer ? InvoiceRepository::guessState(trim(($customer['city'] ?? '') . ' ' . ($customer['address'] ?? ''))) : '';

        $invoice = [
            'id' => null,
            'status' => 'new',
            'booking_id' => $booking ? (int) $booking['id'] : null,
            'customer_id' => $customer ? (int) $customer['id'] : null,
            'issue_date' => date('Y-m-d'),
            'due_date' => '',
            'place_of_supply' => $guess ?: InvoiceRepository::companyState(),
            'tax_percent' => Settings::get('tax_percent_default'),
            'discount' => '0',
            'inclusive' => true,
            'customer_gstin' => '',
            'notes' => '',
            'items' => $items,
        ];
        $this->form($invoice, $booking, 'New GST invoice');
    }

    public function store(): never
    {
        [$data, $calc, $errors] = $this->validated();
        if ($errors !== []) {
            $this->back('/invoices/create' . ($data['booking_id'] ? '?booking_id=' . $data['booking_id'] : ''), $errors);
        }
        $status = $this->request->input('save_as') === 'draft' ? 'draft' : 'issued';
        $data['created_by'] = Auth::id();
        $id = InvoiceRepository::create($data, $calc, $status);
        $inv = InvoiceRepository::find($id);
        $label = $status === 'draft' ? 'draft invoice' : 'invoice ' . ($inv['number'] ?? '');
        Activity::log('created', 'invoice', $id, 'Created ' . $label . ' for ' . ($inv['customer_name'] ?? 'customer') . ' (' . inr2($calc['total']) . ')');
        Flash::success($status === 'draft' ? 'Draft saved. Issue it when it is ready to give it an invoice number.' : 'Invoice ' . ($inv['number'] ?? '') . ' created.');
        $this->redirect('/invoices/' . $id);
    }

    public function show(string $id): never
    {
        $inv = $this->load($id);
        $split = InvoiceRepository::splitNotes($inv['notes'] ?? null);
        $this->view('admin/invoices/show', [
            'title' => self::label($inv),
            'pretitle' => 'GST invoice',
            'inv' => $inv,
            'customerGstin' => $split['gstin'],
            'notes' => $split['notes'],
            'intra' => (float) $inv['igst'] == 0.0,
        ]);
    }

    public function edit(string $id): never
    {
        $inv = $this->load($id);
        if (!in_array($inv['status'], ['draft', 'issued'], true)) {
            Flash::error('Paid and void invoices cannot be changed.');
            $this->redirect('/invoices/' . $inv['id']);
        }
        $split = InvoiceRepository::splitNotes($inv['notes'] ?? null);
        $items = array_map(static fn ($it) => [
            'description' => $it['description'], 'hsn_sac' => (string) $it['hsn_sac'],
            'qty' => rtrim(rtrim((string) $it['qty'], '0'), '.'), 'unit_price' => (string) $it['unit_price'],
        ], $inv['items']);
        $form = [
            ...$inv,
            'tax_percent' => rtrim(rtrim((string) $inv['tax_percent'], '0'), '.'),
            'discount' => (string) $inv['discount'],
            'inclusive' => false,
            'customer_gstin' => $split['gstin'],
            'notes' => $split['notes'],
            'items' => $items ?: [['description' => '', 'hsn_sac' => Settings::get('invoice_hsn_sac'), 'qty' => '1', 'unit_price' => '']],
        ];
        $booking = $inv['booking_id'] ? InvoiceRepository::booking((int) $inv['booking_id']) : null;
        $this->form($form, $booking, 'Edit ' . self::label($inv));
    }

    public function update(string $id): never
    {
        $inv = $this->load($id);
        if (!in_array($inv['status'], ['draft', 'issued'], true)) {
            Flash::error('Paid and void invoices cannot be changed.');
            $this->redirect('/invoices/' . $inv['id']);
        }
        [$data, $calc, $errors] = $this->validated();
        if ($errors !== []) {
            $this->back('/invoices/' . $inv['id'] . '/edit', $errors);
        }
        $issue = $inv['status'] === 'draft' && $this->request->input('save_as') === 'issued' ? 'issued' : null;
        InvoiceRepository::update((int) $inv['id'], $data, $calc, $issue);
        $after = InvoiceRepository::find((int) $inv['id']);
        Activity::log('updated', 'invoice', (int) $inv['id'], ($issue ? 'Issued ' : 'Updated ') . lcfirst(self::label($after ?? $inv)) . ' (' . inr2($calc['total']) . ')');
        Flash::success($issue ? 'Invoice ' . ($after['number'] ?? '') . ' issued.' : 'Invoice saved.');
        $this->redirect('/invoices/' . $inv['id']);
    }

    public function print(string $id): never
    {
        $inv = $this->load($id);
        $split = InvoiceRepository::splitNotes($inv['notes'] ?? null);
        $biz = [];
        foreach (array_keys(config('business')) as $k) {
            $biz[$k] = Settings::get($k);
        }
        $this->view('admin/invoices/print', [
            'title' => self::label($inv) . ' — ' . ($inv['customer_name'] ?? ''),
            'backUrl' => '/invoices/' . $inv['id'],
            'inv' => $inv,
            'biz' => $biz,
            'customerGstin' => $split['gstin'],
            'notes' => $split['notes'],
            'intra' => (float) $inv['igst'] == 0.0,
        ], 'layouts/print');
    }

    public function issue(string $id): never
    {
        $inv = $this->load($id);
        if ($inv['status'] !== 'draft') {
            Flash::error('Only drafts can be issued.');
            $this->redirect('/invoices/' . $inv['id']);
        }
        if ($inv['items'] === []) {
            Flash::error('Add at least one line before issuing this invoice.');
            $this->redirect('/invoices/' . $inv['id'] . '/edit');
        }
        $no = InvoiceRepository::issue((int) $inv['id']);
        Activity::log('updated', 'invoice', (int) $inv['id'], 'Issued invoice ' . $no . ' to ' . $inv['customer_name']);
        Flash::success('Invoice ' . $no . ' issued.');
        $this->redirect('/invoices/' . $inv['id']);
    }

    public function markPaid(string $id): never
    {
        $inv = $this->load($id);
        if ($inv['status'] !== 'issued') {
            Flash::error('Only issued invoices can be marked as paid.');
            $this->redirect('/invoices/' . $inv['id']);
        }
        InvoiceRepository::setStatus((int) $inv['id'], 'paid');
        Activity::log('updated', 'invoice', (int) $inv['id'], 'Marked invoice ' . $inv['number'] . ' as paid');
        Flash::success('Invoice ' . $inv['number'] . ' marked as paid.');
        $this->redirect('/invoices/' . $inv['id']);
    }

    public function void(string $id): never
    {
        $inv = $this->load($id);
        if (!in_array($inv['status'], ['issued', 'paid'], true)) {
            Flash::error($inv['status'] === 'draft' ? 'Drafts can simply be deleted.' : 'This invoice is already void.');
            $this->redirect('/invoices/' . $inv['id']);
        }
        InvoiceRepository::setStatus((int) $inv['id'], 'void');
        Activity::log('updated', 'invoice', (int) $inv['id'], 'Voided invoice ' . $inv['number'] . ' (' . inr2($inv['total']) . ')');
        Flash::success('Invoice ' . $inv['number'] . ' is now void. It stays in your records but no longer counts in totals or GST.');
        $this->redirect('/invoices/' . $inv['id']);
    }

    public function destroy(string $id): never
    {
        $inv = $this->load($id);
        if ($inv['status'] !== 'draft') {
            Flash::error('Issued invoices cannot be deleted (GST rules) — void it instead.');
            $this->redirect('/invoices/' . $inv['id']);
        }
        InvoiceRepository::delete((int) $inv['id']);
        Activity::log('deleted', 'invoice', (int) $inv['id'], 'Deleted draft invoice for ' . $inv['customer_name']);
        Flash::success('Draft deleted.');
        $this->redirect('/invoices');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function load(string $raw): array
    {
        $inv = InvoiceRepository::find($this->id($raw));
        if ($inv === null) {
            Response::abort(404);
        }
        return $inv;
    }

    /** @param array<string,mixed> $invoice @param array<string,mixed>|null $booking */
    private function form(array $invoice, ?array $booking, string $title): never
    {
        $this->view('admin/invoices/form', [
            'title' => $title,
            'pretitle' => 'GST invoices',
            'invoice' => $invoice,
            'booking' => $booking,
            'customers' => InvoiceRepository::customerOptions(),
            'bookings' => $booking ? [] : InvoiceRepository::bookingOptions(),
            'states' => InvoiceRepository::STATES,
            'companyState' => InvoiceRepository::companyState(),
            'sac' => Settings::get('invoice_hsn_sac'),
        ]);
    }

    /** "Tour package — Kashmir Paradise (12 Dec – 17 Dec 2026, 2 adults, 1 child)" @param array<string,mixed> $b */
    public static function bookingLine(array $b): string
    {
        $s = strtotime((string) $b['travel_start']);
        $e = strtotime((string) $b['travel_end']);
        $dates = $s && $e
            ? (date('Y', $s) === date('Y', $e) ? date('j M', $s) : date('j M Y', $s)) . ' – ' . date('j M Y', $e)
            : nice_day((string) $b['travel_start']);
        $a = (int) $b['adults'];
        $c = (int) $b['children'];
        $pax = $a . ' adult' . ($a === 1 ? '' : 's') . ($c > 0 ? ', ' . $c . ' child' . ($c === 1 ? '' : 'ren') : '');
        return mb_substr('Tour package — ' . $b['title'] . ' (' . $dates . ', ' . $pax . ')', 0, 300);
    }

    /** @param array<string,mixed> $inv */
    private static function label(array $inv): string
    {
        return ($inv['status'] ?? '') === 'draft' ? 'Draft invoice #' . $inv['id'] : 'Invoice ' . $inv['number'];
    }

    private static function validDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('!Y-m-d', $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
    }

    /**
     * Validate the invoice form and compute money.
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,string>}
     */
    private function validated(): array
    {
        $in = $this->request->all();
        if (isset($in['customer_gstin']) && is_string($in['customer_gstin'])) {
            $in['customer_gstin'] = strtoupper((string) preg_replace('/\s+/', '', $in['customer_gstin']));
        }
        $v = Validator::make($in, [
            'customer_id' => 'nullable|int|min:1',
            'booking_id' => 'nullable|int|min:1',
            'new_customer_name' => 'nullable|string|max:120',
            'new_customer_phone' => 'nullable|phone|max:30',
            'issue_date' => 'required|string|max:10',
            'due_date' => 'nullable|string|max:10',
            'place_of_supply' => 'required|string|max:80',
            'tax_percent' => 'required|numeric|min:0|max:28',
            'discount' => 'nullable|numeric|min:0|max:999999999',
            'customer_gstin' => 'nullable|string|max:15',
            'notes' => 'nullable|string|max:2000',
        ], [
            'customer_id' => 'Customer', 'booking_id' => 'Booking', 'new_customer_name' => 'Customer name',
            'new_customer_phone' => 'Customer phone', 'issue_date' => 'Invoice date', 'due_date' => 'Due date',
            'place_of_supply' => 'Place of supply', 'tax_percent' => 'GST %', 'customer_gstin' => 'Customer GSTIN',
        ]);
        $errors = $v->errors();
        $c = $v->validated();

        // Booking (optional) decides the customer.
        $bookingId = $c['booking_id'] ?? null;
        $booking = null;
        if ($bookingId) {
            $booking = InvoiceRepository::booking((int) $bookingId);
            if ($booking === null) {
                $errors['booking_id'] = 'That booking no longer exists.';
                $bookingId = null;
            }
        }
        $customerId = $booking ? (int) $booking['customer_id'] : ($c['customer_id'] ?? null);
        if ($customerId && !$booking && CustomerRepository::find((int) $customerId) === null) {
            $errors['customer_id'] = 'Please choose a customer from the list.';
            $customerId = null;
        }
        $newName = trim((string) ($c['new_customer_name'] ?? ''));
        $newPhone = trim((string) ($c['new_customer_phone'] ?? ''));
        if (!$customerId && !isset($errors['customer_id'])) {
            if ($newName === '' && $newPhone === '') {
                $errors['customer_id'] = 'Choose a customer, or type a new customer\'s name and phone.';
            } elseif ($newName === '') {
                $errors['new_customer_name'] = 'Customer name is required.';
            } elseif ($newPhone === '' && !isset($errors['new_customer_phone'])) {
                $errors['new_customer_phone'] = 'Customer phone is required.';
            }
        }

        foreach (['issue_date', 'due_date'] as $k) {
            if (!isset($errors[$k]) && ($c[$k] ?? null) !== null && !self::validDate((string) $c[$k])) {
                $errors[$k] = 'Please pick a valid date.';
            }
        }
        if (!isset($errors['due_date']) && !isset($errors['issue_date']) && ($c['due_date'] ?? null) && ($c['due_date'] < ($c['issue_date'] ?? ''))) {
            $errors['due_date'] = 'Due date cannot be before the invoice date.';
        }
        $pos = InvoiceRepository::canonicalState((string) ($c['place_of_supply'] ?? ''));
        if (!isset($errors['place_of_supply']) && $pos === '') {
            $errors['place_of_supply'] = 'Please choose the place of supply from the list.';
        }
        $gstin = (string) ($c['customer_gstin'] ?? '');
        if (!isset($errors['customer_gstin']) && $gstin !== '' && !InvoiceRepository::validGstin($gstin)) {
            $errors['customer_gstin'] = 'This GSTIN is not valid — it has 15 characters, e.g. 27ABCDE1234F1Z5.';
        }

        // Line items.
        $lines = [];
        $raw = $in['items'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        foreach (array_slice($raw, 0, self::MAX_LINES * 2, true) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $desc = trim((string) ($row['description'] ?? ''));
            $qty = trim((string) ($row['qty'] ?? ''));
            $rate = trim(str_replace(',', '', (string) ($row['unit_price'] ?? '')));
            $sac = trim((string) ($row['hsn_sac'] ?? ''));
            if ($desc === '' && ($rate === '' || (float) $rate == 0.0)) {
                continue; // blank row
            }
            $n = count($lines) + 1;
            if ($desc === '') {
                $errors['items'] = "Line {$n}: please describe the service.";
            } elseif (mb_strlen($desc) > 300) {
                $errors['items'] = "Line {$n}: description is too long (300 characters max).";
            } elseif (!is_numeric($qty) || (float) $qty <= 0 || (float) $qty > 9999) {
                $errors['items'] = "Line {$n}: quantity must be a number above 0.";
            } elseif (!is_numeric($rate) || (float) $rate < 0 || (float) $rate > 99999999) {
                $errors['items'] = "Line {$n}: rate must be a number (₹).";
            } elseif ($sac !== '' && !preg_match('/^[0-9]{4,8}$/', $sac)) {
                $errors['items'] = "Line {$n}: SAC code must be 4–8 digits.";
            }
            $lines[] = ['description' => $desc, 'hsn_sac' => $sac, 'qty' => round((float) $qty, 2), 'unit_price' => round((float) $rate, 2)];
            if (count($lines) > self::MAX_LINES) {
                $errors['items'] = 'An invoice can have up to ' . self::MAX_LINES . ' lines.';
                break;
            }
        }
        if ($lines === [] && !isset($errors['items'])) {
            $errors['items'] = 'Add at least one line (description and amount).';
        }

        $taxPercent = round((float) ($c['tax_percent'] ?? 0), 2);
        $inclusive = in_array($in['amounts_include_gst'] ?? null, ['1', 'on'], true);
        $calc = InvoiceRepository::compute($lines, (float) ($c['discount'] ?? 0), $taxPercent, $inclusive, InvoiceRepository::isIntraState($pos));
        if (!isset($errors['discount']) && (float) ($c['discount'] ?? 0) > 0 && round((float) $c['discount'], 2) > round(array_sum(array_map(static fn ($l) => round($l['qty'] * $l['unit_price'], 2), $lines)), 2)) {
            $errors['discount'] = 'Discount cannot be more than the total of the lines.';
        }
        if ($errors === [] && $calc['total'] <= 0) {
            $errors['items'] = 'The invoice total must be more than ₹0.';
        }

        if ($errors === [] && !$customerId) {
            $customerId = CustomerRepository::findOrCreate(['name' => $newName, 'phone' => $newPhone, 'source' => 'walk-in']);
        }

        $data = [
            'booking_id' => $bookingId ? (int) $bookingId : null,
            'customer_id' => $customerId ? (int) $customerId : null,
            'issue_date' => $c['issue_date'] ?? date('Y-m-d'),
            'due_date' => $c['due_date'] ?? null,
            'place_of_supply' => $pos,
            'tax_percent' => number_format($taxPercent, 2, '.', ''),
            'notes' => InvoiceRepository::joinNotes($gstin, str_replace("\r\n", "\n", trim((string) ($c['notes'] ?? '')))),
        ];
        return [$data, $calc, $errors];
    }
}
