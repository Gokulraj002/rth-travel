<?php
/**
 * Add / edit destination.
 * @var array<string,mixed>|null $destination
 * @var list<string> $highlights @var list<string> $things @var list<string> $gallery
 * @var array<string,string> $regions
 */
$d = $destination ?? [];
$isEdit = $destination !== null;
$action = $isEdit ? url('/destinations/' . $d['id']) : url('/destinations');
$val = static fn (string $k, mixed $default = ''): string => (string) old($k, $d[$k] ?? $default);
$fieldError = static fn (string $k): string => ($err = error_for($k)) ? '<div class="invalid-feedback">' . e($err) . '</div>' : '';
$status = $val('status', 'draft');
$region = $val('region');

$galleryRow = static function (string $path, int $number): string {
    return '<div class="rth-repeater-item">'
        . '<div class="row g-2 align-items-center">'
        . '<div class="col-auto"><span class="rth-repeater-number" data-repeater-number>' . e($number) . '</span></div>'
        . '<div class="col">'
        . '<div class="rth-image-field" data-image-field>'
        . '<img data-image-preview src="' . e(media_url($path)) . '" alt="" class="mb-2" style="max-height:140px">'
        . '<input type="hidden" name="gallery[]" value="' . e($path) . '" data-image-input>'
        . '<input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" data-image-file aria-label="Gallery photo">'
        . '</div></div>'
        . '<div class="col-auto"><div class="btn-list flex-column">'
        . '<button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-up title="Move up" aria-label="Move up">' . icon('arrow-up') . '</button>'
        . '<button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-down title="Move down" aria-label="Move down">' . icon('arrow-down') . '</button>'
        . '<button type="button" class="btn btn-icon btn-ghost-danger" data-repeater-remove title="Remove photo" aria-label="Remove photo">' . icon('trash') . '</button>'
        . '</div></div>'
        . '</div></div>';
};
?>
<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">

      <!-- Basics -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Basics</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required" for="name">Name</label>
              <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" placeholder="e.g. Srinagar" required>
              <?= $fieldError('name') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="display_name">Big display name</label>
              <input type="text" class="form-control<?= invalid('display_name') ?>" id="display_name" name="display_name" value="<?= e($val('display_name')) ?>" maxlength="120" placeholder="e.g. SRINAGAR" data-upper-from="#name">
              <?= $fieldError('display_name') ?>
              <div class="form-hint">The giant word in the website headings — usually the name in CAPITALS (filled in for you).</div>
            </div>
            <div class="col-md-6">
              <label class="form-label required" for="slug">Web address</label>
              <div class="input-group">
                <span class="input-group-text">/destinations/</span>
                <input type="text" class="form-control<?= invalid('slug') ?>" id="slug" name="slug" value="<?= e($val('slug')) ?>" maxlength="80" placeholder="srinagar" data-slug-from="#name" required>
                <?= $fieldError('slug') ?>
              </div>
              <?php if ($isEdit): ?>
                <div class="form-hint">Changing this changes the page link. Packages that include this destination are updated automatically.</div>
              <?php else: ?>
                <div class="form-hint">Created from the name. Lowercase letters, numbers and hyphens only.</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label required" for="region">Region</label>
              <select class="form-select<?= invalid('region') ?>" id="region" name="region" required>
                <option value="">Choose a region…</option>
                <?php foreach ($regions as $key => $label): ?>
                  <option value="<?= e($key) ?>"<?= $region === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= $fieldError('region') ?>
            </div>
            <div class="col-12">
              <label class="form-label" for="tagline">Tagline</label>
              <input type="text" class="form-control<?= invalid('tagline') ?>" id="tagline" name="tagline" value="<?= e($val('tagline')) ?>" maxlength="200" placeholder="e.g. Houseboats, shikaras &amp; Mughal gardens" data-counter="200">
              <?= $fieldError('tagline') ?>
            </div>
            <div class="col-12">
              <label class="form-label required" for="description">Description</label>
              <textarea class="form-control<?= invalid('description') ?>" id="description" name="description" rows="6" maxlength="5000" placeholder="A few sentences that make a traveller want to go." required><?= e($val('description')) ?></textarea>
              <?= $fieldError('description') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Location & facts -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Location &amp; facts</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-sm-4">
              <label class="form-label required" for="lat">Latitude</label>
              <input type="number" step="any" min="-90" max="90" class="form-control<?= invalid('lat') ?>" id="lat" name="lat" value="<?= e($val('lat')) ?>" placeholder="34.0837" required>
              <?= $fieldError('lat') ?>
            </div>
            <div class="col-sm-4">
              <label class="form-label required" for="lng">Longitude</label>
              <input type="number" step="any" min="-180" max="180" class="form-control<?= invalid('lng') ?>" id="lng" name="lng" value="<?= e($val('lng')) ?>" placeholder="74.7973" required>
              <?= $fieldError('lng') ?>
            </div>
            <div class="col-sm-4">
              <label class="form-label" for="altitude_m">Altitude (metres)</label>
              <input type="number" step="1" min="-500" max="9000" class="form-control<?= invalid('altitude_m') ?>" id="altitude_m" name="altitude_m" value="<?= e($val('altitude_m')) ?>" placeholder="1585">
              <?= $fieldError('altitude_m') ?>
            </div>
            <div class="col-12">
              <div class="form-hint">Tip: in Google Maps, right-click the place and click the numbers at the top to copy “latitude, longitude”.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="best_time">Best time to visit</label>
              <input type="text" class="form-control<?= invalid('best_time') ?>" id="best_time" name="best_time" value="<?= e($val('best_time')) ?>" maxlength="200" placeholder="March – October · Tulip season in April">
              <?= $fieldError('best_time') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="distance_note">Distance note</label>
              <input type="text" class="form-control<?= invalid('distance_note') ?>" id="distance_note" name="distance_note" value="<?= e($val('distance_note')) ?>" maxlength="200" placeholder="≈ 50 km · 1.5 – 2 hrs from Srinagar">
              <?= $fieldError('distance_note') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Highlights / things to do -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Highlights</h3></div>
        <div class="card-body">
          <p class="form-hint mt-0">Short names of the best sights, e.g. “Dal Lake”.</p>
          <?= partial('admin/destinations/_list_repeater', ['name' => 'highlights', 'items' => $highlights, 'placeholder' => 'Highlight', 'addLabel' => 'Add highlight']) ?>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Things to do</h3></div>
        <div class="card-body">
          <p class="form-hint mt-0">Activities travellers can enjoy, e.g. “Sunrise shikara ride to the floating market”.</p>
          <?= partial('admin/destinations/_list_repeater', ['name' => 'things', 'items' => $things, 'placeholder' => 'Thing to do', 'addLabel' => 'Add thing to do']) ?>
        </div>
      </div>

      <!-- Gallery -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Photo gallery</h3></div>
        <div class="card-body">
          <p class="form-hint mt-0">Extra photos shown on the destination page. Add a row, choose a photo, and use the arrows to set the order.</p>
          <div data-repeater>
            <div data-repeater-items>
              <?php foreach ($gallery as $i => $path): ?>
                <?= $galleryRow((string) $path, $i + 1) ?>
              <?php endforeach; ?>
            </div>
            <template data-repeater-template><?= $galleryRow('', 0) ?></template>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-repeater-add><?= icon('photo') ?> Add gallery photo</button>
            <?php if ($err = error_for('gallery')): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <!-- Publish -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Publish</h3></div>
        <div class="card-body">
          <label class="form-label" for="status">Status</label>
          <select class="form-select<?= invalid('status') ?>" id="status" name="status">
            <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published — show on the website</option>
            <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft — hidden from the website</option>
          </select>
          <?= $fieldError('status') ?>
          <div class="form-hint mt-1">A main photo is needed before a destination can be published.</div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Add destination' ?></button>
          <a href="<?= e(url('/destinations')) ?>" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>

      <!-- Photos -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Photos</h3></div>
        <div class="card-body">
          <?= partial('admin/destinations/_image_field', [
              'name' => 'image', 'value' => $val('image'), 'label' => 'Main photo', 'required' => true,
              'hint' => 'Wide landscape photo, at least 1600 px across. JPG, PNG or WebP up to 8 MB.',
          ]) ?>
          <?= partial('admin/destinations/_image_field', [
              'name' => 'card_image', 'value' => $val('card_image'), 'label' => 'Card photo (optional)', 'required' => false,
              'hint' => 'A brighter photo for the big featured card on the home page. Leave empty to use the main photo.',
          ]) ?>
          <label class="form-label" for="credit">Photo credit</label>
          <input type="text" class="form-control<?= invalid('credit') ?>" id="credit" name="credit" value="<?= e($val('credit')) ?>" maxlength="120" placeholder="e.g. Unsplash">
          <?= $fieldError('credit') ?>
        </div>
      </div>
    </div>
  </div>
</form>
