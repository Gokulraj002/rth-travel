<?php
/**
 * @var string $q @var string $status
 * @var array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int} $result
 */
$filtered = $q !== '' || $status !== '';
?>
<div class="card">
  <?= partial('admin/destinations/_filters', ['basePath' => '/faqs', 'q' => $q, 'status' => $status, 'placeholder' => 'Search questions and answers…']) ?>

  <?php if ($result['rows'] === []): ?>
    <div class="empty">
      <div class="empty-icon"><?= icon('help') ?></div>
      <p class="empty-title"><?= $filtered ? 'No questions match your search' : 'No questions yet' ?></p>
      <p class="empty-subtitle text-secondary">
        <?= $filtered ? 'Try a different word or clear the filters.' : 'Answer the questions travellers ask you most — permits, weather, payments — so they can book with confidence.' ?>
      </p>
      <?php if (!$filtered): ?>
        <div class="empty-action"><a href="<?= e(url('/faqs/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add question</a></div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th class="w-1"><span class="visually-hidden">Order</span></th>
            <th>Question</th>
            <th>Status</th>
            <th class="w-1"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody data-sortable data-sortable-url="/faqs/reorder">
          <?php foreach ($result['rows'] as $f): ?>
            <tr data-id="<?= e($f['id']) ?>">
              <td><span data-sort-handle title="Drag to change the order on the website"><?= icon('grip') ?></span></td>
              <td>
                <a href="<?= e(url('/faqs/' . $f['id'] . '/edit')) ?>" class="fw-semibold text-reset"><?= e($f['question']) ?></a>
                <div class="small text-secondary"><?= e(str_limit((string) $f['answer'], 140)) ?></div>
              </td>
              <td>
                <?php if ($f['status'] === 'published'): ?>
                  <span class="badge badge-published">Published</span>
                <?php else: ?>
                  <span class="badge badge-draft">Draft</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex gap-1 justify-content-end">
                  <a href="<?= e(url('/faqs/' . $f['id'] . '/edit')) ?>" class="btn btn-sm btn-icon btn-ghost-primary" title="Edit" aria-label="Edit question"><?= icon('edit') ?></a>
                  <form method="post" action="<?= e(url('/faqs/' . $f['id'])) ?>" class="m-0"
                        data-confirm="Delete this question? This cannot be undone.">
                    <?= csrf_field() ?>
                    <?= method_field('DELETE') ?>
                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Delete" aria-label="Delete question"><?= icon('trash') ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= partial('admin/destinations/_pagination', ['result' => $result, 'basePath' => '/faqs', 'query' => ['q' => $q, 'status' => $status]]) ?>
  <?php endif; ?>
</div>
<p class="form-hint mt-2"><?= icon('grip', 'icon icon-sm') ?> Drag rows by the handle to change the order questions appear on the website.</p>
