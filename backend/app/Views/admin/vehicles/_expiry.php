<?php
/**
 * Document expiry badge: red = expired, orange = within 30 days, green = fine.
 * @var string $label e.g. "Insurance" @var string|null $date
 */
use App\Repositories\VehicleRepository;

$x = VehicleRepository::expiry($date ?? null);
?>
<div class="rth-ops-expiry">
  <span class="text-secondary small"><?= e($label) ?></span>
  <?php if ($x['state'] === null): ?>
    <span class="badge bg-secondary-lt">Not added</span>
  <?php elseif ($x['state'] === 'expired'): ?>
    <span class="badge bg-red text-red-fg" title="Expired on <?= e(nice_day($date)) ?>"><?= icon('alert', 'icon icon-sm') ?> Expired <?= e(nice_day($date)) ?></span>
  <?php elseif ($x['state'] === 'soon'): ?>
    <span class="badge bg-orange text-orange-fg" title="Expires on <?= e(nice_day($date)) ?>"><?= icon('clock', 'icon icon-sm') ?> <?= $x['days'] === 0 ? 'Expires today' : 'In ' . e($x['days']) . ' day' . ($x['days'] === 1 ? '' : 's') ?> · <?= e(nice_day($date)) ?></span>
  <?php else: ?>
    <span class="badge bg-green-lt"><?= icon('check', 'icon icon-sm') ?> Valid till <?= e(nice_day($date)) ?></span>
  <?php endif; ?>
</div>
