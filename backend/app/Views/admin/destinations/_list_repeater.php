<?php
/**
 * Simple ordered text list (data-repeater): one text input per row named "<name>[]".
 * @var string $name @var list<string> $items @var string $placeholder @var string $addLabel
 */
$row = static function (string $name, string $value, string $placeholder, int $number): string {
    return '<div class="rth-repeater-item d-flex gap-2 align-items-center">'
        . '<span class="rth-repeater-number" data-repeater-number>' . e($number) . '</span>'
        . '<input type="text" class="form-control" name="' . e($name) . '[]" value="' . e($value) . '" maxlength="300" placeholder="' . e($placeholder) . '" aria-label="' . e($placeholder) . '">'
        . '<div class="btn-list flex-nowrap">'
        . '<button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-up title="Move up" aria-label="Move up">' . icon('arrow-up') . '</button>'
        . '<button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-down title="Move down" aria-label="Move down">' . icon('arrow-down') . '</button>'
        . '<button type="button" class="btn btn-icon btn-ghost-danger" data-repeater-remove title="Remove" aria-label="Remove">' . icon('x') . '</button>'
        . '</div></div>';
};
?>
<div data-repeater>
  <div data-repeater-items>
    <?php foreach ($items as $i => $text): ?>
      <?= $row($name, (string) $text, $placeholder, $i + 1) ?>
    <?php endforeach; ?>
  </div>
  <template data-repeater-template><?= $row($name, '', $placeholder, 0) ?></template>
  <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-repeater-add><?= icon('plus') ?> <?= e($addLabel) ?></button>
  <?php if ($err = error_for($name)): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
</div>
