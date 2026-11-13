<?php
/** @var array<string,mixed> $user */
use App\Repositories\AdminRepository;
?>
<div class="row row-cards">
  <div class="col-lg-6">
    <form method="post" action="<?= e(url('/profile')) ?>" class="card" novalidate>
      <?= csrf_field() ?>
      <?= method_field('PUT') ?>
      <div class="card-header">
        <div>
          <h3 class="card-title">Your details</h3>
          <div class="card-subtitle"><?= e(AdminRepository::ROLES[$user['role']] ?? ucfirst((string) $user['role'])) ?> account<?= $user['last_login_at'] ? ' · last signed in ' . e(nice_date($user['last_login_at'])) : '' ?></div>
        </div>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label required" for="p-name">Name</label>
          <input type="text" id="p-name" name="name" class="form-control<?= invalid('name') ?>" value="<?= e(old('name', $user['name'] ?? '')) ?>" maxlength="120" required autocomplete="name">
          <?php if ($err = error_for('name')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="form-label required" for="p-email">Email (used to sign in)</label>
          <input type="email" id="p-email" name="email" class="form-control<?= invalid('email') ?>" value="<?= e(old('email', $user['email'] ?? '')) ?>" maxlength="190" required autocomplete="username">
          <?php if ($err = error_for('email')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save details</button>
      </div>
    </form>
  </div>

  <div class="col-lg-6">
    <form method="post" action="<?= e(url('/profile/password')) ?>" class="card" novalidate>
      <?= csrf_field() ?>
      <?= method_field('PUT') ?>
      <div class="card-header">
        <div>
          <h3 class="card-title"><?= icon('lock') ?> Change password</h3>
          <div class="card-subtitle">Use at least 10 characters — a short sentence is easy to remember.</div>
        </div>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label required" for="p-current">Current password</label>
          <input type="password" id="p-current" name="current_password" class="form-control<?= invalid('current_password') ?>" required autocomplete="current-password">
          <?php if ($err = error_for('current_password')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label required" for="p-new">New password</label>
          <input type="password" id="p-new" name="password" class="form-control<?= invalid('password') ?>" minlength="10" required autocomplete="new-password">
          <?php if ($err = error_for('password')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="form-label required" for="p-new2">Repeat new password</label>
          <input type="password" id="p-new2" name="password_confirmation" class="form-control<?= invalid('password_confirmation') ?>" required autocomplete="new-password">
          <?php if ($err = error_for('password_confirmation')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">Change password</button>
      </div>
    </form>
  </div>
</div>
