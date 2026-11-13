<?php
/**
 * "Log activity" form → POST $action with type + body.
 * @var string $action  e.g. '/enquiries/12/activities'
 */
use App\Repositories\LeadActivityRepository as Act;

$type = (string) old('type', 'call');
?>
<form method="post" action="<?= e(url($action)) ?>" class="rth-crm-log mb-4">
  <?= csrf_field() ?>
  <div class="btn-group w-100 flex-wrap mb-2" role="group" aria-label="Activity type">
    <?php foreach (Act::MANUAL_TYPES as $t): ?>
      <input type="radio" class="btn-check" name="type" id="act-<?= e($t) ?>" value="<?= e($t) ?>" autocomplete="off"<?= $type === $t ? ' checked' : '' ?>>
      <label class="btn btn-sm" for="act-<?= e($t) ?>"><?= icon(Act::icon($t), 'icon icon-sm') ?> <?= e(Act::label($t)) ?></label>
    <?php endforeach; ?>
  </div>
  <?php if ($err = error_for('type')): ?><div class="text-danger small mb-2"><?= e($err) ?></div><?php endif; ?>
  <label class="visually-hidden" for="act-body">What happened</label>
  <textarea id="act-body" name="body" rows="2" maxlength="2000" class="form-control<?= invalid('body') ?>" placeholder="e.g. Spoke to her — wants 5N houseboat + Gulmarg, budget ₹60k, will confirm dates by Friday" required><?= e(old('body', '')) ?></textarea>
  <?php if ($err = error_for('body')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
  <div class="text-end mt-2">
    <button type="submit" class="btn btn-primary btn-sm"><?= icon('plus', 'icon icon-sm') ?> Add to timeline</button>
  </div>
</form>
