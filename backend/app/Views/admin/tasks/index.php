<?php
/**
 * Follow-ups with tabs Overdue / Today / Upcoming / Done.
 * @var string $tab
 * @var array{q:string,assigned:string} $filters
 * @var array<string,int> $counts
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int} $result
 * @var list<array{id:int,name:string}> $assignees
 */
use App\Repositories\TaskRepository as Tasks;

$active = array_filter($filters, static fn ($v) => $v !== '');
$tabUrl = static fn (string $t): string => url('/tasks') . '?' . http_build_query([...$active, 'tab' => $t]);
$returnTo = '/tasks?tab=' . $tab;
$rows = $result['rows'];
$empty = [
    'overdue' => ['Nothing overdue', 'You are on top of every follow-up. Nice work!'],
    'today' => ['Nothing due today', 'Plan a call-back from any enquiry, or add a reminder here.'],
    'upcoming' => ['No upcoming follow-ups', 'Add reminders for quotes to chase, payments to collect and trips to prepare.'],
    'done' => ['Nothing completed yet', 'Follow-ups you tick off will show up here.'],
][$tab];
?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/tasks')) ?>" class="row g-2 align-items-end" role="search">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div class="col-12 col-md-5">
        <label class="form-label" for="tf-q">Search</label>
        <div class="input-icon">
          <span class="input-icon-addon"><?= icon('search') ?></span>
          <input type="search" id="tf-q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Title or notes">
        </div>
      </div>
      <div class="col-8 col-md-3">
        <label class="form-label" for="tf-assigned">Assigned to</label>
        <select id="tf-assigned" name="assigned" class="form-select">
          <option value="">Everyone</option>
          <option value="me"<?= $filters['assigned'] === 'me' ? ' selected' : '' ?>>Me</option>
          <option value="none"<?= $filters['assigned'] === 'none' ? ' selected' : '' ?>>Unassigned</option>
          <?php foreach ($assignees as $a): ?>
            <option value="<?= e($a['id']) ?>"<?= $filters['assigned'] === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-4 col-md d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill">Filter</button>
        <?php if ($active !== []): ?>
          <a href="<?= e(url('/tasks?tab=' . $tab)) ?>" class="btn btn-icon" title="Clear filters" aria-label="Clear filters"><?= icon('x') ?></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <ul class="nav nav-tabs card-header-tabs rth-crm-tabs" role="tablist">
      <?php foreach (Tasks::TABS as $key => $label): ?>
        <li class="nav-item">
          <a href="<?= e($tabUrl($key)) ?>" class="nav-link<?= $tab === $key ? ' active' : '' ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
            <?= e($label) ?>
            <?php if ($counts[$key] > 0): ?>
              <span class="badge <?= $key === 'overdue' ? 'bg-red text-white' : ($key === 'today' ? 'bg-orange-lt' : 'bg-secondary-lt') ?>"><?= e($counts[$key]) ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php if ($rows === []): ?>
    <div class="card-body">
      <div class="empty py-5">
        <div class="empty-icon"><?= icon($tab === 'overdue' ? 'check' : 'bell') ?></div>
        <p class="empty-title"><?= e($empty[0]) ?></p>
        <p class="empty-subtitle text-secondary"><?= e($empty[1]) ?></p>
        <?php if ($tab !== 'done'): ?>
          <div class="empty-action"><a href="<?= e(url('/tasks/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> New follow-up</a></div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="list-group list-group-flush">
      <?php foreach ($rows as $t): $late = Tasks::isOverdue($t); $rel = Tasks::related($t); $done = $t['done_at'] !== null; ?>
        <div class="list-group-item<?= $done ? ' rth-crm-task-done' : '' ?>">
          <div class="d-flex align-items-start gap-3">
            <form method="post" action="<?= e(url('/tasks/' . $t['id'] . ($done ? '/reopen' : '/done'))) ?>" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
              <?php if ($done): ?>
                <button type="submit" class="btn btn-icon btn-success" title="Reopen" aria-label="Reopen “<?= e($t['title']) ?>”"><?= icon('check') ?></button>
              <?php else: ?>
                <button type="submit" class="btn btn-icon btn-outline-success" title="Mark done" aria-label="Mark “<?= e($t['title']) ?>” done"><?= icon('check') ?></button>
              <?php endif; ?>
            </form>
            <div class="flex-fill min-w-0">
              <div class="fw-semibold rth-crm-task-title"><?= e($t['title']) ?></div>
              <div class="small d-flex flex-wrap column-gap-3">
                <span class="<?= $late ? 'text-danger fw-semibold' : 'text-secondary' ?>"><?= icon('clock', 'icon icon-sm') ?> <?= e(nice_date($t['due_at'])) ?><?= $late ? ' · overdue' : '' ?></span>
                <?php if ($done): ?><span class="text-secondary"><?= icon('check', 'icon icon-sm') ?> Done <?= e(nice_date($t['done_at'])) ?></span><?php endif; ?>
                <?php if ($rel): ?>
                  <span><?= icon('link', 'icon icon-sm') ?> <?php if ($rel['url']): ?><a href="<?= e(url($rel['url'])) ?>"><?= e($rel['label']) ?></a><?php else: ?><?= e($rel['label']) ?><?php endif; ?></span>
                <?php endif; ?>
                <span class="text-secondary"><?= icon('user', 'icon icon-sm') ?> <?= e($t['assigned_name'] ?? 'Unassigned') ?></span>
              </div>
              <?php if (trim((string) $t['notes']) !== ''): ?><div class="small text-secondary mt-1 rth-crm-pre"><?= e(str_limit((string) $t['notes'], 220)) ?></div><?php endif; ?>
            </div>
            <div class="d-flex gap-1">
              <a href="<?= e(url('/tasks/' . $t['id'] . '/edit')) ?>" class="btn btn-icon btn-ghost-secondary" title="Edit" aria-label="Edit “<?= e($t['title']) ?>”"><?= icon('edit') ?></a>
              <form method="post" action="<?= e(url('/tasks/' . $t['id'])) ?>" class="m-0" data-confirm="Delete the follow-up “<?= e($t['title']) ?>”?">
                <?= csrf_field() ?>
                <?= method_field('DELETE') ?>
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <button type="submit" class="btn btn-icon btn-ghost-danger" title="Delete" aria-label="Delete “<?= e($t['title']) ?>”"><?= icon('trash') ?></button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?= partial('admin/destinations/_pagination', ['result' => $result, 'basePath' => '/tasks', 'query' => [...$active, 'tab' => $tab]]) ?>
  <?php endif; ?>
</div>
