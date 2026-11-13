<?php
/**
 * Quotation detail: summary, status actions, share (WhatsApp / email / link / PDF), convert to booking.
 * @var array<string,mixed> $q
 * @var string $status
 * @var list<array<string,mixed>> $items
 * @var list<array<string,mixed>> $days
 * @var array{id:int,number:string}|null $booking
 * @var array<string,mixed>|null $enquiry
 * @var array<string,mixed>|null $package
 * @var string $shareUrl
 * @var bool $canConvert
 */
use App\Repositories\QuotationRepository as Quotes;

$qid = (int) $q['id'];
$base = '/quotations/' . $qid;
$nights = null;
if (!empty($q['travel_start']) && !empty($q['travel_end'])) {
    $nights = (int) round((strtotime((string) $q['travel_end']) - strtotime((string) $q['travel_start'])) / 86400);
}
$adults = (int) $q['adults'];
$children = (int) $q['children'];
$lines = static fn (?string $t): array => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $t) ?: []), static fn ($l) => $l !== ''));
$hasEmail = trim((string) ($q['customer_email'] ?? '')) !== '';
$hasPhone = trim((string) ($q['customer_phone'] ?? '')) !== '';
$hasDates = !empty($q['travel_start']) && !empty($q['travel_end']);
$statusForm = static function (string $to, string $label, string $class, string $iconName) use ($base): string {
    return '<form method="post" action="' . e(url($base . '/status')) . '" class="m-0">' . csrf_field()
        . '<input type="hidden" name="status" value="' . e($to) . '">'
        . '<button type="submit" class="btn ' . e($class) . ' w-100">' . icon($iconName) . ' ' . e($label) . '</button></form>';
};
$qty = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
?>
<div class="rth-quote-show">
  <?php if ($status === 'expired'): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-center"><?= icon('clock') ?>
      <div>This quotation expired on <strong><?= e(nice_day($q['valid_until'])) ?></strong>. <a href="<?= e(url($base . '/edit')) ?>">Extend the validity</a> or duplicate it with fresh prices.</div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <div class="rth-quote-stats">
            <div>
              <div class="rth-quote-stat-label">Grand total</div>
              <div class="rth-quote-stat-value rth-quote-total"><?= e(inr($q['total'])) ?></div>
            </div>
            <div>
              <div class="rth-quote-stat-label">Status</div>
              <div class="rth-quote-stat-value"><?= status_badge($status, Quotes::statusLabel($status)) ?></div>
              <?php if (!empty($q['sent_at'])): ?><div class="small text-secondary">Sent <?= e(nice_date($q['sent_at'])) ?></div><?php endif; ?>
            </div>
            <div>
              <div class="rth-quote-stat-label">Travel dates</div>
              <div class="rth-quote-stat-value">
                <?php if (!empty($q['travel_start'])): ?>
                  <?= e(nice_day($q['travel_start'])) ?><?= !empty($q['travel_end']) ? ' – ' . e(nice_day($q['travel_end'])) : '' ?>
                  <?php if ($nights !== null): ?><div class="small text-secondary"><?= e((string) $nights) ?>N / <?= e((string) ($nights + 1)) ?>D</div><?php endif; ?>
                <?php else: ?><span class="text-secondary">Not fixed yet</span><?php endif; ?>
              </div>
            </div>
            <div>
              <div class="rth-quote-stat-label">Travellers</div>
              <div class="rth-quote-stat-value"><?= e((string) $adults) ?> adult<?= $adults === 1 ? '' : 's' ?><?= $children > 0 ? ', ' . e((string) $children) . ' child' . ($children === 1 ? '' : 'ren') : '' ?></div>
              <?php if (!empty($q['hotel_category'])): ?><div class="small text-secondary"><?= e($q['hotel_category']) ?> hotels</div><?php endif; ?>
            </div>
            <div>
              <div class="rth-quote-stat-label">Valid until</div>
              <div class="rth-quote-stat-value<?= $status === 'expired' ? ' text-danger' : '' ?>"><?= e(nice_day($q['valid_until'])) ?></div>
              <div class="small text-secondary">Created <?= e(nice_day($q['created_at'])) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Price lines</h3></div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?= e($it['description']) ?><div class="small text-secondary"><?= e(Quotes::ITEM_TYPES[$it['type']] ?? $it['type']) ?></div></td>
                  <td class="text-end"><?= e($qty($it['qty'])) ?></td>
                  <td class="text-end text-nowrap"><?= e(inr2($it['unit_price'])) ?></td>
                  <td class="text-end text-nowrap"><?= e(inr2($it['amount'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body border-top">
          <div class="rth-quote-sum ms-auto">
            <div><span>Subtotal</span><span><?= e(inr2($q['subtotal'])) ?></span></div>
            <?php if ((float) $q['discount'] > 0): ?><div><span>Discount</span><span>− <?= e(inr2($q['discount'])) ?></span></div><?php endif; ?>
            <?php if ((float) $q['tax_amount'] > 0): ?><div><span>GST <?= e($qty($q['tax_percent'])) ?>%</span><span><?= e(inr2($q['tax_amount'])) ?></span></div><?php endif; ?>
            <div class="rth-quote-sum-grand"><span>Grand total</span><span><?= e(inr2($q['total'])) ?></span></div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Day-wise itinerary</h3></div>
        <?php if ($days === []): ?>
          <div class="card-body text-secondary">No itinerary added. <a href="<?= e(url($base . '/edit')) ?>">Add days</a> or load them from a package.</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($days as $d): ?>
              <div class="list-group-item">
                <div class="d-flex gap-3">
                  <span class="badge bg-primary-lt rth-quote-daybadge">Day <?= e((string) $d['day']) ?></span>
                  <div>
                    <div class="fw-semibold"><?= e($d['title']) ?></div>
                    <?php if (trim((string) $d['description']) !== ''): ?><div class="text-secondary small"><?= nl2br(e($d['description'])) ?></div><?php endif; ?>
                    <?php if (!empty($d['stay'])): ?><div class="small mt-1"><?= icon('bed', 'icon icon-sm') ?> <?= e($d['stay']) ?></div><?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Included</h3></div>
            <div class="card-body">
              <?php $incl = $lines($q['inclusions'] ?? null); ?>
              <?php if ($incl === []): ?><span class="text-secondary">—</span><?php endif; ?>
              <ul class="list-unstyled m-0 rth-quote-checks">
                <?php foreach ($incl as $l): ?><li><span class="text-green"><?= icon('check', 'icon icon-sm') ?></span> <?= e($l) ?></li><?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Not included</h3></div>
            <div class="card-body">
              <?php $excl = $lines($q['exclusions'] ?? null); ?>
              <?php if ($excl === []): ?><span class="text-secondary">—</span><?php endif; ?>
              <ul class="list-unstyled m-0 rth-quote-checks">
                <?php foreach ($excl as $l): ?><li><span class="text-red"><?= icon('x', 'icon icon-sm') ?></span> <?= e($l) ?></li><?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <?php if (trim((string) ($q['terms'] ?? '')) !== ''): ?>
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Terms</h3></div>
          <div class="card-body small"><?= nl2br(e($q['terms'])) ?></div>
        </div>
      <?php endif; ?>

      <?php if (trim((string) ($q['notes'] ?? '')) !== ''): ?>
        <div class="card mb-3 rth-quote-notes">
          <div class="card-header"><h3 class="card-title"><?= icon('lock') ?> Internal notes</h3><span class="ms-auto small text-secondary">Not shown to the customer</span></div>
          <div class="card-body"><?= nl2br(e($q['notes'])) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-4">
      <!-- Share -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('send') ?> Share with customer</h3></div>
        <div class="card-body d-grid gap-2">
          <?php if ($hasPhone): ?>
            <form method="post" action="<?= e(url($base . '/whatsapp')) ?>" target="_blank" class="m-0" data-quote-wa>
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-success w-100"><?= icon('whatsapp') ?> Send on WhatsApp</button>
            </form>
          <?php endif; ?>
          <?php if ($hasEmail): ?>
            <form method="post" action="<?= e(url($base . '/email')) ?>" class="m-0" data-confirm="Email this quotation to <?= e($q['customer_email']) ?>?">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-outline-primary w-100"><?= icon('mail') ?> Email to <?= e(str_limit((string) $q['customer_email'], 28)) ?></button>
            </form>
          <?php else: ?>
            <button type="button" class="btn btn-outline-secondary w-100" disabled title="Add an email address to the customer first"><?= icon('mail') ?> Email (no email saved)</button>
          <?php endif; ?>
          <div class="input-group">
            <input type="text" class="form-control form-control-sm" value="<?= e($shareUrl) ?>" readonly aria-label="Share link" data-quote-link>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-quote-copy="<?= e($shareUrl) ?>"><?= icon('copy') ?> Copy</button>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary flex-fill" href="<?= e($shareUrl) ?>" target="_blank" rel="noopener"><?= icon('external') ?> Customer view</a>
            <a class="btn btn-outline-secondary flex-fill" href="<?= e(url($base . '/print')) ?>" target="_blank" rel="noopener"><?= icon('printer') ?> Print / PDF</a>
          </div>
          <div class="form-hint">WhatsApp opens in a new tab with the message ready, and the quotation is marked as sent.</div>
        </div>
      </div>

      <!-- Status -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Customer's answer</h3></div>
        <div class="card-body d-grid gap-2">
          <?php if ($booking === null): ?>
            <?php if (in_array($q['status'], ['draft', 'expired'], true)): ?><?= $statusForm('sent', 'Mark as sent', 'btn-outline-primary', 'send') ?><?php endif; ?>
            <?php if ($q['status'] !== 'accepted'): ?><?= $statusForm('accepted', 'Customer accepted', 'btn-outline-success', 'check') ?><?php endif; ?>
            <?php if ($q['status'] !== 'rejected'): ?><?= $statusForm('rejected', 'Customer said no', 'btn-outline-danger', 'x') ?><?php endif; ?>
            <?php if ($q['status'] !== 'draft'): ?><?= $statusForm('draft', 'Back to draft', 'btn-ghost-secondary', 'refresh') ?><?php endif; ?>
          <?php else: ?>
            <div class="text-secondary small">Converted to a booking — status stays “Accepted”.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Booking -->
      <?php if ($booking !== null): ?>
        <div class="card mb-3 border-success">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2 text-success fw-semibold"><?= icon('check') ?> Booked</div>
            <div class="mb-2">This quotation became booking <strong><?= e($booking['number']) ?></strong>.</div>
            <?php if (feature('bookings') && can('bookings')): ?>
              <a class="btn btn-success w-100" href="<?= e(url('/bookings/' . (int) $booking['id'])) ?>"><?= icon('briefcase') ?> Open booking</a>
            <?php endif; ?>
          </div>
        </div>
      <?php elseif ($canConvert): ?>
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title"><?= icon('briefcase') ?> Confirm the trip</h3></div>
          <div class="card-body">
            <?php if ($hasDates): ?>
              <p class="text-secondary small mb-2">Customer agreed? Create a confirmed booking for <?= e(inr($q['total'])) ?> in one click. The quotation will be marked accepted.</p>
              <form method="post" action="<?= e(url($base . '/convert')) ?>" class="m-0" data-confirm="Create a confirmed booking from <?= e($q['number']) ?> for <?= e(inr($q['total'])) ?>?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary w-100"><?= icon('arrow-right') ?> Convert to booking</button>
              </form>
            <?php else: ?>
              <p class="text-secondary small mb-2">Add the trip start and end dates first — a booking needs fixed dates.</p>
              <a class="btn btn-outline-primary w-100" href="<?= e(url($base . '/edit')) ?>"><?= icon('calendar') ?> Add travel dates</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Customer -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('user') ?> Customer</h3></div>
        <div class="card-body">
          <div class="fw-semibold"><?= e($q['customer_name'] ?? '—') ?></div>
          <?php if ($hasPhone): ?><div><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $q['customer_phone'])) ?>"><?= icon('phone', 'icon icon-sm') ?> <?= e($q['customer_phone']) ?></a></div><?php endif; ?>
          <?php if ($hasEmail): ?><div><a href="mailto:<?= e($q['customer_email']) ?>"><?= icon('mail', 'icon icon-sm') ?> <?= e($q['customer_email']) ?></a></div><?php endif; ?>
          <?php if (!empty($q['customer_city'])): ?><div class="text-secondary small"><?= e($q['customer_city']) ?></div><?php endif; ?>
          <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if (feature('customers') && can('customers')): ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/customers/' . (int) $q['customer_id'])) ?>">Customer profile</a>
            <?php endif; ?>
            <?php if ($enquiry !== null && can('enquiries')): ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/enquiries/' . (int) $enquiry['id'])) ?>">Original enquiry</a>
            <?php endif; ?>
            <?php if ($package !== null && can('website')): ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/packages/' . (int) $package['id'] . '/edit')) ?>">Package: <?= e(str_limit((string) $package['title'], 24)) ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- More -->
      <div class="card mb-3">
        <div class="card-body d-grid gap-2">
          <form method="post" action="<?= e(url($base . '/duplicate')) ?>" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-secondary w-100"><?= icon('copy') ?> Duplicate as new quotation</button>
          </form>
          <?php if ($booking === null): ?>
            <form method="post" action="<?= e(url($base)) ?>" class="m-0" data-confirm="Delete quotation <?= e($q['number']) ?>? The share link will stop working. This cannot be undone.">
              <?= csrf_field() ?>
              <?= method_field('DELETE') ?>
              <button type="submit" class="btn btn-ghost-danger w-100"><?= icon('trash') ?> Delete quotation</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
