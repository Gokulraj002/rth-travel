<?php
/**
 * "Upgrade to unlock" screen — rendered by the router for routes with 'feature:x' when the plan lacks x.
 * @var string $lockedFeature
 */
use App\Core\License;

$f = License::feature($lockedFeature) ?? ['plan' => 'pro', 'label' => ucfirst($lockedFeature), 'desc' => ''];
$plan = $f['plan'];
$planInfo = config('plans')['plans'][$plan] ?? ['label' => ucfirst($plan), 'tagline' => ''];
$others = array_filter(config('plans')['features'], static fn ($x) => $x['plan'] === $plan);
?>
<div class="rth-locked card card-lg">
  <div class="card-body text-center py-5">
    <div class="rth-locked-icon mb-3"><?= icon('lock', 'icon icon-lg') ?></div>
    <span class="badge rth-plan-badge rth-plan-<?= e($plan) ?> mb-2"><?= e($planInfo['label']) ?> feature</span>
    <h2 class="h1 mb-2"><?= e($f['label']) ?></h2>
    <p class="text-secondary mx-auto mb-4" style="max-width: 36rem"><?= e($f['desc']) ?></p>
    <div class="d-flex justify-content-center mb-4"><?= partial('partials/upgrade-contact', ['planWanted' => $plan]) ?></div>
    <div class="text-start mx-auto" style="max-width: 40rem">
      <div class="text-uppercase small fw-bold text-secondary mb-2">Everything in <?= e($planInfo['label']) ?> — <?= e($planInfo['tagline']) ?></div>
      <div class="row g-2">
        <?php foreach ($others as $o): ?>
          <div class="col-sm-6"><div class="d-flex gap-2 align-items-start"><span class="text-success"><?= icon('check') ?></span><span><strong><?= e($o['label']) ?></strong></span></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
