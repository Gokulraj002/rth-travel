# RTH CMS: admin panel + JSON API (PHP 8.2 + MySQL)

The content-management backend for the Reyaan Trip Holiday website. The admin UI is served at the root of
`admin.yourdomain.com`; the read-only JSON API that the Next.js site consumes is served under `/api/v1/*`.
It is plain PHP 8.2+, with **no Composer, framework or CDN**, so it runs on Hostinger shared hosting as uploaded.

- Owner-facing deployment guide: [`../docs/DEPLOY-HOSTINGER.md`](../docs/DEPLOY-HOSTINGER.md)
- Build brief and conventions (route contract, JSON shapes, coding rules): [`../docs/ADMIN_BACKEND_BRIEF.md`](../docs/ADMIN_BACKEND_BRIEF.md)

## Folder structure

```
backend/
├── .env.example            Config template. Copy to .env (never committed, never inside public/)
├── .htaccess               Safety net: if backend/ lands inside a docroot, deny everything outside public/
├── app/                    Application code (namespace App\ → app/, autoloaded by bootstrap.php)
│   ├── bootstrap.php       Defines paths, autoloader, loads .env, error logging, last-resort exception handler
│   ├── routes.php          THE route map: every URL → [Controller, method] + middleware (auth/guest/owner/api)
│   ├── Core/               Framework-ish building blocks (no business logic)
│   │   ├── Env.php           .env loader (real env vars win) with get/bool/int
│   │   ├── Database.php      PDO wrapper, prepared statements only: fetch/fetchAll/value/insert/update/query/transaction
│   │   ├── Request.php       Input, query, files, JSON body, headers, client IP, _method=PUT/DELETE override
│   │   ├── Response.php      json / redirect / abort (renders the errors/* views)
│   │   ├── Router.php        Matches method + path ({id}/{slug} params), runs middleware, CSRF check
│   │   ├── Controller.php    Base controller: view/redirect/back/json/id/listInput helpers
│   │   ├── View.php          PHP template renderer with layouts and partials
│   │   ├── Session.php       Hardened session cookie (HttpOnly, SameSite=Lax, Secure), regenerate, destroy
│   │   ├── Csrf.php          Per-session token: csrf_field() in forms, X-CSRF-Token for fetch()
│   │   ├── Auth.php          Login (password_hash), throttling via login_attempts, roles owner/editor
│   │   ├── Flash.php         One-request messages, old input and validation errors
│   │   ├── Validator.php     Rule-string validation (required|email|max:120|in:a,b …)
│   │   ├── Uploader.php      Image uploads: finfo MIME check, size limit, resize ≤2000px, re-encode to WebP
│   │   ├── Revalidator.php   POSTs to the website's /api/revalidate so edits go live in seconds
│   │   ├── Activity.php      Audit trail → activity_log (shown on the dashboard)
│   │   ├── RateLimiter.php   File-based fixed-window limiter (storage/cache/ratelimit)
│   │   ├── Api.php           CORS allow-list, server key check (X-RTH-Key), cache headers
│   │   ├── Mailer.php        Best-effort, header-injection-safe mail() wrapper (enquiry notifications)
│   │   └── helpers.php       e(), url(), asset(), media_url(), csrf_field(), method_field(), old(), error_for(), inr(), icon() …
│   ├── Controllers/
│   │   ├── Admin/            One controller per admin module (Auth, Dashboard, Package, Destination, Enquiry,
│   │   │                     Testimonial, Faq, Setting, Media, Site, User, Profile)
│   │   └── Api/              ContentController (public read endpoints + health), EnquiryController (POST enquiries)
│   ├── Repositories/       All SQL for a table group lives here (PackageRepository …). PublicContent = API read model
│   └── Views/
│       ├── layouts/          admin.php (sidebar shell + $nav), auth.php (sign-in pages), blank.php
│       ├── partials/         flash.php and other shared fragments
│       ├── auth/             login.php, setup.php (first-run owner creation)
│       ├── admin/<module>/   index.php (list), form.php (create/edit), show.php …
│       └── errors/           404.php, error.php (403/405/419/500)
├── bin/
│   ├── create-admin.php    CLI: create an admin or reset a password (php bin/create-admin.php email "Name" owner)
│   └── dev-router.php      Router script for PHP's built-in server (mimics public/.htaccess). Local only
├── config/                 Reserved for future config files (keep outside the docroot)
├── database/
│   ├── schema.sql          Full schema (DROP + CREATE). Import first, on an empty database only
│   └── seed.sql            Generated from src/data/* by `npm run backend:seed`. Import second
├── public/                 THE ONLY WEB-ACCESSIBLE FOLDER (document root of admin.yourdomain.com)
│   ├── index.php           Front controller: security headers, session, CSP, dispatch
│   ├── .htaccess           HTTPS redirect, dotfile block, front-controller rewrite, cache headers
│   ├── assets/admin/       app.css, app.js (declarative data-* hooks, see header comment), logo
│   ├── assets/vendor/      Self-hosted Tabler (Bootstrap 5) CSS/JS
│   └── uploads/            Admin uploads (YYYY/MM/<random>.webp). .htaccess disables script execution
└── storage/                Must be writable (755)
    ├── logs/               php-error.log (all PHP errors and failed revalidations)
    └── cache/              Rate-limit buckets and other throwaway files
```

## Request flow

```
Browser / Next.js server
   │
   ▼
public/.htaccess ── real file (assets, uploads)? → served directly by Apache/LiteSpeed
   │ otherwise
   ▼
public/index.php
   ├─ require app/bootstrap.php   (autoloader, .env, error log → storage/logs, exception handler)
   ├─ security headers (nosniff, DENY framing, Referrer-Policy, Permissions-Policy)
   ├─ non-API: Session::start(), Flash::age(), Cache-Control: no-store, strict CSP (script-src 'self')
   └─ Router::dispatch()
        ├─ match method + path from app/routes.php  (_method=PUT/DELETE spoofing for HTML forms)
        ├─ middleware:  api   → CORS allow-list, OPTIONS preflight → 204
        │               auth  → not signed in? remember path, redirect /login
        │               guest → signed in? redirect /
        │               owner → editors get 403
        │               CSRF  → every non-GET admin request must carry a valid _token / X-CSRF-Token, else 419
        └─ Controller method  →  Validator  →  Repository (SQL via Database::)  →  View / JSON / redirect
                                   after a successful write:  Revalidator::ping([...]) + Activity::log(...)
```

Website integration: the Next.js server (`src/lib/cms.ts`) fetches `GET ${CMS_API_URL}/api/v1/content` (sending `X-RTH-Key: <API_READ_KEY>`) and caches it.
Admin saves call `Revalidator::ping(['packages'])`, which POSTs `{"tags":["cms","packages"]}` to `NEXT_REVALIDATE_URL`
with `x-revalidate-secret: <NEXT_REVALIDATE_SECRET>`. The website's form posts to `POST /api/v1/enquiries`
(CORS-restricted to `API_ALLOWED_ORIGINS`, honeypot `website`, 5 requests per 10 minutes per IP).

## Adding a new admin module

Example: a "Offers" module. Follow the FAQ module (`FaqController`, `FaqRepository`, `Views/admin/faqs/*`), which is the smallest complete example.

1. **Schema:** add the table to `database/schema.sql` (InnoDB, utf8mb4, `status ENUM('published','draft')`, `sort_order`,
   `created_at`/`updated_at`). On existing installs, also give the owner an `ALTER`/`CREATE` snippet to run in phpMyAdmin,
   because re-importing `schema.sql` drops all data.
2. **Routes** (`app/routes.php`): add the standard set with `['auth']` (and `'owner'` if needed):
   ```php
   $router->get('/offers', [OfferController::class, 'index'], ['auth']);
   $router->get('/offers/create', [OfferController::class, 'create'], ['auth']);
   $router->post('/offers', [OfferController::class, 'store'], ['auth']);
   $router->post('/offers/reorder', [OfferController::class, 'reorder'], ['auth']);   // static paths BEFORE /{id}
   $router->get('/offers/{id}/edit', [OfferController::class, 'edit'], ['auth']);
   $router->put('/offers/{id}', [OfferController::class, 'update'], ['auth']);
   $router->delete('/offers/{id}', [OfferController::class, 'destroy'], ['auth']);
   ```
3. **Repository** (`app/Repositories/OfferRepository.php`, `namespace App\Repositories`): `find`, `paginate($q, $status, $page)`,
   `create`, `update`, `delete`, `reorder`. SQL **only** through `Database::` with `?` placeholders. Column and table names
   come from code, never from input. Escape `%`/`_` in LIKE searches.
4. **Controller** (`app/Controllers/Admin/OfferController.php`, `final class … extends Controller`, `declare(strict_types=1)`):
   - Load rows by id and `Response::abort(404)` if missing. Never trust posted ids.
   - Validate with `Validator::make($data, [...])` and whitelist enums. On failure: `$this->back('/offers/'.$id.'/edit', $v->errors())`.
   - Multi-table writes inside `Database::transaction(fn () => …)`.
   - After every successful write: `Revalidator::ping(['offers'])`, `Activity::log('updated', 'offer', $id, 'Updated “…”')`,
     `Flash::success('…')`, then redirect.
   - Images only via `Uploader::image($file)`.
5. **Views** (`app/Views/admin/offers/index.php`, `form.php`): render with `$this->view('admin/offers/index', ['title' => …, 'pageTitle' => …])`.
   Escape everything with `e()`. Every form gets `<?= csrf_field() ?>` (+ `<?= method_field('PUT') ?>` / `'DELETE'`).
   Read values with `old('field', $row['field'] ?? '')` and show `error_for('field')`. Use Tabler markup and the `app.js` hooks
   (`data-confirm`, `data-sortable`, `data-repeater`, `data-image-field`, `data-slug-from`). **No inline `<script>`** (the CSP blocks it).
6. **Navigation:** add `['/offers', 'Offers', 'tag']` to `$nav` at the top of `app/Views/layouts/admin.php` (icon names: see `icon()` in helpers.php).
7. **Public API** (if the website needs it): add a read method to `Repositories/PublicContent.php` (published only, ordered by
   `sort_order, id`, shape = the website's TypeScript type), and expose it from `Controllers/Api/ContentController.php` plus an
   `['api']` route. Then handle the new revalidation tag in the website's `/api/revalidate`.
8. **Check:** `php -l` on every new file, then click through list → create → invalid input (should redirect back with errors) → edit → delete.
   Watch `storage/logs/php-error.log` for warnings.

## Security model

| Threat | Defence |
|---|---|
| Source / secrets exposure | Docroot is `public/` only. `.env`, `app/`, `storage/`, `database/` sit outside it. `backend/.htaccess` denies all if misplaced. `public/.htaccess` blocks dotfiles. `bin/*` refuses non-CLI. `dist/backend.zip` never contains `.env` |
| SQL injection | PDO prepared statements everywhere (`Database::`), emulated prepares off. Identifiers only from code |
| XSS | Every echo through `e()` (htmlspecialchars). Strict CSP `script-src 'self'` with no inline scripts. `X-Content-Type-Options: nosniff` |
| CSRF | Router verifies a per-session token on every non-GET admin request (419 on failure). `SameSite=Lax` cookies |
| Session theft / fixation | HttpOnly + Secure cookies, ID regenerated on login, idle lifetime `SESSION_LIFETIME_MINUTES`, `no-store` on admin pages |
| Brute force | `login_attempts` throttle: 5 per email / 20 per IP per 15 minutes. `password_hash` (bcrypt/argon), auto-rehash |
| Privilege escalation | Roles `owner` / `editor`. `owner` middleware on user management. Ids always re-loaded server-side |
| Malicious uploads | MIME sniffed with finfo (JPEG/PNG/WebP only), size cap, image re-encoded to WebP with GD (strips payloads), random file names, `uploads/.htaccess` disables PHP/CGI |
| API abuse | Read endpoints are published-only data. Enquiries: CORS allow-list, honeypot, validation, rate limit 5/10 min/IP. Optional `X-RTH-Key` server key (constant-time compare) |
| Clickjacking / sniffing | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, HSTS on HTTPS, forced HTTPS redirect |
| First-run takeover | `/setup` only works while `admins` is empty **and** `?token=` matches a ≥16-char `SETUP_TOKEN` (hash_equals). Otherwise 404 |
| Error leakage | `APP_DEBUG=false`: generic error pages/JSON. Details go to `storage/logs/php-error.log` only |

## Local development

Requirements: PHP 8.2+ CLI with `pdo_mysql`, `gd` (with WebP), `fileinfo`, `curl`, `mbstring`; MySQL 8 / MariaDB 10.6+; Node 22 for the seed script.

```bash
# 1. Config
cp backend/.env.example backend/.env
#    Set APP_DEBUG=true, APP_URL=http://127.0.0.1:8095, SESSION_SECURE=false, DB_HOST=127.0.0.1, DB_PORT/NAME/USER/PASS,
#    WEBSITE_URL=http://localhost:3000, API_ALLOWED_ORIGINS=http://localhost:3000,
#    NEXT_REVALIDATE_URL=http://localhost:3000/api/revalidate (+ same secret as the website's .env.local)

# 2. Database
mysql -uroot -p -e "CREATE DATABASE rth_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
npm run backend:seed                                  # regenerate database/seed.sql from src/data/*
mysql -uroot -p rth_cms < backend/database/schema.sql
mysql -uroot -p rth_cms < backend/database/seed.sql

# 3. First admin
php backend/bin/create-admin.php you@example.com "Your Name" owner

# 4. Serve admin + API on http://127.0.0.1:8095
npm run backend:dev        # = php -S 127.0.0.1:8095 -t backend/public backend/bin/dev-router.php
```

`bin/dev-router.php` reproduces `public/.htaccess`: real files under `public/` are served directly, and everything else goes to `index.php`.
The HTTPS redirect in `.htaccess` is skipped for `localhost` / `127.0.0.1`.

Packaging for Hostinger: `npm run backend:zip` writes `dist/backend.zip` (backend/ minus `.env`, logs, cache and uploads, keeping
the `.gitkeep`/`.htaccess` placeholders, including `database/*.sql`). `dist/` is git-ignored.
