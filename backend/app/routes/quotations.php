<?php

declare(strict_types=1);

/**
 * Quotations module (feature "quotations", permission "quotations") + the public share page /q/{token}.
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\QuotationController;
use App\Controllers\PublicQuotationController;

$qmw = ['auth', 'feature:quotations', 'can:quotations'];

// Static paths first.
$router->get('/quotations', [QuotationController::class, 'index'], $qmw);
$router->get('/quotations/create', [QuotationController::class, 'create'], $qmw);
$router->post('/quotations', [QuotationController::class, 'store'], $qmw);
$router->get('/quotations/package/{id}', [QuotationController::class, 'package'], $qmw);      // JSON: itinerary + inclusions for "Load from package"

$router->get('/quotations/{id}', [QuotationController::class, 'show'], $qmw);
$router->get('/quotations/{id}/edit', [QuotationController::class, 'edit'], $qmw);
$router->get('/quotations/{id}/print', [QuotationController::class, 'print'], $qmw);
$router->put('/quotations/{id}', [QuotationController::class, 'update'], $qmw);
$router->delete('/quotations/{id}', [QuotationController::class, 'destroy'], $qmw);
$router->post('/quotations/{id}/status', [QuotationController::class, 'status'], $qmw);         // sent | accepted | rejected | draft
$router->post('/quotations/{id}/whatsapp', [QuotationController::class, 'whatsapp'], $qmw);     // marks sent, redirects to wa.me
$router->post('/quotations/{id}/email', [QuotationController::class, 'email'], $qmw);
$router->post('/quotations/{id}/duplicate', [QuotationController::class, 'duplicate'], $qmw);
$router->post('/quotations/{id}/convert', [QuotationController::class, 'convert'], $qmw);       // → booking (needs bookings feature + permission)

// Public share link — no login. 404 unless the plan includes quotations and the token exists.
$router->get('/q/{token}', [PublicQuotationController::class, 'show'], []);

unset($qmw);
