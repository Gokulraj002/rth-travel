<?php
/** @var array<string,string> $settings */
$field = static function (string $name, string $label, array $opts = []) use ($settings): string {
    $type = $opts['type'] ?? 'text';
    $value = (string) old($name, $settings[$name] ?? '');
    $id = 's-' . $name;
    $attrs = '';
    foreach (['placeholder', 'inputmode', 'maxlength', 'autocomplete'] as $a) {
        if (isset($opts[$a])) {
            $attrs .= ' ' . $a . '="' . e((string) $opts[$a]) . '"';
        }
    }
    $html = '<div class="mb-3"><label class="form-label' . (!empty($opts['required']) ? ' required' : '') . '" for="' . e($id) . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        $html .= '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="' . (int) ($opts['rows'] ?? 3) . '" class="form-control' . invalid($name) . '"' . $attrs
            . (isset($opts['counter']) ? ' data-counter="' . (int) $opts['counter'] . '"' : '') . '>' . e($value) . '</textarea>';
    } else {
        $html .= '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '" class="form-control' . invalid($name) . '"' . $attrs . '>';
    }
    if ($err = error_for($name)) {
        $html .= '<div class="invalid-feedback">' . e($err) . '</div>';
    }
    if (!empty($opts['hint'])) {
        $html .= '<div class="form-hint">' . e($opts['hint']) . '</div>';
    }
    return $html . '</div>';
};
?>
<form method="post" action="<?= e(url('/settings')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= method_field('PUT') ?>

  <div class="row row-cards">
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title">Business</h3>
            <div class="card-subtitle">Your name and how you describe yourselves.</div>
          </div>
        </div>
        <div class="card-body">
          <?= $field('site_name', 'Business name', ['required' => true, 'maxlength' => 120]) ?>
          <?= $field('tagline', 'Tagline', ['maxlength' => 200, 'placeholder' => 'Explore • Travel • Create Memories']) ?>
          <?= $field('description', 'Short description', ['type' => 'textarea', 'rows' => 4, 'counter' => 1000, 'hint' => 'Used in the footer and when your site is shared on Google / WhatsApp.']) ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title">Social media</h3>
            <div class="card-subtitle">Leave blank to hide an icon on the website.</div>
          </div>
        </div>
        <div class="card-body">
          <?= $field('instagram_url', 'Instagram link', ['type' => 'url', 'placeholder' => 'https://instagram.com/yourpage', 'inputmode' => 'url']) ?>
          <?= $field('facebook_url', 'Facebook link', ['type' => 'url', 'placeholder' => 'https://facebook.com/yourpage', 'inputmode' => 'url']) ?>
          <?= $field('youtube_url', 'YouTube link', ['type' => 'url', 'placeholder' => 'https://youtube.com/@yourchannel', 'inputmode' => 'url']) ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title">Contact</h3>
            <div class="card-subtitle">Shown on every page, the contact page and the WhatsApp button.</div>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6"><?= $field('phone_primary', 'Main phone', ['type' => 'tel', 'required' => true, 'placeholder' => '+91 60060 45211', 'inputmode' => 'tel']) ?></div>
            <div class="col-md-6"><?= $field('phone_secondary', 'Second phone', ['type' => 'tel', 'placeholder' => '+91 94197 45211', 'inputmode' => 'tel']) ?></div>
          </div>
          <?= $field('whatsapp_number', 'WhatsApp number', ['required' => true, 'inputmode' => 'numeric', 'placeholder' => '916006045211', 'maxlength' => 15, 'hint' => 'Country code + number, no + (e.g. 916006045211).']) ?>
          <div class="row">
            <div class="col-md-6"><?= $field('email_primary', 'Main email', ['type' => 'email', 'required' => true, 'autocomplete' => 'off']) ?></div>
            <div class="col-md-6"><?= $field('email_secondary', 'Second email', ['type' => 'email', 'autocomplete' => 'off']) ?></div>
          </div>

          <h4 class="mt-2 mb-2">Office address 1</h4>
          <?= $field('address_1_line1', 'Line 1', ['maxlength' => 200]) ?>
          <?= $field('address_1_line2', 'Line 2', ['maxlength' => 200]) ?>

          <h4 class="mt-2 mb-2">Office address 2 <span class="text-secondary fw-normal small">(optional)</span></h4>
          <?= $field('address_2_line1', 'Line 1', ['maxlength' => 200]) ?>
          <?= $field('address_2_line2', 'Line 2', ['maxlength' => 200]) ?>

          <?= $field('map_query', 'Map location', ['maxlength' => 200, 'placeholder' => 'Nowgam Bypass Chowk, Srinagar 190015', 'hint' => 'What to search on Google Maps for the map on your contact page.']) ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">
      <span class="text-secondary me-auto">Changes appear on the website within a few seconds of saving.</span>
      <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save settings</button>
    </div>
  </div>
</form>
