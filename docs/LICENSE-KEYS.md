# License keys: selling Pro and Premium (developer only)

This page is for **you, the developer/vendor**, not for the travel agency. It explains how paid plans are unlocked.

## How it works
- Every installation starts on **Basic**. The features of each plan are listed in `backend/config/plans.php`. Change them there.
- A license key is a short text, `RTH1.xxxxx.yyyyy`, **digitally signed** with your private key (Ed25519).
  The admin panel checks the signature with the public key built into `backend/app/Core/License.php`. No internet connection or license server is needed.
- A key is tied to **one domain** (it also covers every sub-domain, e.g. `admin.client.com` for `client.com`), a
  **plan** and, optionally, an **expiry date**. When it expires, the panel falls back to Basic and nothing is deleted.
  The owner sees a reminder 15 days before the expiry date.
- The client pastes the key under **Plan & upgrade** (owner only).

> Be honest with clients and with yourself: the PHP code runs on *their* server, so a determined programmer could edit it
> to skip the check. Signed keys stop casual copying and sharing between sites. They don't stop someone who edits the code.
> Your real protection is the service: updates, support and hosting help.

## One-time setup (already done for this project)
```bash
php backend/bin/license-keypair.php
```
This creates `license-keys/rth-license-private.key` in the project root (gitignored, never uploaded) and writes the
matching public key into `License.php`. **Back up the private key** in your password manager. If you lose it, you cannot
issue keys for existing installs. Run it with `--force` only if the key has leaked: every existing key stops working, so
re-issue keys to all your clients.

## Issue a key after payment
```bash
php backend/bin/license-generate.php --plan=pro --domain=client.com --licensee="Client Travels" --months=12
php backend/bin/license-generate.php --plan=premium --domain=client.com --licensee="Client Travels"   # lifetime
```
Send the printed key to the client by WhatsApp or email. To **renew**, issue a new key with a later expiry date. To **upgrade**,
issue a Premium key. The client pastes it, and it replaces the old one.

## Local testing
On your own computer (PHP built-in server only) you can set `LICENSE_DEV_PLAN=premium` in `backend/.env` to
unlock everything without a key. Real web servers ignore this setting.

## What ships to clients
`npm run backend:zip` leaves out `bin/license-keypair.php` and `bin/license-generate.php`, and the private key lives
outside `backend/`, so neither ever reaches a client's server.

## Selling to other travel agencies
The panel is generic. For a new client, deploy a copy and set their contact details in **Website settings** and
**Business details**. Also set `LICENSE_VENDOR_*` in their `.env` (so "Upgrade" buttons reach you), then issue keys for their domain.
