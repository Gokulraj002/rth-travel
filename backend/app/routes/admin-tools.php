<?php

declare(strict_types=1);

/**
 * Admin tools: team roles page, message templates, activity log, backup & export.
 * (Team CRUD routes /users… live in app/routes.php with ['auth', 'owner'].)
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\ActivityController;
use App\Controllers\Admin\BackupController;
use App\Controllers\Admin\TemplateController;
use App\Controllers\Admin\UserController;

// ── Team & roles: read-only permission matrix ────────────────────────────────
$router->get('/users/roles', [UserController::class, 'roles'], ['auth', 'owner']);

// ── Message templates ─────────────────────────────────────────────────────────
$tpl = ['auth', 'feature:templates', 'can:templates'];
$router->get('/templates', [TemplateController::class, 'index'], $tpl);
$router->get('/templates/create', [TemplateController::class, 'create'], $tpl);
$router->post('/templates', [TemplateController::class, 'store'], $tpl);
$router->get('/templates/{id}/edit', [TemplateController::class, 'edit'], $tpl);
$router->put('/templates/{id}', [TemplateController::class, 'update'], $tpl);
$router->delete('/templates/{id}', [TemplateController::class, 'destroy'], $tpl);
$router->post('/templates/{id}/toggle', [TemplateController::class, 'toggle'], $tpl);
$router->post('/templates/{id}/duplicate', [TemplateController::class, 'duplicate'], $tpl);

// ── Activity log ──────────────────────────────────────────────────────────────
$act = ['auth', 'feature:activity', 'can:activity'];
$router->get('/activity', [ActivityController::class, 'index'], $act);
$router->get('/activity/export', [ActivityController::class, 'export'], $act);               // CSV download

// ── Backup & export ───────────────────────────────────────────────────────────
$bak = ['auth', 'feature:backup', 'can:backup'];
$router->get('/backup', [BackupController::class, 'index'], $bak);
$router->post('/backup/database', [BackupController::class, 'database'], $bak);              // streams .sql (CSRF-checked)
$router->get('/backup/export/{table}', [BackupController::class, 'export'], $bak);           // whitelisted CSV exports

unset($tpl, $act, $bak);
