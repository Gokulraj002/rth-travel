<?php
/**
 * @var array{q:string,tag:string,source:string} $filters
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int} $result
 * @var list<string> $tags
 */
use App\Repositories\CustomerRepository as C;

$active = array_filter($filters, static fn ($v) => $v !== '');
$rows = $result['rows'];
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/customers')) ?>" class="row g-2 align-items-end" role="search">
      <div class="col-12 col-md-5">
        <label class="form-label" for="cf-q">Search</label>
        <div class="input-icon">
          <span class="input-icon-addon"><?= icon('search') ?></span>
          <input type="search" id="cf-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Name, phone, email or city">
        </div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="cf-tag">Tag</label>
        <select id="cf-tag" name="tag" class="form-select">
          <option value="">Any</option>
          <?php foreach ($tags as $t): ?>
            <option value="<?= e($t) ?>"<?= $filters['tag'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="cf-source">Came from</label>
        <select id="cf-source" name="source" class="form-select">
          <option value="">Any</option>
          <?php foreach (config('crm')['sources'] ?? [] as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $filters['source'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill"><?= icon('filter', 'icon icon-sm') ?> Filter</button>
        <?php if ($active !== []): ?>
          <a href="<?= e(url('/customers')) ?>" class="btn btn-icon" title="Clear filters" aria-label="Clear filters"><?= icon('x') ?></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($rows === []): ?>
    <div class="card-body">
      <div class="empty py-5">
        <div class="empty-icon"><?= icon('user') ?></div>
        <?php if ($active !== []): ?>
          <p class="empty-title">No customers match these filters</p>
          <p class="empty-subtitle text-secondary">Try a different name, phone number or tag.</p>
          <div class="empty-action"><a href="<?= e(url('/customers')) ?>" class="btn">Show all customers</a></div>
        <?php else: ?>
          <p class="empty-title">No customers yet</p>
          <p class="empty-subtitle text-secondary">Website enquiries are added here automatically. You can also add travellers yourself — their trips, payments and birthdays stay in one place.</p>
          <div class="empty-action"><a href="<?= e(url('/customers/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add your first customer</a></div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card-header">
      <h3 class="card-title"><?= e($result['total']) ?> customer<?= $result['total'] === 1 ? '' : 's' ?></h3>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr><th>Name</th><th>Phone</th><th>City</th><th class="text-end">Trips</th><th class="text-end">Total spent</th><th>Last activity</th><th class="w-1"></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <a href="<?= e(url('/customers/' . $r['id'])) ?>" class="fw-semibold"><?= e($r['name']) ?></a>
                <div>
                  <?php foreach (C::tagList($r['tags']) as $t): ?>
                    <a href="<?= e(url('/customers?tag=' . rawurlencode($t))) ?>" class="badge bg-azure-lt rth-crm-tag text-decoration-none"><?= e($t) ?></a>
                  <?php endforeach; ?>
                </div>
              </td>
              <td class="text-nowrap">
                <a href="<?= e(C::telHref($r['phone'])) ?>" class="text-reset"><?= e(C::displayPhone($r['phone'])) ?></a>
                <a href="<?= e(wa_link((string) $r['phone'])) ?>" class="ms-1 text-green" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp <?= e($r['name']) ?>"><?= icon('whatsapp', 'icon icon-sm') ?></a>
              </td>
              <td class="text-secondary"><?= e($r['city'] ?: '—') ?></td>
              <td class="text-end"><?= e((int) $r['trips']) ?></td>
              <td class="text-end text-nowrap"><?= (float) $r['total_spent'] > 0 ? e(inr($r['total_spent'])) : '<span class="text-secondary">—</span>' ?></td>
              <td class="text-secondary small text-nowrap"><?= e(nice_day($r['last_activity_at'])) ?></td>
              <td><a href="<?= e(url('/customers/' . $r['id'])) ?>" class="btn btn-sm">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= partial('admin/destinations/_pagination', ['result' => $result, 'basePath' => '/customers', 'query' => $active]) ?>
  <?php endif; ?>
</div>
