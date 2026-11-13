<?php
/**
 * Add / edit vehicle.
 * @var array<string,mixed>|null $vehicle @var list<array<string,mixed>> $assignments @var int $usage
 */
use App\Repositories\VehicleRepository;

$r = $vehicle ?? [];
$isEdit = $vehicle !== null;
$action = $isEdit ? url('/vehicles/' . $r['id']) : url('/vehicles');
$val = static fn (string $k, mixed $default = ''): string => (string) old($k, $r[$k] ?? $default);
$fieldError = static fn (string $k): string => ($err = error_for($k)) ? '<div class="invalid-feedback">' . e($err) . '</div>' : '';
$type = $val('type', 'sedan');
$hasOld = old('name', null) !== null;
$active = $hasOld ? old('is_active', '') !== '' : (int) ($r['is_active'] ?? 1) === 1;
$canBookings = feature('bookings') && can('bookings');
?>
<form method="post" action="<?= e($action) ?>" novalidate class="rth-ops">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Vehicle</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required" for="name">Vehicle name</label>
              <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" placeholder="e.g. Tempo Traveller 1, Innova Crysta (white)" required>
              <?= $fieldError('name') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label required" for="type">Type</label>
              <select class="form-select<?= invalid('type') ?>" id="type" name="type">
                <?php foreach (VehicleRepository::TYPES as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= $fieldError('type') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="registration_no">Registration number</label>
              <input type="text" class="form-control text-uppercase<?= invalid('registration_no') ?>" id="registration_no" name="registration_no" value="<?= e($val('registration_no')) ?>" maxlength="30" placeholder="e.g. JK01 AB 1234">
              <?= $fieldError('registration_no') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label required" for="seats">Seats (passengers)</label>
              <input type="number" class="form-control<?= invalid('seats') ?>" id="seats" name="seats" value="<?= e($val('seats', 4)) ?>" min="1" max="80" required>
              <?= $fieldError('seats') ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title">Driver</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="driver_name">Driver name</label>
              <input type="text" class="form-control<?= invalid('driver_name') ?>" id="driver_name" name="driver_name" value="<?= e($val('driver_name')) ?>" maxlength="120" placeholder="e.g. Bashir Ahmad">
              <?= $fieldError('driver_name') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="driver_phone">Driver phone / WhatsApp</label>
              <input type="tel" class="form-control<?= invalid('driver_phone') ?>" id="driver_phone" name="driver_phone" value="<?= e($val('driver_phone')) ?>" maxlength="30" placeholder="e.g. 70060 12345">
              <?= $fieldError('driver_phone') ?>
            </div>
            <div class="col-12">
              <label class="form-label" for="notes">Notes</label>
              <textarea class="form-control<?= invalid('notes') ?>" id="notes" name="notes" rows="3" maxlength="2000" data-counter="2000" placeholder="Service due, carrier on roof, languages the driver speaks…"><?= e($val('notes')) ?></textarea>
              <?= $fieldError('notes') ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Documents</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="insurance_expiry">Insurance valid till</label>
            <input type="date" class="form-control<?= invalid('insurance_expiry') ?>" id="insurance_expiry" name="insurance_expiry" value="<?= e(substr($val('insurance_expiry'), 0, 10)) ?>">
            <?= $fieldError('insurance_expiry') ?>
          </div>
          <div class="mb-2">
            <label class="form-label" for="permit_expiry">Tourist permit valid till</label>
            <input type="date" class="form-control<?= invalid('permit_expiry') ?>" id="permit_expiry" name="permit_expiry" value="<?= e(substr($val('permit_expiry'), 0, 10)) ?>">
            <?= $fieldError('permit_expiry') ?>
          </div>
          <div class="form-hint">You will see an orange badge <?= e(VehicleRepository::SOON_DAYS) ?> days before a document expires, and red once it has expired.</div>
          <?php if ($isEdit): ?>
            <div class="mt-3">
              <?= partial('admin/vehicles/_expiry', ['label' => 'Insurance', 'date' => $r['insurance_expiry']]) ?>
              <?= partial('admin/vehicles/_expiry', ['label' => 'Permit', 'date' => $r['permit_expiry']]) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"<?= $active ? ' checked' : '' ?>>
            <span class="form-check-label">Active — offer this vehicle on bookings</span>
          </label>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Add vehicle' ?></button>
          <a href="<?= e(url('/vehicles')) ?>" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php if ($isEdit): ?>
  <div class="card mt-3">
    <div class="card-header"><h3 class="card-title"><?= icon('calendar', 'icon me-1') ?> Next assignments</h3></div>
    <?php if ($assignments === []): ?>
      <div class="card-body text-secondary">No upcoming trips for this vehicle.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Date</th><th>Booking</th><th>Duty</th><th>Pickup</th></tr></thead>
          <tbody>
            <?php foreach ($assignments as $a): ?>
              <tr>
                <td class="text-nowrap"><strong><?= e(nice_day($a['service_date'])) ?></strong><?= $a['nights'] ? '<div class="small text-secondary">for ' . e((int) $a['nights'] + 1) . ' days</div>' : '' ?></td>
                <td>
                  <?php if ($canBookings): ?><a href="<?= e(url('/bookings/' . (int) $a['booking_id'])) ?>"><?= e($a['booking_number']) ?></a><?php else: ?><?= e($a['booking_number']) ?><?php endif; ?>
                  <div class="small text-secondary"><?= e($a['customer_name'] ?? '') ?></div>
                </td>
                <td><?= e(str_limit((string) $a['description'], 60)) ?></td>
                <td><?= $a['pickup_point'] ? e($a['pickup_point']) : '<span class="text-secondary">—</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card mt-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="me-auto">
        <div class="fw-semibold">Delete this vehicle</div>
        <div class="text-secondary small">
          <?= $usage > 0
            ? 'This vehicle is used on ' . e($usage) . ' booking service' . ($usage === 1 ? '' : 's') . ', so it can’t be deleted. Switch off “Active” instead.'
            : 'Only possible while it has never been assigned to a booking.' ?>
        </div>
      </div>
      <form method="post" action="<?= e(url('/vehicles/' . $r['id'])) ?>" class="m-0" data-confirm="Delete <?= e($r['name']) ?>? This cannot be undone.">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-outline-danger"<?= $usage > 0 ? ' disabled' : '' ?>><?= icon('trash') ?> Delete vehicle</button>
      </form>
    </div>
  </div>
<?php endif; ?>
