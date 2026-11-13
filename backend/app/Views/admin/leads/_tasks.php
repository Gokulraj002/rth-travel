<?php
/**
 * Compact list of open follow-ups with "Done" buttons.
 * @var list<array<string,mixed>> $tasks
 * @var string $returnTo  page to come back to after Done (e.g. '/enquiries/12')
 * @var string $addUrl    "Add follow-up" link
 */
use App\Repositories\TaskRepository as Tasks;
?>
<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title"><?= icon('bell') ?> Follow-ups</h3>
    <div class="card-actions"><a href="<?= e(url($addUrl)) ?>" class="btn btn-sm"><?= icon('plus', 'icon icon-sm') ?> Add</a></div>
  </div>
  <?php if ($tasks === []): ?>
    <div class="card-body text-secondary small">No follow-ups planned. Add one so you never forget to call back.</div>
  <?php else: ?>
    <div class="list-group list-group-flush">
      <?php foreach ($tasks as $t): $late = Tasks::isOverdue($t); ?>
        <div class="list-group-item d-flex align-items-start gap-2">
          <form method="post" action="<?= e(url('/tasks/' . $t['id'] . '/done')) ?>" class="m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <button type="submit" class="btn btn-sm btn-icon btn-outline-success" title="Mark done" aria-label="Mark “<?= e($t['title']) ?>” done"><?= icon('check', 'icon icon-sm') ?></button>
          </form>
          <div class="flex-fill min-w-0">
            <a href="<?= e(url('/tasks/' . $t['id'] . '/edit')) ?>" class="text-reset fw-semibold"><?= e($t['title']) ?></a>
            <div class="small <?= $late ? 'text-danger fw-semibold' : 'text-secondary' ?>"><?= icon('clock', 'icon icon-sm') ?> <?= e(nice_date($t['due_at'])) ?><?= $late ? ' · overdue' : '' ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
