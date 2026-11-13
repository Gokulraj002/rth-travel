<?php
/**
 * Invoices list.
 * @var array{q:string,status:string,month:string} $filters
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int,sum:array<string,mixed>} $result
 */
use App\Repositories\InvoiceRepository;

$rows = $result['rows'];
$sum = $result['sum'];
$filtered = $filters['q'] !== '' || $filters['status'] !== '' || $filters['month'] !== '';
$qs = array_filter($filters, static fn ($v) => $v !== '');
$pageUrl = static fn (int $p): string => url('/invoices') . '?' . http_build_query([...$qs, 'page' => $p]);
$from = $result['total'] === 0 ? 0 : ($result['page'] - 1) * $result['perPage'] + 1;
$to = min($result['total'], $result['page'] * $result['perPage']);
$monthLabel = $filters['month'] !== '' ? date('F Y', (int) strtotime($filters['month'] . '-01')) : '';
?>
<div class="rth-invoices">
<div class="row row-cards mb-3">
  <?php foreach ([
      ['Invoices', (string) (int) ($sum['n'] ?? 0), 'receipt', false],
      ['Taxable value', inr2($sum['taxable'] ?? 0), 'rupee', false],
      ['GST collected', inr2($sum['tax'] ?? 0), 'file-text', false],
      ['Awaiting payment', inr2($sum['unpaid'] ?? 0), 'clock', true],
  ] as [$label, $value, $ic, $warn]): ?>
    <div class="col-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar <?= $warn ? 'bg-orange-lt' : 'bg-primary-lt' ?>"><?= icon($ic) ?></span>
          <div>
            <div class="text-secondary small"><?= e($label) ?><?= $monthLabel !== '' ? ' · ' . e($monthLabel) : '' ?></div>
            <div class="fw-bold"><?= e($value) ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/invoices')) ?>" class="row g-2 align-items-end" role="search">
      <div class="col-12 col-md-5">
        <label class="form-label" for="inv-q">Search</label>
        <div class="input-icon">
          <span class="input-icon-addon"><?= icon('search') ?></span>
          <input type="search" id="inv-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Invoice no., customer, phone or booking…">
        </div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="inv-month">Month</label>
        <input type="month" id="inv-month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="inv-status">Status</label>
        <select id="inv-status" name="status" class="form-select">
          <option value="">All</option>
          <?php foreach (InvoiceRepository::STATUSES as $k => $lbl): ?>
            <option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill">Filter</button>
        <?php if ($filtered): ?><a href="<?= e(url('/invoices')) ?>" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($rows === []): ?>
  <div class="card">
    <div class="empty">
      <div class="empty-icon"><?= icon('receipt', 'icon icon-lg') ?></div>
      <?php if ($filtered): ?>
        <p class="empty-title">No invoices match</p>
        <p class="empty-subtitle text-secondary">Try another month or word, or clear the filters.</p>
        <div class="empty-action"><a href="<?= e(url('/invoices')) ?>" class="btn btn-outline-secondary">Clear filters</a></div>
      <?php else: ?>
        <p class="empty-title">No GST invoices yet</p>
        <p class="empty-subtitle text-secondary">Create a tax invoice for a booking in a few clicks — CGST/SGST or IGST is worked out for you.</p>
        <div class="empty-action"><a href="<?= e(url('/invoices/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> New invoice</a></div>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Booking</th>
            <th class="text-end">Taxable</th>
            <th class="text-end">GST</th>
            <th class="text-end">Total</th>
            <th>Status</th>
            <th class="w-1"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $id = (int) $r['id'];
              $isDraft = $r['status'] === 'draft';
          ?>
            <tr class="<?= $r['status'] === 'void' ? 'rth-inv-void' : '' ?>">
              <td><a class="fw-semibold text-reset" href="<?= e(url('/invoices/' . $id)) ?>"><?= e($isDraft ? 'Draft #' . $id : $r['number']) ?></a></td>
              <td class="text-nowrap"><?= e(nice_day((string) $r['issue_date'])) ?></td>
              <td>
                <div><?= e($r['customer_name']) ?></div>
                <div class="small text-secondary"><?= e($r['customer_phone']) ?></div>
              </td>
              <td>
                <?php if ($r['booking_id']): ?>
                  <?php if (feature('bookings') && can('bookings')): ?>
                    <a href="<?= e(url('/bookings/' . (int) $r['booking_id'])) ?>"><?= e($r['booking_number']) ?></a>
                  <?php else: ?><?= e($r['booking_number']) ?><?php endif; ?>
                  <div class="small text-secondary"><?= e(str_limit((string) $r['booking_title'], 40)) ?></div>
                <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
              </td>
              <td class="text-end text-nowrap"><?= e(inr2((float) $r['subtotal'] - (float) $r['discount'])) ?></td>
              <td class="text-end text-nowrap"><?= e(inr2((float) $r['cgst'] + (float) $r['sgst'] + (float) $r['igst'])) ?></td>
              <td class="text-end text-nowrap fw-semibold"><?= e(inr2($r['total'])) ?></td>
              <td><?= status_badge((string) $r['status'], InvoiceRepository::STATUSES[$r['status']] ?? null) ?></td>
              <td class="text-nowrap">
                <a class="btn btn-sm btn-ghost-secondary" href="<?= e(url('/invoices/' . $id . '/print')) ?>" title="Print / PDF" aria-label="Print invoice"><?= icon('printer') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex align-items-center flex-wrap gap-2">
      <p class="m-0 text-secondary small">Showing <?= e((string) $from) ?>–<?= e((string) $to) ?> of <?= e((string) $result['total']) ?> · Totals above leave out drafts and void invoices.</p>
      <?php if ($result['pages'] > 1): ?>
        <ul class="pagination m-0 ms-auto">
          <li class="page-item<?= $result['page'] <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($pageUrl(max(1, $result['page'] - 1))) ?>">‹ Prev</a></li>
          <?php for ($p = max(1, $result['page'] - 3); $p <= min($result['pages'], $result['page'] + 3); $p++): ?>
            <li class="page-item<?= $p === $result['page'] ? ' active' : '' ?>"><a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= e((string) $p) ?></a></li>
          <?php endfor; ?>
          <li class="page-item<?= $result['page'] >= $result['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($pageUrl(min($result['pages'], $result['page'] + 1))) ?>">Next ›</a></li>
        </ul>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
</div>
