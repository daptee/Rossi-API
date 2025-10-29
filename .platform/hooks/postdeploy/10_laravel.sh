#!/usr/bin/env bash
set -euo pipefail

cd /var/app/current

# 1) Composer install (si existe composer y el folder vendor no está)
if [ ! -d vendor ]; then
  echo "[postdeploy] WARNING: 'vendor' folder missing in bundle."
  composer install --no-dev --optimize-autoloader
fi

# 2) Use .env file
echo "[postdeploy] copy prod env to .env"
cp .env.prod .env >/dev/null

# 3) Caches de Laravel
echo "[postdeploy] artisan optimize"
php artisan config:cache || true
php artisan route:cache  || true
php artisan view:cache   || true

# 4) Migraciones (producción)
echo "[postdeploy] artisan migrate --force"
php artisan migrate --force || true

# 5) Storage link
php artisan storage:link || true

# 6) Reinciar supervisor si existe (por queues)
if command -v supervisorctl >/dev/null 2>&1; then
  echo "[postdeploy] supervisor reload"
  supervisorctl reread || true
  supervisorctl update || true
  supervisorctl restart all || true
fi

echo "[postdeploy] done"
