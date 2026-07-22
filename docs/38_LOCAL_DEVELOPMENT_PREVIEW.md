# 38. Local Development Preview

## Table of Contents

- Purpose
- Prerequisites
- Environment variables
- Setup commands
- Server command
- Browser URL
- Resetting preview data
- Common errors
- Host mapping requirements
- Asset configuration
- Data isolation confirmation
- Known limitations

## Purpose

This guide describes how to run the Laravel application locally and view the
completed Syifa Essential reference website (Hero, About, Services, Doctors,
Gallery, Testimonials, FAQ, Contact, Booking CTA, Footer) in a browser.

The preview is produced by publishing one deterministic, local-only Website
through the real `Website::publish()` invariants (ADR-019–ADR-024). It does
not bypass `PublishedWebsiteSnapshot`, does not render mutable Website,
Clinic, Service, or Asset state, and is structurally inert outside
`APP_ENV=local`.

## Prerequisites

- PHP 8.5 with the `gd` and `pgsql` extensions
- Composer
- Node.js and npm
- PostgreSQL 17, running, with a local role and database the app can reach.
  `.env.example` defaults to `DB_USERNAME=postgres`, but a fresh Homebrew
  PostgreSQL install does not create that role automatically — if migration
  fails with `role "postgres" does not exist`, create a local role and point
  `DB_USERNAME`/`DB_PASSWORD`/`DB_DATABASE` at it instead (see Common Errors)
- A Redis-compatible server (Valkey is a drop-in match) running on
  `127.0.0.1:6379` — `SESSION_DRIVER` and `QUEUE_CONNECTION` default to
  `redis`, and the `web` middleware group starts a session on every request,
  so the homepage returns HTTP 500 until this is running:
  ```
  brew install valkey
  brew services start valkey
  ```

## Environment variables

`.env.example` already ships with working defaults. These four control the
preview specifically and only ever take effect when `APP_ENV=local`
(`config/public_website_delivery.php` hard-gates on `env('APP_ENV') === 'local'`
before this mapping is even constructed):

| Variable | Purpose | Default in `.env.example` |
|---|---|---|
| `PUBLIC_WEBSITE_PREVIEW_HOST` | The `Host` header the preview Website answers to | `localhost` |
| `PUBLIC_WEBSITE_PREVIEW_WEBSITE_ID` | The WebsiteId the preview setup command publishes | `00000000-0000-4000-8000-900000000002` |
| `PUBLIC_WEBSITE_PREVIEW_SCHEME` | Scheme used to build the preview's absolute URLs | `http` |
| `PUBLIC_WEBSITE_ASSET_ORIGIN` | Origin the delivery layer resolves published Asset URLs against | `http://localhost:8000` |

Do not change `PUBLIC_WEBSITE_PREVIEW_WEBSITE_ID` unless you also change the
matching constant in
`app/Console/Commands/SyifaEssentialPreviewSetupCommand.php` — the two must
agree, or the host will resolve to a WebsiteId nothing was published under and
you will get a 404.

## Setup commands

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan syifa:preview:setup
npm run build
```

`php artisan syifa:preview:setup` is the dedicated, idempotent Artisan command
for this preview (Approach A). It:

1. Refuses to run unless `APP_ENV=local` (`FAILURE` exit code otherwise).
2. Provisions one deterministic local `Tenant` (skips if it already exists).
3. Builds one `Website` for that Tenant using `Website::create()`, attaches a
   generated local gallery image `Asset`, configures Services presentation,
   calls `readyForReview()`, then `publish()` with full content for all nine
   governed Sections — skips entirely if a Website for that Tenant is already
   published.
4. Generates a real local PNG fixture at `public/assets/<preview-asset-id>`
   (only if it does not already exist) so the Gallery image resolves with a
   real `image/png` response instead of a broken image.

Re-running the command is safe: it performs no writes at all once the
Tenant and Website already exist, and the row counts in `websites`,
`website_published_snapshots`, and `website_publication_history` stay at
exactly one each no matter how many times you run it.

## Server command

```bash
php artisan serve
```

This binds to `127.0.0.1:8000` by default. The published Website answers on
whatever `Host` header matches `PUBLIC_WEBSITE_PREVIEW_HOST` (`localhost` by
default), independent of the port, so `http://localhost:8000/` renders
correctly out of the box.

## Browser URL

```
http://localhost:8000/
```

Section anchors are reachable individually as well:
`http://localhost:8000/#about`, `#services`, `#doctors`, `#gallery`,
`#testimonials`, `#faq`, `#contact`, `#booking`.

`/privacy` and `/terms` return `404` by design (see Known Limitations) —
that is existing ADR-024 behavior (*"Until approved production copy is
configured, their routes return 404; test copy cannot become production
policy"*), unchanged by this preview.

## Resetting preview data

The command is idempotent and has no destructive re-run behavior, so there is
normally nothing to reset. To force a completely fresh publish (e.g. after
editing the fixture content in the command itself), remove the preview's rows
and the generated fixture image, then re-run the command:

```bash
psql "$DATABASE_URL_OR_YOUR_LOCAL_DSN" -c "
  delete from website_published_section_contents where website_id = '00000000-0000-4000-8000-900000000002';
  delete from website_publication_history where website_id = '00000000-0000-4000-8000-900000000002';
  delete from website_published_snapshots where website_id = '00000000-0000-4000-8000-900000000002';
  delete from websites where id = '00000000-0000-4000-8000-900000000002';
  delete from tenants where id = '00000000-0000-4000-8000-900000000001';
"
rm -f public/assets/00000000-0000-4000-8000-900000000006
php artisan syifa:preview:setup
```

## Common errors

| Symptom | Cause | Fix |
|---|---|---|
| `SQLSTATE[08006] ... role "postgres" does not exist` | Fresh Homebrew PostgreSQL has no `postgres` role | Create a local role (e.g. `syifa`) and point `DB_USERNAME`/`DB_PASSWORD`/`DB_DATABASE` at it |
| HTTP 500 on every page, log shows `Predis\Connection\ConnectionException: Connection refused [tcp://127.0.0.1:6379]` | Redis/Valkey not running | `brew services start valkey` |
| `syifa:preview:setup` exits with "local-development only" error | `APP_ENV` is not `local` | Only run this command with `APP_ENV=local` in `.env` |
| `GET /` returns 404 | `public_website_delivery.sites` is empty — either `APP_ENV` isn't `local`, `PUBLIC_WEBSITE_PREVIEW_WEBSITE_ID` isn't set, or config is cached | Confirm `.env`, then `php artisan config:clear` |
| Gallery image is a broken image | `public/assets/<preview-asset-id>` fixture missing or `PUBLIC_WEBSITE_ASSET_ORIGIN` misconfigured | Re-run `syifa:preview:setup`; confirm `PUBLIC_WEBSITE_ASSET_ORIGIN` matches the host/port you're browsing on |
| Migration error `relation "website_service_section_items" does not exist` (or similar) | Pending migrations | `php artisan migrate` |

## Host mapping requirements

No `/etc/hosts` entry is required — `localhost` already resolves to the
loopback interface on every supported OS. `PUBLIC_WEBSITE_PREVIEW_HOST` only
needs to change if you prefer a different local hostname (e.g. a custom
`*.test` domain mapped in `/etc/hosts`); the value must match exactly what the
browser sends as the `Host` header (no port — see Known Limitations).

## Asset configuration

Published Assets resolve through `PublicAssetUrlResolverInterface` to
`{PUBLIC_WEBSITE_ASSET_ORIGIN}/assets/{assetId}?purpose={purpose}` — this is
existing, unmodified ADR-024 delivery behavior. For local preview,
`PUBLIC_WEBSITE_ASSET_ORIGIN` points at the Laravel dev server itself
(`http://localhost:8000`), and `syifa:preview:setup` generates one real
800×600 PNG at the exact path Laravel's static file serving needs
(`public/assets/<preview-asset-id>`, no extension, matching the resolver's
extensionless URL). This file is a generated local fixture, is `.gitignore`d
(`/public/assets/`), and is never committed.

## Data isolation confirmation

- The Tenant, Website, and Asset created by this command use fixed,
  obviously-synthetic UUIDs (`...-900000000NNN`) that do not collide with
  any test-suite fixture range or real tenant identity.
- The command refuses to run unless `APP_ENV=local`.
- `config/public_website_delivery.php`'s `'sites'` mapping evaluates to `[]`
  whenever `APP_ENV` is anything other than `local`, regardless of what the
  `PUBLIC_WEBSITE_PREVIEW_*` env vars contain — verified by loading the
  config directly with `APP_ENV=production` and confirming an empty array.
- No production/domain/migration/repository/business-logic code was changed
  to build this preview — only a new, self-contained Console Command, one
  config file's `'sites'` key, `.env`/`.env.example`, and `.gitignore`.

## Known limitations

- **Nav-bar link ports.** `PublicSiteContext` (ADR-024) deliberately builds
  absolute URLs from `scheme://host` only, with no port — correct for real
  deployments on ports 80/443. Symfony's `Request::getHost()` unconditionally
  strips any port from the incoming `Host` header, so this cannot be worked
  around through configuration. Running `php artisan serve` on its default
  port 8000 means the page renders correctly and every section is directly
  reachable by URL (`http://localhost:8000/#about`), but the in-page nav
  bar's own `<a href="http://localhost/...">` links omit the port. For
  byte-perfect self-links, run the dev server on port 80 instead
  (requires elevated privileges on macOS/Linux for ports below 1024):
  `sudo php artisan serve --port=80`, with `PUBLIC_WEBSITE_PREVIEW_HOST=localhost`
  and `PUBLIC_WEBSITE_ASSET_ORIGIN=http://localhost` (no port) in `.env`.
- **Legal pages return 404 by design.** No production-approved Privacy/Terms
  copy is configured anywhere in this repository, and ADR-024 requires that
  to fail closed rather than fabricate placeholder copy. This preview does
  not add any.
