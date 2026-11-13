<?php
/**
 * @var string $q @var string $status
 * @var array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int} $result
 * @var array<string,string> $regions
 */
$filtered = $q !== '' || $status !== '';
?>
<div class="card">
  <?= partial('admin/destinations/_filters', ['basePath' => '/destinations', 'q' => $q, 'status' => $status, 'placeholder' => 'Search destinations…']) ?>

  <?php if ($result['rows'] === []): ?>
    <div class="empty">
      <div class="empty-icon"><?= icon('map-pin') ?></div>
      <p class="empty-title"><?= $filtered ? 'No destinations match your search' : 'No destinations yet' ?></p>
      <p class="empty-subtitle text-secondary">
        <?= $filtered ? 'Try a different word or clear the filters.' : 'Add places like Srinagar or Gulmarg — they appear on the website and can be linked to packages.' ?>
      </p>
      <?php if (!$filtered): ?>
        <div class="empty-action"><a href="<?= e(url('/destinations/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add destination</a></div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th class="w-1"><span class="visually-hidden">Order</span></th>
            <th class="w-1">Photo</th>
            <th>Name</th>
            <th>Region</th>
            <th>Altitude</th>
            <th>Status</th>
            <th class="w-1"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody data-sortable data-sortable-url="/destinations/reorder">
          <?php foreach ($result['rows'] as $d): ?>
            <tr data-id="<?= e($d['id']) ?>">
              <td><span data-sort-handle title="Drag to change the order on the website"><?= icon('grip') ?></span></td>
              <td>
                <?php if ((string) $d['image'] !== ''): ?>
                  <img class="rth-thumb" src="<?= e(media_url((string) $d['image'])) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="rth-thumb d-inline-flex align-items-center justify-content-center text-secondary"><?= icon('photo') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <a href="<?= e(url('/destinations/' . $d['id'] . '/edit')) ?>" class="fw-semibold text-reset"><?= e($d['name']) ?></a>
                <div class="small text-secondary">/<?= e($d['slug']) ?><?= (string) $d['tagline'] !== '' ? ' · ' . e(str_limit((string) $d['tagline'], 50)) : '' ?></div>
              </td>
              <td><?= e($regions[$d['region']] ?? ucfirst((string) $d['region'])) ?></td>
              <td class="text-nowrap"><?= (int) $d['altitude_m'] > 0 ? e(number_format((int) $d['altitude_m'])) . ' m' : '—' ?></td>
              <td>
                <?php if ($d['status'] === 'published'): ?>
                  <span class="badge badge-published">Published</span>
                <?php else: ?>
                  <span class="badge badge-draft">Draft</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex gap-1 justify-content-end">
                  <a href="<?= e(url('/destinations/' . $d['id'] . '/edit')) ?>" class="btn btn-sm btn-icon btn-ghost-primary" title="Edit" aria-label="Edit <?= e($d['name']) ?>"><?= icon('edit') ?></a>
                  <form method="post" action="<?= e(url('/destinations/' . $d['id'] . '/toggle')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <?php if ($d['status'] === 'published'): ?>
                      <button type="submit" class="btn btn-sm btn-ghost-secondary text-nowrap" title="Hide from the website">Unpublish</button>
                    <?php else: ?>
                      <button type="submit" class="btn btn-sm btn-ghost-success text-nowrap" title="Show on the website">Publish</button>
                    <?php endif; ?>
                  </form>
                  <form method="post" action="<?= e(url('/destinations/' . $d['id'])) ?>" class="m-0"
                        data-confirm="Delete “<?= e($d['name']) ?>”? This cannot be undone.">
                    <?= csrf_field() ?>
                    <?= method_field('DELETE') ?>
                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Delete" aria-label="Delete <?= e($d['name']) ?>"><?= icon('trash') ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= partial('admin/destinations/_pagination', ['result' => $result, 'basePath' => '/destinations', 'query' => ['q' => $q, 'status' => $status]]) ?>
  <?php endif; ?>
</div>
<p class="form-hint mt-2"><?= icon('grip', 'icon icon-sm') ?> Drag rows by the handle to change the order destinations appear on the website.</p>
