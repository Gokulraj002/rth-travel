<?php
/**
 * @var array{plan:string,source:string,licensee:?string,domain:?string,expires:?string,issued:?string,error:?string} $state
 * @var string $host
 * @var int|null $daysLeft
 */
use App\Core\License;

$plans = config('plans');
$current = $state['plan'];
$rank = $plans['plans'][$current]['rank'];
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card rth-plan-card rth-plan-current-<?= e($current) ?>">
      <div class="card-body">
        <div class="text-uppercase small fw-bold text-secondary">Your plan</div>
        <div class="d-flex align-items-center gap-2 my-2">
          <span class="rth-plan-crown"><?= icon('crown') ?></span>
          <span class="h1 m-0"><?= e(License::planLabel($current)) ?></span>
        </div>
        <div class="text-secondary mb-3"><?= e($plans['plans'][$current]['tagline']) ?></div>
        <dl class="row mb-0 small">
          <?php if ($state['licensee']): ?><dt class="col-5">Licensed to</dt><dd class="col-7"><?= e($state['licensee']) ?></dd><?php endif; ?>
          <dt class="col-5">This admin runs on</dt><dd class="col-7"><code><?= e($host) ?></code></dd>
          <?php if ($state['domain']): ?><dt class="col-5">License domain</dt><dd class="col-7"><code><?= e($state['domain']) ?></code></dd><?php endif; ?>
          <?php if ($state['source'] === 'key'): ?>
            <dt class="col-5">Valid until</dt>
            <dd class="col-7"><?= $state['expires'] ? e(nice_day($state['expires'])) . ($daysLeft !== null && $daysLeft <= 30 ? ' <span class="badge bg-warning-lt">' . (int) $daysLeft . ' days left</span>' : '') : 'Lifetime' ?></dd>
          <?php endif; ?>
          <?php if ($state['source'] === 'dev'): ?><dt class="col-5">Mode</dt><dd class="col-7"><span class="badge bg-azure-lt">Local test mode (LICENSE_DEV_PLAN)</span></dd><?php endif; ?>
        </dl>
        <?php if ($state['error']): ?>
          <div class="alert alert-warning mt-3 mb-0"><?= icon('alert') ?> <?= e($state['error']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header"><h3 class="card-title"><?= icon('key') ?> Activate a license key</h3></div>
      <div class="card-body">
        <p class="text-secondary small">After payment you receive a key for <code><?= e($host) ?></code>. Paste it below — the new features unlock instantly. Your data is never deleted when changing plans.</p>
        <form method="post" action="<?= e(url('/license')) ?>">
          <?= csrf_field() ?><?= method_field('PUT') ?>
          <textarea name="license_key" rows="4" class="form-control font-monospace small<?= invalid('license_key') ?>" placeholder="RTH1.xxxxx.xxxxx" spellcheck="false" autocomplete="off"><?= e(old('license_key')) ?></textarea>
          <?php if (error_for('license_key')): ?><div class="invalid-feedback"><?= e(error_for('license_key')) ?></div><?php endif; ?>
          <button class="btn btn-primary mt-3" type="submit"><?= icon('check') ?> Activate</button>
        </form>
        <?php if ($state['source'] === 'key' || $state['source'] === 'invalid'): ?>
          <form method="post" action="<?= e(url('/license')) ?>" class="mt-2" data-confirm="Remove the license key? Paid features will be locked again (your data is kept).">
            <?= csrf_field() ?><?= method_field('DELETE') ?>
            <button class="btn btn-link text-danger p-0" type="submit">Remove license key</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Compare plans</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table rth-plan-table">
          <thead>
            <tr>
              <th>Feature</th>
              <?php foreach ($plans['plans'] as $key => $p): ?>
                <th class="text-center<?= $key === $current ? ' rth-plan-col-current' : '' ?>"><?= e($p['label']) ?><?= $key === $current ? '<div class="small text-primary">current</div>' : '' ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <tr><td><strong>Website manager</strong><div class="small text-secondary"><?= e(implode(' · ', $plans['basic_features'])) ?></div></td>
              <?php foreach ($plans['plans'] as $key => $p): ?><td class="text-center text-success"><?= icon('check') ?></td><?php endforeach; ?></tr>
            <?php foreach ($plans['features'] as $fk => $f): ?>
              <tr>
                <td><strong><?= e($f['label']) ?></strong><div class="small text-secondary"><?= e($f['desc']) ?></div></td>
                <?php foreach ($plans['plans'] as $key => $p): ?>
                  <td class="text-center"><?= $p['rank'] >= $plans['plans'][$f['plan']]['rank'] ? '<span class="text-success">' . icon('check') . '</span>' : '<span class="text-muted">' . icon('lock') . '</span>' ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($rank < 3): ?>
        <div class="card-footer"><?= partial('partials/upgrade-contact', ['planWanted' => $rank < 2 ? 'pro' : 'premium']) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
