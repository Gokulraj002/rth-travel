<?php
/**
 * Printable document layout (quotations, invoices, receipts) — A4, also used for public share links.
 * Expects: $content, $title. Optional: $backUrl (admin back link; omit on public pages), $toolbar (extra raw HTML buttons),
 * $public (bool — hides admin-only chrome).
 * Use <div class="rth-doc"> … </div> inside the view. Toolbar is hidden when printing.
 */
use App\Core\Env;

$title = $title ?? 'Document';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <?php if (empty($public)): ?><meta name="csrf-token" content="<?= e(csrf_token()) ?>"><?php endif; ?>
  <title><?= e($title) ?></title>
  <link rel="icon" href="<?= e(asset('/assets/admin/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/vendor/tabler/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/admin/print.css')) ?>">
  <?php foreach (glob(PUBLIC_PATH . '/assets/admin/css/doc-*.css') ?: [] as $f): ?><link rel="stylesheet" href="<?= e(asset('/assets/admin/css/' . basename($f))) ?>"><?php endforeach; ?>
</head>
<body class="rth-print" data-base="<?= e(rtrim((string) Env::get('APP_BASE_PATH', ''), '/')) ?>">
  <div class="rth-print-toolbar d-print-none">
    <div class="rth-print-toolbar-inner">
      <?php if (!empty($backUrl)): ?><a class="btn btn-ghost-secondary" href="<?= e(url($backUrl)) ?>"><?= icon('arrow-left') ?> Back</a><?php endif; ?>
      <div class="ms-auto d-flex gap-2 flex-wrap">
        <?= $toolbar ?? '' /* trusted HTML built in views */ ?>
        <button class="btn btn-primary" type="button" data-print><?= icon('printer') ?> Print / Save PDF</button>
      </div>
    </div>
  </div>
  <main class="rth-print-page"><?= $content ?></main>
  <script src="<?= e(asset('/assets/admin/print.js')) ?>"></script>
</body>
</html>
