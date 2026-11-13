<?php
/**
 * Fleet cards + upcoming duties.
 * @var array{q:string,type:string,active:string,docs:string} $f
 * @var array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int} $result
 * @var int $attention
 * @var list<array<string,mixed>> $assignments
 */
use App\Repositories\VehicleRepository;

$filtered = $f['q'] !== '' || $f['type'] !== '' || $f['active'] !== '' || $f['docs'] !== '';
$currentUrl = '/vehicles' . (($qs = http_build_query(array_filter([...$f, 'page' => $result['page'] > 1 ? $result['page'] : ''], static fn ($v) => $v !== ''))) !== '' ? '?' . $qs : '');
$canBookings = feature('bookings') && can('bookings');
$tel = static fn (?string $p): string => (string) preg_replace('/[^0-9+]/', '', (string) $p);
?>
<div class="rth-ops" data-ops-page="vehicles">
  <?php if ($attention > 0 && $f['docs'] === ''): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="status">
      <?= icon('alert') ?>
      <div class="me-auto"><strong><?= e($attention) ?> active vehicle<?= $attention === 1 ? '' : 's' ?></strong> <?= $attention === 1 ? 'has' : 'have' ?> insurance or permit expired or expiring within <?= e(VehicleRepository::SOON_DAYS) ?> days.</div>
      <a href="<?= e(url('/vehicles?docs=attention')) ?>" class="btn btn-sm btn-warning">Show <?= $attention === 1 ? 'it' : 'them' ?></a>
    </div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?= e(url('/vehicles')) ?>" class="row g-2 align-items-center" role="search">
        <div class="col-12 col-md">
          <div class="input-icon">
            <span class="input-icon-addon"><?= icon('search') ?></span>
            <input type="search" class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Search vehicle, registration, driver name or phone…" aria-label="Search vehicles">
          </div>
        </div>
        <div class="col-6 col-md-auto">
          <select class="form-select" name="type" aria-label="Filter by type">
            <option value="">All types</option>
            <?php foreach (VehicleRepository::TYPES as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= $f['type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-auto">
          <select class="form-select" name="active" aria-label="Filter by active">
            <option value="">Active &amp; inactive</option>
            <option value="1"<?= $f['active'] === '1' ? ' selected' : '' ?>>Active only</option>
            <option value="0"<?= $f['active'] === '0' ? ' selected' : '' ?>>Inactive only</option>
          </select>
        </div>
        <div class="col-12 col-md-auto">
          <label class="form-check m-0">
            <input class="form-check-input" type="checkbox" name="docs" value="attention"<?= $f['docs'] === 'attention' ? ' checked' : '' ?>>
            <span class="form-check-label">Documents need attention</span>
          </label>
        </div>
        <div class="col-12 col-md-auto d-flex gap-2">
          <button type="submit" class="btn btn-primary">Filter</button>
          <?php if ($filtered): ?><a class="btn btn-ghost-secondary" href="<?= e(url('/vehicles')) ?>">Clear</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <?php if ($result['rows'] === []): ?>
    <div class="card">
      <div class="empty">
        <div class="empty-icon"><?= icon('car') ?></div>
        <p class="empty-title"><?= $filtered ? 'No vehicles match your filters' : 'No vehicles yet' ?></p>
        <p class="empty-subtitle text-secondary">
          <?= $filtered ? 'Try another word or clear the filters.' : 'Add your own cabs and tempo travellers with their drivers. You will get a warning before insurance or permits expire, and see which trips each vehicle is booked for.' ?>
        </p>
        <?php if (!$filtered): ?>
          <div class="empty-action"><a href="<?= e(url('/vehicles/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add vehicle</a></div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="row row-cards">
      <?php foreach ($result['rows'] as $v):
        $id = (int) $v['id'];
        $active = (int) $v['is_active'] === 1;
      ?>
        <div class="col-md-6 col-xl-4">
          <div class="card rth-ops-vehicle<?= $active ? '' : ' rth-ops-inactive' ?>">
            <div class="card-body">
              <div class="d-flex align-items-start gap-3 mb-2">
                <span class="avatar bg-blue-lt"><?= icon('car') ?></span>
                <div class="flex-fill min-w-0">
                  <h3 class="card-title mb-0"><a href="<?= e(url('/vehicles/' . $id . '/edit')) ?>" class="text-reset"><?= e($v['name']) ?></a></h3>
                  <div class="text-secondary small">
                    <?= e(VehicleRepository::typeLabel((string) $v['type'])) ?> · <?= e((int) $v['seats']) ?> seats
                    <?php if ($v['registration_no']): ?> · <span class="rth-ops-reg"><?= e($v['registration_no']) ?></span><?php endif; ?>
                  </div>
                </div>
                <form method="post" action="<?= e(url('/vehicles/' . $id . '/toggle')) ?>" class="m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="back" value="<?= e($currentUrl) ?>">
                  <button type="submit" class="btn btn-sm <?= $active ? 'btn-outline-success' : 'btn-outline-secondary' ?>" title="<?= $active ? 'Click to mark inactive' : 'Click to activate' ?>">
                    <?= $active ? 'Active' : 'Inactive' ?>
                  </button>
                </form>
              </div>

              <div class="rth-ops-driver mb-2">
                <?php if ($v['driver_name'] || $v['driver_phone']): ?>
                  <div class="d-flex align-items-center gap-2">
                    <?= icon('user', 'icon text-secondary') ?>
                    <span class="fw-semibold"><?= e($v['driver_name'] ?: 'Driver') ?></span>
                    <?php if ($v['driver_phone']): ?>
                      <span class="text-secondary small"><?= e($v['driver_phone']) ?></span>
                      <span class="ms-auto d-inline-flex gap-1">
                        <a href="tel:<?= e($tel($v['driver_phone'])) ?>" class="btn btn-sm btn-icon btn-ghost-secondary" title="Call driver" aria-label="Call <?= e($v['driver_name'] ?: 'driver') ?>"><?= icon('phone') ?></a>
                        <a href="<?= e(wa_link((string) $v['driver_phone'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-ghost-success" title="WhatsApp driver" aria-label="WhatsApp <?= e($v['driver_name'] ?: 'driver') ?>"><?= icon('whatsapp') ?></a>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <span class="text-secondary small"><?= icon('user', 'icon icon-sm') ?> No driver added</span>
                <?php endif; ?>
              </div>

              <?= partial('admin/vehicles/_expiry', ['label' => 'Insurance', 'date' => $v['insurance_expiry']]) ?>
              <?= partial('admin/vehicles/_expiry', ['label' => 'Permit', 'date' => $v['permit_expiry']]) ?>
            </div>
            <div class="card-footer d-flex align-items-center gap-2">
              <span class="small text-secondary me-auto">
                <?= icon('calendar', 'icon icon-sm') ?>
                <?php if ($v['next_date']): ?>
                  Next duty <strong class="text-reset"><?= e(nice_day($v['next_date'])) ?></strong><?= (int) $v['upcoming_count'] > 1 ? ' · ' . e((int) $v['upcoming_count']) . ' upcoming' : '' ?>
                <?php else: ?>
                  Free — no upcoming trips
                <?php endif; ?>
              </span>
              <a href="<?= e(url('/vehicles/' . $id . '/edit')) ?>" class="btn btn-sm btn-icon btn-ghost-primary" title="Edit" aria-label="Edit <?= e($v['name']) ?>"><?= icon('edit') ?></a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($result['pages'] > 1 || $result['total'] > 0): ?>
      <div class="card mt-3"><?= partial('admin/destinations/_pagination', ['result' => $result, 'basePath' => '/vehicles', 'query' => $f]) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="card mt-3">
    <div class="card-header">
      <h3 class="card-title"><?= icon('calendar', 'icon me-1') ?> Next assignments</h3>
      <div class="card-actions text-secondary small">Trips from today onwards</div>
    </div>
    <?php if ($assignments === []): ?>
      <div class="card-body text-secondary">No upcoming trips use your vehicles yet. On a booking, add a <strong>Transport</strong> cost and pick a vehicle — it will appear here.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-vcenter card-table rth-ops-table">
          <thead>
            <tr><th>Date</th><th>Vehicle &amp; driver</th><th>Booking</th><th>Duty</th><th>Pickup</th></tr>
          </thead>
          <tbody>
            <?php foreach ($assignments as $a): ?>
              <tr>
                <td class="text-nowrap">
                  <strong><?= e(nice_day($a['service_date'])) ?></strong>
                  <?php if ($a['nights']): ?><div class="small text-secondary">for <?= e((int) $a['nights'] + 1) ?> days</div><?php endif; ?>
                </td>
                <td>
                  <?= e($a['vehicle_name']) ?><?= $a['registration_no'] ? ' <span class="text-secondary small">' . e($a['registration_no']) . '</span>' : '' ?>
                  <?php if ($a['driver_name']): ?><div class="small text-secondary"><?= e($a['driver_name']) ?><?= $a['driver_phone'] ? ' · <a href="tel:' . e($tel($a['driver_phone'])) . '">' . e($a['driver_phone']) . '</a>' : '' ?></div><?php endif; ?>
                </td>
                <td>
                  <?php if ($canBookings): ?><a href="<?= e(url('/bookings/' . (int) $a['booking_id'])) ?>" class="fw-semibold"><?= e($a['booking_number']) ?></a><?php else: ?><span class="fw-semibold"><?= e($a['booking_number']) ?></span><?php endif; ?>
                  <div class="small text-secondary"><?= e($a['customer_name'] ?? '') ?><?= $a['customer_name'] ? ' · ' : '' ?><?= e((int) $a['adults'] + (int) $a['children']) ?> pax</div>
                </td>
                <td><?= e(str_limit((string) $a['description'], 60)) ?> <?= partial('admin/booking-services/_status', ['status' => (string) $a['status']]) ?></td>
                <td><?= $a['pickup_point'] ? e($a['pickup_point']) : '<span class="text-secondary">—</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
