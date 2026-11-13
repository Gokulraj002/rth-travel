<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Env;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\Response;
use App\Core\Template;
use App\Core\Validator;
use App\Repositories\CustomerRepository;
use App\Repositories\PackageRepository;
use App\Repositories\QuotationRepository as Quotes;

/** Quotations: fast builder, share on WhatsApp / email / link, print, convert to booking. */
final class QuotationController extends Controller
{
    private const PER_PAGE = 20;
    private const MAX_ITEMS = 100;
    private const MAX_DAYS = 60;

    // ── List ──────────────────────────────────────────────────────────────────
    public function index(): never
    {
        $status = (string) $this->request->query('status', '');
        $filters = [
            'q' => mb_substr((string) $this->request->query('q', ''), 0, 100),
            'status' => isset(Quotes::STATUSES[$status]) || $status === 'open' ? $status : '',
        ];
        $page = max(1, (int) $this->request->query('page', 1));

        $this->view('admin/quotations/index', [
            'title' => 'Quotations',
            'pretitle' => 'Sales',
            'filters' => $filters,
            'result' => Quotes::paginate($filters, $page, self::PER_PAGE),
            'summary' => Quotes::summary(),
            'pageActions' => '<a class="btn btn-primary" href="' . e(url('/quotations/create')) . '">' . icon('plus') . ' New quotation</a>',
        ]);
    }

    // ── Create / edit ─────────────────────────────────────────────────────────
    public function create(): never
    {
        $form = $this->blankForm();

        $customerId = (int) $this->request->query('customer_id', 0);
        if ($customerId > 0 && CustomerRepository::find($customerId)) {
            $form['customer_id'] = (string) $customerId;
        }

        $enquiryId = (int) $this->request->query('enquiry_id', 0);
        $enquiry = $enquiryId > 0 ? Database::fetch('SELECT * FROM enquiries WHERE id = ?', [$enquiryId]) : null;
        if ($enquiry) {
            $form = $this->fromEnquiry($form, $enquiry);
        }

        $packageId = (int) $this->request->query('package_id', 0);
        if ($packageId <= 0 && $form['package_id'] !== '') {
            $packageId = (int) $form['package_id'];
        }
        if ($packageId > 0 && ($pkg = (new PackageRepository())->find($packageId))) {
            $form = $this->applyPackage($form, $pkg);
        }

        $this->form(null, $form, $enquiry);
    }

    public function edit(string $id): never
    {
        $q = $this->findOr404($id);
        $form = [
            'customer_mode' => 'existing',
            'customer_id' => (string) $q['customer_id'],
            'new_name' => '', 'new_phone' => '', 'new_email' => '',
            'title' => (string) $q['title'],
            'package_id' => $q['package_id'] !== null ? (string) $q['package_id'] : '',
            'enquiry_id' => $q['enquiry_id'] !== null ? (string) $q['enquiry_id'] : '',
            'travel_start' => (string) ($q['travel_start'] ?? ''),
            'travel_end' => (string) ($q['travel_end'] ?? ''),
            'adults' => (string) $q['adults'],
            'children' => (string) $q['children'],
            'hotel_category' => (string) ($q['hotel_category'] ?? ''),
            'discount' => $this->num($q['discount']),
            'tax_percent' => $this->num($q['tax_percent']),
            'valid_until' => (string) ($q['valid_until'] ?? ''),
            'inclusions' => (string) ($q['inclusions'] ?? ''),
            'exclusions' => (string) ($q['exclusions'] ?? ''),
            'terms' => (string) ($q['terms'] ?? ''),
            'notes' => (string) ($q['notes'] ?? ''),
            'items' => array_map(fn (array $it): array => [
                'type' => (string) $it['type'],
                'description' => (string) $it['description'],
                'qty' => $this->num($it['qty']),
                'unit_price' => $this->num($it['unit_price']),
            ], Quotes::items((int) $q['id'])),
            'days' => array_map(static fn (array $d): array => [
                'title' => (string) $d['title'],
                'description' => (string) $d['description'],
                'stay' => (string) ($d['stay'] ?? ''),
            ], Quotes::days((int) $q['id'])),
        ];
        $enquiry = $q['enquiry_id'] !== null ? Database::fetch('SELECT * FROM enquiries WHERE id = ?', [(int) $q['enquiry_id']]) : null;
        $this->form($q, $form, $enquiry);
    }

    /** @param array<string,mixed>|null $q @param array<string,mixed> $form @param array<string,mixed>|null $enquiry */
    private function form(?array $q, array $form, ?array $enquiry): never
    {
        $isEdit = $q !== null;
        $back = $isEdit ? '/quotations/' . (int) $q['id'] : '/quotations';
        $this->view('admin/quotations/form', [
            'title' => $isEdit ? 'Edit ' . $q['number'] : 'New quotation',
            'pretitle' => $isEdit ? 'Quotation ' . $q['number'] : 'Quotations',
            'pageTitle' => $isEdit ? 'Edit quotation' : 'New quotation',
            'quote' => $q,
            'form' => $form,
            'enquiry' => $enquiry,
            'customers' => CustomerRepository::options(2000),
            'packages' => Quotes::packageOptions(),
            'pageActions' => '<a class="btn btn-outline-secondary" href="' . e(url($back)) . '">Cancel</a>',
        ]);
    }

    public function store(): never
    {
        [$data, $items, $days, $customer, $errors] = $this->collect();
        if ($errors !== []) {
            $this->back('/quotations/create', $errors);
        }
        $id = Quotes::save(null, $data, $items, $days, $this->beforeSave($customer));
        $q = Quotes::find($id);
        Activity::log('created', 'quotation', $id, 'Created quotation ' . ($q['number'] ?? '') . ' “' . $data['title'] . '”');
        Flash::success('Quotation ' . ($q['number'] ?? '') . ' saved. Share it with the customer below.');
        $this->redirect('/quotations/' . $id);
    }

    public function update(string $id): never
    {
        $q = $this->findOr404($id);
        $qid = (int) $q['id'];
        [$data, $items, $days, $customer, $errors] = $this->collect();
        if ($errors !== []) {
            $this->back('/quotations/' . $qid . '/edit', $errors);
        }
        Quotes::save($qid, $data, $items, $days, $this->beforeSave($customer));
        Activity::log('updated', 'quotation', $qid, 'Updated quotation ' . $q['number'] . ' “' . $data['title'] . '”');
        Flash::success('Quotation ' . $q['number'] . ' updated.');
        $this->redirect('/quotations/' . $qid);
    }

    /**
     * Runs inside the save transaction: quick-add the customer (find-or-create by phone) and link the lead.
     * @param array<string,mixed> $customer
     */
    private function beforeSave(array $customer): callable
    {
        return static function (array $row) use ($customer): array {
            if ($customer['mode'] === 'new') {
                $row['customer_id'] = CustomerRepository::findOrCreate([
                    'name' => $customer['name'],
                    'phone' => $customer['phone'],
                    'email' => $customer['email'],
                    'source' => $customer['source'],
                ]);
            }
            if (!empty($row['enquiry_id'])) {
                Database::query('UPDATE enquiries SET customer_id = ? WHERE id = ? AND customer_id IS NULL', [$row['customer_id'], $row['enquiry_id']]);
            }
            return $row;
        };
    }

    /**
     * Validate + normalise the builder form.
     * @return array{0:array<string,mixed>,1:list<array<string,mixed>>,2:list<array<string,mixed>>,3:array<string,mixed>,4:array<string,string>}
     */
    private function collect(): array
    {
        $in = $this->request->all();
        $v = Validator::make($in, [
            'customer_mode' => 'required|in:existing,new',
            'title' => 'required|string|max:200',
            'package_id' => 'nullable|int|min:1',
            'enquiry_id' => 'nullable|int|min:1',
            'adults' => 'required|int|min:1|max:99',
            'children' => 'nullable|int|min:0|max:99',
            'hotel_category' => 'nullable|string|max:40',
            'discount' => 'nullable|numeric|min:0|max:99999999',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'inclusions' => 'nullable|string|max:6000',
            'exclusions' => 'nullable|string|max:6000',
            'terms' => 'nullable|string|max:6000',
            'notes' => 'nullable|string|max:6000',
        ], [
            'customer_mode' => 'Customer',
            'title' => 'Trip title',
            'tax_percent' => 'GST %',
            'adults' => 'Adults',
            'notes' => 'Internal notes',
        ]);
        $errors = $v->errors();
        $c = $v->validated();

        // Customer: existing record or quick-add.
        $customer = ['mode' => (string) ($c['customer_mode'] ?? 'existing'), 'name' => '', 'phone' => '', 'email' => null, 'source' => 'other'];
        $customerId = null;
        if ($customer['mode'] === 'new') {
            $cv = Validator::make($in, [
                'new_name' => 'required|string|max:120',
                'new_phone' => 'required|phone',
                'new_email' => 'nullable|email|max:190',
            ], ['new_name' => 'Customer name', 'new_phone' => 'Mobile number', 'new_email' => 'Email']);
            $errors += $cv->errors();
            $cc = $cv->validated();
            if (!isset($errors['new_phone']) && CustomerRepository::normalizePhone((string) ($cc['new_phone'] ?? '')) === '') {
                $errors['new_phone'] = 'Mobile number must be a valid phone number.';
            }
            $customer['name'] = (string) ($cc['new_name'] ?? '');
            $customer['phone'] = (string) ($cc['new_phone'] ?? '');
            $customer['email'] = $cc['new_email'] ?? null;
        } else {
            $customerId = filter_var($in['customer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($customerId === false || CustomerRepository::find($customerId) === null) {
                $errors['customer_id'] = 'Choose the customer, or switch to “New customer” to add one.';
                $customerId = null;
            }
        }

        // Linked records must exist.
        $packageId = $c['package_id'] ?? null;
        if ($packageId !== null && !Quotes::packageExists((int) $packageId)) {
            $errors['package_id'] = 'That package no longer exists.';
            $packageId = null;
        }
        $enquiryId = $c['enquiry_id'] ?? null;
        if ($enquiryId !== null) {
            $enq = Database::fetch('SELECT id, source FROM enquiries WHERE id = ?', [(int) $enquiryId]);
            if ($enq === null) {
                $enquiryId = null;
            } else {
                $customer['source'] = (string) ($enq['source'] ?: 'website');
            }
        }

        // Dates.
        $start = $this->date($in['travel_start'] ?? null, 'travel_start', $errors);
        $end = $this->date($in['travel_end'] ?? null, 'travel_end', $errors);
        if ($start !== null && $end !== null && $end < $start) {
            $errors['travel_end'] = 'The trip cannot end before it starts.';
        }
        $validUntil = $this->date($in['valid_until'] ?? null, 'valid_until', $errors)
            ?? date('Y-m-d', strtotime('+' . $this->validityDays() . ' days'));

        // Price lines.
        $items = [];
        $rawItems = is_array($in['items'] ?? null) ? $in['items'] : [];
        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $desc = trim(is_string($row['description'] ?? null) ? $row['description'] : '');
            $qtyRaw = trim(is_string($row['qty'] ?? null) ? $row['qty'] : '');
            $priceRaw = trim(is_string($row['unit_price'] ?? null) ? str_replace(',', '', $row['unit_price']) : '');
            if ($desc === '' && ($priceRaw === '' || (float) $priceRaw === 0.0)) {
                continue; // blank row
            }
            if ($desc === '') {
                $errors['items'] = 'Every price line needs a description.';
                continue;
            }
            $qty = $qtyRaw === '' ? 1.0 : (is_numeric($qtyRaw) ? (float) $qtyRaw : -1.0);
            if ($qty <= 0 || $qty > 9999) {
                $errors['items'] = 'Quantity must be a number between 0.5 and 9999 (check “' . str_limit($desc, 40) . '”).';
                continue;
            }
            $price = $priceRaw === '' ? 0.0 : (is_numeric($priceRaw) ? (float) $priceRaw : -1.0);
            if ($price < 0 || $price > 99999999) {
                $errors['items'] = 'Rate must be a positive amount in rupees (check “' . str_limit($desc, 40) . '”).';
                continue;
            }
            $type = is_string($row['type'] ?? null) && isset(Quotes::ITEM_TYPES[$row['type']]) ? $row['type'] : 'other';
            $items[] = ['type' => $type, 'description' => mb_substr($desc, 0, 300), 'qty' => round($qty, 2), 'unit_price' => round($price, 2)];
            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }
        if ($items === [] && !isset($errors['items'])) {
            $errors['items'] = 'Add at least one price line (for example the package cost per person).';
        }

        // Itinerary days (numbered in the order shown).
        $days = [];
        $rawDays = is_array($in['days'] ?? null) ? $in['days'] : [];
        foreach ($rawDays as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim(is_string($row['title'] ?? null) ? $row['title'] : '');
            $desc = trim(is_string($row['description'] ?? null) ? $row['description'] : '');
            $stay = trim(is_string($row['stay'] ?? null) ? $row['stay'] : '');
            if ($title === '' && $desc === '' && $stay === '') {
                continue;
            }
            $days[] = [
                'title' => mb_substr($title !== '' ? $title : 'Day ' . (count($days) + 1), 0, 200),
                'description' => mb_substr($desc, 0, 3000),
                'stay' => $stay !== '' ? mb_substr($stay, 0, 120) : null,
            ];
            if (count($days) >= self::MAX_DAYS) {
                break;
            }
        }

        $totals = Quotes::calculate($items, (float) ($c['discount'] ?? 0), (float) ($c['tax_percent'] ?? 0));
        foreach ($items as $i => &$it) {
            $it['amount'] = $totals['amounts'][$i];
        }
        unset($it);

        $data = [
            'customer_id' => $customerId,
            'enquiry_id' => $enquiryId,
            'package_id' => $packageId,
            'title' => (string) ($c['title'] ?? ''),
            'travel_start' => $start,
            'travel_end' => $end,
            'adults' => (int) ($c['adults'] ?? 2),
            'children' => (int) ($c['children'] ?? 0),
            'hotel_category' => ($c['hotel_category'] ?? '') !== '' ? $c['hotel_category'] : null,
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'tax_percent' => $totals['tax_percent'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'valid_until' => $validUntil,
            'inclusions' => $this->lines($c['inclusions'] ?? null),
            'exclusions' => $this->lines($c['exclusions'] ?? null),
            'terms' => ($c['terms'] ?? '') !== '' ? str_replace("\r\n", "\n", (string) $c['terms']) : null,
            'notes' => ($c['notes'] ?? '') !== '' ? str_replace("\r\n", "\n", (string) $c['notes']) : null,
        ];
        return [$data, $items, $days, $customer, $errors];
    }

    // ── Detail ────────────────────────────────────────────────────────────────
    public function show(string $id): never
    {
        $q = $this->findOr404($id);
        $qid = (int) $q['id'];
        $status = Quotes::effectiveStatus($q);
        $shareUrl = $this->shareUrl($q);
        $enquiry = $q['enquiry_id'] !== null ? Database::fetch('SELECT id, name, stage FROM enquiries WHERE id = ?', [(int) $q['enquiry_id']]) : null;
        $package = $q['package_id'] !== null ? Database::fetch('SELECT id, title FROM packages WHERE id = ?', [(int) $q['package_id']]) : null;

        $actions = '<a class="btn btn-outline-secondary" href="' . e(url('/quotations')) . '">' . icon('arrow-left') . ' All quotations</a>'
            . '<a class="btn btn-outline-primary" href="' . e(url('/quotations/' . $qid . '/print')) . '" target="_blank" rel="noopener">' . icon('printer') . ' Print / PDF</a>'
            . '<a class="btn btn-primary" href="' . e(url('/quotations/' . $qid . '/edit')) . '">' . icon('edit') . ' Edit</a>';

        $this->view('admin/quotations/show', [
            'title' => $q['number'] . ' · ' . $q['title'],
            'pretitle' => 'Quotation ' . $q['number'],
            'pageTitle' => (string) $q['title'],
            'pageActions' => $actions,
            'q' => $q,
            'status' => $status,
            'items' => Quotes::items($qid),
            'days' => Quotes::days($qid),
            'booking' => Quotes::booking($qid),
            'enquiry' => $enquiry,
            'package' => $package,
            'shareUrl' => $shareUrl,
            'canConvert' => feature('bookings') && can('bookings'),
        ]);
    }

    public function print(string $id): never
    {
        $q = $this->findOr404($id);
        $qid = (int) $q['id'];
        $toolbar = '<button class="btn btn-outline-secondary" type="button" data-copy="' . e($this->shareUrl($q)) . '">' . icon('link') . ' Copy share link</button>';
        $this->view('admin/quotations/print', [
            'title' => $q['number'] . ' — ' . ($q['customer_name'] ?? 'Quotation'),
            'backUrl' => '/quotations/' . $qid,
            'toolbar' => $toolbar,
            'q' => $q,
            'status' => Quotes::effectiveStatus($q),
            'items' => Quotes::items($qid),
            'days' => Quotes::days($qid),
        ], 'layouts/print');
    }

    // ── Actions ───────────────────────────────────────────────────────────────
    public function status(string $id): never
    {
        $q = $this->findOr404($id);
        $qid = (int) $q['id'];
        $new = (string) $this->request->input('status', '');
        $labels = ['draft' => 'back to draft', 'sent' => 'sent', 'accepted' => 'accepted', 'rejected' => 'rejected'];
        if (!isset($labels[$new])) {
            Flash::error('Unknown status.');
            $this->redirect('/quotations/' . $qid);
        }
        if ($new !== 'accepted' && Quotes::booking($qid) !== null) {
            Flash::error('This quotation is already a booking, so it stays “Accepted”.');
            $this->redirect('/quotations/' . $qid);
        }
        if ($new === 'sent') {
            Quotes::markSent($q);
        } else {
            Quotes::setStatus($qid, $new);
        }
        Activity::log('updated', 'quotation', $qid, 'Marked quotation ' . $q['number'] . ' as ' . $labels[$new]);
        Flash::success('Quotation ' . $q['number'] . ' marked as ' . $labels[$new] . '.');
        $this->redirect('/quotations/' . $qid);
    }

    /** Mark as sent, then hand over to WhatsApp with the template message. */
    public function whatsapp(string $id): never
    {
        $q = $this->findOr404($id);
        Quotes::markSent($q);
        Activity::log('sent', 'quotation', (int) $q['id'], 'Shared quotation ' . $q['number'] . ' on WhatsApp');
        Response::redirect(wa_link((string) ($q['customer_phone'] ?? ''), $this->whatsappText($q)));
    }

    public function email(string $id): never
    {
        $q = $this->findOr404($id);
        $qid = (int) $q['id'];
        $to = trim((string) ($q['customer_email'] ?? ''));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            Flash::error('This customer has no email address. Add one on the customer record, or share on WhatsApp instead.');
            $this->redirect('/quotations/' . $qid);
        }
        $vars = $this->vars($q);
        $tpl = Template::find('Quotation email', 'email');
        $subject = Template::render($tpl && (string) $tpl['subject'] !== '' ? (string) $tpl['subject'] : 'Your trip quotation {{quotation_number}} — {{company_name}}', $vars);
        $body = Template::render($tpl ? (string) $tpl['body'] : "Dear {{customer_name}},\n\nThank you for choosing {{company_name}}. Please find your quotation for {{trip}} here: {{quotation_link}}\n\nTotal: {{amount}} (valid till {{valid_until}}).\n\nWarm regards,\n{{company_name}}\n{{company_phone}}", $vars);

        if (!Mailer::send($to, $subject, $body)) {
            Flash::error('The email could not be sent from this server. Please check the email settings, or share the link on WhatsApp instead.');
            $this->redirect('/quotations/' . $qid);
        }
        Quotes::markSent($q);
        Activity::log('sent', 'quotation', $qid, 'Emailed quotation ' . $q['number'] . ' to ' . $to);
        Flash::success('Quotation emailed to ' . $to . '.');
        $this->redirect('/quotations/' . $qid);
    }

    public function duplicate(string $id): never
    {
        $q = $this->findOr404($id);
        $newId = Quotes::duplicate((int) $q['id'], $this->validityDays());
        if ($newId === null) {
            Response::abort(404);
        }
        $copy = Quotes::find($newId);
        Activity::log('created', 'quotation', $newId, 'Duplicated quotation ' . $q['number'] . ' as ' . ($copy['number'] ?? ''));
        Flash::success('Copy created as ' . ($copy['number'] ?? '') . '. Make your changes and save.');
        $this->redirect('/quotations/' . $newId . '/edit');
    }

    public function destroy(string $id): never
    {
        $q = $this->findOr404($id);
        $qid = (int) $q['id'];
        if (($b = Quotes::booking($qid)) !== null) {
            Flash::error('Quotation ' . $q['number'] . ' was converted to booking ' . $b['number'] . ', so it cannot be deleted.');
            $this->redirect('/quotations/' . $qid);
        }
        Quotes::delete($qid);
        Activity::log('deleted', 'quotation', $qid, 'Deleted quotation ' . $q['number'] . ' “' . $q['title'] . '”');
        Flash::success('Quotation ' . $q['number'] . ' deleted.');
        $this->redirect('/quotations');
    }

    public function convert(string $id): never
    {
        if (!feature('bookings') || !can('bookings')) {
            Response::abort(403, 'Bookings are not available for your account.');
        }
        $q = $this->findOr404($id);
        $qid = (int) $q['id'];
        if (($b = Quotes::booking($qid)) !== null) {
            Flash::info('This quotation is already booking ' . $b['number'] . '.');
            $this->redirect('/bookings/' . (int) $b['id']);
        }
        if (empty($q['travel_start']) || empty($q['travel_end'])) {
            Flash::withInput([], ['travel_start' => 'Add the trip start and end dates — a booking needs them.']);
            Flash::error('Add the trip start and end dates before converting this quotation to a booking.');
            $this->redirect('/quotations/' . $qid . '/edit');
        }
        $result = Quotes::convertToBooking($qid, (string) $q['title']);
        if ($result['created']) {
            Activity::log('created', 'booking', $result['id'], 'Booking ' . $result['number'] . ' created from quotation ' . $q['number']);
            Flash::success('Booking ' . $result['number'] . ' created from quotation ' . $q['number'] . '. Add traveller details and payments next.');
        } else {
            Flash::info('This quotation is already booking ' . $result['number'] . '.');
        }
        $this->redirect('/bookings/' . $result['id']);
    }

    /** JSON for "Load itinerary from package". */
    public function package(string $id): never
    {
        $pkg = (new PackageRepository())->find($this->id($id));
        if ($pkg === null) {
            $this->json(['error' => 'Package not found'], 404);
        }
        $this->json([
            'id' => (int) $pkg['id'],
            'title' => (string) $pkg['title'],
            'nights' => (int) $pkg['nights'],
            'days' => (int) $pkg['days'],
            'priceFrom' => (int) $pkg['price_from'],
            'itinerary' => array_map(static fn (array $d): array => [
                'title' => (string) $d['title'],
                'description' => (string) $d['description'],
                'stay' => (string) ($d['stay'] ?? ''),
            ], $pkg['itinerary']),
            'inclusions' => array_values($pkg['inclusions']),
            'exclusions' => array_values($pkg['exclusions']),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function findOr404(string $id): array
    {
        $q = Quotes::find($this->id($id));
        if ($q === null) {
            Response::abort(404);
        }
        return $q;
    }

    private function validityDays(): int
    {
        return max(1, min(365, (int) setting('quotation_validity_days')));
    }

    /** @return array<string,mixed> */
    private function blankForm(): array
    {
        return [
            'customer_mode' => 'existing', 'customer_id' => '', 'new_name' => '', 'new_phone' => '', 'new_email' => '',
            'title' => '', 'package_id' => '', 'enquiry_id' => '',
            'travel_start' => '', 'travel_end' => '', 'adults' => '2', 'children' => '0', 'hotel_category' => '',
            'discount' => '0', 'tax_percent' => $this->num(setting('tax_percent_default')),
            'valid_until' => date('Y-m-d', strtotime('+' . $this->validityDays() . ' days')),
            'inclusions' => '', 'exclusions' => '', 'terms' => setting('quotation_terms'), 'notes' => '',
            'items' => [], 'days' => [],
        ];
    }

    /** @param array<string,mixed> $form @param array<string,mixed> $enq @return array<string,mixed> */
    private function fromEnquiry(array $form, array $enq): array
    {
        $form['enquiry_id'] = (string) $enq['id'];
        $customer = !empty($enq['customer_id']) ? CustomerRepository::find((int) $enq['customer_id']) : null;
        if ($customer === null && ($phone = CustomerRepository::normalizePhone((string) $enq['phone'])) !== '') {
            $customer = Database::fetch('SELECT id FROM customers WHERE phone = ?', [$phone]);
        }
        if ($customer !== null) {
            $form['customer_mode'] = 'existing';
            $form['customer_id'] = (string) $customer['id'];
        } else {
            $form['customer_mode'] = 'new';
            $form['new_name'] = (string) $enq['name'];
            $form['new_phone'] = (string) $enq['phone'];
            $form['new_email'] = (string) ($enq['email'] ?? '');
        }
        $form['adults'] = (string) max(1, (int) $enq['adults']);
        $form['children'] = (string) max(0, (int) $enq['children']);
        $hotel = trim((string) ($enq['hotel_category'] ?? ''));
        if ($hotel !== '') {
            $form['hotel_category'] = mb_substr($hotel, 0, 40);
        }
        $pkgName = trim((string) ($enq['package'] ?? ''));
        $dest = trim((string) ($enq['destination'] ?? ''));
        $form['title'] = $pkgName !== '' ? $pkgName : ($dest !== '' ? $dest . ' tour' : 'Trip for ' . $enq['name']);
        if ($pkgName !== '') {
            $match = Database::fetch('SELECT id FROM packages WHERE title = ? LIMIT 1', [$pkgName]);
            if ($match) {
                $form['package_id'] = (string) $match['id'];
            }
        }
        if (!empty($enq['travel_date'])) {
            $form['travel_start'] = (string) $enq['travel_date'];
        }
        $notes = [];
        if (trim((string) ($enq['travel_month'] ?? '')) !== '') {
            $notes[] = 'Customer wants to travel in: ' . trim((string) $enq['travel_month']);
        }
        if (!empty($enq['budget'])) {
            $notes[] = 'Budget: ' . inr((int) $enq['budget']);
        }
        if ($dest !== '' && $pkgName !== '') {
            $notes[] = 'Destination asked: ' . $dest;
        }
        $form['notes'] = implode("\n", $notes);
        return $form;
    }

    /** @param array<string,mixed> $form @param array<string,mixed> $pkg @return array<string,mixed> */
    private function applyPackage(array $form, array $pkg): array
    {
        $form['package_id'] = (string) $pkg['id'];
        if ($form['title'] === '' || $form['title'] === $pkg['title']) {
            $form['title'] = (string) $pkg['title'];
        }
        $adults = max(1, (int) $form['adults']);
        $form['items'] = [[
            'type' => 'package',
            'description' => $pkg['title'] . ' — ' . (int) $pkg['nights'] . 'N/' . (int) $pkg['days'] . 'D, per person',
            'qty' => (string) $adults,
            'unit_price' => (string) (int) $pkg['price_from'],
        ]];
        $form['days'] = array_map(static fn (array $d): array => [
            'title' => (string) $d['title'], 'description' => (string) $d['description'], 'stay' => (string) ($d['stay'] ?? ''),
        ], $pkg['itinerary']);
        $form['inclusions'] = implode("\n", $pkg['inclusions']);
        $form['exclusions'] = implode("\n", $pkg['exclusions']);
        if ($form['travel_start'] !== '' && $form['travel_end'] === '' && (int) $pkg['nights'] > 0) {
            $form['travel_end'] = date('Y-m-d', strtotime($form['travel_start'] . ' +' . (int) $pkg['nights'] . ' days'));
        }
        return $form;
    }

    /** Valid Y-m-d or null; records an error for a non-empty invalid value. @param array<string,string> $errors */
    private function date(mixed $raw, string $field, array &$errors): ?string
    {
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($d === false || $d->format('Y-m-d') !== $raw || (int) $d->format('Y') < 2000 || (int) $d->format('Y') > 2100) {
            $errors[$field] = 'Please pick a valid date.';
            return null;
        }
        return $raw;
    }

    /** Trim lines, drop blanks. */
    private function lines(mixed $text): ?string
    {
        $lines = array_filter(array_map('trim', preg_split('/\R/', (string) $text) ?: []), static fn ($l) => $l !== '');
        return $lines ? implode("\n", array_map(static fn ($l) => mb_substr($l, 0, 300), $lines)) : null;
    }

    /** "1500.00" → "1500", "2.50" → "2.5" (for form inputs). */
    private function num(mixed $v): string
    {
        $s = number_format((float) $v, 2, '.', '');
        return rtrim(rtrim($s, '0'), '.') ?: '0';
    }

    /** Absolute public share link. @param array<string,mixed> $q */
    private function shareUrl(array $q): string
    {
        return rtrim((string) Env::get('APP_URL', ''), '/') . url('/q/' . $q['public_token']);
    }

    /** @param array<string,mixed> $q @return array<string,string> */
    private function vars(array $q): array
    {
        return [
            'customer_name' => (string) ($q['customer_name'] ?? ''),
            'trip' => (string) $q['title'],
            'amount' => inr($q['total']),
            'quotation_number' => (string) $q['number'],
            'quotation_link' => $this->shareUrl($q),
            'valid_until' => nice_day($q['valid_until'] ?? null),
            'travel_start' => nice_day($q['travel_start'] ?? null),
            'travel_end' => nice_day($q['travel_end'] ?? null),
        ];
    }

    /** @param array<string,mixed> $q */
    private function whatsappText(array $q): string
    {
        return Template::message(
            'Quotation shared',
            $this->vars($q),
            'Hi {{customer_name}}, here is your trip quotation {{quotation_number}} for {{trip}} ({{amount}}): {{quotation_link}} — valid till {{valid_until}}. Reply here for any changes. — {{company_name}}',
        );
    }
}
