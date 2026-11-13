<?php
/**
 * Read-only permission matrix (config/permissions.php).
 * @var array<string,array{label:string,desc:string,permissions:list<string>}> $roles
 * @var array<string,string> $permissions
 * @var list<string> $assignable
 * @var array<string,int> $counts
 * @var bool $rolesUnlocked
 */
use App\Core\License;

$has = static fn (array $role, string $perm): bool => in_array('*', $role['permissions'], true) || in_array($perm, $role['permissions'], true);

// Sidebar menu items each role would see (config/navigation.php), ignoring the plan.
$menuFor = static function (string $roleKey, array $role) use ($has): array {
    $out = [];
    foreach (config('navigation') as $group) {
        foreach ($group['items'] as $item) {
            if (!empty($item['owner']) && $roleKey !== 'owner') {
                continue;
            }
            if (!empty($item['can']) && $roleKey !== 'owner' && !$has($role, $item['can'])) {
                continue;
            }
            $out[] = $item;
        }
    }
    return $out;
};
?>
<div class="rth-tools-roles">
  <div class="mb-3">
    <a href="<?= e(url('/users')) ?>" class="text-secondary">&larr; Back to team</a>
  </div>

  <p class="text-secondary">
    Each person gets one role. A role decides which menus they see and which pages they can open — anything else shows
    “Your role does not have access”. The <strong>Owner</strong> can always do everything, including managing the team, the plan and backups.
  </p>

  <?php if (!$rolesUnlocked): ?>
    <div class="alert alert-info d-flex align-items-start gap-2">
      <?= icon('lock') ?>
      <div>On your <?= e(License::planLabel()) ?> plan you can give the <strong>Owner</strong> and <strong>Editor</strong> roles. The others need the <a href="<?= e(url('/license')) ?>">Premium plan</a>.</div>
    </div>
  <?php endif; ?>

  <div class="row row-cards mb-3">
    <?php foreach ($roles as $key => $role): $locked = !in_array($key, $assignable, true); ?>
      <div class="col-sm-6 col-xl-4">
        <div class="card h-100<?= $locked ? ' rth-role-card-locked' : '' ?>">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge rth-role rth-role-<?= e($key) ?>"><?= e($role['label']) ?></span>
              <span class="small text-secondary"><?= (int) ($counts[$key] ?? 0) ?> <?= ($counts[$key] ?? 0) === 1 ? 'person' : 'people' ?></span>
              <?php if ($locked): ?><a href="<?= e(url('/license')) ?>" class="rth-role-lock ms-auto"><?= icon('lock') ?> Premium</a><?php endif; ?>
            </div>
            <p class="mb-2"><?= e($role['desc']) ?></p>
            <div class="small text-secondary mb-1">Menus they see:</div>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($menuFor($key, $role) as $item): ?>
                <span class="badge bg-secondary-lt rth-menu-chip"><?= icon($item['icon']) ?> <?= e($item['label']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">Permission matrix</h3></div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table rth-perm-matrix">
        <thead>
          <tr>
            <th>Can…</th>
            <?php foreach ($roles as $key => $role): ?>
              <th class="text-center"><span class="badge rth-role rth-role-<?= e($key) ?>"><?= e($role['label']) ?></span></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($permissions as $perm => $label): ?>
            <tr>
              <td><?= e($label) ?></td>
              <?php foreach ($roles as $key => $role): $yes = $has($role, $perm); ?>
                <td class="text-center">
                  <?php if ($yes): ?>
                    <span class="rth-perm-yes" title="Yes"><?= icon('check') ?><span class="visually-hidden">Yes</span></span>
                  <?php else: ?>
                    <span class="rth-perm-no" title="No">—<span class="visually-hidden">No</span></span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td>Manage team, plan &amp; license</td>
            <?php foreach ($roles as $key => $role): ?>
              <td class="text-center"><?php if ($key === 'owner'): ?><span class="rth-perm-yes" title="Yes"><?= icon('check') ?><span class="visually-hidden">Yes</span></span><?php else: ?><span class="rth-perm-no" title="No">—<span class="visually-hidden">No</span></span><?php endif; ?></td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="card-footer small text-secondary">
      Some sections also need a paid plan (for example GST invoices need Premium). A role cannot open a section your plan does not include.
    </div>
  </div>
</div>
