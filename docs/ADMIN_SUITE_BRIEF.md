# Travel Admin Suite — build brief for module agents

Read first: `docs/ADMIN_BACKEND_BRIEF.md` (conventions: security, Validator, Flash, CSRF, `e()`, Tabler UI, no inline JS).
Everything below adds to it. Backend = `backend/` (plain PHP 8.2, no Composer, MySQL 8, Tabler/Bootstrap 5).

## What already exists (foundation — DO NOT EDIT these files)
- `app/Core/*` (Router, Database, Auth, Validator, Flash, Activity, Uploader, Mailer::send($to,$subject,$body) …) plus NEW:
  - `License::has('quotations')`, `License::plan()`, `License::planLabel()` — paid plans (config/plans.php)
  - `Gate::can('bookings')`, `Gate::authorize()`, `Gate::assignableRoles()`, `Gate::roleLabel()` — roles (config/permissions.php)
  - `Sequence::next('quotation'|'booking'|'invoice'|'receipt')` → "QT-2026-0001" (atomic; call inside your transaction)
  - `Settings::get($key)` / `Settings::set([...])` — any settings key; business defaults in config/business.php
  - `Template::message('Booking confirmed', $vars, $fallbackText)` / `Template::render($body,$vars)` / `Template::placeholders()`
- `app/Core/helpers.php`: e, url, asset, inr (accepts DECIMAL strings), **inr2** (₹1,234.50), nice_date, **nice_day** (date only),
  **amount_in_words**, **status_badge($status, $label?)**, **wa_link($phone, $text)** (wa.me link), **wa_phone**, **config($name)**,
  **setting($key)**, **feature($f)**, **can($perm)**, icon(…) — icons available: dashboard package map-pin inbox star help settings users
  user photo plus edit trash copy eye external logout search grip x check phone mail whatsapp arrow-up arrow-down arrow-left
  arrow-right chevron-left chevron-right chevron-down refresh calendar menu lock world columns bell file-text briefcase building car
  cash receipt chart trending-up trending-down id message history database crown printer send download upload alert clock filter
  rupee list note dots link shield key plane bed sparkles target wallet.
- `app/Support/Chart.php`: `Chart::bars($labels,$values,['money'=>true,'series2'=>[…],'names'=>['A','B']])`, `Chart::line()`, `Chart::donut()` — SVG.
- `app/Repositories/CustomerRepository.php`: `find($id)`, `findOrCreate(['name','phone','email'?,'city'?,'source'?]) : int`,
  `normalizePhone()`, `options()` — the CRM agent may ADD methods, never change these signatures.
- Config: `config/plans.php`, `config/permissions.php`, `config/navigation.php` (sidebar — already lists every module URL below),
  `config/business.php`, `config/crm.php` (lead stages, sources, lost reasons).
- Layouts: `layouts/admin.php` (pass `title`, `pageTitle`, `pretitle`, `pageActions`), **`layouts/print.php`** for A4 documents
  (pass `title`, `backUrl` (admin) or `public => true`, optional `toolbar` HTML; wrap the doc in `<div class="rth-doc">`; base styles in
  `assets/admin/print.css`: .rth-doc-head, .rth-doc-brand, .rth-doc-title, .rth-doc-table (.num), .rth-doc-totals (.grand), .rth-doc-foot, .rth-doc-box;
  `data-print` button and `data-copy="text"` already work there).
- License page `/license`, upgrade screen (router renders it automatically for `feature:` routes), `partials/upgrade-contact`.

## Database (all tables already created — see `backend/database/migrations/002_travel_suite.sql`)
customers, lead_activities, tasks, quotations, quotation_items, quotation_days, bookings, booking_travellers, payments,
invoices, invoice_items, vendors, vehicles, booking_services, message_templates, sequences.
`enquiries` gained: customer_id, stage, priority, assigned_to, budget, travel_date, next_follow_up_at, lost_reason.
Money columns are DECIMAL → PDO returns strings: cast `(float)` before maths; show with inr()/inr2().
Booking money: paid = `SUM(payments.amount)`, balance = `total_amount - paid`. Profit = `total_amount - SUM(booking_services.cost_price)`
(only for non-cancelled services).

## How to add your module (so agents never edit the same file)
1. **Routes** → create `app/routes/<module>.php` (auto-loaded). Middleware: `['auth', 'feature:<feature>', 'can:<permission>']`.
   `$router` is in scope; import controllers with `use`. Static paths (e.g. `/quotations/create`) BEFORE `/{id}` paths.
2. **Controllers** `app/Controllers/Admin/<Name>Controller.php`, **repositories** `app/Repositories/<Name>Repository.php`,
   **views** `app/Views/admin/<module>/…`.
3. **CSS/JS**: create `public/assets/admin/css/<module>.css` / `public/assets/admin/js/<module>.js` — auto-loaded on every admin page,
   so scope selectors under a page class or data attribute. Print-document styles: `public/assets/admin/css/doc-<module>.css`
   (auto-loaded by layouts/print.php). No inline scripts or `style` attributes needing JS; CSP is `script-src 'self'`.
4. Never edit: layouts, helpers.php, app.js, Router, routes.php, config/*, License/Gate/Sequence/Settings/Template, other agents' files.
   (Exception: the UI agent owns app.css, auth views and the dashboard.) If you truly need a shared change, describe it in your final report.
5. After create/update/delete: `Activity::log('created'|'updated'|'deleted', '<entity>', $id, 'Human summary')`, `Flash::success(...)`, redirect.
6. Every list: search `?q=`, useful filters, pagination (20/page), empty state with a call-to-action, `.table-responsive`.
7. Mobile-friendly, plain English for a non-technical travel agency owner, Indian formats (₹, dd Mon yyyy). Show money with inr().
8. Cross-module links use these URLs (all real):
   `/customers/{id}`, `/enquiries/{id}`, `/leads`, `/tasks`, `/quotations/create?customer_id=&enquiry_id=&package_id=`, `/quotations/{id}`,
   `/bookings/create?customer_id=`, `/bookings/{id}`, `/payments`, `/invoices/create?booking_id=`, `/invoices/{id}`, `/vendors/{id}`, `/vehicles`,
   `/calendar`, `/reports`, `/business`, `/templates`, `/activity`, `/backup`, `/users`.
   Only show a link/button when `feature('x') && can('y')` for its target.
9. **Booking page panels** (bookings agent renders, others provide the partial):
   `admin/booking-services/panel.php` (vendors agent; receives `$booking`, shows costs/profit + add/remove service forms),
   `admin/invoices/booking-panel.php` (invoices agent; receives `$booking`, lists its invoices + "Create GST invoice" button).
   The bookings agent includes each only if the file exists and `feature()`/`can()` allow:
   `<?php if (feature('profit') && can('profit') && is_file(APP_PATH . '/Views/admin/booking-services/panel.php')) echo partial('admin/booking-services/panel', ['booking' => $booking]); ?>`
10. WhatsApp sending = open a `wa_link($customer['phone'], Template::message('<template name>', $vars, $fallback))` link in a new tab
    (seeded template names: "Enquiry — first reply", "Quotation shared", "Booking confirmed", "Payment received", "Trip reminder", "Quotation email").

## Local test environment
- Admin: a PHP built-in server on `backend/` (docroot `public/`), with a licence active for the plan under test.
- Sign in with the local test admin account; create one with `php backend/bin/create-admin.php` if there is none.
  Never write credentials into any file in the repository.
- MySQL: a local database loaded from `database/schema.sql` + `seed.sql` (see `backend/README.md` → Local development).
- Test client: a small script that logs in and prints the status plus any PHP warnings for each path. For POST tests,
  read `_token` from the form and send `_method` for PUT/DELETE. Keep such scripts in the session scratchpad, not in the repo.
- Done means: `php -l` passes on every file you wrote; every page you added returns 200 with no PHP warnings/notices
  (check `backend/storage/logs/php-error.log` too); create/edit/delete actually work in the DB; invalid input redirects back with errors.
  Create a few realistic sample records while testing (Indian names, Kashmir trips) and LEAVE them — they demo the product.
- Do not commit to git. Do not write credentials into any file.
