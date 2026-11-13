<?php
/**
 * Admin layout (Tabler / Bootstrap 5, self-hosted).
 * Expects: $content (string), optional $title (string), $pageTitle, $pageActions (raw HTML), $pretitle
 * @var string $content
 */
use App\Core\Auth;
use App\Core\Env;
use App\Core\Gate;
use App\Core\License;
use App\Repositories\TaskRepository;

$user = Auth::user();
$title = $title ?? 'Admin';
$siteUrl = rtrim((string) Env::get('WEBSITE_URL', ''), '/');
$isOwner = Auth::isOwner();
// Sidebar from config/navigation.php: hide items the role can't use; lock badge on paid features.
$navGroups = [];
foreach (config('navigation') as $group) {
    $items = [];
    foreach ($group['items'] as $item) {
        if (!empty($item['owner']) && !$isOwner) {
            continue;
        }
        if (!empty($item['can']) && !Gate::can($item['can'])) {
            continue;
        }
        $item['locked'] = !empty($item['feature']) && !License::has($item['feature']);
        $item['lockPlan'] = $item['locked'] ? License::requiredPlan($item['feature']) : null;
        $item['count'] = 0;
        if (($item['badge'] ?? '') === 'tasks_due' && !$item['locked'] && class_exists(TaskRepository::class)) {
            $item['count'] = TaskRepository::dueCount();
        }
        $items[] = $item;
    }
    if ($items !== []) {
        $navGroups[] = ['label' => $group['label'], 'items' => $items];
    }
}
$plan = License::plan();
$licenseState = License::state();
$daysLeft = License::daysLeft();
// Per-module assets: every file in assets/admin/css/*.css and assets/admin/js/*.js is loaded automatically.
$moduleCss = glob(PUBLIC_PATH . '/assets/admin/css/*.css') ?: [];
$moduleJs = glob(PUBLIC_PATH . '/assets/admin/js/*.js') ?: [];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($title) ?> · RTH Admin</title>
  <link rel="icon" href="<?= e(asset('/assets/admin/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/vendor/tabler/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/assets/admin/app.css')) ?>">
  <?php foreach ($moduleCss as $f): ?><link rel="stylesheet" href="<?= e(asset('/assets/admin/css/' . basename($f))) ?>"><?php endforeach; ?>
</head>
<body data-base="<?= e(rtrim((string) Env::get('APP_BASE_PATH', ''), '/')) ?>">
<div class="page">
  <aside class="navbar navbar-vertical navbar-expand-lg rth-sidebar" data-bs-theme="dark">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <a href="<?= e(url('/')) ?>" class="navbar-brand rth-brand">
        <img src="<?= e(asset('/assets/admin/logo.jpeg')) ?>" alt="" width="40" height="32" class="rth-brand-logo">
        <span>
          <span class="rth-brand-name">Reyaan Trip</span>
          <span class="rth-brand-sub">Admin panel</span>
        </span>
      </a>
      <div class="collapse navbar-collapse" id="sidebar-menu">
        <ul class="navbar-nav pt-lg-3">
          <?php foreach ($navGroups as $group): ?>
            <?php if ($group['label']): ?><li class="rth-nav-group"><?= e($group['label']) ?></li><?php endif; ?>
            <?php foreach ($group['items'] as $item): $active = nav_active($item['href']); ?>
              <li class="nav-item<?= $active ? ' active' : '' ?><?= $item['locked'] ? ' rth-nav-locked' : '' ?>">
                <a class="nav-link" href="<?= e(url($item['href'])) ?>"<?= $active ? ' aria-current="page"' : '' ?><?= $item['locked'] ? ' title="Available on the ' . e(License::planLabel($item['lockPlan'])) . ' plan"' : '' ?>>
                  <span class="nav-link-icon"><?= icon($item['icon']) ?></span>
                  <span class="nav-link-title"><?= e($item['label']) ?></span>
                  <?php if (!empty($item['count'])): ?><span class="rth-nav-count" title="<?= (int) $item['count'] ?> follow-up<?= $item['count'] === 1 ? '' : 's' ?> due"><?= (int) $item['count'] ?></span><?php endif; ?>
                  <?php if ($item['locked']): ?><span class="rth-nav-lock rth-plan-<?= e($item['lockPlan']) ?>" aria-label="<?= e(License::planLabel($item['lockPlan'])) ?> plan"><?= icon('lock', 'icon') ?><?= $item['lockPlan'] === 'premium' ? 'PRE' : 'PRO' ?></span><?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
        <div class="rth-sidebar-foot">
          <?php if ($siteUrl !== ''): ?>
            <a class="btn btn-outline-light btn-sm w-100 mb-2" href="<?= e($siteUrl) ?>" target="_blank" rel="noopener"><?= icon('external') ?> View website</a>
          <?php endif; ?>
          <form method="post" action="<?= e(url('/site/refresh')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm w-100 rth-btn-ghost" type="submit" title="Push the latest changes to the live website now"><?= icon('refresh') ?> Update website now</button>
          </form>
        </div>
      </div>
    </div>
  </aside>

  <div class="page-wrapper">
    <header class="navbar navbar-expand-md d-print-none rth-topbar">
      <div class="container-xl">
        <div class="ms-auto d-flex align-items-center gap-3">
          <a href="<?= e(url($isOwner ? '/license' : '/')) ?>" class="badge rth-plan-badge rth-plan-<?= e($plan) ?> text-decoration-none" title="Current plan"><?= icon('crown') ?> <?= e(License::planLabel($plan)) ?></a>
          <a class="d-flex align-items-center gap-2 text-reset text-decoration-none" href="<?= e(url('/profile')) ?>">
            <span class="avatar avatar-sm rth-avatar"><?= e(mb_strtoupper(mb_substr((string) ($user['name'] ?? 'A'), 0, 1))) ?></span>
            <span class="d-none d-sm-block lh-sm">
              <span class="d-block fw-semibold"><?= e($user['name'] ?? '') ?></span>
              <span class="d-block small text-secondary"><?= e(Gate::roleLabel((string) ($user['role'] ?? ''))) ?></span>
            </span>
          </a>
          <form method="post" action="<?= e(url('/logout')) ?>" class="m-0">
            <?= csrf_field() ?>
            <button class="btn btn-icon btn-ghost-secondary" type="submit" title="Sign out" aria-label="Sign out"><?= icon('logout') ?></button>
          </form>
        </div>
      </div>
    </header>

    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row g-2 align-items-center">
          <div class="col-12 col-md">
            <?php if (!empty($pretitle)): ?><div class="page-pretitle"><?= e($pretitle) ?></div><?php endif; ?>
            <h1 class="page-title"><?= e($pageTitle ?? $title) ?></h1>
          </div>
          <?php if (!empty($pageActions)): ?>
            <div class="col-12 col-md-auto ms-md-auto d-flex gap-2 flex-wrap"><?= $pageActions /* trusted HTML built in views */ ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <?php if ($isOwner && $licenseState['error']): ?>
          <div class="alert alert-warning d-flex align-items-center gap-2"><?= icon('alert') ?><div><?= e($licenseState['error']) ?> <a href="<?= e(url('/license')) ?>">Manage plan</a></div></div>
        <?php elseif ($isOwner && $daysLeft !== null && $daysLeft <= 15): ?>
          <div class="alert alert-info d-flex align-items-center gap-2"><?= icon('clock') ?><div>Your <?= e(License::planLabel()) ?> license renews in <?= (int) $daysLeft ?> day<?= $daysLeft === 1 ? '' : 's' ?>. <a href="<?= e(url('/license')) ?>">Renew now</a></div></div>
        <?php endif; ?>
        <?= partial('partials/flash') ?>
        <?= $content ?>
      </div>
    </div>

    <footer class="footer footer-transparent d-print-none">
      <div class="container-xl small text-secondary">Reyaan Trip Holiday · Admin panel</div>
    </footer>
  </div>
</div>
<script src="<?= e(asset('/assets/vendor/tabler/tabler.min.js')) ?>"></script>
<script src="<?= e(asset('/assets/admin/app.js')) ?>"></script>
<?php foreach ($moduleJs as $f): ?><script src="<?= e(asset('/assets/admin/js/' . basename($f))) ?>"></script><?php endforeach; ?>
</body>
</html>
