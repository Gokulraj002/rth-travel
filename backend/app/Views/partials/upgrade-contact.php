<?php
/**
 * "Contact to upgrade" buttons (vendor details from .env: LICENSE_VENDOR_NAME / _WHATSAPP / _EMAIL).
 * @var string|null $planWanted
 */
use App\Core\Env;
use App\Core\License;

$vName = (string) Env::get('LICENSE_VENDOR_NAME', '');
$vWa = (string) Env::get('LICENSE_VENDOR_WHATSAPP', '');
$vMail = (string) Env::get('LICENSE_VENDOR_EMAIL', '');
$want = License::planLabel($planWanted ?? 'pro');
$msg = 'Hi, I would like to upgrade my travel admin panel (' . License::currentHost() . ') to the ' . $want . ' plan.';
?>
<div class="d-flex flex-wrap gap-2">
  <?php if ($vWa !== ''): ?>
    <a class="btn btn-success" href="<?= e(wa_link($vWa, $msg)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> Upgrade on WhatsApp</a>
  <?php endif; ?>
  <?php if ($vMail !== ''): ?>
    <a class="btn btn-outline-primary" href="mailto:<?= e($vMail) ?>?subject=<?= e(rawurlencode('Upgrade to ' . $want)) ?>&amp;body=<?= e(rawurlencode($msg)) ?>"><?= icon('mail') ?> Email us</a>
  <?php endif; ?>
  <?php if ($vWa === '' && $vMail === ''): ?>
    <span class="text-secondary">Contact <?= e($vName !== '' ? $vName : 'your website developer') ?> to upgrade.</span>
  <?php endif; ?>
  <?php if (App\Core\Auth::isOwner()): ?>
    <a class="btn btn-ghost-primary" href="<?= e(url('/license')) ?>"><?= icon('key') ?> I have a license key</a>
  <?php endif; ?>
</div>
