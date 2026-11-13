<?php

declare(strict_types=1);

/**
 * CRM module: customers, lead pipeline, follow-ups, enquiry upgrades.
 * The Basic-plan enquiry routes (GET /enquiries, /enquiries/export, /enquiries/{id}, PUT/DELETE /enquiries/{id})
 * stay in app/routes.php.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\CustomerController;
use App\Controllers\Admin\EnquiryController;
use App\Controllers\Admin\LeadController;
use App\Controllers\Admin\TaskController;

$crm = ['auth', 'feature:crm', 'can:enquiries'];
$cust = ['auth', 'feature:customers', 'can:customers'];

// ── Enquiries (CRM extras) ────────────────────────────────────────────────────
// NOTE: GET /enquiries/create is matched first by routes.php's /enquiries/{id}; EnquiryController::show()
// hands it to create(), which checks the "crm" feature itself. Registered here too for completeness.
$router->get('/enquiries/create', [EnquiryController::class, 'create'], $crm);
$router->post('/enquiries', [EnquiryController::class, 'store'], $crm);                                   // manual entry
$router->post('/enquiries/{id}/activities', [EnquiryController::class, 'activity'], $crm);                // log call / note …
$router->post('/enquiries/{id}/customer', [EnquiryController::class, 'convert'], $cust);                  // convert / link to customer

// ── Lead pipeline ─────────────────────────────────────────────────────────────
$router->get('/leads', [LeadController::class, 'index'], $crm);                                           // ?view=list for the table
$router->post('/leads/{id}/stage', [LeadController::class, 'stage'], $crm);                               // JSON {stage, lost_reason?} + X-CSRF-Token

// ── Follow-ups / tasks ────────────────────────────────────────────────────────
$router->get('/tasks', [TaskController::class, 'index'], $crm);                                           // ?tab=overdue|today|upcoming|done
$router->get('/tasks/create', [TaskController::class, 'create'], $crm);                                   // ?related=enquiry:12
$router->post('/tasks', [TaskController::class, 'store'], $crm);
$router->get('/tasks/{id}/edit', [TaskController::class, 'edit'], $crm);
$router->put('/tasks/{id}', [TaskController::class, 'update'], $crm);
$router->post('/tasks/{id}/done', [TaskController::class, 'done'], $crm);
$router->post('/tasks/{id}/reopen', [TaskController::class, 'reopen'], $crm);
$router->delete('/tasks/{id}', [TaskController::class, 'destroy'], $crm);

// ── Customers ─────────────────────────────────────────────────────────────────
$router->get('/customers', [CustomerController::class, 'index'], $cust);
$router->get('/customers/create', [CustomerController::class, 'create'], $cust);
$router->get('/customers/export', [CustomerController::class, 'export'], $cust);                         // CSV download
$router->post('/customers', [CustomerController::class, 'store'], $cust);
$router->get('/customers/{id}', [CustomerController::class, 'show'], $cust);
$router->get('/customers/{id}/edit', [CustomerController::class, 'edit'], $cust);
$router->put('/customers/{id}', [CustomerController::class, 'update'], $cust);
$router->delete('/customers/{id}', [CustomerController::class, 'destroy'], $cust);
$router->post('/customers/{id}/activities', [CustomerController::class, 'activity'], $cust);

unset($crm, $cust);
