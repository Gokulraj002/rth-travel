<?php
/**
 * Business details form.
 * @var array<string,string> $values  current values (config/business.php keys)
 * @var array<string,string> $states  GST code => state name
 * @var array<string,string> $previews document label => next number
 */
$field = static function (string $name, string $label, array $opts = []) use ($values): string {
    $type = $opts['type'] ?? 'text';
    $value = (string) old($name, $values[$name] ?? '');
    $id = 'b-' . $name;
    $attrs = '';
    foreach (['placeholder', 'inputmode', 'maxlength', 'autocomplete', 'step', 'min', 'max', 'pattern'] as $a) {
        if (isset($opts[$a])) {
            $attrs .= ' ' . $a . '="' . e((string) $opts[$a]) . '"';
        }
    }
    if (!empty($opts['upper'])) {
        $attrs .= ' data-acc-upper';
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
$currentState = (string) old('company_state', $values['company_state'] ?? '');
$currentCode = (string) ($values['company_state_code'] ?? '');
foreach ($states as $code => $name) {
    if (strcasecmp($name, $currentState) === 0) {
        $currentCode = (string) $code;
    }
}
?>
<form method="post" action="<?= e(url('/business')) ?>" class="rth-business" novalidate>
  <?= csrf_field() ?>
  <?= method_field('PUT') ?>

  <div class="row row-cards">
    <div class="col-lg-7">
      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title"><?= icon('building', 'icon me-1') ?> Company &amp; GST</h3>
            <div class="card-subtitle">Exactly as on your GST registration certificate.</div>
          </div>
        </div>
        <div class="card-body">
          <?= $field('company_legal_name', 'Legal business name', ['required' => true, 'maxlength' => 160, 'hint' => 'The name printed at the top of every invoice.']) ?>
          <div class="row">
            <div class="col-md-7"><?= $field('company_gstin', 'GSTIN', ['maxlength' => 15, 'upper' => true, 'placeholder' => '01ABCDE1234F1Z5', 'autocomplete' => 'off', 'hint' => '15 characters. Leave blank if you are not GST-registered yet.']) ?></div>
            <div class="col-md-5"><?= $field('company_pan', 'PAN', ['maxlength' => 10, 'upper' => true, 'placeholder' => 'ABCDE1234F', 'autocomplete' => 'off']) ?></div>
          </div>
          <div class="row">
            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label required" for="b-company_state">State</label>
                <select id="b-company_state" name="company_state" class="form-select<?= invalid('company_state') ?>" data-acc-state="#b-company_state_code">
                  <option value="">Choose your state…</option>
                  <?php foreach ($states as $code => $name): ?>
                    <option value="<?= e($name) ?>" data-code="<?= e((string) $code) ?>"<?= strcasecmp($name, $currentState) === 0 ? ' selected' : '' ?>><?= e($name) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($err = error_for('company_state')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
                <div class="form-hint">Decides CGST + SGST (same state) or IGST (other states) on invoices.</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="b-company_state_code">State code</label>
                <input type="text" id="b-company_state_code" class="form-control" value="<?= e($currentCode) ?>" readonly tabindex="-1" aria-describedby="b-state-code-hint">
                <div class="form-hint" id="b-state-code-hint">Filled in automatically.</div>
              </div>
            </div>
          </div>
          <?= $field('company_address', 'Registered address', ['type' => 'textarea', 'rows' => 3, 'counter' => 500, 'placeholder' => "Shop 4, Boulevard Road\nSrinagar, Jammu & Kashmir 190001"]) ?>
          <div class="row">
            <div class="col-md-6"><?= $field('company_phone', 'Phone', ['type' => 'tel', 'inputmode' => 'tel', 'placeholder' => '+91 60060 45211']) ?></div>
            <div class="col-md-6"><?= $field('company_email', 'Email', ['type' => 'email', 'autocomplete' => 'off', 'placeholder' => 'accounts@yourdomain.com']) ?></div>
          </div>
          <?= $field('company_website', 'Website', ['type' => 'url', 'inputmode' => 'url', 'placeholder' => 'https://yourdomain.com']) ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title"><?= icon('wallet', 'icon me-1') ?> Bank &amp; UPI</h3>
            <div class="card-subtitle">Printed on invoices and quotations so customers know where to pay.</div>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6"><?= $field('bank_name', 'Bank name', ['maxlength' => 120, 'placeholder' => 'J&K Bank']) ?></div>
            <div class="col-md-6"><?= $field('bank_branch', 'Branch', ['maxlength' => 120, 'placeholder' => 'Residency Road, Srinagar']) ?></div>
          </div>
          <?= $field('bank_account_name', 'Account holder name', ['maxlength' => 160]) ?>
          <div class="row">
            <div class="col-md-7"><?= $field('bank_account_no', 'Account number', ['inputmode' => 'numeric', 'maxlength' => 30, 'autocomplete' => 'off']) ?></div>
            <div class="col-md-5"><?= $field('bank_ifsc', 'IFSC code', ['maxlength' => 11, 'upper' => true, 'autocomplete' => 'off', 'placeholder' => 'JAKA0SRINGR']) ?></div>
          </div>
          <?= $field('upi_id', 'UPI ID', ['maxlength' => 80, 'autocomplete' => 'off', 'placeholder' => 'yourbusiness@okaxis']) ?>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title"><?= icon('file-text', 'icon me-1') ?> Documents</h3>
            <div class="card-subtitle">Tax, numbering and the small print.</div>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-6"><?= $field('tax_percent_default', 'Default GST %', ['type' => 'number', 'step' => '0.01', 'min' => '0', 'max' => '28', 'inputmode' => 'decimal', 'required' => true, 'hint' => 'Tour operators: usually 5%.']) ?></div>
            <div class="col-6"><?= $field('invoice_hsn_sac', 'SAC code', ['inputmode' => 'numeric', 'maxlength' => 8, 'required' => true, 'hint' => 'Tour operator: 998555']) ?></div>
          </div>

          <div class="form-label">Number prefixes</div>
          <div class="row g-2">
            <?php foreach (['quotation_prefix' => 'Quotation', 'booking_prefix' => 'Booking', 'invoice_prefix' => 'Invoice', 'receipt_prefix' => 'Receipt'] as $k => $lbl): ?>
              <div class="col-6 col-sm-3 col-lg-6 col-xl-3">
                <label class="form-label small text-secondary mb-1" for="b-<?= e($k) ?>"><?= e($lbl) ?></label>
                <input type="text" id="b-<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e((string) old($k, $values[$k] ?? '')) ?>" class="form-control<?= invalid($k) ?>" maxlength="6" data-acc-upper autocomplete="off" required>
                <?php if ($err = error_for($k)): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-hint mb-3">Letters and numbers only, up to 6. Numbers restart from 0001 every calendar year.</div>

          <div class="rth-acc-preview mb-3">
            <div class="small text-secondary mb-1">Next numbers</div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($previews as $lbl => $no): ?>
                <span class="badge bg-secondary-lt" title="Next <?= e(strtolower($lbl)) ?> number"><?= e($lbl) ?>: <strong><?= e($no) ?></strong></span>
              <?php endforeach; ?>
            </div>
            <div class="form-hint">A changed prefix applies to the next document you create.</div>
          </div>

          <?= $field('quotation_validity_days', 'Quotations valid for (days)', ['type' => 'number', 'min' => '1', 'max' => '365', 'inputmode' => 'numeric', 'required' => true]) ?>
          <?= $field('quotation_terms', 'Quotation terms', ['type' => 'textarea', 'rows' => 4, 'counter' => 3000, 'hint' => 'One point per line. Leave blank to use the standard terms.']) ?>
          <?= $field('invoice_terms', 'Invoice terms', ['type' => 'textarea', 'rows' => 3, 'counter' => 3000, 'hint' => 'One point per line.']) ?>
          <?= $field('signature_name', 'Signature name', ['maxlength' => 120, 'placeholder' => 'Authorised Signatory', 'hint' => 'Printed under "For ' . ($values['company_legal_name'] ?? '') . '".']) ?>
        </div>
      </div>
    </div>
  </div>

  <div class="rth-sticky-actions d-flex justify-content-end gap-2 py-3">
    <a href="<?= e(url('/')) ?>" class="btn btn-ghost-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save business details</button>
  </div>
</form>
