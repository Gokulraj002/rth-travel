<?php /** Centered card layout for login / setup. @var string $content */ ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title ?? 'Sign in') ?> · RTH Admin</title>
  <link rel="icon" href="<?= e(asset('/assets/admin/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/vendor/tabler/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/admin/app.css')) ?>">
</head>
<body class="d-flex flex-column rth-auth">
<div class="page page-center">
  <div class="container container-tight py-4">
    <div class="text-center mb-4">
      <img src="<?= e(asset('/assets/admin/logo.jpeg')) ?>" alt="Reyaan Trip Holiday" width="120" height="95" class="rth-auth-logo">
    </div>
    <?= partial('partials/flash') ?>
    <?= $content ?>
    <p class="text-center text-secondary small mt-3">Reyaan Trip Holiday · Admin panel</p>
  </div>
</div>
</body>
</html>
