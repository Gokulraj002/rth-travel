<?php
/**
 * Friendly empty state for a dashboard widget.
 * @var string $icon
 * @var string $title
 * @var string $text
 * @var string|null $href
 * @var string|null $cta
 */
?>
<div class="rth-empty rth-empty-sm">
  <div class="rth-empty-icon"><?= icon($icon) ?></div>
  <p class="rth-empty-title"><?= e($title) ?></p>
  <p class="rth-empty-text"><?= e($text) ?></p>
  <?php if (!empty($href) && !empty($cta)): ?>
    <a href="<?= e(url($href)) ?>" class="btn btn-sm"><?= icon('plus') ?> <?= e($cta) ?></a>
  <?php endif; ?>
</div>
