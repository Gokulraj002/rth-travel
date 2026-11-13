<?php
/**
 * @var array{q:string,status:string,from:string,to:string} $filters
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int} $result
 * @var int $newCount
 * @var bool $crm  plan has the lead pipeline → show stage + follow-up
 */
use App\Repositories\EnquiryRepository as Enq;

$crm = $crm ?? false;

$activeFilters = array_filter($filters, static fn ($v) => $v !== '');
$qs = static function (array $extra = []) use ($activeFilters): string {
    $params = array_filter([...$activeFilters, ...$extra], static fn ($v) => $v !== '' && $v !== null);
    return $params === [] ? '' : '?' . http_build_query($params);
};
$rows = $result['rows'];
$from = $result['total'] === 0 ? 0 : ($result['page'] - 1) * $result['perPage'] + 1;
$to = min($result['total'], $result['page'] * $result['perPage']);
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/enquiries')) ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label" for="f-q">Search</label>
        <div class="input-icon">
          <span class="input-icon-addon"><?= icon('search') ?></span>
          <input type="search" id="f-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Name, phone, email or message">
        </div>
      </div>
      <?php if ($crm): ?>
      <div class="col-6 col-md-2">
        <label class="form-label" for="f-stage">Stage</label>
        <select id="f-stage" name="stage" class="form-select">
          <option value="">All</option>
          <?php foreach (Enq::stages() as $key => $st): ?>
            <option value="<?= e($key) ?>"<?= ($filters['stage'] ?? '') === $key ? ' selected' : '' ?>><?= e($st['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <div class="col-6 col-md-2">
        <label class="form-label" for="f-status">Status</label>
        <select id="f-status" name="status" class="form-select">
          <option value="">All</option>
          <?php foreach (Enq::STATUSES as $key => [$label]): ?>
            <option value="<?= e($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?><?= $key === 'new' && $newCount > 0 ? ' (' . $newCount . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-6 col-md-2">
        <label class="form-label" for="f-from">From</label>
        <input type="date" id="f-from" name="from" class="form-control" value="<?= e($filters['from']) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="f-to">To</label>
        <input type="date" id="f-to" name="to" class="form-control" value="<?= e($filters['to']) ?>">
      </div>
      <div class="col-6 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill">Filter</button>
        <?php if ($activeFilters !== []): ?>
          <a href="<?= e(url('/enquiries')) ?>" class="btn btn-icon" title="Clear filters" aria-label="Clear filters"><?= icon('x') ?></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($rows === []): ?>
    <div class="card-body">
      <div class="empty py-5">
        <div class="empty-icon"><?= icon('inbox') ?></div>
        <?php if ($activeFilters !== []): ?>
          <p class="empty-title">No enquiries match these filters</p>
          <p class="empty-subtitle text-secondary">Try a different search word or date range.</p>
          <div class="empty-action"><a href="<?= e(url('/enquiries')) ?>" class="btn">Show all enquiries</a></div>
        <?php else: ?>
          <p class="empty-title">No enquiries yet</p>
          <p class="empty-subtitle text-secondary">When someone fills in the enquiry form on your website, it will appear here — with buttons to call or WhatsApp them.</p>
          <?php if ($crm): ?><div class="empty-action"><a href="<?= e(url('/enquiries/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add a phone or walk-in enquiry</a></div><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card-header">
      <h3 class="card-title"><?= e((string) $result['total']) ?> <?= $result['total'] === 1 ? 'enquiry' : 'enquiries' ?></h3>
      <div class="card-actions text-secondary small">Showing <?= e((string) $from) ?>–<?= e((string) $to) ?></div>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>Name &amp; contact</th>
            <th>Trip</th>
            <th>Travellers</th>
            <th>Source</th>
            <th><?= $crm ? 'Stage' : 'Status' ?></th>
            <?php if ($crm): ?><th>Follow-up</th><?php endif; ?>
            <th>Received</th>
            <th class="w-1"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <a href="<?= e(url('/enquiries/' . $r['id'])) ?>" class="fw-semibold"><?= e($r['name']) ?></a>
                <div class="small text-nowrap">
                  <a href="<?= e(Enq::telHref($r['phone'])) ?>" class="text-secondary"><?= e($r['phone']) ?></a>
                  <a href="<?= e(Enq::whatsappUrl($r['phone'])) ?>" class="ms-1 text-green" target="_blank" rel="noopener" title="Chat on WhatsApp" aria-label="Chat with <?= e($r['name']) ?> on WhatsApp"><?= icon('whatsapp') ?></a>
                </div>
                <?php if (!empty($r['email'])): ?><div class="small text-secondary text-truncate" style="max-width: 16rem"><?= e($r['email']) ?></div><?php endif; ?>
              </td>
              <td>
                <div><?= e(str_limit(Enq::tripLabel($r), 50)) ?></div>
                <?php if (!empty($r['travel_month'])): ?><div class="small text-secondary"><?= icon('calendar', 'icon icon-sm') ?> <?= e($r['travel_month']) ?></div><?php endif; ?>
              </td>
              <td class="text-nowrap"><?= e(Enq::people($r)) ?></td>
              <td class="text-secondary text-nowrap"><?= e(Enq::sourceLabel($r['source'])) ?></td>
              <?php if ($crm): ?>
                <td><?= status_badge((string) $r['stage'], Enq::stageLabel((string) $r['stage'])) ?><?php if ($r['priority'] === 'high'): ?> <span class="badge bg-red-lt" title="High priority">!</span><?php endif; ?></td>
                <td class="small text-nowrap<?= Enq::followUpOverdue($r) ? ' text-danger fw-semibold' : ' text-secondary' ?>"><?= $r['next_follow_up_at'] && !in_array($r['stage'], ['won', 'lost'], true) ? e(nice_date($r['next_follow_up_at'])) : '—' ?></td>
              <?php else: ?>
                <td><span class="badge <?= e(Enq::statusClass((string) $r['status'])) ?>"><?= e(Enq::statusLabel((string) $r['status'])) ?></span></td>
              <?php endif; ?>
              <td class="text-secondary small text-nowrap"><?= e(nice_date($r['created_at'])) ?></td>
              <td><a href="<?= e(url('/enquiries/' . $r['id'])) ?>" class="btn btn-sm">Open</a></td>
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
            <a class="page-link" href="<?= e(url('/enquiries') . $qs(['page' => $result['page'] - 1])) ?>">Previous</a>
          </li>
          <?php
          $start = max(1, $result['page'] - 2);
          $end = min($result['pages'], $result['page'] + 2);
          for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item<?= $p === $result['page'] ? ' active' : '' ?>">
              <a class="page-link" href="<?= e(url('/enquiries') . $qs(['page' => $p])) ?>"<?= $p === $result['page'] ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item<?= $result['page'] >= $result['pages'] ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e(url('/enquiries') . $qs(['page' => $result['page'] + 1])) ?>">Next</a>
          </li>
        </ul>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
