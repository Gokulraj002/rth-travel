<?php
/**
 * @var array<string,mixed>|null $user
 * @var bool $isSelf
 * @var array<string,array{label:string,desc:string,permissions:list<string>}> $roles
 * @var list<string> $assignable
 */
use App\Core\Gate;

$editing = $user !== null;
$action = $editing ? url('/users/' . $user['id']) : url('/users');
$role = (string) old('role', $user['role'] ?? 'editor');
$currentRole = (string) ($user['role'] ?? '');
// Checkbox: after a failed submit, old() holds the posted value ('1') or nothing (unchecked).
$hasOld = old('name', null) !== null;
$active = $hasOld ? old('is_active', '0') === '1' : (int) ($user['is_active'] ?? 1) === 1;
?>
<div class="mb-3">
  <a href="<?= e(url('/users')) ?>" class="text-secondary">&larr; Back to team</a>
</div>

<form method="post" action="<?= e($action) ?>" class="card rth-tools-narrow" novalidate autocomplete="off">
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
        <label class="form-label" for="u-phone">Mobile number</label>
        <input type="tel" id="u-phone" name="phone" class="form-control<?= invalid('phone') ?>" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" maxlength="30" placeholder="+91 98765 43210" autocomplete="off">
        <?php if ($err = error_for('phone')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
      </div>
      <div class="col-12 mb-3">
        <label class="form-label required" for="u-email">Email (used to sign in)</label>
        <input type="email" id="u-email" name="email" class="form-control<?= invalid('email') ?>" value="<?= e(old('email', $user['email'] ?? '')) ?>" maxlength="190" required autocomplete="off">
        <?php if ($err = error_for('email')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
      </div>
    </div>

    <fieldset class="mb-3">
      <legend class="form-label required">Role</legend>
      <div class="rth-role-picker<?= error_for('role') ? ' is-invalid' : '' ?>">
        <?php foreach ($roles as $key => $r):
            $allowed = in_array($key, $assignable, true) || ($editing && $key === $currentRole);
            $id = 'u-role-' . $key; ?>
          <label class="rth-role-option<?= $allowed ? '' : ' is-locked' ?>" for="<?= e($id) ?>">
            <input class="form-check-input" type="radio" name="role" id="<?= e($id) ?>" value="<?= e($key) ?>"<?= $role === $key ? ' checked' : '' ?><?= $allowed ? '' : ' disabled' ?>>
            <span class="rth-role-option-body">
              <span class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge rth-role rth-role-<?= e($key) ?>"><?= e(Gate::roleLabel($key)) ?></span>
                <?php if (!$allowed): ?>
                  <a href="<?= e(url('/license')) ?>" class="rth-role-lock" title="Staff roles need the Premium plan"><?= icon('lock') ?> Premium</a>
                <?php endif; ?>
              </span>
              <span class="d-block small text-secondary mt-1"><?= e($r['desc']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if ($err = error_for('role')): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
      <div class="form-hint"><a href="<?= e(url('/users/roles')) ?>" target="_blank" rel="noopener">Compare what each role can open</a></div>
    </fieldset>

    <div class="mb-3">
      <label class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input<?= invalid('is_active') ?>" type="checkbox" name="is_active" value="1"<?= $active ? ' checked' : '' ?><?= $isSelf ? ' disabled' : '' ?>>
        <span class="form-check-label">Active — can sign in</span>
        <?php if ($err = error_for('is_active')): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
      </label>
      <?php if ($isSelf): ?>
        <input type="hidden" name="is_active" value="1">
        <div class="form-hint">You cannot deactivate your own account.</div>
      <?php else: ?>
        <div class="form-hint">Switch off when someone leaves — their history in the activity log stays.</div>
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
    <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $editing ? 'Save changes' : 'Add team member' ?></button>
  </div>
</form>
