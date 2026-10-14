# Deploy - DarkStore Nepal

Target: **DirectAdmin** shared host (CloudLinux, PHP 8.4, MariaDB 11.4, no Node.js).

| Item | Value |
|------|-------|
| Domain | https://darkstorenepal.com |
| App root | `~/domains/darkstorenepal.com/app` (git working copy) |
| Docroot | `~/domains/darkstorenepal.com/public_html` |
| PHP CLI | `/usr/local/bin/php` (8.4) |
| Composer | `/usr/local/bin/composer` |

## Layout on the server

```
domains/darkstorenepal.com/
├── app/            # Laravel project (git clone of this repo)
└── public_html/    # web docroot: public/ assets + patched index.php + storage symlink
```

`public_html/index.php` is **not** the repo copy - it is written by the deploy script
and points back at `../app/vendor/autoload.php` and `../app/bootstrap/app.php`.
`public_html/storage` is a symlink to `app/storage/app/public`.

## Deploy a release

On the server:

```bash
bash ~/domains/darkstorenepal.com/app/deploy/deploy.sh
```

What it does: `git reset --hard origin/main` → `composer install --no-dev` →
`artisan migrate --force` → rsync `public/` to the docroot → rebuild `index.php`
→ fix permissions → `artisan optimize`.

## Frontend assets (important)

This host has **no Node.js**, so Vite assets are built locally and committed
(`public/build` is force-added despite `.gitignore`). When frontend sources change:

```bash
npm run build
git add -f public/build
git commit -m "chore: build frontend assets"
git push
```

then run the deploy script on the server.

## Scheduler & queue

Two user cron entries run every minute (installed in the account crontab):

```cron
* * * * * cd ~/domains/darkstorenepal.com/app && /usr/local/bin/php artisan schedule:run >> ~/domains/darkstorenepal.com/logs/cron.log 2>&1
* * * * * cd ~/domains/darkstorenepal.com/app && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=55 >> ~/domains/darkstorenepal.com/logs/queue.log 2>&1
```

`QUEUE_CONNECTION=database`, so the second line drains queued mail/SMS jobs each minute.

## First-time server setup (already applied)

```bash
cd ~/domains/darkstorenepal.com
git clone https://github.com/kankuro12/DarkStoreDharan.git app
# create app/.env from deploy/.env.production.example, set DB + mail + APP_KEY
cd app
/usr/local/bin/php artisan key:generate --force
/usr/local/bin/php artisan migrate --force
bash deploy/deploy.sh   # syncs the docroot
```
