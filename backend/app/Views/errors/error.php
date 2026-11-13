<div class="empty">
  <div class="empty-header"><?= e((string) ($status ?? 500)) ?></div>
  <p class="empty-title"><?= e($message ?: 'Something went wrong') ?></p>
  <div class="empty-action"><a href="<?= e(url('/')) ?>" class="btn btn-primary">Back to dashboard</a></div>
</div>
