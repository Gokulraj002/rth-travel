<?php
/**
 * Monthly trip calendar (server-rendered; styles in assets/admin/css/bookings.css).
 * @var string $month
 * @var DateTimeImmutable $first
 * @var list<array{days:list<string>,segments:list<array<string,mixed>>}> $weeks
 * @var array<string,list<array<string,mixed>>> $tasksByDay
 * @var array<string,array{trips?:list<array<string,mixed>>,tasks?:list<array<string,mixed>>}> $agenda
 * @var int $tripCount
 * @var int $departureCount
 * @var int $taskCount
 * @var string $today
 */
use App\Repositories\BookingRepository as Bk;

$tasksUrl = feature('crm') && can('enquiries') ? url('/tasks') : null;
$monthNo = $first->format('Y-m');
$weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$barTitle = static fn (array $b): string => $b['number'] . ' · ' . $b['customer_name'] . ' · ' . $b['title'] . ' (' . Bk::dateRange((string) $b['travel_start'], (string) $b['travel_end']) . ', ' . Bk::people($b) . ')';
?>
<div class="rth-bookings rth-cal">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="badge bg-primary-lt fs-5 py-2 px-3"><?= icon('briefcase', 'icon icon-sm') ?> <?= $tripCount ?> trip<?= $tripCount === 1 ? '' : 's' ?> this month</span>
    <span class="badge bg-green-lt fs-5 py-2 px-3"><?= icon('plane', 'icon icon-sm') ?> <?= $departureCount ?> departure<?= $departureCount === 1 ? '' : 's' ?></span>
    <span class="badge bg-orange-lt fs-5 py-2 px-3"><?= icon('bell', 'icon icon-sm') ?> <?= $taskCount ?> open follow-up<?= $taskCount === 1 ? '' : 's' ?></span>
    <div class="rth-cal-legend ms-md-auto small text-secondary">
      <?php foreach (['pending', 'confirmed', 'ongoing', 'completed'] as $s): ?>
        <span><i class="rth-cal-dot s-<?= e($s) ?>"></i><?= e(Bk::statusLabel($s)) ?></span>
      <?php endforeach; ?>
      <span><i class="rth-cal-dot s-task"></i>Follow-up</span>
    </div>
  </div>

  <!-- Month grid (tablet & desktop) -->
  <div class="card d-none d-md-block rth-cal-card">
    <div class="rth-cal-head" aria-hidden="true">
      <?php foreach ($weekdays as $w): ?><div><?= e($w) ?></div><?php endforeach; ?>
    </div>
    <?php foreach ($weeks as $week): ?>
      <div class="rth-cal-week">
        <div class="rth-cal-bg" aria-hidden="true">
          <?php foreach ($week['days'] as $i => $d): ?>
            <div class="<?= substr($d, 0, 7) !== $monthNo ? 'is-out' : '' ?><?= $d === $today ? ' is-today' : '' ?><?= $i >= 5 ? ' is-weekend' : '' ?>"></div>
          <?php endforeach; ?>
        </div>
        <div class="rth-cal-fg">
          <?php foreach ($week['days'] as $i => $d): ?>
            <div class="rth-cal-num c<?= $i + 1 ?><?= $d === $today ? ' is-today' : '' ?><?= substr($d, 0, 7) !== $monthNo ? ' is-out' : '' ?>">
              <span><?= (int) substr($d, 8, 2) ?><?= (int) substr($d, 8, 2) === 1 ? ' ' . e(date('M', strtotime($d))) : '' ?></span>
            </div>
          <?php endforeach; ?>
          <?php foreach ($week['segments'] as $seg): $b = $seg['booking']; ?>
            <a href="<?= e(url('/bookings/' . $b['id'])) ?>"
               class="rth-cal-bar s-<?= e((string) $b['status']) ?> c<?= (int) $seg['col'] ?> w<?= (int) $seg['span'] ?><?= $seg['starts'] ? ' is-start' : '' ?><?= $seg['ends'] ? ' is-end' : '' ?>"
               title="<?= e($barTitle($b)) ?>">
              <?php if ($seg['starts']): ?><?= icon('plane', 'icon rth-cal-plane') ?><?php else: ?><span class="rth-cal-cont" aria-hidden="true">…</span><?php endif; ?>
              <span class="rth-cal-label"><strong><?= e($b['customer_name']) ?></strong> · <?= e($b['title']) ?></span>
              <span class="rth-cal-pax"><?= (int) $b['adults'] + (int) $b['children'] ?> pax</span>
            </a>
          <?php endforeach; ?>
          <?php foreach ($week['days'] as $i => $d): foreach ($tasksByDay[$d] ?? [] as $t): ?>
            <?php $label = date('g:i a', strtotime((string) $t['due_at'])) . ' · ' . $t['title']; ?>
            <?php if ($tasksUrl): ?>
              <a href="<?= e($tasksUrl) ?>" class="rth-cal-task c<?= $i + 1 ?> w1" title="Follow-up: <?= e($label) ?>"><?= icon('bell', 'icon') ?><span><?= e($t['title']) ?></span></a>
            <?php else: ?>
              <span class="rth-cal-task c<?= $i + 1 ?> w1" title="Follow-up: <?= e($label) ?>"><?= icon('bell', 'icon') ?><span><?= e($t['title']) ?></span></span>
            <?php endif; ?>
          <?php endforeach; endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Agenda (phones) -->
  <div class="d-md-none rth-cal-agenda">
    <?php if ($agenda === []): ?>
      <div class="card"><div class="card-body text-center text-secondary py-5">
        <?= icon('calendar', 'icon icon-lg mb-2') ?>
        <div>No departures, returns or follow-ups in <?= e($first->format('F Y')) ?>.</div>
      </div></div>
    <?php else: ?>
      <?php foreach ($agenda as $day => $items): ?>
        <div class="card mb-2<?= $day === $today ? ' rth-cal-agenda-today' : '' ?>">
          <div class="card-header py-2">
            <div class="fw-bold"><?= e(date('D, j M', strtotime($day))) ?></div>
            <?php if ($day === $today): ?><span class="badge bg-primary text-white ms-2">Today</span><?php endif; ?>
          </div>
          <div class="list-group list-group-flush">
            <?php foreach ($items['trips'] ?? [] as $it): $b = $it['booking']; ?>
              <a href="<?= e(url('/bookings/' . $b['id'])) ?>" class="list-group-item list-group-item-action d-flex gap-2 align-items-start">
                <i class="rth-cal-dot s-<?= e((string) $b['status']) ?> mt-2"></i>
                <div class="flex-fill">
                  <div class="small text-secondary"><?= $it['kind'] === 'departs' ? icon('plane', 'icon icon-sm') . ' Departs' : icon('arrow-left', 'icon icon-sm') . ' Returns' ?> · <?= e($b['number']) ?></div>
                  <div class="fw-semibold"><?= e($b['customer_name']) ?> · <?= (int) $b['adults'] + (int) $b['children'] ?> pax</div>
                  <div class="small text-secondary"><?= e($b['title']) ?><?= $it['kind'] === 'departs' && !empty($b['pickup_point']) ? ' · Pickup: ' . e($b['pickup_point']) : '' ?></div>
                </div>
              </a>
            <?php endforeach; ?>
            <?php foreach ($items['tasks'] ?? [] as $t): ?>
              <<?= $tasksUrl ? 'a href="' . e($tasksUrl) . '"' : 'div' ?> class="list-group-item<?= $tasksUrl ? ' list-group-item-action' : '' ?> d-flex gap-2 align-items-start">
                <i class="rth-cal-dot s-task mt-2"></i>
                <div class="flex-fill">
                  <div class="small text-secondary"><?= icon('bell', 'icon icon-sm') ?> Follow-up · <?= e(date('g:i a', strtotime((string) $t['due_at']))) ?></div>
                  <div><?= e($t['title']) ?></div>
                </div>
              </<?= $tasksUrl ? 'a' : 'div' ?>>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
