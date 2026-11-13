<?php
/**
 * Shared list footer: "Showing x–y of z" + pager. Used by destinations, testimonials and faqs.
 * @var array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, perPage:int} $result
 * @var string $basePath e.g. '/faqs'
 * @var array<string,string> $query current filters (q, status)
 */
$query = array_filter($query ?? [], static fn ($v) => $v !== '' && $v !== null);
$link = static fn (int $p): string => url($basePath) . '?' . http_build_query([...$query, 'page' => $p]);
$from = $result['total'] === 0 ? 0 : ($result['page'] - 1) * $result['perPage'] + 1;
$to = min($result['total'], $result['page'] * $result['perPage']);
?>
<div class="card-footer d-flex align-items-center flex-wrap gap-2">
  <p class="m-0 text-secondary small">Showing <?= e($from) ?>–<?= e($to) ?> of <?= e($result['total']) ?></p>
  <?php if ($result['pages'] > 1): ?>
    <ul class="pagination m-0 ms-auto">
      <li class="page-item<?= $result['page'] <= 1 ? ' disabled' : '' ?>">
        <a class="page-link" href="<?= e($link(max(1, $result['page'] - 1))) ?>">‹ Prev</a>
      </li>
      <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
        <li class="page-item<?= $p === $result['page'] ? ' active' : '' ?>">
          <a class="page-link" href="<?= e($link($p)) ?>"><?= e($p) ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item<?= $result['page'] >= $result['pages'] ? ' disabled' : '' ?>">
        <a class="page-link" href="<?= e($link(min($result['pages'], $result['page'] + 1))) ?>">Next ›</a>
      </li>
    </ul>
  <?php endif; ?>
</div>
