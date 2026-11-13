<?php
/**
 * @var array{from:string,to:string,method:string,q:string} $filters
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int} $result
 * @var array{total:float,count:int,methods:array<string,array{amount:float,count:int}>} $summary
 */
use App\Repositories\PaymentRepository as Pay;

$qs = static function (array $extra = []) use ($filters): string {
    $params = array_filter([...$filters, ...$extra], static fn ($v) => $v !== null);
    return '?' . http_build_query($params);
};
$rows = $result['rows'];
$pageSum = array_sum(array_map(static fn ($r) => (float) $r['amount'], $rows));
$rangeLabel = match (true) {
    $filters['from'] !== '' && $filters['to'] !== '' => nice_day($filters['from']) . ' – ' . nice_day($filters['to']),
    $filters['from'] !== '' => 'From ' . nice_day($filters['from']),
    $filters['to'] !== '' => 'Up to ' . nice_day($filters['to']),
    default => 'All dates',
};
$thisMonth = ['from' => date('Y-m-01'), 'to' => date('Y-m-t')];
$lastMonth = ['from' => date('Y-m-01', strtotime('first day of last month')), 'to' => date('Y-m-t', strtotime('first day of last month'))];
$fy = (int) date('n') >= 4 ? (int) date('Y') : (int) date('Y') - 1;
$thisFy = ['from' => $fy . '-04-01', 'to' => ($fy + 1) . '-03-31'];
$quick = ['This month' => $thisMonth, 'Last month' => $lastMonth, 'This financial year' => $thisFy, 'All time' => ['from' => '', 'to' => '']];
?>
<div class="rth-bookings rth-pay-list">
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/payments')) ?>" class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label" for="p-from">From</label>
        <input type="date" id="p-from" name="from" class="form-control" value="<?= e($filters['from']) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="p-to">To</label>
        <input type="date" id="p-to" name="to" class="form-control" value="<?= e($filters['to']) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="p-method">Method</label>
        <select id="p-method" name="method" class="form-select">
          <option value="">All methods</option>
          <?php foreach (Pay::METHODS as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $filters['method'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label" for="p-q">Search</label>
        <div class="input-icon">
          <span class="input-icon-addon"><?= icon('search') ?></span>
          <input type="search" id="p-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Receipt, booking, customer, reference">
        </div>
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill"><?= icon('filter') ?> Filter</button>
      </div>
    </form>
    <div class="d-flex flex-wrap gap-2 mt-3 small">
      <span class="text-secondary">Quick:</span>
      <?php foreach ($quick as $label => $range):
          $active = $filters['from'] === $range['from'] && $filters['to'] === $range['to'] && $filters['method'] === '' && $filters['q'] === ''; ?>
        <a href="<?= e(url('/payments?' . http_build_query($range))) ?>" class="badge <?= $active ? 'bg-primary text-white' : 'bg-secondary-lt' ?> text-decoration-none"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card card-sm h-100">
      <div class="card-body">
        <div class="small text-secondary"><?= e($rangeLabel) ?><?= $filters['method'] !== '' ? ' · ' . e(Pay::methodLabel($filters['method'])) : '' ?></div>
        <div class="h1 mb-0 mt-1"><?= e(inr($summary['total'])) ?></div>
        <div class="small text-secondary"><?= (int) $summary['count'] ?> payment<?= $summary['count'] === 1 ? '' : 's' ?> received</div>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card card-sm h-100">
      <div class="card-body">
        <div class="small text-secondary mb-2">By payment method</div>
        <?php if ($summary['methods'] === []): ?>
          <div class="text-secondary small">Nothing received in this period.</div>
        <?php else: ?>
          <div class="rth-pay-methods">
            <?php foreach ($summary['methods'] as $m => $s):
                $w = $summary['total'] > 0 ? (int) round($s['amount'] / $summary['total'] * 100) : 0; ?>
              <div class="rth-pay-method">
                <div class="d-flex justify-content-between small">
                  <a href="<?= e(url('/payments' . $qs(['method' => $m, 'page' => null]))) ?>" class="text-reset fw-semibold"><?= e(Pay::methodLabel($m)) ?></a>
                  <span><?= e(inr($s['amount'])) ?> <span class="text-secondary">· <?= (int) $s['count'] ?></span></span>
                </div>
                <div class="progress progress-sm"><div class="progress-bar rth-pay-bar-<?= e($m) ?>" style="width: <?= $w ?>%" role="presentation"></div></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <?php if ($rows === []): ?>
    <div class="card-body">
      <div class="empty py-5">
        <div class="empty-icon"><?= icon('cash') ?></div>
        <p class="empty-title">No payments in this period</p>
        <p class="empty-subtitle text-secondary">Payments are recorded from a booking’s page (“Record a payment”). Try another date range, or open a booking to add one.</p>
        <div class="empty-action d-flex gap-2 justify-content-center flex-wrap">
          <a href="<?= e(url('/payments?from=&to=')) ?>" class="btn">Show all time</a>
          <?php if (can('bookings') && feature('bookings')): ?><a href="<?= e(url('/bookings?due=1')) ?>" class="btn btn-primary">Bookings with balance due</a><?php endif; ?>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr><th>Date</th><th>Receipt no.</th><th>Booking</th><th>Customer</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th class="w-1"></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="text-nowrap"><?= e(nice_day((string) $r['paid_at'])) ?></td>
              <td class="text-nowrap fw-semibold"><?= e($r['receipt_no']) ?></td>
              <td>
                <?php if (feature('bookings') && can('bookings')): ?>
                  <a href="<?= e(url('/bookings/' . $r['booking_id'])) ?>" class="text-nowrap"><?= e($r['booking_number']) ?></a>
                <?php else: ?><span class="text-nowrap"><?= e($r['booking_number']) ?></span><?php endif; ?>
                <div class="small text-secondary"><?= e(str_limit((string) $r['booking_title'], 34)) ?></div>
              </td>
              <td>
                <?= e($r['customer_name']) ?>
                <div class="small text-secondary text-nowrap"><?= e($r['customer_phone']) ?></div>
              </td>
              <td class="text-nowrap"><?= e(Pay::methodLabel($r['method'])) ?></td>
              <td class="text-secondary small text-break"><?= e($r['reference'] ?: '—') ?></td>
              <td class="text-end text-nowrap fw-semibold"><?= e(inr2($r['amount'])) ?></td>
              <td><a href="<?= e(url('/payments/' . $r['id'] . '/receipt')) ?>" class="btn btn-sm btn-icon" title="Print receipt" aria-label="Print receipt <?= e($r['receipt_no']) ?>"><?= icon('printer') ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <?php if ($result['pages'] > 1): ?>
            <tr class="text-secondary"><td colspan="6" class="text-end">This page</td><td class="text-end text-nowrap"><?= e(inr2($pageSum)) ?></td><td></td></tr>
          <?php endif; ?>
          <tr class="rth-pay-total"><td colspan="6" class="text-end fw-bold">Total (<?= (int) $summary['count'] ?> payment<?= $summary['count'] === 1 ? '' : 's' ?>)</td><td class="text-end text-nowrap fw-bold"><?= e(inr2($summary['total'])) ?></td><td></td></tr>
        </tfoot>
      </table>
    </div>
    <?php if ($result['pages'] > 1): ?>
      <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small">Page <?= e((string) $result['page']) ?> of <?= e((string) $result['pages']) ?></p>
        <ul class="pagination m-0 ms-auto">
          <li class="page-item<?= $result['page'] <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(url('/payments') . $qs(['page' => $result['page'] - 1])) ?>">Previous</a></li>
          <?php for ($p = max(1, $result['page'] - 2), $end = min($result['pages'], $result['page'] + 2); $p <= $end; $p++): ?>
            <li class="page-item<?= $p === $result['page'] ? ' active' : '' ?>"><a class="page-link" href="<?= e(url('/payments') . $qs(['page' => $p])) ?>"><?= $p ?></a></li>
          <?php endfor; ?>
          <li class="page-item<?= $result['page'] >= $result['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(url('/payments') . $qs(['page' => $result['page'] + 1])) ?>">Next</a></li>
        </ul>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</div>
