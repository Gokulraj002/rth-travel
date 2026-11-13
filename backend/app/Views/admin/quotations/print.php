<?php
/**
 * Admin print / PDF view (layouts/print.php).
 * @var array<string,mixed> $q
 * @var string $status
 * @var list<array<string,mixed>> $items
 * @var list<array<string,mixed>> $days
 */
echo partial('admin/quotations/_document', ['q' => $q, 'status' => $status, 'items' => $items, 'days' => $days, 'public' => false]);
