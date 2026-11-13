<?php

declare(strict_types=1);

/**
 * Route map — the contract for all controllers.
 * Admin UI lives at the root of the admin subdomain (https://admin.example.com/…).
 * Public JSON API lives under /api/v1/… and is consumed by the Next.js website.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\DestinationController;
use App\Controllers\Admin\EnquiryController;
use App\Controllers\Admin\FaqController;
use App\Controllers\Admin\LicenseController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\PackageController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\SiteController;
use App\Controllers\Admin\TestimonialController;
use App\Controllers\Admin\UserController;
use App\Controllers\Api\ContentController as ApiContent;
use App\Controllers\Api\EnquiryController as ApiEnquiry;

// ── Auth ──────────────────────────────────────────────────────────────────────
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
// First-run setup (only works while there are no admins AND ?token=SETUP_TOKEN matches .env)
$router->get('/setup', [AuthController::class, 'showSetup']);
$router->post('/setup', [AuthController::class, 'setup']);

// ── Dashboard ─────────────────────────────────────────────────────────────────
$router->get('/', [DashboardController::class, 'index'], ['auth']);

// ── Packages ──────────────────────────────────────────────────────────────────
$router->get('/packages', [PackageController::class, 'index'], ['auth', 'can:website']);
$router->get('/packages/create', [PackageController::class, 'create'], ['auth', 'can:website']);
$router->post('/packages', [PackageController::class, 'store'], ['auth', 'can:website']);
$router->post('/packages/reorder', [PackageController::class, 'reorder'], ['auth', 'can:website']);          // JSON {ids:[…]} + X-CSRF-Token
$router->get('/packages/{id}/edit', [PackageController::class, 'edit'], ['auth', 'can:website']);
$router->put('/packages/{id}', [PackageController::class, 'update'], ['auth', 'can:website']);
$router->delete('/packages/{id}', [PackageController::class, 'destroy'], ['auth', 'can:website']);
$router->post('/packages/{id}/duplicate', [PackageController::class, 'duplicate'], ['auth', 'can:website']);
$router->post('/packages/{id}/toggle', [PackageController::class, 'toggle'], ['auth', 'can:website']);       // publish ⇄ draft

// ── Destinations ──────────────────────────────────────────────────────────────
$router->get('/destinations', [DestinationController::class, 'index'], ['auth', 'can:website']);
$router->get('/destinations/create', [DestinationController::class, 'create'], ['auth', 'can:website']);
$router->post('/destinations', [DestinationController::class, 'store'], ['auth', 'can:website']);
$router->post('/destinations/reorder', [DestinationController::class, 'reorder'], ['auth', 'can:website']);
$router->get('/destinations/{id}/edit', [DestinationController::class, 'edit'], ['auth', 'can:website']);
$router->put('/destinations/{id}', [DestinationController::class, 'update'], ['auth', 'can:website']);
$router->delete('/destinations/{id}', [DestinationController::class, 'destroy'], ['auth', 'can:website']);
$router->post('/destinations/{id}/toggle', [DestinationController::class, 'toggle'], ['auth', 'can:website']);

// ── Enquiries (leads) ─────────────────────────────────────────────────────────
$router->get('/enquiries', [EnquiryController::class, 'index'], ['auth', 'can:enquiries']);
$router->get('/enquiries/export', [EnquiryController::class, 'export'], ['auth', 'can:enquiries']);            // CSV download
$router->get('/enquiries/{id}', [EnquiryController::class, 'show'], ['auth', 'can:enquiries']);
$router->put('/enquiries/{id}', [EnquiryController::class, 'update'], ['auth', 'can:enquiries']);              // status + notes
$router->delete('/enquiries/{id}', [EnquiryController::class, 'destroy'], ['auth', 'can:enquiries']);

// ── Testimonials ──────────────────────────────────────────────────────────────
$router->get('/testimonials', [TestimonialController::class, 'index'], ['auth', 'can:website']);
$router->get('/testimonials/create', [TestimonialController::class, 'create'], ['auth', 'can:website']);
$router->post('/testimonials', [TestimonialController::class, 'store'], ['auth', 'can:website']);
$router->post('/testimonials/reorder', [TestimonialController::class, 'reorder'], ['auth', 'can:website']);
$router->get('/testimonials/{id}/edit', [TestimonialController::class, 'edit'], ['auth', 'can:website']);
$router->put('/testimonials/{id}', [TestimonialController::class, 'update'], ['auth', 'can:website']);
$router->delete('/testimonials/{id}', [TestimonialController::class, 'destroy'], ['auth', 'can:website']);

// ── FAQs ──────────────────────────────────────────────────────────────────────
$router->get('/faqs', [FaqController::class, 'index'], ['auth', 'can:website']);
$router->get('/faqs/create', [FaqController::class, 'create'], ['auth', 'can:website']);
$router->post('/faqs', [FaqController::class, 'store'], ['auth', 'can:website']);
$router->post('/faqs/reorder', [FaqController::class, 'reorder'], ['auth', 'can:website']);
$router->get('/faqs/{id}/edit', [FaqController::class, 'edit'], ['auth', 'can:website']);
$router->put('/faqs/{id}', [FaqController::class, 'update'], ['auth', 'can:website']);
$router->delete('/faqs/{id}', [FaqController::class, 'destroy'], ['auth', 'can:website']);

// ── Site settings, media, website refresh ─────────────────────────────────────
$router->get('/settings', [SettingController::class, 'edit'], ['auth', 'can:settings']);
$router->put('/settings', [SettingController::class, 'update'], ['auth', 'can:settings']);
$router->get('/media', [MediaController::class, 'index'], ['auth', 'can:website']);                          // JSON list for the picker
$router->post('/media/upload', [MediaController::class, 'upload'], ['auth', 'can:website']);                 // multipart, returns JSON {path,url}
$router->post('/site/refresh', [SiteController::class, 'refresh'], ['auth', 'can:website']);                 // manual "update website now"

// ── Admin users (owner only) + own profile ────────────────────────────────────
$router->get('/users', [UserController::class, 'index'], ['auth', 'owner']);
$router->get('/users/create', [UserController::class, 'create'], ['auth', 'owner']);
$router->post('/users', [UserController::class, 'store'], ['auth', 'owner']);
$router->get('/users/{id}/edit', [UserController::class, 'edit'], ['auth', 'owner']);
$router->put('/users/{id}', [UserController::class, 'update'], ['auth', 'owner']);
$router->delete('/users/{id}', [UserController::class, 'destroy'], ['auth', 'owner']);
$router->get('/profile', [ProfileController::class, 'edit'], ['auth']);
$router->put('/profile', [ProfileController::class, 'update'], ['auth']);
$router->put('/profile/password', [ProfileController::class, 'password'], ['auth']);

// ── Public JSON API (read by the Next.js website) ─────────────────────────────
$router->get('/api/v1/health', [ApiContent::class, 'health'], ['api']);
$router->get('/api/v1/content', [ApiContent::class, 'content'], ['api']);                     // everything in one call
$router->get('/api/v1/packages', [ApiContent::class, 'packages'], ['api']);
$router->get('/api/v1/packages/{slug}', [ApiContent::class, 'package'], ['api']);
$router->get('/api/v1/destinations', [ApiContent::class, 'destinations'], ['api']);
$router->get('/api/v1/destinations/{slug}', [ApiContent::class, 'destination'], ['api']);
$router->get('/api/v1/testimonials', [ApiContent::class, 'testimonials'], ['api']);
$router->get('/api/v1/faqs', [ApiContent::class, 'faqs'], ['api']);
$router->get('/api/v1/settings', [ApiContent::class, 'settings'], ['api']);
$router->post('/api/v1/enquiries', [ApiEnquiry::class, 'store'], ['api']);
$router->options('/api/v1/enquiries', [ApiEnquiry::class, 'store'], ['api']);

// ── Plan & license (owner only) ───────────────────────────────────────────────
$router->get('/license', [LicenseController::class, 'index'], ['auth', 'owner']);
$router->put('/license', [LicenseController::class, 'update'], ['auth', 'owner']);
$router->delete('/license', [LicenseController::class, 'destroy'], ['auth', 'owner']);

// ── Module route files (one per module: app/routes/crm.php, bookings.php, …) ──
foreach (glob(APP_PATH . '/routes/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
