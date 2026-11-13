<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\BookingServiceRepository;
use App\Repositories\VehicleRepository;
use App\Repositories\VendorRepository;

/**
 * Supplier costs on a booking (hotel, houseboat, cab, guide …) → profit & vendor payables.
 * The "Costs & profit" card is the partial admin/booking-services/panel.php (included on /bookings/{id}).
 * Every action redirects back to /bookings/{booking_id} (or to the preview / vendor page when asked via `return`).
 */
final class BookingServiceController extends Controller
{
    /** Form field names are prefixed "svc_" so they never clash with other forms on the booking page. */
    private const FIELDS = ['svc_type', 'svc_vendor_id', 'svc_vehicle_id', 'svc_description', 'svc_date', 'svc_nights',
        'svc_cost', 'svc_paid', 'svc_status', 'svc_confirmation', 'svc_notes'];

    /** GET /booking-services/preview/{booking_id} — the panel on its own inside the admin layout. */
    public function preview(string $booking_id): never
    {
        $booking = $this->bookingOr404($this->id($booking_id));
        $this->view('admin/booking-services/preview', [
            'title' => 'Costs & profit — ' . $booking['number'],
            'pageTitle' => 'Costs & profit',
            'pretitle' => $booking['number'] . ' · ' . $booking['title'],
            'booking' => $booking,
            'pageActions' => feature('bookings') && can('bookings')
                ? '<a href="' . e(url('/bookings/' . (int) $booking['id'])) . '" class="btn btn-outline-secondary">' . icon('eye') . ' Open booking</a>'
                : '',
        ]);
    }

    /** POST /bookings/{id}/services */
    public function store(string $id): never
    {
        $booking = $this->bookingOr404($this->id($id));
        $return = $this->returnTo((int) $booking['id'], null);
        [$data, $errors] = $this->validateInput(null);
        if ($errors !== []) {
            Flash::withInput($this->request->all(), $errors);
            Flash::error('The service was not added — please fix the highlighted fields.');
            Response::redirect(url($return));
        }
        $sid = BookingServiceRepository::create((int) $booking['id'], $data);
        $this->warnDoubleBooking($data, $sid);
        Activity::log('created', 'booking_service', $sid, 'Added cost “' . $data['description'] . '” (' . inr($data['cost_price']) . ') to booking ' . $booking['number']);
        Flash::success('Added “' . $data['description'] . '” — ' . inr($data['cost_price']) . ' cost.');
        $this->redirect($return);
    }

    /** GET /booking-services/{id}/edit */
    public function edit(string $id): never
    {
        $svc = $this->serviceOr404($this->id($id));
        $booking = $this->bookingOr404((int) $svc['booking_id']);
        $ret = (string) $this->request->query('return', '');
        $this->view('admin/booking-services/edit', [
            'title' => 'Edit service',
            'pageTitle' => 'Edit service',
            'pretitle' => $booking['number'] . ' · ' . $booking['title'],
            'service' => $svc,
            'booking' => $booking,
            'return' => in_array($ret, ['preview', 'vendor'], true) ? $ret : '',
            'cancelUrl' => $this->returnTo((int) $booking['id'], $svc, $ret),
        ]);
    }

    /** PUT /booking-services/{id} */
    public function update(string $id): never
    {
        $svc = $this->serviceOr404($this->id($id));
        $booking = $this->bookingOr404((int) $svc['booking_id']);
        [$data, $errors] = $this->validateInput($svc);
        if ($errors !== []) {
            $ret = (string) $this->request->input('return', '');
            $this->back('/booking-services/' . (int) $svc['id'] . '/edit' . (in_array($ret, ['preview', 'vendor'], true) ? '?return=' . $ret : ''), $errors);
        }
        BookingServiceRepository::update((int) $svc['id'], $data);
        $this->warnDoubleBooking($data, (int) $svc['id']);
        Activity::log('updated', 'booking_service', (int) $svc['id'], 'Updated cost “' . $data['description'] . '” on booking ' . $booking['number']);
        Flash::success('Saved “' . $data['description'] . '”.');
        $this->redirect($this->returnTo((int) $booking['id'], $svc));
    }

    /** DELETE /booking-services/{id} */
    public function destroy(string $id): never
    {
        $svc = $this->serviceOr404($this->id($id));
        $booking = $this->bookingOr404((int) $svc['booking_id']);
        BookingServiceRepository::delete((int) $svc['id']);
        Activity::log('deleted', 'booking_service', (int) $svc['id'], 'Removed cost “' . $svc['description'] . '” from booking ' . $booking['number']);
        Flash::success('Removed “' . $svc['description'] . '”.');
        $this->redirect($this->returnTo((int) $booking['id'], $svc));
    }

    /** POST /booking-services/{id}/paid — supplier fully paid. */
    public function paid(string $id): never
    {
        $svc = $this->serviceOr404($this->id($id));
        $booking = $this->bookingOr404((int) $svc['booking_id']);
        BookingServiceRepository::markPaid((int) $svc['id']);
        $due = (float) $svc['cost_price'] - (float) $svc['paid_to_vendor'];
        Activity::log('updated', 'booking_service', (int) $svc['id'], 'Marked “' . $svc['description'] . '” as paid to '
            . ($svc['vendor_name'] ?: 'vendor') . ' (' . inr($svc['cost_price']) . ') — booking ' . $booking['number']);
        Flash::success('Marked as fully paid to ' . ($svc['vendor_name'] ?: 'the supplier') . ($due > 0 ? ' (' . inr($due) . ' settled).' : '.'));
        $this->redirect($this->returnTo((int) $booking['id'], $svc));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function bookingOr404(int $id): array
    {
        $b = BookingServiceRepository::booking($id);
        if ($b === null) {
            Response::abort(404);
        }
        return $b;
    }

    /** @return array<string,mixed> */
    private function serviceOr404(int $id): array
    {
        $s = BookingServiceRepository::find($id);
        if ($s === null) {
            Response::abort(404);
        }
        return $s;
    }

    /**
     * Where to go after an action: the booking page by default; `return=preview` → preview page;
     * `return=vendor` → the service's vendor profile.
     * @param array<string,mixed>|null $svc
     */
    private function returnTo(int $bookingId, ?array $svc, ?string $ret = null): string
    {
        $ret ??= (string) $this->request->input('return', '');
        if ($ret === 'preview') {
            return '/booking-services/preview/' . $bookingId;
        }
        if ($ret === 'vendor' && $svc !== null && !empty($svc['vendor_id'])) {
            return '/vendors/' . (int) $svc['vendor_id'];
        }
        return '/bookings/' . $bookingId . '#booking-costs';
    }

    /** Same vehicle already on another service that day → yellow warning (the save still goes through). @param array<string,mixed> $data */
    private function warnDoubleBooking(array $data, int $serviceId): void
    {
        if (empty($data['vehicle_id']) || empty($data['service_date']) || $data['status'] === 'cancelled') {
            return;
        }
        $clashes = VehicleRepository::conflicts((int) $data['vehicle_id'], (string) $data['service_date'], (int) ($data['nights'] ?? 0), $serviceId);
        if ($clashes === []) {
            return;
        }
        $vehicle = VehicleRepository::find((int) $data['vehicle_id']);
        $list = implode(', ', array_map(
            static fn (array $c): string => $c['booking_number'] . ' (' . nice_day((string) $c['service_date']) . ')',
            $clashes,
        ));
        $msg = 'Heads up: ' . ($vehicle['name'] ?? 'this vehicle') . ' is already assigned on these dates to ' . $list
            . '. Saved anyway — please check the driver can do both.';
        $all = Session::get('_flash', []);
        $all = is_array($all) ? $all : [];
        $all[] = ['type' => 'warning', 'message' => $msg];
        Session::put('_flash', $all);
    }

    /**
     * @param array<string,mixed>|null $existing
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function validateInput(?array $existing): array
    {
        $in = [];
        foreach (self::FIELDS as $k) {
            $v = $this->request->input($k, '');
            $in[$k] = is_string($v) || is_int($v) ? trim((string) $v) : '';
        }
        // Accept "12,500" or "₹12,500" in money fields.
        foreach (['svc_cost', 'svc_paid'] as $k) {
            $in[$k] = str_replace([',', '₹', ' '], '', $in[$k]);
        }
        if ($in['svc_status'] === '') {
            $in['svc_status'] = 'pending';
        }

        $v = Validator::make($in, [
            'svc_type' => 'required|in:' . implode(',', array_keys(BookingServiceRepository::TYPES)),
            'svc_vendor_id' => 'nullable|int|min:1',
            'svc_vehicle_id' => 'nullable|int|min:1',
            'svc_description' => 'nullable|string|max:300',
            'svc_date' => 'nullable|string|max:10',
            'svc_nights' => 'nullable|int|min:0|max:60',
            'svc_cost' => 'required|numeric|min:0|max:99999999',
            'svc_paid' => 'nullable|numeric|min:0|max:99999999',
            'svc_status' => 'required|in:' . implode(',', array_keys(BookingServiceRepository::STATUSES)),
            'svc_confirmation' => 'nullable|string|max:80',
            'svc_notes' => 'nullable|string|max:255',
        ], [
            'svc_type' => 'Service type',
            'svc_vendor_id' => 'Vendor',
            'svc_vehicle_id' => 'Vehicle',
            'svc_description' => 'Description',
            'svc_date' => 'Date',
            'svc_nights' => 'Nights / days',
            'svc_cost' => 'Cost price',
            'svc_paid' => 'Paid to vendor',
            'svc_status' => 'Status',
            'svc_confirmation' => 'Confirmation no.',
            'svc_notes' => 'Notes',
        ]);
        $errors = $v->errors();
        $c = $v->validated();

        $vendor = null;
        if (!isset($errors['svc_vendor_id']) && !empty($c['svc_vendor_id'])) {
            $vendor = VendorRepository::find((int) $c['svc_vendor_id']);
            if ($vendor === null) {
                $errors['svc_vendor_id'] = 'Please pick a vendor from the list.';
            } elseif ((int) $vendor['is_active'] !== 1 && (int) ($existing['vendor_id'] ?? 0) !== (int) $vendor['id']) {
                $errors['svc_vendor_id'] = $vendor['name'] . ' is inactive. Activate it first or pick another vendor.';
            }
        }
        $vehicle = null;
        $type = (string) ($c['svc_type'] ?? '');
        if ($type === 'transport' && !isset($errors['svc_vehicle_id']) && !empty($c['svc_vehicle_id'])) {
            $vehicle = VehicleRepository::find((int) $c['svc_vehicle_id']);
            if ($vehicle === null) {
                $errors['svc_vehicle_id'] = 'Please pick a vehicle from the list.';
            }
        }
        if (!isset($errors['svc_date']) && $in['svc_date'] !== '') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $in['svc_date']);
            if ($d === false || $d->format('Y-m-d') !== $in['svc_date']) {
                $errors['svc_date'] = 'Date must be a valid date.';
            }
        }
        $cost = (float) ($c['svc_cost'] ?? 0);
        $paid = (float) ($c['svc_paid'] ?? 0);
        if (!isset($errors['svc_cost']) && !isset($errors['svc_paid']) && $paid > $cost + 0.001) {
            $errors['svc_paid'] = 'Paid to vendor cannot be more than the cost price (' . inr($cost) . ').';
        }

        // Empty description → build one from the type + vendor / vehicle, e.g. "Hotel — Hotel Highland Park".
        $description = (string) ($c['svc_description'] ?? '');
        if ($description === '' && !isset($errors['svc_type'])) {
            $who = $vendor['name'] ?? ($vehicle['name'] ?? '');
            $description = $who !== '' ? BookingServiceRepository::typeLabel($type) . ' — ' . $who : '';
        }
        if ($description === '' && !isset($errors['svc_description'])) {
            $errors['svc_description'] = 'Add a short description, e.g. “Deluxe houseboat, 2 nights, MAP” — or pick a vendor.';
        }

        if ($errors !== []) {
            return [[], $errors];
        }
        return [[
            'service_type' => $type,
            'vendor_id' => $vendor !== null ? (int) $vendor['id'] : null,
            'vehicle_id' => $vehicle !== null ? (int) $vehicle['id'] : null,
            'description' => mb_substr($description, 0, 300),
            'service_date' => $in['svc_date'] !== '' ? $in['svc_date'] : null,
            'nights' => $c['svc_nights'] ?? null,
            'cost_price' => round($cost, 2),
            'paid_to_vendor' => round($paid, 2),
            'status' => $c['svc_status'],
            'confirmation_no' => $c['svc_confirmation'] ?? null,
            'notes' => $c['svc_notes'] ?? null,
        ], []];
    }
}
