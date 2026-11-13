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
$router->get('/packages', [PackageController::class, 'index'], ['auth']);
$router->get('/packages/create', [PackageController::class, 'create'], ['auth']);
$router->post('/packages', [PackageController::class, 'store'], ['auth']);
$router->post('/packages/reorder', [PackageController::class, 'reorder'], ['auth']);          // JSON {ids:[…]} + X-CSRF-Token
$router->get('/packages/{id}/edit', [PackageController::class, 'edit'], ['auth']);
$router->put('/packages/{id}', [PackageController::class, 'update'], ['auth']);
$router->delete('/packages/{id}', [PackageController::class, 'destroy'], ['auth']);
$router->post('/packages/{id}/duplicate', [PackageController::class, 'duplicate'], ['auth']);
$router->post('/packages/{id}/toggle', [PackageController::class, 'toggle'], ['auth']);       // publish ⇄ draft

// ── Destinations ──────────────────────────────────────────────────────────────
$router->get('/destinations', [DestinationController::class, 'index'], ['auth']);
$router->get('/destinations/create', [DestinationController::class, 'create'], ['auth']);
$router->post('/destinations', [DestinationController::class, 'store'], ['auth']);
$router->post('/destinations/reorder', [DestinationController::class, 'reorder'], ['auth']);
$router->get('/destinations/{id}/edit', [DestinationController::class, 'edit'], ['auth']);
$router->put('/destinations/{id}', [DestinationController::class, 'update'], ['auth']);
$router->delete('/destinations/{id}', [DestinationController::class, 'destroy'], ['auth']);
$router->post('/destinations/{id}/toggle', [DestinationController::class, 'toggle'], ['auth']);

// ── Enquiries (leads) ─────────────────────────────────────────────────────────
$router->get('/enquiries', [EnquiryController::class, 'index'], ['auth']);
$router->get('/enquiries/export', [EnquiryController::class, 'export'], ['auth']);            // CSV download
$router->get('/enquiries/{id}', [EnquiryController::class, 'show'], ['auth']);
$router->put('/enquiries/{id}', [EnquiryController::class, 'update'], ['auth']);              // status + notes
$router->delete('/enquiries/{id}', [EnquiryController::class, 'destroy'], ['auth']);

// ── Testimonials ──────────────────────────────────────────────────────────────
$router->get('/testimonials', [TestimonialController::class, 'index'], ['auth']);
$router->get('/testimonials/create', [TestimonialController::class, 'create'], ['auth']);
$router->post('/testimonials', [TestimonialController::class, 'store'], ['auth']);
$router->post('/testimonials/reorder', [TestimonialController::class, 'reorder'], ['auth']);
$router->get('/testimonials/{id}/edit', [TestimonialController::class, 'edit'], ['auth']);
$router->put('/testimonials/{id}', [TestimonialController::class, 'update'], ['auth']);
$router->delete('/testimonials/{id}', [TestimonialController::class, 'destroy'], ['auth']);

// ── FAQs ──────────────────────────────────────────────────────────────────────
$router->get('/faqs', [FaqController::class, 'index'], ['auth']);
$router->get('/faqs/create', [FaqController::class, 'create'], ['auth']);
$router->post('/faqs', [FaqController::class, 'store'], ['auth']);
$router->post('/faqs/reorder', [FaqController::class, 'reorder'], ['auth']);
$router->get('/faqs/{id}/edit', [FaqController::class, 'edit'], ['auth']);
$router->put('/faqs/{id}', [FaqController::class, 'update'], ['auth']);
$router->delete('/faqs/{id}', [FaqController::class, 'destroy'], ['auth']);

// ── Site settings, media, website refresh ─────────────────────────────────────
$router->get('/settings', [SettingController::class, 'edit'], ['auth']);
$router->put('/settings', [SettingController::class, 'update'], ['auth']);
$router->get('/media', [MediaController::class, 'index'], ['auth']);                          // JSON list for the picker
$router->post('/media/upload', [MediaController::class, 'upload'], ['auth']);                 // multipart, returns JSON {path,url}
$router->post('/site/refresh', [SiteController::class, 'refresh'], ['auth']);                 // manual "update website now"

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
