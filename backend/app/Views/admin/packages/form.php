<?php
/**
 * Create / edit a tour package.
 * @var array<string,mixed>|null $package  (with children: categories, destinations, highlights, inclusions, exclusions, itinerary)
 * @var list<array{slug:string,name:string,region:string,status:string}> $destinations
 * @var array<string,string> $regions
 * @var array<string,string> $categories
 * @var array<string,string> $statuses
 */
$p = $package ?? [];
$isEdit = $package !== null;
$id = $isEdit ? (int) $p['id'] : 0;
// After a failed submit every field (including repeaters and unchecked boxes) comes from old input.
$submitted = old('_form', '') === 'package';
$val = static fn (string $k, mixed $default = ''): string => (string) (is_scalar($o = old($k, $p[$k] ?? $default)) ? $o : $default);
$list = static function (string $k) use ($submitted, $p): array {
    $v = $submitted ? old($k, []) : ($p[$k] ?? []);
    return is_array($v) ? array_values(array_filter($v, 'is_scalar')) : [];
};

$status = $val('status', 'draft');
$featured = $submitted ? old('featured', '') === '1' : (int) ($p['featured'] ?? 0) === 1;
$selectedCats = array_map('strval', $list('categories'));
$selectedDests = array_map('strval', $list('destinations'));
$image = $val('image');

$itinerary = [];
$rawItin = $submitted ? old('itinerary', []) : ($p['itinerary'] ?? []);
foreach (is_array($rawItin) ? $rawItin : [] as $d) {
    if (is_array($d)) {
        $itinerary[] = [
            'title' => is_scalar($d['title'] ?? null) ? (string) $d['title'] : '',
            'description' => is_scalar($d['description'] ?? null) ? (string) $d['description'] : '',
            'stay' => is_scalar($d['stay'] ?? null) ? (string) $d['stay'] : '',
        ];
    }
}
if ($itinerary === []) {
    $itinerary[] = ['title' => '', 'description' => '', 'stay' => ''];
}
if ($selectedDests === []) {
    $selectedDests = [''];
}
$knownDests = array_column($destinations, 'slug');

/** Destination row (select + up/down/remove). */
$destRow = static function (string $selected) use ($destinations, $knownDests, $regions): string {
    ob_start(); ?>
  <div class="rth-repeater-item d-flex gap-2 align-items-center">
    <span class="rth-repeater-number" data-repeater-number>1</span>
    <select class="form-select" name="destinations[]" aria-label="Destination">
      <option value="">Choose a destination…</option>
      <?php foreach ($regions as $rKey => $rLabel): ?>
        <optgroup label="<?= e($rLabel) ?>">
          <?php foreach ($destinations as $d): if ($d['region'] !== $rKey) { continue; } ?>
            <option value="<?= e($d['slug']) ?>"<?= $selected === $d['slug'] ? ' selected' : '' ?>><?= e($d['name']) ?><?= $d['status'] === 'draft' ? ' (draft)' : '' ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
      <?php if ($selected !== '' && !in_array($selected, $knownDests, true)): ?>
        <option value="<?= e($selected) ?>" selected><?= e($selected) ?> (no longer exists — please change)</option>
      <?php endif; ?>
    </select>
    <div class="btn-list flex-nowrap">
      <button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-up title="Move up" aria-label="Move up"><?= icon('arrow-up') ?></button>
      <button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-down title="Move down" aria-label="Move down"><?= icon('arrow-down') ?></button>
      <button type="button" class="btn btn-icon btn-ghost-danger" data-repeater-remove title="Remove" aria-label="Remove"><?= icon('x') ?></button>
    </div>
  </div>
<?php return (string) ob_get_clean();
};

/** Itinerary day row. */
$dayRow = static function (string $index, array $d): string {
    ob_start(); ?>
  <div class="rth-repeater-item">
    <div class="d-flex align-items-center gap-2 mb-2">
      <span class="badge bg-primary-lt fs-5">Day <span data-repeater-number>1</span></span>
      <div class="btn-list flex-nowrap ms-auto">
        <button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-up title="Move day up" aria-label="Move day up"><?= icon('arrow-up') ?></button>
        <button type="button" class="btn btn-icon btn-ghost-secondary" data-repeater-down title="Move day down" aria-label="Move day down"><?= icon('arrow-down') ?></button>
        <button type="button" class="btn btn-icon btn-ghost-danger" data-repeater-remove title="Remove this day" aria-label="Remove this day"><?= icon('trash') ?></button>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-md-8">
        <label class="form-label small mb-1">Title</label>
        <input type="text" class="form-control" name="itinerary[<?= e($index) ?>][title]" value="<?= e($d['title']) ?>" maxlength="200" placeholder="e.g. Arrive Srinagar · Dal Lake shikara">
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1">Overnight stay <span class="text-secondary">(optional)</span></label>
        <input type="text" class="form-control" name="itinerary[<?= e($index) ?>][stay]" value="<?= e($d['stay']) ?>" maxlength="120" placeholder="e.g. Srinagar">
      </div>
      <div class="col-12">
        <label class="form-label small mb-1">What happens on this day</label>
        <textarea class="form-control" name="itinerary[<?= e($index) ?>][description]" rows="3" maxlength="5000" placeholder="Pick-up, sightseeing, drive times, meals…"><?= e($d['description']) ?></textarea>
      </div>
    </div>
  </div>
<?php return (string) ob_get_clean();
};

$err = static fn (string $k): string => ($m = error_for($k)) ? '<div class="invalid-feedback d-block">' . e($m) . '</div>' : '';
?>
<form method="post" action="<?= e(url($isEdit ? '/packages/' . $id : '/packages')) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
  <input type="hidden" name="_form" value="package">

  <div class="row g-3">
    <!-- ── Main column ─────────────────────────────────────────────────── -->
    <div class="col-lg-8">

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Basics</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label required" for="pkg-title">Package name</label>
            <input type="text" id="pkg-title" name="title" class="form-control<?= invalid('title') ?>" value="<?= e($val('title')) ?>" maxlength="160" required placeholder="e.g. Kashmir Paradise">
            <?= $err('title') ?>
          </div>
          <div class="mb-3">
            <label class="form-label required" for="pkg-slug">Web address</label>
            <div class="input-group">
              <span class="input-group-text">/packages/</span>
              <input type="text" id="pkg-slug" name="slug" class="form-control<?= invalid('slug') ?>" value="<?= e($val('slug')) ?>" maxlength="120" required data-slug-from="#pkg-title" placeholder="kashmir-paradise">
            </div>
            <?= $err('slug') ?>
            <div class="form-hint">Filled in automatically from the name. Lowercase letters, numbers and hyphens only.<?= $isEdit ? ' Changing it breaks old links to this package.' : '' ?></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="pkg-subtitle">Short description</label>
            <input type="text" id="pkg-subtitle" name="subtitle" class="form-control<?= invalid('subtitle') ?>" value="<?= e($val('subtitle')) ?>" maxlength="255" data-counter="255" placeholder="e.g. Srinagar, Sonamarg, Gulmarg &amp; Pahalgam in one easy loop">
            <?= $err('subtitle') ?>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required" for="pkg-region">Region</label>
              <select id="pkg-region" name="region" class="form-select<?= invalid('region') ?>" required>
                <option value="">Choose…</option>
                <?php foreach ($regions as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $val('region') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= $err('region') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label required" for="pkg-price">Starting price</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" id="pkg-price" name="price_from" class="form-control<?= invalid('price_from') ?>" value="<?= e($val('price_from')) ?>" min="0" max="10000000" step="1" inputmode="numeric" required placeholder="15999">
              </div>
              <?= $err('price_from') ?>
              <div class="form-hint">Per person, starting price.</div>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label required" for="pkg-nights">Nights</label>
              <input type="number" id="pkg-nights" name="nights" class="form-control<?= invalid('nights') ?>" value="<?= e($val('nights')) ?>" min="0" max="60" step="1" required placeholder="5">
              <?= $err('nights') ?>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label required" for="pkg-days">Days</label>
              <input type="number" id="pkg-days" name="days" class="form-control<?= invalid('days') ?>" value="<?= e($val('days')) ?>" min="1" max="61" step="1" required placeholder="6">
              <?= $err('days') ?>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-end">
              <div class="form-hint mb-2">Days are usually nights + 1 (5 nights = 6 days).</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title">Trip types</h3>
            <div class="card-subtitle text-secondary small">Used for the filters on the website. Tick all that fit.</div>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <?php foreach ($categories as $k => $label): ?>
              <div class="col-6 col-md-4">
                <label class="form-check">
                  <input class="form-check-input" type="checkbox" name="categories[]" value="<?= e($k) ?>"<?= in_array($k, $selectedCats, true) ? ' checked' : '' ?>>
                  <span class="form-check-label"><?= e($label) ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title">Destinations covered</h3>
            <div class="card-subtitle text-secondary small">In the order the guests visit them. Use the arrows to change the order.</div>
          </div>
        </div>
        <div class="card-body" data-repeater>
          <div data-repeater-items>
            <?php foreach ($selectedDests as $slug): ?>
              <?= $destRow((string) $slug) ?>
            <?php endforeach; ?>
          </div>
          <template data-repeater-template><?= $destRow('') ?></template>
          <?= $err('destinations') ?>
          <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-repeater-add><?= icon('plus') ?> Add destination</button>
        </div>
      </div>

      <?= partial('admin/packages/_list-repeater', [
          'name' => 'highlights', 'title' => 'Highlights', 'addLabel' => 'Add highlight',
          'hint' => 'The best moments of the trip — shown as a bullet list.',
          'placeholder' => 'e.g. Shikara ride on Dal Lake at sunset', 'items' => array_map('strval', $list('highlights')),
      ]) ?>
      <?= partial('admin/packages/_list-repeater', [
          'name' => 'inclusions', 'title' => 'What’s included', 'addLabel' => 'Add inclusion',
          'hint' => 'Hotels, meals, transport, sightseeing…',
          'placeholder' => 'e.g. Daily breakfast & dinner', 'items' => array_map('strval', $list('inclusions')),
      ]) ?>
      <?= partial('admin/packages/_list-repeater', [
          'name' => 'exclusions', 'title' => 'Not included', 'addLabel' => 'Add exclusion',
          'hint' => 'Things guests pay for separately.',
          'placeholder' => 'e.g. Airfare / train tickets', 'items' => array_map('strval', $list('exclusions')),
      ]) ?>

      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title">Day-by-day itinerary</h3>
            <div class="card-subtitle text-secondary small">Days are numbered automatically in the order shown.</div>
          </div>
        </div>
        <div class="card-body" data-repeater>
          <?= $err('itinerary') ?>
          <div data-repeater-items class="mt-1">
            <?php foreach ($itinerary as $i => $d): ?>
              <?= $dayRow((string) $i, $d) ?>
            <?php endforeach; ?>
          </div>
          <template data-repeater-template><?= $dayRow('__INDEX__', ['title' => '', 'description' => '', 'stay' => '']) ?></template>
          <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-repeater-add><?= icon('plus') ?> Add day</button>
        </div>
      </div>

      <div class="d-lg-none mb-3">
        <button type="submit" class="btn btn-primary w-100"><?= icon('check') ?> Save package</button>
      </div>
    </div>

    <!-- ── Side column ─────────────────────────────────────────────────── -->
    <div class="col-lg-4">
      <div class="card mb-3 sticky-lg-top" style="top: 1rem; z-index: 2;">
        <div class="card-header"><h3 class="card-title">Publish</h3></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="pkg-status">Visibility</label>
            <select id="pkg-status" name="status" class="form-select<?= invalid('status') ?>">
              <?php foreach ($statuses as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($k === 'published' ? 'Published — visible on the website' : 'Draft — hidden from the website') ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
          </div>
          <div class="mb-3">
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="featured" value="1"<?= $featured ? ' checked' : '' ?>>
              <span class="form-check-label">Featured on the home page</span>
            </label>
          </div>
          <button type="submit" class="btn btn-primary w-100"><?= icon('check') ?> Save package</button>
          <?php if ($isEdit): ?>
            <div class="small text-secondary mt-2 text-center">Last saved <?= e(nice_date((string) ($p['updated_at'] ?? ''))) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title">Main photo</h3>
            <div class="card-subtitle text-secondary small">Required to publish. Landscape JPG, PNG or WebP, up to 8 MB.</div>
          </div>
        </div>
        <div class="card-body">
          <div class="rth-image-field<?= error_for('image') ? ' border-danger' : '' ?>" data-image-field>
            <img data-image-preview src="<?= e(media_url($image)) ?>" alt="Main photo preview" class="mb-2">
            <input type="hidden" name="image" value="<?= e($image) ?>" data-image-input>
            <input type="file" class="form-control mb-2" accept="image/jpeg,image/png,image/webp" data-image-file aria-label="Upload a photo">
            <button type="button" class="btn btn-sm btn-outline-danger" data-image-clear><?= icon('x') ?> Remove photo</button>
          </div>
          <?= $err('image') ?>
        </div>
      </div>

      <?php if ($isEdit): ?>
        <div class="card mb-3 border-danger-subtle">
          <div class="card-header"><h3 class="card-title text-danger">Danger zone</h3></div>
          <div class="card-body">
            <p class="text-secondary small mb-2">Deleting removes this package from the admin and the website for good. To hide it for now, set it to Draft instead.</p>
            <button type="submit" form="pkg-delete-form" class="btn btn-outline-danger w-100"><?= icon('trash') ?> Delete package</button>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if ($isEdit): ?>
  <form id="pkg-delete-form" method="post" action="<?= e(url('/packages/' . $id)) ?>" data-confirm="Delete “<?= e($p['title']) ?>” for good? This cannot be undone.">
    <?= csrf_field() ?>
    <?= method_field('DELETE') ?>
  </form>
<?php endif; ?>
