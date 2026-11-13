<?php
/**
 * The customer-facing quotation document (A4). Shared by the admin print view and the public share page.
 * Never shows internal notes.
 * @var array<string,mixed> $q
 * @var string $status   effective status
 * @var list<array<string,mixed>> $items
 * @var list<array<string,mixed>> $days
 * @var bool $public
 */
use App\Core\Settings;
use App\Core\Template;
use App\Repositories\QuotationRepository as Quotes;

$co = Template::companyVars();
$stored = Settings::all();
$address = trim((string) ($stored['company_address'] ?? ''));
if ($address === '') {
    $address = trim(implode(', ', array_filter([(string) ($stored['address_1_line1'] ?? ''), (string) ($stored['address_1_line2'] ?? '')])));
}
if ($address === '') {
    $address = setting('company_address');
}
$gstin = setting('company_gstin');

$nights = null;
if (!empty($q['travel_start']) && !empty($q['travel_end'])) {
    $nights = (int) round((strtotime((string) $q['travel_end']) - strtotime((string) $q['travel_start'])) / 86400);
}
$adults = (int) $q['adults'];
$children = (int) $q['children'];
$pax = $adults . ' adult' . ($adults === 1 ? '' : 's') . ($children > 0 ? ', ' . $children . ' child' . ($children === 1 ? '' : 'ren') : '');
$lines = static fn (?string $t): array => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $t) ?: []), static fn ($l) => $l !== ''));
$inclusions = $lines($q['inclusions'] ?? null);
$exclusions = $lines($q['exclusions'] ?? null);
$terms = $lines($q['terms'] ?? null);

$bank = array_filter([
    'Bank' => setting('bank_name'),
    'Account name' => setting('bank_account_name'),
    'Account no.' => setting('bank_account_no'),
    'IFSC' => setting('bank_ifsc'),
    'Branch' => setting('bank_branch'),
], static fn ($v) => $v !== '');
$upi = setting('upi_id');
$discount = (float) $q['discount'];
$tax = (float) $q['tax_amount'];
?>
<div class="rth-doc rth-qdoc">
  <?php if ($status === 'expired'): ?>
    <div class="rth-qdoc-banner is-expired">This quotation expired on <?= e(nice_day($q['valid_until'])) ?>. Prices may have changed — please contact us for an updated quote.</div>
  <?php elseif ($status === 'accepted'): ?>
    <div class="rth-qdoc-banner is-accepted"><?= icon('check') ?> Accepted — thank you for booking with us!</div>
  <?php elseif ($status === 'rejected'): ?>
    <div class="rth-qdoc-banner is-expired">This quotation is no longer active.</div>
  <?php endif; ?>

  <header class="rth-doc-head">
    <div class="rth-doc-brand">
      <img src="<?= e(asset('/assets/admin/logo.jpeg')) ?>" alt="">
      <div>
        <div class="name"><?= e($co['company_name']) ?></div>
        <div class="meta">
          <?= e($address) ?><br>
          <?php if ($co['company_phone'] !== ''): ?><?= e($co['company_phone']) ?><?php endif; ?>
          <?php if ($co['company_phone'] !== '' && $co['company_email'] !== ''): ?> · <?php endif; ?>
          <?php if ($co['company_email'] !== ''): ?><?= e($co['company_email']) ?><?php endif; ?>
          <?php if ($gstin !== ''): ?><br>GSTIN: <?= e($gstin) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="rth-doc-title">
      <h1>Quotation</h1>
      <div class="no"><?= e($q['number']) ?></div>
      <div class="meta">Date: <?= e(nice_day($q['created_at'])) ?></div>
      <?php if (!empty($q['valid_until'])): ?><div class="meta">Valid until: <strong><?= e(nice_day($q['valid_until'])) ?></strong></div><?php endif; ?>
    </div>
  </header>

  <section class="rth-qdoc-hero">
    <div class="rth-qdoc-kicker">Your trip</div>
    <div class="rth-qdoc-trip"><?= e($q['title']) ?></div>
  </section>

  <section class="rth-qdoc-grid">
    <div class="rth-doc-box">
      <div class="rth-qdoc-label">Prepared for</div>
      <div class="rth-qdoc-strong"><?= e($q['customer_name'] ?? '') ?></div>
      <?php if (!empty($q['customer_phone'])): ?><div><?= e($q['customer_phone']) ?></div><?php endif; ?>
      <?php if (!empty($q['customer_email'])): ?><div><?= e($q['customer_email']) ?></div><?php endif; ?>
      <?php if (!empty($q['customer_city'])): ?><div class="text-secondary"><?= e($q['customer_city']) ?></div><?php endif; ?>
    </div>
    <div class="rth-doc-box">
      <div class="rth-qdoc-label">Trip summary</div>
      <dl class="rth-qdoc-facts">
        <div><dt>Dates</dt><dd><?= !empty($q['travel_start']) ? e(nice_day($q['travel_start'])) . (!empty($q['travel_end']) ? ' – ' . e(nice_day($q['travel_end'])) : '') : 'To be confirmed' ?></dd></div>
        <?php if ($nights !== null && $nights >= 0): ?><div><dt>Duration</dt><dd><?= e((string) $nights) ?> night<?= $nights === 1 ? '' : 's' ?> / <?= e((string) ($nights + 1)) ?> days</dd></div><?php endif; ?>
        <div><dt>Travellers</dt><dd><?= e($pax) ?></dd></div>
        <?php if (!empty($q['hotel_category'])): ?><div><dt>Hotels</dt><dd><?= e($q['hotel_category']) ?></dd></div><?php endif; ?>
      </dl>
    </div>
  </section>

  <?php if ($days !== []): ?>
    <h2>Day-wise itinerary</h2>
    <ol class="rth-qdoc-timeline">
      <?php foreach ($days as $d): ?>
        <li>
          <div class="rth-qdoc-day">Day <?= e((string) $d['day']) ?></div>
          <div class="rth-qdoc-daybody">
            <div class="rth-qdoc-daytitle"><?= e($d['title']) ?></div>
            <?php if (trim((string) $d['description']) !== ''): ?><div class="rth-qdoc-daytext"><?= nl2br(e($d['description'])) ?></div><?php endif; ?>
            <?php if (!empty($d['stay'])): ?><div class="rth-qdoc-stay"><?= icon('bed') ?> Overnight: <?= e($d['stay']) ?></div><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <h2>Price details</h2>
  <table class="rth-doc-table rth-qdoc-prices">
    <thead>
      <tr><th class="w-1">#</th><th>Description</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $it): ?>
        <tr>
          <td><?= e((string) ($i + 1)) ?></td>
          <td><?= e($it['description']) ?><div class="rth-qdoc-type"><?= e(Quotes::ITEM_TYPES[$it['type']] ?? $it['type']) ?></div></td>
          <td class="num"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2, '.', ''), '0'), '.')) ?></td>
          <td class="num"><?= e(inr2($it['unit_price'])) ?></td>
          <td class="num"><?= e(inr2($it['amount'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="rth-qdoc-totalwrap">
    <div class="rth-qdoc-words">
      <div class="rth-qdoc-label">Amount in words</div>
      <?= e(amount_in_words($q['total'])) ?>
    </div>
    <div class="rth-doc-totals">
      <div><span>Subtotal</span><span><?= e(inr2($q['subtotal'])) ?></span></div>
      <?php if ($discount > 0): ?><div class="rth-qdoc-discount"><span>Discount</span><span>− <?= e(inr2($discount)) ?></span></div><?php endif; ?>
      <?php if ($tax > 0): ?><div><span>GST <?= e(rtrim(rtrim(number_format((float) $q['tax_percent'], 2, '.', ''), '0'), '.')) ?>%</span><span><?= e(inr2($tax)) ?></span></div><?php endif; ?>
      <div class="grand"><span>Total</span><span><?= e(inr2($q['total'])) ?></span></div>
    </div>
  </div>

  <?php if ($inclusions !== [] || $exclusions !== []): ?>
    <section class="rth-qdoc-grid rth-qdoc-incl">
      <div>
        <h2>What's included</h2>
        <ul class="rth-qdoc-list is-in">
          <?php foreach ($inclusions as $l): ?><li><?= icon('check') ?><span><?= e($l) ?></span></li><?php endforeach; ?>
          <?php if ($inclusions === []): ?><li class="text-secondary"><span>—</span></li><?php endif; ?>
        </ul>
      </div>
      <div>
        <h2>Not included</h2>
        <ul class="rth-qdoc-list is-out">
          <?php foreach ($exclusions as $l): ?><li><?= icon('x') ?><span><?= e($l) ?></span></li><?php endforeach; ?>
          <?php if ($exclusions === []): ?><li class="text-secondary"><span>—</span></li><?php endif; ?>
        </ul>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($terms !== []): ?>
    <h2>Terms &amp; conditions</h2>
    <ol class="rth-qdoc-terms">
      <?php foreach ($terms as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?php if ($bank !== [] || $upi !== ''): ?>
    <h2>Payment details</h2>
    <div class="rth-doc-box rth-qdoc-pay">
      <?php if ($bank !== []): ?>
        <dl class="rth-qdoc-facts">
          <?php foreach ($bank as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?>
        </dl>
      <?php endif; ?>
      <?php if ($upi !== ''): ?><div class="rth-qdoc-upi">UPI: <strong><?= e($upi) ?></strong></div><?php endif; ?>
    </div>
  <?php endif; ?>

  <footer class="rth-doc-foot rth-qdoc-foot">
    <div class="rth-qdoc-thanks">Thank you for choosing <?= e($co['company_name']) ?>!</div>
    <div>
      <?= e(implode(' · ', array_filter([$co['company_phone'], $co['company_email'], $co['company_website']]))) ?>
    </div>
    <div class="small">This is a computer-generated quotation and does not need a signature. Prices are valid until the date shown above and subject to availability.</div>
  </footer>
</div>
