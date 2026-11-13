<?php
/**
 * Manual enquiry (phone call, WhatsApp, walk-in…).
 * @var list<array{id:int,name:string}> $assignees
 * @var list<array{title:string}> $packages
 * @var list<array{name:string}> $destinations
 */
use App\Core\Auth;
use App\Repositories\EnquiryRepository as Enq;

$val = static fn (string $k, mixed $default = ''): string => (string) old($k, $default);
$err = static fn (string $k): string => ($m = error_for($k)) ? '<div class="invalid-feedback">' . e($m) . '</div>' : '';
$sources = config('crm')['sources'] ?? [];
$source = $val('source', 'phone');
$stage = $val('stage', 'new');
$priority = $val('priority', 'normal');
$assigned = $val('assigned_to', (string) Auth::id());
?>
<form method="post" action="<?= e(url('/enquiries')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Who is it?</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required" for="name">Name</label>
              <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" placeholder="e.g. Rohit Malhotra" required autofocus>
              <?= $err('name') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label required" for="phone">Phone / WhatsApp</label>
              <input type="tel" class="form-control<?= invalid('phone') ?>" id="phone" name="phone" value="<?= e($val('phone')) ?>" maxlength="30" placeholder="e.g. 98765 43210" inputmode="tel" required>
              <?= $err('phone') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" value="<?= e($val('email')) ?>" maxlength="190" placeholder="optional">
              <?= $err('email') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label required" for="source">How did they reach you?</label>
              <select id="source" name="source" class="form-select<?= invalid('source') ?>">
                <?php foreach ($sources as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $source === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= $err('source') ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title">Trip they want</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="destination">Destination</label>
              <input type="text" class="form-control<?= invalid('destination') ?>" id="destination" name="destination" list="crm-destinations" value="<?= e($val('destination')) ?>" maxlength="120" placeholder="e.g. Kashmir, Gulmarg">
              <datalist id="crm-destinations"><?php foreach ($destinations as $d): ?><option value="<?= e($d['name']) ?>"></option><?php endforeach; ?></datalist>
              <?= $err('destination') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="package">Package</label>
              <input type="text" class="form-control<?= invalid('package') ?>" id="package" name="package" list="crm-packages" value="<?= e($val('package')) ?>" maxlength="160" placeholder="optional">
              <datalist id="crm-packages"><?php foreach ($packages as $p): ?><option value="<?= e($p['title']) ?>"></option><?php endforeach; ?></datalist>
              <?= $err('package') ?>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label" for="travel_month">Travel month</label>
              <input type="text" class="form-control<?= invalid('travel_month') ?>" id="travel_month" name="travel_month" value="<?= e($val('travel_month')) ?>" maxlength="40" placeholder="e.g. Dec 2026">
              <?= $err('travel_month') ?>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label" for="travel_date">Exact date</label>
              <input type="date" class="form-control<?= invalid('travel_date') ?>" id="travel_date" name="travel_date" value="<?= e($val('travel_date')) ?>">
              <?= $err('travel_date') ?>
            </div>
            <div class="col-4 col-md-2">
              <label class="form-label required" for="adults">Adults</label>
              <input type="number" class="form-control<?= invalid('adults') ?>" id="adults" name="adults" min="1" max="30" value="<?= e($val('adults', '2')) ?>">
              <?= $err('adults') ?>
            </div>
            <div class="col-4 col-md-2">
              <label class="form-label" for="children">Children</label>
              <input type="number" class="form-control<?= invalid('children') ?>" id="children" name="children" min="0" max="20" value="<?= e($val('children', '0')) ?>">
              <?= $err('children') ?>
            </div>
            <div class="col-4 col-md-2">
              <label class="form-label" for="hotel_category">Hotel</label>
              <input type="text" class="form-control<?= invalid('hotel_category') ?>" id="hotel_category" name="hotel_category" list="crm-hotels" value="<?= e($val('hotel_category')) ?>" maxlength="40" placeholder="3 star">
              <datalist id="crm-hotels"><option value="Budget"></option><option value="3 star"></option><option value="4 star"></option><option value="5 star"></option><option value="Houseboat"></option></datalist>
              <?= $err('hotel_category') ?>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="budget">Budget (₹, total)</label>
              <input type="number" class="form-control<?= invalid('budget') ?>" id="budget" name="budget" min="0" step="500" inputmode="numeric" value="<?= e($val('budget')) ?>" placeholder="e.g. 60000">
              <?= $err('budget') ?>
            </div>
            <div class="col-12">
              <label class="form-label" for="message">Requirements</label>
              <textarea class="form-control<?= invalid('message') ?>" id="message" name="message" rows="4" maxlength="2000" data-counter="2000" placeholder="What did they ask for? e.g. honeymoon, houseboat 1 night, Gulmarg gondola, vegetarian food"><?= e($val('message')) ?></textarea>
              <?= $err('message') ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Pipeline</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="stage">Stage</label>
            <select id="stage" name="stage" class="form-select<?= invalid('stage') ?>">
              <?php foreach (Enq::stages() as $k => $s): if ($k === 'lost') { continue; } ?>
                <option value="<?= e($k) ?>"<?= $stage === $k ? ' selected' : '' ?>><?= e($s['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('stage') ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="priority">Priority</label>
            <select id="priority" name="priority" class="form-select<?= invalid('priority') ?>">
              <?php foreach (Enq::PRIORITIES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $priority === $k ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="assigned_to">Assigned to</label>
            <select id="assigned_to" name="assigned_to" class="form-select<?= invalid('assigned_to') ?>">
              <option value="">Nobody</option>
              <?php foreach ($assignees as $a): ?>
                <option value="<?= e($a['id']) ?>"<?= $assigned === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('assigned_to') ?>
          </div>
          <div>
            <label class="form-label" for="next_follow_up_at">Next follow-up</label>
            <input type="datetime-local" class="form-control<?= invalid('next_follow_up_at') ?>" id="next_follow_up_at" name="next_follow_up_at" value="<?= e($val('next_follow_up_at')) ?>">
            <?= $err('next_follow_up_at') ?>
            <div class="form-hint">Optional — adds a reminder to Follow-ups.</div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save enquiry</button>
          <a href="<?= e(url('/enquiries')) ?>" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
