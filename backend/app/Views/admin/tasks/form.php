<?php
/**
 * New / edit follow-up.
 * @var array<string,mixed>|null $task
 * @var array<string,string|null> $defaults
 * @var array<string,list<array{value:string,label:string}>> $relatedOptions
 * @var list<array{id:int,name:string}> $assignees
 * @var string $back
 */
$isEdit = $task !== null;
$action = $isEdit ? url('/tasks/' . $task['id']) : url('/tasks');
$val = static fn (string $k): string => (string) old($k, $defaults[$k] ?? '');
$err = static fn (string $k): string => ($m = error_for($k)) ? '<div class="invalid-feedback">' . e($m) . '</div>' : '';
$related = $val('related');
$assigned = $val('assigned_to');
?>
<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
  <?php if (!$isEdit && $related !== ''): ?><input type="hidden" name="return" value="1"><?php endif; ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title">What needs doing?</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label required" for="title">Title</label>
            <input type="text" class="form-control<?= invalid('title') ?>" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="200" placeholder="e.g. Call back about houseboat dates" required autofocus>
            <?= $err('title') ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="related">Related to</label>
            <select id="related" name="related" class="form-select<?= invalid('related') ?>">
              <option value="">Nothing — general reminder</option>
              <?php foreach ($relatedOptions as $group => $opts): ?>
                <optgroup label="<?= e($group) ?>">
                  <?php foreach ($opts as $o): ?>
                    <option value="<?= e($o['value']) ?>"<?= $related === $o['value'] ? ' selected' : '' ?>><?= e(str_limit($o['label'], 90)) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <?= $err('related') ?>
            <div class="form-hint">Linking to an enquiry also updates its “next follow-up” date.</div>
          </div>
          <div>
            <label class="form-label" for="notes">Notes</label>
            <textarea class="form-control<?= invalid('notes') ?>" id="notes" name="notes" rows="4" maxlength="2000" data-counter="2000" placeholder="Anything to remember for the call"><?= e($val('notes')) ?></textarea>
            <?= $err('notes') ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">When &amp; who</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label required" for="due_at">Due</label>
            <input type="datetime-local" class="form-control<?= invalid('due_at') ?>" id="due_at" name="due_at" value="<?= e($val('due_at')) ?>" required>
            <?= $err('due_at') ?>
            <div class="form-hint">Shows in red on the Follow-ups list once this time has passed.</div>
          </div>
          <div>
            <label class="form-label" for="assigned_to">Assigned to</label>
            <select id="assigned_to" name="assigned_to" class="form-select<?= invalid('assigned_to') ?>">
              <option value="">Nobody</option>
              <?php foreach ($assignees as $a): ?>
                <option value="<?= e($a['id']) ?>"<?= $assigned === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('assigned_to') ?>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Add follow-up' ?></button>
          <a href="<?= e(url($back)) ?>" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
<?php if ($isEdit): ?>
  <div class="row mt-3">
    <div class="col-lg-4 offset-lg-8">
      <form method="post" action="<?= e(url('/tasks/' . $task['id'])) ?>" data-confirm="Delete this follow-up?">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-outline-danger w-100"><?= icon('trash') ?> Delete follow-up</button>
      </form>
    </div>
  </div>
<?php endif; ?>
