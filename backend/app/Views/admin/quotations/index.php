<?php
/**
 * Quotations list.
 * @var array{q:string,status:string} $filters
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int} $result
 * @var array<string,float|int> $summary
 */
use App\Repositories\QuotationRepository as Quotes;

$rows = $result['rows'];
$filtered = $filters['q'] !== '' || $filters['status'] !== '';
$qs = array_filter($filters, static fn ($v) => $v !== '');
$pageUrl = static fn (int $p): string => url('/quotations') . '?' . http_build_query([...$qs, 'page' => $p]);
$statusUrl = static fn (string $s): string => url('/quotations') . ($s !== '' ? '?' . http_build_query(['status' => $s]) : '');
$pax = static function (array $r): string {
    $a = (int) $r['adults'];
    $c = (int) $r['children'];
    return $a . 'A' . ($c > 0 ? ' + ' . $c . 'C' : '');
};
?>
<div class="rth-quotes">
  <div class="rth-quote-chips mb-3">
    <a class="rth-quote-chip<?= $filters['status'] === 'open' ? ' is-active' : '' ?>" href="<?= e($statusUrl('open')) ?>">
      <span class="rth-quote-chip-icon bg-blue-lt"><?= icon('send') ?></span>
      <span>
        <span class="rth-quote-chip-label">Open quotes</span>
        <span class="rth-quote-chip-value"><?= e(inr($summary['open_value'])) ?></span>
        <span class="rth-quote-chip-sub"><?= e((string) $summary['open_count']) ?> waiting for the customer</span>
      </span>
    </a>
    <a class="rth-quote-chip<?= $filters['status'] === 'accepted' ? ' is-active' : '' ?>" href="<?= e($statusUrl('accepted')) ?>">
      <span class="rth-quote-chip-icon bg-green-lt"><?= icon('check') ?></span>
      <span>
        <span class="rth-quote-chip-label">Accepted this month</span>
        <span class="rth-quote-chip-value"><?= e(inr($summary['accepted_value'])) ?></span>
        <span class="rth-quote-chip-sub"><?= e((string) $summary['accepted_count']) ?> quotation<?= $summary['accepted_count'] === 1 ? '' : 's' ?> in <?= e(date('F')) ?></span>
      </span>
    </a>
    <a class="rth-quote-chip<?= $filters['status'] === 'expired' ? ' is-active' : '' ?>" href="<?= e($statusUrl('expired')) ?>">
      <span class="rth-quote-chip-icon bg-orange-lt"><?= icon('clock') ?></span>
      <span>
        <span class="rth-quote-chip-label">Expired</span>
        <span class="rth-quote-chip-value"><?= e((string) $summary['expired_count']) ?></span>
        <span class="rth-quote-chip-sub">Follow up or send a fresh quote</span>
      </span>
    </a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?= e(url('/quotations')) ?>" class="row g-2 align-items-end" role="search">
        <div class="col-12 col-md-6">
          <label class="form-label" for="qt-q">Search</label>
          <div class="input-icon">
            <span class="input-icon-addon"><?= icon('search') ?></span>
            <input type="search" id="qt-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Quotation no., customer name, phone or trip…">
          </div>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label" for="qt-status">Status</label>
          <select id="qt-status" name="status" class="form-select">
            <option value="">All</option>
            <option value="open"<?= $filters['status'] === 'open' ? ' selected' : '' ?>>Open (draft + sent)</option>
            <?php foreach (Quotes::STATUSES as $val => $label): ?>
              <option value="<?= e($val) ?>"<?= $filters['status'] === $val ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">Filter</button>
          <?php if ($filtered): ?><a href="<?= e(url('/quotations')) ?>" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <?php if ($rows === []): ?>
    <div class="card">
      <div class="empty">
        <div class="empty-icon"><?= icon('file-text', 'icon icon-lg') ?></div>
        <?php if ($filtered): ?>
          <p class="empty-title">No quotations match</p>
          <p class="empty-subtitle text-secondary">Try a different word, or clear the filters to see every quotation.</p>
          <div class="empty-action"><a href="<?= e(url('/quotations')) ?>" class="btn btn-outline-secondary">Clear filters</a></div>
        <?php else: ?>
          <p class="empty-title">No quotations yet</p>
          <p class="empty-subtitle text-secondary">Build a day-wise quotation in a couple of minutes and share it on WhatsApp as a link or PDF.</p>
          <div class="empty-action"><a href="<?= e(url('/quotations/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> New quotation</a></div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= e((string) $result['total']) ?> quotation<?= $result['total'] === 1 ? '' : 's' ?></h3>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>Number</th>
              <th>Customer</th>
              <th>Trip</th>
              <th>Travel dates</th>
              <th>Pax</th>
              <th class="text-end">Total</th>
              <th>Status</th>
              <th>Valid until</th>
              <th class="w-1"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
                $id = (int) $r['id'];
                $st = Quotes::effectiveStatus($r);
                $showUrl = url('/quotations/' . $id);
            ?>
              <tr>
                <td class="text-nowrap"><a href="<?= e($showUrl) ?>" class="fw-semibold"><?= e($r['number']) ?></a>
                  <div class="small text-secondary"><?= e(nice_day($r['created_at'])) ?></div></td>
                <td>
                  <div class="fw-medium"><?= e($r['customer_name'] ?? '—') ?></div>
                  <?php if (!empty($r['customer_phone'])): ?><div class="small text-secondary"><?= e($r['customer_phone']) ?></div><?php endif; ?>
                </td>
                <td><a href="<?= e($showUrl) ?>" class="text-reset"><?= e(str_limit((string) $r['title'], 60)) ?></a></td>
                <td class="text-nowrap small">
                  <?php if (!empty($r['travel_start'])): ?>
                    <?= e(nice_day($r['travel_start'])) ?><?php if (!empty($r['travel_end'])): ?><br><span class="text-secondary">to <?= e(nice_day($r['travel_end'])) ?></span><?php endif; ?>
                  <?php else: ?><span class="text-secondary">Not fixed</span><?php endif; ?>
                </td>
                <td class="text-nowrap"><?= e($pax($r)) ?></td>
                <td class="text-end text-nowrap fw-semibold"><?= e(inr($r['total'])) ?></td>
                <td><?= status_badge($st, Quotes::statusLabel($st)) ?></td>
                <td class="text-nowrap small<?= $st === 'expired' ? ' text-danger' : ' text-secondary' ?>"><?= e(nice_day($r['valid_until'])) ?></td>
                <td class="text-end"><a href="<?= e($showUrl) ?>" class="btn btn-sm btn-outline-primary"><?= icon('eye') ?> Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($result['pages'] > 1): ?>
        <div class="card-footer d-flex align-items-center flex-wrap gap-2">
          <p class="m-0 text-secondary small">
            Showing <?= e((string) (($result['page'] - 1) * $result['perPage'] + 1)) ?>–<?= e((string) min($result['total'], $result['page'] * $result['perPage'])) ?> of <?= e((string) $result['total']) ?>
          </p>
          <ul class="pagination m-0 ms-auto">
            <li class="page-item<?= $result['page'] <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($pageUrl(max(1, $result['page'] - 1))) ?>">‹ Prev</a></li>
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
              <li class="page-item<?= $i === $result['page'] ? ' active' : '' ?>"><a class="page-link" href="<?= e($pageUrl($i)) ?>"><?= e((string) $i) ?></a></li>
            <?php endfor; ?>
            <li class="page-item<?= $result['page'] >= $result['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($pageUrl(min($result['pages'], $result['page'] + 1))) ?>">Next ›</a></li>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
