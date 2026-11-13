<?php
/**
 * @var array<string,mixed>|null $tpl
 * @var string $channel
 * @var array<string,string> $placeholders key => description
 * @var array<string,string> $samples key => example value
 */
use App\Repositories\TemplateRepository as Tpl;

$editing = $tpl !== null;
$action = $editing ? url('/templates/' . $tpl['id']) : url('/templates');
$ch = (string) old('channel', $tpl['channel'] ?? $channel);
$hasOld = old('name', null) !== null;
$active = $hasOld ? old('is_active', '0') === '1' : (int) ($tpl['is_active'] ?? 1) === 1;
$originalName = (string) ($tpl['name'] ?? '');
$originalChannel = (string) ($tpl['channel'] ?? '');
$isSystem = $editing && Tpl::isSystem($originalName, $originalChannel);
$systemNames = array_map(static fn (array $s): string => $s[0], Tpl::SYSTEM); // name => channel
?>
<div class="mb-3">
  <a href="<?= e(url('/templates')) ?>" class="text-secondary">&larr; Back to templates</a>
</div>

<form method="post" action="<?= e($action) ?>" class="rth-tools-tpl" novalidate
      data-tpl-editor
      data-tpl-samples="<?= e(json_encode($samples, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
      data-tpl-system="<?= e(json_encode($systemNames, JSON_UNESCAPED_UNICODE)) ?>"
      data-tpl-original-name="<?= e($originalName) ?>"
      data-tpl-original-channel="<?= e($originalChannel) ?>">
  <?= csrf_field() ?>
  <?php if ($editing): ?><?= method_field('PUT') ?><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-body">
          <div class="row">
            <div class="col-sm-4 mb-3">
              <label class="form-label required" for="t-channel">Send by</label>
              <select id="t-channel" name="channel" class="form-select<?= invalid('channel') ?>" data-tpl-channel>
                <?php foreach (Tpl::CHANNELS as $key => $label): ?>
                  <option value="<?= e($key) ?>"<?= $ch === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($err = error_for('channel')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
            <div class="col-sm-8 mb-3">
              <label class="form-label required" for="t-name">Template name</label>
              <input type="text" id="t-name" name="name" class="form-control<?= invalid('name') ?>" value="<?= e(old('name', $originalName)) ?>" maxlength="120" required data-tpl-name placeholder="e.g. Houseboat booking details">
              <?php if ($err = error_for('name')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
              <?php if ($isSystem): ?>
                <div class="form-hint">This name is used for <?= e(Tpl::SYSTEM[$originalName][1]) ?>. Keep it exactly as it is.</div>
              <?php endif; ?>
              <div class="alert alert-warning mt-2 mb-0 py-2 small d-none" data-tpl-rename-warning role="status">
                <?= icon('alert') ?> “<?= e($originalName) ?>” is used by other parts of the panel. If you rename it (or change how it is sent),
                those buttons will use their built-in default text instead. You can still save.
              </div>
            </div>
          </div>

          <div class="mb-3<?= $ch === 'email' ? '' : ' d-none' ?>" data-tpl-subject-group>
            <label class="form-label required" for="t-subject">Email subject</label>
            <input type="text" id="t-subject" name="subject" class="form-control<?= invalid('subject') ?>" value="<?= e(old('subject', $tpl['subject'] ?? '')) ?>" maxlength="200" data-tpl-field="subject">
            <?php if ($err = error_for('subject')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
          </div>

          <div class="mb-2">
            <label class="form-label required" for="t-body">Message</label>
            <textarea id="t-body" name="body" rows="9" class="form-control rth-tpl-body<?= invalid('body') ?>" maxlength="5000" required data-counter="5000" data-tpl-field="body"><?= e(old('body', $tpl['body'] ?? '')) ?></textarea>
            <?php if ($err = error_for('body')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
          </div>

          <div class="mb-3">
            <div class="small text-secondary mb-1">Click to insert at the cursor:</div>
            <div class="rth-tpl-chips">
              <?php foreach ($placeholders as $key => $label): ?>
                <button type="button" class="rth-tpl-chip" data-tpl-insert="{{<?= e($key) ?>}}" title="<?= e($label) ?>">
                  <span class="rth-tpl-chip-label"><?= e($label) ?></span>
                  <code>{{<?= e($key) ?>}}</code>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <label class="form-check form-switch m-0">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"<?= $active ? ' checked' : '' ?>>
            <span class="form-check-label">Switched on — available for sending</span>
          </label>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
          <a href="<?= e(url('/templates')) ?>" class="btn btn-link">Cancel</a>
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $editing ? 'Save changes' : 'Save template' ?></button>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card rth-tpl-preview-card">
        <div class="card-header"><h3 class="card-title"><?= icon('eye') ?> Preview with example details</h3></div>
        <div class="card-body">
          <div class="rth-tpl-phone" data-tpl-preview-wrap data-channel="<?= e($ch) ?>">
            <div class="rth-tpl-subject d-none" data-tpl-preview-subject></div>
            <div class="rth-tpl-bubble" data-tpl-preview>
              <span class="text-secondary">Start typing your message…</span>
            </div>
          </div>
          <p class="small text-secondary mt-3 mb-0">Example values are used here (customer “<?= e($samples['customer_name']) ?>”). Real messages use the customer’s own details.</p>
        </div>
      </div>
    </div>
  </div>
</form>
