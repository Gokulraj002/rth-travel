<?php
/**
 * @var array<string,array{label:string,desc:string,count:int}> $exports
 * @var list<array{name:string,rows:int,bytes:int}> $tables
 * @var array{created_at:string,admin_name:?string}|null $lastBackup
 */
use App\Repositories\ActivityRepository;

$totalRows = array_sum(array_column($tables, 'rows'));
$totalBytes = array_sum(array_column($tables, 'bytes'));
$size = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
?>
<div class="rth-tools-backup">
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <span class="rth-backup-icon"><?= icon('database') ?></span>
            <div class="flex-fill">
              <h3 class="mb-1">Full database backup</h3>
              <p class="text-secondary mb-2">
                One <code>.sql</code> file with everything in this admin panel — website content, enquiries, customers, quotations,
                bookings, payments, invoices, settings and team accounts. Keep it somewhere safe (Google Drive, a USB stick).
              </p>
              <p class="small mb-3">
                <?= count($tables) ?> tables · <?= number_format($totalRows) ?> records · about <?= e($size($totalBytes)) ?>
                <?php if ($lastBackup): ?>
                  <br><span class="text-secondary">Last backup: <span title="<?= e(nice_date($lastBackup['created_at'])) ?>"><?= e(ActivityRepository::ago($lastBackup['created_at'])) ?></span><?= $lastBackup['admin_name'] ? ' by ' . e($lastBackup['admin_name']) : '' ?></span>
                <?php else: ?>
                  <br><span class="text-warning">No backup downloaded yet.</span>
                <?php endif; ?>
              </p>
              <form method="post" action="<?= e(url('/backup/database')) ?>" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"><?= icon('download') ?> Download full database backup (.sql)</button>
              </form>
              <div class="alert alert-warning mt-3 mb-0 small d-flex gap-2">
                <?= icon('shield') ?>
                <div>The file includes team sign-in details (passwords are stored scrambled as “hashes”, never in plain text) and your customers’ personal details. Do not share it or email it to anyone.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('file-text') ?> Excel-ready exports (CSV)</h3></div>
        <div class="list-group list-group-flush">
          <?php foreach ($exports as $key => $x): ?>
            <div class="list-group-item d-flex align-items-center gap-3 flex-wrap">
              <div class="flex-fill">
                <div class="fw-semibold"><?= e($x['label']) ?> <span class="badge bg-secondary-lt ms-1"><?= number_format($x['count']) ?></span></div>
                <div class="small text-secondary"><?= e($x['desc']) ?></div>
              </div>
              <a href="<?= e(url('/backup/export/' . $key)) ?>" class="btn btn-sm"><?= icon('download') ?> Download CSV</a>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="card-footer small text-secondary">Opens directly in Excel or Google Sheets. Every download is recorded in the activity log.</div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= icon('refresh') ?> How to restore a backup</h3></div>
        <div class="card-body">
          <ol class="rth-restore-steps mb-3">
            <li>Log in to <strong>Hostinger hPanel</strong> and open <strong>Databases → phpMyAdmin</strong>.</li>
            <li>Click <strong>Enter phpMyAdmin</strong> next to this website’s database and select it in the left column.</li>
            <li>Open the <strong>Import</strong> tab, click <strong>Choose file</strong> and pick your <code>rth-backup-….sql</code> file.</li>
            <li>Leave the other options as they are and click <strong>Import</strong> (or <strong>Go</strong>). Wait for the green success message.</li>
          </ol>
          <div class="alert alert-danger small mb-2 d-flex gap-2">
            <?= icon('alert') ?>
            <div>Restoring <strong>replaces</strong> everything in the database with the backup — anything added after the backup was taken is lost. Download a fresh backup first.</div>
          </div>
          <p class="small text-secondary mb-0">Not sure? Ask your developer or Hostinger support — they can restore it for you in a few minutes.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title">What’s inside</h3></div>
        <div class="table-responsive rth-backup-tables">
          <table class="table table-sm table-vcenter card-table">
            <thead><tr><th>Table</th><th class="text-end">Records</th></tr></thead>
            <tbody>
              <?php foreach ($tables as $t): ?>
                <tr>
                  <td><code><?= e($t['name']) ?></code><?php if ($t['name'] === 'login_attempts'): ?> <span class="small text-secondary">(structure only)</span><?php endif; ?></td>
                  <td class="text-end"><?= number_format($t['rows']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
