<?php
/**
 * Printable Indian GST "TAX INVOICE" (layouts/print.php, styles in css/doc-invoice.css).
 * @var array<string,mixed> $inv
 * @var array<string,string> $biz  business settings
 * @var string $customerGstin
 * @var string $notes
 * @var bool $intra
 */
use App\Repositories\InvoiceRepository;

$status = (string) $inv['status'];
$cu = $inv['customer'];
$rate = (float) $inv['tax_percent'];
$fmtRate = static fn (float $r): string => rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.') . '%';
$taxable = round((float) $inv['subtotal'] - (float) $inv['discount'], 2);
$posCode = InvoiceRepository::stateCode((string) $inv['place_of_supply']);

// Spread the discount and tax over the lines so each row shows its own taxable value and GST;
// the last row absorbs rounding so the columns add up to the invoice totals exactly.
$lines = [];
$subtotal = (float) $inv['subtotal'];
$n = count($inv['items']);
$acc = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
foreach ($inv['items'] as $i => $it) {
    $last = $i === $n - 1;
    $share = $subtotal > 0 ? (float) $it['amount'] / $subtotal : 0;
    $row = [
        'taxable' => $last ? $taxable - $acc['taxable'] : round($taxable * $share, 2),
        'cgst' => $last ? (float) $inv['cgst'] - $acc['cgst'] : round((float) $inv['cgst'] * $share, 2),
        'sgst' => $last ? (float) $inv['sgst'] - $acc['sgst'] : round((float) $inv['sgst'] * $share, 2),
        'igst' => $last ? (float) $inv['igst'] - $acc['igst'] : round((float) $inv['igst'] * $share, 2),
    ];
    foreach ($acc as $k => $v) {
        $acc[$k] = round($v + $row[$k], 2);
    }
    $lines[] = [...$it, ...$row];
}
$terms = array_filter(array_map('trim', preg_split('/\R/', (string) $biz['invoice_terms']) ?: []));
$hasBank = $biz['bank_account_no'] !== '' || $biz['upi_id'] !== '';
$docNo = $status === 'draft' ? 'DRAFT' : (string) $inv['number'];
?>
<div class="rth-doc rth-inv-doc<?= $status === 'void' ? ' is-void' : '' ?><?= $status === 'draft' ? ' is-draft' : '' ?>">
  <?php if ($status === 'void' || $status === 'draft'): ?><div class="rth-inv-stamp" aria-hidden="true"><?= $status === 'void' ? 'VOID' : 'DRAFT' ?></div><?php endif; ?>

  <div class="rth-doc-head">
    <div class="rth-doc-brand">
      <img src="<?= e(asset('/assets/admin/logo.jpeg')) ?>" alt="">
      <div>
        <div class="name"><?= e($biz['company_legal_name']) ?></div>
        <div class="meta">
          <?= nl2br(e($biz['company_address'])) ?><br>
          <?php if ($biz['company_phone'] !== ''): ?>Ph: <?= e($biz['company_phone']) ?><?php endif; ?>
          <?php if ($biz['company_email'] !== ''): ?><?= $biz['company_phone'] !== '' ? ' · ' : '' ?><?= e($biz['company_email']) ?><?php endif; ?>
          <?php if ($biz['company_website'] !== ''): ?><br><?= e(preg_replace('#^https?://#', '', $biz['company_website'])) ?><?php endif; ?>
        </div>
        <div class="rth-inv-ids">
          <?php if ($biz['company_gstin'] !== ''): ?><span><b>GSTIN:</b> <?= e($biz['company_gstin']) ?></span><?php endif; ?>
          <?php if ($biz['company_pan'] !== ''): ?><span><b>PAN:</b> <?= e($biz['company_pan']) ?></span><?php endif; ?>
          <span><b>State:</b> <?= e($biz['company_state']) ?> (<?= e($biz['company_state_code']) ?>)</span>
        </div>
      </div>
    </div>
    <div class="rth-doc-title">
      <h1>Tax Invoice</h1>
      <div class="meta">Original for recipient</div>
      <table class="rth-inv-meta">
        <tr><th>Invoice no.</th><td class="no"><?= e($docNo) ?></td></tr>
        <tr><th>Invoice date</th><td><?= e(nice_day((string) $inv['issue_date'])) ?></td></tr>
        <?php if (!empty($inv['due_date'])): ?><tr><th>Due date</th><td><?= e(nice_day((string) $inv['due_date'])) ?></td></tr><?php endif; ?>
        <?php if (!empty($inv['booking_number'])): ?><tr><th>Booking ref.</th><td><?= e($inv['booking_number']) ?></td></tr><?php endif; ?>
      </table>
    </div>
  </div>

  <div class="rth-inv-parties">
    <div class="rth-doc-box">
      <div class="rth-doc-section">Bill to</div>
      <div class="fw-bold"><?= e($inv['customer_name']) ?></div>
      <?php if (!empty($cu['address'])): ?><div><?= e($cu['address']) ?></div><?php endif; ?>
      <?php if (!empty($cu['city'])): ?><div><?= e($cu['city']) ?></div><?php endif; ?>
      <div>Ph: <?= e($inv['customer_phone']) ?><?php if (!empty($cu['email'])): ?> · <?= e($cu['email']) ?><?php endif; ?></div>
      <div><b>GSTIN:</b> <?= $customerGstin !== '' ? e($customerGstin) : 'Unregistered (B2C)' ?></div>
    </div>
    <div class="rth-doc-box">
      <div class="rth-doc-section">Supply details</div>
      <div><b>Place of supply:</b> <?= e((string) $inv['place_of_supply']) ?><?= $posCode !== '' ? ' (' . e($posCode) . ')' : '' ?></div>
      <div><b>Supply type:</b> <?= $intra ? 'Intra-state (CGST + SGST)' : 'Inter-state (IGST)' ?></div>
      <div><b>Reverse charge:</b> No</div>
      <?php if (!empty($inv['booking_title'])): ?><div><b>Trip:</b> <?= e($inv['booking_title']) ?></div><?php endif; ?>
    </div>
  </div>

  <table class="rth-doc-table rth-inv-table">
    <thead>
      <tr>
        <th class="w-1">#</th>
        <th>Description of service</th>
        <th>SAC</th>
        <th class="num">Qty</th>
        <th class="num">Rate</th>
        <th class="num">Taxable value</th>
        <?php if ($intra): ?>
          <th class="num">CGST<br><small><?= e($fmtRate($rate / 2)) ?></small></th>
          <th class="num">SGST<br><small><?= e($fmtRate($rate / 2)) ?></small></th>
        <?php else: ?>
          <th class="num">IGST<br><small><?= e($fmtRate($rate)) ?></small></th>
        <?php endif; ?>
        <th class="num">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $i => $l): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= nl2br(e($l['description'])) ?></td>
          <td><?= e($l['hsn_sac'] ?? '') ?></td>
          <td class="num"><?= e(rtrim(rtrim((string) $l['qty'], '0'), '.')) ?></td>
          <td class="num"><?= e(inr2($l['unit_price'])) ?></td>
          <td class="num"><?= e(inr2($l['taxable'])) ?></td>
          <?php if ($intra): ?>
            <td class="num"><?= e(inr2($l['cgst'])) ?></td>
            <td class="num"><?= e(inr2($l['sgst'])) ?></td>
          <?php else: ?>
            <td class="num"><?= e(inr2($l['igst'])) ?></td>
          <?php endif; ?>
          <td class="num"><?= e(inr2($l['taxable'] + $l['cgst'] + $l['sgst'] + $l['igst'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" class="num">Total</td>
        <td class="num"><?= e(inr2($taxable)) ?></td>
        <?php if ($intra): ?>
          <td class="num"><?= e(inr2($inv['cgst'])) ?></td>
          <td class="num"><?= e(inr2($inv['sgst'])) ?></td>
        <?php else: ?>
          <td class="num"><?= e(inr2($inv['igst'])) ?></td>
        <?php endif; ?>
        <td class="num"><?= e(inr2($inv['total'])) ?></td>
      </tr>
    </tfoot>
  </table>

  <div class="rth-inv-bottom">
    <div class="rth-inv-words">
      <div class="rth-doc-section">Amount in words</div>
      <div class="fw-bold"><?= e(amount_in_words((float) $inv['total'])) ?></div>
      <?php if (trim($notes) !== ''): ?>
        <div class="rth-doc-section">Notes</div>
        <div><?= nl2br(e($notes)) ?></div>
      <?php endif; ?>
    </div>
    <div class="rth-doc-totals">
      <div><span>Subtotal</span><span><?= e(inr2($inv['subtotal'])) ?></span></div>
      <?php if ((float) $inv['discount'] > 0): ?><div><span>Less: discount</span><span>− <?= e(inr2($inv['discount'])) ?></span></div><?php endif; ?>
      <div><span>Taxable value</span><span><?= e(inr2($taxable)) ?></span></div>
      <?php if ($intra): ?>
        <div><span>CGST @ <?= e($fmtRate($rate / 2)) ?></span><span><?= e(inr2($inv['cgst'])) ?></span></div>
        <div><span>SGST @ <?= e($fmtRate($rate / 2)) ?></span><span><?= e(inr2($inv['sgst'])) ?></span></div>
      <?php else: ?>
        <div><span>IGST @ <?= e($fmtRate($rate)) ?></span><span><?= e(inr2($inv['igst'])) ?></span></div>
      <?php endif; ?>
      <div class="grand"><span>Invoice total</span><span><?= e(inr2($inv['total'])) ?></span></div>
    </div>
  </div>

  <div class="rth-inv-bottom rth-inv-foot-grid">
    <div>
      <?php if ($hasBank): ?>
        <div class="rth-doc-section">Bank details</div>
        <div class="rth-inv-bank">
          <?php if ($biz['bank_account_name'] !== ''): ?><div><b>A/c name:</b> <?= e($biz['bank_account_name']) ?></div><?php endif; ?>
          <?php if ($biz['bank_name'] !== ''): ?><div><b>Bank:</b> <?= e($biz['bank_name']) ?><?= $biz['bank_branch'] !== '' ? ', ' . e($biz['bank_branch']) : '' ?></div><?php endif; ?>
          <?php if ($biz['bank_account_no'] !== ''): ?><div><b>A/c no.:</b> <?= e($biz['bank_account_no']) ?></div><?php endif; ?>
          <?php if ($biz['bank_ifsc'] !== ''): ?><div><b>IFSC:</b> <?= e($biz['bank_ifsc']) ?></div><?php endif; ?>
          <?php if ($biz['upi_id'] !== ''): ?><div><b>UPI:</b> <?= e($biz['upi_id']) ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($terms !== []): ?>
        <div class="rth-doc-section">Terms</div>
        <ol class="rth-inv-terms"><?php foreach ($terms as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ol>
      <?php endif; ?>
    </div>
    <div class="rth-inv-sign">
      <div>For <b><?= e($biz['company_legal_name']) ?></b></div>
      <div class="rth-inv-sign-space"></div>
      <div class="rth-inv-sign-name"><?= e($biz['signature_name']) ?></div>
    </div>
  </div>

  <div class="rth-doc-foot text-center">This is a computer generated invoice and does not need a physical signature.</div>
</div>
