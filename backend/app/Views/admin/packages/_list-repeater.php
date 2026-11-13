<?php
/**
 * Card with a repeater of single-line text inputs (highlights / inclusions / exclusions).
 * @var string $name        input name (e.g. "highlights" → highlights[])
 * @var string $title       card title
 * @var string $hint        short help text
 * @var string $placeholder placeholder for new rows
 * @var string $addLabel    "Add highlight"
 * @var list<string> $items current values
 */
$items = $items === [] ? [''] : $items;
$row = static function (string $value) use ($name, $placeholder): string {
    ob_start(); ?>
  <div class="rth-repeater-item d-flex gap-2 align-items-center">
    <input type="text" class="form-control" name="<?= e($name) ?>[]" value="<?= e($value) ?>" maxlength="300" placeholder="<?= e($placeholder) ?>" aria-label="<?= e($placeholder) ?>">
    <div class="btn-list flex-nowrap">
      <button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-up title="Move up" aria-label="Move up"><?= icon('arrow-up') ?></button>
      <button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-down title="Move down" aria-label="Move down"><?= icon('arrow-down') ?></button>
      <button type="button" class="btn btn-icon btn-ghost-danger" data-repeater-remove title="Remove" aria-label="Remove"><?= icon('x') ?></button>
    </div>
  </div>
<?php return (string) ob_get_clean();
};
?>
<div class="card mb-3">
  <div class="card-header">
    <div>
      <h3 class="card-title"><?= e($title) ?></h3>
      <div class="card-subtitle text-secondary small"><?= e($hint) ?></div>
    </div>
  </div>
  <div class="card-body" data-repeater>
    <div data-repeater-items>
      <?php foreach ($items as $value): ?>
        <?= $row((string) $value) ?>
      <?php endforeach; ?>
    </div>
    <template data-repeater-template><?= $row('') ?></template>
    <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-repeater-add><?= icon('plus') ?> <?= e($addLabel) ?></button>
  </div>
</div>
