<?php
/**
 * Shared search + status filter bar (card header). Used by destinations, testimonials and faqs.
 * @var string $basePath @var string $q @var string $status @var string $placeholder
 */
?>
<div class="card-header">
  <form method="get" action="<?= e(url($basePath)) ?>" class="row g-2 align-items-center w-100" role="search">
    <div class="col-12 col-md">
      <div class="input-icon">
        <span class="input-icon-addon"><?= icon('search') ?></span>
        <input type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e($placeholder ?? 'Search…') ?>" aria-label="Search">
      </div>
    </div>
    <div class="col-8 col-md-auto">
      <select class="form-select" name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option>
        <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option>
      </select>
    </div>
    <div class="col-4 col-md-auto d-flex gap-2">
      <button type="submit" class="btn btn-primary">Filter</button>
      <?php if ($q !== '' || $status !== ''): ?>
        <a class="btn btn-ghost-secondary" href="<?= e(url($basePath)) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>
