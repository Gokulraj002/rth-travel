<?php
/**
 * @var array{q:string,status:string,month:string,due:string,tab:string} $filters
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int} $result
 * @var array<string,int> $tabCounts
 */
use App\Repositories\BookingRepository as Bk;

$base = array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'month' => $filters['month'], 'due' => $filters['due']], static fn ($v) => $v !== '');
$qs = static function (array $extra = []) use ($base, $filters): string {
    $params = array_filter([...$base, 'tab' => $filters['tab'], ...$extra], static fn ($v) => $v !== '' && $v !== null);
    return $params === [] ? '' : '?' . http_build_query($params);
};
$rows = $result['rows'];
$from = $result['total'] === 0 ? 0 : ($result['page'] - 1) * $result['perPage'] + 1;
$to = min($result['total'], $result['page'] * $result['perPage']);
$hasFilters = $base !== [];
?>
<div class="rth-bookings">
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/bookings')) ?>" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="<?= e($filters['tab']) ?>">
      <div class="col-md-4">
        <label class="form-label" for="f-q">Search</label>
        <div class="input-icon">
          <span class="input-icon-addon"><?= icon('search') ?></span>
          <input type="search" id="f-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Booking no., customer, phone or trip">
        </div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="f-status">Status</label>
        <select id="f-status" name="status" class="form-select">
          <option value="">Any status</option>
          <?php foreach (Bk::STATUSES as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="f-month">Travel month</label>
        <input type="month" id="f-month" name="month" class="form-control" value="<?= e($filters['month']) ?>" placeholder="YYYY-MM">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="due" value="1"<?= $filters['due'] === '1' ? ' checked' : '' ?>>
          <span class="form-check-label">Balance due only</span>
        </label>
      </div>
      <div class="col-6 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill"><?= icon('filter') ?> Filter</button>
        <?php if ($hasFilters): ?>
          <a href="<?= e(url('/bookings?tab=' . $filters['tab'])) ?>" class="btn btn-icon" title="Clear filters" aria-label="Clear filters"><?= icon('x') ?></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <ul class="nav nav-tabs card-header-tabs rth-bk-tabs" role="tablist">
      <?php foreach (Bk::TABS as $key => $label): ?>
        <li class="nav-item">
          <a class="nav-link<?= $filters['tab'] === $key ? ' active' : '' ?>" href="<?= e(url('/bookings') . $qs(['tab' => $key, 'page' => null])) ?>"<?= $filters['tab'] === $key ? ' aria-current="page"' : '' ?>>
            <?= e($label) ?> <span class="badge bg-secondary-lt ms-1"><?= (int) ($tabCounts[$key] ?? 0) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php if ($rows === []): ?>
    <div class="card-body">
      <div class="empty py-5">
        <div class="empty-icon"><?= icon('briefcase') ?></div>
        <?php if ($hasFilters || $tabCounts['all'] > 0): ?>
          <p class="empty-title">No bookings here</p>
          <p class="empty-subtitle text-secondary">Nothing matches this tab<?= $hasFilters ? ' and these filters' : '' ?>. Try another tab or clear the filters.</p>
          <div class="empty-action"><a href="<?= e(url('/bookings?tab=all')) ?>" class="btn">Show all bookings</a></div>
        <?php else: ?>
          <p class="empty-title">No bookings yet</p>
          <p class="empty-subtitle text-secondary">When a customer confirms a trip, add it here to track travellers, pickup, payments and the balance due.</p>
          <div class="empty-action"><a href="<?= e(url('/bookings/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add your first booking</a></div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card-body py-2 border-bottom d-flex flex-wrap gap-2 align-items-center small text-secondary">
      <span><?= e((string) $result['total']) ?> booking<?= $result['total'] === 1 ? '' : 's' ?> · showing <?= e((string) $from) ?>–<?= e((string) $to) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table rth-bk-table">
        <thead>
          <tr>
            <th>Booking</th>
            <th>Customer</th>
            <th>Travel dates</th>
            <th class="text-center">Pax</th>
            <th class="text-end">Total</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Balance</th>
            <th>Status</th>
            <th class="w-1"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $hint = Bk::travelHint($r);
              $balance = (float) $r['balance'];
              $due = $balance > 0.009 && $r['status'] !== 'cancelled'; ?>
            <tr>
              <td>
                <a href="<?= e(url('/bookings/' . $r['id'])) ?>" class="fw-semibold text-nowrap"><?= e($r['number']) ?></a>
                <div class="small text-secondary text-truncate rth-bk-trip" title="<?= e($r['title']) ?>"><?= e(str_limit((string) $r['title'], 40)) ?></div>
              </td>
              <td>
                <div><?= e($r['customer_name']) ?></div>
                <div class="small text-nowrap">
                  <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $r['customer_phone'])) ?>" class="text-secondary"><?= e($r['customer_phone']) ?></a>
                  <a href="<?= e(wa_link((string) $r['customer_phone'])) ?>" class="ms-1 text-green" target="_blank" rel="noopener" title="Chat on WhatsApp" aria-label="WhatsApp <?= e($r['customer_name']) ?>"><?= icon('whatsapp', 'icon icon-sm') ?></a>
                </div>
              </td>
              <td class="text-nowrap">
                <?= e(Bk::dateRange((string) $r['travel_start'], (string) $r['travel_end'])) ?>
                <div class="small">
                  <span class="text-secondary"><?= e(Bk::duration($r)) ?></span>
                  <?php if ($hint['text'] !== ''): ?><span class="rth-bk-hint is-<?= e($hint['tone']) ?>"><?= e($hint['text']) ?></span><?php endif; ?>
                </div>
              </td>
              <td class="text-center text-nowrap" title="<?= e(Bk::people($r)) ?>"><?= (int) $r['adults'] + (int) $r['children'] ?></td>
              <td class="text-end text-nowrap"><?= e(inr($r['total_amount'])) ?></td>
              <td class="text-end text-nowrap text-secondary"><?= e(inr($r['paid'])) ?></td>
              <td class="text-end text-nowrap<?= $due ? ' text-danger fw-semibold' : ' text-secondary' ?>">
                <?= $balance < -0.009 ? '<span title="Customer paid more than the total">' . e(inr($balance)) . '</span>' : e($due ? inr($balance) : '—') ?>
              </td>
              <td><?= status_badge((string) $r['status'], Bk::statusLabel((string) $r['status'])) ?></td>
              <td><a href="<?= e(url('/bookings/' . $r['id'])) ?>" class="btn btn-sm">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($result['pages'] > 1): ?>
      <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small">Page <?= e((string) $result['page']) ?> of <?= e((string) $result['pages']) ?></p>
        <ul class="pagination m-0 ms-auto">
          <li class="page-item<?= $result['page'] <= 1 ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e(url('/bookings') . $qs(['page' => $result['page'] - 1])) ?>">Previous</a>
          </li>
          <?php for ($p = max(1, $result['page'] - 2), $end = min($result['pages'], $result['page'] + 2); $p <= $end; $p++): ?>
            <li class="page-item<?= $p === $result['page'] ? ' active' : '' ?>">
              <a class="page-link" href="<?= e(url('/bookings') . $qs(['page' => $p])) ?>"<?= $p === $result['page'] ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item<?= $result['page'] >= $result['pages'] ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e(url('/bookings') . $qs(['page' => $result['page'] + 1])) ?>">Next</a>
          </li>
        </ul>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</div>
