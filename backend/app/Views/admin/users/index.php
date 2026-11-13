<?php
/**
 * @var list<array<string,mixed>> $users
 * @var int|null $meId
 */
use App\Repositories\AdminRepository;
?>
<div class="d-flex mb-3">
  <p class="text-secondary m-0 me-auto">Owners can manage everything including this page. Editors can manage packages, destinations, enquiries, reviews, FAQs and settings.</p>
  <a href="<?= e(url('/users/create')) ?>" class="btn btn-primary ms-3 text-nowrap"><?= icon('plus') ?> Add user</a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr><th>Name</th><th>Role</th><th>Status</th><th>Last signed in</th><th class="w-1"></th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): $isMe = (int) $u['id'] === $meId; ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm rth-avatar"><?= e(mb_strtoupper(mb_substr((string) $u['name'], 0, 1))) ?></span>
                <div>
                  <div class="fw-semibold"><?= e($u['name']) ?><?php if ($isMe): ?> <span class="badge bg-blue-lt ms-1">You</span><?php endif; ?></div>
                  <div class="small text-secondary"><?= e($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td><?= e(AdminRepository::ROLES[$u['role']] ?? ucfirst((string) $u['role'])) ?></td>
            <td>
              <?php if ((int) $u['is_active'] === 1): ?>
                <span class="badge badge-published">Active</span>
              <?php else: ?>
                <span class="badge badge-draft">Disabled</span>
              <?php endif; ?>
            </td>
            <td class="text-secondary small text-nowrap"><?= $u['last_login_at'] ? e(nice_date($u['last_login_at'])) : 'Never' ?></td>
            <td class="text-nowrap">
              <a href="<?= e(url('/users/' . $u['id'] . '/edit')) ?>" class="btn btn-sm"><?= icon('edit') ?> Edit</a>
              <?php if (!$isMe): ?>
                <form method="post" action="<?= e(url('/users/' . $u['id'])) ?>" class="d-inline" data-confirm="Remove <?= e($u['name']) ?>? They will no longer be able to sign in.">
                  <?= csrf_field() ?>
                  <?= method_field('DELETE') ?>
                  <button type="submit" class="btn btn-sm btn-ghost-danger" title="Remove user" aria-label="Remove <?= e($u['name']) ?>"><?= icon('trash') ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
