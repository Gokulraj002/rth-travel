<?php
/**
 * @var array{user:string,entity:string,action:string,from:string,to:string,q:string} $filters
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int} $result
 * @var array<int,string> $users
 * @var list<string> $entities
 * @var list<string> $actions
 */
use App\Core\Gate;
use App\Repositories\ActivityRepository as Log;

$active = array_filter($filters, static fn ($v) => $v !== '');
$rows = $result['rows'];
$from = $result['total'] === 0 ? 0 : ($result['page'] - 1) * $result['perPage'] + 1;
$to = min($result['total'], $result['page'] * $result['perPage']);
$link = static fn (int $p): string => url('/activity') . '?' . http_build_query([...$active, 'page' => $p]);
// Compact pager: first, last and 2 around the current page.
$pages = [];
for ($p = 1; $p <= $result['pages']; $p++) {
    if ($p === 1 || $p === $result['pages'] || abs($p - $result['page']) <= 2) {
        $pages[] = $p;
    } elseif (end($pages) !== null) {
        $pages[] = null;
    }
}
?>
<div class="rth-tools-activity">
  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?= e(url('/activity')) ?>" class="row g-2 align-items-end">
        <div class="col-md-4 col-lg-3">
          <label class="form-label" for="a-q">Search</label>
          <div class="input-icon">
            <span class="input-icon-addon"><?= icon('search') ?></span>
            <input type="search" id="a-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Details, person or record ID">
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <label class="form-label" for="a-user">Who</label>
          <select id="a-user" name="user" class="form-select">
            <option value="">Everyone</option>
            <?php foreach ($users as $id => $name): ?>
              <option value="<?= (int) $id ?>"<?= $filters['user'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
            <option value="system"<?= $filters['user'] === 'system' ? ' selected' : '' ?>>System / website</option>
          </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <label class="form-label" for="a-entity">Section</label>
          <select id="a-entity" name="entity" class="form-select">
            <option value="">All sections</option>
            <?php foreach ($entities as $ent): ?>
              <option value="<?= e($ent) ?>"<?= $filters['entity'] === $ent ? ' selected' : '' ?>><?= e(Log::entityLabel($ent)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <label class="form-label" for="a-action">Action</label>
          <select id="a-action" name="action" class="form-select">
            <option value="">All actions</option>
            <?php foreach ($actions as $act): ?>
              <option value="<?= e($act) ?>"<?= $filters['action'] === $act ? ' selected' : '' ?>><?= e(Log::actionLabel($act)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
          <label class="form-label" for="a-from">Date range</label>
          <div class="d-flex gap-1 align-items-center">
            <input type="date" id="a-from" name="from" class="form-control" value="<?= e($filters['from']) ?>" aria-label="From date">
            <span class="text-secondary">–</span>
            <input type="date" name="to" class="form-control" value="<?= e($filters['to']) ?>" aria-label="To date">
          </div>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('filter') ?> Filter</button>
          <?php if ($active !== []): ?><a href="<?= e(url('/activity')) ?>" class="btn btn-link">Clear filters</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <?php if ($rows === []): ?>
      <div class="empty">
        <div class="empty-icon"><?= icon('history') ?></div>
        <p class="empty-title"><?= $active === [] ? 'Nothing has happened yet' : 'No activity matches these filters' ?></p>
        <p class="empty-subtitle text-secondary">Every sign-in, change, deletion, export and backup appears here automatically.</p>
        <?php if ($active !== []): ?><div class="empty-action"><a href="<?= e(url('/activity')) ?>" class="btn">Clear filters</a></div><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-vcenter card-table rth-activity-table">
          <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Section</th><th>Details</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r):
                $action = (string) $r['action'];
                $entity = (string) $r['entity'];
                $href = Log::entityUrl($entity, $r['entity_id'], $action); ?>
              <tr>
                <td class="text-nowrap">
                  <div title="<?= e(nice_date((string) $r['created_at'])) ?>"><?= e(Log::ago((string) $r['created_at'])) ?></div>
                  <div class="small text-secondary"><?= e(nice_date((string) $r['created_at'])) ?></div>
                </td>
                <td class="text-nowrap">
                  <?php if ($r['admin_name'] !== null): ?>
                    <div class="d-flex align-items-center gap-2">
                      <span class="avatar avatar-xs rth-avatar"><?= e(mb_strtoupper(mb_substr((string) $r['admin_name'], 0, 1))) ?></span>
                      <div>
                        <div><?= e($r['admin_name']) ?></div>
                        <div class="small text-secondary"><?= e(Gate::roleLabel((string) $r['admin_role'])) ?></div>
                      </div>
                    </div>
                  <?php elseif ($r['admin_id'] !== null): ?>
                    <span class="text-secondary">Removed user #<?= (int) $r['admin_id'] ?></span>
                  <?php else: ?>
                    <span class="text-secondary">System / website</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge rth-act rth-act-<?= e(Log::actionTone($action)) ?>"><?= e(Log::actionLabel($action)) ?></span></td>
                <td class="text-nowrap">
                  <?= e(Log::entityLabel($entity)) ?>
                  <?php if ($r['entity_id'] !== null): ?>
                    <?php if ($href): ?>
                      <a href="<?= e(url($href)) ?>" class="small">#<?= (int) $r['entity_id'] ?></a>
                    <?php else: ?>
                      <span class="small text-secondary">#<?= (int) $r['entity_id'] ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="rth-activity-summary"><?= e($r['summary'] !== '' ? $r['summary'] : '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <div class="card-footer d-flex align-items-center flex-wrap gap-2">
      <p class="m-0 text-secondary small">Showing <?= (int) $from ?>–<?= (int) $to ?> of <?= number_format($result['total']) ?></p>
      <?php if ($result['pages'] > 1): ?>
        <ul class="pagination m-0 ms-auto">
          <li class="page-item<?= $result['page'] <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link(max(1, $result['page'] - 1))) ?>">‹ Prev</a></li>
          <?php foreach ($pages as $p): ?>
            <?php if ($p === null): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php else: ?>
              <li class="page-item<?= $p === $result['page'] ? ' active' : '' ?>"><a class="page-link" href="<?= e($link($p)) ?>"><?= (int) $p ?></a></li>
            <?php endif; ?>
          <?php endforeach; ?>
          <li class="page-item<?= $result['page'] >= $result['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link(min($result['pages'], $result['page'] + 1))) ?>">Next ›</a></li>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
