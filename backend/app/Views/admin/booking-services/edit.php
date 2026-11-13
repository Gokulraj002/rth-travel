<?php
/**
 * Edit one booking service.
 * @var array<string,mixed> $service @var array<string,mixed> $booking @var string $return @var string $cancelUrl
 */
$sid = (int) $service['id'];
?>
<div class="rth-ops">
  <div class="card card-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
      <div class="me-auto">
        <div class="fw-semibold"><?= e($booking['number']) ?> · <?= e($booking['title']) ?></div>
        <div class="text-secondary small"><?= e(nice_day($booking['travel_start'])) ?> → <?= e(nice_day($booking['travel_end'])) ?> · Selling price <?= e(inr($booking['total_amount'])) ?></div>
      </div>
      <a href="<?= e(url($cancelUrl)) ?>" class="btn btn-ghost-secondary"><?= icon('arrow-left') ?> Back</a>
    </div>
  </div>

  <form method="post" action="<?= e(url('/booking-services/' . $sid)) ?>" novalidate>
    <?= csrf_field() ?>
    <?= method_field('PUT') ?>
    <?php if ($return !== ''): ?><input type="hidden" name="return" value="<?= e($return) ?>"><?php endif; ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Service details</h3></div>
      <div class="card-body">
        <?= partial('admin/booking-services/_fields', ['svc' => $service, 'idp' => 'svc-edit-', 'useOld' => true]) ?>
      </div>
      <div class="card-footer d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save changes</button>
        <a href="<?= e(url($cancelUrl)) ?>" class="btn btn-ghost-secondary">Cancel</a>
      </div>
    </div>
  </form>

  <div class="card mt-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="me-auto">
        <div class="fw-semibold">Remove this service</div>
        <div class="text-secondary small">If the supplier cancelled but you want to keep a record, set the status to “Cancelled” instead — cancelled services are not counted in cost or payables.</div>
      </div>
      <form method="post" action="<?= e(url('/booking-services/' . $sid)) ?>" class="m-0" data-confirm="Remove “<?= e($service['description']) ?>” from this booking?">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <?php if ($return !== ''): ?><input type="hidden" name="return" value="<?= e($return) ?>"><?php endif; ?>
        <button type="submit" class="btn btn-outline-danger"><?= icon('trash') ?> Remove</button>
      </form>
    </div>
  </div>
</div>
