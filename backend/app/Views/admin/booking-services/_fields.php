<?php
/**
 * Service form fields (shared by the inline "Add service" form and the edit page).
 * Field names are prefixed "svc_" so they never clash with other forms on the booking page.
 * operations.js filters the vendor list by type and shows the vehicle list only for Transport.
 *
 * @var array<string,mixed> $svc        existing service row (or [] for a new one)
 * @var string $idp                      unique id prefix for labels
 * @var bool $useOld                     read old() input (true when this form was the one submitted)
 */
use App\Repositories\BookingServiceRepository;
use App\Repositories\VehicleRepository;
use App\Repositories\VendorRepository;

$svc ??= [];
$useOld ??= true;
$map = ['svc_type' => 'service_type', 'svc_vendor_id' => 'vendor_id', 'svc_vehicle_id' => 'vehicle_id', 'svc_description' => 'description',
    'svc_date' => 'service_date', 'svc_nights' => 'nights', 'svc_cost' => 'cost_price', 'svc_paid' => 'paid_to_vendor',
    'svc_status' => 'status', 'svc_confirmation' => 'confirmation_no', 'svc_notes' => 'notes'];
$val = static function (string $k, mixed $default = '') use ($svc, $map, $useOld): string {
    $model = $svc[$map[$k]] ?? $default;
    return (string) ($useOld ? old($k, $model) : $model);
};
$err = static fn (string $k): string => $useOld && ($m = error_for($k)) ? '<div class="invalid-feedback d-block">' . e($m) . '</div>' : '';
$inv = static fn (string $k): string => $useOld && error_for($k) ? ' is-invalid' : '';
$money = static function (string $v): string {
    if ($v === '' || !is_numeric($v)) {
        return $v;
    }
    $f = (float) $v;
    return floor($f) == $f ? (string) (int) $f : number_format($f, 2, '.', '');
};

$type = $val('svc_type', 'hotel');
$vendorId = (int) $val('svc_vendor_id');
$vehicleId = (int) $val('svc_vehicle_id');
$status = $val('svc_status', 'pending');
$vendors = VendorRepository::options($vendorId ?: null);
$vehicles = VehicleRepository::options($vehicleId ?: null);
$canVendors = feature('vendors') && can('vendors');
$canVehicles = feature('vehicles') && can('vendors');
?>
<div class="row g-3" data-ops-service-form>
  <div class="col-sm-6 col-lg-3">
    <label class="form-label required" for="<?= e($idp) ?>type">Type</label>
    <select class="form-select<?= $inv('svc_type') ?>" id="<?= e($idp) ?>type" name="svc_type" data-ops-type>
      <?php foreach (BookingServiceRepository::TYPES as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?= $err('svc_type') ?>
  </div>
  <div class="col-sm-6 col-lg-5">
    <label class="form-label" for="<?= e($idp) ?>vendor">Vendor</label>
    <select class="form-select<?= $inv('svc_vendor_id') ?>" id="<?= e($idp) ?>vendor" name="svc_vendor_id" data-ops-vendor>
      <option value="">— No vendor / own arrangement —</option>
      <?php foreach ($vendors as $o): ?>
        <option value="<?= e($o['id']) ?>" data-type="<?= e($o['type']) ?>"<?= $vendorId === $o['id'] ? ' selected' : '' ?>>
          <?= e($o['name']) ?><?= $o['city'] ? ' — ' . e($o['city']) : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?= $err('svc_vendor_id') ?>
    <div class="form-hint" data-ops-vendor-hint>
      <?php if ($vendors === []): ?>
        No vendors yet<?php if ($canVendors): ?> — <a href="<?= e(url('/vendors/create')) ?>" target="_blank" rel="noopener">add one</a><?php endif; ?>.
      <?php else: ?>
        List shows vendors of the chosen type<?php if ($canVendors): ?> · <a href="<?= e(url('/vendors/create')) ?>" target="_blank" rel="noopener">new vendor</a><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-sm-12 col-lg-4" data-ops-vehicle-field<?= $type === 'transport' ? '' : ' hidden' ?>>
    <label class="form-label" for="<?= e($idp) ?>vehicle">Own vehicle</label>
    <select class="form-select<?= $inv('svc_vehicle_id') ?>" id="<?= e($idp) ?>vehicle" name="svc_vehicle_id">
      <option value="">— Not our vehicle —</option>
      <?php foreach ($vehicles as $o): ?>
        <option value="<?= e($o['id']) ?>"<?= $vehicleId === $o['id'] ? ' selected' : '' ?>><?= e($o['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <?= $err('svc_vehicle_id') ?>
    <?php if ($vehicles === [] && $canVehicles): ?><div class="form-hint"><a href="<?= e(url('/vehicles/create')) ?>" target="_blank" rel="noopener">Add your vehicles</a> to assign them here.</div><?php endif; ?>
  </div>

  <div class="col-12 col-lg-6">
    <label class="form-label" for="<?= e($idp) ?>desc">Description</label>
    <input type="text" class="form-control<?= $inv('svc_description') ?>" id="<?= e($idp) ?>desc" name="svc_description" value="<?= e($val('svc_description')) ?>" maxlength="300" placeholder="e.g. Deluxe room, 2 nights, MAP — or leave empty to use the vendor name">
    <?= $err('svc_description') ?>
  </div>
  <div class="col-7 col-sm-4 col-lg-3">
    <label class="form-label" for="<?= e($idp) ?>date">Date</label>
    <input type="date" class="form-control<?= $inv('svc_date') ?>" id="<?= e($idp) ?>date" name="svc_date" value="<?= e(substr($val('svc_date'), 0, 10)) ?>">
    <?= $err('svc_date') ?>
  </div>
  <div class="col-5 col-sm-2 col-lg-3">
    <label class="form-label" for="<?= e($idp) ?>nights">Nights</label>
    <input type="number" class="form-control<?= $inv('svc_nights') ?>" id="<?= e($idp) ?>nights" name="svc_nights" value="<?= e($val('svc_nights')) ?>" min="0" max="60" inputmode="numeric">
    <?= $err('svc_nights') ?>
  </div>

  <div class="col-6 col-sm-4 col-lg-3">
    <label class="form-label required" for="<?= e($idp) ?>cost">Cost price (₹)</label>
    <input type="text" class="form-control<?= $inv('svc_cost') ?>" id="<?= e($idp) ?>cost" name="svc_cost" value="<?= e($money($val('svc_cost'))) ?>" inputmode="decimal" placeholder="What we pay" data-ops-cost>
    <?= $err('svc_cost') ?>
  </div>
  <div class="col-6 col-sm-4 col-lg-3">
    <label class="form-label" for="<?= e($idp) ?>paid">Paid to vendor (₹)</label>
    <div class="input-group">
      <input type="text" class="form-control<?= $inv('svc_paid') ?>" id="<?= e($idp) ?>paid" name="svc_paid" value="<?= e($money($val('svc_paid', '0'))) ?>" inputmode="decimal" data-ops-paid>
      <button type="button" class="btn btn-outline-secondary" data-ops-fill-paid title="Paid in full (same as cost)">Full</button>
    </div>
    <?= $err('svc_paid') ?>
  </div>
  <div class="col-6 col-sm-4 col-lg-3">
    <label class="form-label" for="<?= e($idp) ?>status">Status</label>
    <select class="form-select<?= $inv('svc_status') ?>" id="<?= e($idp) ?>status" name="svc_status">
      <?php foreach (BookingServiceRepository::STATUSES as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?= $err('svc_status') ?>
  </div>
  <div class="col-6 col-sm-6 col-lg-3">
    <label class="form-label" for="<?= e($idp) ?>conf">Confirmation no.</label>
    <input type="text" class="form-control<?= $inv('svc_confirmation') ?>" id="<?= e($idp) ?>conf" name="svc_confirmation" value="<?= e($val('svc_confirmation')) ?>" maxlength="80" placeholder="Hotel / supplier ref.">
    <?= $err('svc_confirmation') ?>
  </div>
  <div class="col-12 col-sm-6 col-lg-12">
    <label class="form-label" for="<?= e($idp) ?>notes">Notes</label>
    <input type="text" class="form-control<?= $inv('svc_notes') ?>" id="<?= e($idp) ?>notes" name="svc_notes" value="<?= e($val('svc_notes')) ?>" maxlength="255" placeholder="e.g. Lake-facing room requested, pay balance at check-out">
    <?= $err('svc_notes') ?>
  </div>
</div>
