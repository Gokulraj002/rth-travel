<?php

declare(strict_types=1);

/**
 * Operations module: hotels & vendors, vehicles & drivers, booking costs & profit.
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\BookingServiceController;
use App\Controllers\Admin\VehicleController;
use App\Controllers\Admin\VendorController;

// ── Hotels & vendors ──────────────────────────────────────────────────────────
$vendorMw = ['auth', 'feature:vendors', 'can:vendors'];
$router->get('/vendors', [VendorController::class, 'index'], $vendorMw);
$router->get('/vendors/create', [VendorController::class, 'create'], $vendorMw);
$router->get('/vendors/payables', [VendorController::class, 'payables'], $vendorMw);
$router->post('/vendors', [VendorController::class, 'store'], $vendorMw);
$router->get('/vendors/{id}', [VendorController::class, 'show'], $vendorMw);
$router->get('/vendors/{id}/edit', [VendorController::class, 'edit'], $vendorMw);
$router->put('/vendors/{id}', [VendorController::class, 'update'], $vendorMw);
$router->delete('/vendors/{id}', [VendorController::class, 'destroy'], $vendorMw);
$router->post('/vendors/{id}/toggle', [VendorController::class, 'toggle'], $vendorMw);          // active ⇄ inactive

// ── Vehicles & drivers ────────────────────────────────────────────────────────
$vehicleMw = ['auth', 'feature:vehicles', 'can:vendors'];
$router->get('/vehicles', [VehicleController::class, 'index'], $vehicleMw);
$router->get('/vehicles/create', [VehicleController::class, 'create'], $vehicleMw);
$router->post('/vehicles', [VehicleController::class, 'store'], $vehicleMw);
$router->get('/vehicles/{id}/edit', [VehicleController::class, 'edit'], $vehicleMw);
$router->put('/vehicles/{id}', [VehicleController::class, 'update'], $vehicleMw);
$router->delete('/vehicles/{id}', [VehicleController::class, 'destroy'], $vehicleMw);
$router->post('/vehicles/{id}/toggle', [VehicleController::class, 'toggle'], $vehicleMw);

// ── Booking costs & profit (panel partial: admin/booking-services/panel.php) ──
$profitMw = ['auth', 'feature:profit', 'can:profit'];
$router->post('/bookings/{id}/services', [BookingServiceController::class, 'store'], $profitMw);
$router->get('/booking-services/preview/{booking_id}', [BookingServiceController::class, 'preview'], $profitMw);
$router->get('/booking-services/{id}/edit', [BookingServiceController::class, 'edit'], $profitMw);
$router->put('/booking-services/{id}', [BookingServiceController::class, 'update'], $profitMw);
$router->delete('/booking-services/{id}', [BookingServiceController::class, 'destroy'], $profitMw);
$router->post('/booking-services/{id}/paid', [BookingServiceController::class, 'paid'], $profitMw);  // paid_to_vendor = cost_price
