<?php
/**
 * Add / edit vendor.
 * @var array<string,mixed>|null $vendor @var string $defaultType
 */
use App\Repositories\VendorRepository;

$r = $vendor ?? [];
$isEdit = $vendor !== null;
$action = $isEdit ? url('/vendors/' . $r['id']) : url('/vendors');
$val = static fn (string $k, mixed $default = ''): string => (string) old($k, $r[$k] ?? $default);
$fieldError = static fn (string $k): string => ($err = error_for($k)) ? '<div class="invalid-feedback">' . e($err) . '</div>' : '';
$type = $val('type', $defaultType);
$rating = (int) $val('rating', '');
$hasOld = old('name', null) !== null;
$active = $hasOld ? old('is_active', '') !== '' : (int) ($r['is_active'] ?? 1) === 1;
?>
<form method="post" action="<?= e($action) ?>" novalidate class="rth-ops">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Vendor details</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label required" for="name">Name</label>
              <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="160" placeholder="e.g. New Golden Flower Houseboats" required>
              <?= $fieldError('name') ?>
            </div>
            <div class="col-md-4">
              <label class="form-label required" for="type">Type</label>
              <select class="form-select<?= invalid('type') ?>" id="type" name="type">
                <?php foreach (VendorRepository::TYPES as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= $fieldError('type') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="contact_person">Contact person</label>
              <input type="text" class="form-control<?= invalid('contact_person') ?>" id="contact_person" name="contact_person" value="<?= e($val('contact_person')) ?>" maxlength="120" placeholder="e.g. Ghulam Rasool (manager)">
              <?= $fieldError('contact_person') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="phone">Phone / WhatsApp</label>
              <input type="tel" class="form-control<?= invalid('phone') ?>" id="phone" name="phone" value="<?= e($val('phone')) ?>" maxlength="30" placeholder="e.g. 94190 12345">
              <?= $fieldError('phone') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" value="<?= e($val('email')) ?>" maxlength="190" placeholder="reservations@example.com">
              <?= $fieldError('email') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="city">City / area</label>
              <input type="text" class="form-control<?= invalid('city') ?>" id="city" name="city" value="<?= e($val('city')) ?>" maxlength="120" placeholder="e.g. Srinagar, Gulmarg, Pahalgam">
              <?= $fieldError('city') ?>
            </div>
            <div class="col-12">
              <label class="form-label" for="address">Address</label>
              <input type="text" class="form-control<?= invalid('address') ?>" id="address" name="address" value="<?= e($val('address')) ?>" maxlength="255" placeholder="e.g. Ghat No. 9, Dal Lake">
              <?= $fieldError('address') ?>
            </div>
            <div class="col-12">
              <label class="form-label" for="notes">Notes</label>
              <textarea class="form-control<?= invalid('notes') ?>" id="notes" name="notes" rows="3" maxlength="2000" data-counter="2000" placeholder="Room categories, rates for the season, check-in rules, who to call after 9 pm…"><?= e($val('notes')) ?></textarea>
              <?= $fieldError('notes') ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Payment details</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="gstin">GSTIN <span class="text-secondary fw-normal">(optional)</span></label>
            <input type="text" class="form-control text-uppercase<?= invalid('gstin') ?>" id="gstin" name="gstin" value="<?= e($val('gstin')) ?>" maxlength="20" placeholder="01ABCDE1234F1Z5" autocomplete="off">
            <?= $fieldError('gstin') ?>
          </div>
          <div>
            <label class="form-label" for="bank_details">Bank / UPI details</label>
            <textarea class="form-control<?= invalid('bank_details') ?>" id="bank_details" name="bank_details" rows="3" maxlength="255" data-counter="255" placeholder="A/c name, A/c no., IFSC — or UPI ID"><?= e($val('bank_details')) ?></textarea>
            <?= $fieldError('bank_details') ?>
            <div class="form-hint">Shown on the payables page so you can pay quickly.</div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="card-title">Rating &amp; status</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="rating">Your rating</label>
            <select class="form-select<?= invalid('rating') ?>" id="rating" name="rating">
              <option value="">Not rated</option>
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?= $i ?>"<?= $rating === $i ? ' selected' : '' ?>><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> — <?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
              <?php endfor; ?>
            </select>
            <?= $fieldError('rating') ?>
            <div class="form-hint">How happy guests were — helps you choose next time.</div>
          </div>
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"<?= $active ? ' checked' : '' ?>>
            <span class="form-check-label">Active — show in pick lists</span>
          </label>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Add vendor' ?></button>
          <a href="<?= e(url($isEdit ? '/vendors/' . $r['id'] : '/vendors')) ?>" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php if ($isEdit): ?>
  <div class="card mt-3 border-danger-subtle">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="me-auto">
        <div class="fw-semibold">Delete this vendor</div>
        <div class="text-secondary small">Only possible when it has never been used on a booking. Otherwise switch off “Active” instead.</div>
      </div>
      <form method="post" action="<?= e(url('/vendors/' . $r['id'])) ?>" class="m-0" data-confirm="Delete <?= e($r['name']) ?>? This cannot be undone.">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-outline-danger"><?= icon('trash') ?> Delete vendor</button>
      </form>
    </div>
  </div>
<?php endif; ?>
