<?php
/**
 * "Costs & profit" card for a booking — include on /bookings/{id}:
 *   <?php if (feature('profit') && can('profit') && is_file(APP_PATH . '/Views/admin/booking-services/panel.php'))
 *         echo partial('admin/booking-services/panel', ['booking' => $booking]); ?>
 *
 * @var array<string,mixed> $booking   row from the bookings table
 * @var string|null $returnTo           'preview' → actions come back to /booking-services/preview/{id} (default: /bookings/{id})
 */
use App\Repositories\BookingServiceRepository;

$bookingId = (int) ($booking['id'] ?? 0);
$returnTo = ($returnTo ?? '') === 'preview' ? 'preview' : '';
$services = BookingServiceRepository::forBooking($bookingId);
$t = BookingServiceRepository::totals($booking, $services);
$canVendors = feature('vendors') && can('vendors');
$canVehicles = feature('vehicles') && can('vendors');
// Re-open the add form (with the typed values) when it came back with validation errors.
$addSubmitted = array_filter(array_keys(\App\Core\Flash::errors()), static fn ($k) => str_starts_with((string) $k, 'svc_')) !== [];
$returnField = $returnTo !== '' ? '<input type="hidden" name="return" value="' . e($returnTo) . '">' : '';
$editQs = $returnTo !== '' ? '?return=' . $returnTo : '';
$profitClass = $t['profit'] >= 0 ? 'text-success' : 'text-danger';
?>
<div class="card rth-ops-panel" id="booking-costs">
  <div class="card-header">
    <h3 class="card-title"><?= icon('wallet', 'icon me-1') ?> Costs &amp; profit</h3>
    <div class="card-actions text-secondary small d-none d-sm-block">Only you and staff with “profit” access can see this.</div>
  </div>

  <div class="card-body border-bottom">
    <div class="row g-3 rth-ops-totals">
      <div class="col-6 col-md-3">
        <div class="text-secondary small">Selling price</div>
        <div class="h3 m-0"><?= e(inr($t['selling'])) ?></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-secondary small">Total cost</div>
        <div class="h3 m-0"><?= e(inr($t['cost'])) ?></div>
        <div class="small text-secondary"><?= e($t['count']) ?> service<?= $t['count'] === 1 ? '' : 's' ?><?= $t['cancelled'] > 0 ? ' · ' . e($t['cancelled']) . ' cancelled not counted' : '' ?></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-secondary small">Profit</div>
        <div class="h3 m-0 <?= $profitClass ?>">
          <?= $t['profit'] < 0 ? '−' : '' ?><?= e(inr(abs($t['profit']))) ?>
          <?= icon($t['profit'] >= 0 ? 'trending-up' : 'trending-down', 'icon icon-sm') ?>
        </div>
        <div class="small <?= $profitClass ?>"><?= $t['margin'] !== null ? ($t['margin'] < 0 ? '−' : '') . e(number_format(abs($t['margin']), 1)) . '% margin' : 'Set the booking total to see margin' ?></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-secondary small">Vendor payable</div>
        <div class="h3 m-0 <?= $t['payable'] > 0.004 ? 'text-orange' : 'text-success' ?>"><?= e($t['payable'] > 0.004 ? inr($t['payable']) : 'All paid') ?></div>
        <div class="small text-secondary">Paid so far <?= e(inr($t['paid'])) ?></div>
      </div>
    </div>
  </div>

  <?php if ($services === []): ?>
    <div class="card-body text-secondary">
      No costs added yet. Add the hotel, houseboat, cab and other supplier costs for this trip to see the real profit.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-vcenter card-table rth-ops-table rth-ops-services">
        <thead>
          <tr>
            <th>Service</th>
            <th>Vendor / vehicle</th>
            <th>Date</th>
            <th class="text-end">Cost</th>
            <th class="text-end">Paid</th>
            <th>Status</th>
            <th class="w-1"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($services as $s):
            $sid = (int) $s['id'];
            $cancelled = $s['status'] === 'cancelled';
            $due = (float) $s['cost_price'] - (float) $s['paid_to_vendor'];
          ?>
            <tr class="<?= $cancelled ? 'rth-ops-cancelled' : '' ?>">
              <td>
                <div class="fw-semibold"><?= e($s['description']) ?></div>
                <div class="small text-secondary">
                  <?= e(BookingServiceRepository::typeLabel((string) $s['service_type'])) ?>
                  <?php if ($s['confirmation_no']): ?> · Conf. <?= e($s['confirmation_no']) ?><?php endif; ?>
                  <?php if ($s['notes']): ?> · <?= e(str_limit((string) $s['notes'], 60)) ?><?php endif; ?>
                </div>
              </td>
              <td>
                <?php if ($s['vendor_id']): ?>
                  <?php if ($canVendors): ?><a href="<?= e(url('/vendors/' . (int) $s['vendor_id'])) ?>"><?= e($s['vendor_name']) ?></a><?php else: ?><?= e($s['vendor_name']) ?><?php endif; ?>
                  <?php if ($s['vendor_phone']): ?>
                    <a href="<?= e(wa_link((string) $s['vendor_phone'])) ?>" target="_blank" rel="noopener" class="text-success ms-1" title="WhatsApp <?= e($s['vendor_name']) ?>" aria-label="WhatsApp <?= e($s['vendor_name']) ?>"><?= icon('whatsapp', 'icon icon-sm') ?></a>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($s['vehicle_id']): ?>
                  <div class="small">
                    <?= icon('car', 'icon icon-sm text-secondary') ?>
                    <?php if ($canVehicles): ?><a href="<?= e(url('/vehicles/' . (int) $s['vehicle_id'] . '/edit')) ?>" class="text-reset"><?= e($s['vehicle_name']) ?></a><?php else: ?><?= e($s['vehicle_name']) ?><?php endif; ?>
                    <?php if ($s['driver_name']): ?><span class="text-secondary"> · <?= e($s['driver_name']) ?></span><?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if (!$s['vendor_id'] && !$s['vehicle_id']): ?><span class="text-secondary">—</span><?php endif; ?>
              </td>
              <td class="text-nowrap">
                <?= e(nice_day($s['service_date'])) ?>
                <?php if ($s['nights']): ?><div class="small text-secondary"><?= e((int) $s['nights']) ?> night<?= (int) $s['nights'] === 1 ? '' : 's' ?></div><?php endif; ?>
              </td>
              <td class="text-end text-nowrap fw-semibold"><?= e(inr($s['cost_price'])) ?></td>
              <td class="text-end text-nowrap">
                <?= e(inr($s['paid_to_vendor'])) ?>
                <?php if (!$cancelled && $due > 0.004): ?><div class="small text-orange"><?= e(inr($due)) ?> due</div><?php endif; ?>
              </td>
              <td><?= partial('admin/booking-services/_status', ['status' => (string) $s['status']]) ?></td>
              <td>
                <div class="d-flex gap-1 justify-content-end">
                  <?php if (!$cancelled && $due > 0.004): ?>
                    <form method="post" action="<?= e(url('/booking-services/' . $sid . '/paid')) ?>" class="m-0"
                          data-confirm="Mark <?= e(inr($due)) ?> as paid<?= $s['vendor_name'] ? ' to ' . e($s['vendor_name']) : '' ?>?">
                      <?= csrf_field() ?><?= $returnField ?>
                      <button type="submit" class="btn btn-sm btn-outline-success text-nowrap" title="Paid to vendor = cost"><?= icon('check', 'icon icon-sm') ?> Paid</button>
                    </form>
                  <?php endif; ?>
                  <a href="<?= e(url('/booking-services/' . $sid . '/edit' . $editQs)) ?>" class="btn btn-sm btn-icon btn-ghost-primary" title="Edit" aria-label="Edit <?= e($s['description']) ?>"><?= icon('edit') ?></a>
                  <form method="post" action="<?= e(url('/booking-services/' . $sid)) ?>" class="m-0" data-confirm="Remove “<?= e($s['description']) ?>” from this booking?">
                    <?= csrf_field() ?><?= method_field('DELETE') ?><?= $returnField ?>
                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Remove" aria-label="Remove <?= e($s['description']) ?>"><?= icon('trash') ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-semibold">
            <td colspan="3" class="text-end">Total (not counting cancelled)</td>
            <td class="text-end text-nowrap"><?= e(inr($t['cost'])) ?></td>
            <td class="text-end text-nowrap"><?= e(inr($t['paid'])) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>

  <div class="card-footer">
    <details class="rth-ops-add"<?= $addSubmitted ? ' open' : '' ?>>
      <summary class="btn btn-outline-primary"><?= icon('plus') ?> Add service / cost</summary>
      <form method="post" action="<?= e(url('/bookings/' . $bookingId . '/services')) ?>" class="mt-3" novalidate>
        <?= csrf_field() ?><?= $returnField ?>
        <?= partial('admin/booking-services/_fields', ['svc' => ['service_date' => $booking['travel_start'] ?? ''], 'idp' => 'svc-add-' . $bookingId . '-', 'useOld' => $addSubmitted]) ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> Add to booking</button>
          <?php if ($canVendors): ?><a href="<?= e(url('/vendors/payables')) ?>" class="btn btn-ghost-secondary ms-auto"><?= icon('wallet') ?> All vendor payables</a><?php endif; ?>
        </div>
      </form>
    </details>
  </div>
</div>
