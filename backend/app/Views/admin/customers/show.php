<?php
/**
 * Customer profile.
 * @var array<string,mixed> $customer
 * @var array{trips:int,total:float,paid:float,balance:float} $totals
 * @var list<array<string,mixed>> $bookings
 * @var list<array<string,mixed>> $quotations
 * @var list<array<string,mixed>> $enquiries
 * @var list<array<string,mixed>> $activities
 * @var list<array<string,mixed>> $tasks
 * @var array{bookings:int,quotations:int,invoices:int} $dependants
 */
use App\Repositories\CustomerRepository as C;
use App\Repositories\EnquiryRepository as Enq;

$c = $customer;
$cid = (int) $c['id'];
$first = explode(' ', trim((string) $c['name']))[0] ?: (string) $c['name'];
$canQuote = feature('quotations') && can('quotations');
$canBook = feature('bookings') && can('bookings');
$crm = feature('crm') && can('enquiries');
$blocked = $dependants['bookings'] + $dependants['quotations'] + $dependants['invoices'] > 0;
$dates = [];
foreach (['date_of_birth' => 'Birthday', 'anniversary' => 'Anniversary'] as $k => $label) {
    if (!empty($c[$k])) {
        $days = C::daysUntil((string) $c[$k]);
        $dates[] = [$label, date('j F', (int) strtotime((string) $c[$k])), $days];
    }
}
?>
<div class="mb-3">
  <a href="<?= e(url('/customers')) ?>" class="text-secondary">&larr; All customers</a>
</div>

<div class="row row-cards">
  <div class="col-lg-4">
    <div class="card mb-3 rth-crm-contact">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <span class="avatar rth-avatar"><?= e(mb_strtoupper(mb_substr((string) $c['name'], 0, 1))) ?></span>
          <div class="min-w-0">
            <h2 class="h3 mb-1 text-truncate"><?= e($c['name']) ?></h2>
            <div>
              <?php foreach (C::tagList($c['tags']) as $t): ?>
                <a href="<?= e(url('/customers?tag=' . rawurlencode($t))) ?>" class="badge bg-azure-lt rth-crm-tag text-decoration-none"><?= e($t) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <a href="<?= e(C::telHref($c['phone'])) ?>" class="btn btn-primary flex-fill"><?= icon('phone') ?> Call</a>
          <a href="<?= e(wa_link((string) $c['phone'], 'Hi ' . $first . ', ')) ?>" class="btn btn-success flex-fill" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a>
          <?php if (!empty($c['email'])): ?>
            <a href="mailto:<?= e(str_replace('%40', '@', rawurlencode((string) $c['email']))) ?>" class="btn flex-fill"><?= icon('mail') ?> Email</a>
          <?php endif; ?>
        </div>
        <dl class="row mb-0 small">
          <dt class="col-5 text-secondary fw-normal">Phone</dt><dd class="col-7"><?= e(C::displayPhone($c['phone'])) ?></dd>
          <?php if (!empty($c['email'])): ?><dt class="col-5 text-secondary fw-normal">Email</dt><dd class="col-7 text-break"><?= e($c['email']) ?></dd><?php endif; ?>
          <dt class="col-5 text-secondary fw-normal">City</dt><dd class="col-7"><?= e($c['city'] ?: '—') ?></dd>
          <?php if (!empty($c['address'])): ?><dt class="col-5 text-secondary fw-normal">Address</dt><dd class="col-7"><?= e($c['address']) ?></dd><?php endif; ?>
          <dt class="col-5 text-secondary fw-normal">Came from</dt><dd class="col-7"><?= e(C::sourceLabel($c['source'])) ?></dd>
          <?php foreach ($dates as [$label, $day, $days]): ?>
            <dt class="col-5 text-secondary fw-normal"><?= e($label) ?></dt>
            <dd class="col-7"><?= e($day) ?>
              <?php if ($days !== null && $days <= 30): ?>
                <span class="badge bg-pink-lt ms-1"><?= $days === 0 ? 'Today!' : 'in ' . e($days) . ' day' . ($days === 1 ? '' : 's') ?></span>
              <?php endif; ?>
            </dd>
          <?php endforeach; ?>
          <dt class="col-5 text-secondary fw-normal">Customer since</dt><dd class="col-7"><?= e(nice_day($c['created_at'])) ?></dd>
        </dl>
        <?php if (trim((string) $c['notes']) !== ''): ?>
          <hr class="my-3">
          <div class="small text-secondary mb-1">Notes</div>
          <div class="rth-crm-pre"><?= e($c['notes']) ?></div>
        <?php endif; ?>
      </div>
      <div class="card-footer d-flex gap-2">
        <a href="<?= e(url('/customers/' . $cid . '/edit')) ?>" class="btn btn-sm"><?= icon('edit', 'icon icon-sm') ?> Edit</a>
        <?php if ($crm): ?>
          <a href="<?= e(url('/tasks/create?related=customer:' . $cid)) ?>" class="btn btn-sm"><?= icon('bell', 'icon icon-sm') ?> Reminder</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($crm): ?>
      <?= partial('admin/leads/_tasks', ['tasks' => $tasks, 'returnTo' => '/customers/' . $cid, 'addUrl' => '/tasks/create?related=customer:' . $cid]) ?>
    <?php endif; ?>

    <?php if ($blocked): ?>
      <div class="card card-sm">
        <div class="card-body small text-secondary d-flex gap-2">
          <?= icon('lock') ?>
          <div>This customer can’t be deleted because they have
            <?= e(implode(' and ', array_filter([
                $dependants['bookings'] ? $dependants['bookings'] . ' booking' . ($dependants['bookings'] === 1 ? '' : 's') : '',
                $dependants['quotations'] ? $dependants['quotations'] . ' quotation' . ($dependants['quotations'] === 1 ? '' : 's') : '',
                $dependants['invoices'] ? $dependants['invoices'] . ' invoice' . ($dependants['invoices'] === 1 ? '' : 's') : '',
            ]))) ?> — that history must stay linked to them.</div>
        </div>
      </div>
    <?php else: ?>
      <form method="post" action="<?= e(url('/customers/' . $cid)) ?>" data-confirm="Delete <?= e($c['name']) ?>? Their enquiries will be kept, but their notes and profile will be removed. This cannot be undone.">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-outline-danger w-100"><?= icon('trash') ?> Delete customer</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <div class="row row-cards mb-3">
      <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Trips</div><div class="rth-crm-stat"><?= e($totals['trips']) ?></div>
      </div></div></div>
      <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Total business</div><div class="rth-crm-stat"><?= e(inr($totals['total'])) ?></div>
      </div></div></div>
      <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Paid</div><div class="rth-crm-stat text-success"><?= e(inr($totals['paid'])) ?></div>
      </div></div></div>
      <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Balance due</div><div class="rth-crm-stat<?= $totals['balance'] > 0.5 ? ' text-danger' : '' ?>"><?= e(inr($totals['balance'])) ?></div>
      </div></div></div>
    </div>

    <!-- Bookings -->
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><?= icon('briefcase') ?> Bookings</h3>
        <?php if ($canBook): ?><div class="card-actions"><a href="<?= e(url('/bookings/create?customer_id=' . $cid)) ?>" class="btn btn-sm btn-primary"><?= icon('plus', 'icon icon-sm') ?> New booking</a></div><?php endif; ?>
      </div>
      <?php if ($bookings === []): ?>
        <div class="card-body text-secondary small">No bookings yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Booking</th><th>Travel</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($bookings as $b): $bal = (float) $b['total_amount'] - (float) $b['paid']; ?>
                <tr>
                  <td>
                    <?php if ($canBook): ?><a href="<?= e(url('/bookings/' . $b['id'])) ?>" class="fw-semibold"><?= e($b['number']) ?></a><?php else: ?><span class="fw-semibold"><?= e($b['number']) ?></span><?php endif; ?>
                    <div class="small text-secondary text-truncate"><?= e(str_limit((string) $b['title'], 40)) ?></div>
                  </td>
                  <td class="small text-nowrap"><?= e(nice_day($b['travel_start'])) ?> – <?= e(nice_day($b['travel_end'])) ?></td>
                  <td class="text-end text-nowrap"><?= e(inr($b['total_amount'])) ?></td>
                  <td class="text-end text-nowrap text-success"><?= e(inr($b['paid'])) ?></td>
                  <td class="text-end text-nowrap<?= $bal > 0.5 && $b['status'] !== 'cancelled' ? ' text-danger' : ' text-secondary' ?>"><?= e(inr($bal)) ?></td>
                  <td><?= status_badge((string) $b['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quotations -->
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><?= icon('file-text') ?> Quotations</h3>
        <?php if ($canQuote): ?><div class="card-actions"><a href="<?= e(url('/quotations/create?customer_id=' . $cid)) ?>" class="btn btn-sm"><?= icon('plus', 'icon icon-sm') ?> New quotation</a></div><?php endif; ?>
      </div>
      <?php if ($quotations === []): ?>
        <div class="card-body text-secondary small">No quotations yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Quotation</th><th>Trip starts</th><th class="text-end">Amount</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
              <?php foreach ($quotations as $q): ?>
                <tr>
                  <td>
                    <?php if ($canQuote): ?><a href="<?= e(url('/quotations/' . $q['id'])) ?>" class="fw-semibold"><?= e($q['number']) ?></a><?php else: ?><span class="fw-semibold"><?= e($q['number']) ?></span><?php endif; ?>
                    <div class="small text-secondary"><?= e(str_limit((string) $q['title'], 40)) ?></div>
                  </td>
                  <td class="small text-nowrap"><?= e(nice_day($q['travel_start'])) ?></td>
                  <td class="text-end text-nowrap"><?= e(inr($q['total'])) ?></td>
                  <td><?= status_badge((string) $q['status']) ?></td>
                  <td class="small text-secondary text-nowrap"><?= e(nice_day($q['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Enquiries -->
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title"><?= icon('inbox') ?> Enquiries</h3></div>
      <?php if ($enquiries === []): ?>
        <div class="card-body text-secondary small">No enquiries linked to this customer.</div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($enquiries as $en): ?>
            <a href="<?= e(url('/enquiries/' . $en['id'])) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
              <div class="flex-fill min-w-0">
                <div class="fw-semibold text-truncate"><?= e(Enq::tripLabel($en)) ?></div>
                <div class="small text-secondary"><?= e(nice_date($en['created_at'])) ?> · <?= e(Enq::sourceLabel($en['source'])) ?><?= Enq::whenLabel($en) !== '' ? ' · travel ' . e(Enq::whenLabel($en)) : '' ?></div>
              </div>
              <?= feature('crm') ? status_badge((string) $en['stage'], Enq::stageLabel((string) $en['stage'])) : '<span class="badge ' . e(Enq::statusClass((string) $en['status'])) . '">' . e(Enq::statusLabel((string) $en['status'])) . '</span>' ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Timeline -->
    <div class="card" id="timeline">
      <div class="card-header"><h3 class="card-title"><?= icon('history') ?> Timeline</h3></div>
      <div class="card-body">
        <?= partial('admin/leads/_activity_form', ['action' => '/customers/' . $cid . '/activities']) ?>
        <?= partial('admin/leads/_timeline', ['activities' => $activities, 'showEnquiry' => true]) ?>
      </div>
    </div>
  </div>
</div>
