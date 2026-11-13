<?php
/**
 * @var array<string,list<array<string,mixed>>> $grouped channel => templates
 * @var string $q
 */
use App\Repositories\TemplateRepository as Tpl;

$total = array_sum(array_map('count', $grouped));
?>
<div class="rth-tools-templates">
  <p class="text-secondary mb-3">
    Write a message once and send it in one click. Words in <code>{{double_braces}}</code> are filled in automatically —
    for example <code>{{customer_name}}</code> becomes “Asha Sharma”. Templates marked <span class="badge bg-azure-lt">Used by the panel</span>
    are picked up by buttons on enquiries, quotations, bookings and payments, so keep their names unchanged.
  </p>

  <form method="get" action="<?= e(url('/templates')) ?>" class="mb-3 rth-tools-search">
    <div class="input-group">
      <span class="input-group-text"><?= icon('search') ?></span>
      <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Search templates" aria-label="Search templates">
      <button class="btn" type="submit">Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-link" href="<?= e(url('/templates')) ?>">Clear</a><?php endif; ?>
    </div>
  </form>

  <?php if ($total === 0 && $q !== ''): ?>
    <div class="card"><div class="empty">
      <p class="empty-title">No templates match “<?= e($q) ?>”</p>
      <div class="empty-action"><a href="<?= e(url('/templates')) ?>" class="btn">Show all templates</a></div>
    </div></div>
  <?php endif; ?>

  <?php foreach ($grouped as $channel => $templates): if ($q !== '' && $templates === []) continue; ?>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title d-flex align-items-center gap-2">
          <span class="rth-channel rth-channel-<?= e($channel) ?>"><?= icon($channel === 'email' ? 'mail' : 'whatsapp') ?></span>
          <?= e(Tpl::CHANNELS[$channel]) ?> templates
          <span class="badge bg-secondary-lt"><?= count($templates) ?></span>
        </h3>
        <div class="card-actions">
          <a href="<?= e(url('/templates/create?channel=' . $channel)) ?>" class="btn btn-sm"><?= icon('plus') ?> New <?= e(Tpl::CHANNELS[$channel]) ?> template</a>
        </div>
      </div>
      <?php if ($templates === []): ?>
        <div class="empty">
          <div class="empty-icon"><?= icon($channel === 'email' ? 'mail' : 'whatsapp') ?></div>
          <p class="empty-title">No <?= e(Tpl::CHANNELS[$channel]) ?> templates yet</p>
          <p class="empty-subtitle text-secondary">Create one for messages you send again and again.</p>
          <div class="empty-action"><a href="<?= e(url('/templates/create?channel=' . $channel)) ?>" class="btn btn-primary"><?= icon('plus') ?> Create a template</a></div>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead><tr><th>Name</th><th class="d-none d-md-table-cell">Message</th><th>On</th><th class="w-1"></th></tr></thead>
            <tbody>
              <?php foreach ($templates as $t): $system = Tpl::isSystem((string) $t['name'], (string) $t['channel']); $on = (int) $t['is_active'] === 1; ?>
                <tr class="<?= $on ? '' : 'rth-row-muted' ?>">
                  <td>
                    <a href="<?= e(url('/templates/' . $t['id'] . '/edit')) ?>" class="fw-semibold text-reset"><?= e($t['name']) ?></a>
                    <?php if ($system): ?><div><span class="badge bg-azure-lt" title="Used for <?= e(Tpl::SYSTEM[$t['name']][1]) ?>">Used by the panel</span></div><?php endif; ?>
                    <?php if (!empty($t['subject'])): ?><div class="small text-secondary">Subject: <?= e(str_limit((string) $t['subject'], 70)) ?></div><?php endif; ?>
                  </td>
                  <td class="d-none d-md-table-cell text-secondary small rth-tpl-snippet"><?= e(str_limit((string) $t['body'], 140)) ?></td>
                  <td>
                    <form method="post" action="<?= e(url('/templates/' . $t['id'] . '/toggle')) ?>" class="m-0">
                      <?= csrf_field() ?>
                      <label class="form-check form-switch m-0" title="<?= $on ? 'Switch off' : 'Switch on' ?>">
                        <input class="form-check-input" type="checkbox" data-tools-autosubmit<?= $on ? ' checked' : '' ?> aria-label="<?= e(($on ? 'Switch off ' : 'Switch on ') . $t['name']) ?>">
                      </label>
                      <noscript><button type="submit" class="btn btn-sm"><?= $on ? 'Switch off' : 'Switch on' ?></button></noscript>
                    </form>
                  </td>
                  <td class="text-nowrap">
                    <a href="<?= e(url('/templates/' . $t['id'] . '/edit')) ?>" class="btn btn-sm"><?= icon('edit') ?> Edit</a>
                    <form method="post" action="<?= e(url('/templates/' . $t['id'] . '/duplicate')) ?>" class="d-inline">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-sm btn-ghost-secondary" title="Duplicate" aria-label="Duplicate <?= e($t['name']) ?>"><?= icon('copy') ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/templates/' . $t['id'])) ?>" class="d-inline"
                          data-confirm="Delete “<?= e($t['name']) ?>”?<?= $system ? ' It is used for ' . e(Tpl::SYSTEM[$t['name']][1]) . ' — the panel will fall back to its built-in text.' : '' ?>">
                      <?= csrf_field() ?>
                      <?= method_field('DELETE') ?>
                      <button type="submit" class="btn btn-sm btn-ghost-danger" title="Delete" aria-label="Delete <?= e($t['name']) ?>"><?= icon('trash') ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
