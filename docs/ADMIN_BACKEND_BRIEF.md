# Admin panel + API — build brief (PHP 8.2+ / MySQL)

The website (Next.js, repo root) gets a CMS: a PHP admin panel + JSON API in `backend/`, MySQL database,
hosted on **Hostinger Business** (PHP + MySQL; the Next.js site runs as a Hostinger Node.js app).

## Architecture
```
yourdomain.com          → Next.js website (Node.js app on Hostinger, deployed from GitHub)
admin.yourdomain.com    → backend/public  (PHP admin UI at /, JSON API at /api/v1/*)
MySQL                   → backend/database/schema.sql (+ seed.sql generated from src/data)
Admin saves → PHP calls Revalidator::ping([...]) → website POST /api/revalidate clears its cache → live in seconds.
Website enquiry form → POST /api/v1/enquiries → saved in `enquiries` (admin "Enquiries" inbox).
```

## Already built (read these first — do not rewrite them)
- `backend/app/bootstrap.php`, `backend/public/index.php`, `backend/app/routes.php` (**the route contract**)
- `backend/app/Core/*`: Env, Database (PDO, prepared statements: fetch/fetchAll/value/insert/update/transaction),
  Request (input/all/query/file/ip/isJson/json/header), Response (json/redirect/abort), Router (middleware auth/guest/owner/api; CSRF auto-checked on every non-GET admin request), View, Session, Csrf, Auth, Flash (success/error/info/withInput/old/errors), Validator, Uploader (Uploader::image($file) → '/uploads/…webp'; Uploader::delete($path)), Revalidator::ping(['packages']), Activity::log($action,$entity,$id,$summary), RateLimiter::hit($key,$max,$seconds), Api (cors/hasValidKey/cacheHeaders), Controller (view/redirect/back/json/id/listInput)
- `backend/app/Core/helpers.php`: e(), url(), asset(), media_url(), csrf_field(), method_field('PUT'), old(), error_for(), invalid(), partial(), inr(), str_limit(), nice_date(), nav_active(), icon('name')
- Views: `layouts/admin.php` (pass `title`, optional `pageTitle`, `pretitle`, `pageActions` HTML), `layouts/auth.php`, `partials/flash.php`, `errors/*`, `auth/*`
- `backend/public/assets/admin/app.css` + `app.js` — **reuse its declarative hooks** (documented at the top of app.js):
  `data-confirm`, `data-slug-from`, `data-repeater` (+ template/add/remove/up/down/number), `data-image-field`,
  `data-sortable` + `data-sortable-url` (POSTs JSON `{ids:[…]}` with X-CSRF-Token), `data-counter`, `window.rthToast`.
- Schema: `backend/database/schema.sql` (tables: admins, login_attempts, destinations, destination_items, destination_gallery,
  packages, package_categories, package_destinations, package_items, package_itinerary, testimonials, faqs, enquiries,
  settings, media, activity_log).

## Conventions (must follow)
- PHP 8.2 compatible (Hostinger) — `declare(strict_types=1);`, namespaces `App\Controllers\Admin\*`, `App\Controllers\Api\*`,
  `App\Repositories\*` (file path = namespace under backend/app). No Composer, no frameworks, no CDN assets.
- **Security:** SQL only via Database:: with `?` params (column names only from code). Escape every output with `e()`.
  Every form: `<?= csrf_field() ?>` (+ `<?= method_field('PUT') ?>` / `'DELETE'` for those routes). Never trust IDs —
  load the row, 404 if missing. Validate all input with Validator; whitelist enums. Uploads only via Uploader.
- Multi-table writes in `Database::transaction(fn () => …)`.
- After every successful create/update/delete/toggle/reorder of public content: `Revalidator::ping(['<entity>'])`
  and `Activity::log('updated', 'package', $id, 'Updated “Kashmir Paradise”')`. Then `Flash::success(...)` + redirect.
- On validation failure: `$this->back('/packages/'.$id.'/edit', $v->errors())` (keeps old input + errors).
  In forms read values as `old('field', $model['field'] ?? '')` and show `error_for('field')` with Bootstrap `is-invalid` / `invalid-feedback`.
- No inline `<script>` (CSP is `script-src 'self'`). If a page truly needs extra JS, add a small generic hook to app.js
  (append — do not reorganise it) and document it in the header comment.
- UI: Tabler (Bootstrap 5) components — cards, tables (`table table-vcenter card-table`), badges, `form-control`,
  `form-select`, `form-check`, `btn btn-primary`, empty states. Icons via `icon('…')`. Mobile-friendly (tables in
  `.table-responsive`). Status badges: `badge badge-published` / `badge badge-draft`. Friendly, plain-English copy for a
  non-technical travel-agency owner. Use `inr()` for prices, `nice_date()` for dates.
- Lists support search (`?q=`), status filter and pagination (20 per page) where useful.

## Public API JSON shapes (match the website's TypeScript types in src/types/index.ts)
Image paths: values starting with `/images/` are website files — return as-is. Values starting with `/uploads/` are
admin uploads — return absolute: `rtrim(APP_URL,'/') . $path`.
```jsonc
// Destination
{ "slug":"srinagar","name":"Srinagar","displayName":"SRINAGAR","region":"kashmir","tagline":"…","description":"…",
  "geo":{"lat":34.0837,"lng":74.7973},"altitudeM":1585,"bestTime":"…","distanceNote":"…",
  "highlights":["…"],"thingsToDo":["…"],"image":"/images/…","cardImage":"/images/…"|null,"gallery":["…"],"credit":"Unsplash" }
// TourPackage
{ "slug":"kashmir-paradise","title":"…","subtitle":"…","destinations":["srinagar","gulmarg"],"region":"kashmir",
  "categories":["family","honeymoon"],"nights":5,"days":6,"priceFrom":15999,"image":"…","featured":true,
  "highlights":["…"],"inclusions":["…"],"exclusions":["…"],
  "itinerary":[{"day":1,"title":"…","description":"…","stay":"Srinagar"|null}] }
// Testimonial  { "name","from","trip","quote","rating" }      // Faq { "q","a" }
// Settings
{ "name","tagline","description","phones":[{"display":"+91 60060 45211","raw":"+916006045211"}],"whatsapp":"916006045211",
  "emails":["…"],"addresses":[{"line1":"…","line2":"…"}],"mapQuery":"…","social":{"instagram":"","facebook":"","youtube":""} }
```
Endpoints (all only `status='published'`, ordered by sort_order, id):
`GET /api/v1/content` → `{ settings, destinations, packages, testimonials, faqs, generatedAt }` (the website uses this one),
`GET /api/v1/packages`, `/packages/{slug}`, `/destinations`, `/destinations/{slug}`, `/testimonials`, `/faqs`, `/settings`, `/health`.
`POST /api/v1/enquiries` (JSON): `{name, phone, email?, destination?, package?, travelMonth?, adults?, children?, hotelCategory?, message?, source?, website?}`
→ 201 `{ok:true,id}` | 422 `{error, fields:{…}}` | 429. `website` is a honeypot (must be empty). Rate limit 5 / 10 min per IP.

Settings keys (table `settings`): site_name, tagline, description, phone_primary, phone_secondary, whatsapp_number,
email_primary, email_secondary, address_1_line1, address_1_line2, address_2_line1, address_2_line2, map_query,
instagram_url, facebook_url, youtube_url.

## Local development
See `backend/README.md` → "Local development" (PHP built-in server via `npm run backend:dev`, a local MySQL
database with `schema.sql` + `seed.sql`, and an admin created with `php backend/bin/create-admin.php`).
Every page must return 200 with no PHP warnings, forms must save, and invalid input must redirect back with errors.
`php -l <file>` must pass for every PHP file.
