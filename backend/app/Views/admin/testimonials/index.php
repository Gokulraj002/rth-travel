<?php
/**
 * @var string $q @var string $status
 * @var array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int} $result
 */
$filtered = $q !== '' || $status !== '';
?>
<div class="card">
  <?= partial('admin/destinations/_filters', ['basePath' => '/testimonials', 'q' => $q, 'status' => $status, 'placeholder' => 'Search by guest, city, trip or words in the review…']) ?>

  <?php if ($result['rows'] === []): ?>
    <div class="empty">
      <div class="empty-icon"><?= icon('star') ?></div>
      <p class="empty-title"><?= $filtered ? 'No reviews match your search' : 'No reviews yet' ?></p>
      <p class="empty-subtitle text-secondary">
        <?= $filtered ? 'Try a different word or clear the filters.' : 'Add what happy guests said about their trip — reviews build trust with new travellers.' ?>
      </p>
      <?php if (!$filtered): ?>
        <div class="empty-action"><a href="<?= e(url('/testimonials/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add review</a></div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th class="w-1"><span class="visually-hidden">Order</span></th>
            <th>Guest</th>
            <th>Review</th>
            <th>Rating</th>
            <th>Status</th>
            <th class="w-1"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody data-sortable data-sortable-url="/testimonials/reorder">
          <?php foreach ($result['rows'] as $t): $rating = max(0, min(5, (int) $t['rating'])); ?>
            <tr data-id="<?= e($t['id']) ?>">
              <td><span data-sort-handle title="Drag to change the order on the website"><?= icon('grip') ?></span></td>
              <td class="text-nowrap">
                <a href="<?= e(url('/testimonials/' . $t['id'] . '/edit')) ?>" class="fw-semibold text-reset"><?= e($t['name']) ?></a>
                <div class="small text-secondary"><?= e(implode(' · ', array_filter([(string) $t['from_city'], (string) $t['trip']]))) ?: '—' ?></div>
              </td>
              <td class="text-secondary"><?= e(str_limit((string) $t['quote'], 110)) ?></td>
              <td class="text-nowrap">
                <span class="text-warning" aria-hidden="true"><?= str_repeat('★', $rating) ?></span><span class="text-muted" aria-hidden="true"><?= str_repeat('☆', 5 - $rating) ?></span>
                <span class="visually-hidden"><?= e($rating) ?> out of 5 stars</span>
              </td>
              <td>
                <?php if ($t['status'] === 'published'): ?>
                  <span class="badge badge-published">Published</span>
                <?php else: ?>
                  <span class="badge badge-draft">Draft</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex gap-1 justify-content-end">
                  <a href="<?= e(url('/testimonials/' . $t['id'] . '/edit')) ?>" class="btn btn-sm btn-icon btn-ghost-primary" title="Edit" aria-label="Edit review from <?= e($t['name']) ?>"><?= icon('edit') ?></a>
                  <form method="post" action="<?= e(url('/testimonials/' . $t['id'])) ?>" class="m-0"
                        data-confirm="Delete the review from <?= e($t['name']) ?>? This cannot be undone.">
                    <?= csrf_field() ?>
                    <?= method_field('DELETE') ?>
                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Delete" aria-label="Delete review from <?= e($t['name']) ?>"><?= icon('trash') ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= partial('admin/destinations/_pagination', ['result' => $result, 'basePath' => '/testimonials', 'query' => ['q' => $q, 'status' => $status]]) ?>
  <?php endif; ?>
</div>
<p class="form-hint mt-2"><?= icon('grip', 'icon icon-sm') ?> Drag rows by the handle to change the order reviews appear on the website.</p>
