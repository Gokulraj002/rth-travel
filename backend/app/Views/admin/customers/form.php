<?php
/**
 * Add / edit customer.
 * @var array<string,mixed>|null $customer
 * @var list<string> $tags  tags already in use (suggestions)
 */
use App\Repositories\CustomerRepository as C;

$c = $customer ?? [];
$isEdit = $customer !== null;
$action = $isEdit ? url('/customers/' . $c['id']) : url('/customers');
$val = static fn (string $k, mixed $default = ''): string => (string) old($k, $c[$k] ?? $default);
$err = static fn (string $k): string => ($m = error_for($k)) ? '<div class="invalid-feedback">' . e($m) . '</div>' : '';
$dupId = (string) old('_duplicate_id', '');
$dup = $dupId !== '' && ctype_digit($dupId) ? C::find((int) $dupId) : null;
$source = $val('source', 'phone');
$typedPhone = old('phone', null);
$phone = $typedPhone !== null ? (string) $typedPhone : ($isEdit ? C::displayPhone((string) $c['phone']) : '');
?>
<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

  <?php if ($dup): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2">
      <?= icon('alert') ?>
      <div>
        <strong><?= e($dup['name']) ?></strong> already uses this phone number.
        <a href="<?= e(url('/customers/' . $dup['id'])) ?>" class="alert-link">Open their profile</a> instead of adding them twice.
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Contact details</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required" for="name">Full name</label>
              <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" placeholder="e.g. Ananya Sharma" required>
              <?= $err('name') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label required" for="phone">Phone / WhatsApp</label>
              <input type="tel" class="form-control<?= invalid('phone') ?>" id="phone" name="phone" value="<?= e($phone) ?>" maxlength="30" inputmode="tel" placeholder="e.g. 98765 43210" required>
              <?php if ($m = error_for('phone')): ?>
                <div class="invalid-feedback"><?= e($m) ?><?php if ($dup): ?> <a href="<?= e(url('/customers/' . $dup['id'])) ?>">Open <?= e($dup['name']) ?></a><?php endif; ?></div>
              <?php else: ?>
                <div class="form-hint">One customer per phone number — we use it to match website enquiries.</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" value="<?= e($val('email')) ?>" maxlength="190">
              <?= $err('email') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="city">City</label>
              <input type="text" class="form-control<?= invalid('city') ?>" id="city" name="city" value="<?= e($val('city')) ?>" maxlength="120" placeholder="e.g. Pune">
              <?= $err('city') ?>
            </div>
            <div class="col-12">
              <label class="form-label" for="address">Address</label>
              <input type="text" class="form-control<?= invalid('address') ?>" id="address" name="address" value="<?= e($val('address')) ?>" maxlength="255" placeholder="For invoices (optional)">
              <?= $err('address') ?>
            </div>
            <div class="col-12">
              <label class="form-label" for="notes">Notes</label>
              <textarea class="form-control<?= invalid('notes') ?>" id="notes" name="notes" rows="4" data-counter="5000" placeholder="Food preferences, health needs, who they travel with…"><?= e($val('notes')) ?></textarea>
              <?= $err('notes') ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">More</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="source">Came from</label>
            <select id="source" name="source" class="form-select<?= invalid('source') ?>">
              <?php foreach (config('crm')['sources'] ?? [] as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $source === $k ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('source') ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="tags">Tags</label>
            <input type="text" class="form-control<?= invalid('tags') ?>" id="tags" name="tags" value="<?= e($val('tags')) ?>" maxlength="255" placeholder="e.g. vip, honeymoon, repeat">
            <?= $err('tags') ?>
            <div class="form-hint">Separate with commas.<?php if ($tags !== []): ?> In use: <?= e(implode(', ', array_slice($tags, 0, 12))) ?><?php endif; ?></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="date_of_birth">Birthday</label>
            <input type="date" class="form-control<?= invalid('date_of_birth') ?>" id="date_of_birth" name="date_of_birth" value="<?= e($val('date_of_birth')) ?>" max="<?= e(date('Y-m-d')) ?>">
            <?= $err('date_of_birth') ?>
          </div>
          <div>
            <label class="form-label" for="anniversary">Anniversary</label>
            <input type="date" class="form-control<?= invalid('anniversary') ?>" id="anniversary" name="anniversary" value="<?= e($val('anniversary')) ?>" max="<?= e(date('Y-m-d')) ?>">
            <?= $err('anniversary') ?>
            <div class="form-hint">Send wishes — and offers — on the day.</div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Add customer' ?></button>
          <a href="<?= e(url($isEdit ? '/customers/' . $c['id'] : '/customers')) ?>" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
