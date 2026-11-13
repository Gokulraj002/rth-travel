<?php
/**
 * One lead card on the kanban board.
 * @var array<string,mixed> $lead
 * @var array<string,array{label:string,color:string,status:string}> $stages
 */
use App\Repositories\EnquiryRepository as Enq;

$l = $lead;
$id = (int) $l['id'];
$trip = Enq::tripLabel($l);
$when = Enq::whenLabel($l);
$late = Enq::followUpOverdue($l);
$open = !in_array($l['stage'], ['won', 'lost'], true);
?>
<article class="rth-kanban-card rth-crm-card<?= $l['priority'] === 'high' ? ' is-high' : '' ?>" draggable="true" tabindex="-1"
         data-crm-card data-id="<?= $id ?>" data-stage="<?= e($l['stage']) ?>" data-name="<?= e($l['name']) ?>" data-budget="<?= e((string) ($l['budget'] ?? 0)) ?>">
  <div class="d-flex align-items-start gap-2">
    <a href="<?= e(url('/enquiries/' . $id)) ?>" class="rth-kanban-card-title text-reset flex-fill text-truncate"><?= e($l['name']) ?></a>
    <?php if ($l['priority'] !== 'normal'): ?>
      <?= status_badge((string) $l['priority'], Enq::PRIORITIES[$l['priority']] ?? '') ?>
    <?php endif; ?>
  </div>
  <?php if ($trip !== '—'): ?><div class="rth-kanban-card-meta text-truncate" title="<?= e($trip) ?>"><?= icon('map-pin', 'icon icon-sm') ?> <?= e($trip) ?></div><?php endif; ?>
  <div class="rth-kanban-card-meta d-flex flex-wrap column-gap-2">
    <?php if ($when !== ''): ?><span><?= icon('calendar', 'icon icon-sm') ?> <?= e($when) ?></span><?php endif; ?>
    <span><?= icon('users', 'icon icon-sm') ?> <?= e((int) $l['adults'] + (int) $l['children']) ?> pax</span>
    <?php if ($l['budget'] !== null): ?><span><?= icon('wallet', 'icon icon-sm') ?> <?= e(inr($l['budget'])) ?></span><?php endif; ?>
  </div>
  <?php if ($open && $l['next_follow_up_at']): ?>
    <div class="rth-kanban-card-meta<?= $late ? ' rth-crm-late' : '' ?>"><?= icon('bell', 'icon icon-sm') ?> <?= e(nice_date($l['next_follow_up_at'])) ?><?= $late ? ' · overdue' : '' ?></div>
  <?php elseif ($l['stage'] === 'lost' && $l['lost_reason']): ?>
    <div class="rth-kanban-card-meta text-truncate" title="<?= e($l['lost_reason']) ?>"><?= icon('x', 'icon icon-sm') ?> <?= e($l['lost_reason']) ?></div>
  <?php endif; ?>
  <div class="d-flex align-items-center gap-2 mt-2">
    <span class="rth-crm-owner" title="<?= e($l['assigned_name'] ? 'Assigned to ' . $l['assigned_name'] : 'Not assigned') ?>">
      <span class="avatar avatar-xs rth-avatar"><?= e($l['assigned_name'] ? mb_strtoupper(mb_substr((string) $l['assigned_name'], 0, 1)) : '?') ?></span>
      <span class="small text-secondary text-truncate"><?= e($l['assigned_name'] ?? 'Unassigned') ?></span>
    </span>
    <form method="post" action="<?= e(url('/leads/' . $id . '/stage')) ?>" class="ms-auto rth-crm-move" data-crm-move-form>
      <?= csrf_field() ?>
      <label class="visually-hidden" for="mv-<?= $id ?>">Move <?= e($l['name']) ?> to stage</label>
      <select id="mv-<?= $id ?>" name="stage" class="form-select form-select-sm" data-crm-stage-select>
        <?php foreach ($stages as $key => $s): ?>
          <option value="<?= e($key) ?>"<?= $key === $l['stage'] ? ' selected' : '' ?>><?= e($s['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-sm rth-crm-nojs">Move</button>
    </form>
  </div>
</article>
