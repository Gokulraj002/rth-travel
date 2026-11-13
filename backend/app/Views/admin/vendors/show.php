<?php
/**
 * Vendor profile + service history.
 * @var array<string,mixed> $vendor @var list<array<string,mixed>> $services
 */
use App\Repositories\BookingServiceRepository;
use App\Repositories\VendorRepository;

$vid = (int) $vendor['id'];
$rating = max(0, min(5, (int) $vendor['rating']));
$payable = (float) $vendor['payable'];
$phoneTel = preg_replace('/[^0-9+]/', '', (string) $vendor['phone']);
$canBookings = feature('bookings') && can('bookings');
$canProfit = feature('profit') && can('profit');
$active = (int) $vendor['is_active'] === 1;
?>
<div class="rth-ops" data-ops-page="vendor-show">
  <?php if (!$active): ?>
    <div class="alert alert-secondary" role="status">This vendor is <strong>inactive</strong> — it is hidden from pick lists on new bookings. Its history is kept below.</div>
  <?php endif; ?>

  <div class="row row-deck row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Outstanding payable</div>
        <div class="h2 m-0 <?= $payable > 0.004 ? 'text-orange' : 'text-success' ?>"><?= e($payable > 0.004 ? inr($payable) : ($payable < -0.004 ? 'Advance ' . inr(abs($payable)) : 'All paid')) ?></div>
      </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Total business given</div>
        <div class="h2 m-0"><?= e(inr($vendor['total_cost'])) ?></div>
      </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Paid so far</div>
        <div class="h2 m-0"><?= e(inr($vendor['total_paid'])) ?></div>
      </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Bookings served</div>
        <div class="h2 m-0"><?= e((int) $vendor['bookings_served']) ?></div>
      </div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><?= icon(VendorRepository::TYPE_ICONS[$vendor['type']] ?? 'briefcase', 'icon me-1') ?> Profile</h3>
          <div class="card-actions">
            <form method="post" action="<?= e(url('/vendors/' . $vid . '/toggle')) ?>" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="back" value="/vendors/<?= e($vid) ?>">
              <button type="submit" class="btn btn-sm <?= $active ? 'btn-outline-success' : 'btn-outline-secondary' ?>"><?= $active ? icon('check', 'icon icon-sm') . ' Active' : 'Inactive — activate' ?></button>
            </form>
          </div>
        </div>
        <div class="card-body">
          <dl class="rth-ops-dl">
            <dt>Type</dt><dd><?= e(VendorRepository::typeLabel((string) $vendor['type'])) ?></dd>
            <dt>Rating</dt>
            <dd>
              <?php if ($rating > 0): ?>
                <span class="text-warning" aria-hidden="true"><?= str_repeat('★', $rating) ?></span><span class="text-muted" aria-hidden="true"><?= str_repeat('☆', 5 - $rating) ?></span>
                <span class="visually-hidden"><?= e($rating) ?> out of 5 stars</span>
              <?php else: ?><span class="text-secondary">Not rated</span><?php endif; ?>
            </dd>
            <dt>Contact</dt><dd><?= $vendor['contact_person'] ? e($vendor['contact_person']) : '<span class="text-secondary">—</span>' ?></dd>
            <dt>Phone</dt>
            <dd>
              <?php if ($vendor['phone']): ?>
                <?= e($vendor['phone']) ?>
                <div class="d-flex gap-2 mt-1">
                  <a href="tel:<?= e($phoneTel) ?>" class="btn btn-sm btn-outline-secondary"><?= icon('phone', 'icon icon-sm') ?> Call</a>
                  <a href="<?= e(wa_link((string) $vendor['phone'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><?= icon('whatsapp', 'icon icon-sm') ?> WhatsApp</a>
                </div>
              <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
            </dd>
            <dt>Email</dt><dd><?= $vendor['email'] ? '<a href="mailto:' . e($vendor['email']) . '">' . e($vendor['email']) . '</a>' : '<span class="text-secondary">—</span>' ?></dd>
            <dt>City</dt><dd><?= $vendor['city'] ? e($vendor['city']) : '<span class="text-secondary">—</span>' ?></dd>
            <dt>Address</dt><dd><?= $vendor['address'] ? e($vendor['address']) : '<span class="text-secondary">—</span>' ?></dd>
            <dt>GSTIN</dt><dd><?= $vendor['gstin'] ? e($vendor['gstin']) : '<span class="text-secondary">—</span>' ?></dd>
            <dt>Bank / UPI</dt><dd class="rth-ops-pre"><?= $vendor['bank_details'] ? e($vendor['bank_details']) : '<span class="text-secondary">—</span>' ?></dd>
          </dl>
          <?php if ($vendor['notes']): ?>
            <div class="mt-3">
              <div class="text-secondary small mb-1">Notes</div>
              <div class="rth-ops-pre"><?= e($vendor['notes']) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Services history</h3>
          <div class="card-actions text-secondary small"><?= e(count($services)) ?> service<?= count($services) === 1 ? '' : 's' ?></div>
        </div>
        <?php if ($services === []): ?>
          <div class="empty">
            <div class="empty-icon"><?= icon('list') ?></div>
            <p class="empty-title">Not used on any booking yet</p>
            <p class="empty-subtitle text-secondary">Open a booking and add a cost under “Costs &amp; profit” — pick <?= e($vendor['name']) ?> as the vendor and it will show up here.</p>
            <?php if ($canBookings): ?>
              <div class="empty-action"><a href="<?= e(url('/bookings')) ?>" class="btn btn-outline-primary"><?= icon('briefcase') ?> Go to bookings</a></div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-vcenter card-table rth-ops-table">
              <thead>
                <tr>
                  <th>Booking</th>
                  <th>Date</th>
                  <th>Service</th>
                  <th class="text-end">Cost</th>
                  <th class="text-end">Paid</th>
                  <th class="text-end">Due</th>
                  <th>Conf. no.</th>
                  <?php if ($canProfit): ?><th class="w-1"><span class="visually-hidden">Actions</span></th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($services as $s):
                  $cancelled = $s['status'] === 'cancelled';
                  $due = $cancelled ? 0.0 : (float) $s['cost_price'] - (float) $s['paid_to_vendor'];
                ?>
                  <tr class="<?= $cancelled ? 'rth-ops-cancelled' : '' ?>">
                    <td class="text-nowrap">
                      <?php if ($canBookings): ?>
                        <a href="<?= e(url('/bookings/' . (int) $s['booking_id'])) ?>" class="fw-semibold"><?= e($s['booking_number']) ?></a>
                      <?php else: ?>
                        <span class="fw-semibold"><?= e($s['booking_number']) ?></span>
                      <?php endif; ?>
                      <div class="small text-secondary"><?= e(str_limit((string) $s['booking_title'], 34)) ?></div>
                    </td>
                    <td class="text-nowrap"><?= e(nice_day($s['service_date'] ?: $s['travel_start'])) ?><?= $s['nights'] ? '<div class="small text-secondary">' . e((int) $s['nights']) . ' night' . ((int) $s['nights'] === 1 ? '' : 's') . '</div>' : '' ?></td>
                    <td>
                      <?= e($s['description']) ?>
                      <div class="small"><?= partial('admin/booking-services/_status', ['status' => (string) $s['status']]) ?> <span class="text-secondary"><?= e(BookingServiceRepository::typeLabel((string) $s['service_type'])) ?></span></div>
                    </td>
                    <td class="text-end text-nowrap"><?= e(inr($s['cost_price'])) ?></td>
                    <td class="text-end text-nowrap"><?= e(inr($s['paid_to_vendor'])) ?></td>
                    <td class="text-end text-nowrap"><?= $due > 0.004 ? '<span class="fw-semibold text-orange">' . e(inr($due)) . '</span>' : '<span class="text-success">' . ($cancelled ? '—' : 'Paid') . '</span>' ?></td>
                    <td><?= $s['confirmation_no'] ? e($s['confirmation_no']) : '<span class="text-secondary">—</span>' ?></td>
                    <?php if ($canProfit): ?>
                      <td>
                        <div class="d-flex gap-1 justify-content-end">
                          <?php if ($due > 0.004): ?>
                            <form method="post" action="<?= e(url('/booking-services/' . (int) $s['id'] . '/paid')) ?>" class="m-0"
                                  data-confirm="Mark <?= e(inr($due)) ?> as paid to <?= e($vendor['name']) ?>?">
                              <?= csrf_field() ?>
                              <input type="hidden" name="return" value="vendor">
                              <button type="submit" class="btn btn-sm btn-outline-success text-nowrap" title="Set paid = cost"><?= icon('check', 'icon icon-sm') ?> Paid</button>
                            </form>
                          <?php endif; ?>
                          <a href="<?= e(url('/booking-services/' . (int) $s['id'] . '/edit?return=vendor')) ?>" class="btn btn-sm btn-icon btn-ghost-primary" title="Edit" aria-label="Edit service"><?= icon('edit') ?></a>
                        </div>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="fw-semibold">
                  <td colspan="3" class="text-end">Total (excluding cancelled)</td>
                  <td class="text-end text-nowrap"><?= e(inr($vendor['total_cost'])) ?></td>
                  <td class="text-end text-nowrap"><?= e(inr($vendor['total_paid'])) ?></td>
                  <td class="text-end text-nowrap <?= $payable > 0.004 ? 'text-orange' : 'text-success' ?>"><?= e($payable > 0.004 ? inr($payable) : 'Nil') ?></td>
                  <td colspan="<?= $canProfit ? 2 : 1 ?>"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
