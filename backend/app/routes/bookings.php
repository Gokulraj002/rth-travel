<?php

declare(strict_types=1);

/**
 * Bookings, payments & trip calendar.
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\BookingController;
use App\Controllers\Admin\CalendarController;
use App\Controllers\Admin\PaymentController;

$bk = ['auth', 'feature:bookings', 'can:bookings'];
$pay = ['auth', 'feature:payments', 'can:payments'];

// ── Bookings ──────────────────────────────────────────────────────────────────
$router->get('/bookings', [BookingController::class, 'index'], $bk);
$router->get('/bookings/export', [BookingController::class, 'export'], $bk);                   // CSV download
$router->get('/bookings/create', [BookingController::class, 'create'], $bk);                   // ?customer_id=
$router->post('/bookings', [BookingController::class, 'store'], $bk);
$router->get('/bookings/{id}', [BookingController::class, 'show'], $bk);
$router->get('/bookings/{id}/edit', [BookingController::class, 'edit'], $bk);
$router->get('/bookings/{id}/voucher', [BookingController::class, 'voucher'], $bk);            // printable traveller voucher
$router->put('/bookings/{id}', [BookingController::class, 'update'], $bk);
$router->post('/bookings/{id}/status', [BookingController::class, 'status'], $bk);             // quick status change / cancel
$router->delete('/bookings/{id}', [BookingController::class, 'destroy'], $bk);                 // only when no payments

// ── Payments ──────────────────────────────────────────────────────────────────
$router->get('/payments', [PaymentController::class, 'index'], $pay);
$router->get('/payments/export', [PaymentController::class, 'export'], $pay);                  // CSV download
$router->post('/bookings/{id}/payments', [PaymentController::class, 'store'], $pay);
$router->get('/payments/{id}/receipt', [PaymentController::class, 'receipt'], $pay);           // printable receipt
$router->delete('/payments/{id}', [PaymentController::class, 'destroy'], $pay);

// ── Trip calendar ─────────────────────────────────────────────────────────────
$router->get('/calendar', [CalendarController::class, 'index'], ['auth', 'feature:calendar', 'can:bookings']);
