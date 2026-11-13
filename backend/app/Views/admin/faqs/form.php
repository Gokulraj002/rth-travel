<?php
/** @var array<string,mixed>|null $faq */
$f = $faq ?? [];
$isEdit = $faq !== null;
$action = $isEdit ? url('/faqs/' . $f['id']) : url('/faqs');
$val = static fn (string $k, mixed $default = ''): string => (string) old($k, $f[$k] ?? $default);
$fieldError = static fn (string $k): string => ($err = error_for($k)) ? '<div class="invalid-feedback">' . e($err) . '</div>' : '';
$status = $val('status', 'published');
?>
<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Question &amp; answer</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label required" for="question">Question</label>
            <input type="text" class="form-control<?= invalid('question') ?>" id="question" name="question" value="<?= e($val('question')) ?>" maxlength="300" data-counter="300" placeholder="e.g. Do I need a permit for Ladakh?" required>
            <?= $fieldError('question') ?>
          </div>
          <div>
            <label class="form-label required" for="answer">Answer</label>
            <textarea class="form-control<?= invalid('answer') ?>" id="answer" name="answer" rows="7" maxlength="5000" placeholder="A short, friendly answer in plain words." required><?= e($val('answer')) ?></textarea>
            <?= $fieldError('answer') ?>
            <div class="form-hint mt-1">Keep it short — two or three sentences is ideal.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Publish</h3></div>
        <div class="card-body">
          <label class="form-label" for="status">Status</label>
          <select class="form-select<?= invalid('status') ?>" id="status" name="status">
            <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published — show on the website</option>
            <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft — hidden from the website</option>
          </select>
          <?= $fieldError('status') ?>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Add question' ?></button>
          <a href="<?= e(url('/faqs')) ?>" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
