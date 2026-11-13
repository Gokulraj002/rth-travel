<?php
/**
 * Create / edit a GST invoice.
 * @var array<string,mixed> $invoice   (status: new|draft|issued)
 * @var array<string,mixed>|null $booking
 * @var list<array<string,mixed>> $customers
 * @var list<array<string,mixed>> $bookings
 * @var array<string,string> $states
 * @var string $companyState
 * @var string $sac
 */
$isNew = $invoice['id'] === null;
$status = (string) $invoice['status'];
$hasOld = old('issue_date', null) !== null;
$val = static fn (string $k, mixed $d = '') => $hasOld ? old($k, '') : $d;

$items = $hasOld && is_array(old('items', null)) ? array_values(old('items', [])) : $invoice['items'];
if ($items === []) {
    $items = [['description' => '', 'hsn_sac' => $sac, 'qty' => '1', 'unit_price' => '']];
}
$inclusive = $hasOld ? in_array(old('amounts_include_gst', ''), ['1', 'on'], true) : (bool) $invoice['inclusive'];
$customerId = (string) $val('customer_id', (string) ($invoice['customer_id'] ?? ''));
$pos = (string) $val('place_of_supply', (string) $invoice['place_of_supply']);
$action = $isNew ? url('/invoices') : url('/invoices/' . $invoice['id']);
$cancel = $isNew ? ($booking && feature('bookings') && can('bookings') ? '/bookings/' . $booking['id'] : '/invoices') : '/invoices/' . $invoice['id'];

$row = static function (int|string $i, array $it) use ($sac): string {
    $n = static fn (string $k) => 'items[' . $i . '][' . $k . ']';
    ob_start(); ?>
    <div class="rth-repeater-item rth-inv-line" data-acc-line>
      <div class="row g-2 align-items-end">
        <div class="col-12">
          <label class="form-label small mb-1">Description</label>
          <textarea class="form-control" name="<?= e($n('description')) ?>" rows="2" maxlength="300" placeholder="e.g. Houseboat stay, 2 nights" aria-label="Description"><?= e($it['description'] ?? '') ?></textarea>
        </div>
        <div class="col-6 col-sm-3">
          <label class="form-label small mb-1">SAC</label>
          <input type="text" class="form-control" name="<?= e($n('hsn_sac')) ?>" value="<?= e($it['hsn_sac'] ?? $sac) ?>" inputmode="numeric" maxlength="8" aria-label="SAC code">
        </div>
        <div class="col-6 col-sm-2">
          <label class="form-label small mb-1">Qty</label>
          <input type="number" class="form-control" name="<?= e($n('qty')) ?>" value="<?= e($it['qty'] ?? '1') ?>" min="0.01" step="0.01" inputmode="decimal" aria-label="Quantity" data-acc-qty>
        </div>
        <div class="col-6 col-sm-3">
          <label class="form-label small mb-1">Rate (₹)</label>
          <input type="number" class="form-control" name="<?= e($n('unit_price')) ?>" value="<?= e($it['unit_price'] ?? '') ?>" min="0" step="0.01" inputmode="decimal" aria-label="Rate in rupees" data-acc-rate>
        </div>
        <div class="col-4 col-sm-3 text-sm-end">
          <div class="small text-secondary mb-1">Amount</div>
          <strong class="d-block py-2" data-acc-amount>—</strong>
        </div>
        <div class="col-2 col-sm-1 text-end">
          <button type="button" class="btn btn-ghost-danger btn-icon" data-repeater-remove title="Remove line" aria-label="Remove line"><?= icon('trash') ?></button>
        </div>
      </div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<?php if ($isNew && !$booking && $bookings !== []): ?>
  <form method="get" action="<?= e(url('/invoices/create')) ?>" class="card mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-12 col-md">
        <label class="form-label" for="inv-from-booking">Start from a booking <span class="text-secondary fw-normal">(optional)</span></label>
        <select id="inv-from-booking" name="booking_id" class="form-select" data-acc-autosubmit>
          <option value="">Choose a booking to fill in the customer and trip…</option>
          <?php foreach ($bookings as $b): ?>
            <option value="<?= e((string) $b['id']) ?>"><?= e($b['number'] . ' · ' . $b['customer_name'] . ' · ' . $b['title'] . ' · ' . nice_day((string) $b['travel_start'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-auto"><button type="submit" class="btn btn-outline-primary w-100">Use booking</button></div>
    </div>
  </form>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="rth-inv-form" novalidate
      data-acc-invoice data-company-state="<?= e($companyState) ?>">
  <?= csrf_field() ?>
  <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
  <?php if ($booking): ?><input type="hidden" name="booking_id" value="<?= e((string) $booking['id']) ?>"><?php endif; ?>

  <div class="row row-cards">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('user', 'icon me-1') ?> Bill to</h3></div>
        <div class="card-body">
          <?php if ($booking): ?>
            <div class="rth-acc-booking mb-3">
              <div class="fw-semibold"><?= e($booking['customer_name']) ?> <span class="text-secondary fw-normal">· <?= e($booking['customer_phone']) ?></span></div>
              <div class="small text-secondary">
                Booking <?= e($booking['number']) ?> — <?= e($booking['title']) ?>, <?= e(nice_day((string) $booking['travel_start'])) ?> to <?= e(nice_day((string) $booking['travel_end'])) ?>
                · Total <?= e(inr2($booking['total_amount'])) ?> · Paid <?= e(inr2($booking['paid'])) ?>
              </div>
              <?php if ($isNew): ?><a class="small" href="<?= e(url('/invoices/create')) ?>">Invoice without a booking instead</a><?php endif; ?>
            </div>
          <?php else: ?>
            <div class="mb-3">
              <label class="form-label" for="inv-customer">Customer</label>
              <select id="inv-customer" name="customer_id" class="form-select<?= invalid('customer_id') ?>">
                <option value="">— New customer (type below) —</option>
                <?php foreach ($customers as $cu): ?>
                  <option value="<?= e((string) $cu['id']) ?>"<?= (string) $cu['id'] === $customerId ? ' selected' : '' ?>><?= e($cu['name'] . ' · ' . $cu['phone'] . ($cu['city'] ? ' · ' . $cu['city'] : '')) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($err = error_for('customer_id')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
            <div class="row" data-acc-newcustomer>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="inv-new-name">New customer name</label>
                <input type="text" id="inv-new-name" name="new_customer_name" class="form-control<?= invalid('new_customer_name') ?>" value="<?= e((string) $val('new_customer_name')) ?>" maxlength="120">
                <?php if ($err = error_for('new_customer_name')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="inv-new-phone">New customer phone</label>
                <input type="tel" id="inv-new-phone" name="new_customer_phone" class="form-control<?= invalid('new_customer_phone') ?>" value="<?= e((string) $val('new_customer_phone')) ?>" inputmode="tel" maxlength="30" placeholder="98765 43210">
                <?php if ($err = error_for('new_customer_phone')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
          <div class="mb-0">
            <label class="form-label" for="inv-gstin">Customer GSTIN <span class="text-secondary fw-normal">(only for business customers)</span></label>
            <input type="text" id="inv-gstin" name="customer_gstin" class="form-control<?= invalid('customer_gstin') ?>" value="<?= e((string) $val('customer_gstin', (string) $invoice['customer_gstin'])) ?>" maxlength="15" data-acc-upper autocomplete="off" placeholder="27ABCDE1234F1Z5">
            <?php if ($err = error_for('customer_gstin')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title"><?= icon('list', 'icon me-1') ?> What you are billing for</h3>
            <div class="card-subtitle">One line per service. Amount = quantity × rate.</div>
          </div>
        </div>
        <div class="card-body">
          <div data-repeater>
            <div data-repeater-items class="d-grid gap-2">
              <?php foreach ($items as $i => $it): ?><?= $row((int) $i, is_array($it) ? $it : []) ?><?php endforeach; ?>
            </div>
            <template data-repeater-template><?= $row('__INDEX__', ['hsn_sac' => $sac, 'qty' => '1']) ?></template>
            <?php if ($err = error_for('items')): ?><div class="text-danger small mt-2"><?= icon('alert', 'icon icon-sm') ?> <?= e($err) ?></div><?php endif; ?>
            <button type="button" class="btn btn-outline-primary mt-3" data-repeater-add><?= icon('plus') ?> Add line</button>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="inv-notes">Notes on the invoice <span class="text-secondary fw-normal">(optional)</span></label>
          <textarea id="inv-notes" name="notes" rows="2" class="form-control<?= invalid('notes') ?>" maxlength="2000" placeholder="e.g. Advance of ₹5,000 received on 2 Nov via UPI."><?= e((string) $val('notes', (string) $invoice['notes'])) ?></textarea>
          <?php if ($err = error_for('notes')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('receipt', 'icon me-1') ?> Invoice details</h3></div>
        <div class="card-body">
          <?php if ($status === 'issued'): ?>
            <div class="mb-3"><span class="text-secondary small">Invoice number</span><div class="fw-bold"><?= e($invoice['number']) ?></div></div>
          <?php endif; ?>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label required" for="inv-date">Invoice date</label>
              <input type="date" id="inv-date" name="issue_date" class="form-control<?= invalid('issue_date') ?>" value="<?= e((string) $val('issue_date', (string) $invoice['issue_date'])) ?>" required>
              <?php if ($err = error_for('issue_date')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label" for="inv-due">Due date</label>
              <input type="date" id="inv-due" name="due_date" class="form-control<?= invalid('due_date') ?>" value="<?= e((string) $val('due_date', (string) ($invoice['due_date'] ?? ''))) ?>">
              <?php if ($err = error_for('due_date')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label required" for="inv-pos">Place of supply</label>
            <select id="inv-pos" name="place_of_supply" class="form-select<?= invalid('place_of_supply') ?>" data-acc-pos>
              <?php foreach ($states as $code => $name): ?>
                <option value="<?= e($name) ?>"<?= strcasecmp($name, $pos) === 0 ? ' selected' : '' ?>><?= e($code . ' — ' . $name) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($err = error_for('place_of_supply')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            <div class="form-hint">Same as your state (<?= e($companyState) ?>) → CGST + SGST. Any other state → IGST.</div>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label required" for="inv-tax">GST %</label>
              <input type="number" id="inv-tax" name="tax_percent" class="form-control<?= invalid('tax_percent') ?>" value="<?= e((string) $val('tax_percent', (string) $invoice['tax_percent'])) ?>" min="0" max="28" step="0.01" inputmode="decimal" data-acc-tax>
              <?php if ($err = error_for('tax_percent')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label" for="inv-discount">Discount (₹)</label>
              <input type="number" id="inv-discount" name="discount" class="form-control<?= invalid('discount') ?>" value="<?= e((string) $val('discount', (string) $invoice['discount'])) ?>" min="0" step="0.01" inputmode="decimal" data-acc-discount>
              <?php if ($err = error_for('discount')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
          </div>
          <label class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" name="amounts_include_gst" value="1"<?= $inclusive ? ' checked' : '' ?> data-acc-inclusive>
            <span class="form-check-label">Amounts include GST</span>
          </label>
          <div class="form-hint">On: the rates above are what the customer pays, GST is worked out from inside them (₹10,500 at 5% = ₹10,000 + ₹500 GST). Off: GST is added on top.</div>
        </div>
      </div>

      <div class="card mb-3 rth-inv-summary">
        <div class="card-body">
          <div class="d-flex justify-content-between"><span class="text-secondary">Taxable value</span><strong data-acc-out="taxable">—</strong></div>
          <div class="d-flex justify-content-between" data-acc-intra><span class="text-secondary">CGST <span data-acc-out="halfrate"></span></span><span data-acc-out="cgst">—</span></div>
          <div class="d-flex justify-content-between" data-acc-intra><span class="text-secondary">SGST <span data-acc-out="halfrate2"></span></span><span data-acc-out="sgst">—</span></div>
          <div class="d-flex justify-content-between" data-acc-inter><span class="text-secondary">IGST <span data-acc-out="rate"></span></span><span data-acc-out="igst">—</span></div>
          <hr class="my-2">
          <div class="d-flex justify-content-between fs-3"><span>Invoice total</span><strong data-acc-out="total">—</strong></div>
          <div class="form-hint mt-2">Preview — final figures are calculated when you save.</div>
        </div>
      </div>

      <div class="d-grid gap-2">
        <?php if ($isNew || $status === 'draft'): ?>
          <button type="submit" name="save_as" value="issued" class="btn btn-primary"><?= icon('check') ?> <?= $isNew ? 'Create invoice' : 'Issue invoice' ?></button>
          <button type="submit" name="save_as" value="draft" class="btn btn-outline-secondary">Save as draft</button>
          <div class="form-hint text-center">The invoice number is given when you issue it, so numbers never skip.</div>
        <?php else: ?>
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save changes</button>
        <?php endif; ?>
        <a href="<?= e(url($cancel)) ?>" class="btn btn-ghost-secondary">Cancel</a>
      </div>
    </div>
  </div>
</form>
