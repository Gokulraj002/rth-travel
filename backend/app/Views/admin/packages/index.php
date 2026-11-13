<?php
/**
 * Packages list.
 * @var array{q:string,status:string,region:string} $filters
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int} $result
 * @var string $websiteUrl
 */
use App\Repositories\PackageRepository;

$rows = $result['rows'];
$filtered = $filters['q'] !== '' || $filters['status'] !== '' || $filters['region'] !== '';
$qs = array_filter($filters, static fn ($v) => $v !== '');
$pageUrl = static fn (int $p): string => url('/packages') . '?' . http_build_query([...$qs, 'page' => $p]);
$currentPath = '/packages' . ($qs || $result['page'] > 1 ? '?' . http_build_query([...$qs, ...($result['page'] > 1 ? ['page' => $result['page']] : [])]) : '');
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/packages')) ?>" class="row g-2 align-items-end" role="search">
      <div class="col-12 col-md-5">
        <label class="form-label" for="pkg-q">Search</label>
        <div class="input-icon">
          <span class="input-icon-addon"><?= icon('search') ?></span>
          <input type="search" id="pkg-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Package name or web address…">
        </div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="pkg-status">Status</label>
        <select id="pkg-status" name="status" class="form-select">
          <option value="">All</option>
          <?php foreach (PackageRepository::STATUSES as $val => $label): ?>
            <option value="<?= e($val) ?>"<?= $filters['status'] === $val ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="pkg-region">Region</label>
        <select id="pkg-region" name="region" class="form-select">
          <option value="">All regions</option>
          <?php foreach (PackageRepository::REGIONS as $val => $label): ?>
            <option value="<?= e($val) ?>"<?= $filters['region'] === $val ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill">Filter</button>
        <?php if ($filtered): ?>
          <a href="<?= e(url('/packages')) ?>" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($rows === []): ?>
  <div class="card">
    <div class="empty">
      <div class="empty-icon"><?= icon('package', 'icon icon-lg') ?></div>
      <?php if ($filtered): ?>
        <p class="empty-title">No packages match your search</p>
        <p class="empty-subtitle text-secondary">Try a different word, or clear the filters to see every package.</p>
        <div class="empty-action">
          <a href="<?= e(url('/packages')) ?>" class="btn btn-outline-secondary">Clear filters</a>
        </div>
      <?php else: ?>
        <p class="empty-title">No packages yet</p>
        <p class="empty-subtitle text-secondary">Add your first tour package — it will appear on the website once you publish it.</p>
        <div class="empty-action">
          <a href="<?= e(url('/packages/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> New package</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><?= e((string) $result['total']) ?> package<?= $result['total'] === 1 ? '' : 's' ?></h3>
      <div class="card-subtitle ms-auto text-secondary small d-none d-md-block">
        <?= icon('grip', 'icon icon-sm') ?> Drag a row by its handle to change the order on the website.
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th class="w-1"><span class="visually-hidden">Order</span></th>
            <th class="w-1">Photo</th>
            <th>Package</th>
            <th>Region</th>
            <th>Duration</th>
            <th>From</th>
            <th>Trip types</th>
            <th class="w-1 text-center">Featured</th>
            <th>Status</th>
            <th>Updated</th>
            <th class="w-1"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody data-sortable data-sortable-url="/packages/reorder">
          <?php foreach ($rows as $p):
              $id = (int) $p['id'];
              $published = $p['status'] === 'published';
              $editUrl = url('/packages/' . $id . '/edit');
          ?>
            <tr data-id="<?= e((string) $id) ?>">
              <td><span data-sort-handle title="Drag to reorder" role="button" aria-label="Drag to reorder"><?= icon('grip') ?></span></td>
              <td>
                <?php if ((string) $p['image'] !== ''): ?>
                  <img class="rth-thumb" src="<?= e(media_url((string) $p['image'])) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="rth-thumb d-inline-flex align-items-center justify-content-center text-secondary" title="No photo yet"><?= icon('photo') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <a href="<?= e($editUrl) ?>" class="fw-semibold text-reset"><?= e($p['title']) ?></a>
                <div class="small text-secondary">/packages/<?= e($p['slug']) ?></div>
              </td>
              <td><?= e(PackageRepository::REGIONS[$p['region']] ?? $p['region']) ?></td>
              <td class="text-nowrap"><?= e((string) $p['nights']) ?>N / <?= e((string) $p['days']) ?>D</td>
              <td class="text-nowrap"><?= e(inr((int) $p['price_from'])) ?></td>
              <td>
                <?php foreach ($p['categories'] as $c): ?>
                  <span class="badge bg-blue-lt me-1 mb-1"><?= e(PackageRepository::CATEGORIES[$c] ?? $c) ?></span>
                <?php endforeach; ?>
                <?php if ($p['categories'] === []): ?><span class="text-secondary">—</span><?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ((int) $p['featured'] === 1): ?>
                  <span class="text-yellow" title="Featured on the home page"><?= icon('star') ?><span class="visually-hidden">Featured</span></span>
                <?php else: ?>
                  <span class="text-muted opacity-50" title="Not featured"><?= icon('star') ?><span class="visually-hidden">Not featured</span></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $published ? 'badge-published' : 'badge-draft' ?>"><?= $published ? 'Published' : 'Draft' ?></span>
              </td>
              <td class="text-nowrap small text-secondary"><?= e(nice_date((string) $p['updated_at'])) ?></td>
              <td class="text-end">
                <div class="d-flex gap-1 justify-content-end flex-nowrap">
                  <a href="<?= e($editUrl) ?>" class="btn btn-sm btn-primary"><?= icon('edit') ?> Edit</a>
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">More</button>
                    <div class="dropdown-menu dropdown-menu-end">
                      <?php if ($published && $websiteUrl !== ''): ?>
                        <a class="dropdown-item" href="<?= e($websiteUrl . '/packages/' . rawurlencode((string) $p['slug'])) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon dropdown-item-icon') ?> View on website</a>
                      <?php endif; ?>
                      <form method="post" action="<?= e(url('/packages/' . $id . '/toggle')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return" value="<?= e($currentPath) ?>">
                        <button type="submit" class="dropdown-item">
                          <?php if ($published): ?>
                            <?= icon('eye', 'icon dropdown-item-icon') ?> Unpublish (hide)
                          <?php else: ?>
                            <?= icon('check', 'icon dropdown-item-icon') ?> Publish
                          <?php endif; ?>
                        </button>
                      </form>
                      <form method="post" action="<?= e(url('/packages/' . $id . '/duplicate')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item"><?= icon('copy', 'icon dropdown-item-icon') ?> Duplicate</button>
                      </form>
                      <div class="dropdown-divider"></div>
                      <form method="post" action="<?= e(url('/packages/' . $id)) ?>" class="m-0" data-confirm="Delete “<?= e($p['title']) ?>”? This cannot be undone.">
                        <?= csrf_field() ?>
                        <?= method_field('DELETE') ?>
                        <button type="submit" class="dropdown-item text-danger"><?= icon('trash', 'icon dropdown-item-icon') ?> Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              </td>
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
          <li class="page-item<?= $result['page'] <= 1 ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e($pageUrl(max(1, $result['page'] - 1))) ?>">‹ Prev</a>
          </li>
          <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <li class="page-item<?= $i === $result['page'] ? ' active' : '' ?>">
              <a class="page-link" href="<?= e($pageUrl($i)) ?>"><?= e((string) $i) ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item<?= $result['page'] >= $result['pages'] ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e($pageUrl(min($result['pages'], $result['page'] + 1))) ?>">Next ›</a>
          </li>
        </ul>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
