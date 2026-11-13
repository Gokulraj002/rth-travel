<?php
/**
 * Reports with date range + tabs.
 * @var array{preset:string,from:string,to:string,label:string} $range
 * @var array<string,string> $tabs
 * @var string $tab
 * @var array<string,mixed> $report
 */
use App\Controllers\Admin\ReportController;
use App\Support\Chart;

$rangeQs = ['preset' => $range['preset']] + ($range['preset'] === 'custom' ? ['from' => $range['from'], 'to' => $range['to']] : []);
$tabUrl = static fn (string $t): string => url('/reports') . '?' . http_build_query(['tab' => $t] + $rangeQs);
$csvUrl = url('/reports/export/' . $tab) . '?' . http_build_query($rangeQs);
$short = static fn (string $ym): string => date('M y', (int) strtotime($ym . '-01'));
$pct = static fn (?float $f): string => $f === null ? '—' : rtrim(rtrim(number_format($f * 100, 1), '0'), '.') . '%';
$kpi = static function (string $label, string $value, string $ic, string $hint = '') {
    return '<div class="col-6 col-lg-3"><div class="card card-sm rth-rep-kpi"><div class="card-body d-flex align-items-center gap-3">'
        . '<span class="avatar bg-primary-lt">' . icon($ic) . '</span><div><div class="text-secondary small">' . e($label) . '</div>'
        . '<div class="h2">' . e($value) . '</div>' . ($hint !== '' ? '<div class="small text-secondary">' . e($hint) . '</div>' : '') . '</div></div></div></div>';
};
$empty = static function (string $title, string $text, string $ic = 'chart', string $action = '') {
    return '<div class="card"><div class="empty"><div class="empty-icon">' . icon($ic, 'icon icon-lg') . '</div><p class="empty-title">' . e($title)
        . '</p><p class="empty-subtitle text-secondary">' . e($text) . '</p>' . ($action !== '' ? '<div class="empty-action">' . $action . '</div>' : '') . '</div></div>';
};
$crm = config('crm');
?>
<div class="rth-reports">
  <form method="get" action="<?= e(url('/reports')) ?>" class="card mb-3" data-acc-range>
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="card-body row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label" for="rep-preset">Period</label>
        <select id="rep-preset" name="preset" class="form-select" data-acc-preset>
          <?php foreach (ReportController::PRESETS as $k => $lbl): ?>
            <option value="<?= e($k) ?>"<?= $range['preset'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3" data-acc-custom>
        <label class="form-label" for="rep-from">From</label>
        <input type="date" id="rep-from" name="from" class="form-control" value="<?= e($range['from']) ?>">
      </div>
      <div class="col-6 col-md-3" data-acc-custom>
        <label class="form-label" for="rep-to">To</label>
        <input type="date" id="rep-to" name="to" class="form-control" value="<?= e($range['to']) ?>">
      </div>
      <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100"><?= icon('filter') ?> Apply</button></div>
    </div>
    <div class="card-footer py-2 small text-secondary d-flex flex-wrap gap-2 align-items-center">
      <span><?= icon('calendar', 'icon icon-sm') ?> <?= e($tab === 'outstanding' ? 'As of today, ' . nice_day(date('Y-m-d')) . ' (not limited by the period)' : $range['label']) ?></span>
      <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e($csvUrl) ?>"><?= icon('download') ?> Download CSV</a>
    </div>
  </form>

  <ul class="nav nav-tabs mb-3" role="tablist">
    <?php foreach ($tabs as $k => $lbl): ?>
      <li class="nav-item"><a class="nav-link<?= $k === $tab ? ' active' : '' ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?> href="<?= e($tabUrl($k)) ?>"><?= e($lbl) ?></a></li>
    <?php endforeach; ?>
  </ul>

<?php if ($tab === 'sales'): ?>
  <div class="row row-cards mb-3">
    <?= $kpi('Bookings', (string) $report['count'], 'briefcase') ?>
    <?= $kpi('Booking value', inr($report['value']), 'rupee') ?>
    <?= $kpi('Collected', inr($report['collected']), 'cash', 'Payments received') ?>
    <?= $kpi('Average booking', inr($report['avg']), 'trending-up') ?>
  </div>
  <?php if ($report['count'] === 0 && $report['collected'] == 0): ?>
    <?= $empty('No sales in this period', 'Bookings and payments recorded in this period will show up here. Try a longer period.', 'chart',
        feature('bookings') && can('bookings') ? '<a class="btn btn-primary" href="' . e(url('/bookings')) . '">Go to bookings</a>' : '') ?>
  <?php else: ?>
    <div class="row row-cards">
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Bookings vs collections by month</h3></div>
          <div class="card-body">
            <?= Chart::bars(array_map($short, array_column($report['months'], 'month')), array_column($report['months'], 'value'),
                ['money' => true, 'series2' => array_column($report['months'], 'collected'), 'names' => ['Booking value', 'Collected']]) ?>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Collections by method</h3></div>
          <div class="card-body">
            <?php if ($report['methods'] === []): ?>
              <p class="text-secondary mb-0">No payments in this period.</p>
            <?php else: ?>
              <?= Chart::donut(array_map([ReportController::class, 'methodLabel'], array_keys($report['methods'])), array_values($report['methods']), ['money' => true, 'center' => 'Collected']) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Month</th><th class="text-end">Bookings</th><th class="text-end">Booking value</th><th class="text-end">Collected</th></tr></thead>
          <tbody>
            <?php foreach ($report['months'] as $m): ?>
              <tr><td><?= e(ReportController::monthLabel($m['month'])) ?></td><td class="text-end"><?= e((string) $m['bookings']) ?></td>
                  <td class="text-end"><?= e(inr($m['value'])) ?></td><td class="text-end"><?= e(inr($m['collected'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end"><?= e((string) $report['count']) ?></td><td class="text-end"><?= e(inr($report['value'])) ?></td><td class="text-end"><?= e(inr($report['collected'])) ?></td></tr></tfoot>
        </table>
      </div>
      <div class="card-footer small text-secondary">Booking value counts bookings by the date they were made (cancelled bookings left out). Collections count payments by the date received.</div>
    </div>
  <?php endif; ?>

<?php elseif ($tab === 'outstanding'):
    $rows = $report['rows'];
    $due = 0.0;
    foreach ($rows as $b) {
        $due += (float) $b['total_amount'] - (float) $b['paid'];
    }
?>
  <div class="row row-cards mb-3">
    <?= $kpi('Total due', inr($due), 'wallet') ?>
    <?= $kpi('Bookings with balance', (string) count($rows), 'briefcase') ?>
  </div>
  <?php if ($rows === []): ?>
    <?= $empty('Nothing outstanding', 'Every active booking is fully paid. Well done!', 'check') ?>
  <?php else: ?>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Travel date</th><th>Booking</th><th>Customer</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance due</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $b):
                $bal = (float) $b['total_amount'] - (float) $b['paid'];
                $soon = strtotime((string) $b['travel_start']) <= strtotime('+7 days');
            ?>
              <tr>
                <td class="text-nowrap"><?= e(nice_day((string) $b['travel_start'])) ?><?php if ($soon): ?> <span class="badge bg-orange-lt" title="Travels within 7 days">Soon</span><?php endif; ?></td>
                <td>
                  <?php if (feature('bookings') && can('bookings')): ?><a href="<?= e(url('/bookings/' . (int) $b['id'])) ?>"><?= e($b['number']) ?></a><?php else: ?><?= e($b['number']) ?><?php endif; ?>
                  <div class="small text-secondary"><?= e(str_limit((string) $b['title'], 45)) ?></div>
                </td>
                <td><?= e($b['customer_name']) ?><div class="small text-secondary"><?= e($b['customer_phone']) ?></div></td>
                <td class="text-end text-nowrap"><?= e(inr($b['total_amount'])) ?></td>
                <td class="text-end text-nowrap"><?= e(inr($b['paid'])) ?></td>
                <td class="text-end text-nowrap fw-bold"><?= e(inr($bal)) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-ghost-success" target="_blank" rel="noopener" title="Send a payment reminder on WhatsApp" aria-label="WhatsApp reminder"
                     href="<?= e(wa_link((string) $b['customer_phone'], 'Hi ' . $b['customer_name'] . ', a gentle reminder: the balance of ' . inr($bal) . ' for your trip ' . $b['title'] . ' (' . $b['number'] . ') starting ' . nice_day((string) $b['travel_start']) . ' is due. Thank you! — ' . setting('company_legal_name'))) ?>"><?= icon('whatsapp') ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot><tr class="fw-bold"><td colspan="5">Total due</td><td class="text-end"><?= e(inr($due)) ?></td><td></td></tr></tfoot>
        </table>
      </div>
    </div>
  <?php endif; ?>

<?php elseif ($tab === 'leads'): ?>
  <div class="row row-cards mb-3">
    <?= $kpi('Enquiries', (string) $report['total'], 'inbox') ?>
    <?= $kpi('Won', (string) $report['won'], 'check') ?>
    <?= $kpi('Lost', (string) $report['lost'], 'x') ?>
    <?= $kpi('Conversion rate', $pct($report['conversion']), 'target', 'Won ÷ (won + lost)') ?>
  </div>
  <?php if ($report['total'] === 0): ?>
    <?= $empty('No enquiries in this period', 'Website, WhatsApp and phone enquiries received in this period will be analysed here.', 'inbox',
        can('enquiries') ? '<a class="btn btn-primary" href="' . e(url('/enquiries')) . '">Open enquiries</a>' : '') ?>
  <?php else: ?>
    <div class="row row-cards">
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Where enquiries come from</h3></div>
          <div class="card-body">
            <?= Chart::donut(array_map(static fn ($k) => $crm['sources'][$k] ?? ucfirst((string) $k), array_keys($report['sources'])), array_values($report['sources']), ['center' => 'Enquiries']) ?>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Pipeline stage</h3></div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <tbody>
                <?php foreach ($crm['stages'] as $k => $st):
                    $n = $report['stages'][$k] ?? 0;
                    $w = $report['total'] > 0 ? round($n / $report['total'] * 100) : 0;
                ?>
                  <tr>
                    <td class="w-25"><?= status_badge($k, $st['label']) ?></td>
                    <td><div class="progress progress-sm"><div class="progress-bar" style="width: <?= (int) $w ?>%" role="progressbar" aria-valuenow="<?= (int) $w ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($st['label']) ?>"></div></div></td>
                    <td class="text-end w-1 fw-semibold"><?= e((string) $n) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

<?php elseif ($tab === 'top'): ?>
  <?php if ($report['packages'] === []): ?>
    <?= $empty('No bookings in this period', 'Your best-selling packages and destinations will appear once bookings are made.', 'package') ?>
  <?php else: ?>
    <div class="row row-cards">
      <?php foreach (['packages' => ['Top packages', 'package'], 'destinations' => ['Top destinations', 'map-pin']] as $key => [$heading, $ic]):
          $list = $report[$key];
          $maxRev = max(1.0, ...array_map(static fn ($r) => $r['revenue'], $list ?: [['revenue' => 1.0]]));
      ?>
        <div class="col-lg-6">
          <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><?= icon($ic, 'icon me-1') ?> <?= e($heading) ?></h3></div>
            <?php if ($list === []): ?>
              <div class="card-body text-secondary">No bookings linked to packages yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-vcenter card-table">
                  <thead><tr><th>#</th><th>Name</th><th class="text-end">Bookings</th><th class="text-end">Revenue</th></tr></thead>
                  <tbody>
                    <?php foreach ($list as $i => $r): ?>
                      <tr>
                        <td class="text-secondary"><?= $i + 1 ?></td>
                        <td><?= e($r['name']) ?><div class="progress progress-sm mt-1"><div class="progress-bar" style="width: <?= (int) round($r['revenue'] / $maxRev * 100) ?>%" role="progressbar" aria-label="Share of revenue"></div></div></td>
                        <td class="text-end"><?= e((string) $r['bookings']) ?></td>
                        <td class="text-end text-nowrap"><?= e(inr($r['revenue'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php if ($key === 'destinations'): ?><div class="card-footer small text-secondary">A booking counts once for every destination in its package.</div><?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php elseif ($tab === 'profit'): ?>
  <div class="row row-cards mb-3">
    <?= $kpi('Revenue', inr($report['revenue']), 'rupee') ?>
    <?= $kpi('Supplier costs', inr($report['cost']), 'building') ?>
    <?= $kpi('Profit', inr($report['profit']), 'trending-up') ?>
    <?= $kpi('Margin', $pct($report['margin']), 'target') ?>
  </div>
  <?php if ($report['bookings'] === []): ?>
    <?= $empty('No bookings in this period', 'Add supplier costs (hotels, cabs, houseboats) on each booking to see your real profit here.', 'trending-up') ?>
  <?php else: ?>
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Revenue vs profit by month</h3></div>
      <div class="card-body">
        <?= Chart::bars(array_map($short, array_column($report['months'], 'month')), array_column($report['months'], 'revenue'),
            ['money' => true, 'series2' => array_map(static fn ($p) => max(0, $p), array_column($report['months'], 'profit')), 'names' => ['Revenue', 'Profit']]) ?>
      </div>
    </div>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Booking</th><th>Customer</th><th class="text-end">Revenue</th><th class="text-end">Supplier cost</th><th class="text-end">Profit</th><th class="text-end">Margin</th></tr></thead>
          <tbody>
            <?php foreach ($report['bookings'] as $b): ?>
              <tr>
                <td>
                  <?php if (feature('bookings') && can('bookings')): ?><a href="<?= e(url('/bookings/' . (int) $b['id'])) ?>"><?= e($b['number']) ?></a><?php else: ?><?= e($b['number']) ?><?php endif; ?>
                  <div class="small text-secondary"><?= e(str_limit((string) $b['title'], 40)) ?> · booked <?= e(nice_day((string) $b['created_at'])) ?></div>
                </td>
                <td><?= e($b['customer_name']) ?></td>
                <td class="text-end text-nowrap"><?= e(inr($b['revenue'])) ?></td>
                <td class="text-end text-nowrap"><?= $b['cost'] > 0 ? e(inr($b['cost'])) : '<span class="text-secondary" title="No supplier costs added yet">—</span>' ?></td>
                <td class="text-end text-nowrap fw-semibold<?= $b['profit'] < 0 ? ' text-loss' : '' ?>"><?= e(($b['profit'] < 0 ? '−' : '') . inr(abs($b['profit']))) ?></td>
                <td class="text-end"><?= e($pct($b['margin'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot><tr class="fw-bold"><td colspan="2">Total</td><td class="text-end"><?= e(inr($report['revenue'])) ?></td><td class="text-end"><?= e(inr($report['cost'])) ?></td>
            <td class="text-end<?= $report['profit'] < 0 ? ' text-loss' : '' ?>"><?= e(($report['profit'] < 0 ? '−' : '') . inr(abs($report['profit']))) ?></td><td class="text-end"><?= e($pct($report['margin'])) ?></td></tr></tfoot>
        </table>
      </div>
      <div class="card-footer small text-secondary">Profit = booking price − supplier costs added on the booking (cancelled services left out). Bookings without costs show 100% margin until you add them.</div>
    </div>
  <?php endif; ?>

<?php elseif ($tab === 'gst'):
    $t = $report['totals'];
?>
  <div class="row row-cards mb-3">
    <?= $kpi('Taxable value', inr2($t['taxable']), 'rupee', $t['invoices'] . ' invoice' . ($t['invoices'] === 1 ? '' : 's')) ?>
    <?= $kpi('CGST', inr2($t['cgst']), 'file-text') ?>
    <?= $kpi('SGST', inr2($t['sgst']), 'file-text') ?>
    <?= $kpi('IGST', inr2($t['igst']), 'file-text') ?>
  </div>
  <?php if ($t['invoices'] === 0): ?>
    <?= $empty('No GST invoices in this period', 'Issued and paid invoices are summarised here month by month, ready for your GST return.', 'receipt',
        can('invoices') ? '<a class="btn btn-primary" href="' . e(url('/invoices/create')) . '">' . icon('plus') . ' New invoice</a>' : '') ?>
  <?php else: ?>
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Month by month</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Month</th><th class="text-end">Invoices</th><th class="text-end">Taxable value</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">IGST</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            <?php foreach ($report['months'] as $m): ?>
              <tr class="<?= $m['invoices'] === 0 ? 'text-secondary' : '' ?>">
                <td><?= e(ReportController::monthLabel($m['month'])) ?></td><td class="text-end"><?= e((string) $m['invoices']) ?></td>
                <td class="text-end text-nowrap"><?= e(inr2($m['taxable'])) ?></td><td class="text-end text-nowrap"><?= e(inr2($m['cgst'])) ?></td>
                <td class="text-end text-nowrap"><?= e(inr2($m['sgst'])) ?></td><td class="text-end text-nowrap"><?= e(inr2($m['igst'])) ?></td>
                <td class="text-end text-nowrap"><?= e(inr2($m['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end"><?= e((string) $t['invoices']) ?></td><td class="text-end text-nowrap"><?= e(inr2($t['taxable'])) ?></td>
            <td class="text-end text-nowrap"><?= e(inr2($t['cgst'])) ?></td><td class="text-end text-nowrap"><?= e(inr2($t['sgst'])) ?></td><td class="text-end text-nowrap"><?= e(inr2($t['igst'])) ?></td><td class="text-end text-nowrap"><?= e(inr2($t['total'])) ?></td></tr></tfoot>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3 class="card-title">By place of supply</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>State</th><th class="text-end">Invoices</th><th class="text-end">Taxable value</th><th class="text-end">CGST + SGST</th><th class="text-end">IGST</th></tr></thead>
          <tbody>
            <?php foreach ($report['states'] as $s): ?>
              <tr><td><?= e($s['state']) ?></td><td class="text-end"><?= e((string) $s['invoices']) ?></td><td class="text-end text-nowrap"><?= e(inr2($s['taxable'])) ?></td>
                <td class="text-end text-nowrap"><?= e(inr2($s['cgst'] + $s['sgst'])) ?></td><td class="text-end text-nowrap"><?= e(inr2($s['igst'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer small text-secondary">Draft and void invoices are not included. Share the CSV with your accountant for GSTR-1 / GSTR-3B.</div>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>
