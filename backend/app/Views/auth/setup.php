<?php /** @var string $token */ ?>
<h1 class="rth-auth-title">Create the owner account</h1>
<p class="rth-auth-sub">This page works only once — while no admin exists yet.</p>
<div class="rth-auth-card">
  <form method="post" action="<?= e(url('/setup')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
      <label class="form-label required" for="name">Your name</label>
      <input class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e(old('name')) ?>" required autocomplete="name">
      <?php if ($m = error_for('name')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
    </div>
    <div class="mb-3">
      <label class="form-label required" for="email">Email</label>
      <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username">
      <?php if ($m = error_for('email')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
    </div>
    <div class="mb-3">
      <label class="form-label required" for="password">Password <span class="text-secondary fw-normal">(min. 10 characters)</span></label>
      <input type="password" class="form-control<?= invalid('password') ?>" id="password" name="password" required autocomplete="new-password" minlength="10">
      <?php if ($m = error_for('password')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
    </div>
    <div class="mb-4">
      <label class="form-label required" for="password_confirmation">Confirm password</label>
      <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary w-100">Create account <?= icon('arrow-right', 'icon ms-1') ?></button>
  </form>
</div>
