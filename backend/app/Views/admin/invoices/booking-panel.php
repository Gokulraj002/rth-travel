<?php
/**
 * Booking page panel: GST invoices for this booking + "Create GST invoice".
 * Included by the bookings module: partial('admin/invoices/booking-panel', ['booking' => $booking]).
 * @var array<string,mixed> $booking  (needs at least id)
 */
use App\Repositories\InvoiceRepository;

if (!feature('invoices') || !can('invoices') || empty($booking['id'])) {
    return;
}
$bookingId = (int) $booking['id'];
$invoices = InvoiceRepository::forBooking($bookingId);
$billed = 0.0;
foreach ($invoices as $i) {
    if (in_array($i['status'], ['issued', 'paid'], true)) {
        $billed += (float) $i['total'];
    }
}
?>
<div class="card mb-3 rth-inv-panel">
  <div class="card-header">
    <h3 class="card-title"><?= icon('receipt', 'icon me-1') ?> GST invoices</h3>
    <div class="card-actions">
      <a href="<?= e(url('/invoices/create?booking_id=' . $bookingId)) ?>" class="btn btn-sm btn-primary"><?= icon('plus') ?> Create GST invoice</a>
    </div>
  </div>
  <?php if ($invoices === []): ?>
    <div class="card-body text-secondary small">
      No invoice yet. Create one in a click — the customer, trip and amount are filled in for you.
    </div>
  <?php else: ?>
    <div class="list-group list-group-flush">
      <?php foreach ($invoices as $i): ?>
        <a class="list-group-item list-group-item-action d-flex align-items-center gap-2<?= $i['status'] === 'void' ? ' rth-inv-void' : '' ?>" href="<?= e(url('/invoices/' . (int) $i['id'])) ?>">
          <div class="flex-fill">
            <div class="fw-semibold"><?= e($i['status'] === 'draft' ? 'Draft #' . $i['id'] : $i['number']) ?></div>
            <div class="small text-secondary"><?= e(nice_day((string) $i['issue_date'])) ?></div>
          </div>
          <div class="text-end">
            <div class="fw-semibold"><?= e(inr2($i['total'])) ?></div>
            <?= status_badge((string) $i['status'], InvoiceRepository::STATUSES[$i['status']] ?? null) ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="card-footer small text-secondary">
      Invoiced: <strong><?= e(inr2($billed)) ?></strong>
      <?php if (isset($booking['total_amount'])): ?> of <?= e(inr2($booking['total_amount'])) ?> booking value<?php endif; ?>
    </div>
  <?php endif; ?>
</div>
