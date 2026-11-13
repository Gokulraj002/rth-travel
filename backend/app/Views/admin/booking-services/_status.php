<?php
/** Service status badge. @var string $status */
$map = ['pending' => ['bg-yellow-lt', 'Pending'], 'confirmed' => ['bg-green-lt', 'Confirmed'], 'cancelled' => ['bg-red-lt', 'Cancelled']];
[$cls, $label] = $map[$status] ?? ['bg-secondary-lt', ucfirst($status)];
?><span class="badge <?= e($cls) ?>"><?= e($label) ?></span>
