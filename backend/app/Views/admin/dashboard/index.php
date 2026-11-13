<?php
/**
 * Dashboard. Every widget is optional (null = hidden by plan / role) and has its own empty state.
 * @var array<string,bool> $show
 * @var bool $isBasic
 * @var bool $showTeaser
 * @var list<array<string,mixed>> $kpis
 * @var array{labels:list<string>,collected:list<float>,booked:list<float>}|null $money
 * @var array{labels:list<string>,values:list<int>,total:int}|null $enqDaily
 * @var array{labels:list<string>,values:list<int>}|null $sources
 * @var array{rows:list<array<string,mixed>>,total:int,overdue:int}|null $tasks
 * @var list<array<string,mixed>>|null $departures
 * @var list<array<string,mixed>>|null $latest
 * @var list<array<string,mixed>>|null $payments
 * @var bool $alertsOn
 * @var list<array<string,string>> $alerts
 * @var int $alertsMore
 */
use App\Controllers\Admin\DashboardController as DashCtl;
use App\Repositories\EnquiryRepository as Enq;
use App\Support\Chart;

$today = date('Y-m-d');
$empty = static fn (string $icon, string $title, string $text, ?string $href = null, ?string $cta = null): string
    => partial('admin/dashboard/_empty', compact('icon', 'title', 'text', 'href', 'cta'));
$stages = (array) (config('crm')['stages'] ?? []);
$methods = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank transfer', 'card' => 'Card', 'cheque' => 'Cheque', 'other' => 'Other'];

// ── Widgets (HTML or null) ─────────────────────────────────────────────────────
$w = [];

if ($money !== null) {
    $collectedTotal = array_sum($money['collected']);
    $hasMoney = $collectedTotal > 0 || array_sum($money['booked']) > 0;
    $opts = ['money' => true, 'height' => 230];
    if ($show['bookings']) {
        $opts += ['series2' => $money['booked'], 'names' => ['Collected', 'Booked']];
    }
    ob_start(); ?>
    <div class="card h-100">
      <div class="card-header">
        <div>
          <h3 class="card-title">Collections</h3>
          <div class="card-subtitle">Money received each month<?= $show['bookings'] ? ' vs. new bookings' : '' ?> · last 6 months</div>
        </div>
        <div class="card-actions text-end">
          <div class="h3 m-0 rth-num"><?= e(inr($collectedTotal)) ?></div>
          <div class="small text-secondary">collected</div>
        </div>
      </div>
      <div class="card-body">
        <?php if (!$hasMoney): ?>
          <?= $empty('cash', 'No payments yet', 'When you record an advance or balance payment, your monthly collections chart will appear here.', '/payments', 'Record a payment') ?>
        <?php else: ?>
          <div class="rth-chart-wrap"><?= Chart::bars($money['labels'], $money['collected'], $opts) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php $w['money'] = (string) ob_get_clean();
}

if ($sources !== null) {
    ob_start(); ?>
    <div class="card h-100">
      <div class="card-header">
        <div>
          <h3 class="card-title">Lead sources</h3>
          <div class="card-subtitle">Where enquiries came from · last 90 days</div>
        </div>
      </div>
      <div class="card-body d-flex align-items-center">
        <?php if ($sources['values'] === []): ?>
          <div class="w-100"><?= $empty('target', 'No leads yet', 'Once enquiries arrive from your website, WhatsApp or phone, you will see which channel works best.') ?></div>
        <?php else: ?>
          <div class="w-100"><?= Chart::donut($sources['labels'], $sources['values'], ['center' => 'Leads']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php $w['sources'] = (string) ob_get_clean();
}

if ($enqDaily !== null) {
    ob_start(); ?>
    <div class="card h-100">
      <div class="card-header">
        <div>
          <h3 class="card-title">Enquiries</h3>
          <div class="card-subtitle">New enquiries per day · last 30 days</div>
        </div>
        <div class="card-actions text-end">
          <div class="h3 m-0 rth-num"><?= (int) $enqDaily['total'] ?></div>
          <div class="small text-secondary">in 30 days</div>
        </div>
      </div>
      <div class="card-body">
        <?php if ($enqDaily['total'] === 0): ?>
          <?= $empty('inbox', 'No enquiries in the last 30 days', 'When someone fills in the enquiry form on your website, it shows up here straight away.') ?>
        <?php else: ?>
          <div class="rth-chart-wrap"><?= Chart::line($enqDaily['labels'], $enqDaily['values'], ['height' => 230]) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php $w['enquiries'] = (string) ob_get_clean();
}

if ($alertsOn) {
    ob_start(); ?>
    <div class="card h-100">
      <div class="card-header">
        <div>
          <h3 class="card-title">Needs attention<?php if ($alerts !== []): ?><span class="rth-count rth-count-danger"><?= count($alerts) + $alertsMore ?></span><?php endif; ?></h3>
          <div class="card-subtitle">Dues, expiring quotes &amp; vehicle papers</div>
        </div>
      </div>
      <?php if ($alerts === []): ?>
        <div class="rth-allclear"><span class="rth-icon-tile"><?= icon('check') ?></span><span>All clear — nothing needs your attention right now.</span></div>
      <?php else: ?>
        <div class="rth-list rth-alert-list">
          <?php foreach ($alerts as $a): ?>
            <a class="rth-list-item" href="<?= e(url($a['href'])) ?>">
              <span class="rth-icon-tile rth-tone-<?= e($a['tone']) ?>"><?= icon($a['icon']) ?></span>
              <span class="rth-list-main">
                <span class="rth-list-title"><?= e($a['title']) ?></span>
                <span class="rth-list-meta"><?= e($a['meta']) ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
        <?php if ($alertsMore > 0): ?><div class="card-footer small text-secondary">+ <?= (int) $alertsMore ?> more</div><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php $w['alerts'] = (string) ob_get_clean();
} elseif ($showTeaser) {
    ob_start(); ?>
    <div class="card rth-teaser">
      <div class="card-body">
        <span class="rth-plan-crown"><?= icon('crown') ?></span>
        <div>
          <h3 class="rth-teaser-title">Unlock the Pro sales suite</h3>
          <p class="rth-teaser-text mt-1">Turn website enquiries into paid trips — without spreadsheets.</p>
        </div>
        <ul>
          <li><?= icon('check') ?><span>Lead pipeline with follow-up reminders</span></li>
          <li><?= icon('check') ?><span>Day-wise quotations shared on WhatsApp</span></li>
          <li><?= icon('check') ?><span>Bookings, payments &amp; receipts</span></li>
          <li><?= icon('check') ?><span>Collections, dues &amp; conversion reports</span></li>
        </ul>
        <a href="<?= e(url('/license')) ?>" class="btn btn-gold"><?= icon('sparkles') ?> See plans &amp; upgrade</a>
      </div>
    </div>
    <?php $w['alerts'] = (string) ob_get_clean();
}

if ($tasks !== null) {
    $relLinks = [
        'enquiry' => ['/enquiries/', $show['enquiries']],
        'customer' => ['/customers/', $show['customers']],
        'quotation' => ['/quotations/', $show['quotations']],
        'booking' => ['/bookings/', $show['bookings']],
    ];
    ob_start(); ?>
    <div class="card h-100">
      <div class="card-header">
        <div>
          <h3 class="card-title">Follow-ups due<?php if ($tasks['total'] > 0): ?><span class="rth-count<?= $tasks['overdue'] > 0 ? ' rth-count-danger' : '' ?>"><?= (int) $tasks['total'] ?></span><?php endif; ?></h3>
          <div class="card-subtitle">Today<?= $tasks['overdue'] > 0 ? ' · ' . (int) $tasks['overdue'] . ' overdue' : '' ?></div>
        </div>
        <div class="card-actions"><a href="<?= e(url('/tasks')) ?>" class="rth-section-link">All follow-ups <?= icon('chevron-right') ?></a></div>
      </div>
      <?php if ($tasks['rows'] === []): ?>
        <?= $empty('bell', 'You are all caught up', 'No calls or reminders due today. Add a follow-up from any lead so you never miss a call back.', '/tasks', 'Add a follow-up') ?>
      <?php else: ?>
        <div class="rth-list">
          <?php foreach ($tasks['rows'] as $t):
              $due = (string) $t['due_at'];
              $overdue = $due < $today . ' 00:00:00';
              [$prefix, $allowed] = $relLinks[(string) $t['related_type']] ?? ['', false];
              $href = ($allowed && $t['related_id']) ? $prefix . (int) $t['related_id'] : '/tasks';
          ?>
            <a class="rth-list-item" href="<?= e(url($href)) ?>">
              <span class="rth-icon-tile <?= $overdue ? 'rth-tone-danger' : 'rth-tone-gold' ?>"><?= icon($overdue ? 'alert' : 'bell') ?></span>
              <span class="rth-list-main">
                <span class="rth-list-title"><?= e($t['title']) ?></span>
                <span class="rth-list-meta"><?= e($t['assignee'] ?? 'Unassigned') ?><?= $t['related_type'] !== 'none' ? ' · ' . e(ucfirst((string) $t['related_type'])) : '' ?></span>
              </span>
              <span class="rth-list-side">
                <?php if ($overdue): ?>
                  <span class="rth-pill rth-pill-danger"><?= e(DashCtl::relDay(substr($due, 0, 10), $today)) ?></span>
                <?php else: ?>
                  <span class="rth-pill rth-pill-gold"><?= e(date('g:i a', (int) strtotime($due))) ?></span>
                <?php endif; ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php $w['tasks'] = (string) ob_get_clean();
}

if ($departures !== null) {
    ob_start(); ?>
    <div class="card h-100">
      <div class="card-header">
        <div>
          <h3 class="card-title">Upcoming departures</h3>
          <div class="card-subtitle">Trips starting in the next 14 days</div>
        </div>
        <div class="card-actions"><a href="<?= e(url(feature('calendar') ? '/calendar' : '/bookings')) ?>" class="rth-section-link"><?= feature('calendar') ? 'Trip calendar' : 'All bookings' ?> <?= icon('chevron-right') ?></a></div>
      </div>
      <?php if ($departures === []): ?>
        <?= $empty('plane', 'No trips in the next two weeks', 'Confirmed bookings starting soon will be listed here with their balance due.', '/bookings/create', 'New booking') ?>
      <?php else: ?>
        <div class="rth-list">
          <?php foreach ($departures as $b):
              $ts = (int) strtotime((string) $b['travel_start']);
              $nights = max(0, (int) round(((int) strtotime((string) $b['travel_end']) - $ts) / 86400));
              $pax = (int) $b['adults'] + (int) $b['children'];
          ?>
            <a class="rth-list-item" href="<?= e(url('/bookings/' . (int) $b['id'])) ?>">
              <span class="rth-date-tile"><span class="rth-date-tile-m"><?= e(date('M', $ts)) ?></span><span class="rth-date-tile-d"><?= e(date('j', $ts)) ?></span></span>
              <span class="rth-list-main">
                <span class="rth-list-title"><?= e($b['customer_name'] ?? 'Customer') ?> · <?= e($b['title']) ?></span>
                <span class="rth-list-meta"><?= e($b['number']) ?> · <?= $pax ?> pax · <?= $nights ?>N · <?= e(DashCtl::relDay((string) $b['travel_start'], $today)) ?></span>
              </span>
              <span class="rth-list-side">
                <?php if ($show['payments']): ?>
                  <?php if ($b['balance'] > 0.009): ?>
                    <span class="rth-pill rth-pill-warn"><?= e(inr($b['balance'])) ?> due</span>
                  <?php else: ?>
                    <span class="rth-pill rth-pill-ok"><?= icon('check', 'icon') ?> Paid</span>
                  <?php endif; ?>
                <?php else: ?>
                  <?= status_badge((string) $b['status']) ?>
                <?php endif; ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php $w['departures'] = (string) ob_get_clean();
}

if ($latest !== null) {
    ob_start(); ?>
    <div class="card h-100">
      <div class="card-header">
        <div>
          <h3 class="card-title">Latest enquiries</h3>
          <div class="card-subtitle">Newest first</div>
        </div>
        <div class="card-actions"><a href="<?= e(url($show['crm'] ? '/leads' : '/enquiries')) ?>" class="rth-section-link"><?= $show['crm'] ? 'Lead pipeline' : 'All enquiries' ?> <?= icon('chevron-right') ?></a></div>
      </div>
      <?php if ($latest === []): ?>
        <?= $empty('inbox', 'No enquiries yet', 'When someone fills in the enquiry form on your website, it will appear here straight away.') ?>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Name</th><th>Trip</th><th><?= $show['crm'] ? 'Stage' : 'Status' ?></th><th class="text-end">Received</th></tr></thead>
            <tbody>
              <?php foreach ($latest as $r): ?>
                <tr>
                  <td>
                    <a href="<?= e(url('/enquiries/' . (int) $r['id'])) ?>" class="fw-semibold text-reset"><?= e($r['name']) ?></a>
                    <?php if ($show['crm'] && $r['priority'] === 'high'): ?><span class="rth-pill rth-pill-danger ms-1">High</span><?php endif; ?>
                    <div class="small text-secondary text-nowrap">
                      <?= e($r['phone']) ?>
                      <a href="<?= e(Enq::whatsappUrl((string) $r['phone'])) ?>" class="ms-1 text-green" target="_blank" rel="noopener" title="Chat on WhatsApp" aria-label="Chat with <?= e($r['name']) ?> on WhatsApp"><?= icon('whatsapp') ?></a>
                    </div>
                  </td>
                  <td class="text-secondary"><?= e(str_limit(Enq::tripLabel($r), 38)) ?><div class="small"><?= e(Enq::sourceLabel((string) $r['source'])) ?></div></td>
                  <td>
                    <?php if ($show['crm']): ?>
                      <?= status_badge((string) $r['stage'], (string) ($stages[$r['stage']]['label'] ?? ucfirst((string) $r['stage']))) ?>
                    <?php else: ?>
                      <?= status_badge((string) $r['status'], Enq::statusLabel((string) $r['status'])) ?>
                    <?php endif; ?>
                  </td>
                  <td class="text-secondary text-nowrap small text-end"><?= e(nice_date((string) $r['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php $w['latest'] = (string) ob_get_clean();
}

if ($payments !== null) {
    ob_start(); ?>
    <div class="card h-100">
      <div class="card-header">
        <div>
          <h3 class="card-title">Recent payments</h3>
          <div class="card-subtitle">Latest money received</div>
        </div>
        <div class="card-actions"><a href="<?= e(url('/payments')) ?>" class="rth-section-link">All payments <?= icon('chevron-right') ?></a></div>
      </div>
      <?php if ($payments === []): ?>
        <?= $empty('wallet', 'No payments recorded', 'Record advances and balances (UPI, cash or bank) to print receipts and track dues.', '/payments', 'Record a payment') ?>
      <?php else: ?>
        <div class="rth-list">
          <?php foreach ($payments as $p): ?>
            <a class="rth-list-item" href="<?= e(url($show['bookings'] && $p['booking_id'] ? '/bookings/' . (int) $p['booking_id'] : '/payments')) ?>">
              <span class="rth-icon-tile rth-tone-green"><?= icon('cash') ?></span>
              <span class="rth-list-main">
                <span class="rth-list-title"><span class="rth-num"><?= e(inr($p['amount'])) ?></span> · <?= e($p['customer_name'] ?? 'Customer') ?></span>
                <span class="rth-list-meta"><?= e($p['receipt_no']) ?> · <?= e($methods[$p['method']] ?? ucfirst((string) $p['method'])) ?><?= $p['booking_number'] ? ' · ' . e($p['booking_number']) : '' ?></span>
              </span>
              <span class="rth-list-side text-secondary text-nowrap"><?= e(nice_day((string) $p['paid_at'])) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php $w['payments'] = (string) ob_get_clean();
}

// Rows of two: [left, right, left-col, right-col]; a lone widget takes the full width.
$rows = [
    ['money', 'sources', 'col-lg-8', 'col-lg-4'],
    ['enquiries', 'alerts', 'col-lg-8', 'col-lg-4'],
    ['tasks', 'departures', 'col-lg-6', 'col-lg-6'],
    ['latest', 'payments', 'col-lg-7', 'col-lg-5'],
];
$kpiCount = count($kpis);
$kpiCol = match (true) {
    $kpiCount >= 6 => 'col-6 col-md-4 col-xxl-2',
    $kpiCount === 5 => 'col-6 col-md-4 col-xl',
    $kpiCount === 4 => 'col-6 col-xl-3',
    $kpiCount === 3 => 'col-12 col-md-4',
    default => 'col-12 col-md-6',
};
?>
<p class="rth-dash-intro"><?= $isBasic ? 'Here is how your website is doing today.' : 'Here is what is happening in your business today.' ?></p>

<?php if ($kpis !== []): ?>
  <div class="row row-cards mb-3">
    <?php foreach ($kpis as $k): ?>
      <div class="<?= e($kpiCol) ?>">
        <a href="<?= e(url($k['href'])) ?>" class="card rth-kpi rth-tone-<?= e($k['tone']) ?>">
          <div class="card-body">
            <div class="rth-kpi-head">
              <span class="rth-kpi-label"><?= e($k['label']) ?></span>
              <span class="rth-kpi-icon"><?= icon($k['icon']) ?></span>
            </div>
            <div class="rth-kpi-value" title="<?= e($k['value']) ?>"><?= e($k['value']) ?></div>
            <?php if ($k['progress'] !== null): ?>
              <div class="rth-kpi-bar" role="img" aria-label="<?= (int) $k['progress'] ?> percent"><span style="width: <?= max(0, min(100, (int) $k['progress'])) ?>%"></span></div>
            <?php endif; ?>
            <div class="rth-kpi-foot">
              <?php if ($k['trend'] !== null): ?>
                <span class="rth-trend rth-trend-<?= e($k['trend']['dir']) ?>"><?= $k['trend']['dir'] === 'up' ? icon('arrow-up') : ($k['trend']['dir'] === 'down' ? icon('arrow-down') : '') ?><?= e($k['trend']['text']) ?></span>
                <span><?= e($k['trendNote']) ?></span>
              <?php else: ?>
                <span><?= e($k['hint']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php foreach ($rows as [$left, $right, $lc, $rc]):
    $l = $w[$left] ?? null;
    $r = $w[$right] ?? null;
    if ($l === null && $r === null) {
        continue;
    }
?>
  <div class="row row-cards mb-3">
    <?php if ($l !== null): ?><div class="<?= e($r !== null ? $lc : 'col-12') ?>"><?= $l ?></div><?php endif; ?>
    <?php if ($r !== null): ?><div class="<?= e($l !== null ? $rc : 'col-12') ?>"><?= $r ?></div><?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ($kpis === [] && $w === []): ?>
  <div class="card">
    <?= $empty('dashboard', 'Welcome aboard', 'Your role does not include any dashboard widgets yet. Use the menu on the left to get started, or ask the owner for access.') ?>
  </div>
<?php endif; ?>
