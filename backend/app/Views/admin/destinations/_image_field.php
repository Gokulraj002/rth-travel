<?php
/**
 * Single image upload field (app.js data-image-field → POST /media/upload).
 * @var string $name @var string $value @var string $label @var string $hint @var bool $required
 */
$value = (string) ($value ?? '');
$err = error_for($name);
?>
<div class="mb-3">
  <label class="form-label<?= !empty($required) ? ' required' : '' ?>" for="file-<?= e($name) ?>"><?= e($label) ?></label>
  <div class="rth-image-field<?= $err ? ' border-danger' : '' ?>" data-image-field>
    <img data-image-preview src="<?= e(media_url($value)) ?>" alt="" class="mb-2">
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>" data-image-input>
    <div class="d-flex gap-2 align-items-center">
      <input type="file" class="form-control" id="file-<?= e($name) ?>" accept="image/jpeg,image/png,image/webp" data-image-file>
      <button type="button" class="btn btn-ghost-danger" data-image-clear title="Remove photo"><?= icon('x') ?> Remove</button>
    </div>
  </div>
  <?php if ($err): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
  <?php if (!empty($hint)): ?><div class="form-hint mt-1"><?= e($hint) ?></div><?php endif; ?>
</div>
