<?php
/**
 * Stand-alone page for the "Costs & profit" panel (GET /booking-services/preview/{booking_id}).
 * @var array<string,mixed> $booking
 */
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-sm mb-0">
      <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <div class="me-auto">
          <div class="fw-semibold"><?= e($booking['number']) ?> · <?= e($booking['title']) ?></div>
          <div class="text-secondary small">
            <?= e(nice_day($booking['travel_start'])) ?> → <?= e(nice_day($booking['travel_end'])) ?> ·
            <?= e((int) $booking['adults']) ?> adult<?= (int) $booking['adults'] === 1 ? '' : 's' ?><?= (int) $booking['children'] > 0 ? ', ' . e((int) $booking['children']) . ' child' . ((int) $booking['children'] === 1 ? '' : 'ren') : '' ?>
          </div>
        </div>
        <?= status_badge((string) $booking['status']) ?>
      </div>
    </div>
  </div>
  <div class="col-12">
    <?= partial('admin/booking-services/panel', ['booking' => $booking, 'returnTo' => 'preview']) ?>
  </div>
</div>
