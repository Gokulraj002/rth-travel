<?php
/**
 * @var array<string,mixed> $enquiry
 * @var bool $crm  plan has the "crm" feature
 * CRM only: @var list<array<string,mixed>> $activities @var list<array<string,mixed>> $tasks
 *           @var list<array{id:int,name:string}> $assignees @var array<string,mixed>|null $customer @var string $waText
 */
use App\Core\Auth;
use App\Repositories\EnquiryRepository as Enq;

$e = $enquiry;
$crm = $crm ?? false;
$eid = (int) $e['id'];
$trip = Enq::tripLabel($e);
if (!$crm) {
    $waText = 'Hi ' . $e['name'] . ', this is Reyaan Trip Holiday regarding your enquiry'
        . ($trip !== '—' ? ' about ' . $trip : '') . '. ';
}
$details = [
    ['Phone', $e['phone']],
    ['Email', $e['email'] ?: '—'],
    ['Destination', $e['destination'] ?: '—'],
    ['Package', $e['package'] ?: '—'],
    ['Travel month', $e['travel_month'] ?: '—'],
];
if ($crm) {
    $details[] = ['Travel date', $e['travel_date'] ? nice_day((string) $e['travel_date']) : '—'];
    $details[] = ['Budget', $e['budget'] !== null ? inr($e['budget']) : '—'];
}
array_push(
    $details,
    ['Travellers', Enq::people($e)],
    ['Hotel category', $e['hotel_category'] ?: '—'],
    ['Came from', Enq::sourceLabel($e['source'])],
    ['Received', nice_date($e['created_at'])],
    ['Last updated', nice_date($e['updated_at'])],
);
if ($crm) {
    $details[] = ['Assigned to', $e['assigned_name'] ?? '—'];
}
$status = (string) old('status', $e['status']);
$stages = Enq::stages();
$canQuote = feature('quotations') && can('quotations');
$canCustomers = feature('customers') && can('customers');
$canBook = feature('bookings') && can('bookings');
?>
<div class="mb-3 d-flex flex-wrap gap-3">
  <a href="<?= e(url('/enquiries')) ?>" class="text-secondary">&larr; Back to all enquiries</a>
  <?php if ($crm): ?><a href="<?= e(url('/leads')) ?>" class="text-secondary"><?= icon('columns', 'icon icon-sm') ?> Lead pipeline</a><?php endif; ?>
</div>

<div class="row row-cards">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><?= e($e['name']) ?></h3>
        <div class="card-actions d-flex gap-1 align-items-center">
          <?php if ($crm): ?>
            <?php if ($e['priority'] === 'high'): ?><span class="badge bg-red-lt">High priority</span><?php endif; ?>
            <?= status_badge((string) $e['stage'], Enq::stageLabel((string) $e['stage'])) ?>
          <?php else: ?>
            <span class="badge <?= e(Enq::statusClass((string) $e['status'])) ?>"><?= e(Enq::statusLabel((string) $e['status'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-4">
          <a href="<?= e(Enq::telHref($e['phone'])) ?>" class="btn btn-primary"><?= icon('phone') ?> Call <?= e($e['phone']) ?></a>
          <?php if ($crm): ?>
            <a href="<?= e(wa_link((string) $e['phone'], $waText)) ?>" class="btn btn-success" target="_blank" rel="noopener" title="Opens WhatsApp with your “first reply” message ready to send"><?= icon('whatsapp') ?> WhatsApp reply</a>
          <?php else: ?>
            <a href="<?= e(Enq::whatsappUrl($e['phone'], $waText)) ?>" class="btn btn-success" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a>
          <?php endif; ?>
          <?php if (!empty($e['email'])): ?>
            <a href="mailto:<?= e(str_replace("%40", "@", rawurlencode((string) $e["email"]))) ?>?subject=<?= e(rawurlencode('Your enquiry with Reyaan Trip Holiday')) ?>" class="btn"><?= icon('mail') ?> Email</a>
          <?php endif; ?>
        </div>
        <?php if ($crm): ?>
          <p class="small text-secondary mt-n2 mb-4">After you call or message, log it in the timeline below — new leads move to “Contacted” automatically.</p>
        <?php endif; ?>

        <dl class="row mb-0">
          <?php foreach ($details as [$label, $value]): ?>
            <dt class="col-sm-4 text-secondary fw-normal"><?= e($label) ?></dt>
            <dd class="col-sm-8"><?= e($value) ?></dd>
          <?php endforeach; ?>
          <?php if ($crm && $e['next_follow_up_at']): $late = Enq::followUpOverdue($e); ?>
            <dt class="col-sm-4 text-secondary fw-normal">Next follow-up</dt>
            <dd class="col-sm-8<?= $late ? ' text-danger fw-semibold' : '' ?>"><?= e(nice_date($e['next_follow_up_at'])) ?><?= $late ? ' — overdue' : '' ?></dd>
          <?php endif; ?>
          <?php if ($crm && $e['stage'] === 'lost' && $e['lost_reason']): ?>
            <dt class="col-sm-4 text-secondary fw-normal">Reason lost</dt>
            <dd class="col-sm-8"><?= e($e['lost_reason']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Message</h3></div>
      <div class="card-body">
        <?php if (trim((string) $e['message']) !== ''): ?>
          <p class="mb-0 rth-crm-pre"><?= e($e['message']) ?></p>
        <?php else: ?>
          <p class="text-secondary mb-0">No message was written.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($crm): ?>
      <div class="card" id="timeline">
        <div class="card-header"><h3 class="card-title"><?= icon('history') ?> Activity timeline</h3></div>
        <div class="card-body">
          <?= partial('admin/leads/_activity_form', ['action' => '/enquiries/' . $eid . '/activities']) ?>
          <?= partial('admin/leads/_timeline', ['activities' => $activities]) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <?php if ($crm): ?>
      <!-- Next steps -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Next steps</h3></div>
        <div class="card-body d-grid gap-2">
          <?php if ($canQuote): ?>
            <a href="<?= e(url('/quotations/create?' . http_build_query(array_filter(['enquiry_id' => $eid, 'customer_id' => $e['customer_id']])))) ?>" class="btn btn-primary"><?= icon('file-text') ?> Create quotation</a>
          <?php endif; ?>
          <?php if ($canBook && $e['stage'] === 'won' && $e['customer_id']): ?>
            <a href="<?= e(url('/bookings/create?' . http_build_query(['customer_id' => $e['customer_id'], 'enquiry_id' => $eid]))) ?>" class="btn btn-success"><?= icon('briefcase') ?> Create booking</a>
          <?php endif; ?>
          <?php if ($canCustomers): ?>
            <?php if (!empty($customer)): ?>
              <a href="<?= e(url('/customers/' . $customer['id'])) ?>" class="btn"><?= icon('user') ?> Customer: <?= e($customer['name']) ?></a>
            <?php else: ?>
              <form method="post" action="<?= e(url('/enquiries/' . $eid . '/customer')) ?>" class="d-grid">
                <?= csrf_field() ?>
                <button type="submit" class="btn" title="Adds them to your customer database (or links the existing customer with this phone number)"><?= icon('user') ?> Convert to customer</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <a href="<?= e(url('/tasks/create?related=enquiry:' . $eid)) ?>" class="btn"><?= icon('bell') ?> Add follow-up reminder</a>
        </div>
      </div>

      <!-- Lead details -->
      <form method="post" action="<?= e(url('/enquiries/' . $eid)) ?>" class="card mb-3" data-crm-lead-form novalidate>
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="card-header"><h3 class="card-title">Lead details</h3></div>
        <div class="card-body">
          <?php $stage = (string) old('stage', $e['stage']); ?>
          <div class="mb-3">
            <label class="form-label" for="stage">Stage</label>
            <select id="stage" name="stage" class="form-select<?= invalid('stage') ?>" data-crm-stage-field>
              <?php foreach ($stages as $key => $s): ?>
                <option value="<?= e($key) ?>"<?= $stage === $key ? ' selected' : '' ?>><?= e($s['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($err = error_for('stage')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
          </div>
          <?php
          $savedReason = (string) ($e['lost_reason'] ?? '');
          $reasonPick = (string) old('lost_reason', '');
          $reasonList = config('crm')['lost_reasons'] ?? [];
          $reasonNote = (string) old('lost_note', '');
          if ($reasonPick === '' && $savedReason !== '') {
              foreach ($reasonList as $r) {
                  if (str_starts_with($savedReason, $r)) {
                      $reasonPick = $r;
                      $reasonNote = (string) preg_replace('/^\s*—\s*/u', '', substr($savedReason, strlen($r)));
                  }
              }
          }
          ?>
          <div class="mb-3 rth-crm-lost-fields" data-crm-lost-fields>
            <label class="form-label" for="lost_reason">Reason lost <span class="text-secondary small">(only for “Lost”)</span></label>
            <select id="lost_reason" name="lost_reason" class="form-select<?= invalid('lost_reason') ?>">
              <option value="">— choose —</option>
              <?php foreach ($reasonList as $r): ?>
                <option value="<?= e($r) ?>"<?= $reasonPick === $r ? ' selected' : '' ?>><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($err = error_for('lost_reason')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            <input type="text" name="lost_note" class="form-control mt-2<?= invalid('lost_note') ?>" maxlength="150" value="<?= e($reasonNote) ?>" placeholder="Details (optional), e.g. went with a Delhi agent" aria-label="Details">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label" for="priority">Priority</label>
              <?php $prio = (string) old('priority', $e['priority']); ?>
              <select id="priority" name="priority" class="form-select<?= invalid('priority') ?>">
                <?php foreach (Enq::PRIORITIES as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $prio === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label" for="assigned_to">Assigned to</label>
              <?php $asg = (string) old('assigned_to', (string) ($e['assigned_to'] ?? '')); ?>
              <select id="assigned_to" name="assigned_to" class="form-select<?= invalid('assigned_to') ?>">
                <option value="">Nobody</option>
                <?php foreach ($assignees as $a): ?>
                  <option value="<?= e($a['id']) ?>"<?= $asg === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($err = error_for('assigned_to')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label" for="budget">Budget (₹)</label>
              <input type="number" id="budget" name="budget" min="0" step="500" inputmode="numeric" class="form-control<?= invalid('budget') ?>" value="<?= e(old('budget', (string) ($e['budget'] ?? ''))) ?>" placeholder="e.g. 60000">
              <?php if ($err = error_for('budget')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
            <div class="col-6">
              <label class="form-label" for="travel_date">Travel date</label>
              <input type="date" id="travel_date" name="travel_date" class="form-control<?= invalid('travel_date') ?>" value="<?= e(old('travel_date', (string) ($e['travel_date'] ?? ''))) ?>">
              <?php if ($err = error_for('travel_date')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="next_follow_up_at">Next follow-up</label>
            <?php $nf = (string) old('next_follow_up_at', $e['next_follow_up_at'] ? date('Y-m-d\TH:i', (int) strtotime((string) $e['next_follow_up_at'])) : ''); ?>
            <input type="datetime-local" id="next_follow_up_at" name="next_follow_up_at" class="form-control<?= invalid('next_follow_up_at') ?>" value="<?= e($nf) ?>">
            <?php if ($err = error_for('next_follow_up_at')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            <div class="form-hint">Adds a reminder to your Follow-ups list.</div>
          </div>
          <div>
            <label class="form-label" for="notes">Internal notes</label>
            <textarea id="notes" name="notes" rows="4" class="form-control<?= invalid('notes') ?>" data-counter="5000" placeholder="Key facts: dates, hotel preference, special requests…"><?= e(old('notes', $e['notes'] ?? '')) ?></textarea>
            <?php if ($err = error_for('notes')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="card-footer text-end">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save</button>
        </div>
      </form>

      <?= partial('admin/leads/_tasks', ['tasks' => $tasks, 'returnTo' => '/enquiries/' . $eid, 'addUrl' => '/tasks/create?related=enquiry:' . $eid]) ?>
    <?php else: ?>
      <form method="post" action="<?= e(url('/enquiries/' . $eid)) ?>" class="card mb-3">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="card-header"><h3 class="card-title">Follow-up</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select<?= invalid('status') ?>">
              <?php foreach (Enq::STATUSES as $key => [$label]): ?>
                <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($err = error_for('status')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            <div class="form-hint">New → Contacted → Quoted → Booked (or Closed if they did not go ahead).</div>
          </div>
          <div>
            <label class="form-label" for="notes">Internal notes</label>
            <textarea id="notes" name="notes" rows="6" class="form-control<?= invalid('notes') ?>" data-counter="5000" placeholder="e.g. Called on Monday, sending a quote for 5 nights with houseboat…"><?= e(old('notes', $e['notes'] ?? '')) ?></textarea>
            <?php if ($err = error_for('notes')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            <div class="form-hint">Only your team can see these notes.</div>
          </div>
        </div>
        <div class="card-footer text-end">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save</button>
        </div>
      </form>

      <?php if (Auth::isOwner()): ?>
        <div class="card card-sm mb-3 rth-crm-unlock">
          <div class="card-body d-flex gap-2 align-items-start">
            <span class="text-secondary"><?= icon('lock') ?></span>
            <div class="small">
              <strong>Unlock the lead pipeline</strong>
              <div class="text-secondary">Drag leads through stages, log every call, set follow-up reminders and turn enquiries into customers.</div>
              <a href="<?= e(url('/license')) ?>" class="d-inline-block mt-1">See plans &rarr;</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/enquiries/' . $eid)) ?>" data-confirm="Delete this enquiry from <?= e($e['name']) ?>? This cannot be undone.">
      <?= csrf_field() ?>
      <?= method_field('DELETE') ?>
      <button type="submit" class="btn btn-outline-danger w-100"><?= icon('trash') ?> Delete enquiry</button>
    </form>
  </div>
</div>
