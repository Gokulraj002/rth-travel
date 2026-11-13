<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\BookingRepository as Bookings;
use App\Repositories\CustomerRepository;
use App\Repositories\PaymentRepository;

/** Confirmed trips: list, create/edit with travellers, detail page, voucher, CSV. */
final class BookingController extends Controller
{
    public function index(): never
    {
        $filters = $this->filters();
        $page = filter_var($this->request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $result = Bookings::paginate($filters, $page, 20);
        $exportUrl = url('/bookings/export') . '?' . http_build_query(array_filter($filters, static fn ($v) => $v !== ''));

        $actions = '<a href="' . e($exportUrl) . '" class="btn" title="Download the bookings shown below as a spreadsheet (CSV)">' . icon('download') . ' Download CSV</a>';
        $actions .= '<a href="' . e(url('/bookings/create')) . '" class="btn btn-primary">' . icon('plus') . ' New booking</a>';

        $this->view('admin/bookings/index', [
            'title' => 'Bookings',
            'pretitle' => 'Confirmed trips',
            'filters' => $filters,
            'result' => $result,
            'tabCounts' => Bookings::tabCounts($filters),
            'pageActions' => $actions,
        ]);
    }

    public function create(): never
    {
        $booking = [
            'customer_id' => null, 'package_id' => null, 'quotation_id' => null, 'enquiry_id' => null, 'title' => '',
            'travel_start' => '', 'travel_end' => '', 'adults' => 2, 'children' => 0, 'pickup_point' => '', 'drop_point' => '',
            'total_amount' => '', 'status' => 'confirmed', 'special_requests' => '', 'notes' => '', 'assigned_to' => null,
        ];
        $customerId = filter_var($this->request->query('customer_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($customerId && CustomerRepository::find($customerId)) {
            $booking['customer_id'] = $customerId;
        }
        $enquiryId = filter_var($this->request->query('enquiry_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($enquiryId && Database::value('SELECT id FROM enquiries WHERE id = ?', [$enquiryId])) {
            $booking['enquiry_id'] = $enquiryId;
        }
        // Prefill from an accepted quotation (?quotation_id=).
        $quotationId = filter_var($this->request->query('quotation_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($quotationId) {
            $q = Database::fetch('SELECT * FROM quotations WHERE id = ?', [$quotationId]);
            if ($q) {
                $booking = [...$booking,
                    'quotation_id' => (int) $q['id'], 'customer_id' => (int) $q['customer_id'], 'package_id' => $q['package_id'],
                    'enquiry_id' => $q['enquiry_id'] ?? $booking['enquiry_id'], 'title' => (string) $q['title'],
                    'travel_start' => (string) ($q['travel_start'] ?? ''), 'travel_end' => (string) ($q['travel_end'] ?? ''),
                    'adults' => (int) $q['adults'], 'children' => (int) $q['children'], 'total_amount' => (string) $q['total'],
                ];
            }
        }
        $this->form($booking, []);
    }

    public function store(): never
    {
        [$data, $travellers] = $this->validated('/bookings/create');
        $id = Bookings::create($data, $travellers);
        $booking = Bookings::find($id);
        Activity::log('created', 'booking', $id, sprintf('Created booking %s for “%s” (%s)', $booking['number'], $booking['customer_name'], $booking['title']));
        Flash::success('Booking ' . $booking['number'] . ' created.');
        $this->redirect('/bookings/' . $id);
    }

    public function show(string $id): never
    {
        $booking = $this->findOr404($id);
        $bid = (int) $booking['id'];
        $canPay = feature('payments') && can('payments');
        $payments = $canPay ? PaymentRepository::forBooking($bid) : [];

        // Running balance after each payment (oldest first).
        $running = 0.0;
        foreach ($payments as &$p) {
            $running += (float) $p['amount'];
            $p['balance_after'] = (float) $booking['total_amount'] - $running;
        }
        unset($p);

        $this->view('admin/bookings/show', [
            'title' => $booking['number'] . ' · ' . $booking['title'],
            'pageTitle' => $booking['title'],
            'pretitle' => 'Booking ' . $booking['number'],
            'booking' => $booking,
            'travellers' => Bookings::travellers($bid),
            'payments' => $payments,
            'canPay' => $canPay,
            'paymentCount' => Bookings::paymentCount($bid),
            'pageActions' => $this->showActions($booking),
        ]);
    }

    public function edit(string $id): never
    {
        $booking = $this->findOr404($id);
        $this->form($booking, Bookings::travellers((int) $booking['id']));
    }

    public function update(string $id): never
    {
        $booking = $this->findOr404($id);
        [$data, $travellers] = $this->validated('/bookings/' . $booking['id'] . '/edit', $booking);
        Bookings::update((int) $booking['id'], $data, $travellers);
        Activity::log('updated', 'booking', (int) $booking['id'], sprintf('Updated booking %s (%s)', $booking['number'], $data['title']));
        Flash::success('Booking ' . $booking['number'] . ' saved.');
        $this->redirect('/bookings/' . $booking['id']);
    }

    /** Quick status change from the detail page (also used by "Cancel booking"). */
    public function status(string $id): never
    {
        $booking = $this->findOr404($id);
        $status = $this->request->input('status');
        if (!is_string($status) || !isset(Bookings::STATUSES[$status])) {
            Flash::error('Please choose a valid status.');
            $this->redirect('/bookings/' . $booking['id']);
        }
        if ($status !== $booking['status']) {
            Bookings::setStatus((int) $booking['id'], $status);
            Activity::log('updated', 'booking', (int) $booking['id'], sprintf('Marked booking %s as %s', $booking['number'], Bookings::statusLabel($status)));
            Flash::success($status === 'cancelled'
                ? 'Booking ' . $booking['number'] . ' cancelled. Payments already received are kept on record.'
                : 'Booking marked as ' . Bookings::statusLabel($status) . '.');
        }
        $this->redirect('/bookings/' . $booking['id']);
    }

    public function destroy(string $id): never
    {
        $booking = $this->findOr404($id);
        $bid = (int) $booking['id'];
        if (Bookings::paymentCount($bid) > 0) {
            Flash::error('This booking has payments recorded, so it can’t be deleted. Cancel the booking instead (payments stay on record).');
            $this->redirect('/bookings/' . $bid);
        }
        if (Bookings::invoiceCount($bid) > 0) {
            Flash::error('This booking has a GST invoice. Void the invoice first, or cancel the booking instead.');
            $this->redirect('/bookings/' . $bid);
        }
        Bookings::delete($bid);
        Activity::log('deleted', 'booking', $bid, sprintf('Deleted booking %s (%s, %s)', $booking['number'], $booking['customer_name'], $booking['title']));
        Flash::success('Booking ' . $booking['number'] . ' deleted.');
        $this->redirect('/bookings');
    }

    /** Traveller-facing confirmation voucher (A4 print). */
    public function voucher(string $id): never
    {
        $booking = $this->findOr404($id);
        $toolbar = '';
        if (!empty($booking['customer_phone'])) {
            $text = 'Hi ' . $booking['customer_name'] . ', here are your trip details for booking ' . $booking['number']
                . ' (' . $booking['title'] . ', ' . Bookings::dateRange((string) $booking['travel_start'], (string) $booking['travel_end']) . '). I am sharing the voucher PDF with you now.';
            $toolbar = '<a class="btn btn-success" href="' . e(wa_link((string) $booking['customer_phone'], $text)) . '" target="_blank" rel="noopener">'
                . icon('whatsapp') . ' Open WhatsApp</a>';
        }
        $this->view('admin/bookings/voucher', [
            'title' => 'Voucher ' . $booking['number'] . ' — ' . $booking['customer_name'],
            'backUrl' => '/bookings/' . $booking['id'],
            'toolbar' => $toolbar,
            'booking' => $booking,
            'travellers' => Bookings::travellers((int) $booking['id']),
            'itinerary' => Bookings::itinerary($booking),
            'lists' => Bookings::inclusions($booking),
        ], 'layouts/print');
    }

    /** CSV of the current filtered list (UTF-8 BOM for Excel). */
    public function export(): never
    {
        $filters = $this->filters();
        $rows = Bookings::forExport($filters);
        Activity::log('exported', 'booking', null, sprintf('Downloaded %d booking%s as CSV', count($rows), count($rows) === 1 ? '' : 's'));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bookings-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        self::csvRow($out, ['Booking no.', 'Status', 'Customer', 'Phone', 'Trip', 'Travel start', 'Travel end', 'Nights', 'Adults', 'Children',
            'Pickup', 'Drop', 'Total (INR)', 'Paid (INR)', 'Balance (INR)', 'Assigned to', 'Created']);
        foreach ($rows as $r) {
            self::csvRow($out, [
                $r['number'], Bookings::statusLabel((string) $r['status']), $r['customer_name'], $r['customer_phone'], $r['title'],
                $r['travel_start'], $r['travel_end'], Bookings::nights($r), $r['adults'], $r['children'], $r['pickup_point'], $r['drop_point'],
                number_format((float) $r['total_amount'], 2, '.', ''), number_format((float) $r['paid'], 2, '.', ''),
                number_format((float) $r['balance'], 2, '.', ''), $r['assigned_name'], $r['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $booking @param list<array<string,mixed>> $travellers */
    private function form(array $booking, array $travellers): never
    {
        $isEdit = !empty($booking['id']);
        $customer = !empty($booking['customer_id']) ? CustomerRepository::find((int) $booking['customer_id']) : null;
        $quotation = !empty($booking['quotation_id']) ? Database::fetch('SELECT id, number FROM quotations WHERE id = ?', [(int) $booking['quotation_id']]) : null;
        $this->view('admin/bookings/form', [
            'title' => $isEdit ? 'Edit booking ' . $booking['number'] : 'New booking',
            'pretitle' => $isEdit ? 'Booking ' . $booking['number'] : 'Bookings',
            'booking' => $booking,
            'travellers' => $travellers,
            'customer' => $customer,
            'quotation' => $quotation,
            'customers' => CustomerRepository::options(2000),
            'packages' => Bookings::packageOptions(),
            'staff' => Bookings::staffOptions(),
            'sources' => (array) (config('crm')['sources'] ?? []),
            'isEdit' => $isEdit,
        ]);
    }

    /**
     * Validate the booking form; on failure redirect back with errors.
     * @param array<string,mixed>|null $existing
     * @return array{0:array<string,mixed>,1:list<array<string,mixed>>}
     */
    private function validated(string $backTo, ?array $existing = null): array
    {
        $in = $this->request->all();
        $v = Validator::make($in, [
            'customer_id' => 'nullable|int|min:1',
            'new_customer_name' => 'nullable|string|max:120',
            'new_customer_phone' => 'nullable|phone',
            'new_customer_source' => 'nullable|string|max:40',
            'package_id' => 'nullable|int|min:1',
            'title' => 'required|string|max:200',
            'travel_start' => 'required|string',
            'travel_end' => 'required|string',
            'adults' => 'required|int|min:1|max:99',
            'children' => 'nullable|int|min:0|max:99',
            'pickup_point' => 'nullable|string|max:160',
            'drop_point' => 'nullable|string|max:160',
            'total_amount' => 'required|numeric|min:0|max:99999999',
            'status' => 'required|in:' . implode(',', array_keys(Bookings::STATUSES)),
            'special_requests' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'assigned_to' => 'nullable|int|min:1',
        ], [
            'title' => 'Trip title', 'travel_start' => 'Travel start date', 'travel_end' => 'Travel end date',
            'total_amount' => 'Total amount', 'new_customer_phone' => 'Phone', 'new_customer_name' => 'Customer name',
        ]);
        $errors = $v->errors();
        $d = $v->validated();

        // Dates
        if (!isset($errors['travel_start']) && !Bookings::validDate($d['travel_start'] ?? null)) {
            $errors['travel_start'] = 'Please pick a valid start date.';
        }
        if (!isset($errors['travel_end']) && !Bookings::validDate($d['travel_end'] ?? null)) {
            $errors['travel_end'] = 'Please pick a valid end date.';
        }
        if (isset($d['travel_start'], $d['travel_end'])
            && Bookings::validDate($d['travel_start']) && Bookings::validDate($d['travel_end']) && $d['travel_end'] < $d['travel_start']) {
            $errors['travel_end'] = 'The trip can’t end before it starts.';
        }

        // Customer: existing or quick-add (name + phone → findOrCreate)
        $customerId = null;
        if (!empty($d['customer_id'])) {
            if (CustomerRepository::find((int) $d['customer_id']) === null) {
                $errors['customer_id'] = 'That customer no longer exists — pick another or add a new one.';
            } else {
                $customerId = (int) $d['customer_id'];
            }
        } elseif (!isset($errors['new_customer_phone']) && !isset($errors['new_customer_name'])) {
            if (empty($d['new_customer_name']) || empty($d['new_customer_phone'])) {
                $errors['customer_id'] = 'Pick a customer, or type the name and phone of a new customer.';
                if (empty($d['new_customer_name']) && !empty($d['new_customer_phone'])) {
                    $errors['new_customer_name'] = 'Customer name is required.';
                }
                if (empty($d['new_customer_phone']) && !empty($d['new_customer_name'])) {
                    $errors['new_customer_phone'] = 'Phone is required.';
                }
            } elseif (strlen(CustomerRepository::normalizePhone((string) $d['new_customer_phone'])) < 7) {
                $errors['new_customer_phone'] = 'Phone must be a valid phone number.';
            }
        }

        // Optional links
        if (!empty($d['package_id']) && !Database::value('SELECT id FROM packages WHERE id = ?', [(int) $d['package_id']])) {
            $errors['package_id'] = 'That package no longer exists.';
        }
        if (!empty($d['assigned_to']) && !Database::value('SELECT id FROM admins WHERE id = ? AND is_active = 1', [(int) $d['assigned_to']])) {
            $errors['assigned_to'] = 'Pick an active team member.';
        }

        // Travellers repeater
        [$travellers, $travellerError, $masked] = $this->travellersInput();
        if ($travellerError !== null) {
            $errors['travellers'] = $travellerError;
        }

        if ($errors !== []) {
            $this->back($backTo, $errors);
        }

        if ($customerId === null) {
            $source = (string) ($d['new_customer_source'] ?? '');
            $sources = (array) (config('crm')['sources'] ?? []);
            $customerId = CustomerRepository::findOrCreate([
                'name' => (string) $d['new_customer_name'],
                'phone' => (string) $d['new_customer_phone'],
                'source' => isset($sources[$source]) ? $source : 'phone',
            ]);
        }

        $data = [
            'customer_id' => $customerId,
            'package_id' => !empty($d['package_id']) ? (int) $d['package_id'] : null,
            'title' => (string) $d['title'],
            'travel_start' => (string) $d['travel_start'],
            'travel_end' => (string) $d['travel_end'],
            'adults' => (int) $d['adults'],
            'children' => (int) ($d['children'] ?? 0),
            'pickup_point' => $d['pickup_point'] ?? null,
            'drop_point' => $d['drop_point'] ?? null,
            'total_amount' => round((float) $d['total_amount'], 2),
            'status' => (string) $d['status'],
            'special_requests' => $d['special_requests'] ?? null,
            'notes' => $d['notes'] ?? null,
            'assigned_to' => !empty($d['assigned_to']) ? (int) $d['assigned_to'] : null,
        ];
        if ($existing === null) {
            // Links to the source quotation / enquiry are set once, on create.
            foreach (['quotation_id' => 'quotations', 'enquiry_id' => 'enquiries'] as $col => $table) {
                $val = filter_var($in[$col] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $data[$col] = ($val && Database::value("SELECT id FROM {$table} WHERE id = ?", [$val])) ? $val : null;
            }
        }
        if ($masked) {
            Flash::info('For privacy, ID numbers were shortened to the last 4 characters (e.g. Aadhaar ••••1234).');
        }
        return [$data, $travellers];
    }

    /**
     * Read travellers[…][name|age|gender|phone|id_proof]. Blank rows are skipped.
     * @return array{0:list<array<string,mixed>>,1:?string,2:bool}
     */
    private function travellersInput(): array
    {
        $raw = $this->request->input('travellers', []);
        if (!is_array($raw)) {
            return [[], null, false];
        }
        $out = [];
        $masked = false;
        $n = 0;
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $n++;
            $get = static fn (string $k): string => is_string($row[$k] ?? null) ? trim($row[$k]) : '';
            $name = $get('name');
            $age = $get('age');
            $gender = $get('gender');
            $phone = $get('phone');
            $idProof = $get('id_proof');
            if ($name === '' && $age === '' && $phone === '' && $idProof === '') {
                continue; // empty row
            }
            if ($name === '') {
                return [[], "Traveller {$n}: please enter a name (or remove the row).", false];
            }
            if ($age !== '' && (filter_var($age, FILTER_VALIDATE_INT) === false || (int) $age < 0 || (int) $age > 120)) {
                return [[], "Traveller {$n}: age must be a number between 0 and 120.", false];
            }
            if ($gender !== '' && !isset(Bookings::GENDERS[$gender])) {
                return [[], "Traveller {$n}: please choose a valid gender.", false];
            }
            if ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{7,20}$/', $phone)) {
                return [[], "Traveller {$n}: phone must be a valid phone number.", false];
            }
            $safeId = self::maskId($idProof);
            if ($safeId !== $idProof) {
                $masked = true;
            }
            $out[] = [
                'name' => mb_substr($name, 0, 120),
                'age' => $age === '' ? null : (int) $age,
                'gender' => $gender === '' ? null : $gender,
                'phone' => $phone === '' ? null : mb_substr($phone, 0, 30),
                'id_proof' => $safeId === '' ? null : mb_substr($safeId, 0, 80),
            ];
            if (count($out) >= 60) {
                break;
            }
        }
        return [$out, null, $masked];
    }

    /** Keep only the last 4 characters of anything that looks like a full ID number (Aadhaar, passport, PAN …). */
    private static function maskId(string $s): string
    {
        return (string) preg_replace_callback('/[A-Za-z]{0,5}[0-9][0-9 \-]{3,}[0-9A-Za-z]/', static function (array $m): string {
            $alnum = (string) preg_replace('/[^0-9A-Za-z]/', '', $m[0]);
            $digits = (string) preg_replace('/\D/', '', $alnum);
            return (strlen($alnum) >= 6 && strlen($digits) >= 4) ? '••••' . substr($alnum, -4) : $m[0];
        }, $s);
    }

    /** Header buttons on the booking detail page. @param array<string,mixed> $b */
    private function showActions(array $b): string
    {
        $vars = Bookings::messageVars($b);
        $confirm = \App\Core\Template::message('Booking confirmed', $vars,
            'Hi {{customer_name}}, your booking {{booking_number}} for {{trip}} from {{travel_start}} is CONFIRMED. Total {{amount}}, paid {{paid}}, balance {{balance}}. — {{company_name}}');
        $reminder = \App\Core\Template::message('Trip reminder', $vars,
            'Hi {{customer_name}}, your {{trip}} starts on {{travel_start}}. Carry warm clothes and ID proof. — {{company_name}}');
        $phone = (string) $b['customer_phone'];

        $h = '<a href="' . e(url('/bookings/' . $b['id'] . '/edit')) . '" class="btn">' . icon('edit') . ' Edit</a>';
        $h .= '<a href="' . e(url('/bookings/' . $b['id'] . '/voucher')) . '" class="btn">' . icon('printer') . ' Voucher</a>';
        if ($b['status'] !== 'cancelled') {
            $h .= '<a href="' . e(wa_link($phone, $confirm)) . '" class="btn btn-success" target="_blank" rel="noopener" title="Opens WhatsApp with the booking confirmation message ready to send">'
                . icon('whatsapp') . ' Send confirmation</a>';
            $h .= '<a href="' . e(wa_link($phone, $reminder)) . '" class="btn" target="_blank" rel="noopener" title="Opens WhatsApp with a trip reminder ready to send">'
                . icon('bell') . ' Trip reminder</a>';
        }
        return $h;
    }

    /** @return array{q:string,status:string,month:string,due:string,tab:string} */
    private function filters(): array
    {
        return Bookings::filtersFrom(
            $this->request->query('q', ''),
            $this->request->query('status', ''),
            $this->request->query('month', ''),
            $this->request->query('due', ''),
            $this->request->query('tab'),
        );
    }

    /** @return array<string,mixed> */
    private function findOr404(string $rawId): array
    {
        $booking = Bookings::find($this->id($rawId));
        if ($booking === null) {
            Response::abort(404);
        }
        return $booking;
    }

    /**
     * One CSV line, neutralising spreadsheet formula injection.
     * @param resource $out @param list<mixed> $cells
     */
    private static function csvRow($out, array $cells): void
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
