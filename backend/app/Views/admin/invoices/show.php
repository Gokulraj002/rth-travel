<?php
/**
 * One invoice (admin view) with actions.
 * @var array<string,mixed> $inv
 * @var string $customerGstin
 * @var string $notes
 * @var bool $intra
 */
use App\Repositories\InvoiceRepository;

$id = (int) $inv['id'];
$status = (string) $inv['status'];
$cu = $inv['customer'];
$taxable = (float) $inv['subtotal'] - (float) $inv['discount'];
$rate = (float) $inv['tax_percent'];
$fmtRate = static fn (float $r): string => rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.') . '%';
?>
<div class="rth-invoice-show">
<?php if ($status === 'void'): ?>
  <div class="alert alert-danger"><?= icon('alert', 'icon me-1') ?> This invoice is <strong>void</strong>. It is kept for your records but does not count in sales or GST totals.</div>
<?php elseif ($status === 'draft'): ?>
  <div class="alert alert-info"><?= icon('note', 'icon me-1') ?> This is a <strong>draft</strong>. Issue it to give it the next invoice number (<?= e(InvoiceRepository::previewNumber('invoice')) ?>).</div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="<?= e(url('/invoices/' . $id . '/print')) ?>" class="btn btn-primary"><?= icon('printer') ?> Print / PDF</a>
  <?php if (in_array($status, ['draft', 'issued'], true)): ?>
    <a href="<?= e(url('/invoices/' . $id . '/edit')) ?>" class="btn btn-outline-secondary"><?= icon('edit') ?> Edit</a>
  <?php endif; ?>
  <?php if ($status === 'draft'): ?>
    <form method="post" action="<?= e(url('/invoices/' . $id . '/issue')) ?>" data-confirm="Issue this invoice? It will get the next invoice number.">
      <?= csrf_field() ?><button type="submit" class="btn btn-success"><?= icon('check') ?> Issue invoice</button>
    </form>
  <?php endif; ?>
  <?php if ($status === 'issued'): ?>
    <form method="post" action="<?= e(url('/invoices/' . $id . '/paid')) ?>">
      <?= csrf_field() ?><button type="submit" class="btn btn-success"><?= icon('cash') ?> Mark as paid</button>
    </form>
  <?php endif; ?>
  <?php if ($cu && !empty($cu['phone'])): ?>
    <a class="btn btn-outline-success" target="_blank" rel="noopener"
       href="<?= e(wa_link((string) $cu['phone'], 'Hi ' . $cu['name'] . ', here are the details of your invoice ' . ($status === 'draft' ? '' : $inv['number'] . ' ') . 'from ' . setting('company_legal_name') . ': total ' . inr2($inv['total']) . '. Thank you!')) ?>"><?= icon('whatsapp') ?> WhatsApp</a>
  <?php endif; ?>
  <div class="ms-auto d-flex gap-2">
    <?php if (in_array($status, ['issued', 'paid'], true)): ?>
      <form method="post" action="<?= e(url('/invoices/' . $id . '/void')) ?>" data-confirm="Void invoice <?= e($inv['number']) ?>? This cannot be undone — the number stays used and the invoice no longer counts.">
        <?= csrf_field() ?><button type="submit" class="btn btn-outline-danger"><?= icon('x') ?> Void</button>
      </form>
    <?php endif; ?>
    <?php if ($status === 'draft'): ?>
      <form method="post" action="<?= e(url('/invoices/' . $id)) ?>" data-confirm="Delete this draft invoice?">
        <?= csrf_field() ?><?= method_field('DELETE') ?><button type="submit" class="btn btn-outline-danger"><?= icon('trash') ?> Delete draft</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row row-cards">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><?= e($status === 'draft' ? 'Draft #' . $id : $inv['number']) ?></h3>
        <div class="card-actions"><?= status_badge($status, InvoiceRepository::STATUSES[$status] ?? null) ?></div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Description</th><th>SAC</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($inv['items'] as $it): ?>
              <tr>
                <td><?= nl2br(e($it['description'])) ?></td>
                <td><?= e($it['hsn_sac'] ?? '') ?></td>
                <td class="text-end"><?= e(rtrim(rtrim((string) $it['qty'], '0'), '.')) ?></td>
                <td class="text-end text-nowrap"><?= e(inr2($it['unit_price'])) ?></td>
                <td class="text-end text-nowrap"><?= e(inr2($it['amount'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body">
        <div class="rth-inv-totals ms-auto">
          <div><span>Subtotal</span><span><?= e(inr2($inv['subtotal'])) ?></span></div>
          <?php if ((float) $inv['discount'] > 0): ?><div><span>Discount</span><span>− <?= e(inr2($inv['discount'])) ?></span></div><?php endif; ?>
          <div><span>Taxable value</span><strong><?= e(inr2($taxable)) ?></strong></div>
          <?php if ($intra): ?>
            <div><span>CGST @ <?= e($fmtRate($rate / 2)) ?></span><span><?= e(inr2($inv['cgst'])) ?></span></div>
            <div><span>SGST @ <?= e($fmtRate($rate / 2)) ?></span><span><?= e(inr2($inv['sgst'])) ?></span></div>
          <?php else: ?>
            <div><span>IGST @ <?= e($fmtRate($rate)) ?></span><span><?= e(inr2($inv['igst'])) ?></span></div>
          <?php endif; ?>
          <div class="grand"><span>Total</span><span><?= e(inr2($inv['total'])) ?></span></div>
        </div>
        <div class="small text-secondary text-end mt-2"><?= e(amount_in_words((float) $inv['total'])) ?></div>
      </div>
    </div>
    <?php if (trim($notes) !== ''): ?>
      <div class="card mb-3"><div class="card-body"><div class="text-secondary small mb-1">Notes</div><?= nl2br(e($notes)) ?></div></div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-body">
        <div class="text-secondary small">Billed to</div>
        <div class="fw-semibold">
          <?php if (feature('customers') && can('customers')): ?>
            <a href="<?= e(url('/customers/' . (int) $inv['customer_id'])) ?>"><?= e($inv['customer_name']) ?></a>
          <?php else: ?><?= e($inv['customer_name']) ?><?php endif; ?>
        </div>
        <div class="small"><?= e($inv['customer_phone']) ?></div>
        <?php if (!empty($cu['city']) || !empty($cu['address'])): ?><div class="small text-secondary"><?= e(trim(($cu['address'] ?? '') . ' ' . ($cu['city'] ?? ''))) ?></div><?php endif; ?>
        <?php if ($customerGstin !== ''): ?><div class="small">GSTIN: <strong><?= e($customerGstin) ?></strong></div><?php endif; ?>
        <hr class="my-2">
        <dl class="row mb-0 small">
          <dt class="col-6 text-secondary fw-normal">Invoice date</dt><dd class="col-6"><?= e(nice_day((string) $inv['issue_date'])) ?></dd>
          <dt class="col-6 text-secondary fw-normal">Due date</dt><dd class="col-6"><?= e(nice_day((string) ($inv['due_date'] ?? ''))) ?></dd>
          <dt class="col-6 text-secondary fw-normal">Place of supply</dt><dd class="col-6"><?= e((string) $inv['place_of_supply']) ?></dd>
          <dt class="col-6 text-secondary fw-normal">Tax type</dt><dd class="col-6"><?= $intra ? 'CGST + SGST' : 'IGST' ?></dd>
          <?php if ($inv['booking_id']): ?>
            <dt class="col-6 text-secondary fw-normal">Booking</dt>
            <dd class="col-6">
              <?php if (feature('bookings') && can('bookings')): ?><a href="<?= e(url('/bookings/' . (int) $inv['booking_id'])) ?>"><?= e($inv['booking_number']) ?></a>
              <?php else: ?><?= e($inv['booking_number']) ?><?php endif; ?>
            </dd>
          <?php endif; ?>
          <dt class="col-6 text-secondary fw-normal">Created</dt><dd class="col-6"><?= e(nice_date((string) $inv['created_at'])) ?></dd>
        </dl>
      </div>
    </div>
    <a href="<?= e(url('/invoices')) ?>" class="btn btn-ghost-secondary w-100"><?= icon('arrow-left') ?> All invoices</a>
  </div>
</div>
</div>
