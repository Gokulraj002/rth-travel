# Putting the website and admin panel live on Hostinger

This guide walks you through publishing **Reyaan Trip Holiday** on a **Hostinger Business web hosting** plan,
one click at a time. You don't need to know how to code. Set aside about an hour.

You will set up three pieces:

| Piece | Address | What it is |
|---|---|---|
| **Website** | `https://yourdomain.com` | The public site your customers see (Next.js, runs as a Hostinger "Node.js web app") |
| **Admin panel** | `https://admin.yourdomain.com` | Where you edit packages, prices, photos and read enquiries (PHP) |
| **Database** | (private) | Where everything you type in the admin panel is stored (MySQL) |

Throughout this guide, replace **`yourdomain.com`** with your real domain name, and **`u123456789`** with
your own Hostinger username, which hPanel shows on every database name.

> hPanel menu names change from time to time. If a button has a slightly different name, look for the
> closest match. The Hostinger help pages listed at the end show the latest screens.

---

## How it fits together

```
                 Visitors
                    │
      ┌─────────────┴──────────────┐
      ▼                            ▼
 yourdomain.com             admin.yourdomain.com
 ┌──────────────────┐       ┌───────────────────────────────┐
 │ WEBSITE          │       │ ADMIN PANEL + API (PHP 8.2)   │
 │ Node.js web app  │──────▶│ web root = public_html     │
 │ (from GitHub,    │ reads │  /            admin screens   │
 │  branch main)    │content│  /api/v1/...  data for website│
 │                  │◀──────│                               │
 │ /api/revalidate  │"update│ app/ config/ storage/ .env    │
 │                  │  now" │ stay OUTSIDE the public folder│
 └──────────────────┘       └───────────────┬───────────────┘
   enquiry form ──POST /api/v1/enquiries──▶ │
                                            ▼
                                   ┌──────────────────┐
                                   │ MySQL database   │
                                   │ u123456789_rth   │
                                   └──────────────────┘
```

- When you **save** something in the admin panel, it tells the website to refresh, and the change is live within seconds.
- When a visitor sends the **enquiry form**, it goes into the admin panel's **Enquiries** inbox.

---

## Before you start (prerequisites)

1. **A Hostinger Business web hosting plan** (or a Cloud plan). Business and Cloud are the plans that can run Node.js web apps.
2. **Your domain** added to Hostinger, ideally using Hostinger's nameservers so that DNS and subdomains are set up for you.
   If your DNS is somewhere else (for example Cloudflare), you will need to add records there yourself.
3. **Access to the GitHub repository** `Gokulraj002/rth-travel`. You need to be able to sign in to the GitHub account that owns it.
4. **The admin package `backend.zip`**. Your developer makes it with `npm run backend:zip`, which saves it as `dist/backend.zip`.
5. A **password manager** or a safe note. You will create a few long secret codes along the way. Keep them private.

### Make your secret codes now

You need **four** different long random codes. Make them now and keep them in your password manager:

| Name | Used for | Where it goes |
|---|---|---|
| **Database password** | the MySQL user | admin `.env` → `DB_PASS` |
| **Setup token** | one-time "create owner" link | admin `.env` → `SETUP_TOKEN` (deleted afterwards) |
| **Refresh secret** | lets the admin panel refresh the website | admin `.env` → `NEXT_REVALIDATE_SECRET` **and** website → `REVALIDATE_SECRET` (the **same** value in both places) |
| **API key** | lets the website read from the admin panel | admin `.env` → `API_READ_KEY` **and** website → `CMS_API_KEY` (the **same** value in both places) |

Any password generator works. Choose 32 or more characters, letters and numbers only, so nothing gets mangled.
On a Mac you can also open **Terminal** and run `openssl rand -hex 24` once per code.

---

## Part A: The database

### A1. Create the database and its user

1. Log in to **hPanel**, then go to **Websites** and click **Manage** (or **Dashboard**) next to your domain.
   You can use any website on the plan, because databases belong to the whole hosting account.
2. In the left menu, open **Databases → MySQL Databases** (sometimes **Databases → Management**).
3. Under **Create a new MySQL database and database user**:
   - **Database name**: type `rth`. Hostinger adds your prefix, so it becomes `u123456789_rth`.
   - **Username**: type `rth`, which becomes `u123456789_rth`.
   - **Password**: paste your **Database password**.
4. Click **Create**. Write down the full database name and username exactly as hPanel shows them.

> If your screen only asks for a database name, Hostinger creates the user for you. Set its password under
> **Databases → MySQL Users → Change Password**.

### A2. Load the tables and starting content (phpMyAdmin)

The `backend.zip` package contains two files in its `database` folder. You can also open the zip on your computer to get them:

- `schema.sql`: creates the empty tables
- `seed.sql`: fills them with everything the website shows today (destinations, packages, reviews, FAQs, contact details)

1. In **Databases**, click **phpMyAdmin** (or **Enter phpMyAdmin**) next to `u123456789_rth`.
2. In phpMyAdmin, click the database name `u123456789_rth` in the left column.
3. Click the **Import** tab at the top.
4. Click **Choose file**, select **`schema.sql`**, then click **Import** (or **Go**) at the bottom. Wait for the green success message.
5. Repeat for **`seed.sql`**. Order matters: **`schema.sql` first, then `seed.sql`**.
6. Click the database name again. You should see about 16 tables, including `packages`, `destinations` and `enquiries`.

> Warning: `schema.sql` **deletes and recreates** the tables. Only import it on a new, empty database, and never
> on the live database once you have started using the admin panel.

---

## Part B: The admin panel (admin.yourdomain.com)

### B1. Create the admin site for `admin.yourdomain.com`

Your main domain will run the Node.js website, and every website deployment rewrites that site's own folder.
So the admin panel gets **its own website slot** on the subdomain. This is the method Hostinger recommends for standalone apps.

1. In hPanel, go to **Websites** and click **Add website** (or **Create site**).
2. Choose **PHP/HTML website** (it may be called *Custom PHP/HTML website*). If you're asked how to start, choose **Start empty** or **Skip**.
3. When asked for the domain, choose **use an existing domain** and type **`admin.yourdomain.com`**.
4. Finish the wizard. Hostinger creates this folder:
   `/home/u123456789/domains/admin.yourdomain.com/public_html`

> If your DNS is not managed by Hostinger, add an **A record** for `admin`, pointing to your hosting IP address
> (shown in hPanel on the site's dashboard). It can take a few hours to start working.

### B2. Upload and unzip `backend.zip` (no SSH needed)

`backend.zip` (made with `npm run backend:zip`) is already laid out exactly like a Hostinger site folder.
After unzipping, only `public_html` is visible to the internet — the program code, database files, logs and your
private `.env` settings sit **next to** it, where nobody can open them in a browser:

```
/home/u123456789/domains/admin.yourdomain.com/
├── .env              ← you create this in step B4 (private settings)
├── .env.example      ← template for .env
├── app/              ← program code            (private)
├── bin/              ← helper scripts          (private)
├── config/           ←                         (private)
├── database/         ← schema.sql, seed.sql    (private)
├── storage/          ← logs and cache          (private)
└── public_html/      ← the ONLY public folder: index.php, assets/, uploads/
```

1. On the admin site's dashboard (**Websites → admin.yourdomain.com → Manage**), open **Files → File Manager**.
2. File Manager opens inside `public_html`. **Delete the default files** Hostinger put there (for example
   `default.php` or `index.html`), so the folder is empty.
3. Go **one level up** to `domains/admin.yourdomain.com` (click the folder name in the path bar, or the "up" arrow).
4. Click **Upload** (the arrow icon) and upload **`backend.zip`** into this folder.
5. Right-click `backend.zip` → **Extract** → extract into the **current folder** (`domains/admin.yourdomain.com`).
   If asked whether to overwrite/merge `public_html`, choose **Yes / Merge**.
6. Check that you now see `app`, `database`, `storage` … **next to** `public_html`, and that `public_html` contains
   `index.php`, `assets` and `uploads`. Then delete `backend.zip`.

> Showing hidden files: `.env.example` and `.htaccess` begin with a dot. If you can't see them, turn on
> **Show hidden files** in File Manager's settings (gear icon).

### B3. Check the private folders are really private

Open these addresses in your browser — each must show **Not Found / Forbidden**, never file contents:
`https://admin.yourdomain.com/.env` · `https://admin.yourdomain.com/../.env` · `https://admin.yourdomain.com/app/routes.php`.
(They should, because those files are outside `public_html`; `public_html/.htaccess` also blocks every dotfile.)

### B4. Create the settings file `.env`

1. In File Manager, open the site folder **`domains/admin.yourdomain.com`** (one level above `public_html`).
2. Right-click **`.env.example`**, choose **Copy**, and copy it into the same folder. Rename the copy to **`.env`**
   (dot, e, n, v, with nothing after it).
3. Right-click `.env` and choose **Edit**. Fill in the values below, then **Save**.

| Setting | What to put | Example |
|---|---|---|
| `APP_DEBUG` | **`false`** on the live site. (`true` shows technical error details to everyone, so only use it briefly while fixing a problem.) | `false` |
| `APP_TIMEZONE` | Your time zone (used for dates in the inbox). | `Asia/Kolkata` |
| `APP_URL` | The admin panel's address, with no `/` at the end. | `https://admin.yourdomain.com` |
| `APP_BASE_PATH` | Leave **empty**. (It's only for installs in a sub-folder such as `/admin`.) | |
| `WEBSITE_URL` | Your public website address. Used for photo previews and the "View website" link. | `https://yourdomain.com` |
| `DB_HOST` | **`localhost`** on Hostinger. | `localhost` |
| `DB_PORT` | `3306` | `3306` |
| `DB_NAME` | The full database name from step A1. | `u123456789_rth` |
| `DB_USER` | The full database username from step A1. | `u123456789_rth` |
| `DB_PASS` | Your **Database password**. | |
| `DB_SOCKET` | Leave empty. | |
| `SESSION_LIFETIME_MINUTES` | How long you stay signed in while idle, in minutes. | `240` |
| `SESSION_SECURE` | `true` (cookies only over HTTPS). | `true` |
| `TRUST_PROXY` | `false`, unless you later put Cloudflare's proxy in front of the admin site. | `false` |
| `SETUP_TOKEN` | Your **Setup token** (16 or more characters). It's used once in step B7 and then **deleted**. | |
| `API_ALLOWED_ORIGINS` | The website addresses allowed to send the enquiry form, separated by commas. | `https://yourdomain.com,https://www.yourdomain.com` |
| `API_READ_KEY` | Your **API key**. It must be identical to `CMS_API_KEY` on the website (step C2). *If this line is not in your file yet, add it.* | |
| `NEXT_REVALIDATE_URL` | The website's refresh address. | `https://yourdomain.com/api/revalidate` |
| `NEXT_REVALIDATE_SECRET` | Your **Refresh secret**. It must be identical to `REVALIDATE_SECRET` on the website (step C2). | |
| `UPLOAD_MAX_MB` | The largest photo you can upload, in MB. Keep it **below** the PHP upload limit set in B6. | `8` |
| `ENQUIRY_NOTIFY_EMAIL` | Optional. An email address that receives a message for every new enquiry. Leave empty for none. | `bookings@yourdomain.com` |
| `MAIL_FROM` | The sender address for those emails. It should be an address on your domain. | `no-reply@yourdomain.com` |

> **Keep `.env` private.** Never email it, share a screenshot of it, or upload it to GitHub. It holds your database password and secrets.

### B5. Folder permissions

The admin panel must be able to write log files and save uploaded photos.

1. In File Manager (in `domains/admin.yourdomain.com`), right-click **`storage`**, choose **Permissions**, set **755**, and tick
   **apply to sub-folders** (recursive) if offered. Do the same for **`storage/logs`** and **`storage/cache`**.
2. Do the same for **`public_html/uploads`** (**755**).
3. Right-click **`.env`** (next to `public_html`) and set **600** (or **640**), so only your account can read it. If the admin later
   shows an error about the database, set it back to **644**.

On Hostinger the website runs as your own user, so **755** is enough. Never use 777.

### B6. PHP version and extensions

1. On the admin site's dashboard, open **Advanced → PHP Configuration** (or just **PHP**).
2. **PHP version** tab: choose **8.2** or newer (8.3 or 8.4 are fine too), then click **Save**.
3. **PHP extensions** tab: make sure these are **ticked**, then **Save**:
   - `pdo_mysql` (and `pdo`, `mysqlnd`): database
   - `gd`: resizes photos and converts them to WebP
   - `fileinfo`: checks that uploads really are images
   - `curl`: lets the admin panel refresh the website
   - `mbstring`: handles text in any language
4. **PHP options** tab: set these values, then **Save**:
   - `upload_max_filesize` = **10M**
   - `post_max_size` = **12M**
   - `max_execution_time` = **60**
   - `memory_limit` = **256M**
   - `display_errors` = **Off**

### B7. Turn on SSL (the padlock)

1. On the admin site's dashboard, open **Security → SSL**.
2. If the status isn't **Active**, click **Install SSL** next to `admin.yourdomain.com`. It takes a few minutes.
3. If offered, switch on **Force HTTPS**. (The admin panel also forces HTTPS by itself.)

> SSL can only be issued once `admin.yourdomain.com` points to Hostinger. If it fails, wait an hour for DNS and try again.

### B8. Create the owner account (you)

Choose **one** of these two ways.

**Way 1, in the browser (easiest)**

1. Open `https://admin.yourdomain.com/setup?token=YOUR-SETUP-TOKEN`, replacing `YOUR-SETUP-TOKEN` with the value of `SETUP_TOKEN` from `.env`.
2. Enter your name, email and a strong password (at least 10 characters; 16 or more is better), then click create.
3. You're sent to the sign-in page. Sign in.
4. **Important:** edit `.env` again, **delete the value after `SETUP_TOKEN=`** so the line reads `SETUP_TOKEN=`, and save.

The setup page only works while there are **no** admin accounts yet **and** the token matches. Afterwards it shows "Not found". That's expected.

**Way 2, over SSH**

In the SSH window from step B3, run:

```
cd ~/domains/admin.yourdomain.com/backend
php -v
php bin/create-admin.php you@yourdomain.com "Your Name" owner
```

`php -v` must show **8.2 or newer**. The last command asks for a password (nothing appears while you type) and
prints `Created admin …`. Run the same command again later if you ever need to reset a forgotten password.

You can add staff later in the admin panel under **Admin users**. Give staff the **Editor** role; only the owner can manage users.

---

## Part C: The website (yourdomain.com) as a Node.js web app

### C1. Create the Node.js web app from GitHub

> If `yourdomain.com` is already on your plan as another website (for example an old WordPress site),
> **back it up** and **remove that website** first. The Node.js wizard needs a free slot for the domain.
> When removing it, keep the email accounts if hPanel asks.

1. In hPanel, go to **Websites → Add website** (or **Create site**) and choose **Node.js web app** (or **Web App**).
2. Choose your domain **`yourdomain.com`**. You can also start on the free temporary `…hostingersite.com` address and connect the domain later.
3. Choose **Import your Git repository**, click **Connect with GitHub**, and approve the **Hostinger** GitHub app.
   Give it access to the repository **`Gokulraj002/rth-travel`**.
4. Select **`rth-travel`** in the list (use **Refresh repositories** if it isn't there).
5. Check the **build settings**. Hostinger fills most of them in:

| Setting | Value |
|---|---|
| Framework preset | **Next.js** |
| Branch | **`main`** |
| Node.js version | **22** (recommended; 20 also works, but 18 is too old for this site) |
| Root directory | `/` (leave as is) |
| Package manager | **npm** |
| Build command | **`npm run build`** (this already runs `next build --webpack`, which Hostinger requires for Next.js 16.3.3+ — see their Turbopack article) |
| Output directory | **`.next`** |
| Entry file / start command | leave as is. Hostinger starts Next.js for you. |

Behind the scenes, Hostinger builds the site in Next.js "standalone" server mode and reads our `next.config.ts`
(TypeScript config files are supported on Next.js 15 and newer; this site uses Next.js 16).

6. **Before clicking Deploy**, add the environment variables from step C2 (click **Add** under *Environment variables*).
   Some of them are needed **during** the build.
7. Click **Deploy**. The first build takes a few minutes. Wait for **Deployment completed!**

From now on, every change pushed to the `main` branch on GitHub redeploys the website automatically.

### C2. Website environment variables

You can add these during setup (step C1.6) or later on the website's dashboard under **Environment variables**.
You can also use **Import .env** to upload them as a file. **Saving them redeploys the site automatically.**

| Name | Value | Notes |
|---|---|---|
| `CMS_API_URL` | `https://admin.yourdomain.com` | The admin panel address, **without** `/api/v1` and without a `/` at the end. The website reads its content from here (server side). |
| `CMS_API_KEY` | your **API key** | Must be **exactly** the same as `API_READ_KEY` in the admin `.env`. |
| `NEXT_PUBLIC_CMS_API_URL` | `https://admin.yourdomain.com` | Same address as above (no `/api/v1`). Used by visitors' browsers to send the enquiry form. Needed **at build time**. |
| `CMS_MEDIA_URL` | `https://admin.yourdomain.com` | Where photos uploaded in the admin panel live (the `/uploads/…` files). Needed **at build time**, so the website is allowed to show those photos. |
| `REVALIDATE_SECRET` | your **Refresh secret** | Must be **exactly** the same as `NEXT_REVALIDATE_SECRET` in the admin `.env`. |

> If you change a variable whose name starts with `NEXT_PUBLIC_`, or `CMS_MEDIA_URL`, make sure a **new deployment**
> runs after you save. Check the website's **Deployments** page.

### C3. Connect the domain and SSL

1. If you started on a temporary address, open the website's dashboard and use **Connect domain** (or **Domains**) to attach `yourdomain.com`.
   With Hostinger nameservers, the DNS is set up for you. Otherwise, point the domain's **A record** to the IP shown in hPanel.
2. Open **Security → SSL** on the website's dashboard. If `yourdomain.com` (and `www.yourdomain.com`) aren't **Active**, click **Install SSL**.
3. Open `https://yourdomain.com`. You should see the site with a padlock.

---

## Part D: Check that everything works

Work through this list in order. Each step should pass before you move on.

- [ ] **Admin API is alive:** open `https://admin.yourdomain.com/api/v1/health`. You should see `{"ok":true,"time":"…"}`.
  If you see `"ok":false`, the database settings in `.env` are wrong (see Part F).
- [ ] **Content comes out:** open `https://admin.yourdomain.com/api/v1/packages`. You should see a list of packages.
- [ ] **Private files are hidden:** `https://admin.yourdomain.com/.env` must show **403 / Not Found**, and never your settings.
- [ ] **Admin sign-in:** open `https://admin.yourdomain.com`, sign in, and you see the **Dashboard**.
- [ ] **Setup link is closed:** `https://admin.yourdomain.com/setup?token=…` now says **Not found**, and `SETUP_TOKEN=` is empty in `.env`.
- [ ] **Live price change:** in the admin panel, open **Packages**, edit any package, change **Starting price** (for example 15999 to 15499), and click **Save**.
  Then open that package on `https://yourdomain.com/packages/…` and refresh. The new price should appear **within a few seconds**.
  Change it back afterwards.
- [ ] **Photo upload:** edit a package, upload a photo, save, and check the photo shows on the website.
- [ ] **Enquiry form:** send a test enquiry from `https://yourdomain.com/contact`. It appears under **Enquiries** in the admin panel. Delete it afterwards.
- [ ] **"Update website now":** click that button in the admin sidebar. You should see *"The website has been refreshed — changes are live."*

---

## Part E: Day-to-day use

**Signing in:** go to `https://admin.yourdomain.com`. For safety, after 5 wrong passwords the account is paused for 15 minutes.

**Add a package**
1. **Packages → Add package.**
2. Fill in the title, nights and days, **Starting price** (in ₹, per person), region, categories and destinations.
3. Add highlights, inclusions, exclusions and the day-by-day itinerary. Use the **+** buttons to add rows and drag rows to reorder them.
4. Upload the main photo.
5. Set the status to **Published** to show it on the website, or **Draft** to keep it hidden while you work on it.
6. **Save.** The website updates within seconds. Use **Duplicate** on a similar package to save typing.

**Change the order:** on the Packages, Destinations, Reviews or FAQs lists, drag rows up and down. The website follows the same order.

**Photos**
- Use JPG, PNG or WebP files up to **8 MB**. Large photos are resized automatically and saved in the fast WebP format.
- Landscape photos about **1600–2000 px wide** look best.
- Only upload photos you own or have permission to use.

**Enquiries inbox**
- Every website enquiry lands in **Enquiries**, newest first. Open one to see the details and to call or WhatsApp the customer.
- Set the status (*New → Contacted → Quoted → Booked → Closed*) and add private notes, so the whole team knows where each lead stands.
- Use **Download CSV** to open the list in Excel or Google Sheets.
- If `ENQUIRY_NOTIFY_EMAIL` is set in `.env`, you also get an email for each new enquiry. Check your spam folder the first time.

**Reviews, FAQs and site settings:** phone numbers, WhatsApp number, emails, addresses and social links live under
**Site settings**. A change there updates the whole website.

**"Update website now" button:** saves already refresh the website automatically. Use this button if a change
doesn't seem to appear. (The website also refreshes itself every few minutes as a backup.)

**Backups:** do this weekly, and always before big changes.
1. hPanel → your website → **Files → Backups** (or just **Backups**).
2. Hostinger makes automatic backups (weekly or daily, depending on your plan). You can also click **Generate backup**
   (once per 24 hours) and **Download** a copy to your computer.
3. For a quick database-only copy: **phpMyAdmin → select `u123456789_rth` → Export → Go**. Keep the `.sql` file safe.
4. Restoring a backup **overwrites** what's there now, so download a fresh copy first.
5. Keep a copy of your `.env` in your password manager (not in email).

**Updating the admin software** (when your developer sends a new `backend.zip`): back up first, then upload and extract
it into the same `domains/admin.yourdomain.com` folder, overwriting the existing files. Your `.env`, uploaded photos and logs are not in the zip, so they are kept.

---

## Part F: Troubleshooting

| What you see | Likely cause | What to do |
|---|---|---|
| **500 / "Something went wrong"** on the admin site | A PHP error | Open `storage/logs/php-error.log` (next to `public_html`) in File Manager and read the last lines. Common causes: a typo in `.env`, PHP older than 8.2 (B6), a missing extension (B6), or `storage/` not writable (B5). For a few minutes only, you can set `APP_DEBUG=true` to see the error on screen, then set it back to `false`. |
| `/api/v1/health` shows `"ok":false` or "Database unavailable" | Wrong database settings | Check `DB_NAME`, `DB_USER` and `DB_PASS` in `.env` against hPanel (including the `u123456789_` prefix). `DB_HOST` must be `localhost`. Reset the user's password in hPanel if unsure. |
| Tables missing / "Table … doesn't exist" | SQL not imported | Repeat A2: import `schema.sql`, then `seed.sql`. |
| **419 "Your session expired"** | You were signed out, or the page was open too long before saving | Go back, **refresh the page**, and save again. If it happens constantly, check that you use `https://` and that `APP_URL` matches the address in your browser exactly. |
| **403 Forbidden** on the whole admin site | `public_html` is empty, or the PHP site was not created | Check `public_html` contains `index.php`, `.htaccess` and `assets/` (step B2). Show hidden files to see `.htaccess`. |
| **404** for `/setup?token=…` | Normal once an admin exists, or the token is wrong/short | Check `SETUP_TOKEN` in `.env` (at least 16 characters, copied exactly), or create the owner over SSH (B8, Way 2). |
| **Photos uploaded in the admin don't show on the website** | The website isn't allowed to load images from the admin address | Check `CMS_MEDIA_URL` = `https://admin.yourdomain.com` on the website (C2), and redeploy so `next.config.ts` (`images.remotePatterns`) picks it up. Check that `APP_URL` in `.env` is correct and that the photo opens directly at `https://admin.yourdomain.com/uploads/…`. |
| **Changes don't appear on the website** | The refresh call fails | `NEXT_REVALIDATE_SECRET` (admin) and `REVALIDATE_SECRET` (website) must be **identical**. `NEXT_REVALIDATE_URL` must be `https://yourdomain.com/api/revalidate`. Click **Update website now**: a red message means the website couldn't be reached. Details are in `php-error.log` ("Revalidate failed"). Also check the item is **Published**, not Draft. |
| **Website shows old or sample content everywhere** | The website can't read the API | Check `CMS_API_URL` and `CMS_API_KEY` (= `API_READ_KEY`) on the website, and open `https://admin.yourdomain.com/api/v1/health`. |
| **Enquiry form fails** | Browser blocked by the API, or the wrong API address | `API_ALLOWED_ORIGINS` in `.env` must list the exact website address(es), including `https://` and with/without `www`. Check `NEXT_PUBLIC_CMS_API_URL`, then redeploy. More than 5 enquiries in 10 minutes from one connection are refused on purpose. |
| **"The image is too large"** when uploading | File bigger than the limits | Use a smaller photo (under 8 MB). Or raise `upload_max_filesize` / `post_max_size` in **PHP Configuration → PHP options** (B6), and keep `UPLOAD_MAX_MB` in `.env` **lower** than `upload_max_filesize`. |
| **"Too many failed attempts"** at sign-in | 5 wrong passwords | Wait 15 minutes, or reset the password over SSH: `php bin/create-admin.php you@yourdomain.com "Your Name" owner`. |
| **Website build fails** on Hostinger (Deployments → build log) | Usually a temporary build-server problem | Click **Redeploy** once. The site already builds with webpack (`npm run build` = `next build --webpack`), as Hostinger recommends for Next.js 16 — so a `TurbopackInternalError` means the build command was changed; set it back to `npm run build`. Otherwise send the build log to your developer. |
| **Website shows "Service unavailable" / blank** | App crashed or a missing env var | Website dashboard → **Runtime logs**. Check every variable in C2 is set, then **Restart** or **Redeploy**. |

---

## Security checklist

- [ ] `APP_DEBUG=false` in `.env`.
- [ ] `SETUP_TOKEN=` is **empty** after the owner account was created.
- [ ] Strong, **unique** passwords for hPanel, the database and every admin user (16+ characters, from a password manager).
- [ ] Two-factor authentication turned on for your **Hostinger** and **GitHub** accounts.
- [ ] `.env` is **private**: not shared, not in GitHub, permission 600/640, and only next to `public_html`, never inside it.
- [ ] `https://admin.yourdomain.com/.env` and `/storage/logs/php-error.log` show 403/404.
- [ ] SSL **Active** on both `yourdomain.com` and `admin.yourdomain.com`.
- [ ] Staff accounts use the **Editor** role. Disable or delete accounts of people who leave (**Admin users**).
- [ ] Regular backups (Part E), with a downloaded copy kept somewhere other than Hostinger.
- [ ] The refresh secret and API key are long random values and the same on both sides. If one ever leaks, change it in **both** places.
- [ ] Delete `public_html.old` (B3) and any leftover `backend.zip` from the server.

---

## Hostinger help pages used for this guide

- Node.js web apps: [How to add a Node.js web app](https://www.hostinger.com/support/how-to-deploy-a-nodejs-website-in-hostinger/) ·
  [Creating a Node.js app](https://docs.hostinger.com/node.js/creating-an-app) ·
  [Build settings](https://docs.hostinger.com/node.js/build-settings) ·
  [Next.js on Hostinger](https://docs.hostinger.com/node.js/overview-1/next) (standalone mode, `next.config.ts` on Next.js 15+) ·
  [Environment variables](https://docs.hostinger.com/node.js/environment-variables) ·
  [File structure](https://docs.hostinger.com/node.js/file-structure) ·
  [Next.js 16 Turbopack build errors](https://www.hostinger.com/support/how-to-fix-next-js-16-turbopack-build-errors-on-hostinger/)
- Websites and subdomains: [How to create a subdomain](https://www.hostinger.com/support/1583405-how-to-create-and-delete-subdomains-in-hpanel) ·
  [How to add a website](https://www.hostinger.com/support/1583214-how-to-add-a-website-in-hostinger/) ·
  [Subdomains](https://docs.hostinger.com/domains/subdomains)
- [Databases and phpMyAdmin](https://docs.hostinger.com/websites/databases) · [PHP configuration](https://docs.hostinger.com/websites/php) ·
  [SSL certificates](https://docs.hostinger.com/websites/ssl) · [FTP & SSH access](https://docs.hostinger.com/websites/ftp-ssh) ·
  [File Manager](https://docs.hostinger.com/websites/file-manager) · [Backups](https://docs.hostinger.com/websites/backups)
