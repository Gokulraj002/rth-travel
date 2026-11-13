<?php
/**
 * Payment receipt (layouts/print + doc-receipt.css).
 * @var array<string,mixed> $payment
 * @var float $paidUpTo
 * @var float $balanceAfter
 */
use App\Core\Template;
use App\Repositories\BookingRepository as Bk;
use App\Repositories\PaymentRepository as Pay;

$p = $payment;
$co = Template::companyVars();
$address = setting('company_address');
$gstin = setting('company_gstin');
$signature = setting('signature_name');
?>
<div class="rth-doc rth-receipt">
  <header class="rth-doc-head">
    <div class="rth-doc-brand">
      <img src="<?= e(asset('/assets/admin/logo.jpeg')) ?>" alt="">
      <div>
        <div class="name"><?= e($co['company_name'] ?: 'Reyaan Trip Holiday') ?></div>
        <div class="meta">
          <?php if ($address !== ''): ?><?= e($address) ?><br><?php endif; ?>
          <?= e($co['company_phone']) ?><?= $co['company_email'] !== '' ? ' · ' . e($co['company_email']) : '' ?>
          <?php if ($gstin !== ''): ?><br>GSTIN <?= e($gstin) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="rth-doc-title">
      <h1>Payment receipt</h1>
      <div class="no"><?= e($p['receipt_no']) ?></div>
      <div class="meta">Date: <?= e(nice_day((string) $p['paid_at'])) ?></div>
    </div>
  </header>

  <div class="rth-receipt-amount">
    <div class="lbl">Amount received</div>
    <div class="amt"><?= e(inr2($p['amount'])) ?></div>
    <div class="words"><?= e(amount_in_words((float) $p['amount'])) ?></div>
  </div>

  <table class="rth-receipt-table">
    <tbody>
      <tr><th>Received from</th><td><strong><?= e($p['customer_name']) ?></strong><?= !empty($p['customer_phone']) ? ' · ' . e($p['customer_phone']) : '' ?><?= !empty($p['customer_city']) ? ' · ' . e($p['customer_city']) : '' ?></td></tr>
      <tr><th>Towards booking</th><td><strong><?= e($p['booking_number']) ?></strong> — <?= e($p['booking_title']) ?></td></tr>
      <tr><th>Travel dates</th><td><?= e(Bk::dateRange((string) $p['travel_start'], (string) $p['travel_end'])) ?></td></tr>
      <tr><th>Payment mode</th><td><?= e(Pay::methodLabel($p['method'])) ?><?= !empty($p['reference']) ? ' · Ref: ' . e($p['reference']) : '' ?></td></tr>
      <?php if (!empty($p['notes'])): ?><tr><th>Note</th><td><?= e($p['notes']) ?></td></tr><?php endif; ?>
    </tbody>
  </table>

  <div class="rth-receipt-bottom">
    <div class="rth-doc-totals">
      <div><span>Booking total</span><span><?= e(inr2($p['total_amount'])) ?></span></div>
      <div><span>Received till date (incl. this)</span><span><?= e(inr2($paidUpTo)) ?></span></div>
      <div class="grand"><span><?= $balanceAfter > 0.009 ? 'Balance due' : 'Balance' ?></span><span><?= e(inr2(max(0, $balanceAfter))) ?></span></div>
      <?php if ($balanceAfter < -0.009): ?><div class="small"><span>Excess received</span><span><?= e(inr2(-$balanceAfter)) ?></span></div><?php endif; ?>
    </div>
    <div class="rth-receipt-sign">
      <div class="line"></div>
      <div>For <?= e($co['company_name'] ?: 'Reyaan Trip Holiday') ?></div>
      <div class="small"><?= e($signature) ?></div>
    </div>
  </div>

  <?php if ($p['booking_status'] === 'cancelled'): ?>
    <p class="rth-receipt-note">Note: this booking has since been cancelled.</p>
  <?php endif; ?>

  <footer class="rth-doc-foot">
    Thank you for your payment. This is a computer-generated receipt; it is not a GST tax invoice.
    <?php if (!empty($p['recorded_by'])): ?><br>Recorded by <?= e($p['recorded_by']) ?>.<?php endif; ?>
  </footer>
</div>
