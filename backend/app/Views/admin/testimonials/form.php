<?php
/** @var array<string,mixed>|null $review */
$r = $review ?? [];
$isEdit = $review !== null;
$action = $isEdit ? url('/testimonials/' . $r['id']) : url('/testimonials');
$val = static fn (string $k, mixed $default = ''): string => (string) old($k, $r[$k] ?? $default);
$fieldError = static fn (string $k): string => ($err = error_for($k)) ? '<div class="invalid-feedback">' . e($err) . '</div>' : '';
$rating = (int) $val('rating', 5);
$status = $val('status', 'published');
?>
<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Review</h3></div>
        <div class="card-body">
          <div class="alert alert-info mb-3" role="note">
            Use real guest reviews (with permission). A first name and initial — like “Priya S.” — is enough.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required" for="name">Guest name</label>
              <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" placeholder="e.g. Priya S." required>
              <?= $fieldError('name') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="from_city">From (city)</label>
              <input type="text" class="form-control<?= invalid('from_city') ?>" id="from_city" name="from_city" value="<?= e($val('from_city')) ?>" maxlength="120" placeholder="e.g. Pune">
              <?= $fieldError('from_city') ?>
            </div>
            <div class="col-12">
              <label class="form-label" for="trip">Trip</label>
              <input type="text" class="form-control<?= invalid('trip') ?>" id="trip" name="trip" value="<?= e($val('trip')) ?>" maxlength="160" placeholder="e.g. Kashmir Honeymoon">
              <?= $fieldError('trip') ?>
            </div>
            <div class="col-12">
              <label class="form-label required" for="quote">What they said</label>
              <textarea class="form-control<?= invalid('quote') ?>" id="quote" name="quote" rows="5" maxlength="600" data-counter="600" placeholder="Their words, lightly tidied for spelling." required><?= e($val('quote')) ?></textarea>
              <?= $fieldError('quote') ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Rating &amp; status</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label required" for="rating">Star rating</label>
            <select class="form-select<?= invalid('rating') ?>" id="rating" name="rating">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?= $i ?>"<?= $rating === $i ? ' selected' : '' ?>><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> — <?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
              <?php endfor; ?>
            </select>
            <?= $fieldError('rating') ?>
          </div>
          <label class="form-label" for="status">Status</label>
          <select class="form-select<?= invalid('status') ?>" id="status" name="status">
            <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published — show on the website</option>
            <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft — hidden from the website</option>
          </select>
          <?= $fieldError('status') ?>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Add review' ?></button>
          <a href="<?= e(url('/testimonials')) ?>" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
