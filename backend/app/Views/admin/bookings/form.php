<?php
/**
 * Create / edit booking.
 * @var array<string,mixed> $booking
 * @var list<array<string,mixed>> $travellers
 * @var array<string,mixed>|null $customer
 * @var array<string,mixed>|null $quotation
 * @var list<array<string,mixed>> $customers
 * @var list<array<string,mixed>> $packages
 * @var list<array<string,mixed>> $staff
 * @var array<string,string> $sources
 * @var bool $isEdit
 */
use App\Repositories\BookingRepository as Bk;

$action = $isEdit ? url('/bookings/' . $booking['id']) : url('/bookings');
$val = static fn (string $k) => old($k, $booking[$k] ?? '');
$customerId = (string) old('customer_id', (string) ($booking['customer_id'] ?? ''));
$oldTravellers = old('travellers', null);
$rows = is_array($oldTravellers) ? array_values(array_filter($oldTravellers, 'is_array')) : $travellers;
if ($rows === []) {
    $rows = [['name' => '', 'age' => '', 'gender' => '', 'phone' => '', 'id_proof' => '']];
}
$field = static function (string $name, string $label, string $value, string $type = 'text', array $attrs = []) {
    $id = 'bk-' . str_replace('_', '-', $name);
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . '="' . e($v) . '"';
    }
    $err = error_for($name);
    return '<label class="form-label' . (!empty($attrs['required']) ? ' required' : '') . '" for="' . e($id) . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '" class="form-control' . invalid($name) . '" value="' . e($value) . '"' . $extra . '>'
        . ($err ? '<div class="invalid-feedback">' . e($err) . '</div>' : '');
};
$travellerRow = static function (string $index, array $t): string {
    $g = (string) ($t['gender'] ?? '');
    ob_start(); ?>
    <div class="rth-repeater-item rth-bk-traveller">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="rth-repeater-number" data-repeater-number></span>
        <span class="small text-secondary">Traveller</span>
        <div class="ms-auto btn-list">
          <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" data-repeater-up title="Move up" aria-label="Move up"><?= icon('arrow-up') ?></button>
          <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" data-repeater-down title="Move down" aria-label="Move down"><?= icon('arrow-down') ?></button>
          <button type="button" class="btn btn-sm btn-icon btn-ghost-danger" data-repeater-remove title="Remove traveller" aria-label="Remove traveller"><?= icon('trash') ?></button>
        </div>
      </div>
      <div class="row g-2">
        <div class="col-md-4">
          <input type="text" class="form-control" name="travellers[<?= e($index) ?>][name]" value="<?= e($t['name'] ?? '') ?>" placeholder="Full name" maxlength="120" aria-label="Traveller name">
        </div>
        <div class="col-4 col-md-1">
          <input type="number" class="form-control" name="travellers[<?= e($index) ?>][age]" value="<?= e($t['age'] ?? '') ?>" placeholder="Age" min="0" max="120" aria-label="Age">
        </div>
        <div class="col-8 col-md-2">
          <select class="form-select" name="travellers[<?= e($index) ?>][gender]" aria-label="Gender">
            <option value="">Gender</option>
            <?php foreach (Bk::GENDERS as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= $g === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="tel" class="form-control" name="travellers[<?= e($index) ?>][phone]" value="<?= e($t['phone'] ?? '') ?>" placeholder="Phone (optional)" maxlength="30" aria-label="Traveller phone">
        </div>
        <div class="col-md-3">
          <input type="text" class="form-control" name="travellers[<?= e($index) ?>][id_proof]" value="<?= e($t['id_proof'] ?? '') ?>" placeholder="e.g. Aadhaar ••••1234" maxlength="80" aria-label="ID proof note">
        </div>
      </div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<form method="post" action="<?= e($action) ?>" class="rth-bookings rth-bk-form" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
  <?php if (!$isEdit): ?>
    <input type="hidden" name="quotation_id" value="<?= e((string) old('quotation_id', (string) ($booking['quotation_id'] ?? ''))) ?>">
    <input type="hidden" name="enquiry_id" value="<?= e((string) old('enquiry_id', (string) ($booking['enquiry_id'] ?? ''))) ?>">
  <?php endif; ?>

  <?php if ($quotation): ?>
    <div class="alert alert-info d-flex align-items-center gap-2"><?= icon('file-text') ?>
      <div>Booking from quotation <strong><?= e($quotation['number']) ?></strong> — details were copied over; check them before saving.</div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <!-- Customer -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('user') ?> Customer</h3></div>
        <div class="card-body">
          <label class="form-label" for="bk-customer-id">Existing customer</label>
          <select id="bk-customer-id" name="customer_id" class="form-select<?= invalid('customer_id') ?>" data-bk-customer>
            <option value="">— New customer (fill in below) —</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= $customerId === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?> · <?= e($c['phone']) ?></option>
            <?php endforeach; ?>
            <?php if ($customer && !in_array((int) $customer['id'], array_map(static fn ($c) => (int) $c['id'], $customers), true)): ?>
              <option value="<?= (int) $customer['id'] ?>" selected><?= e($customer['name']) ?> · <?= e($customer['phone']) ?></option>
            <?php endif; ?>
          </select>
          <?php if ($err = error_for('customer_id')): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>

          <div class="rth-bk-newcustomer mt-3" data-bk-newcustomer>
            <div class="small text-secondary mb-2">New customer? Type their name and phone — if the phone is already saved, the booking is linked to that customer.</div>
            <div class="row g-2">
              <div class="col-md-5"><?= $field('new_customer_name', 'Name', (string) old('new_customer_name', ''), 'text', ['maxlength' => '120', 'placeholder' => 'e.g. Aarav Sharma']) ?></div>
              <div class="col-md-4"><?= $field('new_customer_phone', 'Phone / WhatsApp', (string) old('new_customer_phone', ''), 'tel', ['maxlength' => '20', 'placeholder' => '98xxxxxxxx']) ?></div>
              <div class="col-md-3">
                <label class="form-label" for="bk-new-customer-source">Came from</label>
                <select id="bk-new-customer-source" name="new_customer_source" class="form-select">
                  <?php $src = (string) old('new_customer_source', 'phone'); foreach ($sources as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $src === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Trip -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('map-pin') ?> Trip</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="bk-package-id">Package <span class="text-secondary small">(optional)</span></label>
              <select id="bk-package-id" name="package_id" class="form-select<?= invalid('package_id') ?>" data-bk-package>
                <option value="">— Custom trip —</option>
                <?php $pid = (string) old('package_id', (string) ($booking['package_id'] ?? '')); foreach ($packages as $p): ?>
                  <option value="<?= (int) $p['id'] ?>" data-title="<?= e($p['title'] . ' (' . $p['nights'] . 'N/' . $p['days'] . 'D)') ?>" data-nights="<?= (int) $p['nights'] ?>"<?= $pid === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['title']) ?> · <?= (int) $p['nights'] ?>N/<?= (int) $p['days'] ?>D</option>
                <?php endforeach; ?>
              </select>
              <?php if ($err = error_for('package_id')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6"><?= $field('title', 'Trip title', (string) $val('title'), 'text', ['required' => 'required', 'maxlength' => '200', 'placeholder' => 'e.g. Kashmir Family Trip 5N/6D', 'data-bk-title' => '1']) ?></div>
            <div class="col-6 col-md-3"><?= $field('travel_start', 'Start date', (string) $val('travel_start'), 'date', ['required' => 'required', 'data-bk-start' => '1']) ?></div>
            <div class="col-6 col-md-3"><?= $field('travel_end', 'End date', (string) $val('travel_end'), 'date', ['required' => 'required', 'data-bk-end' => '1']) ?></div>
            <div class="col-6 col-md-3"><?= $field('adults', 'Adults', (string) $val('adults'), 'number', ['required' => 'required', 'min' => '1', 'max' => '99']) ?></div>
            <div class="col-6 col-md-3"><?= $field('children', 'Children', (string) $val('children'), 'number', ['min' => '0', 'max' => '99']) ?></div>
            <div class="col-md-6"><?= $field('pickup_point', 'Pickup point', (string) $val('pickup_point'), 'text', ['maxlength' => '160', 'placeholder' => 'e.g. Srinagar Airport, 11:30 am flight']) ?></div>
            <div class="col-md-6"><?= $field('drop_point', 'Drop point', (string) $val('drop_point'), 'text', ['maxlength' => '160', 'placeholder' => 'e.g. Jammu Tawi railway station']) ?></div>
            <div class="col-12">
              <label class="form-label" for="bk-special-requests">Special requests <span class="text-secondary small">(shown on the voucher)</span></label>
              <textarea id="bk-special-requests" name="special_requests" rows="2" class="form-control<?= invalid('special_requests') ?>" maxlength="5000" placeholder="e.g. Honeymoon decoration, vegetarian meals, ground-floor room"><?= e((string) $val('special_requests')) ?></textarea>
              <?php if ($err = error_for('special_requests')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Travellers -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('users') ?> Travellers</h3></div>
        <div class="card-body" data-repeater>
          <p class="small text-secondary mb-2">Add everyone travelling (optional). <strong>Privacy:</strong> for ID proof write only the type and last 4 digits, e.g. “Aadhaar ••••1234” — never the full number. Full numbers are shortened automatically.</p>
          <?php if ($err = error_for('travellers')): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
          <div data-repeater-items>
            <?php foreach ($rows as $i => $t): ?>
              <?= $travellerRow('r' . $i, $t) ?>
            <?php endforeach; ?>
          </div>
          <template data-repeater-template><?= $travellerRow('__INDEX__', []) ?></template>
          <button type="button" class="btn btn-outline-primary mt-2" data-repeater-add><?= icon('plus') ?> Add traveller</button>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('rupee') ?> Price &amp; status</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label required" for="bk-total-amount">Total amount (₹)</label>
            <input type="number" id="bk-total-amount" name="total_amount" class="form-control<?= invalid('total_amount') ?>" value="<?= e((string) $val('total_amount')) ?>" min="0" step="0.01" required placeholder="e.g. 48000">
            <?php if ($err = error_for('total_amount')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            <div class="form-hint">The final price agreed with the customer. Payments are recorded on the booking page.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="bk-status">Status</label>
            <select id="bk-status" name="status" class="form-select<?= invalid('status') ?>">
              <?php $st = (string) $val('status'); foreach (Bk::STATUSES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $st === $k ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label" for="bk-assigned-to">Handled by</label>
            <select id="bk-assigned-to" name="assigned_to" class="form-select<?= invalid('assigned_to') ?>">
              <option value="">— Not assigned —</option>
              <?php $as = (string) old('assigned_to', (string) ($booking['assigned_to'] ?? '')); foreach ($staff as $s): ?>
                <option value="<?= (int) $s['id'] ?>"<?= $as === (string) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($err = error_for('assigned_to')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('lock') ?> Internal notes</h3></div>
        <div class="card-body">
          <textarea name="notes" rows="5" class="form-control<?= invalid('notes') ?>" maxlength="5000" aria-label="Internal notes" placeholder="Only your team sees this — hotel confirmations, driver name, reminders…"><?= e((string) $val('notes')) ?></textarea>
          <?php if ($err = error_for('notes')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill"><?= icon('check') ?> <?= $isEdit ? 'Save booking' : 'Create booking' ?></button>
        <a href="<?= e(url($isEdit ? '/bookings/' . $booking['id'] : '/bookings')) ?>" class="btn">Cancel</a>
      </div>
    </div>
  </div>
</form>
