<?php
/**
 * Split layout for login / setup: brand panel (left, desktop only) + form panel (right).
 * Expects: $content (string), optional $title.
 * @var string $content
 */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0b1f3a">
  <title><?= e($title ?? 'Sign in') ?> · RTH Admin</title>
  <link rel="icon" href="<?= e(asset('/assets/admin/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/vendor/tabler/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/admin/app.css')) ?>">
</head>
<body class="rth-auth">
<div class="rth-auth-shell">
  <aside class="rth-auth-brand" aria-label="About this admin panel">
    <div class="rth-auth-mark">
      <img src="<?= e(asset('/assets/admin/logo.jpeg')) ?>" alt="" width="64" height="51">
      <span>
        <span class="rth-auth-mark-name">Reyaan Trip Holiday</span>
        <span class="rth-auth-mark-sub">Travel admin</span>
      </span>
    </div>

    <div>
      <div class="rth-auth-eyebrow"><?= icon('sparkles') ?> Your travel business, in one place</div>
      <h2 class="rth-auth-headline">Plan trips, close leads and get paid — <em>without the paperwork.</em></h2>
      <p class="rth-auth-lead">Manage your website, enquiries, quotations, bookings and payments from one calm, secure dashboard.</p>
      <ul class="rth-auth-points">
        <li><span class="rth-auth-point-icon"><?= icon('inbox') ?></span><span><strong>Every enquiry, instantly</strong>Website and WhatsApp leads land in one inbox.</span></li>
        <li><span class="rth-auth-point-icon"><?= icon('file-text') ?></span><span><strong>Quotes in minutes</strong>Day-wise itineraries your guests will love.</span></li>
        <li><span class="rth-auth-point-icon"><?= icon('wallet') ?></span><span><strong>Know what is due</strong>Payments, balances and departures at a glance.</span></li>
      </ul>
    </div>

    <div class="rth-auth-foot">&copy; <?= e(date('Y')) ?> Reyaan Trip Holiday · Admin panel</div>
  </aside>

  <main class="rth-auth-main">
    <div class="rth-auth-panel">
      <div class="rth-auth-mobile-mark">
        <img src="<?= e(asset('/assets/admin/logo.jpeg')) ?>" alt="Reyaan Trip Holiday" width="84" height="67">
        <span>Travel admin</span>
      </div>
      <?= partial('partials/flash') ?>
      <?= $content ?>
      <p class="rth-auth-note"><?= icon('shield') ?> Secure, private admin area</p>
    </div>
  </main>
</div>
</body>
</html>
