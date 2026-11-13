<?php
use App\Core\Flash;

foreach (Flash::pull() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible" role="<?= $f['type'] === 'danger' ? 'alert' : 'status' ?>">
    <div><?= e($f['message']) ?></div>
    <a class="btn-close" data-bs-dismiss="alert" aria-label="Close"></a>
  </div>
<?php endforeach;
