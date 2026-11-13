<?php
/**
 * Vendor-wise outstanding payables.
 * @var list<array<string,mixed>> $rows @var string $type @var float $total
 */
use App\Repositories\VendorRepository;
?>
<div class="rth-ops" data-ops-page="payables">
  <div class="row row-deck row-cards mb-3">
    <div class="col-sm-6 col-lg-4">
      <div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Total we owe suppliers</div>
        <div class="h1 m-0 <?= $total > 0 ? 'text-orange' : 'text-success' ?>"><?= e($total > 0 ? inr($total) : 'Nothing due') ?></div>
      </div></div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="card card-sm"><div class="card-body">
        <div class="text-secondary small">Vendors with dues</div>
        <div class="h1 m-0"><?= e(count($rows)) ?></div>
      </div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <form method="get" action="<?= e(url('/vendors/payables')) ?>" class="d-flex flex-wrap gap-2 align-items-center w-100">
        <label class="form-label m-0 me-1" for="ptype">Show</label>
        <select class="form-select w-auto" id="ptype" name="type">
          <option value="">All vendor types</option>
          <?php foreach (VendorRepository::TYPES as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($type !== ''): ?><a href="<?= e(url('/vendors/payables')) ?>" class="btn btn-ghost-secondary">Clear</a><?php endif; ?>
      </form>
    </div>

    <?php if ($rows === []): ?>
      <div class="empty">
        <div class="empty-icon"><?= icon('wallet') ?></div>
        <p class="empty-title">All suppliers are paid</p>
        <p class="empty-subtitle text-secondary">When you add hotel, houseboat or cab costs to a booking and haven’t paid the vendor in full, the balance shows up here.</p>
        <div class="empty-action"><a href="<?= e(url('/vendors')) ?>" class="btn btn-outline-primary"><?= icon('building') ?> View vendors</a></div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-vcenter card-table rth-ops-table">
          <thead>
            <tr>
              <th>Vendor</th>
              <th>Phone</th>
              <th class="text-end">Bookings</th>
              <th class="text-end">Unpaid items</th>
              <th>Oldest due</th>
              <th class="text-end">Total cost</th>
              <th class="text-end">Paid</th>
              <th class="text-end">Outstanding</th>
              <th class="w-1"><span class="visually-hidden">Open</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): $vid = (int) $r['id']; ?>
              <tr>
                <td>
                  <a href="<?= e(url('/vendors/' . $vid)) ?>" class="fw-semibold text-reset"><?= e($r['name']) ?></a>
                  <div class="small text-secondary"><?= e(VendorRepository::typeLabel((string) $r['type'])) ?><?= $r['city'] ? ' · ' . e($r['city']) : '' ?></div>
                  <?php if ($r['bank_details']): ?><div class="small text-secondary rth-ops-pre"><?= icon('cash', 'icon icon-sm') ?> <?= e(str_limit((string) $r['bank_details'], 70)) ?></div><?php endif; ?>
                </td>
                <td class="text-nowrap">
                  <?php if ($r['phone']): ?>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $r['phone'])) ?>" class="btn btn-sm btn-icon btn-ghost-secondary" title="Call" aria-label="Call <?= e($r['name']) ?>"><?= icon('phone') ?></a>
                    <a href="<?= e(wa_link((string) $r['phone'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-ghost-success" title="WhatsApp" aria-label="WhatsApp <?= e($r['name']) ?>"><?= icon('whatsapp') ?></a>
                  <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                </td>
                <td class="text-end"><?= e((int) $r['bookings_served']) ?></td>
                <td class="text-end"><?= e((int) $r['unpaid_count']) ?></td>
                <td class="text-nowrap"><?= e(nice_day($r['oldest_due'] ?? null)) ?></td>
                <td class="text-end text-nowrap"><?= e(inr($r['total_cost'])) ?></td>
                <td class="text-end text-nowrap"><?= e(inr($r['total_paid'])) ?></td>
                <td class="text-end text-nowrap fw-bold text-orange"><?= e(inr($r['payable'])) ?></td>
                <td><a href="<?= e(url('/vendors/' . $vid)) ?>" class="btn btn-sm btn-outline-primary text-nowrap">Details <?= icon('chevron-right', 'icon icon-sm') ?></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td colspan="7" class="text-end">Total outstanding</td>
              <td class="text-end text-nowrap text-orange"><?= e(inr($total)) ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <p class="form-hint mt-2">Outstanding = cost − paid to vendor, for every service that is not cancelled. Open a vendor to mark individual items as paid.</p>
</div>
