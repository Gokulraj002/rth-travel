<?php
/**
 * Traveller-facing booking confirmation voucher (layouts/print).
 * @var array<string,mixed> $booking
 * @var list<array<string,mixed>> $travellers
 * @var list<array<string,mixed>> $itinerary
 * @var array{inclusions:list<string>,exclusions:list<string>} $lists
 */
use App\Core\Template;
use App\Repositories\BookingRepository as Bk;

$b = $booking;
$co = Template::companyVars();
$address = setting('company_address');
$phone2 = setting('phone_secondary');
$whatsapp = setting('whatsapp_number');
$gstin = setting('company_gstin');
$staffPhone = (string) ($b['assigned_phone'] ?? '');
$balance = (float) $b['balance'];
?>
<div class="rth-doc rth-voucher">
  <header class="rth-doc-head">
    <div class="rth-doc-brand">
      <img src="<?= e(asset('/assets/admin/logo.jpeg')) ?>" alt="">
      <div>
        <div class="name"><?= e($co['company_name'] ?: 'Reyaan Trip Holiday') ?></div>
        <div class="meta">
          <?php if ($address !== ''): ?><?= e($address) ?><br><?php endif; ?>
          <?php if ($co['company_phone'] !== ''): ?><?= e($co['company_phone']) ?><?= $phone2 !== '' ? ' · ' . e($phone2) : '' ?><br><?php endif; ?>
          <?= e($co['company_email']) ?><?= $co['company_website'] !== '' ? ' · ' . e(preg_replace('#^https?://#', '', $co['company_website'])) : '' ?>
          <?php if ($gstin !== ''): ?><br>GSTIN <?= e($gstin) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="rth-doc-title">
      <h1>Booking voucher</h1>
      <div class="no"><?= e($b['number']) ?></div>
      <div class="meta">Issued <?= e(nice_day(date('Y-m-d'))) ?></div>
      <div class="rth-voucher-status is-<?= e((string) $b['status']) ?>"><?= e($b['status'] === 'cancelled' ? 'Cancelled' : ($b['status'] === 'pending' ? 'Pending confirmation' : 'Confirmed')) ?></div>
    </div>
  </header>

  <p class="rth-voucher-greet">Dear <strong><?= e($b['customer_name']) ?></strong>, thank you for booking with us. Please keep this voucher handy during your trip and show it at check-in when asked.</p>

  <div class="rth-voucher-grid">
    <div class="rth-doc-box">
      <div class="rth-voucher-label">Trip</div>
      <div class="rth-voucher-value"><?= e($b['title']) ?></div>
      <div class="rth-voucher-sub"><?= e(Bk::duration($b)) ?></div>
    </div>
    <div class="rth-doc-box">
      <div class="rth-voucher-label">Travel dates</div>
      <div class="rth-voucher-value"><?= e(nice_day((string) $b['travel_start'])) ?> → <?= e(nice_day((string) $b['travel_end'])) ?></div>
      <div class="rth-voucher-sub"><?= e(date('l', strtotime((string) $b['travel_start']))) ?> to <?= e(date('l', strtotime((string) $b['travel_end']))) ?></div>
    </div>
    <div class="rth-doc-box">
      <div class="rth-voucher-label">Guests</div>
      <div class="rth-voucher-value"><?= e(Bk::people($b)) ?></div>
      <div class="rth-voucher-sub">Lead guest: <?= e($b['customer_name']) ?> · <?= e($b['customer_phone']) ?></div>
    </div>
  </div>

  <div class="rth-voucher-grid rth-voucher-grid-2">
    <div class="rth-doc-box">
      <div class="rth-voucher-label">Pickup</div>
      <div class="rth-voucher-value"><?= e($b['pickup_point'] ?: 'To be confirmed') ?></div>
      <div class="rth-voucher-sub">on <?= e(nice_day((string) $b['travel_start'])) ?></div>
    </div>
    <div class="rth-doc-box">
      <div class="rth-voucher-label">Drop</div>
      <div class="rth-voucher-value"><?= e($b['drop_point'] ?: 'To be confirmed') ?></div>
      <div class="rth-voucher-sub">on <?= e(nice_day((string) $b['travel_end'])) ?></div>
    </div>
  </div>

  <?php if ($travellers !== []): ?>
    <h2>Travellers</h2>
    <table class="rth-doc-table">
      <thead><tr><th>#</th><th>Name</th><th>Age</th><th>Gender</th><th>ID proof</th></tr></thead>
      <tbody>
        <?php foreach ($travellers as $i => $t): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= e($t['name']) ?></strong></td>
            <td><?= $t['age'] !== null ? (int) $t['age'] : '—' ?></td>
            <td><?= e(Bk::GENDERS[(string) $t['gender']] ?? '—') ?></td>
            <td><?= e($t['id_proof'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($itinerary !== []): ?>
    <h2>Day-wise itinerary</h2>
    <ol class="rth-voucher-days">
      <?php foreach ($itinerary as $i => $d):
          $date = date('Y-m-d', strtotime((string) $b['travel_start'] . ' +' . max(0, (int) $d['day'] - 1) . ' days')); ?>
        <li>
          <div class="rth-voucher-day">
            <span class="d">Day <?= (int) $d['day'] ?></span>
            <span class="dt"><?= e(date('D, j M', strtotime($date))) ?></span>
          </div>
          <div class="rth-voucher-daybody">
            <div class="t"><?= e($d['title']) ?></div>
            <?php if (trim((string) $d['description']) !== ''): ?><div class="desc"><?= nl2br(e($d['description'])) ?></div><?php endif; ?>
            <?php if (!empty($d['stay'])): ?><div class="stay">Overnight: <?= e($d['stay']) ?></div><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?php if ($lists['inclusions'] !== [] || $lists['exclusions'] !== []): ?>
    <div class="rth-voucher-grid rth-voucher-grid-2">
      <?php if ($lists['inclusions'] !== []): ?>
        <div>
          <h2>Inclusions</h2>
          <ul class="rth-voucher-list is-in"><?php foreach ($lists['inclusions'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
      <?php if ($lists['exclusions'] !== []): ?>
        <div>
          <h2>Not included</h2>
          <ul class="rth-voucher-list is-out"><?php foreach ($lists['exclusions'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($b['special_requests'])): ?>
    <h2>Special requests</h2>
    <div class="rth-doc-box rth-voucher-pre"><?= e($b['special_requests']) ?></div>
  <?php endif; ?>

  <h2>Payment summary</h2>
  <div class="rth-doc-totals">
    <div><span>Package total</span><span><?= e(inr2($b['total_amount'])) ?></span></div>
    <div><span>Received</span><span><?= e(inr2($b['paid'])) ?></span></div>
    <div class="grand"><span><?= $balance > 0.009 ? 'Balance due' : 'Balance' ?></span><span><?= e(inr2(max(0, $balance))) ?></span></div>
  </div>

  <h2>Contact during your trip</h2>
  <div class="rth-voucher-grid">
    <div class="rth-doc-box">
      <div class="rth-voucher-label">Office</div>
      <div class="rth-voucher-value"><?= e($co['company_phone'] ?: '—') ?></div>
      <?php if ($phone2 !== ''): ?><div class="rth-voucher-sub"><?= e($phone2) ?></div><?php endif; ?>
    </div>
    <?php if ($whatsapp !== ''): ?>
      <div class="rth-doc-box">
        <div class="rth-voucher-label">WhatsApp</div>
        <div class="rth-voucher-value">+<?= e(wa_phone($whatsapp)) ?></div>
      </div>
    <?php endif; ?>
    <?php if (!empty($b['assigned_name'])): ?>
      <div class="rth-doc-box">
        <div class="rth-voucher-label">Your trip coordinator</div>
        <div class="rth-voucher-value"><?= e($b['assigned_name']) ?></div>
        <?php if ($staffPhone !== ''): ?><div class="rth-voucher-sub"><?= e($staffPhone) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <footer class="rth-doc-foot">
    <strong>Good to know:</strong> carry a government photo ID for every traveller (hotels and checkpoints in J&amp;K ask for it), warm clothes even in summer at high altitude,
    and some cash — card and UPI may not work in remote valleys. Driver details are shared one day before pickup.
    <br>This is a computer-generated voucher from <?= e($co['company_name'] ?: 'Reyaan Trip Holiday') ?>.
  </footer>
</div>
