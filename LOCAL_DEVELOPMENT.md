# Local Development

## Table of Contents

- Purpose
- Prerequisites
- Installation
- Demo accounts
- Demo workflows
- Commands reference
- Known limitations

## Purpose

This guide gets a freshly-cloned copy of SYIFA.my running locally with
realistic demo data — three working logins (Super Admin, Website Designer,
Clinic Owner), a published clinic Website, a booking, an active subscription
against a real commercial catalogue, and an assigned onboarding job — so a
new developer can explore every dashboard without creating any data by hand.

All demo data is seeded by `database/seeders/DemoSeeder.php`, which refuses
to run outside `APP_ENV=local` (see docs/19_DATABASE_STRATEGY.md's Seed
Philosophy — this data is disposable and must never be treated as production
reference data). It is separate from, and does not touch, the existing
`php artisan syifa:preview:setup` command described in
`docs/38_LOCAL_DEVELOPMENT_PREVIEW.md`, which publishes an unrelated,
ownerless Website purely to preview the public marketing template.

## Prerequisites

- PHP 8.3+ with the `gd` and `pgsql` extensions
- Composer
- Node.js and npm
- PostgreSQL 17, running, with a role and database the app can reach.
  `.env.example` defaults to `DB_USERNAME=postgres`, but a fresh Homebrew
  PostgreSQL install does not create that role automatically — if migration
  fails with `role "postgres" does not exist`, create a local role (or reuse
  your own OS user role, which Homebrew's installer creates by default) and
  point `DB_USERNAME`/`DB_PASSWORD`/`DB_DATABASE` at it instead.
- A Redis-compatible server (Valkey is a drop-in match) running on
  `127.0.0.1:6379` — `SESSION_DRIVER` and `QUEUE_CONNECTION` default to
  `redis`, and the `web` middleware group starts a session on every request,
  so most pages return HTTP 500 until this is running:
  ```bash
  brew install valkey
  brew services start valkey
  ```

## Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

`migrate --seed` runs every migration and then `DemoSeeder`. The seeder is
idempotent — re-running `php artisan db:seed` (or `migrate --seed` again)
does not duplicate any of the demo records; it detects each one already
exists and skips it.

Open `http://127.0.0.1:8000/` (or `http://localhost:8000/`) in a browser —
you'll land on the login page. Pick the "Klinik" / "Pereka" / "Admin" tab and
sign in with the matching demo account below. (If you ever see a minimal
plain-text notice instead of the login form, you're on a host `RootEntryController`
doesn't recognize as the app itself or an authenticated session already
exists — see Known Limitations.)

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Super Admin | `admin@syifa.my` | `password` |
| Website Designer | `designer@syifa.my` | `password` |
| Clinic Owner | `clinic@example.com` | `password` |

### Browser (recommended)

Super Admin and Website Designer both sign in from the login page on the
app's own host (`http://127.0.0.1:8000/`) — pick the matching tab, enter the
email/password above, and submit. This works with `.env.example`'s defaults,
no changes needed.

Clinic Owner signs in from that same localhost page: choose **Klinik**, enter
`clinic@example.com` / `password`, and submit. Local development resolves
`localhost` to the seeded `demo-clinic` routing label through server-owned
configuration. The browser never sends a tenant identifier or routing label.
No `/etc/hosts` change and no insecure-cookie override are required.

This localhost fallback exists only when `APP_ENV=local` (and in automated
tests). Staging and production continue to require the tenant's real,
host-derived admin route. `LOCAL_DEMO_TENANT_ROUTING_LABEL` selects only a
seeded routing label; it is configuration read by the server and is never
accepted from a query string, header, cookie, form field, or Vue state.

### API (curl)

Useful for scripting or testing without a browser:

```bash
# Super Admin / Website Designer — same host as the app itself.
curl -c cookies.txt http://127.0.0.1:8000/operations/health   # obtain a session + CSRF cookie
XSRF=$(grep XSRF-TOKEN cookies.txt | awk '{print $NF}' | python3 -c "import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read()))")
curl -b cookies.txt -c cookies.txt -X POST http://127.0.0.1:8000/api/v1/platform/sessions \
  -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $XSRF" \
  -d '{"email":"admin@syifa.my","password":"password"}'

# Clinic Owner — localhost resolves to the configured demo routing label
# only in APP_ENV=local. No tenant ID or routing label is submitted.
curl -c clinic-cookies.txt http://localhost:8000/operations/health
XSRF=$(grep XSRF-TOKEN clinic-cookies.txt | awk '{print $NF}' | python3 -c "import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read()))")
curl -b clinic-cookies.txt -c clinic-cookies.txt \
  -X POST http://localhost:8000/api/v1/sessions \
  -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $XSRF" \
  -d '{"email":"clinic@example.com","password":"password"}'
```

Once signed in (browser or curl), `/dashboard` lands each role on their own
dashboard — it's a single, shared entry route that resolves the correct view
by role; it is never hardcoded to any one role.

## Demo workflows

Every route below returns the dashboard shell for its role once the matching
account is logged in — this is the exact path DEV-001 required to be
click-through reachable:

**Clinic Owner** (`clinic@example.com`)
`/dashboard` → `/dashboard/website` → `/dashboard/website/content` →
`/dashboard/bookings`

**Website Designer** (`designer@syifa.my`)
`/dashboard` → `/dashboard/onboarding` → `/dashboard/onboarding/{jobId}`

**Super Admin** (`admin@syifa.my`)
`/dashboard` → `/dashboard/tenants` → `/dashboard/billing` →
`/dashboard/billing/subscriptions/{subscriptionId}` →
`/dashboard/commercial` → `/dashboard/commercial/plans/{planId}` →
`/dashboard/commercial/plans/{planId}/offerings/{offeringId}`

The demo tenant ("Klinik Sihat Sejahtera") already has a published Website
(all nine governed sections), one confirmed booking, an active subscription
against a real, activated commercial Plan/Billing Option/Capability/Plan
Offering, a succeeded Payment, and an assigned onboarding job — every
`{id}`-shaped route above resolves to a real row, not a 404.

## Commands reference

| Command | Purpose |
|---|---|
| `php artisan migrate` | Run pending migrations |
| `php artisan migrate --seed` | Migrate and seed demo data (fresh clone) |
| `php artisan migrate:fresh --seed` | Drop all tables, re-migrate, and reseed — the reliable way to get back to a known-clean demo state |
| `php artisan db:seed` | Re-run seeders only (idempotent; safe on an already-seeded database) |
| `php artisan serve` | Start the dev server on `127.0.0.1:8000` |
| `npm run dev` | Vite dev server with hot reload |
| `npm run build` | Production asset build |
| `composer test` | Run the PHPUnit suite |
| `composer format` | Run Laravel Pint |
| `composer format:check` | Check formatting without writing |
| `composer analyse` | Run PHPStan/Larastan |
| `php artisan optimize:clear` | Clear all cached config/routes/views — run this if anything behaves unexpectedly after pulling changes |

## Known limitations

- **`role "postgres" does not exist`** is a common fresh-Homebrew-PostgreSQL
  error, not a bug in this repository — see Prerequisites.
- **Redis/Valkey must be running** before `php artisan serve`, or every page
  behind the `web` middleware group 500s on session start.
- **The localhost Clinic Owner selector is intentionally singular.** It
  resolves only the server-configured `LOCAL_DEMO_TENANT_ROUTING_LABEL`.
  Testing multiple tenants still uses their normal host-based admin routes;
  the application never accepts a browser-supplied tenant selector.
- **The demo seeder is local-only by design.** It silently no-ops outside
  `APP_ENV=local`; it will never run against a staging or production
  database no matter how `migrate --seed` is invoked there.
