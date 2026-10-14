# Production Deployment Report - DarkStore Nepal

**Host:** DirectAdmin shared server (`dacloud.himalayan.host:2222`, account `darkstor`)
**Domain:** https://darkstorenepal.com
**Date:** 2026-10-14
**Status:** Live - storefront and admin panel both responding. No demo/product data seeded (per request); admin adds everything manually.

> Secrets (DB password, mailbox passwords, app admin password) are kept out of the
> repo on purpose. They are in `deploy/CREDENTIALS.local.md` (gitignored) and were
> shared in the chat. Rotate the app admin password after first login.

---

## 1. Server environment (discovered)

| Item | Value |
|------|-------|
| OS / panel | CloudLinux, DirectAdmin Evolution |
| PHP (CLI + web) | 8.4.25, extensions incl. pdo_mysql, mbstring, gd, intl, zip, redis, opcache |
| Database | MariaDB 11.4.13 |
| Composer | /usr/local/bin/composer |
| Git | /usr/bin/git (public clone works) |
| Node / npm | **not installed** |
| Docroot | `~/domains/darkstorenepal.com/public_html` |

## 2. Database

- Created via DirectAdmin **Databases**:
  - Database: `darkstor_darkstore`
  - User: `darkstor_darkstore` (full privileges, localhost)
- All 24 migrations applied (`php artisan migrate --force`).
- Seeders **not** run for products. Only `SettingSeeder` was run (store name / SEO
  defaults the admin edits later).

## 3. Code fixes made to get a clean MySQL migrate

The app migrated fine on local SQLite but hit three MySQL-specific failures. All fixed
and pushed:

1. `2026_09_30_032914_create_inventory_movements_table.php`
   Composite index auto-name was 68 chars; MySQL caps identifiers at 64.
   Fix: explicit name `inv_movements_wh_variant_created_idx`.

2. `2026_09_30_060001_move_customers_to_own_table.php` (drop order)
   MySQL refuses to drop an index still backing a foreign key.
   Fix: drop the `user_id` foreign key **before** dropping its indexes.

3. Same migration (`dropIndex` name mismatch)
   `hasIndex(['user_id'])` matches the FK's implicit index **by column**, but
   `dropIndex(['user_id'])` guesses the name `{table}_user_id_index`, which for a FK
   index is actually `{table}_user_id_foreign`. Fix: resolve the real index name via
   `Schema::getIndexes()` and drop by name.
   Also made the migration re-runnable (guarded the `user_id`/`customer_id` updates
   behind `hasColumn`) since the earlier partial run had already dropped columns.

Commits: `0d0e00a`, `1d226a4`, `dac5686` (plus `e522822` for prebuilt assets).

## 4. Application deployment

- `git clone` of the repo into `~/domains/darkstorenepal.com/app`.
- `composer install --no-dev --optimize-autoloader` (publishes Filament assets).
- `app/.env` written for production: `APP_ENV=production`, `APP_DEBUG=false`,
  `APP_URL=https://darkstorenepal.com`, MySQL connection, `APP_KEY` generated.
- Docroot wired by copying `public/` into `public_html/` and writing a patched
  `index.php` that requires `../app/vendor/autoload.php` + `../app/bootstrap/app.php`.
- `public_html/storage` → `../app/storage/app/public` symlink.
- Caches: `config:cache`, `route:cache`, `view:cache` (via `artisan optimize`).
- Verified: `/` 200, `/admin/login` 200, `/admin` 302, homepage title
  "DarkStore - Instant Local Fulfillment".

### Frontend assets
The host has no Node.js, so `npm run build` was run **locally** and `public/build`
committed (force-added past `.gitignore`). `deploy/deploy.sh` rsyncs it to the docroot.

## 5. Email

Mail accounts created in DirectAdmin (`mail.darkstorenepal.com`, SMTP 587):

| Mailbox | Purpose |
|---------|---------|
| `noreply@darkstorenepal.com` | transactional sender (MAIL_FROM) |
| `admin@darkstorenepal.com` | admin contact |
| `support@darkstorenepal.com` | customer support |
| `darkstor@darkstorenepal.com` | pre-existing default account |

- **DKIM enabled** for `darkstorenepal.com`.
- App mailer: `MAIL_MAILER=sendmail` via `/usr/sbin/sendmail -bs`. A test mail to
  `admin@darkstorenepal.com` returned `MAIL_SENT_OK`. (Local SMTP :587 hung in the
  PHP client, so the local MTA transport is used instead; DKIM signing is done by
  the server MTA either way.)

## 6. HTTPS, permissions, cron

- `http://` → `https://` **301** redirect (also added to `public_html/.htaccess`;
  DA "Force SSL with https redirect" turned on as backup).
- Permissions: `app/storage` + `app/bootstrap/cache` → `775`; `public_html` → `755`.
- Cron (user crontab, every minute):
  - `artisan schedule:run`
  - `artisan queue:work --stop-when-empty --max-time=55` (drains the database queue)

## 7. Admin access

- URL: https://darkstorenepal.com/admin/login
- Account: `admin@darkstorenepal.com` (Super Admin), created via tinker.
- Password: see `deploy/CREDENTIALS.local.md`. **Change it after first login.**

## 8. Manual steps left for the admin (by design)

1. Change the admin password.
2. Add cities, warehouses, categories, products, variants and stock in the panel.
3. Configure payment gateway keys (prepaid) and COD rules in Settings.
4. Set the store logo/favicon/SEO in Settings.
5. Point/replace mailbox passwords if desired.

## 9. Known caveats

- Assets are committed for this Node-less host; wire a CI/build step if one is added.
- Queue runs per-minute via cron (`--stop-when-empty`), not as a long-lived daemon.
- No Redis installed; cache/session/queue all use the database driver.
