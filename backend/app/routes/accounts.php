<?php

declare(strict_types=1);

/**
 * Accounts module: Business details (all plans), GST invoices (Premium), Reports (Pro).
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\BusinessController;
use App\Controllers\Admin\InvoiceController;
use App\Controllers\Admin\ReportController;

// ── Business details (no plan gate — Basic too) ───────────────────────────────
$router->get('/business', [BusinessController::class, 'edit'], ['auth', 'can:settings']);
$router->put('/business', [BusinessController::class, 'update'], ['auth', 'can:settings']);

// ── GST invoices ──────────────────────────────────────────────────────────────
$inv = ['auth', 'feature:invoices', 'can:invoices'];
$router->get('/invoices', [InvoiceController::class, 'index'], $inv);
$router->get('/invoices/create', [InvoiceController::class, 'create'], $inv);           // ?booking_id=
$router->post('/invoices', [InvoiceController::class, 'store'], $inv);
$router->get('/invoices/{id}', [InvoiceController::class, 'show'], $inv);
$router->get('/invoices/{id}/edit', [InvoiceController::class, 'edit'], $inv);
$router->get('/invoices/{id}/print', [InvoiceController::class, 'print'], $inv);
$router->put('/invoices/{id}', [InvoiceController::class, 'update'], $inv);
$router->post('/invoices/{id}/issue', [InvoiceController::class, 'issue'], $inv);       // draft → issued (gets its number)
$router->post('/invoices/{id}/paid', [InvoiceController::class, 'markPaid'], $inv);
$router->post('/invoices/{id}/void', [InvoiceController::class, 'void'], $inv);
$router->delete('/invoices/{id}', [InvoiceController::class, 'destroy'], $inv);         // drafts only

// ── Reports ───────────────────────────────────────────────────────────────────
$rep = ['auth', 'feature:reports', 'can:reports'];
$router->get('/reports', [ReportController::class, 'index'], $rep);                     // ?tab=&preset=&from=&to=
$router->get('/reports/export/{section}', [ReportController::class, 'export'], $rep);   // CSV
