<?php
/**
 * @var array<string,int> $stats
 * @var list<array<string,mixed>> $recent
 * @var list<array<string,mixed>> $activity
 * @var string $websiteUrl
 */
use App\Repositories\EnquiryRepository as Enq;

$cards = [
    ['New enquiries', $stats['newEnquiries'], $stats['newEnquiries'] === 1 ? 'waiting for a reply' : 'waiting for a reply', '/enquiries?status=new', 'inbox', 'bg-blue text-white'],
    ['Last 30 days', $stats['enquiries30'], $stats['enquiries30'] === 1 ? 'enquiry received' : 'enquiries received', '/enquiries?from=' . date('Y-m-d', strtotime('-30 days')), 'calendar', 'bg-cyan text-white'],
    ['Packages', $stats['packagesPublished'], 'live' . ($stats['packagesDraft'] > 0 ? ' · ' . $stats['packagesDraft'] . ' draft' . ($stats['packagesDraft'] === 1 ? '' : 's') : ''), '/packages', 'package', 'bg-primary text-white'],
    ['Destinations', $stats['destinations'], $stats['destinations'] === 1 ? 'place' : 'places', '/destinations', 'map-pin', 'bg-green text-white'],
    ['Reviews', $stats['reviews'], $stats['reviews'] === 1 ? 'customer review' : 'customer reviews', '/testimonials', 'star', 'bg-yellow text-white'],
];
$activityVerb = [
    'created' => 'bg-green-lt', 'updated' => 'bg-blue-lt', 'deleted' => 'bg-red-lt',
    'login' => 'bg-secondary-lt', 'exported' => 'bg-purple-lt',
];
?>
<p class="text-secondary mb-4">Here is what is happening with your website today.</p>

<div class="row row-cards mb-3">
  <?php foreach ($cards as [$label, $value, $hint, $href, $ico, $iconClass]): ?>
    <div class="col-sm-6 col-lg">
      <a href="<?= e(url($href)) ?>" class="card card-sm card-link text-reset text-decoration-none h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <span class="avatar <?= e($iconClass) ?>"><?= icon($ico) ?></span>
            <div>
              <div class="text-secondary small"><?= e($label) ?></div>
              <div class="h2 mb-0"><?= e((string) $value) ?></div>
              <div class="text-secondary small"><?= e($hint) ?></div>
            </div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row row-cards">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Recent enquiries</h3>
        <div class="card-actions">
          <a href="<?= e(url('/enquiries')) ?>" class="btn btn-sm">See all</a>
        </div>
      </div>
      <?php if ($recent === []): ?>
        <div class="card-body">
          <div class="empty py-4">
            <div class="empty-icon"><?= icon('inbox') ?></div>
            <p class="empty-title">No enquiries yet</p>
            <p class="empty-subtitle text-secondary">When someone fills in the enquiry form on your website, it will appear here straight away.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr><th>Name</th><th>Contact</th><th>Trip</th><th>Status</th><th>Received</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
                <tr>
                  <td><a href="<?= e(url('/enquiries/' . $r['id'])) ?>" class="fw-semibold"><?= e($r['name']) ?></a></td>
                  <td class="text-nowrap">
                    <a href="<?= e(Enq::telHref($r['phone'])) ?>" class="text-reset"><?= icon('phone', 'icon text-secondary') ?> <?= e($r['phone']) ?></a>
                    <a href="<?= e(Enq::whatsappUrl($r['phone'])) ?>" class="ms-2 text-green" target="_blank" rel="noopener" title="Chat on WhatsApp" aria-label="Chat with <?= e($r['name']) ?> on WhatsApp"><?= icon('whatsapp') ?></a>
                  </td>
                  <td class="text-secondary"><?= e(str_limit(Enq::tripLabel($r), 40)) ?></td>
                  <td><span class="badge <?= e(Enq::statusClass((string) $r['status'])) ?>"><?= e(Enq::statusLabel((string) $r['status'])) ?></span></td>
                  <td class="text-secondary text-nowrap small"><?= e(nice_date($r['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Quick actions</h3></div>
      <div class="card-body d-grid gap-2">
        <a href="<?= e(url('/packages/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> New package</a>
        <?php if ($websiteUrl !== ''): ?>
          <a href="<?= e($websiteUrl) ?>" class="btn" target="_blank" rel="noopener"><?= icon('external') ?> View website</a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/site/refresh')) ?>" class="d-grid m-0">
          <?= csrf_field() ?>
          <button type="submit" class="btn" title="Push the latest changes to the live website now"><?= icon('refresh') ?> Update website now</button>
        </form>
        <div class="form-hint">Changes usually go live by themselves within seconds. Use “Update website now” if something looks out of date.</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Recent activity</h3></div>
      <?php if ($activity === []): ?>
        <div class="card-body text-secondary">Nothing yet — edits made in this panel will be listed here.</div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($activity as $a): ?>
            <div class="list-group-item">
              <div class="d-flex gap-2 align-items-start">
                <span class="badge <?= e($activityVerb[$a['action']] ?? 'bg-secondary-lt') ?> mt-1"><?= e(ucfirst((string) $a['action'])) ?></span>
                <div class="min-w-0">
                  <div class="text-truncate" title="<?= e($a['summary']) ?>"><?= e($a['summary'] !== '' ? $a['summary'] : ucfirst((string) $a['entity'])) ?></div>
                  <div class="small text-secondary"><?= e($a['admin_name'] ?? 'A removed user') ?> · <?= e(nice_date($a['created_at'])) ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
