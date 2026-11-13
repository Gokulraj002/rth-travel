<?php
/**
 * @var list<array<string,mixed>> $users
 * @var int|null $meId
 * @var bool $rolesUnlocked
 */
use App\Core\Gate;
use App\Repositories\ActivityRepository;

$roles = Gate::roles();
?>
<div class="rth-tools-users">
  <p class="text-secondary mb-3">
    Give each person their own sign-in so you always know who did what. Their <strong>role</strong> decides which menus they see.
    <a href="<?= e(url('/users/roles')) ?>">See what each role can do</a>.
  </p>

  <?php if (!$rolesUnlocked): ?>
    <div class="alert alert-info d-flex align-items-start gap-2">
      <?= icon('lock') ?>
      <div>
        Your plan includes <strong>Owner</strong> and <strong>Editor</strong> accounts. Sales, Accounts and Manager roles — so staff only see the screens they need — are part of the
        <a href="<?= e(url('/license')) ?>">Premium plan</a>.
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr><th>Name</th><th>Role</th><th>Status</th><th>Last signed in</th><th class="w-1"></th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): $isMe = (int) $u['id'] === $meId; $role = (string) $u['role']; ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="avatar avatar-sm rth-avatar"><?= e(mb_strtoupper(mb_substr((string) $u['name'], 0, 1))) ?></span>
                  <div>
                    <div class="fw-semibold"><?= e($u['name']) ?><?php if ($isMe): ?> <span class="badge bg-blue-lt ms-1">You</span><?php endif; ?></div>
                    <div class="small text-secondary">
                      <?= e($u['email']) ?><?php if (!empty($u['phone'])): ?> · <?= e($u['phone']) ?><?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge rth-role rth-role-<?= e(preg_replace('/[^a-z0-9_-]/', '', $role)) ?>"><?= e(Gate::roleLabel($role)) ?></span>
                <?php if (isset($roles[$role]['desc'])): ?><div class="small text-secondary mt-1 d-none d-lg-block"><?= e($roles[$role]['desc']) ?></div><?php endif; ?>
              </td>
              <td>
                <?php if ((int) $u['is_active'] === 1): ?>
                  <span class="badge badge-published">Active</span>
                <?php else: ?>
                  <span class="badge badge-draft">Deactivated</span>
                <?php endif; ?>
              </td>
              <td class="small text-nowrap">
                <?php if ($u['last_login_at']): ?>
                  <span title="<?= e(nice_date($u['last_login_at'])) ?>"><?= e(ActivityRepository::ago((string) $u['last_login_at'])) ?></span>
                  <div class="text-secondary"><?= e(nice_date($u['last_login_at'])) ?></div>
                <?php else: ?>
                  <span class="text-secondary">Never</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <a href="<?= e(url('/users/' . $u['id'] . '/edit')) ?>" class="btn btn-sm"><?= icon('edit') ?> Edit</a>
                <?php if (!$isMe): ?>
                  <form method="post" action="<?= e(url('/users/' . $u['id'])) ?>" class="d-inline" data-confirm="Remove <?= e($u['name']) ?>? They will no longer be able to sign in. (Tip: you can deactivate them instead to keep their history.)">
                    <?= csrf_field() ?>
                    <?= method_field('DELETE') ?>
                    <button type="submit" class="btn btn-sm btn-ghost-danger" title="Remove team member" aria-label="Remove <?= e($u['name']) ?>"><?= icon('trash') ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
