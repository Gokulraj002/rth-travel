<?php
/** @var array<string,mixed> $enquiry */
use App\Repositories\EnquiryRepository as Enq;

$e = $enquiry;
$waText = 'Hi ' . $e['name'] . ', this is Reyaan Trip Holiday regarding your enquiry'
    . (Enq::tripLabel($e) !== '—' ? ' about ' . Enq::tripLabel($e) : '') . '. ';
$details = [
    ['Phone', $e['phone']],
    ['Email', $e['email'] ?: '—'],
    ['Destination', $e['destination'] ?: '—'],
    ['Package', $e['package'] ?: '—'],
    ['Travel month', $e['travel_month'] ?: '—'],
    ['Travellers', Enq::people($e)],
    ['Hotel category', $e['hotel_category'] ?: '—'],
    ['Came from', Enq::sourceLabel($e['source'])],
    ['Received', nice_date($e['created_at'])],
    ['Last updated', nice_date($e['updated_at'])],
];
$status = (string) old('status', $e['status']);
?>
<div class="mb-3">
  <a href="<?= e(url('/enquiries')) ?>" class="text-secondary">&larr; Back to all enquiries</a>
</div>

<div class="row row-cards">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><?= e($e['name']) ?></h3>
        <div class="card-actions">
          <span class="badge <?= e(Enq::statusClass((string) $e['status'])) ?>"><?= e(Enq::statusLabel((string) $e['status'])) ?></span>
        </div>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-4">
          <a href="<?= e(Enq::telHref($e['phone'])) ?>" class="btn btn-primary"><?= icon('phone') ?> Call <?= e($e['phone']) ?></a>
          <a href="<?= e(Enq::whatsappUrl($e['phone'], $waText)) ?>" class="btn btn-success" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a>
          <?php if (!empty($e['email'])): ?>
            <a href="mailto:<?= e(str_replace("%40", "@", rawurlencode((string) $e["email"]))) ?>?subject=<?= e(rawurlencode('Your enquiry with Reyaan Trip Holiday')) ?>" class="btn"><?= icon('mail') ?> Email</a>
          <?php endif; ?>
        </div>

        <dl class="row mb-0">
          <?php foreach ($details as [$label, $value]): ?>
            <dt class="col-sm-4 text-secondary fw-normal"><?= e($label) ?></dt>
            <dd class="col-sm-8"><?= e($value) ?></dd>
          <?php endforeach; ?>
        </dl>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Message</h3></div>
      <div class="card-body">
        <?php if (trim((string) $e['message']) !== ''): ?>
          <p class="mb-0" style="white-space: pre-line"><?= e($e['message']) ?></p>
        <?php else: ?>
          <p class="text-secondary mb-0">No message was written.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <form method="post" action="<?= e(url('/enquiries/' . $e['id'])) ?>" class="card mb-3">
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

    <form method="post" action="<?= e(url('/enquiries/' . $e['id'])) ?>" data-confirm="Delete this enquiry from <?= e($e['name']) ?>? This cannot be undone.">
      <?= csrf_field() ?>
      <?= method_field('DELETE') ?>
      <button type="submit" class="btn btn-outline-danger w-100"><?= icon('trash') ?> Delete enquiry</button>
    </form>
  </div>
</div>
