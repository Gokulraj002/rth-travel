<?php
/**
 * Lead pipeline: kanban board (default) or list.
 * @var array{q:string,assigned:string,priority:string,source:string,stage:string} $filters
 * @var string $viewMode
 * @var array{cards:array<string,list<array<string,mixed>>>,counts:array<string,int>,values:array<string,float>}|null $board
 * @var array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,perPage:int}|null $result
 * @var list<array{id:int,name:string}> $assignees
 */
use App\Repositories\EnquiryRepository as Enq;

$stages = Enq::stages();
$active = array_filter($filters, static fn ($v) => $v !== '');
?>
<?= partial('admin/leads/_filters', ['filters' => $filters, 'assignees' => $assignees, 'viewMode' => $viewMode]) ?>

<?php if ($viewMode === 'board' && $board !== null): ?>
  <?php $total = array_sum($board['counts']); ?>
  <?php if ($total === 0 && $active === []): ?>
    <div class="card"><div class="card-body">
      <div class="empty py-5">
        <div class="empty-icon"><?= icon('columns') ?></div>
        <p class="empty-title">Your pipeline is empty</p>
        <p class="empty-subtitle text-secondary">Website enquiries land in “New” automatically. Add phone, WhatsApp and walk-in enquiries yourself so every lead is in one place.</p>
        <div class="empty-action"><a href="<?= e(url('/enquiries/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add an enquiry</a></div>
      </div>
    </div></div>
  <?php else: ?>
    <div class="rth-kanban rth-crm-board" data-crm-board data-stage-url="<?= e(url('/leads/__ID__/stage')) ?>">
      <?php foreach ($stages as $key => $s): $cards = $board['cards'][$key] ?? []; $count = $board['counts'][$key] ?? 0; ?>
        <section class="rth-kanban-col" data-stage="<?= e($key) ?>" data-crm-col aria-label="<?= e($s['label']) ?>">
          <header class="rth-kanban-head">
            <span class="d-flex align-items-center gap-2 min-w-0">
              <span class="rth-crm-dot bg-<?= e($s['color']) ?>" aria-hidden="true"></span>
              <span class="text-truncate"><?= e($s['label']) ?></span>
              <span class="badge bg-white text-secondary" data-crm-count><?= e($count) ?></span>
            </span>
            <?php $val = (float) ($board['values'][$key] ?? 0); ?>
            <span class="small fw-normal text-secondary" title="Total budget of these leads" data-crm-value data-value="<?= e($val) ?>"<?= $val > 0 ? '' : ' hidden' ?>><?= e(inr($val)) ?></span>
          </header>
          <div class="rth-kanban-list" data-crm-drop data-stage="<?= e($key) ?>">
            <?php foreach ($cards as $lead): ?>
              <?= partial('admin/leads/_card', ['lead' => $lead, 'stages' => $stages]) ?>
            <?php endforeach; ?>
            <div class="rth-crm-empty small text-secondary"<?= $cards !== [] ? ' hidden' : '' ?> data-crm-empty>No leads here</div>
          </div>
          <?php if ($count > count($cards)): ?>
            <a class="small text-center d-block" href="<?= e(url('/leads') . '?' . http_build_query([...$active, 'view' => 'list', 'stage' => $key])) ?>">+ <?= e($count - count($cards)) ?> more in list view</a>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Asked when a lead is moved to "Lost" -->
  <dialog class="rth-crm-dialog" data-crm-lost-dialog aria-labelledby="crm-lost-title">
    <div class="card m-0">
      <div class="card-header"><h3 class="card-title" id="crm-lost-title">Why was this lead lost?</h3></div>
      <div class="card-body">
        <p class="text-secondary small mb-3" data-crm-lost-name></p>
        <label class="form-label" for="crm-lost-reason">Reason</label>
        <select id="crm-lost-reason" class="form-select mb-2" data-crm-lost-reason>
          <option value="">— choose —</option>
          <?php foreach (config('crm')['lost_reasons'] ?? [] as $r): ?>
            <option value="<?= e($r) ?>"><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" class="form-control" maxlength="150" placeholder="Details (optional)" aria-label="Details" data-crm-lost-note>
        <div class="text-danger small mt-2" data-crm-lost-error hidden>Please choose a reason.</div>
      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-ghost-secondary" data-crm-lost-cancel>Cancel</button>
        <button type="button" class="btn btn-danger" data-crm-lost-ok>Mark as lost</button>
      </div>
    </div>
  </dialog>

  <!-- Shown after a lead is moved to "Won" -->
  <dialog class="rth-crm-dialog" data-crm-won-dialog aria-labelledby="crm-won-title">
    <div class="card m-0">
      <div class="card-body text-center py-4">
        <div class="text-success mb-2"><?= icon('sparkles', 'icon icon-lg') ?></div>
        <h3 class="mb-1" id="crm-won-title">Lead won — well done!</h3>
        <p class="text-secondary mb-0" data-crm-won-text>Next step: confirm the trip as a booking.</p>
      </div>
      <div class="card-footer d-flex justify-content-center flex-wrap gap-2">
        <button type="button" class="btn btn-ghost-secondary" data-crm-dialog-close>Later</button>
        <a href="#" class="btn" data-crm-won-enquiry>Open enquiry</a>
        <a href="#" class="btn btn-success" data-crm-won-booking hidden><?= icon('briefcase') ?> Create booking</a>
      </div>
    </div>
  </dialog>
<?php elseif ($result !== null): ?>
  <?php $rows = $result['rows']; ?>
  <div class="card">
    <?php if ($rows === []): ?>
      <div class="card-body">
        <div class="empty py-5">
          <div class="empty-icon"><?= icon('list') ?></div>
          <p class="empty-title"><?= $active !== [] ? 'No leads match these filters' : 'No leads yet' ?></p>
          <p class="empty-subtitle text-secondary"><?= $active !== [] ? 'Try clearing a filter.' : 'Add your first enquiry to start the pipeline.' ?></p>
          <div class="empty-action">
            <?php if ($active !== []): ?>
              <a href="<?= e(url('/leads?view=list')) ?>" class="btn">Show all leads</a>
            <?php else: ?>
              <a href="<?= e(url('/enquiries/create')) ?>" class="btn btn-primary"><?= icon('plus') ?> Add an enquiry</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr><th>Lead</th><th>Trip</th><th>Budget</th><th>Stage</th><th>Assigned</th><th>Next follow-up</th><th class="w-1"></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): $late = Enq::followUpOverdue($r); $when = Enq::whenLabel($r); ?>
              <tr>
                <td>
                  <a href="<?= e(url('/enquiries/' . $r['id'])) ?>" class="fw-semibold"><?= e($r['name']) ?></a>
                  <?php if ($r['priority'] === 'high'): ?> <?= status_badge('high', 'High') ?><?php endif; ?>
                  <div class="small text-secondary text-nowrap">
                    <?= e($r['phone']) ?>
                    <a href="<?= e(wa_link((string) $r['phone'])) ?>" class="ms-1 text-green" target="_blank" rel="noopener" aria-label="WhatsApp <?= e($r['name']) ?>"><?= icon('whatsapp', 'icon icon-sm') ?></a>
                  </div>
                </td>
                <td>
                  <div><?= e(str_limit(Enq::tripLabel($r), 40)) ?></div>
                  <div class="small text-secondary"><?= e(trim($when . ($when !== '' ? ' · ' : '') . Enq::people($r))) ?></div>
                </td>
                <td class="text-nowrap"><?= $r['budget'] !== null ? e(inr($r['budget'])) : '<span class="text-secondary">—</span>' ?></td>
                <td><?= status_badge((string) $r['stage'], Enq::stageLabel((string) $r['stage'])) ?></td>
                <td class="text-secondary small text-nowrap"><?= e($r['assigned_name'] ?? '—') ?></td>
                <td class="small text-nowrap<?= $late ? ' text-danger fw-semibold' : ' text-secondary' ?>">
                  <?= $r['next_follow_up_at'] && !in_array($r['stage'], ['won', 'lost'], true) ? e(nice_date($r['next_follow_up_at'])) . ($late ? ' · overdue' : '') : '—' ?>
                </td>
                <td><a href="<?= e(url('/enquiries/' . $r['id'])) ?>" class="btn btn-sm">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= partial('admin/destinations/_pagination', ['result' => $result, 'basePath' => '/leads', 'query' => [...$active, 'view' => 'list']]) ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
