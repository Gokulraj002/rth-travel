<?php
/**
 * Hotels & vendors list.
 * @var array{q:string,type:string,city:string,active:string} $f
 * @var array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int} $result
 * @var array<string,int> $typeCounts
 * @var list<string> $cities
 */
use App\Repositories\VendorRepository;

$filtered = $f['q'] !== '' || $f['type'] !== '' || $f['city'] !== '' || $f['active'] !== '';
$tabLink = static function (string $type) use ($f): string {
    $q = array_filter(['q' => $f['q'], 'city' => $f['city'], 'active' => $f['active'], 'type' => $type], static fn ($v) => $v !== '');
    return url('/vendors') . ($q ? '?' . http_build_query($q) : '');
};
$currentUrl = '/vendors' . (($qs = http_build_query(array_filter([...$f, 'page' => $result['page'] > 1 ? $result['page'] : ''], static fn ($v) => $v !== ''))) !== '' ? '?' . $qs : '');
$allCount = array_sum($typeCounts);
?>
<div class="rth-ops" data-ops-page="vendors">
  <ul class="nav nav-tabs rth-ops-tabs mb-0" role="tablist" aria-label="Vendor type">
    <li class="nav-item"><a class="nav-link<?= $f['type'] === '' ? ' active' : '' ?>" href="<?= e($tabLink('')) ?>">All <span class="badge bg-secondary-lt ms-1"><?= e($allCount) ?></span></a></li>
    <?php foreach (VendorRepository::TYPES as $key => $label): ?>
      <li class="nav-item">
        <a class="nav-link<?= $f['type'] === $key ? ' active' : '' ?>" href="<?= e($tabLink($key)) ?>">
          <?= icon(VendorRepository::TYPE_ICONS[$key], 'icon me-1') ?><?= e($label) ?>
          <span class="badge bg-secondary-lt ms-1"><?= e($typeCounts[$key] ?? 0) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="card rth-ops-tabcard">
    <div class="card-header">
      <form method="get" action="<?= e(url('/vendors')) ?>" class="row g-2 align-items-center w-100" role="search">
        <?php if ($f['type'] !== ''): ?><input type="hidden" name="type" value="<?= e($f['type']) ?>"><?php endif; ?>
        <div class="col-12 col-md">
          <div class="input-icon">
            <span class="input-icon-addon"><?= icon('search') ?></span>
            <input type="search" class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Search name, contact person, phone or email…" aria-label="Search vendors">
          </div>
        </div>
        <div class="col-6 col-md-auto">
          <select class="form-select" name="city" aria-label="Filter by city">
            <option value="">All cities</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= e($c) ?>"<?= $f['city'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-auto">
          <select class="form-select" name="active" aria-label="Filter by active">
            <option value="">Active &amp; inactive</option>
            <option value="1"<?= $f['active'] === '1' ? ' selected' : '' ?>>Active only</option>
            <option value="0"<?= $f['active'] === '0' ? ' selected' : '' ?>>Inactive only</option>
          </select>
        </div>
        <div class="col-12 col-md-auto d-flex gap-2">
          <button type="submit" class="btn btn-primary">Filter</button>
          <?php if ($filtered): ?><a class="btn btn-ghost-secondary" href="<?= e(url('/vendors')) ?>">Clear</a><?php endif; ?>
        </div>
      </form>
    </div>

    <?php if ($result['rows'] === []): ?>
      <div class="empty">
        <div class="empty-icon"><?= icon('building') ?></div>
        <p class="empty-title"><?= $filtered ? 'No vendors match your filters' : 'No hotels or vendors yet' ?></p>
        <p class="empty-subtitle text-secondary">
          <?= $filtered
            ? 'Try another word, city or tab — or clear the filters.'
            : 'Add the hotels, houseboats, taxi operators and guides you work with. Then you can add their costs to bookings and always know what you owe them.' ?>
        </p>
        <div class="empty-action">
          <a href="<?= e(url('/vendors/create' . ($f['type'] !== '' ? '?type=' . rawurlencode($f['type']) : ''))) ?>" class="btn btn-primary"><?= icon('plus') ?> Add vendor</a>
        </div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-vcenter card-table rth-ops-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Contact</th>
              <th>Phone</th>
              <th>City</th>
              <th>Rating</th>
              <th class="text-end">Bookings</th>
              <th class="text-end">Payable</th>
              <th>Active</th>
              <th class="w-1"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($result['rows'] as $v):
              $vid = (int) $v['id'];
              $rating = max(0, min(5, (int) $v['rating']));
              $payable = (float) $v['payable'];
              $active = (int) $v['is_active'] === 1;
            ?>
              <tr class="<?= $active ? '' : 'rth-ops-inactive' ?>">
                <td>
                  <a href="<?= e(url('/vendors/' . $vid)) ?>" class="fw-semibold text-reset"><?= e($v['name']) ?></a>
                  <?php if (!empty($v['gstin'])): ?><div class="small text-secondary">GSTIN <?= e($v['gstin']) ?></div><?php endif; ?>
                </td>
                <td class="text-nowrap"><span class="badge bg-azure-lt"><?= icon(VendorRepository::TYPE_ICONS[$v['type']] ?? 'briefcase', 'icon icon-sm me-1') ?><?= e(VendorRepository::typeLabel((string) $v['type'])) ?></span></td>
                <td><?= $v['contact_person'] ? e($v['contact_person']) : '<span class="text-secondary">—</span>' ?></td>
                <td class="text-nowrap">
                  <?php if (!empty($v['phone'])): ?>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $v['phone'])) ?>" class="text-reset"><?= e($v['phone']) ?></a>
                    <div class="d-inline-flex gap-1 ms-1">
                      <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $v['phone'])) ?>" class="btn btn-sm btn-icon btn-ghost-secondary" title="Call" aria-label="Call <?= e($v['name']) ?>"><?= icon('phone') ?></a>
                      <a href="<?= e(wa_link((string) $v['phone'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-ghost-success" title="WhatsApp" aria-label="WhatsApp <?= e($v['name']) ?>"><?= icon('whatsapp') ?></a>
                    </div>
                  <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                </td>
                <td><?= $v['city'] ? e($v['city']) : '<span class="text-secondary">—</span>' ?></td>
                <td class="text-nowrap">
                  <?php if ($rating > 0): ?>
                    <span class="text-warning" aria-hidden="true"><?= str_repeat('★', $rating) ?></span><span class="text-muted" aria-hidden="true"><?= str_repeat('☆', 5 - $rating) ?></span>
                    <span class="visually-hidden"><?= e($rating) ?> out of 5 stars</span>
                  <?php else: ?><span class="text-secondary small">Not rated</span><?php endif; ?>
                </td>
                <td class="text-end"><?= e((int) $v['bookings_served']) ?></td>
                <td class="text-end text-nowrap">
                  <?php if ($payable > 0.004): ?>
                    <span class="fw-semibold text-orange"><?= e(inr($payable)) ?></span>
                  <?php elseif ($payable < -0.004): ?>
                    <span class="text-azure" title="Paid more than the cost — an advance with this vendor">Advance <?= e(inr(abs($payable))) ?></span>
                  <?php else: ?>
                    <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" action="<?= e(url('/vendors/' . $vid . '/toggle')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="<?= e($currentUrl) ?>">
                    <button type="submit" class="btn btn-sm <?= $active ? 'btn-outline-success' : 'btn-outline-secondary' ?>"
                            title="<?= $active ? 'Click to mark inactive' : 'Click to activate' ?>">
                      <?= $active ? icon('check', 'icon icon-sm') . ' Active' : 'Inactive' ?>
                    </button>
                  </form>
                </td>
                <td>
                  <div class="d-flex gap-1 justify-content-end">
                    <a href="<?= e(url('/vendors/' . $vid)) ?>" class="btn btn-sm btn-icon btn-ghost-secondary" title="View" aria-label="View <?= e($v['name']) ?>"><?= icon('eye') ?></a>
                    <a href="<?= e(url('/vendors/' . $vid . '/edit')) ?>" class="btn btn-sm btn-icon btn-ghost-primary" title="Edit" aria-label="Edit <?= e($v['name']) ?>"><?= icon('edit') ?></a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= partial('admin/destinations/_pagination', ['result' => $result, 'basePath' => '/vendors', 'query' => $f]) ?>
    <?php endif; ?>
  </div>
</div>
