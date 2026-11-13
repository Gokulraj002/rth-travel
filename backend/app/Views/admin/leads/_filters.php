<?php
/**
 * Lead filters (assigned, priority, source, search) + board/list toggle.
 * @var array{q:string,assigned:string,priority:string,source:string,stage:string} $filters
 * @var list<array{id:int,name:string}> $assignees
 * @var string $viewMode board|list
 */
use App\Repositories\EnquiryRepository as Enq;

$active = array_filter($filters, static fn ($v) => $v !== '');
$toggle = static fn (string $mode): string => url('/leads') . '?' . http_build_query(array_filter([...$active, 'view' => $mode === 'list' ? 'list' : ''], static fn ($v) => $v !== ''));
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/leads')) ?>" class="row g-2 align-items-end" role="search">
      <?php if ($viewMode === 'list'): ?><input type="hidden" name="view" value="list"><?php endif; ?>
      <div class="col-12 col-md-3">
        <label class="form-label" for="lf-q">Search</label>
        <div class="input-icon">
          <span class="input-icon-addon"><?= icon('search') ?></span>
          <input type="search" id="lf-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Name, phone, destination">
        </div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="lf-assigned">Assigned to</label>
        <select id="lf-assigned" name="assigned" class="form-select">
          <option value="">Everyone</option>
          <option value="me"<?= $filters['assigned'] === 'me' ? ' selected' : '' ?>>Me</option>
          <option value="none"<?= $filters['assigned'] === 'none' ? ' selected' : '' ?>>Unassigned</option>
          <?php foreach ($assignees as $a): ?>
            <option value="<?= e($a['id']) ?>"<?= $filters['assigned'] === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="lf-priority">Priority</label>
        <select id="lf-priority" name="priority" class="form-select">
          <option value="">Any</option>
          <?php foreach (Enq::PRIORITIES as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $filters['priority'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="lf-source">Source</label>
        <select id="lf-source" name="source" class="form-select">
          <option value="">Any</option>
          <?php foreach (Enq::allSources() as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $filters['source'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($viewMode === 'list'): ?>
        <div class="col-6 col-md-2">
          <label class="form-label" for="lf-stage">Stage</label>
          <select id="lf-stage" name="stage" class="form-select">
            <option value="">All</option>
            <?php foreach (Enq::stages() as $k => $s): ?>
              <option value="<?= e($k) ?>"<?= $filters['stage'] === $k ? ' selected' : '' ?>><?= e($s['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="col-6 col-md d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill"><?= icon('filter', 'icon icon-sm') ?> Filter</button>
        <?php if ($active !== []): ?>
          <a href="<?= e(url('/leads') . ($viewMode === 'list' ? '?view=list' : '')) ?>" class="btn btn-icon" title="Clear filters" aria-label="Clear filters"><?= icon('x') ?></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <div class="btn-group" role="group" aria-label="View">
    <a href="<?= e($toggle('board')) ?>" class="btn<?= $viewMode === 'board' ? ' btn-primary' : '' ?>"<?= $viewMode === 'board' ? ' aria-current="page"' : '' ?>><?= icon('columns') ?> Board</a>
    <a href="<?= e($toggle('list')) ?>" class="btn<?= $viewMode === 'list' ? ' btn-primary' : '' ?>"<?= $viewMode === 'list' ? ' aria-current="page"' : '' ?>><?= icon('list') ?> List</a>
  </div>
  <?php if ($viewMode === 'board'): ?>
    <span class="small text-secondary ms-md-2">Drag a card to another column — or use the menu on each card — to move a lead.</span>
  <?php endif; ?>
</div>
