#!/usr/bin/env bash
#
# DarkStore Nepal - production deploy script (DirectAdmin shared host, no Node.js).
#
# Run on the server:
#   bash ~/domains/darkstorenepal.com/app/deploy/deploy.sh
#
# Idempotent: safe to run on every release. Frontend assets are committed to the
# repo (public/build) because this host has no Node.js to run `npm run build`.
# Build locally and commit before deploying when frontend sources change:
#   npm run build && git add -f public/build && git commit -m "chore: build assets"
#
set -euo pipefail

DOMAIN_DIR="${DOMAIN_DIR:-$HOME/domains/darkstorenepal.com}"
APP_DIR="${APP_DIR:-$DOMAIN_DIR/app}"
PUB_DIR="${PUB_DIR:-$DOMAIN_DIR/public_html}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-/usr/local/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

cd "$APP_DIR"

echo "==> Pulling origin/$BRANCH"
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "==> Installing PHP dependencies"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

echo "==> Running migrations"
"$PHP_BIN" artisan migrate --force

echo "==> Syncing public/ into docroot (assets, .htaccess, robots, favicon)"
# index.php is written separately (it points at ../app); storage is a symlink.
rsync -a --exclude 'index.php' --exclude 'storage' "$APP_DIR/public/" "$PUB_DIR/"

echo "==> Ensuring storage symlink"
if [ ! -L "$PUB_DIR/storage" ]; then
    ln -sfn "$APP_DIR/storage/app/public" "$PUB_DIR/storage"
fi

echo "==> Writing docroot bootstrap (index.php -> ../app)"
cat > "$PUB_DIR/index.php" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__ . '/../app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__ . '/../app/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__ . '/../app/bootstrap/app.php';

$app->handleRequest(Request::capture());
PHP

echo "==> Fixing permissions"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 755 "$PUB_DIR"

echo "==> Caching config / routes / views"
"$PHP_BIN" artisan optimize

echo "==> Deploy complete: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
