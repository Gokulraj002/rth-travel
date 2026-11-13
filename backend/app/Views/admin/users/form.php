<?php
/**
 * @var array<string,mixed>|null $user
 * @var bool $isSelf
 */
use App\Repositories\AdminRepository;

$editing = $user !== null;
$action = $editing ? url('/users/' . $user['id']) : url('/users');
$role = (string) old('role', $user['role'] ?? 'editor');
// Checkbox: after a failed submit, old() holds the posted value ('1') or nothing (unchecked).
$hasOld = old('name', null) !== null;
$active = $hasOld ? old('is_active', '0') === '1' : (int) ($user['is_active'] ?? 1) === 1;
?>
<div class="mb-3">
  <a href="<?= e(url('/users')) ?>" class="text-secondary">&larr; Back to admin users</a>
</div>

<form method="post" action="<?= e($action) ?>" class="card" style="max-width: 44rem" novalidate autocomplete="off">
  <?= csrf_field() ?>
  <?php if ($editing): ?><?= method_field('PUT') ?><?php endif; ?>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label required" for="u-name">Name</label>
        <input type="text" id="u-name" name="name" class="form-control<?= invalid('name') ?>" value="<?= e(old('name', $user['name'] ?? '')) ?>" maxlength="120" required>
        <?php if ($err = error_for('name')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label required" for="u-email">Email (used to sign in)</label>
        <input type="email" id="u-email" name="email" class="form-control<?= invalid('email') ?>" value="<?= e(old('email', $user['email'] ?? '')) ?>" maxlength="190" required autocomplete="off">
        <?php if ($err = error_for('email')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label required" for="u-role">Role</label>
      <select id="u-role" name="role" class="form-select<?= invalid('role') ?>">
        <?php foreach (AdminRepository::ROLES as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= $role === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($err = error_for('role')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
      <div class="form-hint">Owners can also add and remove admin users. Editors can do everything else.</div>
    </div>

    <div class="mb-3">
      <label class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input<?= invalid('is_active') ?>" type="checkbox" name="is_active" value="1"<?= $active ? ' checked' : '' ?><?= $isSelf ? ' disabled' : '' ?>>
        <span class="form-check-label">Active — can sign in</span>
        <?php if ($err = error_for('is_active')): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
      </label>
      <?php if ($isSelf): ?>
        <input type="hidden" name="is_active" value="1">
        <div class="form-hint">You cannot disable your own account.</div>
      <?php endif; ?>
    </div>

    <hr class="my-3">
    <h4 class="mb-1"><?= $editing ? 'Change password' : 'Password' ?></h4>
    <p class="text-secondary small mb-3"><?= $editing ? 'Leave blank to keep the current password.' : 'At least 10 characters. Share it with the person privately.' ?></p>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label<?= $editing ? '' : ' required' ?>" for="u-password"><?= $editing ? 'New password' : 'Password' ?></label>
        <input type="password" id="u-password" name="password" class="form-control<?= invalid('password') ?>" minlength="10" autocomplete="new-password"<?= $editing ? '' : ' required' ?>>
        <?php if ($err = error_for('password')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label<?= $editing ? '' : ' required' ?>" for="u-password2">Repeat password</label>
        <input type="password" id="u-password2" name="password_confirmation" class="form-control<?= invalid('password_confirmation') ?>" autocomplete="new-password"<?= $editing ? '' : ' required' ?>>
        <?php if ($err = error_for('password_confirmation')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex gap-2 justify-content-end">
    <a href="<?= e(url('/users')) ?>" class="btn btn-link">Cancel</a>
    <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $editing ? 'Save changes' : 'Add user' ?></button>
  </div>
</form>
