<?php
/**
 * Booking detail page.
 * @var array<string,mixed> $booking
 * @var list<array<string,mixed>> $travellers
 * @var list<array<string,mixed>> $payments
 * @var bool $canPay
 * @var int $paymentCount
 */
use App\Core\Template;
use App\Repositories\BookingRepository as Bk;
use App\Repositories\PaymentRepository as Pay;

$b = $booking;
$bid = (int) $b['id'];
$total = (float) $b['total_amount'];
$paid = (float) $b['paid'];
$balance = $total - $paid;
$pct = $total > 0 ? (int) min(100, round($paid / $total * 100)) : ($paid > 0 ? 100 : 0);
$hint = Bk::travelHint($b);
$cancelled = $b['status'] === 'cancelled';
$phone = (string) $b['customer_phone'];
$tel = 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
$payFallback = 'Hi {{customer_name}}, we received {{payment_amount}} for booking {{booking_number}} (receipt {{receipt_no}}). Balance due: {{balance}}. Thank you! — {{company_name}}';
$amountDefault = $balance > 0.009 ? number_format($balance, 2, '.', '') : '';
?>
<div class="rth-bookings rth-bk-show">

  <!-- Summary strip -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-center">
        <div class="col-lg-5">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="fw-bold fs-3"><?= e($b['number']) ?></span>
            <?= status_badge((string) $b['status'], Bk::statusLabel((string) $b['status'])) ?>
            <?php if ($hint['text'] !== ''): ?><span class="rth-bk-hint is-<?= e($hint['tone']) ?>"><?= e($hint['text']) ?></span><?php endif; ?>
          </div>
          <div class="text-secondary">
            <?= icon('calendar', 'icon icon-sm') ?> <?= e(Bk::dateRange((string) $b['travel_start'], (string) $b['travel_end'])) ?> · <?= e(Bk::duration($b)) ?>
            · <?= icon('users', 'icon icon-sm') ?> <?= e(Bk::people($b)) ?>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="d-flex justify-content-between small mb-1">
            <span>Paid <strong><?= e(inr($paid)) ?></strong> of <?= e(inr($total)) ?></span>
            <span class="<?= $balance > 0.009 && !$cancelled ? 'text-danger fw-semibold' : 'text-success' ?>">
              <?= $balance > 0.009 ? 'Balance ' . e(inr($balance)) : ($balance < -0.009 ? 'Over-paid ' . e(inr(-$balance)) : 'Fully paid') ?>
            </span>
          </div>
          <div class="progress progress-sm rth-bk-progress" role="progressbar" aria-label="Amount paid" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar <?= $pct >= 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $pct ?>%"></div>
          </div>
        </div>
        <div class="col-lg-3">
          <form method="post" action="<?= e(url('/bookings/' . $bid . '/status')) ?>" class="d-flex gap-2" aria-label="Change status">
            <?= csrf_field() ?>
            <select name="status" class="form-select form-select-sm" aria-label="Booking status">
              <?php foreach (Bk::STATUSES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $b['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm">Update</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php if ($cancelled): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2"><?= icon('alert') ?><div>This booking is <strong>cancelled</strong>. It is hidden from the calendar. Any payments received stay on record — handle refunds outside the system and note them below.</div></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">

      <!-- Trip -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('map-pin') ?> Trip details</h3></div>
        <div class="card-body">
          <dl class="row mb-0 rth-bk-dl">
            <dt class="col-sm-4">Trip</dt><dd class="col-sm-8"><?= e($b['title']) ?></dd>
            <?php if (!empty($b['package_title'])): ?><dt class="col-sm-4">Package</dt><dd class="col-sm-8"><?= e($b['package_title']) ?></dd><?php endif; ?>
            <dt class="col-sm-4">Travel dates</dt><dd class="col-sm-8"><?= e(nice_day((string) $b['travel_start'])) ?> → <?= e(nice_day((string) $b['travel_end'])) ?> <span class="text-secondary">(<?= e(Bk::duration($b)) ?>)</span></dd>
            <dt class="col-sm-4">Travellers</dt><dd class="col-sm-8"><?= e(Bk::people($b)) ?></dd>
            <dt class="col-sm-4">Pickup</dt><dd class="col-sm-8"><?= e($b['pickup_point'] ?: '—') ?></dd>
            <dt class="col-sm-4">Drop</dt><dd class="col-sm-8"><?= e($b['drop_point'] ?: '—') ?></dd>
            <?php if (!empty($b['special_requests'])): ?><dt class="col-sm-4">Special requests</dt><dd class="col-sm-8 rth-bk-pre"><?= e($b['special_requests']) ?></dd><?php endif; ?>
            <?php if (!empty($b['quotation_id'])): ?>
              <dt class="col-sm-4">From quotation</dt>
              <dd class="col-sm-8">
                <?php if (feature('quotations') && can('quotations')): ?>
                  <a href="<?= e(url('/quotations/' . (int) $b['quotation_id'])) ?>"><?= icon('file-text', 'icon icon-sm') ?> <?= e($b['quotation_number'] ?: 'Quotation #' . (int) $b['quotation_id']) ?></a>
                <?php else: ?><?= e($b['quotation_number'] ?: '—') ?><?php endif; ?>
              </dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>

      <!-- Travellers -->
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><?= icon('users') ?> Travellers <span class="badge bg-secondary-lt ms-1"><?= count($travellers) ?></span></h3>
          <div class="card-actions"><a href="<?= e(url('/bookings/' . $bid . '/edit')) ?>" class="btn btn-sm"><?= icon('edit') ?> Edit</a></div>
        </div>
        <?php if ($travellers === []): ?>
          <div class="card-body text-secondary small">No traveller names added yet. <a href="<?= e(url('/bookings/' . $bid . '/edit')) ?>">Add travellers</a> so they appear on the voucher.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th class="w-1">#</th><th>Name</th><th>Age</th><th>Gender</th><th>Phone</th><th>ID proof</th></tr></thead>
              <tbody>
                <?php foreach ($travellers as $i => $t): ?>
                  <tr>
                    <td class="text-secondary"><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($t['name']) ?></td>
                    <td><?= $t['age'] !== null ? (int) $t['age'] : '—' ?></td>
                    <td><?= e(Bk::GENDERS[(string) $t['gender']] ?? '—') ?></td>
                    <td class="text-nowrap"><?= !empty($t['phone']) ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', (string) $t['phone'])) . '">' . e($t['phone']) . '</a>' : '—' ?></td>
                    <td class="text-secondary"><?= e($t['id_proof'] ?: '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Payments -->
      <?php if ($canPay): ?>
        <div class="card mb-3" id="payments">
          <div class="card-header">
            <h3 class="card-title"><?= icon('cash') ?> Payments <span class="badge bg-secondary-lt ms-1"><?= count($payments) ?></span></h3>
            <div class="card-actions small text-secondary">Received <strong class="text-body"><?= e(inr2($paid)) ?></strong> of <?= e(inr2($total)) ?></div>
          </div>
          <div class="card-body border-bottom">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="progress rth-bk-progress is-lg flex-fill" role="progressbar" aria-label="Amount paid" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar <?= $pct >= 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $pct ?>%"></div>
              </div>
              <span class="small fw-semibold"><?= $pct ?>% paid</span>
            </div>
            <div class="row text-center g-2 rth-bk-money">
              <div class="col-4"><div class="small text-secondary">Total</div><div class="fw-bold"><?= e(inr($total)) ?></div></div>
              <div class="col-4"><div class="small text-secondary">Paid</div><div class="fw-bold text-success"><?= e(inr($paid)) ?></div></div>
              <div class="col-4"><div class="small text-secondary"><?= $balance < -0.009 ? 'Over-paid' : 'Balance' ?></div><div class="fw-bold <?= $balance > 0.009 ? 'text-danger' : '' ?>"><?= e(inr(abs($balance))) ?></div></div>
            </div>
          </div>

          <?php if ($payments !== []): ?>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead><tr><th>Date</th><th>Receipt</th><th>Method</th><th class="text-end">Amount</th><th class="text-end">Balance after</th><th class="w-1"></th></tr></thead>
                <tbody>
                  <?php foreach ($payments as $p):
                      $msg = Template::message('Payment received', Pay::messageVars([...$p, 'customer_name' => $b['customer_name'], 'booking_number' => $b['number'], 'booking_title' => $b['title'], 'total_amount' => $b['total_amount']], (float) $p['balance_after']), $payFallback); ?>
                    <tr>
                      <td class="text-nowrap"><?= e(nice_day((string) $p['paid_at'])) ?></td>
                      <td class="text-nowrap">
                        <span class="fw-semibold"><?= e($p['receipt_no']) ?></span>
                        <?php if (!empty($p['notes'])): ?><div class="small text-secondary"><?= e(str_limit((string) $p['notes'], 40)) ?></div><?php endif; ?>
                      </td>
                      <td>
                        <?= e(Pay::methodLabel($p['method'])) ?>
                        <?php if (!empty($p['reference'])): ?><div class="small text-secondary text-break"><?= e($p['reference']) ?></div><?php endif; ?>
                      </td>
                      <td class="text-end text-nowrap fw-semibold"><?= e(inr2($p['amount'])) ?></td>
                      <td class="text-end text-nowrap text-secondary"><?= e(inr(max(0, (float) $p['balance_after']))) ?></td>
                      <td class="text-nowrap">
                        <div class="d-flex gap-1 rth-bk-actions">
                          <a href="<?= e(url('/payments/' . $p['id'] . '/receipt')) ?>" class="btn btn-sm btn-icon" title="Print receipt" aria-label="Print receipt <?= e($p['receipt_no']) ?>"><?= icon('printer') ?></a>
                          <a href="<?= e(wa_link($phone, $msg)) ?>" class="btn btn-sm btn-icon text-green" target="_blank" rel="noopener" title="Send “payment received” on WhatsApp" aria-label="Send payment received message on WhatsApp"><?= icon('whatsapp') ?></a>
                          <form method="post" action="<?= e(url('/payments/' . $p['id'])) ?>" class="m-0" data-confirm="Delete payment <?= e($p['receipt_no']) ?> of <?= e(inr2($p['amount'])) ?>? This cannot be undone.">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Delete payment" aria-label="Delete payment <?= e($p['receipt_no']) ?>"><?= icon('trash') ?></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <div class="card-body rth-bk-payform">
            <h4 class="mb-3"><?= icon('plus') ?> Record a payment</h4>
            <form method="post" action="<?= e(url('/bookings/' . $bid . '/payments')) ?>" data-bk-payform data-balance="<?= e(number_format(max(0, $balance), 2, '.', '')) ?>">
              <?= csrf_field() ?>
              <div class="row g-2">
                <div class="col-6 col-md-3">
                  <label class="form-label required" for="pay-amount">Amount (₹)</label>
                  <input type="number" id="pay-amount" name="amount" class="form-control<?= invalid('amount') ?>" min="1" step="0.01" required value="<?= e((string) old('amount', $amountDefault)) ?>" data-bk-amount>
                  <?php if ($err = error_for('amount')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label required" for="pay-method">Method</label>
                  <select id="pay-method" name="method" class="form-select<?= invalid('method') ?>">
                    <?php $pm = (string) old('method', 'upi'); foreach (Pay::METHODS as $k => $label): ?>
                      <option value="<?= e($k) ?>"<?= $pm === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($err = error_for('method')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label required" for="pay-date">Date</label>
                  <input type="date" id="pay-date" name="paid_at" class="form-control<?= invalid('paid_at') ?>" required value="<?= e((string) old('paid_at', date('Y-m-d'))) ?>" max="<?= e(date('Y-m-d')) ?>">
                  <?php if ($err = error_for('paid_at')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label" for="pay-ref">Reference</label>
                  <input type="text" id="pay-ref" name="reference" class="form-control<?= invalid('reference') ?>" maxlength="120" value="<?= e((string) old('reference', '')) ?>" placeholder="UPI txn / cheque no.">
                  <?php if ($err = error_for('reference')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
                </div>
                <div class="col-md-9">
                  <label class="form-label" for="pay-notes">Notes</label>
                  <input type="text" id="pay-notes" name="notes" class="form-control<?= invalid('notes') ?>" maxlength="255" value="<?= e((string) old('notes', '')) ?>" placeholder="e.g. Advance to confirm, balance at pickup">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary w-100"><?= icon('check') ?> Save payment</button>
                </div>
              </div>
              <div class="alert alert-warning py-2 mt-2 mb-0 rth-bk-overpay" data-bk-overpay hidden>
                <?= icon('alert', 'icon icon-sm') ?> This is more than the balance due (<?= e(inr2(max(0, $balance))) ?>). You can still save it.
              </div>
              <div class="form-hint mt-2">A receipt number is created automatically. After saving, use the <?= icon('whatsapp', 'icon icon-sm') ?> button to send the customer a “payment received” message.</div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <?php if (feature('profit') && can('profit') && is_file(APP_PATH . '/Views/admin/booking-services/panel.php')) echo partial('admin/booking-services/panel', ['booking' => $booking]); ?>
      <?php if (feature('invoices') && can('invoices') && is_file(APP_PATH . '/Views/admin/invoices/booking-panel.php')) echo partial('admin/invoices/booking-panel', ['booking' => $booking]); ?>
    </div>

    <div class="col-lg-4">
      <!-- Customer -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('user') ?> Customer</h3></div>
        <div class="card-body">
          <div class="fw-bold fs-3 mb-1">
            <?php if (feature('customers') && can('customers')): ?>
              <a href="<?= e(url('/customers/' . (int) $b['customer_id'])) ?>" class="text-reset"><?= e($b['customer_name']) ?></a>
            <?php else: ?><?= e($b['customer_name']) ?><?php endif; ?>
          </div>
          <div class="text-secondary mb-1"><?= icon('phone', 'icon icon-sm') ?> <?= e($phone) ?></div>
          <?php if (!empty($b['customer_email'])): ?><div class="text-secondary mb-1 text-break"><?= icon('mail', 'icon icon-sm') ?> <?= e($b['customer_email']) ?></div><?php endif; ?>
          <?php if (!empty($b['customer_city'])): ?><div class="text-secondary mb-1"><?= icon('map-pin', 'icon icon-sm') ?> <?= e($b['customer_city']) ?></div><?php endif; ?>
          <div class="d-flex gap-2 mt-3">
            <a href="<?= e($tel) ?>" class="btn flex-fill"><?= icon('phone') ?> Call</a>
            <a href="<?= e(wa_link($phone, 'Hi ' . $b['customer_name'] . ', ')) ?>" class="btn btn-success flex-fill" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a>
          </div>
        </div>
      </div>

      <!-- Team & notes -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('note') ?> Team notes</h3></div>
        <div class="card-body">
          <div class="small text-secondary mb-1">Handled by</div>
          <div class="mb-3"><?= e($b['assigned_name'] ?: 'Not assigned') ?></div>
          <div class="small text-secondary mb-1">Internal notes <span class="text-secondary">(not shown to the customer)</span></div>
          <div class="rth-bk-pre"><?= !empty($b['notes']) ? e($b['notes']) : '<span class="text-secondary">No notes yet.</span>' ?></div>
          <div class="small text-secondary mt-3">Created <?= e(nice_date((string) $b['created_at'])) ?> · updated <?= e(nice_date((string) $b['updated_at'])) ?></div>
        </div>
      </div>

      <!-- Documents -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('file-text') ?> Documents</h3></div>
        <div class="list-group list-group-flush">
          <a href="<?= e(url('/bookings/' . $bid . '/voucher')) ?>" class="list-group-item list-group-item-action"><?= icon('printer') ?> Booking voucher (for the traveller)</a>
          <?php if (feature('invoices') && can('invoices')): ?>
            <a href="<?= e(url('/invoices/create?booking_id=' . $bid)) ?>" class="list-group-item list-group-item-action"><?= icon('receipt') ?> Create GST invoice</a>
          <?php endif; ?>
          <?php if (!empty($b['quotation_id']) && feature('quotations') && can('quotations')): ?>
            <a href="<?= e(url('/quotations/' . (int) $b['quotation_id'])) ?>" class="list-group-item list-group-item-action"><?= icon('file-text') ?> Original quotation</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Danger zone -->
      <div class="card mb-3 rth-bk-danger">
        <div class="card-body">
          <?php if (!$cancelled): ?>
            <form method="post" action="<?= e(url('/bookings/' . $bid . '/status')) ?>" data-confirm="Cancel booking <?= e($b['number']) ?>? It will be removed from the calendar. Payments stay on record.">
              <?= csrf_field() ?>
              <input type="hidden" name="status" value="cancelled">
              <button type="submit" class="btn btn-outline-warning w-100 mb-2"><?= icon('x') ?> Cancel booking</button>
            </form>
          <?php endif; ?>
          <?php if ($paymentCount === 0): ?>
            <form method="post" action="<?= e(url('/bookings/' . $bid)) ?>" data-confirm="Permanently delete booking <?= e($b['number']) ?> and its travellers? This cannot be undone.">
              <?= csrf_field() ?><?= method_field('DELETE') ?>
              <button type="submit" class="btn btn-outline-danger w-100"><?= icon('trash') ?> Delete booking</button>
            </form>
          <?php else: ?>
            <p class="small text-secondary mb-0"><?= icon('lock', 'icon icon-sm') ?> This booking has payments, so it can’t be deleted. Cancel it instead.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
