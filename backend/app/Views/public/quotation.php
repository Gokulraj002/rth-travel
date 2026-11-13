<?php
/**
 * Public share page /q/{token} (layouts/print.php with public => true). Read-only, no internal notes.
 * @var array<string,mixed> $q
 * @var string $status
 * @var list<array<string,mixed>> $items
 * @var list<array<string,mixed>> $days
 */
echo partial('admin/quotations/_document', ['q' => $q, 'status' => $status, 'items' => $items, 'days' => $days, 'public' => true]);
