<?php
/**
 * Quotation builder (create + edit). Live totals + "load from package" in assets/admin/js/quotations.js.
 * @var array<string,mixed>|null $quote
 * @var array<string,mixed> $form          values (prefilled or from DB)
 * @var array<string,mixed>|null $enquiry
 * @var list<array{id:int,name:string,phone:string}> $customers
 * @var list<array<string,mixed>> $packages
 */
use App\Repositories\QuotationRepository as Quotes;

$isEdit = $quote !== null;
$action = $isEdit ? url('/quotations/' . (int) $quote['id']) : url('/quotations');
$submitted = old('_form', '') === 'quotation';
$val = static function (string $k) use ($submitted, $form): string {
    $v = $submitted ? old($k, '') : ($form[$k] ?? '');
    return is_scalar($v) ? (string) $v : '';
};
$rows = static function (string $k, array $fields) use ($submitted, $form): array {
    $raw = $submitted ? old($k, []) : ($form[$k] ?? []);
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $r) {
        if (!is_array($r)) {
            continue;
        }
        $row = [];
        foreach ($fields as $f) {
            $row[$f] = is_scalar($r[$f] ?? null) ? (string) $r[$f] : '';
        }
        $out[] = $row;
    }
    return $out;
};
$items = $rows('items', ['type', 'description', 'qty', 'unit_price']);
$days = $rows('days', ['title', 'description', 'stay']);
if ($items === []) {
    $items[] = ['type' => 'package', 'description' => '', 'qty' => '1', 'unit_price' => ''];
}
if ($days === []) {
    $days[] = ['title' => '', 'description' => '', 'stay' => ''];
}
$mode = $val('customer_mode') === 'new' ? 'new' : 'existing';
$customerId = $val('customer_id');
$packageId = $val('package_id');
$hotel = $val('hotel_category');

// Initial totals (JS keeps them live; the server recalculates on save).
$calcItems = array_map(static fn (array $r): array => [
    'qty' => is_numeric($r['qty']) ? (float) $r['qty'] : 1.0,
    'unit_price' => is_numeric(str_replace(',', '', $r['unit_price'])) ? (float) str_replace(',', '', $r['unit_price']) : 0.0,
], $items);
$totals = Quotes::calculate($calcItems, (float) $val('discount'), (float) $val('tax_percent'));

$err = static fn (string $k): string => error_for($k) !== null ? '<div class="invalid-feedback d-block">' . e(error_for($k)) . '</div>' : '';
$inv = static fn (string $k): string => error_for($k) !== null ? ' is-invalid' : '';

$itemRow = static function (string $index, array $r): string {
    ob_start(); ?>
  <div class="rth-repeater-item rth-qitem">
    <div class="row g-2 align-items-center">
      <div class="col-12 col-md-2">
        <label class="form-label d-md-none small mb-1">Type</label>
        <select class="form-select" name="items[<?= e($index) ?>][type]" aria-label="Line type">
          <?php foreach (Quotes::ITEM_TYPES as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $r['type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label d-md-none small mb-1">Description</label>
        <input type="text" class="form-control" name="items[<?= e($index) ?>][description]" value="<?= e($r['description']) ?>" maxlength="300" placeholder="e.g. Houseboat 1 night (deluxe)" aria-label="Description" data-field="description">
      </div>
      <div class="col-4 col-md-1">
        <label class="form-label d-md-none small mb-1">Qty</label>
        <input type="number" class="form-control" name="items[<?= e($index) ?>][qty]" value="<?= e($r['qty'] !== '' ? $r['qty'] : '1') ?>" min="0" max="9999" step="any" inputmode="decimal" aria-label="Quantity" data-qty data-field="qty">
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label d-md-none small mb-1">Rate (₹)</label>
        <input type="number" class="form-control" name="items[<?= e($index) ?>][unit_price]" value="<?= e($r['unit_price']) ?>" min="0" step="any" inputmode="decimal" placeholder="0" aria-label="Rate in rupees" data-price data-field="unit_price">
      </div>
      <div class="col-4 col-md-2 text-end">
        <label class="form-label d-md-none small mb-1">Amount</label>
        <div class="rth-qitem-amount" data-amount>—</div>
      </div>
      <div class="col-12 col-md-1 text-end">
        <button type="button" class="btn btn-icon btn-ghost-danger" data-repeater-remove title="Remove line" aria-label="Remove line"><?= icon('x') ?></button>
      </div>
    </div>
  </div>
<?php return (string) ob_get_clean();
};

$dayRow = static function (string $index, array $d): string {
    ob_start(); ?>
  <div class="rth-repeater-item">
    <div class="d-flex align-items-center gap-2 mb-2">
      <span class="badge bg-primary-lt fs-5">Day <span data-repeater-number>1</span></span>
      <div class="btn-list flex-nowrap ms-auto">
        <button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-up title="Move up" aria-label="Move up"><?= icon('arrow-up') ?></button>
        <button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-down title="Move down" aria-label="Move down"><?= icon('arrow-down') ?></button>
        <button type="button" class="btn btn-icon btn-ghost-danger" data-repeater-remove title="Remove day" aria-label="Remove day"><?= icon('x') ?></button>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-md-8">
        <input type="text" class="form-control" name="days[<?= e($index) ?>][title]" value="<?= e($d['title']) ?>" maxlength="200" placeholder="e.g. Arrive Srinagar · Shikara ride on Dal Lake" aria-label="Day title" data-field="title">
      </div>
      <div class="col-md-4">
        <input type="text" class="form-control" name="days[<?= e($index) ?>][stay]" value="<?= e($d['stay']) ?>" maxlength="120" placeholder="Night stay (e.g. Srinagar houseboat)" aria-label="Night stay" data-field="stay">
      </div>
      <div class="col-12">
        <textarea class="form-control" name="days[<?= e($index) ?>][description]" rows="2" maxlength="3000" placeholder="What happens this day…" aria-label="Day description" data-field="description"><?= e($d['description']) ?></textarea>
      </div>
    </div>
  </div>
<?php return (string) ob_get_clean();
};
?>
<form method="post" action="<?= e($action) ?>" class="rth-quote-form" data-quote-builder novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
  <input type="hidden" name="_form" value="quotation">
  <input type="hidden" name="enquiry_id" value="<?= e($val('enquiry_id')) ?>">

  <?php if ($enquiry !== null): ?>
    <div class="alert alert-info d-flex gap-2 align-items-start">
      <?= icon('inbox') ?>
      <div>Quoting for enquiry from <strong><?= e($enquiry['name']) ?></strong>
        <?php if (can('enquiries')): ?> · <a href="<?= e(url('/enquiries/' . (int) $enquiry['id'])) ?>">open enquiry</a><?php endif; ?>
        <?php if (!$isEdit && trim((string) ($enquiry['message'] ?? '')) !== ''): ?><div class="small text-secondary mt-1">“<?= e(str_limit((string) $enquiry['message'], 220)) ?>”</div><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <!-- Customer -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('user') ?> Customer</h3></div>
        <div class="card-body">
          <div class="btn-group w-100 mb-3" role="group" aria-label="Customer type">
            <input type="radio" class="btn-check" name="customer_mode" id="cm-existing" value="existing"<?= $mode === 'existing' ? ' checked' : '' ?> data-customer-mode>
            <label class="btn" for="cm-existing">Existing customer</label>
            <input type="radio" class="btn-check" name="customer_mode" id="cm-new" value="new"<?= $mode === 'new' ? ' checked' : '' ?> data-customer-mode>
            <label class="btn" for="cm-new"><?= icon('plus') ?> New customer</label>
          </div>
          <div data-customer-panel="existing">
            <label class="form-label required" for="q-customer">Customer</label>
            <select id="q-customer" name="customer_id" class="form-select<?= $inv('customer_id') ?>">
              <option value="">Choose a customer…</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= e((string) $c['id']) ?>"<?= $customerId === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?> · <?= e($c['phone']) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('customer_id') ?>
            <?php if ($customers === []): ?><div class="form-hint">No customers yet — choose “New customer” to add one.</div><?php endif; ?>
          </div>
          <div data-customer-panel="new">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label required" for="q-new-name">Name</label>
                <input type="text" id="q-new-name" name="new_name" class="form-control<?= $inv('new_name') ?>" value="<?= e($val('new_name')) ?>" maxlength="120" autocomplete="off" placeholder="e.g. Rohan Mehta">
                <?= $err('new_name') ?>
              </div>
              <div class="col-md-4">
                <label class="form-label required" for="q-new-phone">Mobile</label>
                <input type="tel" id="q-new-phone" name="new_phone" class="form-control<?= $inv('new_phone') ?>" value="<?= e($val('new_phone')) ?>" maxlength="20" autocomplete="off" placeholder="98765 43210">
                <?= $err('new_phone') ?>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="q-new-email">Email <span class="text-secondary small">(optional)</span></label>
                <input type="email" id="q-new-email" name="new_email" class="form-control<?= $inv('new_email') ?>" value="<?= e($val('new_email')) ?>" maxlength="190" autocomplete="off">
                <?= $err('new_email') ?>
              </div>
            </div>
            <div class="form-hint mt-1">If this mobile number is already saved, the quotation is added to that customer.</div>
          </div>
        </div>
      </div>

      <!-- Trip -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('map-pin') ?> Trip details</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label required" for="q-title">Trip title</label>
            <input type="text" id="q-title" name="title" class="form-control<?= $inv('title') ?>" value="<?= e($val('title')) ?>" maxlength="200" placeholder="e.g. Kashmir Honeymoon — 5 Nights / 6 Days" data-quote-title>
            <?= $err('title') ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="q-package">Based on package <span class="text-secondary small">(optional)</span></label>
            <div class="d-flex gap-2 flex-wrap flex-md-nowrap">
              <select id="q-package" name="package_id" class="form-select<?= $inv('package_id') ?>" data-quote-package>
                <option value="">No package — custom trip</option>
                <?php foreach ($packages as $p): ?>
                  <option value="<?= e((string) $p['id']) ?>" data-nights="<?= e((string) $p['nights']) ?>"<?= $packageId === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['title']) ?> (<?= e((string) $p['nights']) ?>N/<?= e((string) $p['days']) ?>D · from <?= e(inr($p['price_from'])) ?>)<?= $p['status'] !== 'published' ? ' — draft' : '' ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-outline-primary text-nowrap" data-quote-load-package><?= icon('download') ?> Load itinerary from package</button>
            </div>
            <?= $err('package_id') ?>
            <div class="form-hint">Copies the package's day-wise plan, inclusions and exclusions into this quotation — then edit freely.</div>
          </div>
          <div class="row g-2">
            <div class="col-6 col-md-3">
              <label class="form-label" for="q-start">Trip starts</label>
              <input type="date" id="q-start" name="travel_start" class="form-control<?= $inv('travel_start') ?>" value="<?= e($val('travel_start')) ?>" data-quote-start>
              <?= $err('travel_start') ?>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label" for="q-end">Trip ends</label>
              <input type="date" id="q-end" name="travel_end" class="form-control<?= $inv('travel_end') ?>" value="<?= e($val('travel_end')) ?>" data-quote-end>
              <?= $err('travel_end') ?>
              <div class="form-hint" data-quote-nights></div>
            </div>
            <div class="col-4 col-md-2">
              <label class="form-label required" for="q-adults">Adults</label>
              <input type="number" id="q-adults" name="adults" class="form-control<?= $inv('adults') ?>" value="<?= e($val('adults')) ?>" min="1" max="99" data-quote-adults>
              <?= $err('adults') ?>
            </div>
            <div class="col-4 col-md-2">
              <label class="form-label" for="q-children">Children</label>
              <input type="number" id="q-children" name="children" class="form-control<?= $inv('children') ?>" value="<?= e($val('children')) ?>" min="0" max="99">
              <?= $err('children') ?>
            </div>
            <div class="col-4 col-md-2">
              <label class="form-label" for="q-hotel">Hotels</label>
              <select id="q-hotel" name="hotel_category" class="form-select<?= $inv('hotel_category') ?>">
                <option value="">Any</option>
                <?php foreach (Quotes::HOTEL_CATEGORIES as $h): ?>
                  <option value="<?= e($h) ?>"<?= $hotel === $h ? ' selected' : '' ?>><?= e($h) ?></option>
                <?php endforeach; ?>
                <?php if ($hotel !== '' && !in_array($hotel, Quotes::HOTEL_CATEGORIES, true)): ?>
                  <option value="<?= e($hotel) ?>" selected><?= e($hotel) ?></option>
                <?php endif; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Prices -->
      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title"><?= icon('rupee') ?> Price lines</h3>
            <div class="card-subtitle text-secondary small">Package cost, hotels, cabs, activities… Amount = quantity × rate.</div>
          </div>
        </div>
        <div class="card-body" data-repeater data-quote-items>
          <?php if (error_for('items') !== null): ?><div class="alert alert-danger py-2"><?= e(error_for('items')) ?></div><?php endif; ?>
          <div class="row g-2 d-none d-md-flex small text-secondary fw-medium mb-1 px-2">
            <div class="col-md-2">Type</div><div class="col-md-4">Description</div><div class="col-md-1">Qty</div>
            <div class="col-md-2">Rate (₹)</div><div class="col-md-2 text-end">Amount</div><div class="col-md-1"></div>
          </div>
          <div data-repeater-items>
            <?php foreach ($items as $i => $r): ?><?= $itemRow((string) $i, $r) ?><?php endforeach; ?>
          </div>
          <template data-repeater-template><?= $itemRow('__INDEX__', ['type' => 'other', 'description' => '', 'qty' => '1', 'unit_price' => '']) ?></template>
          <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-repeater-add><?= icon('plus') ?> Add price line</button>
        </div>
      </div>

      <!-- Itinerary -->
      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title"><?= icon('calendar') ?> Day-wise itinerary</h3>
            <div class="card-subtitle text-secondary small">Days are numbered in the order shown. Leave empty rows — they are ignored.</div>
          </div>
        </div>
        <div class="card-body" data-repeater data-quote-days>
          <div data-repeater-items>
            <?php foreach ($days as $i => $d): ?><?= $dayRow((string) $i, $d) ?><?php endforeach; ?>
          </div>
          <template data-repeater-template><?= $dayRow('__INDEX__', ['title' => '', 'description' => '', 'stay' => '']) ?></template>
          <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-repeater-add><?= icon('plus') ?> Add day</button>
        </div>
      </div>

      <!-- Inclusions -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('list') ?> Inclusions &amp; exclusions</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="q-incl">What's included <span class="text-secondary small">(one per line)</span></label>
              <textarea id="q-incl" name="inclusions" class="form-control<?= $inv('inclusions') ?>" rows="6" maxlength="6000" placeholder="Accommodation on twin sharing&#10;Daily breakfast &amp; dinner&#10;Private cab for all transfers" data-quote-inclusions><?= e($val('inclusions')) ?></textarea>
              <?= $err('inclusions') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="q-excl">Not included <span class="text-secondary small">(one per line)</span></label>
              <textarea id="q-excl" name="exclusions" class="form-control<?= $inv('exclusions') ?>" rows="6" maxlength="6000" placeholder="Air / train fare&#10;Pony rides &amp; Gondola tickets&#10;Personal expenses" data-quote-exclusions><?= e($val('exclusions')) ?></textarea>
              <?= $err('exclusions') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Terms + notes -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('note') ?> Terms &amp; notes</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="q-terms">Terms shown to the customer <span class="text-secondary small">(one per line)</span></label>
            <textarea id="q-terms" name="terms" class="form-control<?= $inv('terms') ?>" rows="4" maxlength="6000"><?= e($val('terms')) ?></textarea>
            <?= $err('terms') ?>
          </div>
          <div>
            <label class="form-label" for="q-notes">Internal notes <span class="badge bg-secondary-lt ms-1"><?= icon('lock', 'icon icon-sm') ?> only your team sees this</span></label>
            <textarea id="q-notes" name="notes" class="form-control<?= $inv('notes') ?>" rows="3" maxlength="6000" placeholder="Hotel options checked, vendor rates, customer preferences…"><?= e($val('notes')) ?></textarea>
            <?= $err('notes') ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar: totals + save -->
    <div class="col-lg-4">
      <div class="rth-quote-side">
        <div class="card mb-3 rth-quote-totals">
          <div class="card-header"><h3 class="card-title">Total price</h3></div>
          <div class="card-body">
            <div class="rth-qt-row"><span>Subtotal</span><strong data-total="subtotal"><?= e(inr2($totals['subtotal'])) ?></strong></div>
            <div class="row g-2 my-2">
              <div class="col-6">
                <label class="form-label small mb-1" for="q-discount">Discount (₹)</label>
                <input type="number" id="q-discount" name="discount" class="form-control<?= $inv('discount') ?>" value="<?= e($val('discount')) ?>" min="0" step="any" inputmode="decimal" data-discount>
                <?= $err('discount') ?>
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="q-tax">GST %</label>
                <input type="number" id="q-tax" name="tax_percent" class="form-control<?= $inv('tax_percent') ?>" value="<?= e($val('tax_percent')) ?>" min="0" max="100" step="any" inputmode="decimal" data-tax>
                <?= $err('tax_percent') ?>
              </div>
            </div>
            <div class="rth-qt-row text-secondary"><span>Discount</span><span data-total="discount">− <?= e(inr2($totals['discount'])) ?></span></div>
            <div class="rth-qt-row text-secondary"><span>GST</span><span data-total="tax"><?= e(inr2($totals['tax_amount'])) ?></span></div>
            <div class="rth-qt-row rth-qt-grand"><span>Grand total</span><span data-total="total"><?= e(inr2($totals['total'])) ?></span></div>
            <div class="small text-secondary mt-1" data-total="pp"></div>
          </div>
        </div>
        <div class="card mb-3">
          <div class="card-body">
            <label class="form-label" for="q-valid">Valid until</label>
            <input type="date" id="q-valid" name="valid_until" class="form-control<?= $inv('valid_until') ?>" value="<?= e($val('valid_until')) ?>">
            <?= $err('valid_until') ?>
            <div class="form-hint">After this date the quotation shows as “Expired”.</div>
            <button type="submit" class="btn btn-primary w-100 mt-3"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Save quotation' ?></button>
            <?php if ($isEdit): ?>
              <a class="btn btn-ghost-secondary w-100 mt-2" href="<?= e(url('/quotations/' . (int) $quote['id'])) ?>">Cancel</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
