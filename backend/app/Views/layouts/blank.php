<?php /** Minimal layout for error pages. @var string $content */ ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e((string) ($status ?? 'Error')) ?> · RTH Admin</title>
  <link rel="stylesheet" href="<?= e(asset('/assets/vendor/tabler/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/admin/app.css')) ?>">
</head>
<body class="d-flex flex-column rth-auth">
<div class="page page-center"><div class="container container-tight py-4"><?= $content ?></div></div>
</body>
</html>
